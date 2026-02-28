<?php
// Start output buffering to catch any stray output or PHP errors
ob_start();

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/ajax_errors.log');

// Include status column helpers for real-time icon updates
require_once "../includes/status_column_helpers.php";

// Initialize a global response variable for the shutdown function to access
$GLOBALS['ajax_response_data'] = ['status' => 'error', 'message' => 'An unexpected server error occurred.'];

// Function to send JSON response and exit cleanly
function send_ajax_json_response($data) {
    // Log the response for debugging
    error_log("AJAX Response: " . json_encode($data));
    
    // If output buffering is active and has content, clean it to prevent interference with JSON.
    if (ob_get_level() > 0 && ob_get_length() > 0) {
        ob_end_clean(); // Discard current buffer contents
    }
    // Ensure headers are not already sent (though ob_end_clean should handle most cases)
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode($data);
    exit;
}

// Shutdown function to catch fatal errors and ensure JSON is output
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        error_log("PHP Fatal Error in AJAX: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        
        $GLOBALS['ajax_response_data']['status'] = 'error';
        $GLOBALS['ajax_response_data']['message'] = 'Critical server error. Please contact support or check server logs. (Error type: ' . $error['type'] . ')';
        
        send_ajax_json_response($GLOBALS['ajax_response_data']);
    }
});

// --- Main script logic begins --- 
require_once '../includes/config.php'; // This line assumes config.php exists in includes/
require_once '../includes/functions.php'; 

// Log the request for debugging
error_log("action_update_task_status.php called with POST data: " . json_encode($_POST));

if (!isset($_SESSION['id'])) {
    error_log("User not authenticated in action_update_task_status.php");
    $GLOBALS['ajax_response_data']['message'] = 'User not authenticated.';
    send_ajax_json_response($GLOBALS['ajax_response_data']);
}

// Log user session info
error_log("User session info - ID: " . $_SESSION['id'] . ", Username: " . ($_SESSION['username'] ?? 'N/A') . ", User Type: " . ($_SESSION['user_type'] ?? 'N/A'));

// --- MODIFIED Authorization Check ---
// Allow Doers to proceed if they are trying to complete a task, further checks will apply.
// Admins and Managers can perform any status update.
$is_completing_action = (isset($_POST['status']) && $_POST['status'] === 'completed');

if (!$is_completing_action && !isAdmin() && !isManager()) {
    error_log("User not authorized for general status update - User Type: " . ($_SESSION['user_type'] ?? 'N/A') . ", Status: " . ($_POST['status'] ?? 'N/A'));
    $GLOBALS['ajax_response_data']['message'] = 'User not authorized to perform this general status update.';
    send_ajax_json_response($GLOBALS['ajax_response_data']);
}
// If it IS a completing action by a non-admin/manager, specific checks will be done later.
// If user is Admin or Manager, they are allowed to proceed for any status.


if (!isset($conn)) {
    error_log("Database connection failed in action_update_task_status.php");
    $GLOBALS['ajax_response_data']['message'] = 'Database connection failed (db.php did not establish $conn).';
    send_ajax_json_response($GLOBALS['ajax_response_data']);
}

$GLOBALS['ajax_response_data']['message'] = 'Invalid request data.';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id']) && isset($_POST['status'])) {
    $task_id = (int)$_POST['task_id'];
    $new_status = $_POST['status']; // This will be e.g., 'pending', 'completed', 'not_done', 'cant_be_done' from the dropdown value
    $task_type = $_POST['task_type'] ?? 'delegation'; // Get task type from POST data
    
    error_log("Processing task update - Task ID: $task_id, New Status: $new_status, Task Type: $task_type");
    
    // Align with the actual ENUM values in the database schema
    $allowed_statuses = ['pending', 'completed', 'shifted', 'not done', 'can not be done'];

    // Map dropdown values (with underscores) to DB values (with spaces) if necessary,
    // or ensure dropdown values directly match DB ENUM.
    // Current JS sends 'not_done', 'cant_be_done'. The DB ENUM is 'not done', 'can not be done'.
    // So, we need to ensure the $new_status variable used for DB operations has spaces.
    // The $new_status from POST is already 'completed' for the doer dashboard button.
    $db_new_status = $new_status; // Assume $new_status is already correct for DB.
                                  // This script is also used by manager_dashboard where other statuses are possible.
    if ($new_status === 'not_done') {
        $db_new_status = 'not done';
    } elseif ($new_status === 'cant_be_done') {
        $db_new_status = 'can not be done';
    }
    // 'completed', 'pending', 'shifted' directly map.


    if (!in_array($db_new_status, $allowed_statuses)) { // Check $db_new_status against allowed DB ENUMs
        error_log("Invalid status for DB: $db_new_status");
        $GLOBALS['ajax_response_data']['message'] = 'Invalid status for DB: ' . htmlspecialchars($db_new_status);
        send_ajax_json_response($GLOBALS['ajax_response_data']);
    }

    if (!function_exists('formatSecondsToHHMMSS')) {
        error_log("FATAL: formatSecondsToHHMMSS function not found in action_update_task_status.php. Check includes/functions.php.");
        $GLOBALS['ajax_response_data']['message'] = 'Server configuration error: Essential formatting function (formatSecondsToHHMMSS) is missing.';
        send_ajax_json_response($GLOBALS['ajax_response_data']);
    }

    // Fetch task details based on task type
    $task_details = null;
    $table_name = '';
    $id_field = 'id';
    
    switch ($task_type) {
        case 'delegation':
            $table_name = 'tasks';
            $id_field = 'id';
            break;
        case 'checklist':
            $table_name = 'checklist_subtasks';
            $id_field = 'id';
            break;
        case 'fms':
            $table_name = 'fms_tasks';
            $id_field = 'id';
            break;
        default:
            $GLOBALS['ajax_response_data']['message'] = 'Invalid task type: ' . htmlspecialchars($task_type);
            send_ajax_json_response($GLOBALS['ajax_response_data']);
    }
    
    // Build query based on task type
    if ($task_type === 'delegation') {
        $sql_get_task = "SELECT planned_date, planned_time, doer_id FROM tasks WHERE id = ?";
    } elseif ($task_type === 'checklist') {
        $sql_get_task = "SELECT task_date as planned_date, '23:59:59' as planned_time, assignee as doer_id FROM checklist_subtasks WHERE id = ?";
    } elseif ($task_type === 'fms') {
        $sql_get_task = "SELECT planned, '00:00:00' as planned_time, doer_name as doer_id FROM fms_tasks WHERE id = ?";
    }
    
    $stmt_get_task = mysqli_prepare($conn, $sql_get_task);
    
    if (!$stmt_get_task) {
        $GLOBALS['ajax_response_data']['message'] = 'DB Error (select prepare): ' . mysqli_error($conn);
        send_ajax_json_response($GLOBALS['ajax_response_data']);
    }
    
    mysqli_stmt_bind_param($stmt_get_task, "i", $task_id);
    if (!mysqli_stmt_execute($stmt_get_task)) {
        $GLOBALS['ajax_response_data']['message'] = 'DB Error (select execute): ' . mysqli_stmt_error($stmt_get_task);
        mysqli_stmt_close($stmt_get_task);
        send_ajax_json_response($GLOBALS['ajax_response_data']);
    }
    
    $result_get_task = mysqli_stmt_get_result($stmt_get_task);
    $task_details = mysqli_fetch_assoc($result_get_task);
    mysqli_stmt_close($stmt_get_task);

    if (!$task_details) {
        $GLOBALS['ajax_response_data']['message'] = 'Task not found.';
        send_ajax_json_response($GLOBALS['ajax_response_data']);
    }

    $planned_datetime_str = $task_details['planned_date'] . ' ' . $task_details['planned_time'];
    $planned_timestamp = (!empty($task_details['planned_date']) && !empty($task_details['planned_time'])) ? strtotime($planned_datetime_str) : false;
    
    date_default_timezone_set('Asia/Kolkata'); // Set the timezone
    $actual_date = date('Y-m-d'); // Get current date based on timezone
    $actual_time = date('H:i:s'); // Get current time based on timezone

    // Use these new variables for the logic below, instead of deriving from $current_datetime for these specific values.
    // $current_datetime is still useful for $current_timestamp comparison.
    $current_datetime = new DateTime(); // This will now respect Asia/Kolkata for subsequent operations if needed
    $current_timestamp = $current_datetime->getTimestamp();

    $actual_date_val = NULL;
    $actual_time_val = NULL;
    $is_delayed_val = 0;
    $delay_duration_val = NULL;
    // Use $db_new_status for formatting text for user messages, assuming it's the canonical version.
    $formatted_new_status_text = ucfirst($db_new_status); 

    $sql_update = ""; 
    $param_types_update = "";
    $params_update = [];

    // Handle FMS tasks differently (they don't support status updates via this interface)
    if ($task_type === 'fms') {
        $GLOBALS['ajax_response_data']['message'] = 'FMS tasks cannot be updated through this interface.';
        send_ajax_json_response($GLOBALS['ajax_response_data']);
    }

    switch ($db_new_status) { // Switch on $db_new_status for DB operations
        case 'completed':
            // --- ADDED Specific Authorization for Doer 'completed' action ---
            if (!isAdmin() && !isManager()) { // Current user is a Doer
                if (!isset($task_details['doer_id']) || $task_details['doer_id'] != $_SESSION['id']) {
                    $GLOBALS['ajax_response_data']['message'] = 'User not authorized to mark this task as completed.';
                    send_ajax_json_response($GLOBALS['ajax_response_data']);
                }
            }
            // --- END ADDED Authorization ---

            $actual_date_val = $actual_date; // Use the new variable
            $actual_time_val = $actual_time; // Use the new variable
            if ($planned_timestamp !== false && $current_timestamp > $planned_timestamp) {
                $is_delayed_val = 1;
                $delay_seconds = $current_timestamp - $planned_timestamp;
                $delay_duration_val = formatDelayWithDays($delay_seconds);
            } else {
                $is_delayed_val = 0;
                $delay_duration_val = '00:00:00';
            }
            
            if ($task_type === 'delegation') {
                $sql_update = "UPDATE tasks SET status = ?, actual_date = ?, actual_time = ?, is_delayed = ?, delay_duration = ? WHERE id = ?";
                $param_types_update = "sssisi";
                $params_update = [$db_new_status, $actual_date_val, $actual_time_val, $is_delayed_val, $delay_duration_val, $task_id];
            } elseif ($task_type === 'checklist') {
                $sql_update = "UPDATE checklist_subtasks SET status = ?, actual_date = ?, actual_time = ?, is_delayed = ?, delay_duration = ? WHERE id = ?";
                $param_types_update = "sssisi";
                $params_update = [$db_new_status, $actual_date_val, $actual_time_val, $is_delayed_val, $delay_duration_val, $task_id];
            }
            break;

        case 'pending':
            $actual_date_val = NULL;
            $actual_time_val = NULL;
            if ($planned_timestamp !== false && $current_timestamp > $planned_timestamp) {
                $is_delayed_val = 1;
                $delay_seconds = $current_timestamp - $planned_timestamp;
                $delay_duration_val = formatDelayWithDays($delay_seconds);
            } else {
                $is_delayed_val = 0;
                $delay_duration_val = NULL; // Or '00:00:00' if preferred for pending non-delayed
            }
            // For pending, we primarily update status, is_delayed and delay_duration. Actual times are nulled.
            if ($task_type === 'delegation') {
                $sql_update = "UPDATE tasks SET status = ?, actual_date = NULL, actual_time = NULL, is_delayed = ?, delay_duration = ? WHERE id = ?";
                $param_types_update = "sisi"; // status, is_delayed, delay_duration, id
                $params_update = [$db_new_status, $is_delayed_val, $delay_duration_val, $task_id];
            } elseif ($task_type === 'checklist') {
                $sql_update = "UPDATE checklist_subtasks SET status = ?, actual_date = NULL, actual_time = NULL, is_delayed = ?, delay_duration = ? WHERE id = ?";
                $param_types_update = "sisi"; // status, is_delayed, delay_duration, id
                $params_update = [$db_new_status, $is_delayed_val, $delay_duration_val, $task_id];
            }
            break;

        case 'not done':             // Use space version for case
        case 'can not be done':      // Use space version for case
            if ($task_type === 'delegation') {
                $sql_update = "UPDATE tasks SET status = ?, actual_date = NULL, actual_time = NULL, is_delayed = 0, delay_duration = NULL WHERE id = ?";
                $param_types_update = "si"; // status, id
                $params_update = [$db_new_status, $task_id];
            } elseif ($task_type === 'checklist') {
                $sql_update = "UPDATE checklist_subtasks SET status = ?, actual_date = NULL, actual_time = NULL, is_delayed = 0, delay_duration = NULL WHERE id = ?";
                $param_types_update = "si"; // status, id
                $params_update = [$db_new_status, $task_id];
            }
            $actual_date_val = NULL; 
            $actual_time_val = NULL;
            $is_delayed_val = 0;
            $delay_duration_val = NULL;
            break;
    }

    if (empty($sql_update)) {
        // Use $db_new_status in error message
        $GLOBALS['ajax_response_data']['message'] = 'Internal error: SQL update query not formed for status ' . htmlspecialchars($db_new_status);
        send_ajax_json_response($GLOBALS['ajax_response_data']);
    }

    $stmt_update = mysqli_prepare($conn, $sql_update);

    if (!$stmt_update) {
        $GLOBALS['ajax_response_data']['message'] = 'DB Error (update prepare): ' . mysqli_error($conn);
    } else {
        mysqli_stmt_bind_param($stmt_update, $param_types_update, ...$params_update);
        if (mysqli_stmt_execute($stmt_update)) {
            if (mysqli_stmt_affected_rows($stmt_update) > 0) {
                $GLOBALS['ajax_response_data']['status'] = 'success';
                $GLOBALS['ajax_response_data']['message'] = 'Task status updated to ' . $formatted_new_status_text . '.';
                $GLOBALS['ajax_response_data']['new_status_text'] = $formatted_new_status_text;
                $GLOBALS['ajax_response_data']['new_status_icon'] = get_status_icon($new_status);
                
                if ($db_new_status === 'completed') {
                    $GLOBALS['ajax_response_data']['updated_actual_display'] = formatDateTime($actual_date_val, $actual_time_val);
                    if ($is_delayed_val == 1 && !empty($delay_duration_val)) {
                        $GLOBALS['ajax_response_data']['updated_delay_display_html'] = '<span class="text-danger">' . htmlspecialchars($delay_duration_val) . '</span>';
                    } else {
                        $GLOBALS['ajax_response_data']['updated_delay_display_html'] = !empty($delay_duration_val) ? htmlspecialchars($delay_duration_val) : '00:00:00';
                    }
                } else if ($db_new_status === 'pending') {
                     $GLOBALS['ajax_response_data']['updated_actual_display'] = 'N/A';
                     // For pending tasks, calculate delay based on current time vs planned time
                     if ($is_delayed_val == 1 && !empty($delay_duration_val)) {
                         $GLOBALS['ajax_response_data']['updated_delay_display_html'] = '<span class="text-danger">' . htmlspecialchars($delay_duration_val) . '</span>';
                     } else {
                         $GLOBALS['ajax_response_data']['updated_delay_display_html'] = 'N/A';
                     }
                } else { // not_done, cant_be_done
                    $GLOBALS['ajax_response_data']['updated_actual_display'] = 'N/A';
                    $GLOBALS['ajax_response_data']['updated_delay_display_html'] = 'N/A';
                }
            } else {
                // Fetch current details to check if status is already set and for UI update fields
                if ($task_type === 'delegation') {
                    $current_db_details_sql = "SELECT status, actual_date, actual_time, is_delayed, delay_duration FROM tasks WHERE id = ?";
                } elseif ($task_type === 'checklist') {
                    $current_db_details_sql = "SELECT status, actual_date, actual_time, is_delayed, delay_duration FROM checklist_subtasks WHERE id = ?";
                }
                
                if($stmt_check = mysqli_prepare($conn, $current_db_details_sql)){
                    mysqli_stmt_bind_param($stmt_check, "i", $task_id);
                    mysqli_stmt_execute($stmt_check);
                    $result_check_details = mysqli_stmt_get_result($stmt_check);
                    $current_task_db_details = mysqli_fetch_assoc($result_check_details);
                    mysqli_stmt_close($stmt_check);

                    if ($current_task_db_details && $current_task_db_details['status'] == $db_new_status) {
                        // This is Path B1: Status already the same
                        $GLOBALS['ajax_response_data']['status'] = 'success'; 
                        $GLOBALS['ajax_response_data']['message'] = 'Task status is already ' . ucfirst($db_new_status) . '.';
                        $GLOBALS['ajax_response_data']['new_status_text'] = ucfirst($db_new_status);
                        $GLOBALS['ajax_response_data']['new_status_icon'] = get_status_icon($new_status);

                        if ($db_new_status === 'completed') {
                            $GLOBALS['ajax_response_data']['updated_actual_display'] = (!empty($current_task_db_details['actual_date']) && !empty($current_task_db_details['actual_time'])) ? formatDateTime($current_task_db_details['actual_date'], $current_task_db_details['actual_time']) : 'N/A';
                            if ($current_task_db_details['is_delayed'] == 1 && !empty($current_task_db_details['delay_duration'])) {
                                $GLOBALS['ajax_response_data']['updated_delay_display_html'] = '<span class="text-danger">' . htmlspecialchars($current_task_db_details['delay_duration']) . '</span>';
                            } else {
                                $GLOBALS['ajax_response_data']['updated_delay_display_html'] = !empty($current_task_db_details['delay_duration']) ? htmlspecialchars($current_task_db_details['delay_duration']) : '00:00:00';
                            }
                        } else if ($db_new_status === 'pending') {
                            $GLOBALS['ajax_response_data']['updated_actual_display'] = 'N/A';
                            // For pending tasks, show actual delay from database
                            if ($current_task_db_details['is_delayed'] == 1 && !empty($current_task_db_details['delay_duration'])) {
                                $GLOBALS['ajax_response_data']['updated_delay_display_html'] = '<span class="text-danger">' . htmlspecialchars($current_task_db_details['delay_duration']) . '</span>';
                            } else {
                                $GLOBALS['ajax_response_data']['updated_delay_display_html'] = 'N/A';
                            }
                        } else { // not_done, cant_be_done
                            $GLOBALS['ajax_response_data']['updated_actual_display'] = 'N/A';
                            $GLOBALS['ajax_response_data']['updated_delay_display_html'] = 'N/A';
                        }
                    } else {
                        // Path B2: Error - affected_rows is 0, and (task not found OR task status in DB != new_status)
                        $GLOBALS['ajax_response_data']['message'] = 'Task status not updated. (No rows affected)';
                        if ($current_task_db_details) {
                            // Task was found, but its status in DB is different from the new status we tried to set.
                            // This means the UPDATE statement, despite executing, did not change the status field.
                            $GLOBALS['ajax_response_data']['debug_info'] = "Error: DB status ('" . $current_task_db_details['status'] . "') did not change to intended status ('" . $db_new_status . "') after update attempt. Affected rows: 0.";
                        } else {
                            // Task was not found by ID after the update attempt.
                            $GLOBALS['ajax_response_data']['debug_info'] = "Error: Task with ID '" . $task_id . "' not found during verification after update attempt. Affected rows: 0.";
                        }
                    }
                } else {
                     $GLOBALS['ajax_response_data']['message'] = 'Task status not updated. (No rows affected, verification failed)';
                }
            }
        } else {
            $GLOBALS['ajax_response_data']['message'] = 'DB Error (update execute): ' . mysqli_stmt_error($stmt_update);
        }
        mysqli_stmt_close($stmt_update);
    }
}

send_ajax_json_response($GLOBALS['ajax_response_data']);
?>
