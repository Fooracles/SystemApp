<?php
/**
 * Cron Job RQC Sync Script
 * Runs automatically every hour to sync Google Sheets "All RQC" tab data into database
 * This script is designed to run from command line (cron job)
 */

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Include database configuration from config file
require_once __DIR__ . '/../includes/config.php';

// Include Google Sheets client
require_once __DIR__ . '/../includes/google_sheets_client.php';

// Google Sheet configuration
define('RQC_SHEET_ID', '1E0wn_EIhNRyDnfBZDVm3swxE5YQxJkT5GPRssDbiZMQ');
define('RQC_TAB_NAME', 'All RQC');

// Log file for cron sync operations
$log_file = __DIR__ . '/../logs/rqc_cron.log';

/**
 * Log a message to the cron log file
 * 
 * @param string $message The message to log
 */
function logRqcSync($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] CRON: {$message}\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    // Also output to console for cron job monitoring
    echo $log_entry;
}

/**
 * Clean up log entries older than 2 days
 */
function cleanupOldLogs() {
    global $log_file;
    
    if (!file_exists($log_file)) {
        return;
    }
    
    $log_content = file_get_contents($log_file);
    $lines = explode("\n", $log_content);
    $cutoff_time = time() - (2 * 24 * 60 * 60); // 2 days ago
    $kept_lines = [];
    
    foreach ($lines as $line) {
        if (empty(trim($line))) {
            continue;
        }
        
        // Extract timestamp from log entry [YYYY-MM-DD HH:MM:SS]
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
            $log_timestamp = strtotime($matches[1]);
            if ($log_timestamp >= $cutoff_time) {
                $kept_lines[] = $line;
            }
        } else {
            // Keep lines without timestamps (shouldn't happen, but be safe)
            $kept_lines[] = $line;
        }
    }
    
    // Write back the cleaned log
    file_put_contents($log_file, implode("\n", $kept_lines) . "\n", LOCK_EX);
}

// Start logging
logRqcSync("=== STARTING RQC SHEET SYNC ===");

try {
    // Use database connection from config.php
    if (!$conn) {
        logRqcSync("ERROR: Database connection failed: " . mysqli_connect_error());
        exit(1);
    }
    
    // Set timezone
    mysqli_query($conn, "SET time_zone = '+05:30'");
    logRqcSync("Database connected successfully");
    
    // Ensure rqc_scores table exists
    $table_check = "SHOW TABLES LIKE 'rqc_scores'";
    $table_result = mysqli_query($conn, $table_check);
    if (mysqli_num_rows($table_result) == 0) {
        logRqcSync("Creating rqc_scores table...");
        $create_table = "CREATE TABLE IF NOT EXISTS rqc_scores (
            id INT(11) NOT NULL AUTO_INCREMENT,
            timestamp DATETIME NOT NULL,
            name VARCHAR(255) NOT NULL,
            score VARCHAR(50) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX (timestamp),
            INDEX (name),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        if (mysqli_query($conn, $create_table)) {
            logRqcSync("rqc_scores table created successfully");
        } else {
            logRqcSync("ERROR: Failed to create rqc_scores table: " . mysqli_error($conn));
            exit(1);
        }
    }
    
    // Initialize Google Sheets client
    $gs_client = new GoogleSheetsClient();
    logRqcSync("Google Sheets client initialized");
    
    // Fetch data from Google Sheet
    $range = RQC_TAB_NAME . '!A:C'; // Columns: Timestamp, Name, Score
    logRqcSync("Fetching data from Google Sheet: " . RQC_SHEET_ID . ", Tab: " . RQC_TAB_NAME);
    
    $sheet_data = $gs_client->gs_list(RQC_SHEET_ID, $range);
    
    if (empty($sheet_data)) {
        logRqcSync("WARNING: No data found in Google Sheet");
        exit(0);
    }
    
    // Skip header row if present (first row)
    if (count($sheet_data) > 0) {
        $first_row = $sheet_data[0];
        // Check if first row looks like a header (contains "Timestamp", "Name", or "Score")
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
        exit(0);
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
                logRqcSync("Skipped row " . ($row_index + 1) . ": Missing required fields (Timestamp, Name, or Score)");
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
                        logRqcSync("ERROR: Row " . ($row_index + 1) . " - Invalid timestamp format: {$sheet_timestamp}");
                        continue;
                    }
                }
            }
            
            // Check if record already exists (based on timestamp + name)
            // This allows updating the score if it changed
            $check_query = "SELECT id, score FROM rqc_scores WHERE timestamp = ? AND name = ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            
            if (!$check_stmt) {
                $errors[] = "Row " . ($row_index + 1) . ": Failed to prepare check query: " . mysqli_error($conn);
                logRqcSync("ERROR: Row " . ($row_index + 1) . " - " . mysqli_error($conn));
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
                        $errors[] = "Row " . ($row_index + 1) . ": Failed to prepare update query: " . mysqli_error($conn);
                        logRqcSync("ERROR: Row " . ($row_index + 1) . " - " . mysqli_error($conn));
                        continue;
                    }
                    
                    $record_id = $existing_record['id'];
                    mysqli_stmt_bind_param($update_stmt, 'si', $score, $record_id);
                    
                    if (mysqli_stmt_execute($update_stmt)) {
                        $updated_count++;
                        logRqcSync("UPDATED: {$name} - Score: {$existing_score} → {$score} - Timestamp: {$timestamp}");
                    } else {
                        $errors[] = "Row " . ($row_index + 1) . ": Failed to update: " . mysqli_error($conn);
                        logRqcSync("ERROR: Row " . ($row_index + 1) . " - " . mysqli_error($conn));
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
                $errors[] = "Row " . ($row_index + 1) . ": Failed to prepare insert query: " . mysqli_error($conn);
                logRqcSync("ERROR: Row " . ($row_index + 1) . " - " . mysqli_error($conn));
                continue;
            }
            
            mysqli_stmt_bind_param($insert_stmt, 'sss', $timestamp, $name, $score);
            
            if (mysqli_stmt_execute($insert_stmt)) {
                $inserted_count++;
                logRqcSync("INSERTED: {$name} - Score: {$score} - Timestamp: {$timestamp}");
            } else {
                $errors[] = "Row " . ($row_index + 1) . ": Failed to insert: " . mysqli_error($conn);
                logRqcSync("ERROR: Row " . ($row_index + 1) . " - " . mysqli_error($conn));
            }
            
            mysqli_stmt_close($insert_stmt);
            
        } catch (Exception $e) {
            $errors[] = "Row " . ($row_index + 1) . ": " . $e->getMessage();
            logRqcSync("ERROR: Row " . ($row_index + 1) . " - " . $e->getMessage());
        }
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    // Log final results
    logRqcSync("=== RQC SYNC COMPLETED ===");
    logRqcSync("Total rows fetched: {$total_rows}");
    logRqcSync("New records inserted: {$inserted_count}");
    logRqcSync("Records updated: {$updated_count}");
    logRqcSync("Records skipped (no changes): {$skipped_count}");
    
    if (!empty($errors)) {
        logRqcSync("Errors encountered: " . count($errors));
        foreach (array_slice($errors, 0, 10) as $error) { // Log first 10 errors
            logRqcSync("ERROR: {$error}");
        }
        if (count($errors) > 10) {
            logRqcSync("... and " . (count($errors) - 10) . " more errors");
        }
    }
    
    // Clean up old log entries (older than 2 days)
    logRqcSync("Cleaning up log entries older than 2 days");
    cleanupOldLogs();
    logRqcSync("Log cleanup completed");
    
    logRqcSync("=== END RQC SYNC ===");
    
} catch (Exception $e) {
    if (isset($conn)) {
        mysqli_rollback($conn);
    }
    
    logRqcSync("FATAL ERROR: " . $e->getMessage());
    logRqcSync("Stack trace: " . $e->getTraceAsString());
    exit(1);
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
}

// Exit with success code
exit(0);
?>

