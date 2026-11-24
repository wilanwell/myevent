<?php
/**
 * MyEvent Admin - Create event handler
 */
require_once __DIR__ . '/../../config.php';

require_admin();
require_post();

$event_name  = trim((string) ($_POST['event_name'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$category_id = (int) ($_POST['category_id'] ?? 0);
$contact_no  = trim((string) ($_POST['contact_no'] ?? ''));
$venue       = trim((string) ($_POST['venue'] ?? ''));
$event_date  = (string) ($_POST['event_date'] ?? '');
$event_time  = (string) ($_POST['event_time'] ?? '');
$mode        = (string) ($_POST['mode'] ?? '');
$reg_close   = (string) ($_POST['reg_close_date'] ?? '');
$max_part    = max(0, (int) ($_POST['max_participants'] ?? 0));
$fee         = max(0, (float) ($_POST['fee'] ?? 0));
$remarks     = trim((string) ($_POST['remarks'] ?? ''));
$user_id     = current_user_id(); // previously read an unset key, so created_by was always NULL

if ($event_name === '' || $venue === '' || $event_date === '' || $event_time === ''
    || !in_array($mode, ['Physical', 'Online', 'Hybrid'], true)) {
    flash('error', 'Please fill in all required fields with valid values.');
    redirect('admin/events/create.php');
}

[$filename, $uploadError] = upload_image($_FILES['poster'] ?? null, POSTER_DIR, 'event');
if ($uploadError) {
    flash('error', $uploadError);
    redirect('admin/events/create.php');
}
$filename = $filename ?? '';

$stmt = $conn->prepare(
    'INSERT INTO events (event_name, description, category_id, contact_no, venue, event_date, event_time,
                         mode, reg_close_date, max_participants, fee, remarks, poster_path, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->bind_param(
    'ssissssssidssi',
    $event_name, $description, $category_id, $contact_no, $venue, $event_date, $event_time,
    $mode, $reg_close, $max_part, $fee, $remarks, $filename, $user_id
);
$stmt->execute();
$stmt->close();

flash('success', 'Event created successfully.');
redirect('admin/events/manage.php');
