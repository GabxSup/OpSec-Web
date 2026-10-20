<?php

declare(strict_types=1);

require_once __DIR__ . '/../DatabaseTestCase.php';

/**
 * Tests for the RateLimiter component.
 * 
 * Validates IP-based brute-force protection:
 * - Attempts below threshold are allowed
 * - Exceeding threshold blocks the IP
 * - Clearing attempts resets the block
 * - Different IPs are tracked independently
 */
final class RateLimiterTest extends DatabaseTestCase
{
    private TestRateLimiter $limiter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->limiter = new TestRateLimiter();
    }

    // ──────────────────────────────────────────
    // Basic blocking logic
    // ──────────────────────────────────────────

    public function testNewIpIsNotBlocked(): void
    {
        $this->assertFalse($this->limiter->isBlocked('192.168.1.1'));
    }

    public function testIpNotBlockedBelowThreshold(): void
    {
        $ip = '10.0.0.1';
        $maxAttempts = $this->limiter->getMaxAttempts();

        // Log exactly (threshold - 1) attempts
        for ($i = 0; $i < $maxAttempts - 1; $i++) {
            $this->limiter->logAttempt($ip);
        }

        $this->assertFalse(
            $this->limiter->isBlocked($ip),
            "IP should NOT be blocked with " . ($maxAttempts - 1) . " attempts."
        );
    }

    public function testIpBlockedAtExactThreshold(): void
    {
        $ip = '10.0.0.2';
        $maxAttempts = $this->limiter->getMaxAttempts();

        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->limiter->logAttempt($ip);
        }

        $this->assertTrue(
            $this->limiter->isBlocked($ip),
            "IP should be blocked after exactly $maxAttempts attempts."
        );
    }

    public function testIpBlockedAboveThreshold(): void
    {
        $ip = '10.0.0.3';

        for ($i = 0; $i < 15; $i++) {
            $this->limiter->logAttempt($ip);
        }

        $this->assertTrue($this->limiter->isBlocked($ip));
    }

    // ──────────────────────────────────────────
    // Clearing attempts
    // ──────────────────────────────────────────

    public function testClearAttemptsUnblocksIp(): void
    {
        $ip = '10.0.0.4';

        // Block the IP
        for ($i = 0; $i < 12; $i++) {
            $this->limiter->logAttempt($ip);
        }
        $this->assertTrue($this->limiter->isBlocked($ip));

        // Clear and verify unblocked
        $this->limiter->clearAttempts($ip);
        $this->assertFalse($this->limiter->isBlocked($ip));
    }

    public function testClearAttemptsOnlyAffectsTargetIp(): void
    {
        $ip1 = '10.0.0.5';
        $ip2 = '10.0.0.6';

        // Block both IPs
        for ($i = 0; $i < 12; $i++) {
            $this->limiter->logAttempt($ip1);
            $this->limiter->logAttempt($ip2);
        }

        // Clear only ip1
        $this->limiter->clearAttempts($ip1);

        $this->assertFalse($this->limiter->isBlocked($ip1), 'Cleared IP should be unblocked.');
        $this->assertTrue($this->limiter->isBlocked($ip2), 'Other IP should remain blocked.');
    }

    // ──────────────────────────────────────────
    // IP isolation
    // ──────────────────────────────────────────

    public function testDifferentIpsAreTrackedIndependently(): void
    {
        $attackerIp = '10.0.0.7';
        $legitimateIp = '10.0.0.8';

        // Only the attacker IP gets hammered
        for ($i = 0; $i < 12; $i++) {
            $this->limiter->logAttempt($attackerIp);
        }

        $this->assertTrue($this->limiter->isBlocked($attackerIp));
        $this->assertFalse(
            $this->limiter->isBlocked($legitimateIp),
            'Legitimate IP should not be affected by attacker\'s attempts.'
        );
    }

    // ──────────────────────────────────────────
    // Attempt logging
    // ──────────────────────────────────────────

    public function testLogAttemptInsertsRecord(): void
    {
        $ip = '10.0.0.9';

        $this->assertEquals(0, $this->countRows('login_attempts'));

        $this->limiter->logAttempt($ip);

        $this->assertEquals(1, $this->countRows('login_attempts'));
    }

    public function testLogAttemptRecordsCorrectIp(): void
    {
        $ip = '172.16.0.1';
        $this->limiter->logAttempt($ip);

        $row = $this->db->query("SELECT ip_address FROM login_attempts LIMIT 1")->fetch();
        $this->assertSame($ip, $row['ip_address']);
    }

    // ──────────────────────────────────────────
    // Configuration
    // ──────────────────────────────────────────

    public function testMaxAttemptsIsReasonable(): void
    {
        $max = $this->limiter->getMaxAttempts();
        $this->assertGreaterThanOrEqual(3, $max, 'Max attempts should be at least 3.');
        $this->assertLessThanOrEqual(50, $max, 'Max attempts should be at most 50.');
    }

    public function testLockoutTimeIsReasonable(): void
    {
        $lockout = $this->limiter->getLockoutTime();
        $this->assertGreaterThanOrEqual(1, $lockout, 'Lockout should be at least 1 minute.');
        $this->assertLessThanOrEqual(60, $lockout, 'Lockout should be at most 60 minutes.');
    }
}
