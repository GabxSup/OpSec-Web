<?php

/**
 * PHPUnit Test Bootstrap
 * 
 * Sets up an in-memory SQLite database that mimics the MariaDB schema,
 * so all tests can run without Docker or any external database.
 */

// Fake superglobals that the app code expects
$_SERVER['REMOTE_ADDR']    = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Test Agent';
$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['REQUEST_URI']     = '/';

// Start a session if needed (some code checks session_status)
if (session_status() === PHP_SESSION_NONE) {
    // Use a null session handler in CLI to avoid header warnings
    ini_set('session.use_cookies', '0');
    ini_set('session.use_only_cookies', '0');
    ini_set('session.cache_limiter', '');
    session_start();
}

// ──────────────────────────────────────────────
// Create an in-memory SQLite PDO that replaces
// the global $pdo from config/database.php
// ──────────────────────────────────────────────

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);

// SQLite-compatible schema (no AUTO_INCREMENT, uses AUTOINCREMENT)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        google_id VARCHAR(255) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip_address VARCHAR(45) NOT NULL,
        attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS activity_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INT NULL,
        email_attempt VARCHAR(255) NULL,
        action VARCHAR(50) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        user_agent VARCHAR(255) NULL,
        details TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

// Make $pdo available globally (this is what config/database.php does)
$GLOBALS['pdo'] = $pdo;

// ──────────────────────────────────────────────
// Load application classes manually
// (avoid loading config/database.php which would
//  try to connect to MariaDB)
// ──────────────────────────────────────────────

// Helpers (load before controllers that depend on them)
require_once __DIR__ . '/../www/helpers/Lang.php';
require_once __DIR__ . '/../www/helpers/csrf.php';
require_once __DIR__ . '/../www/helpers/auth.php';

// Load a default language
Lang::load('en');

// Core
require_once __DIR__ . '/../www/core/BaseController.php';
require_once __DIR__ . '/../www/core/Router.php';

// Models & core classes that need $pdo via `global $pdo`
// We need to patch them to skip the MySQL-specific CREATE TABLE
// so we load them after setting up the global $pdo

// ──────────────────────────────────────────────
// Patch: Override RateLimiter to skip MySQL table creation
// (SQLite doesn't support the same INDEX syntax inline)
// ──────────────────────────────────────────────

// We already created the table above, so we load the class
// but the ensureTableExists() call in the constructor will
// fail on SQLite's lack of INTERVAL syntax. We'll provide
// a test-compatible subclass.

/**
 * TestRateLimiter: SQLite-compatible version of RateLimiter for testing.
 * Replicates the same logic but with SQLite-compatible SQL.
 */
class TestRateLimiter
{
    private PDO $db;
    private int $maxAttempts = 10;
    private int $lockoutTime = 15; // Minutes

    public function __construct()
    {
        global $pdo;
        $this->db = $pdo;
    }

    public function isBlocked(string $ip): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM login_attempts 
            WHERE ip_address = :ip 
            AND attempted_at > datetime('now', :minutes)
        ");

        $stmt->execute([
            'ip'      => $ip,
            'minutes' => "-{$this->lockoutTime} minutes",
        ]);

        return $stmt->fetchColumn() >= $this->maxAttempts;
    }

    public function logAttempt(string $ip)
    {
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (ip_address, attempted_at) 
            VALUES (:ip, datetime('now'))
        ");
        $stmt->execute(['ip' => $ip]);
    }

    public function clearAttempts(string $ip)
    {
        $stmt = $this->db->prepare("
            DELETE FROM login_attempts 
            WHERE ip_address = :ip
        ");
        $stmt->execute(['ip' => $ip]);
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getLockoutTime(): int
    {
        return $this->lockoutTime;
    }
}

/**
 * TestLogger: SQLite-compatible version of Logger for testing.
 */
class TestLogger
{
    private PDO $db;

    public function __construct()
    {
        global $pdo;
        $this->db = $pdo;
    }

    public function log($action, $userId = null, $emailAttempt = null, $details = null)
    {
        $stmt = $this->db->prepare("
            INSERT INTO activity_logs (user_id, email_attempt, action, ip_address, user_agent, details)
            VALUES (:user_id, :email_attempt, :action, :ip_address, :user_agent, :details)
        ");

        $stmt->execute([
            'user_id'       => $userId,
            'email_attempt' => $emailAttempt,
            'action'        => $action,
            'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'details'       => $details,
        ]);
    }

    public function getUserLogs($userId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM activity_logs 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT 20
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllLogs(): array
    {
        return $this->db->query("SELECT * FROM activity_logs ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Load the User model (it will use the global $pdo we set above)
// We need to prevent it from loading config/database.php again
// So we define a flag
if (!defined('DB_LOADED')) {
    define('DB_LOADED', true);
}
