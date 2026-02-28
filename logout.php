<?php
require_once "includes/config.php";
require_once "includes/functions.php";

session_start();

// Log the logout before destroying session
if (isLoggedIn() && isset($_SESSION["username"])) {
    $username = $_SESSION["username"];
    logUserLogout($conn, $username, 'manual');
}
 
// Destroy the session
session_destroy();
 
// Close database connection before redirecting
if (isset($conn) && $conn instanceof mysqli) {
    mysqli_close($conn);
}

// Redirect to login page
header("location: login.php");
exit;
?>
