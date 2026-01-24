<?php
// Suppress all output except JSON
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once '../includes/config.php';
require_once '../includes/functions.php';

session_start();

// Clear any output buffer
ob_clean();

// Check if user is logged in
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get user_id from request
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$current_user_id = $_SESSION['id'] ?? $_SESSION['user_id'] ?? 0;

// Allow access if:
// 1. User is admin (can view any user's data)
// 2. User is viewing their own data
if (!isAdmin() && $user_id != $current_user_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized - You can only view your own motivation data']);
    exit;
}

if ($user_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
    exit;
}

try {
    // Fetch user motivation data
    $stmt = mysqli_prepare($conn, "SELECT current_insights, areas_of_improvement, updated_at FROM user_motivation WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => [
                'current_insights' => $row['current_insights'] ?? '',
                'areas_of_improvement' => $row['areas_of_improvement'] ?? '',
                'updated_at' => $row['updated_at'] ?? null
            ]
        ]);
    } else {
        // Return empty data if no record exists
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => [
                'current_insights' => '',
                'areas_of_improvement' => '',
                'updated_at' => null
            ]
        ]);
    }
    
    mysqli_stmt_close($stmt);
} catch (Exception $e) {
    error_log("Get User Motivation Error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>

