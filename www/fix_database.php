<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Logger.php';
require_once __DIR__ . '/core/RateLimiter.php';

echo "====== Starting Database Fix ======\n";

try {
    global $pdo;

    if (!$pdo) {
        throw new Exception("Database connection failed. Check config/database.php.");
    }

    // 1. Create Users Table
    echo "[1/3] Checking 'users' table...\n";
    $sql = file_get_contents(__DIR__ . '/migrations/init.sql');
    if ($sql) {
        $pdo->exec($sql);

        // Ensure Index exists for existing tables (won't run if table created afresh)
        try {
            $pdo->exec("CREATE INDEX idx_google_id ON users(google_id)");
        } catch (Exception $e) {
            // Ignore if index already exists
        }

        echo "   -> 'users' table check/creation completed.\n";
    } else {
        echo "   -> ERROR: migrations/init.sql not found or empty.\n";
    }

    // 2. Ensure Logger Table
    echo "[2/3] Checking 'activity_logs' table...\n";
    $logger = new Logger();
    $logger->ensureTableExists();
    echo "   -> 'activity_logs' table verified.\n";

    // 3. Ensure RateLimiter Table
    echo "[3/3] Checking 'login_attempts' table...\n";
    // Creating the object triggers the constructor which calls ensureTableExists
    $limiter = new RateLimiter();
    echo "   -> 'login_attempts' table verified.\n";

    echo "\n====== Database Fixed Successfully! ======\n";

} catch (Exception $e) {
    echo "\n[ERROR] FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
