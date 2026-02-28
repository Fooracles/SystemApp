<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isAdmin()) {
    http_response_code(403);
    jsonError('Access denied', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonError('Method not allowed', 405);
}

// CSRF protection for POST requests
csrfProtect();

$request_id = $_POST['request_id'] ?? null;

if (!$request_id) {
    jsonError('Request ID is required', 400);
}

// Update the password reset request status to rejected
$sql = "UPDATE password_reset_requests SET 
        status = 'rejected', 
        rejected_at = NOW(), 
        rejected_by = ?
        WHERE id = ? AND status = 'pending'";

if ($stmt = mysqli_prepare($conn, $sql)) {
    $rejected_by = $_SESSION['id'] ?? null;
    mysqli_stmt_bind_param($stmt, "ii", $rejected_by, $request_id);
    
    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_affected_rows($conn) > 0) {
            // Get updated count
            $count_sql = "SELECT COUNT(*) as count FROM password_reset_requests WHERE status = 'pending'";
            $count_result = mysqli_query($conn, $count_sql);
            $count = 0;
            if ($count_result) {
                $row = mysqli_fetch_assoc($count_result);
                $count = $row['count'];
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Password reset request rejected',
                'count' => $count
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Request not found or already processed']);
        }
    } else {
        handleDbError($conn, 'C:/xampp/htdocs/app-v5.5-new/ajax/reject_password_reset.php');
    }
    
    mysqli_stmt_close($stmt);
} else {
    handleDbError($conn, 'C:/xampp/htdocs/app-v5.5-new/ajax/reject_password_reset.php');
}

// Close database connection
if (isset($conn) && $conn instanceof mysqli) {
    mysqli_close($conn);
}
?>
