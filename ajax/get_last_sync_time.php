<?php
/**
 * Get Last Sync Time from Database
 * Returns the last_synced timestamp from leave_sheet_sync table
 */

// Set JSON header first
header('Content-Type: application/json');

// Disable error display to prevent HTML in JSON
error_reporting(0);
ini_set('display_errors', 0);

session_start();

// Include database configuration from config file
require_once __DIR__ . '/../includes/config.php';

// Use database connection from config.php
if(!$conn) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . mysqli_connect_error(),
        'timestamp' => null
    ]);
    exit;
}

try {
    // Get Google Sheets ID from config
    $sheet_id = LEAVE_SHEET_ID;
    
    // Query the leave_sheet_sync table for last_synced timestamp
    $query = "SELECT last_synced FROM leave_sheet_sync WHERE sheet_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        error_log("[DB Error] Failed to prepare query: " . mysqli_error($conn)); throw new Exception('A database error occurred');
    }
    
    mysqli_stmt_bind_param($stmt, 's', $sheet_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row && !empty($row['last_synced'])) {
        // Convert MySQL datetime to ISO format for JavaScript
        $timestamp = $row['last_synced'];
        echo json_encode([
            'success' => true,
            'timestamp' => $timestamp,
            'timestamp_iso' => date('c', strtotime($timestamp))
        ]);
    } else {
        // No record found
        echo json_encode([
            'success' => true,
            'timestamp' => null,
            'timestamp_iso' => null
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => null
    ]);
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
?>

