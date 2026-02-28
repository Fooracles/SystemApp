<?php
/**
 * Clear Leave Data Endpoint - Admin Only
 * Truncates the Leave_request table (removes all leave data)
 * 
 * SECURITY: Only accessible by Admin users
 */

// Set JSON header first
header('Content-Type: application/json');

// Disable error display to prevent HTML in JSON
error_reporting(0);
ini_set('display_errors', 0);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_type']) || !isset($_SESSION['username'])) {
    jsonError('Unauthorized: Please log in.', 401);
}

// Check if user is Admin
if ($_SESSION['user_type'] !== 'admin') {
    jsonError('Forbidden: Admin access required.', 400);
}

// Include database configuration and functions
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// CSRF protection for POST requests
csrfProtect();

// Use database connection from config.php
if (!$conn) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . mysqli_connect_error()
    ]);
    exit;
}

// Set timezone
mysqli_query($conn, "SET time_zone = '+05:30'");

// Log file for clear operations
$log_file = __DIR__ . '/../logs/db_operations.log';

function logClearOperation($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $username = $_SESSION['username'] ?? 'Unknown';
    $log_entry = "[{$timestamp}] CLEAR_DATA [{$username}]: {$message}\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

try {
    logClearOperation("Starting clear leave data operation");
    
    // Start transaction for safety
    mysqli_begin_transaction($conn);
    
    // Get count before truncation (for logging)
    $count_query = "SELECT COUNT(*) as total FROM Leave_request";
    $count_result = mysqli_query($conn, $count_query);
    $count_row = mysqli_fetch_assoc($count_result);
    $total_records = $count_row['total'] ?? 0;
    
    logClearOperation("Found {$total_records} records to clear");
    
    // Truncate Leave_request table
    // Note: TRUNCATE cannot be rolled back, but we're in a transaction for consistency
    $truncate_query = "TRUNCATE TABLE Leave_request";
    
    if (mysqli_query($conn, $truncate_query)) {
        // Also clear leave_status_actions table for consistency
        // (Optional - uncomment if you want to clear action history too)
        // $truncate_actions = "TRUNCATE TABLE leave_status_actions";
        // mysqli_query($conn, $truncate_actions);
        
        // Commit transaction
        mysqli_commit($conn);
        
        logClearOperation("Successfully cleared {$total_records} leave records");
        
        echo json_encode([
            'success' => true,
            'message' => 'All leave data cleared successfully.',
            'records_cleared' => $total_records
        ]);
        
    } else {
        // Rollback on error
        mysqli_rollback($conn);
        
        $error = mysqli_error($conn);
        logClearOperation("Error clearing data: {$error}");
        
        echo json_encode([
            'success' => false,
            'error' => 'Failed to clear leave data: ' . $error
        ]);
    }
    
} catch (Exception $e) {
    // Rollback on exception
    if (isset($conn)) {
        mysqli_rollback($conn);
    }
    
    logClearOperation("Exception: " . $e->getMessage());
    
    handleException($e, 'clear_leave_data');
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
?>

