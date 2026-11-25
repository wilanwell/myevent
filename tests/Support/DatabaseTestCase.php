<?php

namespace MyEvent\Tests\Support;

use mysqli;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that use the database. Every test starts with empty tables
 * and builds only the rows it needs with the make*() helpers.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected mysqli $db;

    protected function setUp(): void
    {
        $this->db = $GLOBALS['conn'];
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->db->query('SHOW TABLES')->fetch_all() as [$table]) {
            $this->db->query("DELETE FROM `$table`"); // faster than TRUNCATE for tiny tables
        }
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
        $_SESSION = [];
        putenv('MAIL_DRIVER=log');
        MailSpy::clear();
    }

    protected function tearDown(): void
    {
        putenv('MAIL_DRIVER=log');
    }

    protected function makeUser(string $name = 'Test User', array $overrides = []): int
    {
        static $n = 0;
        $n++;
        $row = $overrides + [
            'category'          => 'Student',
            'name'              => $name,
            'email'             => 'user' . $n . '_' . bin2hex(random_bytes(3)) . '@example.test',
            'organization'      => 'Test Campus',
            'password'          => password_hash('irrelevant', PASSWORD_DEFAULT, ['cost' => 4]),
            'role'              => 'User',
            'email_participant' => 1,
            'email_organiser'   => 1,
        ];
        return $this->insert('users', $row);
    }

    /** An event a week from now with registration open, unless overridden. */
    protected function makeEvent(int $organiserId, array $overrides = []): int
    {
        $row = $overrides + [
            'event_name'       => 'Test Event',
            'venue'            => 'Hall A',
            'event_date'       => date('Y-m-d', strtotime('+7 days')),
            'event_time'       => '09:00:00',
            'mode'             => 'Physical',
            'visibility'       => 'Public',
            'created_by'       => $organiserId,
            'max_participants' => 0,
        ];
        return $this->insert('events', $row);
    }

    protected function register(int $userId, int $eventId, string $status = 'Pending'): int
    {
        return $this->insert('registrations', ['user_id' => $userId, 'event_id' => $eventId, 'approval_status' => $status]);
    }

    /** Join the waitlist; $secondsAgo orders people in line deterministically. */
    protected function joinWaitlist(int $userId, int $eventId, int $secondsAgo = 0): int
    {
        return $this->insert('waitlist', [
            'user_id'    => $userId,
            'event_id'   => $eventId,
            'created_at' => date('Y-m-d H:i:s', time() - $secondsAgo),
        ]);
    }

    protected function insert(string $table, array $row): int
    {
        $columns = implode(', ', array_map(static fn($c) => "`$c`", array_keys($row)));
        $marks = implode(', ', array_fill(0, count($row), '?'));
        $stmt = $this->db->prepare("INSERT INTO `$table` ($columns) VALUES ($marks)");
        $stmt->bind_param(str_repeat('s', count($row)), ...array_map(static fn($v) => $v === null ? null : (string) $v, array_values($row)));
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        return (int) $id;
    }

    protected function countRows(string $sql, string $types = '', array $params = []): int
    {
        return (int) db_value($this->db, $sql, $types, $params);
    }
}
