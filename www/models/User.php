<?php

require_once __DIR__ . '/../config/database.php';

class User
{
    private PDO $db;

    public function __construct()
    {
        global $pdo;
        $this->db = $pdo;
        $this->ensureAppleColumnExists();
    }

    private function ensureAppleColumnExists(): void
    {
        try {
            $this->db->exec("ALTER TABLE users ADD COLUMN apple_id VARCHAR(255) NULL AFTER google_id");
            $this->db->exec("CREATE INDEX idx_apple_id ON users(apple_id)");
        } catch (PDOException $e) {
            // Ignorar si la columna o índice ya existen
        }
    }

    public function all(int $limit = 10, int $offset = 0)
    {
        $stmt = $this->db->prepare("SELECT id, name, email FROM users LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }

    public function findByEmail(string $email)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM users WHERE email = :email LIMIT 1"
        );
        $stmt->execute(['email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function find($id)
    {
        $stmt = $this->db->prepare(
            "SELECT id, name, email FROM users WHERE id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create(array $data): bool
    {
        // Basic validations
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            return false;
        }

        // Validar formato de email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Confirm password
        if ($data['password'] !== ($data['password_confirm'] ?? '')) {
            return false;
        }

        // Avoid duplicates
        if ($this->findByEmail($data['email'])) {
            return false;
        }

        // Use ARGON2ID if available, else BCRYPT as secure fallback
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $options = [];
        if ($algo === (defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : 'argon2id')) {
            $options = [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 1
            ];
        }
        $hashedPassword = password_hash($data['password'], $algo, $options);

        // Dynamic query matching the data provided
        $googleId = $data['google_id'] ?? null;
        $appleId = $data['apple_id'] ?? null;

        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password, google_id, apple_id)
            VALUES (:name, :email, :password, :google_id, :apple_id)
        ");
        return $stmt->execute([
            'name' => trim($data['name']),
            'email' => trim($data['email']),
            'password' => $hashedPassword,
            'google_id' => $googleId,
            'apple_id' => $appleId,
        ]);
    }

    public function findByAppleId(string $appleId)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM users WHERE apple_id = :apple_id LIMIT 1"
        );
        $stmt->execute(['apple_id' => $appleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateGoogleId($userId, $googleId)
    {
        $stmt = $this->db->prepare("UPDATE users SET google_id = :google_id WHERE id = :id");
        return $stmt->execute([
            'google_id' => $googleId,
            'id' => $userId
        ]);
    }

    public function updateAppleId($userId, $appleId)
    {
        $stmt = $this->db->prepare("UPDATE users SET apple_id = :apple_id WHERE id = :id");
        return $stmt->execute([
            'apple_id' => $appleId,
            'id' => $userId
        ]);
    }
}

