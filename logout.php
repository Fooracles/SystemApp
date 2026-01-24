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
 
// Redirect to login page
header("location: login.php");
exit;
?> 