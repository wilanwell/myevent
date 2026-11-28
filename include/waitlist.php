<?php
/**
 * MyEvent - Capacity, registration windows and the waitlist.
 *
 * Places are held by Pending and Approved registrations. When an event is full but
 * registration is still open, people can join a first-come-first-served waitlist.
 * Whenever a place frees up (cancellation, rejection, higher capacity, deleted account)
 * call promote_waitlist(): the first person in line becomes a Pending registration
 * and is notified; the organiser still approves as usual.
 */

/** Places taken, capacity and whether registration is open / full. Lock the event row when $forUpdate. */
function event_availability(mysqli $conn, int $eventId, bool $forUpdate = false): ?array
{
    $event = db_all($conn,
        'SELECT event_id, event_name, created_by, event_date, event_time, end_time, venue, mode, reg_close_date, max_participants
         FROM events WHERE event_id = ?' . ($forUpdate ? ' FOR UPDATE' : ''), 'i', [$eventId])[0] ?? null;
    if (!$event) {
        return null;
    }
    $taken = (int) db_value($conn,
        "SELECT COUNT(*) FROM registrations WHERE event_id = ? AND approval_status <> 'Rejected'", 'i', [$eventId]);
    $capacity = (int) $event['max_participants'];
    $closeDate = $event['reg_close_date'] ?: $event['event_date'];

    return $event + [
        'taken'    => $taken,
        'capacity' => $capacity,
        'open'     => $closeDate >= date('Y-m-d'),
        'full'     => $capacity > 0 && $taken >= $capacity,
        'left'     => $capacity > 0 ? max(0, $capacity - $taken) : null,
    ];
}

/** 1-based place in the waitlist, or null when the user is not waiting. */
function waitlist_position(mysqli $conn, int $eventId, int $userId): ?int
{
    $mine = db_all($conn, 'SELECT waitlist_id, created_at FROM waitlist WHERE event_id = ? AND user_id = ?', 'ii', [$eventId, $userId])[0] ?? null;
    if (!$mine) {
        return null;
    }
    return 1 + (int) db_value($conn,
        'SELECT COUNT(*) FROM waitlist WHERE event_id = ? AND (created_at < ? OR (created_at = ? AND waitlist_id < ?))',
        'issi', [$eventId, $mine['created_at'], $mine['created_at'], (int) $mine['waitlist_id']]);
}

function waitlist_count(mysqli $conn, int $eventId): int
{
    return (int) db_value($conn, 'SELECT COUNT(*) FROM waitlist WHERE event_id = ?', 'i', [$eventId]);
}


/**
 * Move people from the waitlist into free places. Returns how many were promoted.
 * Safe to call any time; does nothing when the event is full, closed or nobody waits.
 */
function promote_waitlist(mysqli $conn, int $eventId): int
{
    $promoted = 0;
    $conn->begin_transaction();
    try {
        $state = event_availability($conn, $eventId, true);
        if (!$state || !$state['open']) {
            $conn->commit();
            return 0;
        }

        $free = $state['capacity'] > 0 ? $state['capacity'] - $state['taken'] : PHP_INT_MAX;
        while ($free > 0) {
            $next = db_all($conn,
                'SELECT w.waitlist_id, w.user_id FROM waitlist w
                 WHERE w.event_id = ? ORDER BY w.created_at, w.waitlist_id LIMIT 1 FOR UPDATE', 'i', [$eventId])[0] ?? null;
            if (!$next) {
                break;
            }

            $stmt = $conn->prepare('DELETE FROM waitlist WHERE waitlist_id = ?');
            $stmt->bind_param('i', $next['waitlist_id']);
            $stmt->execute();
            $stmt->close();

            // INSERT IGNORE: if they somehow registered already, just drop them from the line.
            $stmt = $conn->prepare("INSERT IGNORE INTO registrations (user_id, event_id, approval_status) VALUES (?, ?, 'Pending')");
            $stmt->bind_param('ii', $next['user_id'], $eventId);
            $stmt->execute();
            $added = $stmt->affected_rows > 0;
            $stmt->close();

            if ($added) {
                $free--;
                $promoted++;
                $name = (string) db_value($conn, 'SELECT name FROM users WHERE user_id = ?', 'i', [(int) $next['user_id']]);
                $event = $state['event_name'];
                notify_user($conn, (int) $next['user_id'],
                    fn() => '🎟️ ' . __('A place opened up for {event}. You moved off the waitlist and your registration is now waiting for the organiser\'s approval.', ['event' => "<strong>{$event}</strong>"]),
                    'participant',
                    fn() => [
                        'subject' => __('A place opened up: {event}', ['event' => $event]),
                        'heading' => __('You are off the waitlist'),
                        'intro'   => __('A place opened up for {event}, so you have been registered automatically. The organiser will review your registration and you will hear back once it is approved.', ['event' => $event]),
                        'facts'   => event_facts($state),
                        'button'  => [__('View my registrations'), BASE_URL . 'users/my_registrations.php'],
                    ]);
                if (!empty($state['created_by'])) {
                    notify_user($conn, (int) $state['created_by'],
                        fn() => '⬆️ ' . __('{name} moved from the waitlist into {event} and is waiting for your approval.', ['name' => "<strong>{$name}</strong>", 'event' => "<strong>{$event}</strong>"]),
                        'organiser',
                        fn() => [
                            'subject' => __('Waitlist: {name} moved into {event}', ['name' => $name, 'event' => $event]),
                            'heading' => __('Someone moved up from the waitlist'),
                            'intro'   => __('{name} was first in line and now holds a place in {event}. Their registration is waiting for your approval.', ['name' => $name, 'event' => $event]),
                            'button'  => [__('Review participants'), BASE_URL . 'users/manage_participants.php?event_id=' . $eventId],
                        ]);
                }
                audit($conn, 'waitlist.promote', 'event', $eventId, $name . ' → ' . $state['event_name']);
            }
        }
        $conn->commit();
    } catch (Throwable $ex) {
        $conn->rollback();
        throw $ex;
    }
    return $promoted;
}
