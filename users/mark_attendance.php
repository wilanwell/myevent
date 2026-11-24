<?php

require_once '../config.php';

// 1. Access Control: Ensure organizer is logged in
if (!isset($_SESSION['loggedin'])) {
    header("location: ../auth/login.php");
    exit;
}

// 2. Validate Inputs from the URL
if (isset($_GET['id']) && isset($_GET['status']) && isset($_GET['event_id'])) {
    $reg_id = intval($_GET['id']);
    $new_status = $_GET['status']; // 'Attended' or 'Absent'
    $event_id = intval($_GET['event_id']);
    $organizer_id = $_SESSION['user_id'] ?? $_SESSION['id'];

    /**
     * 3. Security Check: Verify Event Ownership
     * Only the person who created the event can mark attendance.
     */
    $v_stmt = $conn->prepare("SELECT event_id FROM events WHERE event_id = ? AND created_by = ?");
    $v_stmt->bind_param("ii", $event_id, $organizer_id);
    $v_stmt->execute();
    
    if ($v_stmt->get_result()->num_rows > 0) {
        // 4. Update Attendance Status in database
        $stmt = $conn->prepare("UPDATE registrations SET attendance_status = ? WHERE reg_id = ? AND event_id = ?");
        $stmt->bind_param("sii", $new_status, $reg_id, $event_id);
        
        if ($stmt->execute()) {
            // Success: Redirect back to the participant list
            header("location: manage_participants.php?event_id=" . $event_id . "&msg=attendance_updated");
        } else {
            echo "Error: " . $conn->error;
        }
        $stmt->close();
    } else {
        die("Unauthorized Action.");
    }
    $v_stmt->close();
} else {
    header("location: my_events.php");
}

$conn->close();
?>