<?php

require_once '../config.php';

require_login();

$event_id = (int) ($_GET['event_id'] ?? 0);
$user_id = current_user_id();

// Verify Ownership
$v_stmt = $conn->prepare("SELECT event_name FROM events WHERE event_id = ? AND created_by = ?");
$v_stmt->bind_param("ii", $event_id, $user_id);
$v_stmt->execute();
$event_res = $v_stmt->get_result();

if ($event_res->num_rows === 0) { exit("Unauthorized access."); }
$event_name = $event_res->fetch_assoc()['event_name'];

// Set CSV Headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . safe_filename('Attendance_' . $event_name) . '"');

$output = fopen('php://output', 'w');

fputcsv($output, array('Participant Name', 'Email', 'Category', 'Approval Status', 'Attendance Status'));

// Fetch Data including Attendance
$sql = "SELECT u.name, u.email, u.category, r.approval_status, r.attendance_status 
        FROM registrations r 
        JOIN users u ON r.user_id = u.user_id 
        WHERE r.event_id = ? 
        ORDER BY u.name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    fputcsv($output, csv_row($row));
}

fclose($output);
exit;