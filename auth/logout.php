<?php
session_start();

// 1. Clear session
$_SESSION = array();

// 2. Destroy cookies
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destroy session
session_destroy();

// 4. Redirect to Home
header("Location: ../index.php"); 
exit;
?>