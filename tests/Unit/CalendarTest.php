<?php

namespace MyEvent\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Times are in APP_TIMEZONE (Asia/Kuala_Lumpur, UTC+8) in the test bootstrap. */
final class CalendarTest extends TestCase
{
    private function event(array $overrides = []): array
    {
        return $overrides + [
            'event_id'    => 7,
            'event_name'  => 'UX Research Workshop',
            'description' => 'Hands-on session',
            'venue'       => 'Dewan Kuliah 3',
            'event_date'  => '2026-12-01',
            'event_time'  => '09:30:00',
            'end_time'    => '12:30:00',
        ];
    }

    public function testBootstrapUsesKualaLumpurTime(): void
    {
        $this->assertSame('Asia/Kuala_Lumpur', date_default_timezone_get());
    }

    public function testEventPeriodUsesTheEndTime(): void
    {
        [$start, $end] = event_period($this->event());

        $this->assertSame('2026-12-01 09:30', $start->format('Y-m-d H:i'));
        $this->assertSame('2026-12-01 12:30', $end->format('Y-m-d H:i'));
    }

    public function testEventPeriodDefaultsToTwoHours(): void
    {
        [, $end] = event_period($this->event(['end_time' => null]));
        $this->assertSame('11:30', $end->format('H:i'));
    }

    public function testEndTimeBeforeStartFallsBackToTwoHours(): void
    {
        [, $end] = event_period($this->event(['end_time' => '08:00:00']));
        $this->assertSame('11:30', $end->format('H:i'));
    }

    public function testIcsEscape(): void
    {
        $this->assertSame('a\\\\b\\; c\\, d\\ne', ics_escape("a\\b; c, d\r\ne"));
    }

    public function testIcsFoldKeepsLinesWithin75OctetsAndNeverSplitsCharacters(): void
    {
        $line = 'SUMMARY:' . str_repeat('Majlis Anugerah – é ', 12);
        $folded = ics_fold($line);
        $parts = explode("\r\n", $folded);

        $this->assertGreaterThan(1, count($parts));
        foreach ($parts as $i => $part) {
            $this->assertLessThanOrEqual(75, strlen($part), "line $i is too long");
            $this->assertTrue(mb_check_encoding($part, 'UTF-8'), "line $i splits a UTF-8 character");
            if ($i > 0) {
                $this->assertStringStartsWith(' ', $part);
            }
        }
        // Unfolding restores the original.
        $this->assertSame($line, str_replace("\r\n ", '', $folded));
    }

    public function testEventIcsIsAValidCalendarInUtc(): void
    {
        $ics = event_ics($this->event(['event_name' => 'Talk; Q&A, part 1']));

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        $this->assertSame(0, preg_match("/(?<!\r)\n/", $ics), 'every line must end with CRLF');
        $this->assertStringContainsString("DTSTART:20261201T013000Z\r\n", $ics);
        $this->assertStringContainsString("DTEND:20261201T043000Z\r\n", $ics);
        $this->assertStringContainsString('SUMMARY:Talk\\; Q&A\\, part 1', $ics);
        $this->assertStringContainsString('UID:event-7@localhost', $ics);
        $this->assertStringContainsString('TRIGGER:-PT1H', $ics);
    }

    public function testGoogleCalendarLink(): void
    {
        $url = google_calendar_url($this->event());
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://calendar.google.com/calendar/render?', $url);
        $this->assertSame('TEMPLATE', $query['action']);
        $this->assertSame('20261201T013000Z/20261201T043000Z', $query['dates']);
        $this->assertSame('Dewan Kuliah 3', $query['location']);
        $this->assertStringEndsWith('event_detail.php?id=7', $query['details']);
    }
}
