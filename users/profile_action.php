<?php
/**
 * MyEvent - Profile Update Logic
 * Location: users/profile_action.php
 * -----------------------------
 * This script processes the profile form and updates the 'users' table.
 */

require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// 1. SECURITY CHECK: Ensure user is logged in
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['loggedin'])) {
    
    // Support both session naming conventions
    $user_id      = $_SESSION['user_id'] ?? $_SESSION['id'];
    $name         = trim($_POST['name']);
    $phone        = trim($_POST['phone']);
    $organization = trim($_POST['organization']);
    $bio          = trim($_POST['bio']);
    $new_pwd      = $_POST['new_password'];

    /** * 2. DATA CONVERSION: Checkboxes
     */
    $recommend_events = isset($_POST['event']) ? implode(', ', $_POST['event']) : '';

    // 3. BASIC VALIDATION
    if (empty($name) || empty($phone)) {
        echo "<script>alert('Name and Phone are required.'); window.history.back();</script>";
        exit;
    }

    // 4. HANDLE PROFILE PICTURE UPLOAD
    $profile_pic_name = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "../assets/uploads/profiles/";
        
        // Ensure directory exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $profile_pic_name = "profile_" . $user_id . "_" . time() . "." . $file_ext;
        $dest_path = $upload_dir . $profile_pic_name;

        if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $dest_path)) {
            echo "<script>alert('Failed to upload image.'); window.history.back();</script>";
            exit;
        }
    }

    /**
     * 5. CONSTRUCT DYNAMIC SQL
     */
    $params = [$name, $phone, $organization, $bio, $recommend_events];
    $types = "sssss";
    $sql = "UPDATE users SET name = ?, phone = ?, organization = ?, bio = ?, recommend_events = ?";

    // Add profile picture to query if a new one was uploaded
    if ($profile_pic_name) {
        $sql .= ", profile_pic = ?";
        $params[] = $profile_pic_name;
        $types .= "s";
    }

    // Add password to query if the user provided a new one
    if (!empty($new_pwd)) {
        $sql .= ", password = ?";
        $params[] = password_hash($new_pwd, PASSWORD_DEFAULT);
        $types .= "s";
    }

    // Finalize query with WHERE clause
    $sql .= " WHERE user_id = ?";
    $params[] = $user_id;
    $types .= "i";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    // 6. EXECUTION AND REDIRECT
    if ($stmt->execute()) {
        $_SESSION['name'] = $name;

        // Update session picture if a new one was uploaded
        if ($profile_pic_name) {
            $_SESSION['profile_pic'] = $profile_pic_name; 
        }

        header("Location: profile.php?success=1");
        exit;
    } else {
        echo "Error updating profile: " . $conn->error;
    }

    $stmt->close();
} else {
    // If someone tries to access this file directly without POST
    header("location: profile.php");
    exit;
}

$conn->close();
?>