<?php

namespace MyEvent\Tests\Integration;

use MyEvent\Tests\Support\DatabaseTestCase;

final class ReportsTest extends DatabaseTestCase
{
    private function day(string $offset): string
    {
        return date('Y-m-d', strtotime($offset));
    }

    /** A past event with 4 approved (3 present), 1 pending, 1 rejected, and two ratings. */
    private function pastEvent(int $organiser, array $overrides = []): int
    {
        $event = $this->makeEvent($organiser, $overrides + ['event_date' => $this->day('-10 days'), 'max_participants' => 10]);
        foreach (['Present', 'Present', 'Present', 'Absent'] as $i => $att) {
            $u = $this->makeUser('Approved ' . $i, ['category' => $i < 3 ? 'Student' : 'Staff']);
            $this->insert('registrations', ['user_id' => $u, 'event_id' => $event, 'approval_status' => 'Approved', 'attendance_status' => $att]);
            if ($i < 2) {
                $this->insert('feedback', ['event_id' => $event, 'user_id' => $u, 'rating' => $i === 0 ? 5 : 4]);
            }
        }
        $this->register($this->makeUser('Pending'), $event, 'Pending');
        $this->register($this->makeUser('Rejected'), $event, 'Rejected');
        return $event;
    }

    public function testFilterPresets(): void
    {
        $f = report_filter(['range' => '30d']);
        $this->assertSame($this->day('-30 days'), $f['from']);
        $this->assertNull($f['to']);
        $this->assertStringStartsWith('Events since ', $f['label']);

        $this->assertSame('12m', report_filter([])['preset'], 'default');
        $this->assertSame('12m', report_filter(['range' => 'bogus'])['preset']);
        $all = report_filter(['range' => 'all'], 9);
        $this->assertNull($all['from']);
        $this->assertSame(9, $all['organiser']);
    }

    public function testCustomRangeIsValidatedAndOrdered(): void
    {
        $f = report_filter(['range' => 'custom', 'from' => '2026-06-30', 'to' => '2026-06-01']);
        $this->assertSame(['2026-06-01', '2026-06-30'], [$f['from'], $f['to']]);
        $this->assertSame('Events from 1 Jun 2026 to 30 Jun 2026', $f['label']);

        $bad = report_filter(['range' => 'custom', 'from' => "2026-01-01' OR 1=1", 'to' => 'tomorrow']);
        $this->assertSame('all', $bad['preset']);
        $this->assertNull($bad['from']);
    }

    public function testSummaryDefinitions(): void
    {
        $org = $this->makeUser('Organiser');
        $this->pastEvent($org);
        $upcoming = $this->makeEvent($org, ['max_participants' => 1]);
        $this->register($this->makeUser(), $upcoming, 'Approved');
        $this->joinWaitlist($this->makeUser(), $upcoming);

        $s = report_summary($this->db, report_filter(['range' => 'all']));

        $this->assertSame(2, $s['events']);
        $this->assertSame(1, $s['upcoming']);
        $this->assertSame(6, $s['registrations'], 'pending + approved, rejected excluded');
        $this->assertSame(5, $s['approved']);
        $this->assertSame(1, $s['pending']);
        $this->assertSame(4, $s['approved_held'], 'only events that already happened count for attendance');
        $this->assertSame(3, $s['attended']);
        $this->assertSame(75.0, $s['attendance_rate']);
        $this->assertSame(83.3, $s['approval_rate'], '5 approved of 6 decided');
        $this->assertSame(4.5, $s['rating_avg']);
        $this->assertSame(2, $s['rating_count']);
        $this->assertSame(1, $s['waiting']);
        $this->assertSame(6, $s['people']);
    }

    public function testEmptyPeriodHasNoRates(): void
    {
        $s = report_summary($this->db, report_filter(['range' => 'all']));

        $this->assertSame(0, $s['events']);
        $this->assertNull($s['attendance_rate']);
        $this->assertNull($s['approval_rate']);
        $this->assertNull($s['rating_avg']);
        $this->assertSame([], report_registrations_by_month($this->db, report_filter(['range' => 'all'])));
        $this->assertSame([5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0], report_rating_distribution($this->db, report_filter([])));
    }

    public function testOrganiserFilterOnlySeesTheirOwnEvents(): void
    {
        $mine = $this->makeUser('Me');
        $other = $this->makeUser('Someone else');
        $this->pastEvent($mine, ['event_name' => 'Mine']);
        $this->pastEvent($other, ['event_name' => 'Theirs']);

        $f = report_filter(['range' => 'all'], $mine);

        $this->assertSame(['Mine'], array_column(report_events($this->db, $f), 'event_name'));
        $this->assertSame(1, report_summary($this->db, $f)['events']);
        $this->assertSame(2, report_summary($this->db, $f)['rating_count']);
    }

    public function testDateRangeAppliesToTheEventDate(): void
    {
        $org = $this->makeUser();
        $this->pastEvent($org, ['event_name' => 'Old', 'event_date' => $this->day('-400 days')]);
        $this->pastEvent($org, ['event_name' => 'Recent', 'event_date' => $this->day('-20 days')]);
        $this->makeEvent($org, ['event_name' => 'Upcoming']);

        $names = array_column(report_events($this->db, report_filter(['range' => '12m'])), 'event_name');
        $this->assertEqualsCanonicalizing(['Recent', 'Upcoming'], $names);

        $custom = report_filter(['range' => 'custom', 'to' => $this->day('-100 days')]);
        $this->assertSame(['Old'], array_column(report_events($this->db, $custom), 'event_name'));
    }

    public function testPerEventRowsAndRates(): void
    {
        $org = $this->makeUser('Olivia');
        $past = $this->pastEvent($org, ['event_name' => 'Past']);
        $future = $this->makeEvent($org, ['event_name' => 'Future', 'max_participants' => 0]);
        $this->register($this->makeUser(), $future, 'Approved');

        $rows = array_column(report_events($this->db, report_filter(['range' => 'all'])), null, 'event_name');

        $this->assertSame(['Past', 'Future'], array_keys($rows), 'most registrations first');
        $p = $rows['Past'];
        $this->assertSame([$past, 5, 4, 3, 10, 50.0, 75.0, 4.5, 2, 'Olivia'],
            [$p['event_id'], $p['registrations'], $p['approved'], $p['attended'], $p['capacity'], $p['fill_rate'],
             $p['attendance_rate'], $p['rating_avg'], $p['rating_count'], $p['organiser']]);
        $this->assertNull($rows['Future']['fill_rate'], 'no capacity, no fill rate');
        $this->assertNull($rows['Future']['attendance_rate'], 'not held yet');
        $this->assertFalse($rows['Future']['held']);
    }

    public function testMonthlySeriesFillsGapsAndCapsLength(): void
    {
        $event = $this->makeEvent($this->makeUser(), ['event_date' => $this->day('+5 days')]);
        foreach (['2026-01-15', '2026-01-20', '2026-03-02'] as $i => $d) {
            $this->insert('registrations', ['user_id' => $this->makeUser("R$i"), 'event_id' => $event, 'registration_date' => "$d 10:00:00"]);
        }
        $this->insert('registrations', ['user_id' => $this->makeUser('No'), 'event_id' => $event,
                                        'approval_status' => 'Rejected', 'registration_date' => '2026-04-01 10:00:00']);
        $f = report_filter(['range' => 'all']);

        $this->assertSame([
            ['month' => '2026-01', 'label' => 'Jan 2026', 'count' => 2],
            ['month' => '2026-02', 'label' => 'Feb 2026', 'count' => 0],
            ['month' => '2026-03', 'label' => 'Mar 2026', 'count' => 1],
        ], report_registrations_by_month($this->db, $f));

        $capped = report_registrations_by_month($this->db, $f, 2);
        $this->assertSame(['2026-02', '2026-03'], array_column($capped, 'month'));
    }

    public function testCategoryAndRatingBreakdowns(): void
    {
        $this->pastEvent($this->makeUser());
        $f = report_filter(['range' => 'all']);

        $this->assertSame([['label' => 'Student', 'count' => 4], ['label' => 'Staff', 'count' => 1]], report_by_category($this->db, $f));
        $this->assertSame([5 => 1, 4 => 1, 3 => 0, 2 => 0, 1 => 0], report_rating_distribution($this->db, $f));
    }
}
