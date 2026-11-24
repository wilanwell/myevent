<?php
/**
 * MyEvent - User Registration Action
 * ----------------------------------
 */

// 1. Include global configuration
require_once '../config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 2. Capture and Sanitize Form Data
    $category     = $_POST['category'] ?? ''; 
    $name         = trim($_POST['name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $organization = trim($_POST['organization'] ?? '');
    $password     = $_POST['password'] ?? '';
    
    // Convert recommendation array into a single string to save in the 'users' table
    $recommendations = isset($_POST['event']) ? implode(", ", $_POST['event']) : "";

    /**
     * 3. Server-side Validation
     */
    if (empty($name) || empty($email) || empty($password) || empty($category) || empty($organization)) {
        echo "<script>alert('Error: All required fields (including Organization) must be filled.'); window.history.back();</script>";
        exit;
    }

    if (empty($recommendations)) {
        echo "<script>alert('Error: Please select at least one event recommendation.'); window.history.back();</script>";
        exit;
    }

    // 4. Check for existing email to prevent duplicate accounts
    $check_email = "SELECT user_id FROM users WHERE email = ?";
    $stmt = $conn->prepare($check_email);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo "<script>alert('Error: This email is already registered.'); window.history.back();</script>";
        exit;
    }
    $stmt->close();

    // 5. Password Hashing (Security Requirement)
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    /**
     * 6. Database Integration
     */
    $sql = "INSERT INTO users (category, name, email, phone, organization, recommend_events, password, role) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'User')";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("sssssss", $category, $name, $email, $phone, $organization, $recommendations, $hashed_password);
        
        if ($stmt->execute()) {
            echo "<script>alert('Registration Successful! Please login to MyEvent.'); window.location.href='login.php';</script>";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    }
    $conn->close();
} else {
    header("location: register.php");
    exit;
}
?>