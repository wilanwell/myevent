<?php

namespace MyEvent\Tests\Integration;

use MyEvent\Tests\Support\DatabaseTestCase;
use MyEvent\Tests\Support\MailSpy;

final class WaitlistTest extends DatabaseTestCase
{
    public function testAvailabilityCountsPendingAndApprovedButNotRejected(): void
    {
        $org = $this->makeUser('Organiser');
        $event = $this->makeEvent($org, ['max_participants' => 3]);
        $this->register($this->makeUser(), $event, 'Pending');
        $this->register($this->makeUser(), $event, 'Approved');
        $this->register($this->makeUser(), $event, 'Rejected');

        $state = event_availability($this->db, $event);

        $this->assertSame(2, $state['taken']);
        $this->assertSame(1, $state['left']);
        $this->assertFalse($state['full']);
        $this->assertTrue($state['open']);
    }

    public function testFullClosedAndUnlimitedEvents(): void
    {
        $org = $this->makeUser('Organiser');
        $full = $this->makeEvent($org, ['max_participants' => 1]);
        $this->register($this->makeUser(), $full, 'Approved');
        $closed = $this->makeEvent($org, ['reg_close_date' => date('Y-m-d', strtotime('-1 day'))]);
        $unlimited = $this->makeEvent($org, ['max_participants' => 0]);

        $this->assertTrue(event_availability($this->db, $full)['full']);
        $this->assertFalse(event_availability($this->db, $closed)['open']);
        $this->assertFalse(event_availability($this->db, $unlimited)['full']);
        $this->assertNull(event_availability($this->db, $unlimited)['left']);
        $this->assertNull(event_availability($this->db, 999999));
    }

    public function testRegistrationStaysOpenOnTheEventDayWithoutACloseDate(): void
    {
        $event = $this->makeEvent($this->makeUser(), ['event_date' => date('Y-m-d')]);
        $this->assertTrue(event_availability($this->db, $event)['open']);
    }

    public function testWaitlistPositionIsFirstComeFirstServed(): void
    {
        $event = $this->makeEvent($this->makeUser('Organiser'), ['max_participants' => 1]);
        [$a, $b, $c] = [$this->makeUser('A'), $this->makeUser('B'), $this->makeUser('C')];
        $this->joinWaitlist($b, $event, 30);
        $this->joinWaitlist($a, $event, 60);
        $this->joinWaitlist($c, $event, 30); // same second as B, joined after B

        $this->assertSame(1, waitlist_position($this->db, $event, $a));
        $this->assertSame(2, waitlist_position($this->db, $event, $b));
        $this->assertSame(3, waitlist_position($this->db, $event, $c));
        $this->assertNull(waitlist_position($this->db, $event, $this->makeUser('Not waiting')));
        $this->assertSame(3, waitlist_count($this->db, $event));
    }

    public function testFreedPlaceGoesToTheFirstPersonInLine(): void
    {
        $org = $this->makeUser('Olivia Organiser');
        $event = $this->makeEvent($org, ['max_participants' => 2, 'event_name' => 'Robotics Day']);
        $this->register($this->makeUser(), $event, 'Approved');
        $leaving = $this->register($this->makeUser(), $event, 'Approved');
        $first = $this->makeUser('Farah First');
        $second = $this->makeUser('Sam Second');
        $this->joinWaitlist($first, $event, 120);
        $this->joinWaitlist($second, $event, 60);

        $this->assertSame(0, promote_waitlist($this->db, $event), 'nothing moves while the event is full');

        $this->db->query("DELETE FROM registrations WHERE reg_id = $leaving");
        $this->assertSame(1, promote_waitlist($this->db, $event));

        $this->assertSame('Pending', db_value($this->db, 'SELECT approval_status FROM registrations WHERE user_id = ? AND event_id = ?', 'ii', [$first, $event]));
        $this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM waitlist WHERE user_id = ?', 'i', [$first]));
        $this->assertSame(1, waitlist_position($this->db, $event, $second));
        $this->assertTrue(event_availability($this->db, $event)['full']);
    }

    public function testPromotionNotifiesTheParticipantAndTheOrganiser(): void
    {
        $org = $this->makeUser('Olivia Organiser', ['email' => 'olivia@example.test']);
        $event = $this->makeEvent($org, ['max_participants' => 1, 'event_name' => 'Robotics Day']);
        $farah = $this->makeUser('Farah First', ['email' => 'farah@example.test']);
        $this->joinWaitlist($farah, $event);

        promote_waitlist($this->db, $event);

        $this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM notifications WHERE user_id = ?', 'i', [$farah]));
        $this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM notifications WHERE user_id = ?', 'i', [$org]));
        $this->assertEqualsCanonicalizing(['farah@example.test', 'olivia@example.test'], MailSpy::recipients());
        $this->assertSame(1, $this->countRows("SELECT COUNT(*) FROM audit_log WHERE action = 'waitlist.promote' AND target_id = ?", 'i', [$event]));
    }

    public function testUnlimitedEventPromotesEveryone(): void
    {
        $event = $this->makeEvent($this->makeUser(), ['max_participants' => 0]);
        foreach ([1, 2, 3] as $i) {
            $this->joinWaitlist($this->makeUser("W$i"), $event, 10 * $i);
        }

        $this->assertSame(3, promote_waitlist($this->db, $event));
        $this->assertSame(0, waitlist_count($this->db, $event));
    }

    public function testClosedRegistrationKeepsPeopleWaiting(): void
    {
        $event = $this->makeEvent($this->makeUser(), [
            'max_participants' => 5,
            'reg_close_date'   => date('Y-m-d', strtotime('-1 day')),
        ]);
        $this->joinWaitlist($this->makeUser(), $event);

        $this->assertSame(0, promote_waitlist($this->db, $event));
        $this->assertSame(1, waitlist_count($this->db, $event));
    }

    public function testSomeoneAlreadyRegisteredIsDroppedFromTheLineWithoutUsingAPlace(): void
    {
        $event = $this->makeEvent($this->makeUser(), ['max_participants' => 2]);
        $dup = $this->makeUser('Already In');
        $this->register($dup, $event, 'Approved');
        $this->joinWaitlist($dup, $event, 60);
        $next = $this->makeUser('Next');
        $this->joinWaitlist($next, $event, 30);

        $this->assertSame(1, promote_waitlist($this->db, $event));
        $this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM registrations WHERE user_id = ?', 'i', [$dup]));
        $this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM registrations WHERE user_id = ?', 'i', [$next]));
        $this->assertSame(0, waitlist_count($this->db, $event));
    }

    public function testDatabaseRejectsDuplicateRegistrations(): void
    {
        $event = $this->makeEvent($this->makeUser());
        $user = $this->makeUser();
        $this->register($user, $event);

        $this->expectException(\mysqli_sql_exception::class);
        $this->register($user, $event);
    }
}
