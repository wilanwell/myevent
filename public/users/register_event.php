<?php
/**
 * MyEvent - Register for an event, or join / leave its waitlist (POST only).
 *
 * Rules: the event exists, registration is still open, organisers cannot join their
 * own event, one registration per person, and capacity is respected. The event row is
 * locked while checking, so two people cannot both take the last place.
 * If the last place goes while someone is registering, they are put on the waitlist.
 */
require_once __DIR__ . '/../../config.php';

require_post();
if (!is_logged_in()) {
    flash('info', __('Please log in to register for this event.'));
    redirect('auth/login.php');
}

$user_id  = current_user_id();
$event_id = (int) ($_POST['event_id'] ?? 0);
$action   = (string) ($_POST['action'] ?? 'register');
$back     = 'event_detail.php?id=' . $event_id;

/* ---------- Leave the waitlist ---------- */
if ($action === 'leave_waitlist') {
    $stmt = $conn->prepare('DELETE FROM waitlist WHERE event_id = ? AND user_id = ?');
    $stmt->bind_param('ii', $event_id, $user_id);
    $stmt->execute();
    $left = $stmt->affected_rows > 0;
    $stmt->close();
    flash($left ? 'success' : 'info', $left ? __('You have left the waitlist.') : __('You were not on the waitlist for this event.'));
    redirect(($_POST['return'] ?? '') === 'registrations' ? 'users/my_registrations.php' : $back);
}

$conn->begin_transaction();
try {
    $event = event_availability($conn, $event_id, true);

    $error = null;
    $already = false;
    if (!$event) {
        $error = __('Event not found.');
    } elseif ((int) $event['created_by'] === $user_id) {
        $error = __('You cannot register for an event you organise.');
    } elseif (!$event['open']) {
        $error = __('Registration for this event has closed.');
    } elseif ($status = db_value($conn, 'SELECT approval_status FROM registrations WHERE event_id = ? AND user_id = ?', 'ii', [$event_id, $user_id])) {
        $already = $status !== 'Rejected';
        $error = $already
            ? __('You are already registered for this event.')
            : __('The organiser did not approve your registration for this event.');
    }

    if ($error) {
        $conn->rollback();
        flash($already ? 'info' : 'error', $error);
        redirect($event ? $back : 'index.php');
    }

    // Full (or the user explicitly asked): join the waitlist instead.
    if ($event['full'] || $action === 'join_waitlist') {
        if (!$event['full']) {
            // A place is free after all: register normally below.
            $action = 'register';
        } else {
            $stmt = $conn->prepare('INSERT IGNORE INTO waitlist (event_id, user_id) VALUES (?, ?)');
            $stmt->bind_param('ii', $event_id, $user_id);
            $stmt->execute();
            $stmt->close();
            $conn->commit();

            $pos = waitlist_position($conn, $event_id, $user_id);
            flash('success', ($action === 'join_waitlist'
                    ? __('You joined the waitlist. You are number {n} in line.', ['n' => $pos])
                    : __('The last place has just gone, so you joined the waitlist. You are number {n} in line.', ['n' => $pos]))
                . ' ' . __('We will register you automatically if a place opens up.'));
            redirect($back);
        }
    }

    $stmt = $conn->prepare("INSERT INTO registrations (user_id, event_id, approval_status) VALUES (?, ?, 'Pending')");
    $stmt->bind_param('ii', $user_id, $event_id);
    $stmt->execute();
    $stmt->close();

    // In case they were waiting and a place opened before promotion ran.
    $stmt = $conn->prepare('DELETE FROM waitlist WHERE event_id = ? AND user_id = ?');
    $stmt->bind_param('ii', $event_id, $user_id);
    $stmt->execute();
    $stmt->close();

    if (!empty($event['created_by'])) {
        $student = $_SESSION['name'] ?? '';
        $name = $event['event_name'];
        notify_user($conn, (int) $event['created_by'],
            fn() => '📩 ' . __('{name} has registered for: {event}.', ['name' => '<strong>' . ($student ?: __('A participant')) . '</strong>', 'event' => "<strong>{$name}</strong>"]),
            'organiser',
            fn() => [
                'subject' => __('New registration: {event}', ['event' => $name]),
                'heading' => __('Someone registered for your event'),
                'intro'   => __('{name} registered for {event} and is waiting for your approval.', ['name' => $student ?: __('A participant'), 'event' => $name])
                           . ($event['capacity'] > 0 ? ' ' . __('{taken} of {capacity} places are now taken.', ['taken' => $event['taken'] + 1, 'capacity' => $event['capacity']]) : ''),
                'button'  => [__('Review participants'), BASE_URL . 'users/manage_participants.php?event_id=' . $event_id],
            ]);
    }
    $conn->commit();
} catch (Throwable $ex) {
    $conn->rollback();
    throw $ex;
}

flash('success', __('Registration submitted! You will be notified once the organiser reviews it.'));
redirect('users/my_registrations.php');
