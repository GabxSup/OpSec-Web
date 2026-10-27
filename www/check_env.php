<?php

// CLI or Web support
$isCli = php_sapi_name() === 'cli';
$newline = $isCli ? "\n" : "<br>";

function printResult($label, $status, $message = '')
{
    global $isCli, $newline;
    $icon = $status ? '[OK]' : '[FAIL]';
    echo "{$icon} <strong>{$label}</strong>: {$message}{$newline}";
}

echo "<h1>Environment Check</h1>" . ($isCli ? "\n" : "");

// 1. PHP Version
$phpVersion = phpversion();
printResult("PHP Version", version_compare($phpVersion, '8.3.36', '>='), "Current: $phpVersion (Required: 8.3.36+ to patch CVE-2026-109618/19)");

// 2. Extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'openssl', 'mbstring', 'json'];
foreach ($requiredExtensions as $ext) {
    printResult("Extension: $ext", extension_loaded($ext));
}

// 3. Database Connection
echo $newline . "<strong>Checking Database Connection...</strong>" . $newline;
if (file_exists(__DIR__ . '/config/database.php')) {
    require_once __DIR__ . '/config/database.php';
    if (isset($pdo)) {
        printResult("Database Connection", true, "Connected to '$db'");

        // 4. Check Tables
        $tables = ['users', 'activity_logs', 'login_attempts'];
        foreach ($tables as $table) {
            try {
                $result = $pdo->query("SHOW TABLES LIKE '$table'");
                $exists = $result->rowCount() > 0;
                printResult("Table: $table", $exists);
            } catch (PDOException $e) {
                printResult("Table: $table", false, "Error checking: " . $e->getMessage());
            }
        }
    } else {
        printResult("Database Connection", false, "\$pdo variable not found in config/database.php");
    }
} else {
    printResult("Config File", false, "config/database.php not found");
}

echo $newline . "Done.";
