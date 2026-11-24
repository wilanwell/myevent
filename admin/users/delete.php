<?php
require_once __DIR__ . '/../../config.php'; 
if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Admin Security Check
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    die("Unauthorized access.");
}

// 2. Get ID from URL
$id = $_GET['id'] ?? null;

if ($id) {
    // 3. Prevent deleting yourself
    $current_admin_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
    
    if ($id == $current_admin_id) {
        echo "<script>alert('You cannot delete your own account!'); window.location='manage.php';</script>";
        exit;
    }

    // 4. Delete Query
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        // Redirect back with a success message
        header("Location: manage.php?msg=deleted");
        exit;
    } else {
        echo "Error deleting record: " . $conn->error;
    }
    $stmt->close();
} else {
    header("Location: manage.php");
    exit;
}
?>