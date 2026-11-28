<?php
/**
 * MyEvent - Notifications: one call writes the in-app notification and, when the person
 * allows it, queues a matching email. Both are written in the recipient's language.
 *
 * Categories decide which email preference applies:
 *   'participant'  my registrations, waitlist, reminders, changes to events I joined
 *   'organiser'    new registrations / waitlist moves for events I organise
 */

/**
 * @param string|callable     $inApp  Short message for the bell (may contain <strong> markers),
 *                                    or a function returning it. Functions run in the recipient's language.
 * @param array|callable|null $email  ['subject', 'heading', 'intro', 'button' => [label, url], 'facts' => [...], 'footnote'],
 *                                    or a function returning that array (also run in the recipient's language)
 */
function notify_user(mysqli $conn, int $userId, $inApp, string $category = 'participant', $email = null): void
{
    $column = $category === 'organiser' ? 'email_organiser' : 'email_participant';
    $user = db_all($conn, "SELECT name, email, locale, $column AS allowed FROM users WHERE user_id = ?", 'i', [$userId])[0] ?? null;
    if (!$user) {
        return;
    }

    with_locale($user['locale'] ?: default_locale(), static function () use ($conn, $userId, $inApp, $email, $user) {
        $message = is_callable($inApp) ? $inApp() : (string) $inApp;
        $stmt = $conn->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
        $stmt->bind_param('is', $userId, $message);
        $stmt->execute();
        $stmt->close();

        if ($email === null || !(int) $user['allowed']) {
            return;
        }
        $email = is_callable($email) ? $email() : $email;

        [$label, $url] = $email['button'] ?? [null, null];
        $facts = $email['facts'] ?? [];
        $first = strtok((string) $user['name'], ' ') ?: __('there');
        $greeting = __('Hi {name},', ['name' => $first]);

        $text = $greeting . "\n\n" . $email['intro'] . "\n";
        foreach ($facts as $k => $v) {
            $text .= "\n{$k}: {$v}";
        }
        if ($label && $url) {
            $text .= "\n\n{$label}: {$url}";
        }
        if (!empty($email['footnote'])) {
            $text .= "\n\n" . $email['footnote'];
        }
        $text .= "\n\n- " . APP_NAME . "\n" . __('Change email settings') . ': ' . BASE_URL . "users/profile.php#email-settings\n";

        $html = email_html($email['heading'], $greeting . "\n\n" . $email['intro'], $label, $url, $facts, $email['footnote'] ?? '');
        queue_mail($conn, $user['email'], $email['subject'], $text, $html);
    });
}

/** "When" and "Where" rows for an event row (event_date, event_time, end_time?, venue, mode?), in the current language. */
function event_facts(array $event): array
{
    $when = ldate('l, j F Y', $event['event_date']) . ', ' . ldate('g:i A', $event['event_time']);
    if (!empty($event['end_time'])) {
        $when = ldate('l, j F Y', $event['event_date']) . ', '
              . __('{start} to {end}', ['start' => ldate('g:i A', $event['event_time']), 'end' => ldate('g:i A', $event['end_time'])]);
    }
    $where = $event['venue'] . (!empty($event['mode']) ? ' (' . status_label($event['mode']) . ')' : '');
    return [__('When') => $when, __('Where') => $where];
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
    $name = $event['event_name'];
    foreach ($people as $uid) {
        notify_user($conn, $uid,
            fn() => '❌ ' . __('{event} has been cancelled.', ['event' => "<strong>{$name}</strong>"]),
            'participant',
            fn() => [
                'subject' => __('Cancelled: {event}', ['event' => $name]),
                'heading' => __('This event has been cancelled'),
                'intro'   => __('{event} has been cancelled. Your registration has been removed, so there is nothing you need to do.', ['event' => $name]),
                'facts'   => event_facts($event),
                'button'  => [__('Find another event'), BASE_URL . 'browse_events.php'],
            ]);
    }
    return count($people);
}

/** Tell people when the date, time or place of an event they joined changes. */
function notify_event_changed(mysqli $conn, array $before, array $after): int
{
    $fields = ['event_date', 'event_time', 'end_time', 'venue', 'mode'];
    $changed = [];
    foreach ($fields as $field) {
        $len = $field === 'event_time' || $field === 'end_time' ? 5 : 255;
        if (substr((string) ($before[$field] ?? ''), 0, $len) !== substr((string) ($after[$field] ?? ''), 0, $len)) {
            $changed[] = $field;
        }
    }
    if (!$changed || $after['event_date'] < date('Y-m-d')) {
        return 0;
    }
    // Labels are translated per recipient, inside notify_user().
    $what = static function () use ($changed): string {
        $labels = ['event_date' => __('date'), 'event_time' => __('start time'), 'end_time' => __('end time'),
                   'venue' => __('venue'), 'mode' => __('format')];
        return implode(', ', array_map(static fn($f) => $labels[$f], $changed));
    };
    $name = $after['event_name'];
    $people = event_audience($conn, (int) $after['event_id']);
    foreach ($people as $uid) {
        notify_user($conn, $uid,
            fn() => '📅 ' . __('The {what} of {event} changed. Check the new details.', ['what' => $what(), 'event' => "<strong>{$name}</strong>"]),
            'participant',
            fn() => [
                'subject'  => __('Updated: {event}', ['event' => $name]),
                'heading'  => __('Event details have changed'),
                'intro'    => __('The {what} of {event} has changed. Here are the new details.', ['what' => $what(), 'event' => $name]),
                'facts'    => event_facts($after),
                'button'   => [__('View the event'), event_link((int) $after['event_id'])],
                'footnote' => __('If you added this event to your calendar, update it there too.'),
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
    $sent = 0;
    foreach ($due as $row) {
        $mark = $conn->prepare('UPDATE registrations SET reminder_sent_at = NOW() WHERE reg_id = ? AND reminder_sent_at IS NULL');
        $mark->bind_param('i', $row['reg_id']);
        $mark->execute();
        $claimed = $mark->affected_rows === 1;
        $mark->close();
        if (!$claimed) {
            continue;
        }
        $sent++;
        $today = $row['event_date'] === date('Y-m-d');
        $name = $row['event_name'];
        $vars = static fn() => ['event' => $name, 'time' => ldate('g:i A', $row['event_time'])];
        notify_user($conn, (int) $row['user_id'],
            fn() => '⏰ ' . ($today
                ? __('Reminder: {event} starts today at {time}.', ['event' => "<strong>{$name}</strong>"] + $vars())
                : __('Reminder: {event} starts tomorrow at {time}.', ['event' => "<strong>{$name}</strong>"] + $vars())),
            'participant',
            fn() => [
                'subject' => $today ? __('Reminder: {event} today at {time}', $vars()) : __('Reminder: {event} tomorrow at {time}', $vars()),
                'heading' => $today ? __('See you today!') : __('See you tomorrow!'),
                'intro'   => ($today ? __('{event} starts today at {time}.', $vars()) : __('{event} starts tomorrow at {time}.', $vars()))
                           . ' ' . __('Bring your ticket: the organiser scans its QR code at the entrance.'),
                'facts'   => event_facts($row),
                'button'  => [__('Open my ticket'), BASE_URL . 'users/generate_ticket.php?event_id=' . (int) $row['event_id']],
            ]);
    }
    return $sent;
}

/**
 * Tell an organiser that an administrator edited or deleted their event ($what: 'edited' | 'deleted' | 'assigned').
 * Nothing is sent when the organiser is the one acting, or the event has no organiser.
 */
function notify_organiser_of_admin_change(mysqli $conn, ?int $organiserId, string $eventName, string $what): void
{
    if (!$organiserId || $organiserId === current_user_id()) {
        return;
    }
    notify_user($conn, $organiserId,
        fn() => '🛡️ ' . match ($what) {
            'deleted'  => __('An administrator deleted your event {event}.', ['event' => "<strong>{$eventName}</strong>"]),
            'assigned' => __('An administrator made you the organiser of {event}. You can manage it from My events.', ['event' => "<strong>{$eventName}</strong>"]),
            default    => __('An administrator edited your event {event}.', ['event' => "<strong>{$eventName}</strong>"]),
        },
        'organiser');
}
