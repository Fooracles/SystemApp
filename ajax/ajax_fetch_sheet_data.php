<?php


// Capture output buffering to prevent any HTML or PHP notices from corrupting JSON output
ob_start();

// Temporarily enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Change to 0 to prevent PHP errors from being output

// Define a simple error logging function if it doesn't exist
if (!function_exists('log_activity')) {
    function log_activity($msg) {
        file_put_contents(__DIR__ . '/../log.txt', date('Y-m-d H:i:s') . ' [AJAX] ' . $msg . "\n", FILE_APPEND);
    }
}

// Log the start of the request
log_activity("AJAX fetch_sheet_data request started");

// Initialize response array to include logs
$response = [
    'status' => 'error',
    'message' => 'Invalid request.',
    'logs' => [],
    'debug_enabled' => true,
    'console_time' => date('Y-m-d H:i:s')
];

// Function to add logs to both file and response
function add_log($message) {
    global $response;
    log_activity($message);
    $response['logs'][] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message
    ];
}

add_log("AJAX fetch_sheet_data process initiated");

try {
    require_once '../includes/config.php';
    require_once '../includes/functions.php';
    add_log("Required files loaded");
    
    // Check if credentials file exists
    if (!file_exists(GOOGLE_APPLICATION_CREDENTIALS)) {
        add_log("ERROR: Google credentials file not found at: " . GOOGLE_APPLICATION_CREDENTIALS);
        throw new Exception("Google credentials file not found. Please contact administrator.");
    }
    add_log("Google credentials file found at: " . GOOGLE_APPLICATION_CREDENTIALS);
    
    // Check if vendor/autoload.php exists
    $autoload_path = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload_path)) {
        add_log("ERROR: vendor/autoload.php not found at: " . $autoload_path);
        throw new Exception("Required libraries not found. Please run 'composer install'.");
    }
    add_log("Vendor autoload file found");
    
    // Explicitly load the autoload file
    require_once $autoload_path;
    add_log("Autoload file loaded successfully");
    
    session_start();
    add_log("Session started");
    
    // Check if user is logged in
    if (!isset($_SESSION['id'])) {
        $response['message'] = 'User not authenticated.';
        add_log("User not authenticated");
        throw new Exception("User not authenticated.");
    }
    add_log("User authenticated: " . ($_SESSION['username'] ?? 'Unknown'));
    
    // Check if this is a POST request with required parameters
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_sheet_data') {
        $task_id = isset($_POST['task_id']) ? $_POST['task_id'] : '';
        $attempt_number = isset($_POST['attempt']) ? (int)$_POST['attempt'] : 0;
        
        add_log("Processing request: action=fetch_sheet_data, task_id={$task_id}, attempt={$attempt_number}");
        
        // Log the refresh attempt
        $user_name = $_SESSION['username'] ?? 'Unknown User';
        $log_message = "FMS refresh #{$attempt_number} requested by {$user_name} for task: {$task_id}";
        add_log($log_message);
        
        // Get sheet information for this task
        $fms_task_query = "SELECT sheet_id, sheet_label FROM fms_tasks WHERE unique_key = ?";
        add_log("Executing query: {$fms_task_query} with task_id: {$task_id}");
        
        // Try alternative column names if the task is not found
        $stmt = $conn->prepare($fms_task_query);
        
        if ($stmt) {
            $stmt->bind_param("s", $task_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                // Original query worked
                $task_data = $result->fetch_assoc();
                $sheet_id = $task_data['sheet_id'];
                $sheet_label = $task_data['sheet_label'] ?? '';
                add_log("Found sheet_id: {$sheet_id} for task: {$task_id}");
                
                // Get tab name from fms_sheets table
                $sheet_query = "SELECT tab_name, label FROM fms_sheets WHERE sheet_id = ?";
                add_log("Executing query: {$sheet_query} with sheet_id: {$sheet_id}");
                $sheet_stmt = $conn->prepare($sheet_query);
                
                if ($sheet_stmt) {
                    $sheet_stmt->bind_param("s", $sheet_id);
                    $sheet_stmt->execute();
                    $sheet_result = $sheet_stmt->get_result();
                    
                    if ($sheet_result && $sheet_result->num_rows > 0) {
                        $sheet_data = $sheet_result->fetch_assoc();
                        $tab_name = $sheet_data['tab_name'];
                        $sheet_label_from_sheets = $sheet_data['label'] ?? '';
                        if (empty($sheet_label) && !empty($sheet_label_from_sheets)) {
                            $sheet_label = $sheet_label_from_sheets;
                        }
                        add_log("Found tab_name: {$tab_name} for sheet_id: {$sheet_id}");
                        
                        try {
                            add_log("Attempting to call fetchSheetData({$sheet_id}, {$tab_name}, '')");
                            
                            // Verify that required classes are available
                            if (!class_exists('Google\\Client')) {
                                add_log("ERROR: Google Client class not found. Check if vendor/autoload.php is loaded correctly");
                                
                                // Try to load it explicitly again
                                $google_client_path = __DIR__ . '/../vendor/google/apiclient/src/Client.php';
                                if (file_exists($google_client_path)) {
                                    add_log("Found Google Client at: " . $google_client_path . ", attempting to load it directly");
                                    require_once $google_client_path;
                                    
                                    // Check again if class exists
                                    if (!class_exists('Google\\Client')) {
                                        add_log("ERROR: Still could not load Google Client class after direct inclusion");
                                        throw new Exception("Google API Client not available even after direct inclusion. Please check library installation.");
                                    } else {
                                        add_log("Successfully loaded Google Client class directly");
                                    }
                                } else {
                                    add_log("ERROR: Google Client file not found at expected path: " . $google_client_path);
                                    throw new Exception("Google API Client not available. Please check if the library is installed correctly.");
                                }
                            }
                            add_log("Google Client class found");
                            
                            // Call fetchSheetData function (full tab)
                            $sheet_values = fetchSheetData($sheet_id, $tab_name, '');

                            if ($sheet_values && count($sheet_values) > 0) {
                                add_log("Successfully fetched " . count($sheet_values) . " rows of data from Google Sheets");

                                // Synchronize this sheet into fms_tasks (reuse manage_sheets.php logic)
                                $imported_count = 0;
                                if (count($sheet_values) > 1) {
                                    // Remove header row
                                    array_shift($sheet_values);

                                    // Delete existing tasks for this sheet
                                    $del_stmt = $conn->prepare("DELETE FROM fms_tasks WHERE sheet_id = ?");
                                    if ($del_stmt) {
                                        $del_stmt->bind_param("s", $sheet_id);
                                        $del_stmt->execute();
                                        $del_stmt->close();
                                        add_log("Deleted existing fms_tasks for sheet_id: {$sheet_id}");
                                    }

                                    // Prepare insert statement
                                    $insert_stmt = $conn->prepare("INSERT INTO fms_tasks (sheet_id, unique_key, step_name, planned, actual, status, duration, doer_name, department, task_link, sheet_label, step_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                                    if ($insert_stmt) {
                                        foreach ($sheet_values as $row) {
                                            // Ensure at least 11 columns
                                            $row = array_pad(array_slice($row, 0, 11), 11, '');

                                            // Skip empty rows
                                            if (empty($row[0]) && empty($row[1]) && empty($row[2])) {
                                                continue;
                                            }

                                            $unique_key = $row[0] ?? '';
                                            $step_name  = $row[1] ?? '';
                                            $planned    = $row[2] ?? '';
                                            $actual     = $row[3] ?? '';
                                            $status     = $row[4] ?? '';
                                            $duration   = $row[6] ?? '';
                                            $doer_name  = $row[7] ?? '';
                                            $department = $row[9] ?? '';
                                            $task_link  = $row[8] ?? '';
                                            $step_code  = $row[10] ?? '';

                                            $label_to_use = $sheet_label ?? '';

                                            $insert_stmt->bind_param(
                                                "ssssssssssss",
                                                $sheet_id,
                                                $unique_key,
                                                $step_name,
                                                $planned,
                                                $actual,
                                                $status,
                                                $duration,
                                                $doer_name,
                                                $department,
                                                $task_link,
                                                $label_to_use,
                                                $step_code
                                            );

                                            if ($insert_stmt->execute()) {
                                                $imported_count++;
                                            }
                                        }
                                        $insert_stmt->close();
                                    }

                                    // Update sync metadata (last_synced)
                                    $now = date('Y-m-d H:i:s');
                                    $sync_stmt = $conn->prepare("INSERT INTO fms_sheet_sync (sheet_id, last_synced) VALUES (?, ?) ON DUPLICATE KEY UPDATE last_synced = ?");
                                    if ($sync_stmt) {
                                        $sync_stmt->bind_param("sss", $sheet_id, $now, $now);
                                        $sync_stmt->execute();
                                        $sync_stmt->close();
                                    }

                                    add_log("Synchronized {$imported_count} rows for sheet_id: {$sheet_id}");
                                } else {
                                    add_log("No data rows (only header) to import for sheet_id: {$sheet_id}");
                                }

                                // Determine if the clicked task is still pending after sync
                                $current_task_pending = null;
                                if (!empty($task_id)) {
                                    $pending_check_stmt = $conn->prepare("SELECT actual, status FROM fms_tasks WHERE unique_key = ? AND sheet_id = ? LIMIT 1");
                                    if ($pending_check_stmt) {
                                        $pending_check_stmt->bind_param("ss", $task_id, $sheet_id);
                                        $pending_check_stmt->execute();
                                        $pending_result = $pending_check_stmt->get_result();
                                        if ($pending_result && $pending_result->num_rows > 0) {
                                            $pending_row = $pending_result->fetch_assoc();
                                            $status_raw = isset($pending_row['status']) ? trim($pending_row['status']) : '';
                                            $status_lower = strtolower($status_raw);
                                            $actual_raw = isset($pending_row['actual']) ? trim($pending_row['actual']) : '';

                                            // Determine if 'actual' is truly filled (ignore placeholders like N/A, -, pending)
                                            $invalid_actuals = ['n/a','na','pending','-','—','not applicable',''];
                                            $has_actual = false;
                                            if ($actual_raw !== '') {
                                                $normalized_actual = strtolower($actual_raw);
                                                if (!in_array($normalized_actual, $invalid_actuals, true)) {
                                                    $ts = strtotime($actual_raw);
                                                    if ($ts !== false) {
                                                        $has_actual = true;
                                                    } else if (preg_match('/\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}/', $actual_raw)) {
                                                        $has_actual = true;
                                                    }
                                                }
                                            }

                                            // Normalize status to detect completion-like values
                                            $normalized_status = str_replace([" ", "'", '"'], '', $status_lower);
                                            $non_pending_statuses = [
                                                'completed','done','notdone','cannotbedone','cantbedone','can_not_be_done','not_done','closed'
                                            ];

                                            $is_non_pending_status = in_array($normalized_status, $non_pending_statuses, true);
                                            $current_task_pending = (!$has_actual && !$is_non_pending_status);
                                        }
                                        $pending_check_stmt->close();
                                    }
                                }
                                
                                // Debug logging for current_task_pending calculation
                                add_log("DEBUG: Main path - task_id: {$task_id}, current_task_pending: " . ($current_task_pending === null ? 'null' : ($current_task_pending ? 'true' : 'false')));

                                // Build success response
                                $response = [
                                    'status' => 'success',
                                    'message' => 'Sheet data fetched and synchronized successfully',
                                    'timestamp' => date('Y-m-d H:i:s'),
                                    'rows_count' => count($sheet_values),
                                    'imported_count' => $imported_count,
                                    'sheet_id' => $sheet_id,
                                    'tab_name' => $tab_name,
                                    'current_task_id' => $task_id,
                                    'current_task_pending' => $current_task_pending,
                                    'attempt' => $attempt_number,
                                    'logs' => $response['logs'],
                                    'debug_enabled' => true,
                                    'console_time' => date('Y-m-d H:i:s')
                                ];
                            } else {
                                add_log("No data found in the sheet");
                                $response = [
                                    'status' => 'warning',
                                    'message' => 'No data found in the sheet',
                                    'timestamp' => date('Y-m-d H:i:s'),
                                    'attempt' => $attempt_number,
                                    'logs' => $response['logs'],
                                    'debug_enabled' => true,
                                    'console_time' => date('Y-m-d H:i:s')
                                ];
                            }
                        } catch (Exception $e) {
                            $error_message = 'Error refreshing sheet data: ' . $e->getMessage();
                            add_log("Exception: " . $error_message);
                            throw new Exception($error_message);
                        }
                    } else {
                        add_log("Sheet not found for sheet_id: {$sheet_id}");
                        throw new Exception("Sheet not found");
                    }
                    
                    $sheet_stmt->close();
                    add_log("Sheet statement closed");
                } else {
                    $db_error = "Database error: " . $conn->error;
                    add_log($db_error);
                    throw new Exception($db_error);
                }
            } else {
                // Try alternative column name
                add_log("Task not found with unique_key, trying step_code column");
                $stmt->close();
                
                $alt_query = "SELECT sheet_id, sheet_label FROM fms_tasks WHERE step_code = ?";
                add_log("Executing alternative query: {$alt_query} with task_id: {$task_id}");
                
                $alt_stmt = $conn->prepare($alt_query);
                if ($alt_stmt) {
                    $alt_stmt->bind_param("s", $task_id);
                    $alt_stmt->execute();
                    $alt_result = $alt_stmt->get_result();
                    
                    if ($alt_result && $alt_result->num_rows > 0) {
                        $task_data = $alt_result->fetch_assoc();
                        $sheet_id = $task_data['sheet_id'];
                        $sheet_label = $task_data['sheet_label'] ?? '';
                        add_log("Found sheet_id with step_code: {$sheet_id} for task: {$task_id}");
                    } else {
                        add_log("Task not found with step_code either, last attempt with step_name");
                        $alt_stmt->close();
                        
                        // One last attempt with step_name
                        $last_query = "SELECT sheet_id, sheet_label FROM fms_tasks WHERE step_name = ?";
                        add_log("Executing last query: {$last_query} with task_id: {$task_id}");
                        
                        $last_stmt = $conn->prepare($last_query);
                        if ($last_stmt) {
                            $last_stmt->bind_param("s", $task_id);
                            $last_stmt->execute();
                            $last_result = $last_stmt->get_result();
                            
                            if ($last_result && $last_result->num_rows > 0) {
                                $task_data = $last_result->fetch_assoc();
                                $sheet_id = $task_data['sheet_id'];
                                $sheet_label = $task_data['sheet_label'] ?? '';
                                add_log("Found sheet_id with step_name: {$sheet_id} for task: {$task_id}");
                            } else {
                                add_log("Task not found for task_id: {$task_id} after trying all columns");
                                throw new Exception("Task not found after trying all possible columns");
                            }
                            $last_stmt->close();
                            add_log("Last statement closed");
                        }
                    }
                    if (isset($alt_stmt) && $alt_stmt) {
                        $alt_stmt->close();
                        add_log("Alternative statement closed");
                    }
                    
                    // Proceed with the same processing flow using the found $sheet_id
                    if (!empty($sheet_id)) {
                        $sheet_query = "SELECT tab_name, label FROM fms_sheets WHERE sheet_id = ?";
                        add_log("Executing query: {$sheet_query} with sheet_id: {$sheet_id}");
                        $sheet_stmt = $conn->prepare($sheet_query);
                        if ($sheet_stmt) {
                            $sheet_stmt->bind_param("s", $sheet_id);
                            $sheet_stmt->execute();
                            $sheet_result = $sheet_stmt->get_result();
                            if ($sheet_result && $sheet_result->num_rows > 0) {
                                $sheet_row = $sheet_result->fetch_assoc();
                                $tab_name = $sheet_row['tab_name'];
                                $sheet_label_from_sheets = $sheet_row['label'] ?? '';
                                if (empty($sheet_label) && !empty($sheet_label_from_sheets)) {
                                    $sheet_label = $sheet_label_from_sheets;
                                }
                                add_log("Found tab_name: {$tab_name} for sheet_id: {$sheet_id}");
                                
                                // Fetch and synchronize
                                $sheet_values = fetchSheetData($sheet_id, $tab_name, '');
                                if ($sheet_values && count($sheet_values) > 0) {
                                    add_log("Successfully fetched " . count($sheet_values) . " rows of data from Google Sheets");
                                    $imported_count = 0;
                                    if (count($sheet_values) > 1) {
                                        array_shift($sheet_values);
                                        $del_stmt2 = $conn->prepare("DELETE FROM fms_tasks WHERE sheet_id = ?");
                                        if ($del_stmt2) {
                                            $del_stmt2->bind_param("s", $sheet_id);
                                            $del_stmt2->execute();
                                            $del_stmt2->close();
                                            add_log("Deleted existing fms_tasks for sheet_id: {$sheet_id}");
                                        }
                                        $insert_stmt2 = $conn->prepare("INSERT INTO fms_tasks (sheet_id, unique_key, step_name, planned, actual, status, duration, doer_name, department, task_link, sheet_label, step_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                        if ($insert_stmt2) {
                                            foreach ($sheet_values as $row) {
                                                $row = array_pad(array_slice($row, 0, 11), 11, '');
                                                if (empty($row[0]) && empty($row[1]) && empty($row[2])) {
                                                    continue;
                                                }
                                                $insert_stmt2->bind_param(
                                                    "ssssssssssss",
                                                    $sheet_id,
                                                    ($row[0] ?? ''),
                                                    ($row[1] ?? ''),
                                                    ($row[2] ?? ''),
                                                    ($row[3] ?? ''),
                                                    ($row[4] ?? ''),
                                                    ($row[6] ?? ''),
                                                    ($row[7] ?? ''),
                                                    ($row[9] ?? ''),
                                                    ($row[8] ?? ''),
                                                    ($sheet_label ?? ''),
                                                    ($row[10] ?? '')
                                                );
                                                if ($insert_stmt2->execute()) { $imported_count++; }
                                            }
                                            $insert_stmt2->close();
                                        }
                                        $now = date('Y-m-d H:i:s');
                                        $sync_stmt2 = $conn->prepare("INSERT INTO fms_sheet_sync (sheet_id, last_synced) VALUES (?, ?) ON DUPLICATE KEY UPDATE last_synced = ?");
                                        if ($sync_stmt2) {
                                            $sync_stmt2->bind_param("sss", $sheet_id, $now, $now);
                                            $sync_stmt2->execute();
                                            $sync_stmt2->close();
                                        }
                                        add_log("Synchronized {$imported_count} rows for sheet_id: {$sheet_id}");
                                        
                                        // Check if the clicked task is still pending after sync (for fallback paths)
                                        $current_task_pending = null;
                                        if (!empty($task_id)) {
                                            $pending_check_stmt2 = $conn->prepare("SELECT actual, status FROM fms_tasks WHERE unique_key = ? AND sheet_id = ? LIMIT 1");
                                            if ($pending_check_stmt2) {
                                                $pending_check_stmt2->bind_param("ss", $task_id, $sheet_id);
                                                $pending_check_stmt2->execute();
                                                $pending_result2 = $pending_check_stmt2->get_result();
                                                if ($pending_result2 && $pending_result2->num_rows > 0) {
                                                    $pending_row2 = $pending_result2->fetch_assoc();
                                                    $status_raw2 = isset($pending_row2['status']) ? trim($pending_row2['status']) : '';
                                                    $status_lower2 = strtolower($status_raw2);
                                                    $actual_raw2 = isset($pending_row2['actual']) ? trim($pending_row2['actual']) : '';

                                                    // Determine if 'actual' is truly filled (ignore placeholders like N/A, -, pending)
                                                    $invalid_actuals2 = ['n/a','na','pending','-','—','not applicable',''];
                                                    $has_actual2 = false;
                                                    if ($actual_raw2 !== '') {
                                                        $normalized_actual2 = strtolower($actual_raw2);
                                                        if (!in_array($normalized_actual2, $invalid_actuals2, true)) {
                                                            $ts2 = strtotime($actual_raw2);
                                                            if ($ts2 !== false) {
                                                                $has_actual2 = true;
                                                            } else if (preg_match('/\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}/', $actual_raw2)) {
                                                                $has_actual2 = true;
                                                            }
                                                        }
                                                    }

                                                    // Normalize status to detect completion-like values
                                                    $normalized_status2 = str_replace([" ", "'", '"'], '', $status_lower2);
                                                    $non_pending_statuses2 = [
                                                        'completed','done','notdone','cannotbedone','cantbedone','can_not_be_done','not_done','closed'
                                                    ];

                                                    $is_non_pending_status2 = in_array($normalized_status2, $non_pending_statuses2, true);
                                                    $current_task_pending = (!$has_actual2 && !$is_non_pending_status2);
                                                }
                                                $pending_check_stmt2->close();
                                            }
                                        }
                                        
                                        // Debug logging for current_task_pending calculation
                                        add_log("DEBUG: Fallback path - task_id: {$task_id}, current_task_pending: " . ($current_task_pending === null ? 'null' : ($current_task_pending ? 'true' : 'false')));
                                    }
                                    $response = [
                                        'status' => 'success',
                                        'message' => 'Sheet data fetched and synchronized successfully',
                                        'timestamp' => date('Y-m-d H:i:s'),
                                        'rows_count' => count($sheet_values),
                                        'imported_count' => $imported_count,
                                        'sheet_id' => $sheet_id,
                                        'tab_name' => $tab_name,
                                        'current_task_id' => $task_id,
                                        'current_task_pending' => $current_task_pending,
                                        'attempt' => $attempt_number,
                                        'logs' => $response['logs'],
                                        'debug_enabled' => true,
                                        'console_time' => date('Y-m-d H:i:s')
                                    ];
                                } else {
                                    add_log("No data found in the sheet");
                                    $response = [
                                        'status' => 'warning',
                                        'message' => 'No data found in the sheet',
                                        'timestamp' => date('Y-m-d H:i:s'),
                                        'attempt' => $attempt_number,
                                        'logs' => $response['logs'],
                                        'debug_enabled' => true,
                                        'console_time' => date('Y-m-d H:i:s')
                                    ];
                                }
                            } else {
                                add_log("Sheet not found for sheet_id: {$sheet_id}");
                                throw new Exception("Sheet not found");
                            }
                            $sheet_stmt->close();
                            add_log("Sheet statement closed (alt path)");
                        } else {
                            $db_error = "Database error: " . $conn->error;
                            add_log($db_error);
                            throw new Exception($db_error);
                        }
                    }
                }
            }
            
            $stmt->close();
            add_log("Main statement closed");
        } else {
            $db_error = "Database error: " . $conn->error;
            add_log($db_error);
            throw new Exception($db_error);
        }
    } else {
        add_log("Invalid request parameters");
        throw new Exception("Invalid request parameters");
    }

} catch (Throwable $t) {
    // Catch any errors that might occur
    $error_message = "Error: " . $t->getMessage();
    add_log($error_message);
    
    $response = [
        'status' => 'error',
        'message' => $error_message,
        'timestamp' => date('Y-m-d H:i:s'),
        'attempt' => $attempt_number ?? 0,
        'logs' => $response['logs'],
        'debug_enabled' => true,
        'console_time' => date('Y-m-d H:i:s')
    ];
}

// Clean any output buffer before sending JSON
ob_end_clean();

// Send JSON response
header('Content-Type: application/json');


echo json_encode($response);

// After sending the response, restore error reporting settings for production
error_reporting(0);
ini_set('display_errors', 0);

add_log("Response sent, process completed");
exit;

// Close database connection
if (isset($conn) && $conn instanceof mysqli) {
    mysqli_close($conn);
}
?>
