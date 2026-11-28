<?php
/**
 * MyEvent Admin - Delete event (POST only).
 * Registrations and notifications are removed by ON DELETE CASCADE.
 */
require_once __DIR__ . '/../../../config.php';

require_admin();
require_post();

$event_id = (int) ($_POST['id'] ?? 0);

$stmt = $conn->prepare('SELECT event_name, poster_path, created_by FROM events WHERE event_id = ?');
$stmt->bind_param('i', $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    flash('error', __('Event not found.'));
    redirect('admin/events/manage.php');
}

$told = notify_event_cancelled($conn, $event_id);

$conn->begin_transaction();
try {
    $stmt = $conn->prepare('DELETE FROM registrations WHERE event_id = ?');
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM events WHERE event_id = ?');
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
} catch (Throwable $ex) {
    $conn->rollback();
    throw $ex;
}

delete_upload($event['poster_path'], POSTER_DIR);
audit($conn, 'event.delete', 'event', $event_id, $event['event_name']);
notify_organiser_of_admin_change($conn, $event['created_by'] !== null ? (int) $event['created_by'] : null, $event['event_name'], 'deleted');

flash('success', __('Event and all associated registrations deleted.') . ($told ? ' ' . _n('{n} registered person was told it is cancelled.', '{n} registered people were told it is cancelled.', $told) : ''));
redirect('admin/events/manage.php');
