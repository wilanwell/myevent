<?php
// 1. Start Session
session_start();

// 2. Include Database Config
require_once __DIR__ . '/../config.php'; 

// Initialize variables
$email = $password = "";
$error_msg = "";

// 3. Process Form Data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Sanitize input
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error_msg = "Please enter both email and password.";
    } else {
        $sql = "SELECT user_id, name, password, role FROM users WHERE email = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email);
            
            if ($stmt->execute()) {
                $stmt->store_result();

                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($user_id, $name, $hashed_password, $role);
                    $stmt->fetch();

                    // 4. Verify the Password
                    if (password_verify($password, $hashed_password)) {
                        $_SESSION["loggedin"] = true;
                        $_SESSION["id"] = $user_id; 
                        $_SESSION["email"] = $email;
                        $_SESSION["name"] = $name;
                        $_SESSION["role"] = trim($role); 

                        if ($_SESSION["role"] === 'Admin') {
                            header("location: ../admin/index.php"); 
                        } else {
                            // Redirect others to the dashboard
                            header("location: ../index.php");
                        }
                        exit;

                    } else {
                        $error_msg = "The password you entered was not valid.";
                    }
                } else {
                    $error_msg = "No account found with that email address.";
                }
            } else {
                $error_msg = "Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login Failed</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: #f1f5f9; margin: 0; }
        .error-card { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 100%; border-top: 5px solid #ef4444; }
        .btn { display: inline-block; margin-top: 20px; padding: 10px 25px; background: #4f46e5; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="error-card">
        <h2>Login Error</h2>
        <p><?php echo $error_msg; ?></p>
        <a href="login.php" class="btn">Try Again</a>
    </div>
</body>
</html>