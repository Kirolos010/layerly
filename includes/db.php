<?php
/**
 * AI Design Studio - Database Connection (PDO)
 * 
 * Provides a secure, persistent PDO connection configured with utf8mb4
 * and strict prepared statements.
 */

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('403 Forbidden - Direct access not allowed.');
}

// Load configuration if not already loaded
if (!defined('DB_HOST')) {
    $configFile = dirname(__DIR__) . '/config.php';
    if (!file_exists($configFile)) {
        http_response_code(500);
        die(json_encode([
            'success' => false,
            'error' => 'Configuration file (config.php) is missing. Please copy config.example.php to config.php and configure your database.'
        ], JSON_UNESCAPED_UNICODE));
    }
    require_once $configFile;
}

/**
 * Get the PDO Database Instance (Singleton)
 * 
 * @return PDO
 * @throws PDOException
 */
function get_db(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
    $port = defined('DB_PORT') ? DB_PORT : 3306;
    $dbName = defined('DB_NAME') ? DB_NAME : 'ai_design_studio';
    $user = defined('DB_USER') ? DB_USER : 'root';
    $pass = defined('DB_PASS') ? DB_PASS : '';
    $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci",
        PDO::ATTR_TIMEOUT            => 5,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Log sensitive connection errors securely
        $logDir = dirname(__DIR__) . '/storage/logs';
        if (is_dir($logDir) && is_writable($logDir)) {
            $logMsg = date('[Y-m-d H:i:s]') . " Database Connection Error: " . $e->getMessage() . PHP_EOL;
            @file_put_contents($logDir . '/db_errors.log', $logMsg, FILE_APPEND);
        }

        if (defined('DEBUG') && DEBUG) {
            throw new PDOException("Database connection failed: " . $e->getMessage(), (int)$e->getCode());
        }

        throw new PDOException("Unable to connect to the database. Please verify configuration.", 500);
    }
}

/**
 * Execute a prepared statement safely
 * 
 * @param string $sql
 * @param array $params
 * @return PDOStatement
 */
function db_query(string $sql, array $params = []): PDOStatement {
    $db = get_db();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

