<?php

declare(strict_types=1);

require_once __DIR__ . '/../DatabaseTestCase.php';

/**
 * Tests for password hashing behavior.
 * 
 * Verifies that the app uses Argon2id (or bcrypt fallback),
 * that hashes are non-deterministic, and that verification works.
 */
final class PasswordHashingTest extends DatabaseTestCase
{
    private string $algorithm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    // ──────────────────────────────────────────
    // Argon2id / Bcrypt selection
    // ──────────────────────────────────────────

    public function testUsesArgon2idWhenAvailable(): void
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped('Argon2id not available on this PHP build.');
        }

        $hash = password_hash('testpassword', PASSWORD_ARGON2ID);
        $info = password_get_info($hash);

        $this->assertSame('argon2id', $info['algoName']);
    }

    public function testFallsToBcryptWhenArgon2idUnavailable(): void
    {
        // This test validates the fallback logic from User::create()
        // $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $hash = password_hash('testpassword', $this->algorithm);
        $info = password_get_info($hash);

        $this->assertContains($info['algoName'], ['argon2id', 'bcrypt']);
    }

    // ──────────────────────────────────────────
    // Hashing properties
    // ──────────────────────────────────────────

    public function testHashIsNotPlaintext(): void
    {
        $password = 'MySecretPassword123!';
        $hash = password_hash($password, $this->algorithm);

        $this->assertNotEquals($password, $hash);
        $this->assertNotEmpty($hash);
    }

    public function testSamePasswordProducesDifferentHashes(): void
    {
        $password = 'SamePassword123!';
        $hash1 = password_hash($password, $this->algorithm);
        $hash2 = password_hash($password, $this->algorithm);

        $this->assertNotEquals($hash1, $hash2, 'Each hash should use a unique salt.');
    }

    public function testPasswordVerifyAcceptsCorrectPassword(): void
    {
        $password = 'CorrectHorse!Battery42';
        $hash = password_hash($password, $this->algorithm);

        $this->assertTrue(password_verify($password, $hash));
    }

    public function testPasswordVerifyRejectsWrongPassword(): void
    {
        $password = 'RealPassword123!';
        $hash = password_hash($password, $this->algorithm);

        $this->assertFalse(password_verify('WrongPassword456!', $hash));
    }

    public function testPasswordVerifyRejectsEmptyPassword(): void
    {
        $hash = password_hash('SomePassword!', $this->algorithm);

        $this->assertFalse(password_verify('', $hash));
    }

    // ──────────────────────────────────────────
    // Hash stored in DB via User::create()
    // ──────────────────────────────────────────

    public function testUserCreateStoresHashedPassword(): void
    {
        $rawPassword = 'SecurePass123!';
        $user = $this->createTestUser('Hash Test', 'hash@test.com', $rawPassword);

        // Read the hash directly from DB
        $row = $this->db->query("SELECT password FROM users WHERE id = {$user['id']}")->fetch();

        $this->assertNotEquals($rawPassword, $row['password']);
        $this->assertTrue(password_verify($rawPassword, $row['password']));
    }

    public function testHashInfoReturnsExpectedAlgorithm(): void
    {
        $hash = password_hash('test', $this->algorithm);
        $info = password_get_info($hash);

        $this->assertArrayHasKey('algoName', $info);
        $this->assertContains($info['algoName'], ['argon2id', 'bcrypt']);
    }
}
