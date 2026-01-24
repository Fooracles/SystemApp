<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is admin
if (!isAdmin()) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Admin privileges required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

try {
    // Clear all password reset requests
    $sql = "DELETE FROM password_reset_requests";
    
    if (mysqli_query($conn, $sql)) {
        $affected_rows = mysqli_affected_rows($conn);
        echo json_encode([
            'status' => 'success', 
            'message' => "Successfully cleared $affected_rows notifications.",
            'cleared_count' => $affected_rows
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($conn)]);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}

mysqli_close($conn);
?>
