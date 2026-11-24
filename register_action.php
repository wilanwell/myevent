<?php

require_once 'config.php';

// 1. Session Check: Use the same fix as browse_events.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Access Control: Must be logged in to register
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // Redirect to login if not authenticated
    header("location: auth/login.php");
    exit;
}

// 3. Validate Event ID from URL
if (!isset($_GET['event_id'])) {
    header("location: browse_events.php");
    exit;
}

$event_id = intval($_GET['event_id']);
$user_id = $_SESSION['user_id'] ?? $_SESSION['id']; // Support both session keys

// 4. Duplicate Check: Prevent multiple registrations for the same event
$check_sql = "SELECT reg_id FROM registrations WHERE event_id = ? AND user_id = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("ii", $event_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // If already registered, go to registration list with notice
    header("location: users/my_registrations.php?msg=already_joined");
    exit;
}
$stmt->close();

// 5. Create Registration: Set status to 'Pending'
$insert_sql = "INSERT INTO registrations (event_id, user_id, approval_status, registration_date) 
               VALUES (?, ?, 'Pending', NOW())";

if ($stmt = $conn->prepare($insert_sql)) {
    $stmt->bind_param("ii", $event_id, $user_id);
    
    if ($stmt->execute()) {
        // Requirement: Confirmation via dashboard notification
        // Redirect back to dashboard with success message
        header("location: users/dashboard.php?msg=success");
    } else {
        echo "Error: Database update failed. " . $conn->error;
    }
    $stmt->close();
}

$conn->close();
?>