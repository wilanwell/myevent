<?php
// 1. Start Session & Include Config
session_start();
require_once '../../config.php'; 

// 2. SECURITY CHECK
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'Admin') {
    die("Unauthorized access.");
}

// 3. PROCESS THE DELETION
if (isset($_GET['id'])) {
    $event_id = intval($_GET['id']); // Sanitize input

    /**
     * DATABASE INTEGRITY STEP
     * Before deleting the event, we must remove all participant registrations 
     * associated with it to prevent foreign key errors.
     */
    $delete_regs = $conn->prepare("DELETE FROM registrations WHERE event_id = ?");
    $delete_regs->bind_param("i", $event_id);
    $delete_regs->execute();
    $delete_regs->close();

    // Now safe to delete the actual event
    $sql = "DELETE FROM events WHERE event_id = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $event_id);
        
        if ($stmt->execute()) {
            echo "<script>alert('Event and all associated registrations deleted successfully.'); window.location.href='manage.php';</script>";
        } else {
            echo "<script>alert('Error deleting event. Please try again.'); window.location.href='manage.php';</script>";
        }
        $stmt->close();
    }
} else {
    header("location: manage.php");
}
$conn->close();
?>