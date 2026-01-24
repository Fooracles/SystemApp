<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get POST data
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$current_insights = isset($_POST['current_insights']) ? trim($_POST['current_insights']) : '';
$areas_of_improvement = isset($_POST['areas_of_improvement']) ? trim($_POST['areas_of_improvement']) : '';
$admin_id = $_SESSION['id'] ?? 0;

if ($user_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
    exit;
}

try {
    // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both insert and update
    $stmt = mysqli_prepare($conn, "
        INSERT INTO user_motivation (user_id, current_insights, areas_of_improvement, updated_by)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            current_insights = VALUES(current_insights),
            areas_of_improvement = VALUES(areas_of_improvement),
            updated_by = VALUES(updated_by),
            updated_at = CURRENT_TIMESTAMP
    ");
    
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "issi", $user_id, $current_insights, $areas_of_improvement, $admin_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Get updated timestamp
        $timestamp_stmt = mysqli_prepare($conn, "SELECT updated_at FROM user_motivation WHERE user_id = ?");
        mysqli_stmt_bind_param($timestamp_stmt, "i", $user_id);
        mysqli_stmt_execute($timestamp_stmt);
        $timestamp_result = mysqli_stmt_get_result($timestamp_stmt);
        $timestamp_row = mysqli_fetch_assoc($timestamp_result);
        mysqli_stmt_close($timestamp_stmt);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Motivation insights updated successfully',
            'updated_at' => $timestamp_row['updated_at'] ?? null
        ]);
    } else {
        throw new Exception('Database execute error: ' . mysqli_stmt_error($stmt));
    }
    
    mysqli_stmt_close($stmt);
} catch (Exception $e) {
    error_log("Update User Motivation Error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>

