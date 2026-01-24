<?php
/**
 * Cron Job Leave Sync Script - SMART VERSION
 * Runs automatically every 10-15 minutes to sync Google Sheets data
 * Uses U2/V2 change detection for efficiency (only syncs when needed)
 * This script is designed to run from command line (cron job)
 */

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Include database configuration from config file
require_once(__DIR__ . '/includes/config.php');


// Get Google Sheets configuration from config
$sheet_id = LEAVE_SHEET_ID;
$tab_name = LEAVE_TAB_REQUESTS;

// Log file for cron sync operations
$log_file = __DIR__ . '/../logs/cron_sync.log';

function logCronSync($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] CRON: {$message}\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    // Also output to console for cron job monitoring
    echo $log_entry;
}

// Start logging
logCronSync("=== STARTING SMART CRON SYNC WITH U2/V2 DETECTION ===");

try {
    // Use database connection from config.php
    if (!$conn) {
        logCronSync("ERROR: Database connection failed: " . mysqli_connect_error());
        exit(1);
    }
    
    // Set timezone
    mysqli_query($conn, "SET time_zone = '+05:30'");
    logCronSync("Database connected successfully");
    
    // Include Google Sheets client
    require_once(__DIR__ . '/includes/google_sheets_client.php');
    logCronSync("Google Sheets client loaded");
    
    // Initialize Google Sheets client
    $gs_client = new GoogleSheetsClient();
    
    logCronSync("Checking U2/V2 cells for changes in sheet: {$sheet_id}, tab: {$tab_name}");
    
    // Step 1: Read U2 and V2 cells from Google Sheet
    $check_range = $tab_name . '!U2:V2';
    $u2_v2_data = $gs_client->gs_list($sheet_id, $check_range);
    
    if (empty($u2_v2_data) || count($u2_v2_data) < 1) {
        logCronSync("ERROR: Could not read U2/V2 cells from Google Sheet");
        exit(1);
    }
    
    // Extract current U2 and V2 values
    $current_u2 = isset($u2_v2_data[0][0]) ? trim($u2_v2_data[0][0]) : '';
    $current_v2 = isset($u2_v2_data[0][1]) ? trim($u2_v2_data[0][1]) : '';
    
    logCronSync("Current U2 (total count): '{$current_u2}'");
    logCronSync("Current V2 (blank status count): '{$current_v2}'");
    
    // Step 2: Check existing sync record in database
    $sync_query = "SELECT * FROM leave_sheet_sync WHERE sheet_id = ?";
    $sync_stmt = mysqli_prepare($conn, $sync_query);
    mysqli_stmt_bind_param($sync_stmt, 's', $sheet_id);
    mysqli_stmt_execute($sync_stmt);
    $sync_result = mysqli_stmt_get_result($sync_stmt);
    $sync_record = mysqli_fetch_assoc($sync_result);
    mysqli_stmt_close($sync_stmt);
    
    // Step 3: Compare current vs stored values
    $should_sync = false;
    $sync_reason = '';
    
    if (!$sync_record) {
        // No sync record exists - create one and sync
        logCronSync("No sync record found - creating new record and syncing");
        $should_sync = true;
        $sync_reason = 'First sync - no previous record';
        
        // Create sync record
        $insert_sync = "INSERT INTO leave_sheet_sync (sheet_id, rows_count, actuals_count, last_rows_count, last_actuals_count) VALUES (?, ?, ?, '', '')";
        $insert_stmt = mysqli_prepare($conn, $insert_sync);
        mysqli_stmt_bind_param($insert_stmt, 'sss', $sheet_id, $current_u2, $current_v2);
        mysqli_stmt_execute($insert_stmt);
        mysqli_stmt_close($insert_stmt);
        
    } else {
        // Compare with stored values
        $stored_u2 = $sync_record['rows_count'] ?? '';
        $stored_v2 = $sync_record['actuals_count'] ?? '';
        
        logCronSync("Stored U2: '{$stored_u2}', Stored V2: '{$stored_v2}'");
        
        // Check if either U2 or V2 has changed
        if ($current_u2 !== $stored_u2 || $current_v2 !== $stored_v2) {
            $should_sync = true;
            $sync_reason = "Changes detected - U2: '{$stored_u2}' → '{$current_u2}', V2: '{$stored_v2}' → '{$current_v2}'";
            logCronSync("SYNC TRIGGERED: {$sync_reason}");
        } else {
            $should_sync = false;
            $sync_reason = "No changes detected - U2 and V2 values unchanged";
            logCronSync("SKIP SYNC: {$sync_reason}");
        }
    }
    
    // Step 4: Execute sync or skip
    if ($should_sync) {
        logCronSync("=== EXECUTING SYNC ===");
        logCronSync("Reason: {$sync_reason}");
        
        // Read ALL data from Google Sheets
        $data_range = $tab_name . '!A:O';
        $sheet_data = $gs_client->gs_list($sheet_id, $data_range);
        
        // Skip header rows (first 2 rows) since we're reading from A:O
        if (count($sheet_data) > 2) {
            $sheet_data = array_slice($sheet_data, 2); // Remove first 2 rows (headers)
        }
        
        if (empty($sheet_data)) {
            logCronSync("WARNING: No data found in sheet after sync trigger");
            exit(0);
        }
        
        $total_rows = count($sheet_data);
        logCronSync("Google Sheets API returned {$total_rows} data rows");
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        $synced_count = 0;
        $updated_count = 0;
        $inserted_count = 0;
        $errors = [];
        
        // Process ALL rows
        for ($row_index = 0; $row_index < $total_rows; $row_index++) {
            $row = $sheet_data[$row_index];
            
            try {
                // Skip empty rows
                if (empty($row[0]) && empty($row[1])) {
                    continue;
                }
                
                // Map columns
                $sheet_timestamp = $row[0] ?? null;
                $unique_service_no = $row[1] ?? null;
                $employee_name = $row[2] ?? null;
                $leave_type = $row[3] ?? null;
                $duration = $row[4] ?? null;
                $start_date = $row[5] ?? null;
                $end_date = $row[6] ?? null;
                $reason = $row[7] ?? null;
                $file_url = $row[8] ?? null;
                $leave_count = $row[9] ?? null;
                $manager_name = $row[10] ?? null;
                $department = $row[11] ?? null;
                $status = $row[13] ?? '';
                
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
                if ($insert_stmt) {
                    $manager_email = '';
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
                                    logCronSync("UPDATED: {$unique_service_no} - {$employee_name} (Status: {$old_status} → {$status})");
                                } else {
                                    logCronSync("UPDATED: {$unique_service_no} - {$employee_name} (Data refreshed)");
                                }
                            } else {
                                $inserted_count++;
                                logCronSync("INSERTED: {$unique_service_no} - {$employee_name} (New record)");
                            }
                        }
                    }
                    mysqli_stmt_close($insert_stmt);
                }
                
            } catch (Exception $e) {
                $errors[] = "Error processing row " . ($row_index + 3) . ": " . $e->getMessage();
                logCronSync("ERROR: Row " . ($row_index + 3) . " - " . $e->getMessage());
            }
        }
        
        // Update sync record with new values
        $update_sync = "UPDATE leave_sheet_sync SET 
            last_rows_count = rows_count,
            last_actuals_count = actuals_count,
            rows_count = ?,
            actuals_count = ?,
            last_synced = NOW()
            WHERE sheet_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sync);
        mysqli_stmt_bind_param($update_stmt, 'sss', $current_u2, $current_v2, $sheet_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
        
        // Commit transaction
        mysqli_commit($conn);
        
        // Log final results
        logCronSync("=== SMART CRON SYNC COMPLETED ===");
        logCronSync("Total records processed: {$total_rows}");
        logCronSync("Records synced: {$synced_count}");
        logCronSync("New records inserted: {$inserted_count}");
        logCronSync("Existing records updated: {$updated_count}");
        
        if (!empty($errors)) {
            logCronSync("Errors encountered: " . count($errors));
            foreach ($errors as $error) {
                logCronSync("ERROR: {$error}");
            }
        }
        
    } else {
        // Skip sync - update timestamp only
        $update_timestamp = "UPDATE leave_sheet_sync SET last_synced = NOW() WHERE sheet_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_timestamp);
        mysqli_stmt_bind_param($update_stmt, 's', $sheet_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
        
        logCronSync("=== SYNC SKIPPED ===");
        logCronSync("Reason: {$sync_reason}");
        logCronSync("Timestamp updated - no data processing needed");
    }
    
    logCronSync("=== END SMART CRON SYNC ===");
    
} catch (Exception $e) {
    if (isset($conn)) {
        mysqli_rollback($conn);
    }
    
    logCronSync("FATAL ERROR: " . $e->getMessage());
    exit(1);
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
}

// Exit with success code
exit(0);
?>