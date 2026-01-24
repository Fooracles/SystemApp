<?php
/**
 * Minimal Leave Requests Sync Script
 * Syncs data from Google Sheets to database without including problematic files
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON header for AJAX responses
header('Content-Type: application/json');

// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'task_management');

// Google Sheets configuration
define('LEAVE_SHEET_ID', '1uLjlLs1Nd1eumtP3XjCWa0BEa4yPqev1ato5kGr5UY0');
define('LEAVE_TAB_REQUESTS', 'Leave_Requests');

// Connect to database
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if(!$conn) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . mysqli_connect_error()
    ]);
    exit;
}

// Set timezone
mysqli_query($conn, "SET time_zone = '+05:30'");

// Include Google Sheets client
require_once __DIR__ . '/../includes/google_sheets_client.php';

// Log file for sync operations
$log_file = __DIR__ . '/../logs/leave_sync.log';

function logSync($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

try {
    echo "Starting sync process...\n";
    
    // Initialize Google Sheets client
    $gs_client = new GoogleSheetsClient();
    echo "Google Sheets client initialized\n";
    
    // Debug: Test if client is working
    if (!$gs_client) {
        throw new Exception('Failed to initialize Google Sheets client');
    }
    
    // Get sheet ID and tab name from constants
    $sheet_id = LEAVE_SHEET_ID;
    $tab_name = LEAVE_TAB_REQUESTS;
    
    logSync("Starting minimal leave sync for sheet: $sheet_id, tab: $tab_name");
    
    // Read limited data from Google Sheets (A3:O range - only first 100 rows to avoid timeout)
    $data_range = $tab_name . '!A3:O100';
    $sheet_data = $gs_client->gs_list($sheet_id, $data_range);
    
    if (empty($sheet_data)) {
        logSync("No data found in sheet");
        echo json_encode([
            'success' => true,
            'message' => 'No data found',
            'data' => ['synced' => 0]
        ]);
        exit;
    }
    
    logSync("Found " . count($sheet_data) . " rows in sheet");
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    $synced_count = 0;
    $errors = [];
    
    // Process each row (limit to first 50 rows to avoid timeout)
    $max_rows = min(50, count($sheet_data));
    for ($row_index = 0; $row_index < $max_rows; $row_index++) {
        $row = $sheet_data[$row_index];
        
        try {
            // Skip empty rows
            if (empty($row[0]) && empty($row[1])) {
                continue;
            }
            
            // Map columns according to the guide
            $sheet_timestamp = $row[0] ?? null;      // Column A
            $unique_service_no = $row[1] ?? null;    // Column B
            $employee_name = $row[2] ?? null;        // Column C
            $leave_type = $row[3] ?? null;           // Column D
            $duration = $row[4] ?? null;              // Column E
            $start_date = $row[5] ?? null;            // Column F
            $end_date = $row[6] ?? null;             // Column G
            $reason = $row[7] ?? null;               // Column H
            $file_url = $row[8] ?? null;              // Column I
            $leave_count = $row[9] ?? null;          // Column J
            $manager_name = $row[10] ?? null;        // Column K
            $department = $row[11] ?? null;           // Column L
            $status = $row[13] ?? '';                // Column N - blank means pending
            
            // Normalize status to match database enum values
            $status = trim($status);
            if (empty($status)) {
                $status = 'PENDING';
            } else {
                // Map common status variations to enum values
                $status_lower = strtolower($status);
                if (in_array($status_lower, ['approved', 'approve', 'yes'])) {
                    $status = 'Approve';
                } elseif (in_array($status_lower, ['rejected', 'reject', 'no'])) {
                    $status = 'Reject';
                } elseif (in_array($status_lower, ['cancelled', 'cancel', 'cancelled'])) {
                    $status = 'Cancelled';
                } else {
                    // Default to PENDING for unknown values
                    $status = 'PENDING';
                }
            }
            
            // Convert date formats - handle empty dates
            if ($start_date && trim($start_date) !== '') {
                $start_date = date('Y-m-d', strtotime($start_date));
            } else {
                $start_date = null;
            }
            if ($end_date && trim($end_date) !== '') {
                $end_date = date('Y-m-d', strtotime($end_date));
            } else {
                $end_date = null;
            }
            if ($sheet_timestamp && trim($sheet_timestamp) !== '') {
                $sheet_timestamp = date('Y-m-d H:i:s', strtotime($sheet_timestamp));
            } else {
                $sheet_timestamp = null;
            }
            
            // Use INSERT ... ON DUPLICATE KEY UPDATE to handle duplicates and update existing records
            $insert_query = "INSERT INTO Leave_request (
                unique_service_no, employee_name, manager_name, manager_email, 
                department, leave_type, duration, start_date, end_date, 
                reason, leave_count, file_url, status, sheet_timestamp, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                employee_name = VALUES(employee_name),
                manager_name = VALUES(manager_name),
                department = VALUES(department),
                leave_type = VALUES(leave_type),
                duration = VALUES(duration),
                start_date = VALUES(start_date),
                end_date = VALUES(end_date),
                reason = VALUES(reason),
                leave_count = VALUES(leave_count),
                file_url = VALUES(file_url),
                status = VALUES(status),
                sheet_timestamp = VALUES(sheet_timestamp)";
            
            $insert_stmt = mysqli_prepare($conn, $insert_query);
            if (!$insert_stmt) {
                throw new Exception('Prepare error: ' . mysqli_error($conn));
            }
            
            // Bind parameters
            $manager_email = ''; // manager_email - not in sheet data
            mysqli_stmt_bind_param($insert_stmt, 'ssssssssssssss',
                $unique_service_no,
                $employee_name,
                $manager_name,
                $manager_email,
                $department,
                $leave_type,
                $duration,
                $start_date,
                $end_date,
                $reason,
                $leave_count,
                $file_url,
                $status,
                $sheet_timestamp
            );
            
            if (mysqli_stmt_execute($insert_stmt)) {
                $affected_rows = mysqli_stmt_affected_rows($insert_stmt);
                if ($affected_rows > 0) {
                    $synced_count++;
                    $action = ($affected_rows == 1) ? "Inserted" : "Updated";
                    logSync("$action: $unique_service_no - $employee_name (Status: $status)");
                }
            } else {
                $errors[] = "Error processing row " . ($row_index + 2) . ": " . mysqli_stmt_error($insert_stmt);
            }
            
            mysqli_stmt_close($insert_stmt);
            
        } catch (Exception $e) {
            $errors[] = "Error processing row " . ($row_index + 2) . ": " . $e->getMessage();
            logSync("Error processing row " . ($row_index + 2) . ": " . $e->getMessage());
        }
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    logSync("Minimal sync completed. Synced: $synced_count records");
    
    echo json_encode([
        'success' => true,
        'message' => "Sync completed successfully",
        'data' => [
            'synced' => $synced_count,
            'total_rows' => $max_rows,
            'errors' => $errors
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        mysqli_rollback($conn);
    }
    
    logSync("Minimal sync failed: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Sync failed: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    // Handle fatal errors
    logSync("Fatal error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
?>
