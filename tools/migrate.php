<?php
/**
 * AI Design Studio - Database Migration Runner
 * 
 * Applies unapplied migrations from database/migrations/*.sql in alphabetical order
 * and tracks execution state in the `migrations` table.
 * 
 * Usage:
 *   php tools/migrate.php
 */

define('APP_INIT', true);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$pdo = get_db();
if (!$pdo) {
    echo "Error: Database connection failed. Please check config.php.\n";
    exit(1);
}

// Ensure tracking table exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `migrations` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `migration` VARCHAR(255) NOT NULL UNIQUE,
        `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Fetch applied migrations
$applied = $pdo->query("SELECT `migration` FROM `migrations`")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$appliedSet = array_flip($applied);

// Find migration files in database/migrations
$migrationsDir = dirname(__DIR__) . '/database/migrations';
$files = glob($migrationsDir . '/*.sql') ?: [];
sort($files);

echo "========================================================\n";
echo "       AI Design Studio - Database Migrations           \n";
echo "========================================================\n";

if (empty($files)) {
    echo "No migration files found in {$migrationsDir}.\n";
    exit(0);
}

$pendingCount = 0;
foreach ($files as $file) {
    $filename = basename($file);
    if (!isset($appliedSet[$filename])) {
        $pendingCount++;
    }
}

if ($pendingCount === 0) {
    echo "Database is up to date. " . count($applied) . " migration(s) already applied.\n";
    exit(0);
}

echo "Found {$pendingCount} pending migration(s) to apply:\n\n";

foreach ($files as $file) {
    $filename = basename($file);
    if (isset($appliedSet[$filename])) {
        continue;
    }

    echo "  Applying {$filename}... ";
    $sql = file_get_contents($file);
    
    // Split into individual queries separated by semicolon
    $queries = array_filter(
        array_map('trim', explode(';', $sql)),
        function($q) {
            // Filter out empty lines or pure comment blocks
            $cleaned = preg_replace('/--.*$/m', '', $q);
            return !empty(trim($cleaned));
        }
    );

    try {
        foreach ($queries as $query) {
            $pdo->exec($query);
        }

        $stmt = $pdo->prepare("INSERT INTO `migrations` (`migration`) VALUES (?)");
        $stmt->execute([$filename]);

        echo "[OK]\n";
    } catch (PDOException $e) {
        echo "[FAILED]\n";
        echo "\nError executing {$filename}:\n" . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\nMigration process completed successfully! All migrations applied.\n";

