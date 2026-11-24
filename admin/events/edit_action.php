<?php
/**
 * MyEvent Admin - Update event handler
 */
require_once __DIR__ . '/../../config.php';

require_admin();
require_post();

$event_id    = (int) ($_POST['event_id'] ?? 0);
$event_name  = trim((string) ($_POST['event_name'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$category_id = (int) ($_POST['category_id'] ?? 0);
$contact_no  = trim((string) ($_POST['contact_no'] ?? ''));
$venue       = trim((string) ($_POST['venue'] ?? ''));
$event_date  = (string) ($_POST['date'] ?? '');
$event_time  = (string) ($_POST['time'] ?? '');
$mode        = (string) ($_POST['mode'] ?? '');
$reg_close   = (string) ($_POST['reg_close_date'] ?? '');
$fee         = max(0, (float) ($_POST['fee'] ?? 0));
$max_part    = max(0, (int) ($_POST['max_participants'] ?? 0));
$remarks     = trim((string) ($_POST['remarks'] ?? ''));

$back = 'admin/events/edit.php?id=' . $event_id;

if ($event_name === '' || $venue === '' || $event_date === '' || $event_time === ''
    || !in_array($mode, ['Physical', 'Online', 'Hybrid'], true)) {
    flash('error', 'Please fill in all required fields with valid values.');
    redirect($back);
}

$stmt = $conn->prepare('SELECT poster_path FROM events WHERE event_id = ?');
$stmt->bind_param('i', $event_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$existing) {
    flash('error', 'Event not found.');
    redirect('admin/events/manage.php');
}

$poster_path = $existing['poster_path'] ?? '';

[$newPoster, $uploadError] = upload_image($_FILES['poster'] ?? null, POSTER_DIR, 'event');
if ($uploadError) {
    flash('error', $uploadError);
    redirect($back);
}
if ($newPoster) {
    delete_upload($poster_path, POSTER_DIR);
    $poster_path = $newPoster;
}

$stmt = $conn->prepare(
    'UPDATE events SET event_name = ?, description = ?, category_id = ?, contact_no = ?, venue = ?,
            event_date = ?, event_time = ?, mode = ?, reg_close_date = ?, fee = ?, max_participants = ?,
            remarks = ?, poster_path = ?
     WHERE event_id = ?'
);
// Note: fee is a decimal ("d"); it was previously bound as an integer and lost its cents.
$stmt->bind_param(
    'ssissssssdissi',
    $event_name, $description, $category_id, $contact_no, $venue,
    $event_date, $event_time, $mode, $reg_close, $fee, $max_part,
    $remarks, $poster_path, $event_id
);
$stmt->execute();
$stmt->close();

flash('success', 'Event updated successfully.');
redirect('admin/events/manage.php');
