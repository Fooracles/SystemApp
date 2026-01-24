<?php
/**
 * RQC Auto Sync Endpoint
 * Manual sync trigger for RQC scores from Google Sheets
 * This endpoint can be called from the Admin UI
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
        'error' => 'Database connection failed: ' . mysqli_connect_error()
    ]);
    exit;
}

// Set timezone
mysqli_query($conn, "SET time_zone = '+05:30'");

// Include Google Sheets client
require_once __DIR__ . '/../includes/google_sheets_client.php';

// Google Sheet configuration
define('RQC_SHEET_ID', '1E0wn_EIhNRyDnfBZDVm3swxE5YQxJkT5GPRssDbiZMQ');
define('RQC_TAB_NAME', 'All RQC');

// Log file for sync operations
$log_file = __DIR__ . '/../logs/rqc_cron.log';

function logRqcSync($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] MANUAL: {$message}\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

try {
    logRqcSync("=== STARTING MANUAL RQC SYNC ===");
    logRqcSync("Database connection: " . ($conn ? "SUCCESS" : "FAILED"));
    logRqcSync("Sheet ID: " . RQC_SHEET_ID);
    logRqcSync("Tab Name: " . RQC_TAB_NAME);
    
    // Initialize Google Sheets client
    $gs_client = new GoogleSheetsClient();
    logRqcSync("Google Sheets client initialized successfully");
    
    // Fetch data from Google Sheet
    $range = RQC_TAB_NAME . '!A:C'; // Columns: Timestamp, Name, Score
    logRqcSync("Fetching data from Google Sheet");
    
    $sheet_data = $gs_client->gs_list(RQC_SHEET_ID, $range);
    
    if (empty($sheet_data)) {
        logRqcSync("WARNING: No data found in Google Sheet");
        echo json_encode([
            'success' => true,
            'message' => 'No data found in Google Sheet',
            'data' => ['synced' => 0, 'inserted' => 0, 'updated' => 0]
        ]);
        exit;
    }
    
    // Skip header row if present (first row)
    if (count($sheet_data) > 0) {
        $first_row = $sheet_data[0];
        // Check if first row looks like a header
        if (isset($first_row[0]) && (
            stripos($first_row[0], 'timestamp') !== false || 
            stripos($first_row[0], 'name') !== false || 
            stripos($first_row[0], 'score') !== false
        )) {
            $sheet_data = array_slice($sheet_data, 1); // Remove header row
            logRqcSync("Skipped header row");
        }
    }
    
    $total_rows = count($sheet_data);
    logRqcSync("Fetched {$total_rows} rows from Google Sheet");
    
    if ($total_rows == 0) {
        logRqcSync("No data rows to process");
        echo json_encode([
            'success' => true,
            'message' => 'No data rows to process',
            'data' => ['synced' => 0, 'inserted' => 0, 'updated' => 0]
        ]);
        exit;
    }
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    $inserted_count = 0;
    $updated_count = 0;
    $skipped_count = 0;
    $errors = [];
    
    // Process each row
    foreach ($sheet_data as $row_index => $row) {
        try {
            // Skip empty rows
            if (empty($row[0]) && empty($row[1]) && empty($row[2])) {
                $skipped_count++;
                continue;
            }
            
            // Extract columns: Timestamp, Name, Score
            $sheet_timestamp = isset($row[0]) ? trim($row[0]) : '';
            $name = isset($row[1]) ? trim($row[1]) : '';
            $score = isset($row[2]) ? trim($row[2]) : '';
            
            // Validate required fields
            if (empty($sheet_timestamp) || empty($name) || empty($score)) {
                $skipped_count++;
                continue;
            }
            
            // Convert timestamp to MySQL DATETIME format
            $timestamp = null;
            if (!empty($sheet_timestamp)) {
                // Try to parse the timestamp
                $parsed_timestamp = strtotime($sheet_timestamp);
                if ($parsed_timestamp !== false) {
                    $timestamp = date('Y-m-d H:i:s', $parsed_timestamp);
                } else {
                    // Try alternative formats
                    $timestamp_formats = [
                        'Y-m-d H:i:s',
                        'Y/m/d H:i:s',
                        'd/m/Y H:i:s',
                        'd-m-Y H:i:s',
                        'Y-m-d',
                        'd/m/Y',
                        'd-m-Y'
                    ];
                    
                    $timestamp = null;
                    foreach ($timestamp_formats as $format) {
                        $parsed = DateTime::createFromFormat($format, $sheet_timestamp);
                        if ($parsed !== false) {
                            $timestamp = $parsed->format('Y-m-d H:i:s');
                            break;
                        }
                    }
                    
                    if ($timestamp === null) {
                        $errors[] = "Row " . ($row_index + 1) . ": Could not parse timestamp: {$sheet_timestamp}";
                        continue;
                    }
                }
            }
            
            // Check if record already exists (based on timestamp + name)
            $check_query = "SELECT id, score FROM rqc_scores WHERE timestamp = ? AND name = ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            
            if (!$check_stmt) {
                $errors[] = "Row " . ($row_index + 1) . ": Failed to prepare check query";
                continue;
            }
            
            mysqli_stmt_bind_param($check_stmt, 'ss', $timestamp, $name);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            $existing_record = mysqli_fetch_assoc($check_result);
            mysqli_stmt_close($check_stmt);
            
            if ($existing_record) {
                // Record exists - check if score needs updating
                $existing_score = $existing_record['score'];
                if ($existing_score !== $score) {
                    // Update the score
                    $update_query = "UPDATE rqc_scores SET score = ? WHERE id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_query);
                    
                    if (!$update_stmt) {
                        $errors[] = "Row " . ($row_index + 1) . ": Failed to prepare update query";
                        continue;
                    }
                    
                    $record_id = $existing_record['id'];
                    mysqli_stmt_bind_param($update_stmt, 'si', $score, $record_id);
                    
                    if (mysqli_stmt_execute($update_stmt)) {
                        $updated_count++;
                        logRqcSync("UPDATED: {$name} - Score: {$existing_score} → {$score}");
                    } else {
                        $errors[] = "Row " . ($row_index + 1) . ": Failed to update";
                    }
                    
                    mysqli_stmt_close($update_stmt);
                } else {
                    // Score unchanged, skip
                    $skipped_count++;
                }
                continue;
            }
            
            // Insert new record
            $insert_query = "INSERT INTO rqc_scores (timestamp, name, score, created_at) VALUES (?, ?, ?, NOW())";
            $insert_stmt = mysqli_prepare($conn, $insert_query);
            
            if (!$insert_stmt) {
                $errors[] = "Row " . ($row_index + 1) . ": Failed to prepare insert query";
                continue;
            }
            
            mysqli_stmt_bind_param($insert_stmt, 'sss', $timestamp, $name, $score);
            
            if (mysqli_stmt_execute($insert_stmt)) {
                $inserted_count++;
                logRqcSync("INSERTED: {$name} - Score: {$score}");
            } else {
                $errors[] = "Row " . ($row_index + 1) . ": Failed to insert";
            }
            
            mysqli_stmt_close($insert_stmt);
            
        } catch (Exception $e) {
            $errors[] = "Row " . ($row_index + 1) . ": " . $e->getMessage();
            logRqcSync("ERROR: Row " . ($row_index + 1) . " - " . $e->getMessage());
        }
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    $synced_count = $inserted_count + $updated_count;
    logRqcSync("Manual sync completed. Total: {$synced_count} (Inserted: {$inserted_count}, Updated: {$updated_count})");
    
    echo json_encode([
        'success' => true,
        'message' => 'RQC data synced successfully',
        'data' => [
            'synced' => $synced_count,
            'inserted' => $inserted_count,
            'updated' => $updated_count,
            'skipped' => $skipped_count,
            'total_rows' => $total_rows,
            'errors' => $errors
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        mysqli_rollback($conn);
    }
    
    logRqcSync("Manual sync failed: " . $e->getMessage());
    
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

