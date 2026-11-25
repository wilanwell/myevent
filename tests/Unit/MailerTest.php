<?php

namespace MyEvent\Tests\Unit;

use MyEvent\Tests\Support\MailSpy;
use PHPUnit\Framework\TestCase;

final class MailerTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('MAIL_DRIVER=log');
        MailSpy::clear();
    }

    public function testHeaderEncodingBlocksInjectionAndEncodesUnicode(): void
    {
        $this->assertSame('Hello', mail_encode_header('Hello'));
        $this->assertSame('HiBcc: x@evil.test', mail_encode_header("Hi\r\nBcc: x@evil.test"));
        $this->assertSame('=?UTF-8?B?' . base64_encode('Jemputan – Majlis') . '?=', mail_encode_header('Jemputan – Majlis'));
    }

    public function testSendMailWritesAMultipartMessageWithTheLogDriver(): void
    {
        $ok = send_mail('aisyah@example.test', 'Your ticket', "Plain body", '<p>HTML body</p>', $error);

        $this->assertTrue($ok, (string) $error);
        $messages = MailSpy::messages();
        $this->assertCount(1, $messages);
        $mail = $messages[0];
        $this->assertSame('aisyah@example.test', $mail['to']);
        $this->assertSame('Your ticket', $mail['subject']);
        $this->assertSame('Plain body', $mail['text']);
        $this->assertSame('<p>HTML body</p>', $mail['html']);
        $this->assertStringContainsString('Content-Type: multipart/alternative', $mail['raw']);
        $this->assertStringContainsString('Auto-Submitted: auto-generated', $mail['raw']);
        $this->assertStringContainsString("\r\n\r\n", $mail['raw']);
    }

    public function testPlainTextOnlyMessage(): void
    {
        $this->assertTrue(send_mail('a@example.test', 'Hi', 'Only text'));
        $mail = MailSpy::messages()[0];

        $this->assertNull($mail['html']);
        $this->assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $mail['raw']);
        $this->assertStringContainsString(base64_encode('Only text'), $mail['raw']);
    }

    public function testInvalidRecipientIsRefused(): void
    {
        $this->assertFalse(send_mail("victim@example.test\r\nBcc: all@example.test", 'x', 'y', null, $error));
        $this->assertStringContainsString('invalid address', (string) $error);
        $this->assertSame([], MailSpy::messages());
    }

    public function testSmtpWithoutHostFailsCleanly(): void
    {
        putenv('MAIL_DRIVER=smtp');
        putenv('SMTP_HOST=');

        $this->assertFalse(send_mail('a@example.test', 'x', 'y', null, $error));
        $this->assertSame('SMTP_HOST is not configured', $error);
        putenv('MAIL_DRIVER=log');
    }

    public function testEmailHtmlEscapesEverythingItIsGiven(): void
    {
        $html = email_html('<b>Heading</b>', "Line 1\n<script>x</script>", 'Open "it"', 'https://example.test/?a=1&b=2',
            ['When' => '<i>today</i>'], 'Footnote & more');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>Heading', $html);
        $this->assertStringContainsString('&lt;b&gt;Heading&lt;/b&gt;', $html);
        $this->assertStringContainsString('Line 1<br', $html);
        $this->assertStringContainsString('href="https://example.test/?a=1&amp;b=2"', $html);
        $this->assertStringContainsString('&lt;i&gt;today&lt;/i&gt;', $html);
        $this->assertStringContainsString('Footnote &amp; more', $html);
        $this->assertStringContainsString('users/profile.php#email-settings', $html);
    }

    public function testEmailHtmlOmitsTheButtonWithoutAUrl(): void
    {
        $this->assertStringNotContainsString('<a href="" ', email_html('H', 'I', 'Label', null));
        $this->assertSame(1, substr_count(email_html('H', 'I', 'Label', null), '<a '));
    }

    public function testEventFacts(): void
    {
        $facts = event_facts(['event_date' => '2026-12-01', 'event_time' => '09:30:00', 'end_time' => '12:30:00',
                              'venue' => 'Dewan Kuliah 3', 'mode' => 'Hybrid']);

        $this->assertSame([
            'When'  => 'Tuesday, 1 December 2026, 9:30 AM to 12:30 PM',
            'Where' => 'Dewan Kuliah 3 (Hybrid)',
        ], $facts);
    }
}
