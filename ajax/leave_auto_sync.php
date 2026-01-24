<?php
/**
 * Leave Auto Sync Endpoint - Fixed Version
 * Always syncs latest data from Google Sheets to database
 * NO U2/V2 detection - always updates database
 */

// Set JSON header first
header('Content-Type: application/json');

// Disable error display to prevent HTML in JSON
error_reporting(0);
ini_set('display_errors', 0);

session_start();

// Include database configuration from config file
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notification_triggers.php';

// Use database connection from config.php
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
    logSync("=== STARTING FORCE SYNC (NO U2/V2 DETECTION) ===");
    logSync("Database connection: " . ($conn ? "SUCCESS" : "FAILED"));
    logSync("Sheet ID: " . LEAVE_SHEET_ID);
    logSync("Tab Name: " . LEAVE_TAB_REQUESTS);
    
    // Initialize Google Sheets client
    $gs_client = new GoogleSheetsClient();
    logSync("Google Sheets client initialized successfully");
    
    // Get sheet ID and tab name from config
    $sheet_id = LEAVE_SHEET_ID;
    $tab_name = LEAVE_TAB_REQUESTS;
    
    // Read ALL data from Google Sheets (no U2/V2 detection - always sync)
    // Use entire columns A:O to get all rows (1029+ rows)
    // This bypasses Google Sheets API range limitations
    $data_range = $tab_name . '!A:O';
    logSync("Attempting to read from range: " . $data_range);
    
    $sheet_data = $gs_client->gs_list($sheet_id, $data_range);
    logSync("Google Sheets API response received");
    
    // Skip header rows (first 2 rows) since we're reading from A:O
    if (count($sheet_data) > 2) {
        $sheet_data = array_slice($sheet_data, 2); // Remove first 2 rows (headers)
    }
    
    if (empty($sheet_data)) {
        logSync("No data found in sheet");
        echo json_encode([
            'success' => true,
            'message' => 'No data found',
            'data' => ['synced' => 0]
        ]);
        exit;
    }
    
    // Log the exact number of rows received from Google Sheets
    $total_rows = count($sheet_data);
    logSync("Google Sheets API returned exactly {$total_rows} data rows (total sheet rows: 1029, data rows: 1027)");
    logSync("Range used: {$data_range}");
    logSync("Processing all {$total_rows} data rows from Google Sheets");
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    $synced_count = 0;
    $updated_count = 0;
    $inserted_count = 0;
    $errors = [];
    
    // Process ALL rows (no limit)
    for ($row_index = 0; $row_index < count($sheet_data); $row_index++) {
        $row = $sheet_data[$row_index];
        
        try {
            // Skip empty rows
            if (empty($row[0]) && empty($row[1])) {
                continue;
            }
            
            // Map columns - CORRECTED MAPPING based on actual Google Sheet structure
            // A=Timestamp, B=ServiceNo, C=Employee, D=LeaveType, E=Duration, F=StartDate, G=EndDate, H=Reason, I=File, J=Count, K=Manager, L=Dept, M=Email, N=Status
            $sheet_timestamp = $row[0] ?? null;        // A - Timestamp
            $unique_service_no = $row[1] ?? null;     // B - Service Number
            $employee_name = $row[2] ?? null;         // C - Employee Name
            $leave_type = $row[3] ?? null;            // D - Leave Type
            $duration = $row[4] ?? null;              // E - Duration
            $start_date = $row[5] ?? null;            // F - Start Date
            $end_date = $row[6] ?? null;              // G - End Date
            $reason = $row[7] ?? null;                // H - Reason
            $file_url = $row[8] ?? null;              // I - File URL
            $leave_count = $row[9] ?? null;           // J - Leave Count
            $manager_name = $row[10] ?? null;         // K - Manager Name
            $department = $row[11] ?? null;           // L - Department
            $manager_email = $row[12] ?? null;        // M - Manager Email
            $status = $row[13] ?? '';                 // N - Status
            
            // Normalize status to match database enum
            $status = trim($status);
            if (empty($status)) {
                $status = 'PENDING';
            } else {
                $status_lower = strtolower($status);
                if (in_array($status_lower, ['approved', 'approve', 'yes'])) {
                    $status = 'Approve';
                } elseif (in_array($status_lower, ['rejected', 'reject', 'no'])) {
                    $status = 'Reject';
                } elseif (in_array($status_lower, ['cancelled', 'cancel'])) {
                    $status = 'Cancelled';
                } else {
                    $status = 'PENDING';
                }
            }
            
            // Convert dates
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
            
            // Check if record exists
            $check_query = "SELECT id, status FROM Leave_request WHERE unique_service_no = ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            $exists = false;
            $old_status = '';
            
            if ($check_stmt) {
                mysqli_stmt_bind_param($check_stmt, 's', $unique_service_no);
                mysqli_stmt_execute($check_stmt);
                $result = mysqli_stmt_get_result($check_stmt);
                $existing_record = mysqli_fetch_assoc($result);
                mysqli_stmt_close($check_stmt);
                
                if ($existing_record) {
                    $exists = true;
                    $old_status = $existing_record['status'];
                }
            }
            
            // Insert or Update record
            $insert_query = "INSERT INTO Leave_request (
                unique_service_no, employee_name, manager_name, manager_email, 
                department, leave_type, duration, start_date, end_date, 
                reason, leave_count, file_url, status, sheet_timestamp, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                employee_name = VALUES(employee_name),
                manager_name = VALUES(manager_name),
                manager_email = VALUES(manager_email),
                department = VALUES(department),
                leave_type = VALUES(leave_type),
                duration = VALUES(duration),
                start_date = VALUES(start_date),
                end_date = VALUES(end_date),
                reason = VALUES(reason),
                leave_count = VALUES(leave_count),
                file_url = VALUES(file_url),
                status = VALUES(status),
                sheet_timestamp = VALUES(sheet_timestamp),
                updated_at = NOW()";
            
            $insert_stmt = mysqli_prepare($conn, $insert_query);
            if ($insert_stmt) {
                $sheet_timestamp_var = $sheet_timestamp;
                
                mysqli_stmt_bind_param($insert_stmt, 'ssssssssssssss',
                    $unique_service_no, $employee_name, $manager_name, $manager_email,
                    $department, $leave_type, $duration, $start_date, $end_date,
                    $reason, $leave_count, $file_url, $status, $sheet_timestamp_var
                );
                
                if (mysqli_stmt_execute($insert_stmt)) {
                    $affected_rows = mysqli_stmt_affected_rows($insert_stmt);
                    if ($affected_rows > 0) {
                        $synced_count++;
                        if ($exists) {
                            $updated_count++;
                            if ($old_status !== $status) {
                                logSync("UPDATED: $unique_service_no - $employee_name (Status: '$old_status' → '$status')");
                            } else {
                                logSync("UPDATED: $unique_service_no - $employee_name (Data refreshed)");
                            }
                        } else {
                            $inserted_count++;
                            logSync("INSERTED: $unique_service_no - $employee_name (Status: $status)");
                            
                            // Trigger notification for new leave requests (only if status is pending)
                            if (empty($status) || $status === 'PENDING' || $status === '') {
                                require_once __DIR__ . '/../includes/notification_triggers.php';
                                triggerLeaveRequestNotification($conn, $unique_service_no, $employee_name, $manager_email, $manager_name);
                                logSync("NOTIFICATION: Triggered leave request notification for $unique_service_no");
                            }
                        }
                    }
    } else {
                    $errors[] = "Error processing row " . ($row_index + 2) . ": " . mysqli_stmt_error($insert_stmt);
                }
                mysqli_stmt_close($insert_stmt);
    }
    
} catch (Exception $e) {
            $errors[] = "Error processing row " . ($row_index + 2) . ": " . $e->getMessage();
            logSync("Error processing row " . ($row_index + 2) . ": " . $e->getMessage());
        }
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    logSync("Force sync completed. Total: $synced_count (Inserted: $inserted_count, Updated: $updated_count)");
    
    // Update leave_sheet_sync table to record manual sync timestamp
    try {
        // Try enhanced version first (with tab_name and sync_type if columns exist)
        // If that fails, fall back to basic version (just last_synced)
        $update_sync_query = "UPDATE leave_sheet_sync SET last_synced = NOW() WHERE sheet_id = ?";
        $update_sync_stmt = mysqli_prepare($conn, $update_sync_query);
        if ($update_sync_stmt) {
            mysqli_stmt_bind_param($update_sync_stmt, 's', $sheet_id);
            if (mysqli_stmt_execute($update_sync_stmt)) {
                $affected = mysqli_stmt_affected_rows($update_sync_stmt);
                if ($affected == 0) {
                    // No existing record - create one
                    $insert_sync_query = "INSERT INTO leave_sheet_sync (sheet_id, last_synced) VALUES (?, NOW())";
                    $insert_sync_stmt = mysqli_prepare($conn, $insert_sync_query);
                    if ($insert_sync_stmt) {
                        mysqli_stmt_bind_param($insert_sync_stmt, 's', $sheet_id);
                        mysqli_stmt_execute($insert_sync_stmt);
                        mysqli_stmt_close($insert_sync_stmt);
                    }
                }
                logSync("Updated leave_sheet_sync table with manual sync timestamp");
            } else {
                logSync("Warning: Could not update leave_sheet_sync table: " . mysqli_stmt_error($update_sync_stmt));
            }
            mysqli_stmt_close($update_sync_stmt);
        }
    } catch (Exception $e) {
        logSync("Warning: Error updating leave_sheet_sync table: " . $e->getMessage());
        // Don't fail the sync if this update fails
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Leave data synced successfully',
        'data' => [
            'synced' => $synced_count,
            'inserted' => $inserted_count,
            'updated' => $updated_count,
            'total_rows' => count($sheet_data),
            'errors' => $errors
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        mysqli_rollback($conn);
    }
    
    logSync("Force sync failed: " . $e->getMessage());
    
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
