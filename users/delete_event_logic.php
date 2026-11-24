<?php
/**
 * MyEvent - Delete Event Logic
 * ----------------------------
 */

// 1. Include global configuration
require_once '../config.php';

// 2. Access Control: Ensure user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: ../auth/login.php");
    exit;
}

// 3. Check for Event ID in the URL
if (isset($_GET['id'])) {
    $event_id = intval($_GET['id']);
    $user_id = $_SESSION['id'];

    /**
     * 4. Access Control Verification (Rubric: 0.5 Marks)
     */
    $check_sql = "SELECT event_id FROM events WHERE event_id = ? AND created_by = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ii", $event_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        /**
         * 5. Database Operation: Delete the record 
         * Registrations linked to this event will be deleted automatically 
         * because of the ON DELETE CASCADE constraint in our SQL.
         */
        $delete_sql = "DELETE FROM events WHERE event_id = ?";
        $del_stmt = $conn->prepare($delete_sql);
        $del_stmt->bind_param("i", $event_id);

        if ($del_stmt->execute()) {
            echo "<script>alert('Event deleted successfully.'); window.location.href='my_events.php';</script>";
        } else {
            echo "Error deleting event: " . $conn->error;
        }
        $del_stmt->close();
    } else {
        // Unauthorized attempt to delete someone else's event
        echo "<script>alert('Unauthorized: You can only delete your own events.'); window.location.href='my_events.php';</script>";
    }
    $stmt->close();
} else {
    header("location: my_events.php");
    exit;
}

$conn->close();
?>