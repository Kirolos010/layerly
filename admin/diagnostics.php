<?php
/**
 * AI Design Studio - System Diagnostics
 * 
 * Protected by DIAGNOSTICS_SECRET.
 * Verifies PHP version, required extensions, DB connection,
 * outgoing AI API network connectivity, and storage permissions.
 */

defined('APP_INIT') or define('APP_INIT', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$configFile = BASE_PATH . '/config.php';
$hasConfig = file_exists($configFile);
if ($hasConfig) {
    require_once $configFile;
}

$expectedSecret = defined('DIAGNOSTICS_SECRET') ? DIAGNOSTICS_SECRET : null;
$providedSecret = $_GET['secret'] ?? $_POST['secret'] ?? null;

// Verify authorization
$authorized = false;
if (!empty($expectedSecret) && !empty($providedSecret) && hash_equals($expectedSecret, (string)$providedSecret)) {
    $authorized = true;
}

if (!$authorized):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostics Authentication - AI Design Studio</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .box { background: #1e293b; padding: 2rem; border-radius: 12px; border: 1px solid #334155; width: 100%; max-width: 400px; text-align: center; }
        h2 { margin-bottom: 1rem; color: #60a5fa; }
        p { color: #94a3b8; font-size: 0.9rem; margin-bottom: 1.5rem; }
        input[type="password"] { width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid #475569; background: #0f172a; color: #fff; margin-bottom: 1rem; box-sizing: border-box; }
        button { background: #3b82f6; color: #fff; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600; width: 100%; }
        button:hover { background: #2563eb; }
        .error { color: #ef4444; font-size: 0.85rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="box">
        <h2>🔒 Protected Diagnostics</h2>
        <p>Please enter the <code>DIAGNOSTICS_SECRET</code> configured in your <code>config.php</code>.</p>
        <?php if (!empty($providedSecret)): ?>
            <div class="error">Invalid secret. Please check config.php</div>
        <?php endif; ?>
        <form method="POST">
            <input type="password" name="secret" placeholder="Enter secret..." required autofocus>
            <button type="submit">Unlock Diagnostics</button>
        </form>
    </div>
</body>
</html>
<?php
    exit;
endif;

// --- Run Diagnostics Checks ---
$tests = [];

// 1. PHP Version
$phpVersion = PHP_VERSION;
$phpPass = version_compare($phpVersion, '8.0.0', '>=');
$tests[] = [
    'category' => 'PHP Environment',
    'title'    => 'PHP Version (>= 8.0)',
    'status'   => $phpPass ? 'PASS' : 'FAIL',
    'detail'   => "Current version: {$phpVersion}" . ($phpPass ? '' : ' - PHP 8.0 or higher is required.')
];

// 2. Required Extensions
$requiredExts = [
    'curl'      => 'Outgoing AI API requests',
    'pdo_mysql' => 'MySQL database communication',
    'mbstring'  => 'Multilingual UTF-8 & Arabic text handling',
    'fileinfo'  => 'MIME type validation for asset uploads',
    'json'      => 'Structured schema encoding and decoding',
    'openssl'   => 'Encrypted HTTPS API connections'
];

foreach ($requiredExts as $ext => $purpose) {
    $loaded = extension_loaded($ext);
    $tests[] = [
        'category' => 'PHP Extensions',
        'title'    => "Extension: {$ext}",
        'status'   => $loaded ? 'PASS' : 'FAIL',
        'detail'   => $loaded ? "Loaded ({$purpose})" : "MISSING: Required for {$purpose}"
    ];
}

// 3. Database Connection & Tables
$dbPass = false;
$dbDetail = '';
if ($hasConfig) {
    try {
        require_once BASE_PATH . '/includes/db.php';
        $pdo = get_db();
        $dbPass = true;
        
        // Check tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $reqTables = ['users', 'password_resets', 'projects', 'generations', 'rate_limits'];
        $missingTables = array_diff($reqTables, $tables);

        if (empty($missingTables)) {
            $dbDetail = "Connected successfully. Tables verified: " . implode(', ', $tables);
        } else {
            $dbDetail = "Connected, but missing tables: " . implode(', ', $missingTables) . ". Run install.php.";
            $dbPass = false;
        }
    } catch (Exception $e) {
        $dbDetail = "Connection failed: " . $e->getMessage();
    }
} else {
    $dbDetail = "config.php not found. Copy config.example.php to config.php.";
}

$tests[] = [
    'category' => 'Database',
    'title'    => 'MySQL PDO & Schema',
    'status'   => $dbPass ? 'PASS' : 'FAIL',
    'detail'   => $dbDetail
];

// 4. Outbound AI API Connectivity (Google Gemini)
$geminiHost = 'https://generativelanguage.googleapis.com';
$ch = curl_init($geminiHost);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 6,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_SSL_VERIFYPEER => true
]);
$curlRes = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

$aiNetworkPass = ($httpCode > 0 && empty($curlErr));
$tests[] = [
    'category' => 'Network & AI Services',
    'title'    => 'Outbound cURL to Google AI (generativelanguage.googleapis.com)',
    'status'   => $aiNetworkPass ? 'PASS' : 'FAIL',
    'detail'   => $aiNetworkPass 
        ? "Network reachable (HTTP status: {$httpCode}, SSL handshake verified)"
        : "Failed to connect: {$curlErr}"
];

// Gemini Key Check
$geminiKey = defined('GEMINI_API_KEY') ? trim(GEMINI_API_KEY) : '';
$tests[] = [
    'category' => 'Network & AI Services',
    'title'    => 'Gemini API Key Configured',
    'status'   => !empty($geminiKey) ? 'PASS' : 'WARN',
    'detail'   => !empty($geminiKey) 
        ? "Key configured (Length: " . strlen($geminiKey) . " chars, Model: " . (defined('GEMINI_MODEL') ? GEMINI_MODEL : 'default') . ")"
        : "Key is empty. App will use intelligent internal generator until key is set in config.php."
];

// Fallback Provider Check
$fallbackKey = defined('FALLBACK_API_KEY') ? trim(FALLBACK_API_KEY) : '';
$fallbackProvider = defined('FALLBACK_PROVIDER') ? FALLBACK_PROVIDER : 'none';
$tests[] = [
    'category' => 'Network & AI Services',
    'title'    => "Fallback Provider ({$fallbackProvider})",
    'status'   => !empty($fallbackKey) ? 'PASS' : 'INFO',
    'detail'   => !empty($fallbackKey)
        ? "Fallback active for {$fallbackProvider} (Model: " . (defined('FALLBACK_MODEL') ? FALLBACK_MODEL : 'default') . ")"
        : "Fallback provider not configured (optional)."
];

// Phase 6: Gemini Image & Vision Models Check
$geminiImgModel = defined('GEMINI_IMAGE_MODEL') ? GEMINI_IMAGE_MODEL : 'gemini-2.5-flash-image';
$tests[] = [
    'category' => 'Network & AI Services',
    'title'    => "AI Poster Image Model ({$geminiImgModel})",
    'status'   => !empty($geminiKey) ? 'PASS' : 'WARN',
    'detail'   => !empty($geminiKey)
        ? "Configured: {$geminiImgModel}. Automatic inpainting fallback is active if model returns 429 quota."
        : "Gemini API key missing."
];

// Phase 6: PHP GD Inpainting Engine Check
$hasGd = extension_loaded('gd') && function_exists('imagecreatetruecolor') && function_exists('imagefilter');
$tests[] = [
    'category' => 'PHP Extensions',
    'title'    => 'PHP GD Inpainting Engine',
    'status'   => $hasGd ? 'PASS' : 'FAIL',
    'detail'   => $hasGd ? 'GD extension loaded with imagefilter & truecolor support (clean plate fallback ready).' : 'GD missing or lacks imagefilter.'
];

// Phase 6: Poster Rate Limiting & Cache Storage
$posterLimit = defined('POSTER_DAILY_LIMIT') ? POSTER_DAILY_LIMIT : 5;
$cacheDir = BASE_PATH . '/storage/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
$cacheWritable = is_writable($cacheDir);
$tests[] = [
    'category' => 'File Permissions',
    'title'    => 'Directory: storage/cache (Poster Caching)',
    'status'   => $cacheWritable ? 'PASS' : 'FAIL',
    'detail'   => $cacheWritable ? "Writable. Daily poster quota: {$posterLimit} per IP." : 'NOT writable.'
];

// 5. Storage Directory Permissions
$storagePaths = [
    'storage/logs'    => BASE_PATH . '/storage/logs',
    'storage/uploads' => BASE_PATH . '/storage/uploads'
];

foreach ($storagePaths as $rel => $abs) {
    if (!is_dir($abs)) {
        @mkdir($abs, 0755, true);
    }
    $writable = is_writable($abs);
    $tests[] = [
        'category' => 'File Permissions',
        'title'    => "Directory: {$rel}",
        'status'   => $writable ? 'PASS' : 'FAIL',
        'detail'   => $writable ? "Writable ({$abs})" : "NOT writable. Web server needs write permissions."
    ];
}

// Compute total summary
$failCount = count(array_filter($tests, fn($t) => $t['status'] === 'FAIL'));
$warnCount = count(array_filter($tests, fn($t) => $t['status'] === 'WARN'));
$passCount = count(array_filter($tests, fn($t) => $t['status'] === 'PASS'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Design Studio - System Diagnostics</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a;
            --card-bg: #1e293b;
            --border: #334155;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --pass: #10b981;
            --fail: #ef4444;
            --warn: #f59e0b;
            --info: #3b82f6;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 2rem 1rem;
            min-height: 100vh;
        }
        .container {
            max-width: 840px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
        }
        .header h1 {
            font-size: 1.6rem;
            font-weight: 700;
        }
        .summary-pills {
            display: flex;
            gap: 0.75rem;
        }
        .pill {
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .pill-pass { background: rgba(16, 185, 129, 0.2); color: var(--pass); border: 1px solid var(--pass); }
        .pill-fail { background: rgba(239, 68, 68, 0.2); color: var(--fail); border: 1px solid var(--fail); }
        .pill-warn { background: rgba(245, 158, 11, 0.2); color: var(--warn); border: 1px solid var(--warn); }

        .section-title {
            font-size: 1.1rem;
            color: #93c5fd;
            margin: 1.5rem 0 0.75rem 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .test-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .test-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }
        .test-info h4 {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .test-info p {
            font-size: 0.82rem;
            color: var(--text-muted);
            line-height: 1.4;
        }
        .badge {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            text-transform: uppercase;
            flex-shrink: 0;
        }
        .badge-PASS { background: rgba(16, 185, 129, 0.2); color: var(--pass); border: 1px solid var(--pass); }
        .badge-FAIL { background: rgba(239, 68, 68, 0.2); color: var(--fail); border: 1px solid var(--fail); }
        .badge-WARN { background: rgba(245, 158, 11, 0.2); color: var(--warn); border: 1px solid var(--warn); }
        .badge-INFO { background: rgba(59, 130, 246, 0.2); color: var(--info); border: 1px solid var(--info); }

        .footer-nav {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        .btn {
            padding: 0.65rem 1.25rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            background: rgba(255,255,255,0.08);
            color: #fff;
            border: 1px solid var(--border);
            transition: all 0.2s;
        }
        .btn:hover {
            background: rgba(255,255,255,0.15);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>System Diagnostics</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 4px;">Host: <?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost') ?> &bull; PHP <?= PHP_VERSION ?></p>
            </div>
            <div class="summary-pills">
                <span class="pill pill-pass"><?= $passCount ?> Passed</span>
                <?php if ($warnCount > 0): ?>
                    <span class="pill pill-warn"><?= $warnCount ?> Warning</span>
                <?php endif; ?>
                <?php if ($failCount > 0): ?>
                    <span class="pill pill-fail"><?= $failCount ?> Failed</span>
                <?php endif; ?>
            </div>
        </div>

        <?php
        $grouped = [];
        foreach ($tests as $t) {
            $grouped[$t['category']][] = $t;
        }
        foreach ($grouped as $cat => $items):
        ?>
            <div class="section-title"><?= htmlspecialchars($cat) ?></div>
            <div class="test-list">
                <?php foreach ($items as $item): ?>
                    <div class="test-card">
                        <div class="test-info">
                            <h4><?= htmlspecialchars($item['title']) ?></h4>
                            <p><?= htmlspecialchars($item['detail']) ?></p>
                        </div>
                        <span class="badge badge-<?= $item['status'] ?>"><?= $item['status'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <div class="footer-nav">
            <a href="index.php" class="btn">&larr; Return to Studio Test Bench</a>
            <a href="diagnostics.php?secret=<?= urlencode($providedSecret) ?>" class="btn">🔄 Re-run Diagnostics</a>
        </div>
    </div>
</body>
</html>

