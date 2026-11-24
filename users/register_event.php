<?php
/**
 * MyEvent - Register the current user for an event (POST only).
 * Enforces: event exists, registration still open, capacity, no duplicates,
 * and organisers cannot register for their own event.
 */
require_once __DIR__ . '/../config.php';

require_post();
if (!is_logged_in()) {
    flash('info', 'Please log in to register for this event.');
    redirect('auth/login.php');
}

$user_id  = current_user_id();
$event_id = (int) ($_POST['event_id'] ?? 0);
$back     = 'event_detail.php?id=' . $event_id;

$stmt = $conn->prepare(
    "SELECT e.event_id, e.event_name, e.created_by, e.event_date, e.reg_close_date, e.max_participants,
            (SELECT COUNT(*) FROM registrations r
              WHERE r.event_id = e.event_id AND r.approval_status <> 'Rejected') AS taken
     FROM events e WHERE e.event_id = ?"
);
$stmt->bind_param('i', $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    flash('error', 'Event not found.');
    redirect('index.php');
}

$today = date('Y-m-d');
$closeDate = $event['reg_close_date'] ?: $event['event_date'];

if ((int) $event['created_by'] === $user_id) {
    flash('error', 'You cannot register for an event you organise.');
    redirect($back);
}
if ($closeDate && $closeDate < $today) {
    flash('error', 'Registration for this event has closed.');
    redirect($back);
}
if ((int) $event['max_participants'] > 0 && (int) $event['taken'] >= (int) $event['max_participants']) {
    flash('error', 'Sorry, this event is already full.');
    redirect($back);
}

try {
    $stmt = $conn->prepare("INSERT INTO registrations (user_id, event_id, approval_status) VALUES (?, ?, 'Pending')");
    $stmt->bind_param('ii', $user_id, $event_id);
    $stmt->execute();
    $stmt->close();
} catch (mysqli_sql_exception $ex) {
    if ($ex->getCode() === 1062) { // duplicate key: already registered
        flash('info', 'You are already registered for this event.');
        redirect('users/my_registrations.php');
    }
    throw $ex;
}

// Notify the organiser. Names are stored as plain text; render_message() escapes them on display.
if (!empty($event['created_by'])) {
    $student = $_SESSION['name'] ?? 'A participant';
    $msg = "📩 <strong>{$student}</strong> has registered for: <strong>{$event['event_name']}</strong>.";
    $stmt = $conn->prepare('INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)');
    $organizer = (int) $event['created_by'];
    $stmt->bind_param('is', $organizer, $msg);
    $stmt->execute();
    $stmt->close();
}

flash('success', 'Registration submitted! You will be notified once the organiser reviews it.');
redirect('users/my_registrations.php');
