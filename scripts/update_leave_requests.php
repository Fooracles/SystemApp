<?php
/**
 * Leave Requests Sync Script
 * Syncs data from Google Sheets to database
 * Uses U2/V2 cell change detection similar to FMS Task sync
 */

// Disable error display to prevent HTML in JSON
error_reporting(0);
ini_set('display_errors', 0);

// Handle paths from both root and ajax directory
$config_path = file_exists('../includes/config.php') ? '../includes/config.php' : 'includes/config.php';
$sheets_path = file_exists('../includes/google_sheets_client.php') ? '../includes/google_sheets_client.php' : 'includes/google_sheets_client.php';

require_once $config_path;
require_once $sheets_path;

// Set JSON header for AJAX responses
header('Content-Type: application/json');

// Log file for sync operations
$log_file = file_exists('../logs/leave_sync.log') ? '../logs/leave_sync.log' : 'logs/leave_sync.log';

function logSync($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

try {
    // Initialize Google Sheets client
    $gs_client = new GoogleSheetsClient();
    
    // Get sheet ID and tab name from constants
    $sheet_id = LEAVE_SHEET_ID;
    $tab_name = LEAVE_TAB_REQUESTS;
    
    logSync("Starting leave sync for sheet: $sheet_id, tab: $tab_name");
    
    // FORCE SYNC - No U2/V2 detection, always sync latest data
    logSync("=== STARTING FORCE SYNC (NO U2/V2 DETECTION) ===");
    
    // Read all data from Google Sheets (A3:O range - data starts from row 3, includes column N)
    $data_range = $tab_name . '!A3:O';
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
    
    // Process each row
    foreach ($sheet_data as $row_index => $row) {
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
            
            // Convert date formats
            if ($start_date) {
                $start_date = date('Y-m-d', strtotime($start_date));
            }
            if ($end_date) {
                $end_date = date('Y-m-d', strtotime($end_date));
            }
            if ($sheet_timestamp) {
                $sheet_timestamp = date('Y-m-d H:i:s', strtotime($sheet_timestamp));
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
            mysqli_stmt_bind_param($insert_stmt, 'ssssssssssssss',
                $unique_service_no,
                $employee_name,
                $manager_name,
                '', // manager_email - not in sheet data
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
                } else {
                    logSync("No changes needed: $unique_service_no - $employee_name");
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
    
    // Update sync record with sync timestamp
    $update_sync = "UPDATE leave_sheet_sync SET 
        last_synced = NOW() 
        WHERE sheet_id = ?";
    
    $update_stmt = mysqli_prepare($conn, $update_sync);
    mysqli_stmt_bind_param($update_stmt, 's', $sheet_id);
    mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);
    
    // Commit transaction
    mysqli_commit($conn);
    
    logSync("Sync completed. Synced: $synced_count records");
    
    echo json_encode([
        'success' => true,
        'message' => "Sync completed successfully",
        'data' => [
            'synced' => $synced_count,
            'total_rows' => count($sheet_data),
            'errors' => $errors
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    
    logSync("Sync failed: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Sync failed: ' . $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
?>
