<?php
/**
 * MyEvent - Notifications: one call writes the in-app notification and, when the person
 * allows it, queues a matching email.
 *
 * Categories decide which email preference applies:
 *   'participant'  my registrations, waitlist, reminders, changes to events I joined
 *   'organiser'    new registrations / waitlist moves for events I organise
 */

/**
 * @param string     $inApp    Short message for the bell (may contain <strong> markers)
 * @param array|null $email    ['subject', 'heading', 'intro', 'button' => [label, url], 'facts' => [...], 'footnote']
 */
function notify_user(mysqli $conn, int $userId, string $inApp, string $category = 'participant', ?array $email = null): void
{
    $stmt = $conn->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
    $stmt->bind_param('is', $userId, $inApp);
    $stmt->execute();
    $stmt->close();

    if ($email === null) {
        return;
    }
    $column = $category === 'organiser' ? 'email_organiser' : 'email_participant';
    $user = db_all($conn, "SELECT name, email, $column AS allowed FROM users WHERE user_id = ?", 'i', [$userId])[0] ?? null;
    if (!$user || !(int) $user['allowed']) {
        return;
    }

    [$label, $url] = $email['button'] ?? [null, null];
    $facts = $email['facts'] ?? [];
    $first = strtok((string) $user['name'], ' ') ?: 'there';

    $text = "Hi {$first},\n\n" . $email['intro'] . "\n";
    foreach ($facts as $k => $v) {
        $text .= "\n{$k}: {$v}";
    }
    if ($label && $url) {
        $text .= "\n\n{$label}: {$url}";
    }
    if (!empty($email['footnote'])) {
        $text .= "\n\n" . $email['footnote'];
    }
    $text .= "\n\n- " . APP_NAME . "\nChange email settings: " . BASE_URL . "users/profile.php#email-settings\n";

    $html = email_html($email['heading'], "Hi {$first},\n\n" . $email['intro'], $label, $url, $facts, $email['footnote'] ?? '');
    queue_mail($conn, $user['email'], $email['subject'], $text, $html);
}

/** "When" and "Where" rows for an event row (event_date, event_time, end_time?, venue, mode?). */
function event_facts(array $event): array
{
    $when = date('l, j F Y', strtotime($event['event_date'])) . ', ' . date('g:i A', strtotime($event['event_time']));
    if (!empty($event['end_time'])) {
        $when .= ' to ' . date('g:i A', strtotime($event['end_time']));
    }
    return ['When' => $when, 'Where' => $event['venue'] . (!empty($event['mode']) ? ' (' . $event['mode'] . ')' : '')];
}

function event_link(int $eventId): string
{
    return BASE_URL . 'event_detail.php?id=' . $eventId;
}

/** Everyone holding or waiting for a place: [user_id, ...] (excludes rejected registrations). */
function event_audience(mysqli $conn, int $eventId): array
{
    return array_map('intval', array_unique(array_merge(
        array_column(db_all($conn, "SELECT user_id FROM registrations WHERE event_id = ? AND approval_status <> 'Rejected'", 'i', [$eventId]), 'user_id'),
        array_column(db_all($conn, 'SELECT user_id FROM waitlist WHERE event_id = ?', 'i', [$eventId]), 'user_id')
    )));
}

/** Tell registered and waitlisted people that an event is being cancelled. Call BEFORE deleting it. */
function notify_event_cancelled(mysqli $conn, int $eventId): int
{
    $event = db_all($conn, 'SELECT event_id, event_name, event_date, event_time, end_time, venue, mode FROM events WHERE event_id = ?', 'i', [$eventId])[0] ?? null;
    if (!$event || $event['event_date'] < date('Y-m-d')) {
        return 0; // past events: nothing to warn about
    }
    $people = event_audience($conn, $eventId);
    foreach ($people as $uid) {
        notify_user($conn, $uid, "❌ <strong>{$event['event_name']}</strong> has been cancelled by the organiser.", 'participant', [
            'subject' => 'Cancelled: ' . $event['event_name'],
            'heading' => 'This event has been cancelled',
            'intro'   => "The organiser has cancelled {$event['event_name']}. Your registration has been removed, so there is nothing you need to do.",
            'facts'   => event_facts($event),
            'button'  => ['Find another event', BASE_URL . 'browse_events.php'],
        ]);
    }
    return count($people);
}

/** Tell people when the date, time or place of an event they joined changes. */
function notify_event_changed(mysqli $conn, array $before, array $after): int
{
    $labels = ['event_date' => 'date', 'event_time' => 'start time', 'end_time' => 'end time', 'venue' => 'venue', 'mode' => 'format'];
    $changed = [];
    foreach ($labels as $field => $label) {
        $old = substr((string) ($before[$field] ?? ''), 0, $field === 'event_time' || $field === 'end_time' ? 5 : 255);
        $new = substr((string) ($after[$field] ?? ''), 0, $field === 'event_time' || $field === 'end_time' ? 5 : 255);
        if ($old !== $new) {
            $changed[] = $label;
        }
    }
    if (!$changed || $after['event_date'] < date('Y-m-d')) {
        return 0;
    }
    $what = implode(', ', $changed);
    $people = event_audience($conn, (int) $after['event_id']);
    foreach ($people as $uid) {
        notify_user($conn, $uid, "📅 The {$what} of <strong>{$after['event_name']}</strong> changed. Check the new details.", 'participant', [
            'subject' => 'Updated: ' . $after['event_name'],
            'heading' => 'Event details have changed',
            'intro'   => "The organiser changed the {$what} of {$after['event_name']}. Here are the new details.",
            'facts'   => event_facts($after),
            'button'  => ['View the event', event_link((int) $after['event_id'])],
            'footnote' => 'If you added this event to your calendar, update it there too.',
        ]);
    }
    return count($people);
}

/**
 * Queue a reminder for approved participants of events starting in the next 24 hours.
 * Each registration gets one reminder. Returns how many were queued.
 */
function queue_event_reminders(mysqli $conn): int
{
    $due = db_all($conn,
        "SELECT r.reg_id, r.user_id, e.event_id, e.event_name, e.event_date, e.event_time, e.end_time, e.venue, e.mode
         FROM registrations r JOIN events e ON e.event_id = r.event_id
         WHERE r.approval_status = 'Approved' AND r.reminder_sent_at IS NULL
           AND TIMESTAMP(e.event_date, e.event_time) BETWEEN NOW() AND NOW() + INTERVAL 24 HOUR");
    foreach ($due as $row) {
        $mark = $conn->prepare('UPDATE registrations SET reminder_sent_at = NOW() WHERE reg_id = ? AND reminder_sent_at IS NULL');
        $mark->bind_param('i', $row['reg_id']);
        $mark->execute();
        $claimed = $mark->affected_rows === 1;
        $mark->close();
        if (!$claimed) {
            continue;
        }
        $when = date('g:i A', strtotime($row['event_time']));
        $day = $row['event_date'] === date('Y-m-d') ? 'today' : 'tomorrow';
        notify_user($conn, (int) $row['user_id'], "⏰ Reminder: <strong>{$row['event_name']}</strong> starts {$day} at {$when}.", 'participant', [
            'subject' => "Reminder: {$row['event_name']} {$day} at {$when}",
            'heading' => "See you {$day}!",
            'intro'   => "{$row['event_name']} starts {$day} at {$when}. Bring your ticket: the organiser scans its QR code at the entrance.",
            'facts'   => event_facts($row),
            'button'  => ['Open my ticket', BASE_URL . 'users/generate_ticket.php?event_id=' . (int) $row['event_id']],
        ]);
    }
    return count($due);
}
