<?php

namespace MyEvent\Tests\Integration;

use MyEvent\Tests\Support\DatabaseTestCase;

/** Login throttling, the audit trail and other security features that keep state in the database. */
final class SecurityStoreTest extends DatabaseTestCase
{
    protected function tearDown(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        parent::tearDown();
    }

    public function testLoginIsThrottledAfterFiveFailures(): void
    {
        for ($i = 1; $i < LOGIN_MAX_ATTEMPTS; $i++) {
            login_record_failure($this->db, 'ali@example.test');
        }
        $this->assertFalse(login_is_throttled($this->db, 'ali@example.test'));

        login_record_failure($this->db, 'ali@example.test');
        $this->assertTrue(login_is_throttled($this->db, 'ali@example.test'));
    }

    public function testThrottlingAlsoAppliesPerIpAddress(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        foreach (range(1, LOGIN_MAX_ATTEMPTS) as $i) {
            login_record_failure($this->db, "guess$i@example.test");
        }

        $this->assertTrue(login_is_throttled($this->db, 'someone-else@example.test'), 'same IP trying many accounts');

        $_SERVER['REMOTE_ADDR'] = '198.51.100.1';
        $this->assertFalse(login_is_throttled($this->db, 'someone-else@example.test'));
    }

    public function testOldFailuresExpire(): void
    {
        foreach (range(1, LOGIN_MAX_ATTEMPTS) as $i) {
            login_record_failure($this->db, 'ali@example.test');
        }
        $this->db->query('UPDATE login_attempts SET attempted_at = NOW() - INTERVAL ' . (LOGIN_WINDOW_MINUTES + 1) . ' MINUTE');

        $this->assertFalse(login_is_throttled($this->db, 'ali@example.test'));
    }

    public function testSuccessfulLoginClearsFailures(): void
    {
        foreach (range(1, LOGIN_MAX_ATTEMPTS) as $i) {
            login_record_failure($this->db, 'ali@example.test');
        }
        login_clear_failures($this->db, 'ali@example.test');

        $this->assertFalse(login_is_throttled($this->db, 'ali@example.test'));
    }

    public function testAuditRecordsTheActorAndTruncatesLongDetails(): void
    {
        $admin = $this->makeUser('Admin Person', ['role' => 'Admin']);
        $_SESSION = ['loggedin' => true, 'user_id' => $admin, 'name' => 'Admin Person', 'role' => 'Admin'];

        audit($this->db, 'user.delete', 'user', 42, str_repeat('x', 900));

        $row = db_all($this->db, 'SELECT actor_id, actor_name, action, target_type, target_id, CHAR_LENGTH(details) AS len, ip_address FROM audit_log')[0];
        $this->assertSame([
            'actor_id' => $admin, 'actor_name' => 'Admin Person', 'action' => 'user.delete',
            'target_type' => 'user', 'target_id' => 42, 'len' => 500, 'ip_address' => '127.0.0.1',
        ], $row);
    }

    public function testAuditNeverBreaksTheActionItAccompanies(): void
    {
        $this->db->query('RENAME TABLE audit_log TO audit_log_gone');
        try {
            $previous = ini_set('error_log', (string) tempnam(sys_get_temp_dir(), 'audit'));
            audit($this->db, 'anything');
            ini_set('error_log', (string) $previous);
            $this->addToAssertionCount(1);
        } finally {
            $this->db->query('RENAME TABLE audit_log_gone TO audit_log');
        }
    }

    public function testFeedbackIsLimitedToOnePerPersonPerEvent(): void
    {
        $event = $this->makeEvent($this->makeUser());
        $user = $this->makeUser();
        $this->insert('feedback', ['event_id' => $event, 'user_id' => $user, 'rating' => 4]);

        $this->expectException(\mysqli_sql_exception::class);
        $this->insert('feedback', ['event_id' => $event, 'user_id' => $user, 'rating' => 5]);
    }

    public function testEventRatingAverage(): void
    {
        $event = $this->makeEvent($this->makeUser());
        foreach ([5, 4, 4] as $stars) {
            $this->insert('feedback', ['event_id' => $event, 'user_id' => $this->makeUser(), 'rating' => $stars]);
        }

        $rating = event_rating($this->db, $event);
        $this->assertSame(3, $rating['count']);
        $this->assertEqualsWithDelta(4.333, $rating['avg'], 0.001);
        $this->assertSame(['avg' => 0.0, 'count' => 0], event_rating($this->db, $this->makeEvent($this->makeUser())));
    }
}
