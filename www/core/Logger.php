<?php

class Logger
{
    private PDO $db;

    public function __construct()
    {
        global $pdo;
        $this->db = $pdo;
    }

    public function ensureTableExists()
    {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS activity_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NULL,
                    email_attempt VARCHAR(255) NULL,
                    action VARCHAR(50) NOT NULL,
                    ip_address VARCHAR(45) NOT NULL,
                    user_agent VARCHAR(255) NULL,
                    details TEXT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } catch (PDOException $e) {
            // Silently fail or log to file if we can't create the table
            error_log("Logger Error (Create Table): " . $e->getMessage());
        }
    }

    public function log($action, $userId = null, $emailAttempt = null, $details = null)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, email_attempt, action, ip_address, user_agent, details)
                VALUES (:user_id, :email_attempt, :action, :ip_address, :user_agent, :details)
            ");

            $stmt->execute([
                'user_id' => $userId,
                'email_attempt' => $emailAttempt,
                'action' => $action,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255),
                'details' => $details
            ]);
        } catch (PDOException $e) {
            // Si la tabla no existe, intentamos crearla y reintentamos el log (una sola vez)
            if ($e->getCode() === '42S02') {
                $this->ensureTableExists();
                try {
                    // Retry once
                    $stmt = $this->db->prepare("
                        INSERT INTO activity_logs (user_id, email_attempt, action, ip_address, user_agent, details)
                        VALUES (:user_id, :email_attempt, :action, :ip_address, :user_agent, :details)
                    ");
                    $stmt->execute([
                        'user_id' => $userId,
                        'email_attempt' => $emailAttempt,
                        'action' => $action,
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                        'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255),
                        'details' => $details
                    ]);
                } catch (Exception $retryError) {
                    error_log("Logger Fail Retry: " . $retryError->getMessage());
                }
            } else {
                error_log("Logger Fail: " . $e->getMessage());
            }
        }
    }

    public function getUserLogs($userId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM activity_logs 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT 20
            ");
            $stmt->execute(['user_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return []; // Return empty logs if table error
        }
    }
}
