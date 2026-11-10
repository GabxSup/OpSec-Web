<?php

declare(strict_types=1);

require_once __DIR__ . '/../DatabaseTestCase.php';

/**
 * Tests for the Logger component (audit logging).
 * 
 * Validates that activity logs are properly recorded
 * with correct fields and can be retrieved per user.
 */
final class LoggerTest extends DatabaseTestCase
{
    private TestLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new TestLogger();
    }

    // ──────────────────────────────────────────
    // Basic logging
    // ──────────────────────────────────────────

    public function testLogInsertsRecord(): void
    {
        $this->assertEquals(0, $this->countRows('activity_logs'));

        $this->logger->log('login_success', 1, 'test@example.com');

        $this->assertEquals(1, $this->countRows('activity_logs'));
    }

    public function testLogRecordsCorrectAction(): void
    {
        $this->logger->log('login_failed', null, 'fail@example.com', 'Invalid password');

        $log = $this->db->query("SELECT * FROM activity_logs LIMIT 1")->fetch();

        $this->assertSame('login_failed', $log['action']);
        $this->assertSame('fail@example.com', $log['email_attempt']);
        $this->assertSame('Invalid password', $log['details']);
    }

    public function testLogRecordsIpAddress(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.42';
        $this->logger->log('test_action');

        $log = $this->db->query("SELECT ip_address FROM activity_logs LIMIT 1")->fetch();
        $this->assertSame('203.0.113.42', $log['ip_address']);

        // Restore
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    public function testLogRecordsUserAgent(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test Browser';
        $this->logger->log('test_action');

        $log = $this->db->query("SELECT user_agent FROM activity_logs LIMIT 1")->fetch();
        $this->assertSame('Mozilla/5.0 Test Browser', $log['user_agent']);

        // Restore
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Test Agent';
    }

    public function testLogAcceptsNullUserId(): void
    {
        $this->logger->log('login_failed', null, 'anon@example.com');

        $log = $this->db->query("SELECT user_id FROM activity_logs LIMIT 1")->fetch();
        $this->assertNull($log['user_id']);
    }

    public function testLogAcceptsNullDetails(): void
    {
        $this->logger->log('logout', 1);

        $log = $this->db->query("SELECT details FROM activity_logs LIMIT 1")->fetch();
        $this->assertNull($log['details']);
    }

    // ──────────────────────────────────────────
    // Multiple log entries
    // ──────────────────────────────────────────

    public function testMultipleLogsAreAllRecorded(): void
    {
        $this->logger->log('login_success', 1, 'user@example.com');
        $this->logger->log('login_failed', null, 'attacker@example.com', 'Wrong password');
        $this->logger->log('logout', 1);
        $this->logger->log('blocked_ip', null, 'spam@example.com', 'Brute force');

        $this->assertEquals(4, $this->countRows('activity_logs'));
    }

    // ──────────────────────────────────────────
    // Retrieving user-specific logs
    // ──────────────────────────────────────────

    public function testGetUserLogsReturnsOnlyThatUsersLogs(): void
    {
        // User 1 logs
        $this->logger->log('login_success', 1, 'user1@example.com');
        $this->logger->log('logout', 1);

        // User 2 logs
        $this->logger->log('login_success', 2, 'user2@example.com');

        // Anonymous logs (no user)
        $this->logger->log('login_failed', null, 'unknown@example.com');

        $user1Logs = $this->logger->getUserLogs(1);
        $this->assertCount(2, $user1Logs);

        $user2Logs = $this->logger->getUserLogs(2);
        $this->assertCount(1, $user2Logs);
    }

    public function testGetUserLogsReturnsEmptyForNoLogs(): void
    {
        $logs = $this->logger->getUserLogs(999);
        $this->assertIsArray($logs);
        $this->assertEmpty($logs);
    }

    public function testGetUserLogsOrderedByNewest(): void
    {
        $this->logger->log('first_action', 1, 'user@example.com');
        // Small delay simulation - insert with explicit ordering
        $this->logger->log('second_action', 1, 'user@example.com');

        $logs = $this->logger->getUserLogs(1);
        $this->assertCount(2, $logs);
        // Most recent should be first (ORDER BY created_at DESC)
        $this->assertSame('second_action', $logs[0]['action']);
        $this->assertSame('first_action', $logs[1]['action']);
    }

    public function testGetUserLogsLimitedTo20(): void
    {
        // Insert 25 logs for user 1
        for ($i = 0; $i < 25; $i++) {
            $this->logger->log("action_$i", 1, 'user@example.com');
        }

        $logs = $this->logger->getUserLogs(1);
        $this->assertCount(20, $logs, 'Should return at most 20 logs.');
    }

    // ──────────────────────────────────────────
    // Action types coverage
    // ──────────────────────────────────────────

    public function testLogsAllExpectedActionTypes(): void
    {
        $actions = [
            'login_success',
            'login_failed',
            'logout',
            'blocked_ip',
            'register_google',
            'login_google',
            'blocked_ip_register',
        ];

        foreach ($actions as $action) {
            $this->logger->log($action, 1, 'test@example.com', "Testing $action");
        }

        $this->assertEquals(count($actions), $this->countRows('activity_logs'));
    }
}
