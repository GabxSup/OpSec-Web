<?php

class RateLimiter
{
    private PDO $db;
    private int $maxAttempts = 10;
    private int $lockoutTime = 15; // Minutes

    public function __construct()
    {
        global $pdo;
        $this->db = $pdo;
        $this->ensureTableExists();
    }

    private function ensureTableExists()
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (ip_address, attempted_at)
            )
        ");
    }

    public function isBlocked(string $ip): bool
    {
        $minutes = (int) $this->lockoutTime;
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM login_attempts 
            WHERE ip_address = :ip 
            AND attempted_at > (NOW() - INTERVAL {$minutes} MINUTE)
        ");

        $stmt->execute(['ip' => $ip]);

        return $stmt->fetchColumn() >= $this->maxAttempts;
    }

    public function logAttempt(string $ip)
    {
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (ip_address, attempted_at) 
            VALUES (:ip, NOW())
        ");
        $stmt->execute(['ip' => $ip]);
    }

    public function clearAttempts(string $ip)
    {
        // Optional: Clean up old attempts or just specific IP history
        // For strict security, we might only want to clear on successful login if it wasn't a brute force, 
        // but typically successful login resets the counter.
        $stmt = $this->db->prepare("
            DELETE FROM login_attempts 
            WHERE ip_address = :ip
        ");
        $stmt->execute(['ip' => $ip]);
    }
}
