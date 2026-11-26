<?php

namespace MyEvent\Tests\Integration;

use MyEvent\Tests\Support\DatabaseTestCase;
use MyEvent\Tests\Support\MailSpy;

final class NotificationTest extends DatabaseTestCase
{
    private function email(array $overrides = []): array
    {
        return $overrides + ['subject' => 'Subject', 'heading' => 'Heading', 'intro' => 'Intro text.'];
    }

    public function testNotifyUserWritesTheBellNotificationAndSendsAPersonalEmail(): void
    {
        $user = $this->makeUser('Nur Aisyah Ali', ['email' => 'aisyah@example.test']);

        notify_user($this->db, $user, 'Approved <strong>Robotics Day</strong>', 'participant', $this->email([
            'button' => ['View my ticket', 'https://example.test/ticket'],
            'facts'  => ['Where' => 'Hall A'],
        ]));

        $this->assertSame('Approved <strong>Robotics Day</strong>', db_value($this->db, 'SELECT message FROM notifications WHERE user_id = ?', 'i', [$user]));
        $mail = MailSpy::messages()[0];
        $this->assertSame('aisyah@example.test', $mail['to']);
        $this->assertStringStartsWith("Hi Nur,\n\nIntro text.", $mail['text']);
        $this->assertStringContainsString('Where: Hall A', $mail['text']);
        $this->assertStringContainsString('View my ticket: https://example.test/ticket', $mail['text']);
        $this->assertStringContainsString('users/profile.php#email-settings', $mail['text']);
        $this->assertStringContainsString('View my ticket', (string) $mail['html']);
    }

    public function testInAppOnlyNotificationSendsNoEmail(): void
    {
        notify_user($this->db, $this->makeUser(), 'Just the bell');

        $this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM notifications'));
        $this->assertSame([], MailSpy::messages());
    }

    public function testEmailPreferencesAreRespectedPerCategory(): void
    {
        $noParticipantMail = $this->makeUser('Quiet', ['email_participant' => 0, 'email_organiser' => 1]);
        $noOrganiserMail = $this->makeUser('Busy', ['email_participant' => 1, 'email_organiser' => 0, 'email' => 'busy@example.test']);

        notify_user($this->db, $noParticipantMail, 'x', 'participant', $this->email());
        notify_user($this->db, $noOrganiserMail, 'x', 'organiser', $this->email());
        $this->assertSame([], MailSpy::messages(), 'switched-off categories send nothing');
        $this->assertSame(2, $this->countRows('SELECT COUNT(*) FROM notifications'), 'but the bell still shows them');

        notify_user($this->db, $noOrganiserMail, 'x', 'participant', $this->email());
        $this->assertSame(['busy@example.test'], MailSpy::recipients());
    }

    public function testAudienceIncludesRegisteredAndWaitingButNotRejected(): void
    {
        $event = $this->makeEvent($this->makeUser('Organiser'));
        $pending = $this->makeUser();
        $approved = $this->makeUser();
        $rejected = $this->makeUser();
        $waiting = $this->makeUser();
        $this->register($pending, $event, 'Pending');
        $this->register($approved, $event, 'Approved');
        $this->register($rejected, $event, 'Rejected');
        $this->joinWaitlist($waiting, $event);

        $this->assertEqualsCanonicalizing([$pending, $approved, $waiting], event_audience($this->db, $event));
    }

    public function testCancellingAnUpcomingEventTellsEveryoneInvolved(): void
    {
        $event = $this->makeEvent($this->makeUser('Organiser'), ['event_name' => 'Hackathon']);
        $this->register($this->makeUser('', ['email' => 'p@example.test']), $event, 'Approved');
        $this->joinWaitlist($this->makeUser('', ['email' => 'w@example.test']), $event);

        $this->assertSame(2, notify_event_cancelled($this->db, $event));
        $this->assertEqualsCanonicalizing(['p@example.test', 'w@example.test'], MailSpy::recipients());
        $this->assertSame('Cancelled: Hackathon', MailSpy::messages()[0]['subject']);
    }

    public function testCancellingAPastEventSendsNothing(): void
    {
        $event = $this->makeEvent($this->makeUser(), ['event_date' => date('Y-m-d', strtotime('-3 days'))]);
        $this->register($this->makeUser(), $event, 'Approved');

        $this->assertSame(0, notify_event_cancelled($this->db, $event));
        $this->assertSame([], MailSpy::messages());
    }

    public function testChangeNoticeListsWhatChangedAndIgnoresCosmeticEdits(): void
    {
        $event = $this->makeEvent($this->makeUser());
        $this->register($this->makeUser(), $event, 'Approved');
        $before = db_all($this->db, 'SELECT * FROM events WHERE event_id = ?', 'i', [$event])[0];

        $this->assertSame(0, notify_event_changed($this->db, $before, ['description' => 'New text', 'event_time' => '09:00'] + $before),
            'a description change, or the same time without seconds, is not news');

        $after = ['venue' => 'Hall B', 'event_time' => '10:00:00'] + $before;
        $this->assertSame(1, notify_event_changed($this->db, $before, $after));
        $this->assertStringContainsString('start time, venue', MailSpy::messages()[0]['text']);
    }

    public function testRemindersGoOutOnceToApprovedParticipantsOfEventsWithin24Hours(): void
    {
        $org = $this->makeUser('Organiser');
        $soon = $this->makeEvent($org, ['event_name' => 'Soon', 'event_date' => date('Y-m-d', strtotime('+20 hours')),
                                        'event_time' => date('H:i:s', strtotime('+20 hours'))]);
        $later = $this->makeEvent($org, ['event_name' => 'Later']); // a week away
        $approved = $this->makeUser('Approved', ['email' => 'approved@example.test']);
        $this->register($approved, $soon, 'Approved');
        $this->register($this->makeUser('Pending'), $soon, 'Pending');
        $this->register($this->makeUser('Too early'), $later, 'Approved');

        $this->assertSame(1, queue_event_reminders($this->db));
        $this->assertSame(['approved@example.test'], MailSpy::recipients());
        $this->assertStringStartsWith('Reminder: Soon', MailSpy::messages()[0]['subject']);

        $this->assertSame(0, queue_event_reminders($this->db), 'never remind twice');
        $this->assertCount(1, MailSpy::messages());
    }

    public function testRemindersSkipEventsThatAlreadyStarted(): void
    {
        $started = $this->makeEvent($this->makeUser(), ['event_date' => date('Y-m-d', strtotime('-1 hour')),
                                                        'event_time' => date('H:i:s', strtotime('-1 hour'))]);
        $this->register($this->makeUser(), $started, 'Approved');

        $this->assertSame(0, queue_event_reminders($this->db));
    }
    public function testNotificationsAreWrittenInTheRecipientsLanguage(): void
    {
        $malay = $this->makeUser('Nur Aisyah', ['email' => 'aisyah@example.test', 'locale' => 'ms']);
        $english = $this->makeUser('John Smith', ['email' => 'john@example.test', 'locale' => 'en']);
        $event = $this->makeEvent($this->makeUser('Organiser'), ['event_name' => 'Hackathon', 'event_date' => '2030-03-04', 'event_time' => '09:00:00']);
        $this->register($malay, $event, 'Approved');
        $this->register($english, $event, 'Approved');

        set_locale('en'); // the organiser cancelling uses English
        notify_event_cancelled($this->db, $event);

        $this->assertStringContainsString('telah dibatalkan oleh penganjur', (string) db_value($this->db, 'SELECT message FROM notifications WHERE user_id = ?', 'i', [$malay]));
        $this->assertStringContainsString('has been cancelled by the organiser', (string) db_value($this->db, 'SELECT message FROM notifications WHERE user_id = ?', 'i', [$english]));

        $byRecipient = array_column(MailSpy::messages(), null, 'to');
        $ms = $byRecipient['aisyah@example.test'];
        $this->assertSame('Dibatalkan: Hackathon', $ms['subject']);
        $this->assertStringStartsWith('Hai Nur,', $ms['text']);
        $this->assertStringContainsString('Bila: Isnin, 4 Mac 2030, 9:00 PG', $ms['text']);
        $this->assertStringContainsString('<html lang="ms">', (string) $ms['html']);
        $this->assertStringContainsString('Tukar tetapan e-mel', (string) $ms['html']);

        $en = $byRecipient['john@example.test'];
        $this->assertSame('Cancelled: Hackathon', $en['subject']);
        $this->assertStringContainsString('When: Monday, 4 March 2030, 9:00 AM', $en['text']);
        $this->assertSame('en', current_locale(), 'the language of the sender is restored');
    }

    public function testAccountsWithoutALanguageGetTheDefault(): void
    {
        $user = $this->makeUser('No Choice', ['locale' => null]);
        set_locale('ms'); // whoever triggers it browses in Malay
        notify_user($this->db, $user, fn() => __('Dashboard'));
        set_locale('en');

        $this->assertSame('Dashboard', db_value($this->db, 'SELECT message FROM notifications WHERE user_id = ?', 'i', [$user]));
    }
}
