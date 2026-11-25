<?php

namespace MyEvent\Tests\Integration;

use MyEvent\Tests\Support\DatabaseTestCase;
use MyEvent\Tests\Support\MailSpy;

final class MailQueueTest extends DatabaseTestCase
{
    public function testLogDriverDeliversImmediatelyWithoutQueueing(): void
    {
        queue_mail($this->db, 'a@example.test', 'Hello', 'Text', '<p>HTML</p>');

        $this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM mail_queue'));
        $this->assertSame(['a@example.test'], MailSpy::recipients());
    }

    public function testSmtpDriverQueuesInsteadOfSending(): void
    {
        putenv('MAIL_DRIVER=smtp');
        queue_mail($this->db, 'a@example.test', 'Hello', 'Text', '<p>HTML</p>');

        $row = db_all($this->db, 'SELECT to_email, subject, body_text, body_html, status, attempts FROM mail_queue')[0];
        $this->assertSame(['to_email' => 'a@example.test', 'subject' => 'Hello', 'body_text' => 'Text',
                           'body_html' => '<p>HTML</p>', 'status' => 'pending', 'attempts' => 0], $row);
        $this->assertSame([], MailSpy::messages());
    }

    public function testWorkerSendsDueMailAndMarksItSent(): void
    {
        putenv('MAIL_DRIVER=smtp');
        queue_mail($this->db, 'a@example.test', 'One', 'Text 1');
        queue_mail($this->db, 'b@example.test', 'Two', 'Text 2');
        putenv('MAIL_DRIVER=log'); // the "server" accepts everything

        $this->assertSame(['sent' => 2, 'failed' => 0, 'retry' => 0], process_mail_queue($this->db));
        $this->assertSame(2, $this->countRows("SELECT COUNT(*) FROM mail_queue WHERE status = 'sent' AND sent_at IS NOT NULL"));
        $this->assertEqualsCanonicalizing(['a@example.test', 'b@example.test'], MailSpy::recipients());

        $this->assertSame(['sent' => 0, 'failed' => 0, 'retry' => 0], process_mail_queue($this->db), 'sent mail is never sent twice');
    }

    public function testFailedSendIsRetriedLaterWithBackoff(): void
    {
        putenv('MAIL_DRIVER=smtp'); // no SMTP_HOST in tests, so every send fails
        queue_mail($this->db, 'a@example.test', 'Hello', 'Text');

        $this->assertSame(['sent' => 0, 'failed' => 0, 'retry' => 1], process_mail_queue($this->db));

        $row = db_all($this->db, 'SELECT status, attempts, last_error, TIMESTAMPDIFF(SECOND, NOW(), next_attempt_at) AS wait FROM mail_queue')[0];
        $this->assertSame('pending', $row['status']);
        $this->assertSame(1, (int) $row['attempts']);
        $this->assertSame('SMTP_HOST is not configured', $row['last_error']);
        $this->assertEqualsWithDelta(5 * 60, (int) $row['wait'], 5);

        $this->assertSame(['sent' => 0, 'failed' => 0, 'retry' => 0], process_mail_queue($this->db), 'not due yet');
    }

    public function testMailIsMarkedFailedAfterTheLastAttempt(): void
    {
        putenv('MAIL_DRIVER=smtp');
        queue_mail($this->db, 'a@example.test', 'Hello', 'Text');
        $this->db->query('UPDATE mail_queue SET attempts = ' . (MAIL_MAX_ATTEMPTS - 1));

        $this->assertSame(['sent' => 0, 'failed' => 1, 'retry' => 0], process_mail_queue($this->db));
        $this->assertSame('failed', db_value($this->db, 'SELECT status FROM mail_queue'));

        $this->db->query('UPDATE mail_queue SET next_attempt_at = NOW() - INTERVAL 1 DAY');
        $this->assertSame(['sent' => 0, 'failed' => 0, 'retry' => 0], process_mail_queue($this->db), 'failed mail is left alone');
    }

    public function testRetryDelaysGrowExponentially(): void
    {
        putenv('MAIL_DRIVER=smtp');
        $waits = [];
        queue_mail($this->db, 'a@example.test', 'Hello', 'Text');
        for ($attempt = 1; $attempt < MAIL_MAX_ATTEMPTS; $attempt++) {
            $this->db->query('UPDATE mail_queue SET next_attempt_at = NOW()');
            process_mail_queue($this->db);
            $waits[] = (int) round((int) db_value($this->db, 'SELECT TIMESTAMPDIFF(SECOND, NOW(), next_attempt_at) FROM mail_queue') / 60);
        }

        $this->assertSame([5, 15, 45, 135], $waits);
    }

    public function testWorkerRespectsTheBatchLimit(): void
    {
        putenv('MAIL_DRIVER=smtp');
        foreach (range(1, 5) as $i) {
            queue_mail($this->db, "u$i@example.test", "Mail $i", 'Text');
        }
        putenv('MAIL_DRIVER=log');

        $this->assertSame(2, process_mail_queue($this->db, 2)['sent']);
        $this->assertSame(3, $this->countRows("SELECT COUNT(*) FROM mail_queue WHERE status = 'pending'"));
    }
}
