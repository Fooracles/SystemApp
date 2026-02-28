<?php
// Start output buffering to catch any stray output or PHP errors
ob_start();

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/ajax_errors.log');

// Initialize a global response variable for the shutdown function to access
$GLOBALS['ajax_response_data'] = ['status' => 'error', 'message' => 'An unexpected server error occurred.'];

// Function to send JSON response and exit cleanly
function send_ajax_json_response($data) {
    error_log("AJAX Response: " . json_encode($data));
    
    if (ob_get_level() > 0 && ob_get_length() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode($data);
    exit;
}

// Shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        error_log("PHP Fatal Error in AJAX: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        $GLOBALS['ajax_response_data']['status'] = 'error';
        $GLOBALS['ajax_response_data']['message'] = 'Critical server error. Please contact support or check server logs.';
        send_ajax_json_response($GLOBALS['ajax_response_data']);
    }
});

// Main script logic
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_functions.php';

// Ensure priority columns exist
if (isset($conn)) {
    ensureTasksColumns($conn);
    ensureChecklistPriorityColumn($conn);
    ensureFmsTasksPriorityColumn($conn);
}

if (!isset($_SESSION['id'])) {
    error_log("User not authenticated in action_toggle_priority.php");
    $GLOBALS['ajax_response_data']['message'] = 'User not authenticated.';
    send_ajax_json_response($GLOBALS['ajax_response_data']);
}

// Only Admin and Manager can set priority
if (!isAdmin() && !isManager()) {
    error_log("User not authorized for priority toggle - User Type: " . ($_SESSION['user_type'] ?? 'N/A'));
    $GLOBALS['ajax_response_data']['message'] = 'Only Administrators and Managers can set task priority.';
    send_ajax_json_response($GLOBALS['ajax_response_data']);
}

if (!isset($conn)) {
    error_log("Database connection failed in action_toggle_priority.php");
    $GLOBALS['ajax_response_data']['message'] = 'Database connection failed.';
    send_ajax_json_response($GLOBALS['ajax_response_data']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id']) && isset($_POST['task_type'])) {
    $task_id = (int)$_POST['task_id'];
    $task_type = $_POST['task_type'];
    
    error_log("Processing priority toggle - Task ID: $task_id, Task Type: $task_type");
    
    $success = false;
    $new_priority = 0;
    $message = '';
    
    if ($task_type === 'delegation') {
        // Get current priority - read actual value (not COALESCE) to detect NULL
        $check_sql = "SELECT id, priority FROM tasks WHERE id = ?";
        if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
            mysqli_stmt_bind_param($check_stmt, "i", $task_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            if ($check_row = mysqli_fetch_assoc($check_result)) {
                // Handle NULL explicitly - treat NULL as 0 for comparison
                $priority_raw = $check_row['priority'];
                $current_priority = ($priority_raw === null) ? 0 : (int)$priority_raw;
                $new_priority = ($current_priority === 1) ? 0 : 1;
                
                // Log before update for debugging
                error_log("Delegation Priority Toggle - Task ID: $task_id, Current Priority (raw): " . ($priority_raw === null ? 'NULL' : $priority_raw) . ", Normalized: $current_priority, Attempting to set: $new_priority");
                
                // Update priority - handle NULL explicitly by not using WHERE condition on priority
                $update_sql = "UPDATE tasks SET priority = ? WHERE id = ?";
                if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                    mysqli_stmt_bind_param($update_stmt, "ii", $new_priority, $task_id);
                    if (mysqli_stmt_execute($update_stmt)) {
                        // Check affected rows for debugging
                        $affected_rows_stmt = mysqli_stmt_affected_rows($update_stmt);
                        $affected_rows_conn = mysqli_affected_rows($conn);
                        error_log("Delegation Priority Toggle - Task ID: $task_id, Affected Rows (stmt): $affected_rows_stmt, Affected Rows (conn): $affected_rows_conn");
                        
                        // Always verify by reading back the actual value
                        $verify_sql = "SELECT priority FROM tasks WHERE id = ?";
                        if ($verify_stmt = mysqli_prepare($conn, $verify_sql)) {
                            mysqli_stmt_bind_param($verify_stmt, "i", $task_id);
                            mysqli_stmt_execute($verify_stmt);
                            $verify_result = mysqli_stmt_get_result($verify_stmt);
                            if ($verify_row = mysqli_fetch_assoc($verify_result)) {
                                $verify_priority_raw = $verify_row['priority'];
                                $verify_priority = ($verify_priority_raw === null) ? 0 : (int)$verify_priority_raw;
                                error_log("Delegation Priority Toggle - Verification - Task ID: $task_id exists, Actual Priority (raw): " . ($verify_priority_raw === null ? 'NULL' : $verify_priority_raw) . ", Normalized: $verify_priority, Expected: $new_priority");
                                
                                // If the actual priority matches what we're trying to set, it's a success
                                if ($verify_priority === $new_priority) {
                                    $success = true;
                                    $message = $new_priority ? 'Task marked as priority!' : 'Priority removed from task.';
                                    error_log("Delegation Priority Toggle Success - Task ID: $task_id, New Priority: $new_priority (verified)");
                                } else {
                                    // If affected rows > 0 but verification doesn't match, something went wrong
                                    // If affected rows = 0, try direct query as fallback
                                    if ($affected_rows_stmt == 0 && $affected_rows_conn == 0) {
                                        error_log("Delegation Priority Toggle - No rows affected, trying direct UPDATE as fallback");
                                        $direct_update = "UPDATE tasks SET priority = $new_priority WHERE id = $task_id";
                                        if (mysqli_query($conn, $direct_update)) {
                                            $direct_affected = mysqli_affected_rows($conn);
                                            error_log("Delegation Priority Toggle - Direct UPDATE affected: $direct_affected rows");
                                            
                                            // Re-verify after direct update
                                            $reverify_result = mysqli_query($conn, "SELECT priority FROM tasks WHERE id = $task_id");
                                            if ($reverify_row = mysqli_fetch_assoc($reverify_result)) {
                                                $reverify_priority_raw = $reverify_row['priority'];
                                                $reverify_priority = ($reverify_priority_raw === null) ? 0 : (int)$reverify_priority_raw;
                                                if ($reverify_priority === $new_priority) {
                                                    $success = true;
                                                    $message = $new_priority ? 'Task marked as priority!' : 'Priority removed from task.';
                                                    error_log("Delegation Priority Toggle Success (via direct UPDATE) - Task ID: $task_id, New Priority: $new_priority");
                                                } else {
                                                    $success = false;
                                                    $message = 'Task not found or priority was already in the requested state.';
                                                    error_log("Delegation Priority Toggle - Direct UPDATE also failed - Task ID: $task_id, Reverify: $reverify_priority, Expected: $new_priority");
                                                }
                                            }
                                        }
                                    } else {
                                        $success = false;
                                        $message = 'Task not found or priority was already in the requested state.';
                                        error_log("Delegation Priority Toggle - Update failed - Task ID: $task_id, Actual: $verify_priority, Expected: $new_priority");
                                    }
                                }
                            } else {
                                $success = false;
                                $message = 'Task not found.';
                                error_log("Delegation Priority Toggle - Verification - Task ID: $task_id no longer exists!");
                            }
                            mysqli_stmt_close($verify_stmt);
                        } else {
                            $success = false;
                            $message = 'Error preparing verification statement: ' . mysqli_error($conn);
                            error_log("Delegation Priority Toggle - Verify Prepare Error: " . mysqli_error($conn));
                        }
                    } else {
                        $success = false;
                        $message = 'Error updating priority: ' . mysqli_stmt_error($update_stmt);
                        error_log("Delegation Priority Toggle Error - Task ID: $task_id, Error: " . mysqli_stmt_error($update_stmt));
                    }
                    mysqli_stmt_close($update_stmt);
                } else {
                    $success = false;
                    $message = 'Error preparing update statement: ' . mysqli_error($conn);
                    error_log("Delegation Priority Toggle - Prepare Error: " . mysqli_error($conn));
                }
            } else {
                $success = false;
                $message = 'Task not found.';
                error_log("Delegation Priority Toggle - Task not found - Task ID: $task_id");
            }
            mysqli_stmt_close($check_stmt);
        } else {
            $success = false;
            $message = 'Error preparing check statement: ' . mysqli_error($conn);
            error_log("Delegation Priority Toggle - Check Prepare Error: " . mysqli_error($conn));
        }
    } elseif ($task_type === 'checklist') {
        // Get current priority - read actual value to detect NULL
        $check_sql = "SELECT priority FROM checklist_subtasks WHERE id = ?";
        if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
            mysqli_stmt_bind_param($check_stmt, "i", $task_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            if ($check_row = mysqli_fetch_assoc($check_result)) {
                $priority_raw = $check_row['priority'];
                $current_priority = ($priority_raw === null) ? 0 : (int)$priority_raw;
                $new_priority = ($current_priority === 1) ? 0 : 1;
                
                error_log("Checklist Priority Toggle - Task ID: $task_id, Current Priority (raw): " . ($priority_raw === null ? 'NULL' : $priority_raw) . ", Normalized: $current_priority, Attempting to set: $new_priority");
                
                // Update priority
                $update_sql = "UPDATE checklist_subtasks SET priority = ? WHERE id = ?";
                if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                    mysqli_stmt_bind_param($update_stmt, "ii", $new_priority, $task_id);
                    if (mysqli_stmt_execute($update_stmt)) {
                        $affected_rows_stmt = mysqli_stmt_affected_rows($update_stmt);
                        $affected_rows_conn = mysqli_affected_rows($conn);
                        error_log("Checklist Priority Toggle - Task ID: $task_id, Affected Rows (stmt): $affected_rows_stmt, Affected Rows (conn): $affected_rows_conn");
                        
                        // Verify by reading back
                        $verify_sql = "SELECT priority FROM checklist_subtasks WHERE id = ?";
                        if ($verify_stmt = mysqli_prepare($conn, $verify_sql)) {
                            mysqli_stmt_bind_param($verify_stmt, "i", $task_id);
                            mysqli_stmt_execute($verify_stmt);
                            $verify_result = mysqli_stmt_get_result($verify_stmt);
                            if ($verify_row = mysqli_fetch_assoc($verify_result)) {
                                $verify_priority_raw = $verify_row['priority'];
                                $verify_priority = ($verify_priority_raw === null) ? 0 : (int)$verify_priority_raw;
                                
                                if ($verify_priority === $new_priority) {
                                    $success = true;
                                    $message = $new_priority ? 'Task marked as priority!' : 'Priority removed from task.';
                                    error_log("Checklist Priority Toggle Success - Task ID: $task_id, New Priority: $new_priority (verified)");
                                } else {
                                    if ($affected_rows_stmt == 0 && $affected_rows_conn == 0) {
                                        $direct_update = "UPDATE checklist_subtasks SET priority = $new_priority WHERE id = $task_id";
                                        if (mysqli_query($conn, $direct_update)) {
                                            $direct_affected = mysqli_affected_rows($conn);
                                            $reverify_result = mysqli_query($conn, "SELECT priority FROM checklist_subtasks WHERE id = $task_id");
                                            if ($reverify_row = mysqli_fetch_assoc($reverify_result)) {
                                                $reverify_priority_raw = $reverify_row['priority'];
                                                $reverify_priority = ($reverify_priority_raw === null) ? 0 : (int)$reverify_priority_raw;
                                                if ($reverify_priority === $new_priority) {
                                                    $success = true;
                                                    $message = $new_priority ? 'Task marked as priority!' : 'Priority removed from task.';
                                                    error_log("Checklist Priority Toggle Success (via direct UPDATE) - Task ID: $task_id");
                                                }
                                            }
                                        }
                                    }
                                    if (!$success) {
                                        $success = false;
                                        $message = 'Task not found or priority was already in the requested state.';
                                        error_log("Checklist Priority Toggle - Update failed - Task ID: $task_id, Actual: $verify_priority, Expected: $new_priority");
                                    }
                                }
                            }
                            mysqli_stmt_close($verify_stmt);
                        }
                    } else {
                        $success = false;
                        $message = 'Error updating priority: ' . mysqli_stmt_error($update_stmt);
                        error_log("Checklist Priority Toggle Error - Task ID: $task_id, Error: " . mysqli_stmt_error($update_stmt));
                    }
                    mysqli_stmt_close($update_stmt);
                } else {
                    $success = false;
                    $message = 'Error preparing update statement: ' . mysqli_error($conn);
                }
            } else {
                $success = false;
                $message = 'Task not found.';
            }
            mysqli_stmt_close($check_stmt);
        } else {
            $success = false;
            $message = 'Error preparing check statement: ' . mysqli_error($conn);
        }
    } elseif ($task_type === 'fms') {
        // Get current priority - read actual value to detect NULL
        $check_sql = "SELECT priority FROM fms_tasks WHERE id = ?";
        if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
            mysqli_stmt_bind_param($check_stmt, "i", $task_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            if ($check_row = mysqli_fetch_assoc($check_result)) {
                $priority_raw = $check_row['priority'];
                $current_priority = ($priority_raw === null) ? 0 : (int)$priority_raw;
                $new_priority = ($current_priority === 1) ? 0 : 1;
                
                error_log("FMS Priority Toggle - Task ID: $task_id, Current Priority (raw): " . ($priority_raw === null ? 'NULL' : $priority_raw) . ", Normalized: $current_priority, Attempting to set: $new_priority");
                
                // Update priority
                $update_sql = "UPDATE fms_tasks SET priority = ? WHERE id = ?";
                if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                    mysqli_stmt_bind_param($update_stmt, "ii", $new_priority, $task_id);
                    if (mysqli_stmt_execute($update_stmt)) {
                        $affected_rows_stmt = mysqli_stmt_affected_rows($update_stmt);
                        $affected_rows_conn = mysqli_affected_rows($conn);
                        error_log("FMS Priority Toggle - Task ID: $task_id, Affected Rows (stmt): $affected_rows_stmt, Affected Rows (conn): $affected_rows_conn");
                        
                        // Verify by reading back
                        $verify_sql = "SELECT priority FROM fms_tasks WHERE id = ?";
                        if ($verify_stmt = mysqli_prepare($conn, $verify_sql)) {
                            mysqli_stmt_bind_param($verify_stmt, "i", $task_id);
                            mysqli_stmt_execute($verify_stmt);
                            $verify_result = mysqli_stmt_get_result($verify_stmt);
                            if ($verify_row = mysqli_fetch_assoc($verify_result)) {
                                $verify_priority_raw = $verify_row['priority'];
                                $verify_priority = ($verify_priority_raw === null) ? 0 : (int)$verify_priority_raw;
                                
                                if ($verify_priority === $new_priority) {
                                    $success = true;
                                    $message = $new_priority ? 'Task marked as priority!' : 'Priority removed from task.';
                                    error_log("FMS Priority Toggle Success - Task ID: $task_id, New Priority: $new_priority (verified)");
                                } else {
                                    if ($affected_rows_stmt == 0 && $affected_rows_conn == 0) {
                                        $direct_update = "UPDATE fms_tasks SET priority = $new_priority WHERE id = $task_id";
                                        if (mysqli_query($conn, $direct_update)) {
                                            $direct_affected = mysqli_affected_rows($conn);
                                            $reverify_result = mysqli_query($conn, "SELECT priority FROM fms_tasks WHERE id = $task_id");
                                            if ($reverify_row = mysqli_fetch_assoc($reverify_result)) {
                                                $reverify_priority_raw = $reverify_row['priority'];
                                                $reverify_priority = ($reverify_priority_raw === null) ? 0 : (int)$reverify_priority_raw;
                                                if ($reverify_priority === $new_priority) {
                                                    $success = true;
                                                    $message = $new_priority ? 'Task marked as priority!' : 'Priority removed from task.';
                                                    error_log("FMS Priority Toggle Success (via direct UPDATE) - Task ID: $task_id");
                                                }
                                            }
                                        }
                                    }
                                    if (!$success) {
                                        $success = false;
                                        $message = 'Task not found or priority was already in the requested state.';
                                        error_log("FMS Priority Toggle - Update failed - Task ID: $task_id, Actual: $verify_priority, Expected: $new_priority");
                                    }
                                }
                            }
                            mysqli_stmt_close($verify_stmt);
                        }
                    } else {
                        $success = false;
                        $message = 'Error updating priority: ' . mysqli_stmt_error($update_stmt);
                        error_log("FMS Priority Toggle Error - Task ID: $task_id, Error: " . mysqli_stmt_error($update_stmt));
                    }
                    mysqli_stmt_close($update_stmt);
                } else {
                    $success = false;
                    $message = 'Error preparing update statement: ' . mysqli_error($conn);
                }
            } else {
                $success = false;
                $message = 'Task not found.';
            }
            mysqli_stmt_close($check_stmt);
        } else {
            $success = false;
            $message = 'Error preparing check statement: ' . mysqli_error($conn);
        }
    } else {
        $success = false;
        $message = 'Invalid task type.';
    }
    
    $GLOBALS['ajax_response_data'] = [
        'status' => $success ? 'success' : 'error',
        'message' => $message,
        'priority' => $new_priority
    ];
    
    send_ajax_json_response($GLOBALS['ajax_response_data']);
} else {
    $GLOBALS['ajax_response_data']['message'] = 'Invalid request data.';
    send_ajax_json_response($GLOBALS['ajax_response_data']);
}
