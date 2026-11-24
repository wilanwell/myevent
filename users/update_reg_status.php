<?php
/**
 * MyEvent - Update Registration Status Logic
 * -----------------------------------------
 * Module 4: Approve or reject participant requests.
 * Requirement: Ensures organizers manage participants of their own events.
 */

// 1. Include global configuration
require_once '../config.php';

// 2. Access Control: Ensure user is logged in
if (!isset($_SESSION['loggedin'])) {
    header("location: ../auth/login.php");
    exit;
}

// 3. Capture and Validate Inputs from manage_participants.php
if (isset($_GET['id']) && isset($_GET['status']) && isset($_GET['event_id'])) {
    $reg_id = intval($_GET['id']);
    $status = $_GET['status']; // Will be 'Approved' or 'Rejected'
    $event_id = intval($_GET['event_id']);
    
    // FIXED: Using 'user_id' session key to match your login system
    $organizer_id = $_SESSION['user_id'] ?? $_SESSION['id'];

    /**
     * 4. Ownership Verification (Access Control)
     * Verifies that the current user is the actual creator of the event.
     */
    $verify_sql = "SELECT event_id FROM events WHERE event_id = ? AND created_by = ?";
    $v_stmt = $conn->prepare($verify_sql);
    $v_stmt->bind_param("ii", $event_id, $organizer_id);
    $v_stmt->execute();
    
    if ($v_stmt->get_result()->num_rows === 0) {
        // Security check failed: User doesn't own this event
        die("Unauthorized Action: Access Denied.");
    }
    $v_stmt->close();

    /**
     * 5. Database Update
     * Updates the specific registration record with the new status.
     */
    $update_sql = "UPDATE registrations SET approval_status = ? WHERE reg_id = ? AND event_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sii", $status, $reg_id, $event_id);

    if ($stmt->execute()) {
        // Success: Alert the user and return to the participant list
        echo "<script>
                alert('Participant status updated to " . $status . "');
                window.location.href = 'manage_participants.php?event_id=" . $event_id . "';
              </script>";
    } else {
        echo "Error updating status: " . $conn->error;
    }
    $stmt->close();
} else {
    // If accessed without parameters, redirect back to the event list
    header("location: my_events.php");
    exit;
}

$conn->close();
?>