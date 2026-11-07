<?php
/**
 * AI Design Studio - Tool: Run Database Migrations
 */

define('APP_INIT', true);
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/../includes/db.php';

$pdo = get_db();
if (!$pdo) {
    echo "Error: Database connection failed.\n";
    exit(1);
}

// Find migration files in database/migrations or root database/
$migrationFiles = glob(__DIR__ . '/../database/migrations/*.sql');
if (empty($migrationFiles)) {
    $migrationFiles = glob(__DIR__ . '/../database/*.sql');
}

echo "Found " . count($migrationFiles) . " migration file(s).\n";
foreach ($migrationFiles as $file) {
    echo "Applying: " . basename($file) . "... ";
    $sql = file_get_contents($file);
    try {
        $pdo->exec($sql);
        echo "[DONE]\n";
    } catch (PDOException $e) {
        echo "[ERROR: " . $e->getMessage() . "]\n";
    }
}
echo "Migration process finished.\n";
