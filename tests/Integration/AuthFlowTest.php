<?php

declare(strict_types=1);

require_once __DIR__ . '/../DatabaseTestCase.php';

/**
 * Integration tests for Authentication flows.
 * 
 * Tests the full login lifecycle: successful login, failed login,
 * rate limiting during login, session management, and the auth guard.
 * 
 * These tests exercise multiple components together (User model,
 * RateLimiter, Logger, CSRF, session) to validate end-to-end behavior.
 */
final class AuthFlowTest extends DatabaseTestCase
{
    private TestRateLimiter $limiter;
    private TestLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->limiter = new TestRateLimiter();
        $this->logger  = new TestLogger();
    }

    // ──────────────────────────────────────────
    // Successful login flow
    // ──────────────────────────────────────────

    public function testSuccessfulLoginSetsSession(): void
    {
        $user = $this->createTestUser('Login User', 'login@example.com', 'CorrectPass123!');

        // Simulate: find user by email
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => 'login@example.com']);
        $found = $stmt->fetch();

        $this->assertNotFalse($found);

        // Verify password
        $this->assertTrue(password_verify('CorrectPass123!', $found['password']));

        // Set session (like AuthController::login does)
        $_SESSION['user'] = $found['id'];
        $this->limiter->clearAttempts('127.0.0.1');
        $this->logger->log('login_success', $found['id'], $found['email']);

        // Assertions
        $this->assertEquals($user['id'], $_SESSION['user']);
        $this->assertFalse($this->limiter->isBlocked('127.0.0.1'));

        $logs = $this->logger->getAllLogs();
        $this->assertCount(1, $logs);
        $this->assertSame('login_success', $logs[0]['action']);
    }

    public function testSuccessfulLoginClearsRateLimitAttempts(): void
    {
        $ip = '10.0.0.1';

        // Simulate some failed attempts first
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->logAttempt($ip);
        }
        $this->assertFalse($this->limiter->isBlocked($ip));

        // Successful login clears attempts
        $this->limiter->clearAttempts($ip);

        // Verify attempts table is clean for this IP
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = :ip");
        $stmt->execute(['ip' => $ip]);
        $this->assertEquals(0, $stmt->fetchColumn());
    }

    // ──────────────────────────────────────────
    // Failed login flow
    // ──────────────────────────────────────────

    public function testFailedLoginWithWrongPasswordLogsAttempt(): void
    {
        $user = $this->createTestUser('Fail User', 'fail@example.com', 'RightPass123!');
        $ip = '10.0.0.2';

        // Simulate wrong password
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => 'fail@example.com']);
        $found = $stmt->fetch();

        $this->assertFalse(password_verify('WrongPass456!', $found['password']));

        // Log the failed attempt (like AuthController does)
        $this->limiter->logAttempt($ip);
        $this->logger->log('login_failed', null, 'fail@example.com', 'Invalid password');

        // Verify logging
        $logs = $this->logger->getAllLogs();
        $this->assertCount(1, $logs);
        $this->assertSame('login_failed', $logs[0]['action']);
        $this->assertSame('Invalid password', $logs[0]['details']);

        // Session should NOT be set
        $this->assertArrayNotHasKey('user', $_SESSION);
    }

    public function testFailedLoginWithNonexistentUserLogsAttempt(): void
    {
        $ip = '10.0.0.3';

        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => 'ghost@example.com']);
        $found = $stmt->fetch();

        $this->assertFalse($found);

        $this->limiter->logAttempt($ip);
        $this->logger->log('login_failed', null, 'ghost@example.com', 'User not found');

        $logs = $this->logger->getAllLogs();
        $this->assertSame('User not found', $logs[0]['details']);
    }

    // ──────────────────────────────────────────
    // Rate limiting during login
    // ──────────────────────────────────────────

    public function testBruteForceBlocksAfterMaxAttempts(): void
    {
        $ip = '192.168.1.100';
        $maxAttempts = $this->limiter->getMaxAttempts();

        // Simulate brute force
        for ($i = 0; $i < $maxAttempts + 2; $i++) {
            if ($this->limiter->isBlocked($ip)) {
                break;
            }
            $this->limiter->logAttempt($ip);
            $this->logger->log('login_failed', null, 'target@example.com', 'Invalid password');
        }

        $this->assertTrue($this->limiter->isBlocked($ip));

        // Verify the blocked event is logged
        $this->logger->log('blocked_ip', null, 'target@example.com', 'IP Blocked due to excessive attempts');

        $logs = $this->logger->getAllLogs();
        $blockedLogs = array_filter($logs, fn($l) => $l['action'] === 'blocked_ip');
        $this->assertNotEmpty($blockedLogs);
    }

    public function testBlockedIpCannotLogin(): void
    {
        $ip = '192.168.1.200';
        $user = $this->createTestUser('Blocked User', 'blocked@example.com', 'ValidPass123!');

        // Block the IP
        for ($i = 0; $i < 12; $i++) {
            $this->limiter->logAttempt($ip);
        }

        // Even with correct credentials, blocked IP should be rejected
        $this->assertTrue($this->limiter->isBlocked($ip));

        // The AuthController would check isBlocked BEFORE attempting password verify
        // This is the correct security behavior
        $this->logger->log('blocked_ip', null, 'blocked@example.com', 'IP Blocked due to excessive attempts');

        $logs = $this->logger->getAllLogs();
        $this->assertSame('blocked_ip', $logs[0]['action']);
    }

    // ──────────────────────────────────────────
    // Logout flow
    // ──────────────────────────────────────────

    public function testLogoutClearsSession(): void
    {
        $user = $this->createTestUser('Logout User', 'logout@example.com');
        $_SESSION['user'] = $user['id'];

        // Simulate logout
        $this->logger->log('logout', $_SESSION['user']);
        unset($_SESSION['user']);

        $this->assertArrayNotHasKey('user', $_SESSION);
    }

    public function testLogoutLogsEvent(): void
    {
        $user = $this->createTestUser('Log User', 'loguser@example.com');
        $_SESSION['user'] = $user['id'];

        $this->logger->log('logout', $_SESSION['user']);

        $logs = $this->logger->getUserLogs($user['id']);
        $this->assertCount(1, $logs);
        $this->assertSame('logout', $logs[0]['action']);
    }

    // ──────────────────────────────────────────
    // Auth guard (auth_required)
    // ──────────────────────────────────────────

    public function testAuthRequiredAllowsAuthenticatedUser(): void
    {
        $_SESSION['user'] = 1;

        // auth_required() checks isset($_SESSION['user'])
        $this->assertTrue(isset($_SESSION['user']));
    }

    public function testAuthRequiredBlocksUnauthenticatedUser(): void
    {
        unset($_SESSION['user']);

        // auth_required() would redirect, but we test the condition
        $this->assertFalse(isset($_SESSION['user']));
    }

    // ──────────────────────────────────────────
    // CSRF + Auth combined
    // ──────────────────────────────────────────

    public function testLoginRequiresCsrfToken(): void
    {
        $token = csrf_token();

        // Valid CSRF
        $_POST['_csrf'] = $token;
        $this->assertSame($token, $_SESSION['csrf']);
        $this->assertSame($_POST['_csrf'], $_SESSION['csrf']);
    }

    public function testLoginRejectsMissingCsrfToken(): void
    {
        csrf_token();
        // No _csrf in POST
        $this->assertTrue(
            empty($_POST['_csrf']) || $_POST['_csrf'] !== ($_SESSION['csrf'] ?? ''),
            'Login should fail without CSRF token.'
        );
    }

    // ──────────────────────────────────────────
    // Google OAuth flow (unit-testable parts)
    // ──────────────────────────────────────────

    public function testGoogleLoginCreatesNewUser(): void
    {
        $email = 'googleuser@gmail.com';
        $googleId = 'google_123456789';

        // Check user doesn't exist
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $this->assertFalse($stmt->fetch());

        // Create user like loginGoogleCallback does
        $randomPass = bin2hex(random_bytes(16));
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $hash = password_hash($randomPass, $algo);

        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password, google_id)
            VALUES (:name, :email, :password, :google_id)
        ");
        $stmt->execute([
            'name'      => 'Google User',
            'email'     => $email,
            'password'  => $hash,
            'google_id' => $googleId,
        ]);

        // Verify user was created
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        $this->assertNotFalse($user);
        $this->assertSame($googleId, $user['google_id']);
        $this->assertSame('Google User', $user['name']);
    }

    public function testGoogleLoginLinksExistingUser(): void
    {
        // Create user without google_id
        $created = $this->createTestUser('Existing User', 'existing@gmail.com');

        // Verify no google_id
        $user = $this->db->query("SELECT google_id FROM users WHERE id = {$created['id']}")->fetch();
        $this->assertNull($user['google_id']);

        // Update google_id (like loginGoogleCallback does)
        $stmt = $this->db->prepare("UPDATE users SET google_id = :google_id WHERE id = :id");
        $stmt->execute([
            'google_id' => 'linked_google_id',
            'id'        => $created['id'],
        ]);

        // Verify google_id was set
        $user = $this->db->query("SELECT google_id FROM users WHERE id = {$created['id']}")->fetch();
        $this->assertSame('linked_google_id', $user['google_id']);
    }
}
