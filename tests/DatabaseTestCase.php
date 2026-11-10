<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Base test case that provides shared utilities for all tests.
 * 
 * - Resets the in-memory database between tests
 * - Provides factory helpers for creating test users
 */
abstract class DatabaseTestCase extends TestCase
{
    protected PDO $db;

    protected function setUp(): void
    {
        global $pdo;
        $this->db = $pdo;

        // Clean all tables before each test
        $this->db->exec("DELETE FROM users");
        $this->db->exec("DELETE FROM login_attempts");
        $this->db->exec("DELETE FROM activity_logs");

        // Reset auto-increment counters (SQLite)
        $this->db->exec("DELETE FROM sqlite_sequence WHERE name='users'");
        $this->db->exec("DELETE FROM sqlite_sequence WHERE name='login_attempts'");
        $this->db->exec("DELETE FROM sqlite_sequence WHERE name='activity_logs'");

        // Reset session between tests
        $_SESSION = [];
    }

    /**
     * Helper: create a test user and return their data.
     */
    protected function createTestUser(
        string $name = 'Test User',
        string $email = 'test@example.com',
        string $password = 'SecurePass123!'
    ): array {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $hash = password_hash($password, $algo);

        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password) VALUES (:name, :email, :password)
        ");
        $stmt->execute([
            'name'     => $name,
            'email'    => $email,
            'password' => $hash,
        ]);

        return [
            'id'       => (int) $this->db->lastInsertId(),
            'name'     => $name,
            'email'    => $email,
            'password' => $hash,
            'raw_password' => $password,
        ];
    }

    /**
     * Helper: count rows in a table.
     */
    protected function countRows(string $table): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    }
}
