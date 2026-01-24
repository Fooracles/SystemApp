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
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$request_id = $_POST['request_id'] ?? null;

if (!$request_id) {
    echo json_encode(['status' => 'error', 'message' => 'Request ID is required']);
    exit;
}

// Generate random 6-digit reset code
$reset_code = sprintf('%06d', rand(100000, 999999));

// Update the password reset request
$sql = "UPDATE password_reset_requests SET 
        status = 'approved', 
        reset_code = ?, 
        approved_at = NOW(), 
        approved_by = ? 
        WHERE id = ? AND status = 'pending'";

if ($stmt = mysqli_prepare($conn, $sql)) {
    $approved_by = $_SESSION['id'] ?? null;
    mysqli_stmt_bind_param($stmt, "sii", $reset_code, $approved_by, $request_id);
    
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
                'message' => 'Password reset request approved',
                'reset_code' => $reset_code,
                'count' => $count
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Request not found or already processed']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_stmt_error($stmt)]);
    }
    
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($conn)]);
}
?>
