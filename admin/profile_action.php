<?php
/**
 * MyEvent - Admin Profile Update Logic
 * Location: admin/profile_action.php
 */

require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// SECURITY: Ensure only Admins can run this specific script
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['loggedin']) && $_SESSION['role'] === 'Admin') {
    
    $user_id      = $_SESSION['user_id'] ?? $_SESSION['id'];
    $name         = trim($_POST['name']);
    $phone        = trim($_POST['phone']);
    $organization = trim($_POST['organization']);
    $bio          = trim($_POST['bio']);
    $new_pwd      = $_POST['new_password'];

    // Convert the checkbox array into a comma-separated string for the database
    $recommend_events = isset($_POST['event']) ? implode(', ', $_POST['event']) : '';

    // 1. Basic Validation
    if (empty($name) || empty($phone)) {
        echo "<script>alert('Name and Phone are required.'); window.history.back();</script>";
        exit;
    }

    // 2. Handle Profile Picture Upload
    $profile_pic_name = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "../assets/uploads/profiles/";
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $profile_pic_name = "profile_" . $user_id . "_" . time() . "." . $file_ext;
        $dest_path = $upload_dir . $profile_pic_name;

        move_uploaded_file($_FILES['profile_pic']['tmp_name'], $dest_path);
    }

    // 3. Construct SQL Statement
    $params = [$name, $phone, $organization, $bio, $recommend_events];
    $types = "sssss";
    $sql = "UPDATE users SET name = ?, phone = ?, organization = ?, bio = ?, recommend_events = ?";

    if ($profile_pic_name) {
        $sql .= ", profile_pic = ?";
        $params[] = $profile_pic_name;
        $types .= "s";
    }

    if (!empty($new_pwd)) {
        $sql .= ", password = ?";
        $params[] = password_hash($new_pwd, PASSWORD_DEFAULT);
        $types .= "s";
    }

    $sql .= " WHERE user_id = ?";
    $params[] = $user_id;
    $types .= "i";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $_SESSION['name'] = $name;
        if ($profile_pic_name) $_SESSION['profile_pic'] = $profile_pic_name;
        header("Location: profile.php?success=1");
        exit;
    } else {
        echo "Error updating profile: " . $conn->error;
    }
    $stmt->close();
} else {
    header("location: profile.php");
    exit;
}