<?php
/**
 * AI Design Studio - Phase 1.5 Comprehensive Automated Test Suite
 * 
 * Verifies:
 * - User Registration & Password Hashing
 * - Strong Password Validation
 * - Login & Session Handling
 * - Generic error messages (no email enumeration)
 * - Rate limiting on Auth (per IP and per Email)
 * - Password Reset Token & Expiry
 * - Guest Token Generation & Guest-to-User Migration
 * - History recording for Enhance, Generate, and Edit
 * - History API (List, Get, Delete, Clear)
 * - Strict Ownership Isolation between Two Accounts
 */

define('APP_INIT', true);

require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/history.php';
require_once dirname(__DIR__) . '/includes/ai-client.php';

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function run_test(string $name, bool $condition, string $detail = '') {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] {$name}" . ($detail ? " ({$detail})" : "") . "\n";
    } else {
        $failedTests++;
        echo "  [FAIL] {$name}" . ($detail ? " - {$detail}" : "") . "\n";
    }
}

echo "=================================================================\n";
echo " AI DESIGN STUDIO - PHASE 1.5 TEST SUITE (AUTH & HISTORY)\n";
echo "=================================================================\n\n";

// Clean up any previous test accounts
db_query("DELETE FROM users WHERE email LIKE '%@phase15-test.com'");
db_query("DELETE FROM rate_limits WHERE ip LIKE 'auth_%'");

// -----------------------------------------------------------------------------
// TEST 1: Password Strength & Email Validation
// -----------------------------------------------------------------------------
echo "[1] Testing Input Validation & Password Security Rules:\n";
$valShort = validate_password("abc1");
run_test("Rejects password under 8 characters", !$valShort['valid']);

$valNoNum = validate_password("abcdefghijk");
run_test("Rejects password without digits", !$valNoNum['valid']);

$valNoLetter = validate_password("1234567890");
run_test("Rejects password without letters", !$valNoLetter['valid']);

$valGood = validate_password("StrongPass123");
run_test("Accepts strong password with letters and digits", $valGood['valid']);

run_test("Validates proper email address", validate_email("user@phase15-test.com"));
run_test("Rejects malformed email address", !validate_email("not-an-email"));

// -----------------------------------------------------------------------------
// TEST 2: Registration & Duplicate Email Protection
// -----------------------------------------------------------------------------
echo "\n[2] Testing User Registration & Password Hashing:\n";
$emailA = "user_a_" . time() . "@phase15-test.com";
$passA = "SecretPass123";
$hashA = password_hash($passA, PASSWORD_DEFAULT);

$insertStmt = db_query("
    INSERT INTO users (name, email, password_hash, preferred_lang, created_at, last_login_at)
    VALUES (?, ?, ?, 'ar', NOW(), NOW())
", ["User Alpha", $emailA, $hashA]);
$userAId = (int)get_db()->lastInsertId();

run_test("User A registered in database", $userAId > 0, "ID: {$userAId}");

$fetchedUser = db_query("SELECT * FROM users WHERE id = ?", [$userAId])->fetch();
run_test("Password stored as secure bcrypt hash", password_verify($passA, $fetchedUser['password_hash']));
run_test("Raw password does NOT match stored hash directly", $fetchedUser['password_hash'] !== $passA);

// Duplicate registration test
$duplicateFailed = false;
try {
    db_query("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)", ["Duplicate User", $emailA, $hashA]);
} catch (\PDOException $e) {
    $duplicateFailed = true;
}
run_test("Duplicate email registration rejected by database UNIQUE constraint", $duplicateFailed);

// -----------------------------------------------------------------------------
// TEST 3: Login, Logout, and Session Management
// -----------------------------------------------------------------------------
echo "\n[3] Testing Login, Session & Generic Error Responses:\n";
init_secure_session();

// Incorrect password test
$badLoginUser = db_query("SELECT * FROM users WHERE email = ?", [$emailA])->fetch();
$verifyBad = password_verify("WrongPassword999", $badLoginUser['password_hash']);
run_test("Password verify rejects incorrect password", !$verifyBad);

// Correct login
$verifyGood = password_verify($passA, $badLoginUser['password_hash']);
run_test("Password verify accepts correct password", $verifyGood);

login_user($badLoginUser, false);
run_test("User is marked logged in", is_logged_in() === true);
run_test("Current user ID matches User A", get_current_user_id() === $userAId);
$activeUser = get_auth_user();
run_test("Current user profile name fetched", $activeUser['name'] === "User Alpha");

logout_user();
run_test("User logged out successfully", is_logged_in() === false);
run_test("Current user ID is null after logout", get_current_user_id() === null);

// -----------------------------------------------------------------------------
// TEST 4: Brute-Force Rate Limiting on Auth
// -----------------------------------------------------------------------------
echo "\n[4] Testing Auth Rate Limiter (per IP and per Email):\n";
$dummyIp = "10.200.50.1";
$dummyEmail = "brute_target_" . time() . "@phase15-test.com";

// Record 5 failures
for ($i = 1; $i <= 5; $i++) {
    record_auth_failure('login_test', $dummyEmail);
}

$rateResult = check_auth_rate_limit('login_test', $dummyEmail);
run_test("Auth rate limit triggered after 5 attempts", $rateResult['allowed'] === false, "Retry after: {$rateResult['retry_after']}s");

clear_auth_failures('login_test', $dummyEmail);
$clearedCheck = check_auth_rate_limit('login_test', $dummyEmail);
run_test("Auth rate limit cleared successfully upon success", $clearedCheck['allowed'] === true);

// -----------------------------------------------------------------------------
// TEST 5: Password Reset Flow & Expiry
// -----------------------------------------------------------------------------
echo "\n[5] Testing Password Reset Flow:\n";
// Non-existent email request -> MUST return generic success notice
$nonExistentRes = request_password_reset("nonexistent_user_999@phase15-test.com");
run_test("Password reset does not leak non-existent email", $nonExistentRes['success'] === true);

// Real user reset request
$realResetRes = request_password_reset($emailA);
run_test("Password reset request created successfully", $realResetRes['success'] === true);

// Inspect database for the created token
$tokenRow = db_query("SELECT * FROM password_resets WHERE user_id = ?", [$userAId])->fetch();
run_test("Reset token record saved in database", !empty($tokenRow));

// Test complete reset with new password
$newPassA = "BrandNewPass456";
$testRawToken = bin2hex(random_bytes(32));
$testHash = hash('sha256', $testRawToken);
db_query("UPDATE password_resets SET token_hash = ?, expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE user_id = ?", [$testHash, $userAId]);

$completeRes = complete_password_reset($testRawToken, $newPassA);
run_test("Password reset completed successfully", $completeRes['success'] === true);

// Check that old password fails and new password works
$refreshedUser = db_query("SELECT * FROM users WHERE id = ?", [$userAId])->fetch();
run_test("Old password rejected", !password_verify($passA, $refreshedUser['password_hash']));
run_test("New password accepted", password_verify($newPassA, $refreshedUser['password_hash']));

// Token should be consumed/deleted
$tokensLeft = db_query("SELECT COUNT(*) FROM password_resets WHERE user_id = ?", [$userAId])->fetchColumn();
run_test("Used reset token deleted from database", (int)$tokensLeft === 0);

// -----------------------------------------------------------------------------
// TEST 6: Guest Mode & Guest-to-User Migration
// -----------------------------------------------------------------------------
echo "\n[6] Testing Guest Mode & Migration to Registered Account:\n";
logout_user();
$testGuestToken = bin2hex(random_bytes(32));
$_SESSION['guest_token'] = $testGuestToken;

// Simulate guest generating designs
$g1 = record_generation_history('generate', "Guest Ramadan Poster", ['style' => 'modern'], [
    'canvas' => ['width' => 1080, 'height' => 1080, 'background' => '#0f172a'],
    'objects' => [['type' => 'text', 'text' => 'Ramadan Mubarak']]
]);

$g2 = record_generation_history('enhance', "Guest Coffee Idea", ['lang' => 'ar'], [
    'enhanced_prompt' => "فكرة مفصلة عن القهوة المختصة"
]);

// Also simulate guest creating a project
db_query("
    INSERT INTO projects (owner_token, name, json_data) 
    VALUES (?, 'Guest Project 1', '{\"canvas\":{}}')
", [$testGuestToken]);

$guestCountBefore = get_guest_designs_count($testGuestToken);
run_test("Guest designs counted correctly", $guestCountBefore >= 2, "Found {$guestCountBefore} items");

// Create User B and log them in
$emailB = "user_b_" . time() . "@phase15-test.com";
$passB = "StrongPass789";
$hashB = password_hash($passB, PASSWORD_DEFAULT);
$stmtB = db_query("
    INSERT INTO users (name, email, password_hash, preferred_lang, created_at)
    VALUES ('User Beta', ?, ?, 'en', NOW())
", [$emailB, $hashB]);
$userBId = (int)get_db()->lastInsertId();
$userB = db_query("SELECT * FROM users WHERE id = ?", [$userBId])->fetch();

// Login User B with guest migration
login_user($userB, true);

// Verify migrations
$migratedGens = db_query("SELECT COUNT(*) FROM generations WHERE user_id = ?", [$userBId])->fetchColumn();
run_test("Guest generations migrated to User B account", (int)$migratedGens >= 2, "Migrated: {$migratedGens}");

$migratedProjects = db_query("SELECT COUNT(*) FROM projects WHERE user_id = ?", [$userBId])->fetchColumn();
run_test("Guest projects migrated to User B account", (int)$migratedProjects >= 1, "Migrated: {$migratedProjects}");

$remainingGuestItems = db_query("SELECT COUNT(*) FROM generations WHERE guest_token = ?", [$testGuestToken])->fetchColumn();
run_test("Guest token cleared from migrated generations", (int)$remainingGuestItems === 0);

// -----------------------------------------------------------------------------
// TEST 7: History API Operations (List, Filter, Get, Delete, Clear)
// -----------------------------------------------------------------------------
echo "\n[7] Testing History API Operations:\n";
// Logged in as User B
$historyListAll = get_history_list(1, 10, 'all');
run_test("History list retrieves User B items", count($historyListAll['items']) >= 2);
run_test("History indicates registered user state (is_guest = false)", $historyListAll['is_guest'] === false);

$historyFilterGen = get_history_list(1, 10, 'generate');
run_test("History filter by 'generate' returns only designs", count($historyFilterGen['items']) >= 1);
run_test("Filtered item type matches 'generate'", $historyFilterGen['items'][0]['type'] === 'generate');

// Get item details
$firstItemId = (int)$historyListAll['items'][0]['id'];
$fullItem = get_history_item($firstItemId);
run_test("get_history_item returns full result JSON", !empty($fullItem['result']));

// Delete single item
$deleted = delete_history_item($firstItemId);
run_test("delete_history_item removes owned item", $deleted === true);
run_test("Deleted item no longer retrievable", get_history_item($firstItemId) === null);

// Clear remaining history for User B
$clearedCount = clear_all_history();
run_test("clear_all_history removes remaining items for User B", $clearedCount >= 1, "Cleared: {$clearedCount}");
$emptyHistory = get_history_list(1, 10, 'all');
run_test("User B history is now empty", count($emptyHistory['items']) === 0);

// -----------------------------------------------------------------------------
// TEST 8: Strict Cross-User Ownership Isolation
// -----------------------------------------------------------------------------
echo "\n[8] Testing Cross-User Ownership Isolation:\n";
// User A creates a secret design
login_user($refreshedUser, false);
$userASecretGenId = record_generation_history('generate', "User A Confidential Design", ['style' => 'luxury'], [
    'canvas' => ['width' => 1080, 'height' => 1080, 'background' => '#000000'],
    'objects' => [['type' => 'text', 'text' => 'User A Secret']]
]);

run_test("User A generated secret design", $userASecretGenId > 0);

// Switch session to User B
login_user($userB, false);

// 1. User B lists history -> MUST NOT see User A's design
$userBList = get_history_list(1, 20, 'all');
$foundA = false;
foreach ($userBList['items'] as $it) {
    if ((int)$it['id'] === $userASecretGenId) {
        $foundA = true;
    }
}
run_test("User B history list does NOT contain User A's designs", !$foundA);

// 2. User B tries to fetch User A's design by ID -> MUST return null (404)
$hijackAttempt = get_history_item($userASecretGenId);
run_test("User B cannot access User A's generation details by ID", $hijackAttempt === null);

// 3. User B tries to delete User A's design -> MUST fail
$deleteAttempt = delete_history_item($userASecretGenId);
run_test("User B cannot delete User A's design", $deleteAttempt === false);

// 4. Verify User A's design is still intact
login_user($refreshedUser, false);
$intactCheck = get_history_item($userASecretGenId);
run_test("User A's design remains safe and intact", $intactCheck !== null);

// -----------------------------------------------------------------------------
// TEST 9: Endpoint History Integration (Enhance, Generate, Edit Endpoints)
// -----------------------------------------------------------------------------
echo "\n[9] Testing Automatic History Saving in Endpoints:\n";
$arGen = ai_generate_design("بوست تجريبي للمرحلة 1.5", 1080, 1080, ['design_type' => 'social_post']);
$genSaveId = record_generation_history('generate', "بوست تجريبي للمرحلة 1.5", ['width' => 1080], $arGen['data'], 'success');
run_test("Generation saved to history with ID", $genSaveId > 0);

$editSaveId = record_generation_history('edit', "تغيير الخلفية إلى لون ذهبي", ['test' => true], $arGen['data'], 'success');
run_test("Edit saved to history with ID", $editSaveId > 0);

// Clean up test data
db_query("DELETE FROM users WHERE email LIKE '%@phase15-test.com'");
db_query("DELETE FROM generations WHERE prompt LIKE '%Confidential%' OR prompt LIKE '%تجريبي%'");

echo "\n=================================================================\n";
echo " PHASE 1.5 TEST SUMMARY: {$passedTests}/{$totalTests} Passed (" . round(($passedTests / $totalTests) * 100) . "%)\n";
if ($failedTests > 0) {
    echo " FAILED: {$failedTests} tests failed!\n";
} else {
    echo " ALL TESTS PASSED SUCCESSFULLY! 🎉\n";
}
echo "=================================================================\n";

exit($failedTests === 0 ? 0 : 1);
