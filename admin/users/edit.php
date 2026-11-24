<?php
require_once __DIR__ . '/../../config.php'; 
if (session_status() === PHP_SESSION_NONE) session_start();

// Admin Check
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}

$id = $_GET['id'] ?? null;
$error = '';
$success = '';

// Fetch User Data
if ($id) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user) { die("User not found."); }
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $category = $_POST['category'];
    
    if (empty($name) || empty($email)) {
        $error = "Name and Email are required.";
    } else {
        $updateSql = "UPDATE users SET name=?, email=?, role=?, category=? WHERE user_id=?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("ssssi", $name, $email, $role, $category, $id);
        
        if ($stmt->execute()) {
            $success = "User updated successfully!";
            // Refresh user data for display
            $user['name'] = $name;
            $user['email'] = $email;
            $user['role'] = $role;
            $user['category'] = $category;
        } else {
            $error = "Database error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User | MyEvent System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --primary: #4f46e5; --bg-body: #f1f5f9; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg-body); padding: 40px; }
        .form-container { background: white; padding: 30px; border-radius: 12px; max-width: 500px; margin: 20px auto; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; box-sizing: border-box; }
        .btn-save { background: var(--primary); color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; width: 100%; font-weight: bold; font-size: 1rem; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .back-link { text-decoration: none; color: #64748b; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; }
    </style>
</head>
<body>
    <div style="max-width: 500px; margin: 0 auto;">
        <a href="manage.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to User List</a>
        
        <div class="form-container">
            <h2 style="margin-top: 0; color: #1e293b;"><i class="fas fa-user-edit"></i> Edit User</h2>
            
            <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Role</label>
                    <select name="role">
                        <option value="User" <?= $user['role'] == 'User' ? 'selected' : '' ?>>User</option>
                        <option value="Admin" <?= $user['role'] == 'Admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" value="<?= htmlspecialchars($user['category']) ?>">
                </div>

                <button type="submit" class="btn-save">Update User Info</button>
            </form>
        </div>
    </div>
</body>
</html>