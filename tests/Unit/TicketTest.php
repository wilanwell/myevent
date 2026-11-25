<?php

namespace MyEvent\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TicketTest extends TestCase
{
    private string $key;

    protected function setUp(): void
    {
        $this->key = (string) getenv('APP_KEY');
    }

    protected function tearDown(): void
    {
        putenv('APP_KEY=' . $this->key);
    }

    public function testTicketCodeFormat(): void
    {
        $this->assertMatchesRegularExpression('/^ME-0044-00012-[0-9A-F]{6}$/', ticket_code(44, 12));
    }

    public function testTicketCodeIsDeterministicAndUniquePerRegistration(): void
    {
        $this->assertSame(ticket_code(1, 2), ticket_code(1, 2));
        $this->assertNotSame(ticket_code(1, 2), ticket_code(1, 3));
        $this->assertNotSame(ticket_code(1, 2), ticket_code(2, 2));
    }

    public function testVerifyAcceptsAValidCodeTypedSloppily(): void
    {
        $code = ticket_code(44, 12);

        $this->assertSame(['event_id' => 44, 'reg_id' => 12], verify_ticket_code($code));
        $this->assertSame(['event_id' => 44, 'reg_id' => 12], verify_ticket_code('  ' . strtolower($code) . " \n"));
    }

    public function testVerifyRejectsTamperedCodes(): void
    {
        $code = ticket_code(44, 12);
        $sig = substr($code, -6);
        $otherReg = 'ME-0044-00013-' . $sig;               // someone else's registration, same signature
        $flipped = substr($code, 0, -1) . (substr($code, -1) === 'A' ? 'B' : 'A');

        $this->assertNull(verify_ticket_code($otherReg));
        $this->assertNull(verify_ticket_code($flipped));
        $this->assertNull(verify_ticket_code('ME-44-12'));
        $this->assertNull(verify_ticket_code(''));
        $this->assertNull(verify_ticket_code("ME-0044-00012-' OR 1=1"));
    }

    public function testCodesSignedWithAnotherKeyDoNotVerify(): void
    {
        $code = ticket_code(5, 6);
        putenv('APP_KEY=' . str_repeat('f', 64));

        $this->assertNull(verify_ticket_code($code));
    }

    public function testMissingOrShortKeyIsRefused(): void
    {
        putenv('APP_KEY=short');

        $this->expectException(RuntimeException::class);
        ticket_code(1, 1);
    }

    public function testCheckinUrlPointsAtTheCheckinPage(): void
    {
        $this->assertSame(BASE_URL . 'users/checkin.php?code=ME-0001-00002-ABCDEF', ticket_checkin_url('ME-0001-00002-ABCDEF'));
    }
}
