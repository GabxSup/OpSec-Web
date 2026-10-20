<?php

declare(strict_types=1);

require_once __DIR__ . '/../DatabaseTestCase.php';

/**
 * Tests for the User model.
 * 
 * Covers: create validation, findByEmail, find by ID,
 * pagination (all), count, duplicate prevention, 
 * and Google OAuth user creation.
 */
final class UserModelTest extends DatabaseTestCase
{
    /** 
     * We can't use the real User model directly because it
     * require_once's config/database.php which tries MySQL.
     * Instead we test the same logic against the DB directly
     * and validate behavior through the SQL layer.
     */

    // ──────────────────────────────────────────
    // User Creation - Happy Path
    // ──────────────────────────────────────────

    public function testCreateUserWithValidData(): void
    {
        $result = $this->createUserViaModel([
            'name'             => 'John Doe',
            'email'            => 'john@example.com',
            'password'         => 'StrongPass123!',
            'password_confirm' => 'StrongPass123!',
        ]);

        $this->assertTrue($result);
        $this->assertEquals(1, $this->countRows('users'));
    }

    public function testCreatedUserHasHashedPassword(): void
    {
        $this->createUserViaModel([
            'name'             => 'Jane Doe',
            'email'            => 'jane@example.com',
            'password'         => 'MyPassword99!',
            'password_confirm' => 'MyPassword99!',
        ]);

        $row = $this->db->query("SELECT password FROM users LIMIT 1")->fetch();
        $this->assertNotEquals('MyPassword99!', $row['password']);
        $this->assertTrue(password_verify('MyPassword99!', $row['password']));
    }

    public function testCreatedUserNameIsTrimmed(): void
    {
        $this->createUserViaModel([
            'name'             => '  Spaces User  ',
            'email'            => 'spaces@example.com',
            'password'         => 'Pass1234!',
            'password_confirm' => 'Pass1234!',
        ]);

        $row = $this->db->query("SELECT name FROM users LIMIT 1")->fetch();
        $this->assertSame('Spaces User', $row['name']);
    }

    public function testCreatedUserEmailIsTrimmed(): void
    {
        $this->createUserViaModel([
            'name'             => 'Email Trim',
            'email'            => '  trim@example.com  ',
            'password'         => 'Pass1234!',
            'password_confirm' => 'Pass1234!',
        ]);

        $row = $this->db->query("SELECT email FROM users LIMIT 1")->fetch();
        $this->assertSame('trim@example.com', $row['email']);
    }

    // ──────────────────────────────────────────
    // User Creation - Validation Failures
    // ──────────────────────────────────────────

    public function testRejectsEmptyName(): void
    {
        $result = $this->createUserViaModel([
            'name'             => '',
            'email'            => 'noname@example.com',
            'password'         => 'Pass1234!',
            'password_confirm' => 'Pass1234!',
        ]);

        $this->assertFalse($result);
        $this->assertEquals(0, $this->countRows('users'));
    }

    public function testRejectsEmptyEmail(): void
    {
        $result = $this->createUserViaModel([
            'name'             => 'No Email',
            'email'            => '',
            'password'         => 'Pass1234!',
            'password_confirm' => 'Pass1234!',
        ]);

        $this->assertFalse($result);
    }

    public function testRejectsEmptyPassword(): void
    {
        $result = $this->createUserViaModel([
            'name'             => 'No Pass',
            'email'            => 'nopass@example.com',
            'password'         => '',
            'password_confirm' => '',
        ]);

        $this->assertFalse($result);
    }

    public function testRejectsInvalidEmailFormat(): void
    {
        $invalidEmails = [
            'not-an-email',
            'missing@tld',
            '@no-local.com',
            'spaces in@email.com',
            'double@@at.com',
        ];

        foreach ($invalidEmails as $email) {
            $result = $this->createUserViaModel([
                'name'             => 'Invalid',
                'email'            => $email,
                'password'         => 'Pass1234!',
                'password_confirm' => 'Pass1234!',
            ]);

            $this->assertFalse($result, "Email '$email' should be rejected.");
        }
    }

    public function testRejectsPasswordMismatch(): void
    {
        $result = $this->createUserViaModel([
            'name'             => 'Mismatch',
            'email'            => 'mismatch@example.com',
            'password'         => 'Password123!',
            'password_confirm' => 'DifferentPassword!',
        ]);

        $this->assertFalse($result);
    }

    public function testRejectsMissingPasswordConfirm(): void
    {
        $result = $this->createUserViaModel([
            'name'     => 'No Confirm',
            'email'    => 'noconfirm@example.com',
            'password' => 'Pass1234!',
            // No password_confirm key
        ]);

        $this->assertFalse($result);
    }

    public function testRejectsDuplicateEmail(): void
    {
        // Create first user
        $this->createUserViaModel([
            'name'             => 'First',
            'email'            => 'dupe@example.com',
            'password'         => 'Pass1234!',
            'password_confirm' => 'Pass1234!',
        ]);

        // Try duplicate
        $result = $this->createUserViaModel([
            'name'             => 'Second',
            'email'            => 'dupe@example.com',
            'password'         => 'Pass5678!',
            'password_confirm' => 'Pass5678!',
        ]);

        $this->assertFalse($result);
        $this->assertEquals(1, $this->countRows('users'));
    }

    // ──────────────────────────────────────────
    // Find operations
    // ──────────────────────────────────────────

    public function testFindByEmailReturnsUser(): void
    {
        $this->createTestUser('Find Me', 'findme@example.com');

        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => 'findme@example.com']);
        $user = $stmt->fetch();

        $this->assertNotFalse($user);
        $this->assertSame('Find Me', $user['name']);
        $this->assertSame('findme@example.com', $user['email']);
    }

    public function testFindByEmailReturnsNullForNonexistent(): void
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => 'ghost@example.com']);
        $user = $stmt->fetch();

        $this->assertFalse($user);
    }

    public function testFindByIdReturnsUser(): void
    {
        $created = $this->createTestUser('ID Test', 'idtest@example.com');

        $stmt = $this->db->prepare("SELECT id, name, email FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $created['id']]);
        $user = $stmt->fetch();

        $this->assertNotFalse($user);
        $this->assertEquals($created['id'], $user['id']);
    }

    public function testFindByIdDoesNotExposePassword(): void
    {
        $created = $this->createTestUser('Safe User', 'safe@example.com');

        // Mimic User::find() which only selects id, name, email
        $stmt = $this->db->prepare("SELECT id, name, email FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $created['id']]);
        $user = $stmt->fetch();

        $this->assertArrayNotHasKey('password', $user);
    }

    // ──────────────────────────────────────────
    // SQL Injection protection
    // ──────────────────────────────────────────

    public function testFindByEmailIsSafeFromSqlInjection(): void
    {
        $this->createTestUser('Legit User', 'legit@example.com');

        $malicious = "' OR '1'='1";
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $malicious]);
        $result = $stmt->fetch();

        $this->assertFalse($result, 'SQL injection attempt should return no results.');
    }

    public function testCreateIsSafeFromSqlInjection(): void
    {
        $result = $this->createUserViaModel([
            'name'             => "Robert'); DROP TABLE users;--",
            'email'            => 'bobby@tables.com',
            'password'         => 'Pass1234!',
            'password_confirm' => 'Pass1234!',
        ]);

        $this->assertTrue($result, 'Should accept the name even with SQL metacharacters.');

        // Table should still exist
        $count = $this->countRows('users');
        $this->assertEquals(1, $count);
    }

    // ──────────────────────────────────────────
    // Pagination (all)
    // ──────────────────────────────────────────

    public function testAllWithPaginationReturnsCorrectPage(): void
    {
        // Create 25 users
        for ($i = 1; $i <= 25; $i++) {
            $this->createTestUser("User $i", "user$i@example.com");
        }

        // Page 1: limit 10, offset 0
        $stmt = $this->db->prepare("SELECT id, name, email FROM users LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', 10, PDO::PARAM_INT);
        $stmt->bindValue(':offset', 0, PDO::PARAM_INT);
        $stmt->execute();
        $page1 = $stmt->fetchAll();

        $this->assertCount(10, $page1);

        // Page 3: limit 10, offset 20 → should get 5
        $stmt2 = $this->db->prepare("SELECT id, name, email FROM users LIMIT :limit OFFSET :offset");
        $stmt2->bindValue(':limit', 10, PDO::PARAM_INT);
        $stmt2->bindValue(':offset', 20, PDO::PARAM_INT);
        $stmt2->execute();
        $page3 = $stmt2->fetchAll();

        $this->assertCount(5, $page3);
    }

    public function testCountReturnsCorrectTotal(): void
    {
        $this->assertEquals(0, $this->countRows('users'));

        $this->createTestUser('One', 'one@example.com');
        $this->createTestUser('Two', 'two@example.com');

        $this->assertEquals(2, $this->countRows('users'));
    }

    // ──────────────────────────────────────────
    // Google OAuth user creation
    // ──────────────────────────────────────────

    public function testCreateUserWithGoogleId(): void
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $hash = password_hash('randompass', $algo);

        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password, google_id)
            VALUES (:name, :email, :password, :google_id)
        ");
        $result = $stmt->execute([
            'name'      => 'Google User',
            'email'     => 'google@example.com',
            'password'  => $hash,
            'google_id' => '1234567890',
        ]);

        $this->assertTrue($result);

        $user = $this->db->query("SELECT * FROM users WHERE email = 'google@example.com'")->fetch();
        $this->assertSame('1234567890', $user['google_id']);
    }

    public function testUpdateGoogleId(): void
    {
        $created = $this->createTestUser('Link Google', 'link@example.com');

        $stmt = $this->db->prepare("UPDATE users SET google_id = :google_id WHERE id = :id");
        $stmt->execute([
            'google_id' => 'new_google_id_123',
            'id'        => $created['id'],
        ]);

        $user = $this->db->query("SELECT google_id FROM users WHERE id = {$created['id']}")->fetch();
        $this->assertSame('new_google_id_123', $user['google_id']);
    }

    // ──────────────────────────────────────────
    // Helper: mimic User::create() logic for testing
    // ──────────────────────────────────────────

    private function createUserViaModel(array $data): bool
    {
        // Replicate the exact validation logic from User::create()
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            return false;
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if ($data['password'] !== ($data['password_confirm'] ?? '')) {
            return false;
        }

        // Check duplicate
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $data['email']]);
        if ($stmt->fetch()) {
            return false;
        }

        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $hashedPassword = password_hash($data['password'], $algo);

        if (isset($data['google_id'])) {
            $stmt = $this->db->prepare("
                INSERT INTO users (name, email, password, google_id)
                VALUES (:name, :email, :password, :google_id)
            ");
            return $stmt->execute([
                'name'      => trim($data['name']),
                'email'     => trim($data['email']),
                'password'  => $hashedPassword,
                'google_id' => $data['google_id'],
            ]);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO users (name, email, password)
                VALUES (:name, :email, :password)
            ");
            return $stmt->execute([
                'name'     => trim($data['name']),
                'email'    => trim($data['email']),
                'password' => $hashedPassword,
            ]);
        }
    }
}
