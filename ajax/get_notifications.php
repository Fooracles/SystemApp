<?php
// Session is automatically started by includes/functions.php

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Debug: Log session info
error_log("AJAX Session Debug - User Type: " . (isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'NOT SET'));
error_log("AJAX Session Debug - isAdmin(): " . (isAdmin() ? 'TRUE' : 'FALSE'));

// Check if user is admin
if (!isAdmin()) {
    // If session is not working, try to get session ID from parameter
    if (isset($_GET['session_id']) && !empty($_GET['session_id'])) {
        error_log("Attempting to restore session with ID: " . $_GET['session_id']);
        
        // Close current session and start new one with provided ID
        session_write_close();
        session_id($_GET['session_id']);
        session_start();
        
        // Check again
        if (isAdmin()) {
            error_log("Session restored successfully - isAdmin(): TRUE");
        } else {
            error_log("Session restored but still not admin - isAdmin(): FALSE");
        }
    }
    
    // Final check
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error', 
            'message' => 'Access denied - Admin required',
            'debug' => [
                'user_type' => isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'NOT SET',
                'is_admin' => isAdmin(),
                'session_id' => session_id(),
                'session_status' => session_status(),
                'provided_session_id' => $_GET['session_id'] ?? 'NOT PROVIDED'
            ]
        ]);
        exit;
    }
}

// Get all password reset requests
$sql = "SELECT * FROM password_reset_requests ORDER BY requested_at DESC";
$result = mysqli_query($conn, $sql);

$notifications = [];
$count = 0;

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = [
            'id' => $row['id'],
            'username' => htmlspecialchars($row['username']),
            'email' => htmlspecialchars($row['email']),
            'status' => $row['status'],
            'reset_code' => $row['reset_code'],
            'requested_at' => $row['requested_at'],
            'approved_at' => $row['approved_at']
        ];
        
        if ($row['status'] === 'pending') {
            $count++;
        }
    }
} else {
    error_log("Database query failed: " . mysqli_error($conn));
}

echo json_encode([
    'status' => 'success',
    'notifications' => $notifications,
    'count' => $count,
    'debug' => [
        'user_type' => $_SESSION['user_type'] ?? 'NOT SET',
        'is_admin' => isAdmin(),
        'session_id' => session_id()
    ]
]);

// Close database connection
if (isset($conn) && $conn instanceof mysqli) {
    mysqli_close($conn);
}
?>
