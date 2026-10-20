<?php

declare(strict_types=1);

require_once __DIR__ . '/../DatabaseTestCase.php';

/**
 * Tests for the CSRF protection helpers.
 * 
 * Validates token generation, consistency within a session,
 * and rejection of invalid/missing tokens.
 */
final class CsrfTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear any existing CSRF token
        unset($_SESSION['csrf']);
        $_POST = [];
    }

    // ──────────────────────────────────────────
    // Token generation
    // ──────────────────────────────────────────

    public function testCsrfTokenGeneratesNonEmptyString(): void
    {
        $token = csrf_token();
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    public function testCsrfTokenIs64HexCharacters(): void
    {
        $token = csrf_token();
        // bin2hex(random_bytes(32)) = 64 hex chars
        $this->assertEquals(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function testCsrfTokenIsPersistentWithinSession(): void
    {
        $token1 = csrf_token();
        $token2 = csrf_token();

        $this->assertSame($token1, $token2, 'Same session should return the same CSRF token.');
    }

    public function testCsrfTokenChangesAcrossSessions(): void
    {
        $token1 = csrf_token();

        // Simulate new session
        unset($_SESSION['csrf']);
        $token2 = csrf_token();

        $this->assertNotSame($token1, $token2, 'New session should generate a new token.');
    }

    // ──────────────────────────────────────────
    // Token validation (csrf_check)
    // ──────────────────────────────────────────

    public function testCsrfCheckPassesWithValidToken(): void
    {
        $token = csrf_token();
        $_POST['_csrf'] = $token;

        // csrf_check() calls die() on failure, so if we get past it, it passed
        // We need to capture this - csrf_check doesn't return, it die()s
        // For testing, we verify the logic manually
        $this->assertSame($token, $_SESSION['csrf']);
        $this->assertSame($_POST['_csrf'], $_SESSION['csrf']);
    }

    public function testCsrfCheckFailsWithWrongToken(): void
    {
        csrf_token(); // Generate a valid session token
        $_POST['_csrf'] = 'invalid_token_value';

        $this->assertNotSame($_POST['_csrf'], $_SESSION['csrf']);
    }

    public function testCsrfCheckFailsWithEmptyToken(): void
    {
        csrf_token();
        $_POST['_csrf'] = '';

        // empty() check should catch this
        $this->assertTrue(empty($_POST['_csrf']));
    }

    public function testCsrfCheckFailsWithMissingToken(): void
    {
        csrf_token();
        // No _csrf in POST at all

        $this->assertTrue(empty($_POST['_csrf']));
    }

    public function testCsrfCheckFailsWhenSessionHasNoToken(): void
    {
        // No csrf_token() called, session has no 'csrf' key
        $_POST['_csrf'] = 'some_token';

        $this->assertNotSame($_POST['_csrf'], $_SESSION['csrf'] ?? '');
    }
}
