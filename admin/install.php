<?php
/**
 * AI Design Studio - Database Installer & Setup Wizard
 * 
 * Automatically initializes database tables from database/schema.sql.
 * Supports both Web UI and CLI execution.
 * 
 * SECURITY WARNING: Delete or rename this file after completing installation!
 */

define('APP_INIT', true);

$configFile = dirname(__DIR__) . '/config.php';
$schemaFile = dirname(__DIR__) . '/database/schema.sql';
$isCli = (php_sapi_name() === 'cli');

$stepResults = [];
$canProceed = true;
$alreadyInstalled = false;

// 1. Check config.php
if (!file_exists($configFile)) {
    $canProceed = false;
    $stepResults[] = [
        'title' => 'Configuration File (config.php)',
        'status' => 'FAIL',
        'message' => 'config.php not found. Please duplicate config.example.php to config.php and enter your MySQL details.'
    ];
} else {
    require_once $configFile;
    $stepResults[] = [
        'title' => 'Configuration File (config.php)',
        'status' => 'PASS',
        'message' => 'Configuration loaded successfully.'
    ];
}

// 2. Test Connection & Create Database
$pdo = null;
if ($canProceed) {
    $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
    $port = defined('DB_PORT') ? DB_PORT : 3306;
    $dbName = defined('DB_NAME') ? DB_NAME : 'ai_design_studio';
    $user = defined('DB_USER') ? DB_USER : 'root';
    $pass = defined('DB_PASS') ? DB_PASS : '';
    $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

    try {
        // Try connecting directly to the server (without specifying DB name first)
        $serverDsn = "mysql:host={$host};port={$port};charset={$charset}";
        $serverPdo = new PDO($serverDsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);

        // Create database if not exists
        $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET {$charset} COLLATE {$charset}_unicode_ci");

        // Now connect to the database
        $dbDsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";
        $pdo = new PDO($dbDsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);

        $stepResults[] = [
            'title' => 'Database Connection & Creation',
            'status' => 'PASS',
            'message' => "Successfully connected to MySQL and verified database '{$dbName}'."
        ];
    } catch (PDOException $e) {
        $canProceed = false;
        $stepResults[] = [
            'title' => 'Database Connection & Creation',
            'status' => 'FAIL',
            'message' => 'Could not connect to MySQL server: ' . $e->getMessage()
        ];
    }
}

// 3. Execute Schema
if ($canProceed && $pdo) {
    if (!file_exists($schemaFile)) {
        $canProceed = false;
        $stepResults[] = [
            'title' => 'Schema File (database/schema.sql)',
            'status' => 'FAIL',
            'message' => 'database/schema.sql is missing.'
        ];
    } else {
        $sql = file_get_contents($schemaFile);
        try {
            // Check if tables already exist
            $existing = $pdo->query("SHOW TABLES LIKE 'projects'")->fetch();
            if ($existing) {
                $alreadyInstalled = true;
            }

            // Split queries by semicolon and execute
            $pdo->exec($sql);

            // Ensure projects table has user_id column (for existing installs migrating to Phase 1.5)
            $userCol = $pdo->query("SHOW COLUMNS FROM `projects` LIKE 'user_id'")->fetch();
            if (!$userCol) {
                $pdo->exec("ALTER TABLE `projects` ADD COLUMN `user_id` INT UNSIGNED NULL AFTER `id`, ADD INDEX `idx_user_id` (`user_id`)");
            }

            // Verify tables
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $requiredTables = ['users', 'password_resets', 'projects', 'generations', 'rate_limits'];
            $missing = array_diff($requiredTables, $tables);

            if (empty($missing)) {
                $stepResults[] = [
                    'title' => 'Table Migration (users, password_resets, projects, generations, rate_limits)',
                    'status' => 'PASS',
                    'message' => 'All Phase 1 & 1.5 database tables were created and verified successfully.' . ($alreadyInstalled ? ' (Existing tables upgraded)' : '')
                ];
            } else {
                $canProceed = false;
                $stepResults[] = [
                    'title' => 'Table Migration',
                    'status' => 'FAIL',
                    'message' => 'Missing expected tables: ' . implode(', ', $missing)
                ];
            }
        } catch (PDOException $e) {
            $canProceed = false;
            $stepResults[] = [
                'title' => 'Table Migration Execution',
                'status' => 'FAIL',
                'message' => 'SQL Execution Error: ' . $e->getMessage()
            ];
        }
    }
}

// 4. Check Storage Directories
$storageDirs = [
    'storage/logs'    => __DIR__ . '/storage/logs',
    'storage/uploads' => __DIR__ . '/storage/uploads'
];

foreach ($storageDirs as $name => $path) {
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
    $isWritable = is_writable($path);
    $stepResults[] = [
        'title'   => "Directory Permissions ({$name})",
        'status'  => $isWritable ? 'PASS' : 'WARN',
        'message' => $isWritable ? 'Directory exists and is writable.' : 'Directory exists but is not writable. Please check folder permissions.'
    ];
}

// CLI Output
if ($isCli) {
    echo "==========================================\n";
    echo " AI Design Studio - Database Installer\n";
    echo "==========================================\n\n";
    foreach ($stepResults as $step) {
        echo "[{$step['status']}] {$step['title']}\n";
        echo "       {$step['message']}\n";
    }
    echo "\n";
    if ($canProceed) {
        echo "SUCCESS: AI Design Studio installed successfully!\n";
        echo "SECURITY WARNING: Please delete install.php before using in production.\n";
    } else {
        echo "FAILED: Please resolve the issues listed above.\n";
    }
    exit($canProceed ? 0 : 1);
}

// Web UI Output
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Design Studio - Installation</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --border-color: #334155;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --color-pass: #10b981;
            --color-fail: #ef4444;
            --color-warn: #f59e0b;
            --color-primary: #3b82f6;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Poppins', 'Cairo', sans-serif;
            background: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .container {
            width: 100%;
            max-width: 680px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }
        .header {
            text-align: center;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1.5rem;
        }
        .header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            background: linear-gradient(135deg, #60a5fa, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }
        .header p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }
        .step-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .step-item {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }
        .badge {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            text-transform: uppercase;
            flex-shrink: 0;
            letter-spacing: 0.5px;
        }
        .badge.PASS { background: rgba(16, 185, 129, 0.2); color: var(--color-pass); border: 1px solid var(--color-pass); }
        .badge.FAIL { background: rgba(239, 68, 68, 0.2); color: var(--color-fail); border: 1px solid var(--color-fail); }
        .badge.WARN { background: rgba(245, 158, 11, 0.2); color: var(--color-warn); border: 1px solid var(--color-warn); }
        .step-content h3 { font-size: 1rem; font-weight: 600; margin-bottom: 0.25rem; }
        .step-content p { font-size: 0.85rem; color: var(--text-muted); line-height: 1.4; }

        .security-alert {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid var(--color-fail);
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 2rem;
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
        .security-alert .icon { font-size: 1.5rem; }
        .security-alert-text { font-size: 0.9rem; color: #fca5a5; line-height: 1.4; }
        .security-alert-text strong { color: #fff; }

        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background: var(--color-primary);
            color: #fff;
        }
        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: var(--text-main);
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.15);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>AI Design Studio</h1>
            <p>Database Installation & Environment Verification</p>
        </div>

        <div class="step-list">
            <?php foreach ($stepResults as $step): ?>
                <div class="step-item">
                    <span class="badge <?= $step['status'] ?>"><?= $step['status'] ?></span>
                    <div class="step-content">
                        <h3><?= htmlspecialchars($step['title']) ?></h3>
                        <p><?= htmlspecialchars($step['message']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($canProceed): ?>
            <div class="security-alert">
                <span class="icon">⚠️</span>
                <div class="security-alert-text">
                    <strong>SECURITY ADVISORY:</strong><br>
                    Installation complete! For security reasons, please <strong>delete</strong> or rename <code>install.php</code> before deploying to production.
                </div>
            </div>

            <div class="actions">
                <a href="../" class="btn btn-primary">Open AI Design Studio &rarr;</a>
                <a href="diagnostics.php?secret=<?= defined('DIAGNOSTICS_SECRET') ? urlencode(DIAGNOSTICS_SECRET) : '' ?>" class="btn btn-secondary">Run Diagnostics</a>
            </div>
        <?php else: ?>
            <div class="actions">
                <a href="install.php" class="btn btn-primary">Retry Installation</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

