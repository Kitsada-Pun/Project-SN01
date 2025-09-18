<?php
// logout.php
// Ensure this is the absolute first line, no spaces or blank lines above it.
session_start(); 

// Set a success message in the session before destroying it
$_SESSION['message'] = [
    'type' => 'success', // This type will be used by index.php to display the correct message style
    'text' => 'ออกจากระบบสำเร็จ!' // The message to display
];

// Unset all of the session variables.
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect to the login page (index.php)
header('Location: ../login.php');
exit();
?>