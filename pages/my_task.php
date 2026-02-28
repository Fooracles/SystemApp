<?php
$page_title = "My Tasks";
require_once "../includes/header.php";
require_once "../includes/status_column_helpers.php";
require_once "../includes/sorting_helpers.php";


// Date parsing function is now available globally from includes/functions.php

// Performance optimization: Increase execution time limit for large datasets
set_time_limit(120); // 2 minutes instead of default 30 seconds
ini_set('memory_limit', '256M'); // Increase memory limit if needed

// Helper functions are now available globally from includes/functions.php
// - log_activity() for logging
// - fetchSheetData() for Google Sheets API
// - parseFMSDateTimeString_my_task() for date parsing

// --- START: Task Status Update Logic (Adapted from manage_tasks.php) ---
if (isset($_POST['update_status']) && !empty($_POST['task_id']) && !empty($_POST['status']) && !empty($_POST['task_type'])) {
    $task_id = $_POST['task_id'];
    $new_status = $_POST['status'];
    $task_type = $_POST['task_type'];
    $current_doer_id_for_update = $_SESSION['id']; // Ensure action is for current doer

    $valid_statuses = ['pending', 'completed', 'not_done', 'can_not_be_done']; // Shifted is not for doer

    if (in_array($new_status, $valid_statuses)) {
        if ($task_type === 'delegation') {
            if ($new_status == 'completed') {
                // For delegation, doer can only mark as 'completed'. Other statuses are by manager.
                // Ensure it is their task.
                $sql = "UPDATE tasks SET status = ?, actual_date = CURDATE(), actual_time = CURTIME() WHERE id = ? AND doer_id = ?";
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "sii", $new_status, $task_id, $current_doer_id_for_update);
                    if (mysqli_stmt_execute($stmt)) {
                        // Recalculate delay logic (copied from manage_tasks, ensure calculateCompletionDelay is available)
                        $task_sql_delay = "SELECT planned_date, planned_time FROM tasks WHERE id = ?";
                        if($task_stmt_delay = mysqli_prepare($conn, $task_sql_delay)) {
                            mysqli_stmt_bind_param($task_stmt_delay, "i", $task_id);
                            mysqli_stmt_execute($task_stmt_delay);
                            $task_result_delay = mysqli_stmt_get_result($task_stmt_delay);
                            if($task_row_delay = mysqli_fetch_assoc($task_result_delay)) {
                                $delay = calculateCompletionDelay($task_row_delay['planned_date'], $task_row_delay['planned_time'], date('Y-m-d'), date('H:i:s'));
                                if($delay && $delay !== 'On Time') {
                                    $update_delay_sql = "UPDATE tasks SET is_delayed = 1, delay_duration = ? WHERE id = ?";
                                    if($update_stmt_d = mysqli_prepare($conn, $update_delay_sql)) {
                                        mysqli_stmt_bind_param($update_stmt_d, "si", $delay, $task_id);
                                        mysqli_stmt_execute($update_stmt_d);
                                        mysqli_stmt_close($update_stmt_d);
                                    }
                                } else { 
                                     $update_delay_sql = "UPDATE tasks SET is_delayed = 0, delay_duration = NULL WHERE id = ?";
                                     if($update_stmt_d = mysqli_prepare($conn, $update_delay_sql)) {
                                        mysqli_stmt_bind_param($update_stmt_d, "i", $task_id);
                                        mysqli_stmt_execute($update_stmt_d);
                                        mysqli_stmt_close($update_stmt_d);
                                    }
                                }
                            }
                            mysqli_stmt_close($task_stmt_delay);
                        }
                        $_SESSION['my_task_success_msg'] = "Delegation Task status updated to Completed!";
                    } else {
                        $_SESSION['my_task_error_msg'] = "Error updating delegation task: " . mysqli_stmt_error($stmt);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                     $_SESSION['my_task_error_msg'] = "DB error preparing delegation task update.";
                }
            } elseif ($new_status == 'not_done') { // Doer can mark their task as 'not_done'
                 $sql = "UPDATE tasks SET status = ? WHERE id = ? AND doer_id = ?";
                 if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "sii", $new_status, $task_id, $current_doer_id_for_update);
                    if (mysqli_stmt_execute($stmt)) {
                        $_SESSION['my_task_success_msg'] = "Delegation Task status updated to Not Done!";
                    } else {
                        $_SESSION['my_task_error_msg'] = "Error updating delegation task to Not Done: " . mysqli_stmt_error($stmt);
                    }
                    mysqli_stmt_close($stmt);
                 } else {
                     $_SESSION['my_task_error_msg'] = "DB error preparing delegation task update (not done).";
                 }
            } else {
                 $_SESSION['my_task_error_msg'] = "Invalid status update for your role on Delegation task.";
            }
        } elseif ($task_type === 'checklist') {
            // For checklist, doer can mark 'completed' or 'not_done'
            if ($new_status == 'completed' || $new_status == 'not_done') {
                $current_doer_name_for_update = $_SESSION['username']; // Checklist uses assignee name
                $sql_checklist_update = "UPDATE checklist_subtasks SET status = ? ";
                if ($new_status == 'completed') { // Set actual_date and actual_time if completing
                    $sql_checklist_update .= ", actual_date = CURDATE(), actual_time = CURTIME() ";
                }
                $sql_checklist_update .= " WHERE id = ? AND cs.assignee = ?";
                
                if ($stmt_cl = mysqli_prepare($conn, $sql_checklist_update)) {
                    mysqli_stmt_bind_param($stmt_cl, "sis", $new_status, $task_id, $current_doer_name_for_update);
                    if (mysqli_stmt_execute($stmt_cl)) {
                        if($new_status == 'completed') {
                             // Potentially recalculate delay for checklist if needed, similar to delegation.
                             // For now, just success message.
                        }
                        $_SESSION['my_task_success_msg'] = "Checklist Task status updated successfully!";
                    } else {
                        $_SESSION['my_task_error_msg'] = "Error updating checklist task status: " . mysqli_stmt_error($stmt_cl);
                    }
                    mysqli_stmt_close($stmt_cl);
                } else {
                    $_SESSION['my_task_error_msg'] = "DB error preparing checklist task update: " . mysqli_error($conn);
                }
            } else {
                $_SESSION['my_task_error_msg'] = "Invalid status update for your role on Checklist task.";
            }
        }
    } else {
        $_SESSION['my_task_error_msg'] = "Invalid status selected.";
    }
    header("Location: my_task.php"); // Refresh to show updated list
    exit;
}
// --- END: Task Status Update Logic ---

// Check if the user is logged in
if(!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

// All logged-in users can access this page
// No additional role restrictions needed

// Update all pending tasks delay status
updateAllTasksDelayStatus($conn);

// Check for task delay warnings (5 minutes before delay)
require_once "../includes/notification_triggers.php";
checkTaskDelayWarnings($conn);

// Ensure priority columns exist
require_once "../includes/db_functions.php";
ensureTasksColumns($conn);
ensureChecklistPriorityColumn($conn);
ensureFmsTasksPriorityColumn($conn);

// Get the current user's ID
$current_user_id = $_SESSION["id"];
$current_user_name = $_SESSION["username"];

// Fetch user's assigned tasks
$delegation_tasks = array();
$checklist_tasks_list = array();
$fms_tasks_list = array();

// Get delegation tasks assigned to this user (only pending and shifted)
$sql_delegation = "SELECT 
                    t.id as task_id, 
                    t.description, 
                    t.planned_date, 
                    t.planned_time, 
                    t.actual_date, 
                    t.actual_time, 
                    t.status, 
                    t.delay_duration,
                    t.duration,
                    t.assigned_by,
                    COALESCE(t.priority, 0) as priority,
                    u.username as assigned_by_name,
                    m.name as manager_name,
                    d.name as department_name,
                    t.shifted_count,
                    'delegation' as task_type,
                    t.unique_id
                  FROM tasks t 
                  LEFT JOIN users u ON t.assigned_by = u.id 
                  LEFT JOIN users m ON t.manager_id = m.id
                  LEFT JOIN departments d ON t.department_id = d.id
                  WHERE t.doer_id = ? AND t.status IN ('pending', 'shifted')
                  ORDER BY t.priority DESC, t.planned_date DESC, t.planned_time DESC";

$stmt = mysqli_prepare($conn, $sql_delegation);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $current_user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $delegation_tasks[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Get checklist tasks assigned to this user with frequency-based filtering
$today = date('Y-m-d');
$current_week_start = date('Y-m-d', strtotime('monday this week'));
$current_week_end = date('Y-m-d', strtotime('sunday this week'));

$sql_checklist = "SELECT 
                    cs.id as task_id,
                    cs.task_description as description,
                    cs.task_date as planned_date,
                    cs.duration as duration,
                    cs.actual_date,
                    cs.actual_time,
                    cs.status,
                    cs.delay_duration,
                    cs.frequency,
                    COALESCE(cs.priority, 0) as priority,
                    cs.assignee as assigned_by_name,
                    cs.assigned_by,
                    cs.doer_name,
                    'checklist' as task_type,
                    cs.task_code as unique_id
                  FROM checklist_subtasks cs
                  WHERE (cs.assignee = ? OR cs.doer_name = ?) 
                    AND cs.status IN ('pending', 'shifted')
                    AND (
                        -- Daily frequency: show only on exact planned date (today)
                        (cs.frequency = 'Daily' AND cs.task_date = ?)
                        OR
                        -- Other frequencies: show if planned date is within current week
                        (cs.frequency != 'Daily' AND cs.task_date >= ? AND cs.task_date <= ?)
                    )
                  ORDER BY cs.priority DESC, cs.task_date DESC, cs.duration DESC";

$stmt = mysqli_prepare($conn, $sql_checklist);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "sssss", $current_user_name, $current_user_name, $today, $current_week_start, $current_week_end);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $checklist_tasks_list[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// --- START: Fetching FMS Tasks from Database (Updated for Real-time) ---
log_activity("Fetching FMS tasks from database for real-time updates.");
$fms_tasks_list = array();

// Fetch FMS tasks from database instead of Google Sheets
$fms_tasks_query = "SELECT *, COALESCE(priority, 0) as priority FROM fms_tasks WHERE doer_name = ?";
$fms_tasks_stmt = mysqli_prepare($conn, $fms_tasks_query);

if ($fms_tasks_stmt) {
    mysqli_stmt_bind_param($fms_tasks_stmt, "s", $current_user_name);
    mysqli_stmt_execute($fms_tasks_stmt);
    $fms_tasks_result = mysqli_stmt_get_result($fms_tasks_stmt);

    if ($fms_tasks_result && mysqli_num_rows($fms_tasks_result) > 0) {
        while ($fms_task = mysqli_fetch_assoc($fms_tasks_result)) {
            // Parse the planned and actual datetime strings from database
            $planned_datetime_str = $fms_task['planned'];
            $actual_datetime_str = $fms_task['actual'];
            $fms_status_raw = $fms_task['status'];
            
            $fms_status = !empty($fms_status_raw) ? $fms_status_raw : 'Pending';

            $planned_timestamp = parseFMSDateTimeString_my_task($planned_datetime_str);
            $actual_timestamp = parseFMSDateTimeString_my_task($actual_datetime_str);
            $current_timestamp = time();

            // Skip if Actual time is filled (task is completed)
            if ($actual_timestamp) {
                continue;
            }

            $is_delayed_fms = 0;
            $delay_duration_fms_str = null;

            if ($planned_timestamp && $current_timestamp > $planned_timestamp) {
                $is_delayed_fms = 1;
                $delay_duration_seconds = $current_timestamp - $planned_timestamp;
                $delay_duration_fms_str = formatSecondsToHHMMSS($delay_duration_seconds);
            }
            
            $fms_tasks_list[] = [
                'task_id'         => $fms_task['id'],
                'unique_id'       => $fms_task['unique_key'],
                'description'     => $fms_task['step_name'],
                'planned_date'    => $planned_timestamp ? date('Y-m-d', $planned_timestamp) : null,
                'planned_time'    => $planned_timestamp ? date('H:i:s', $planned_timestamp) : null,
                'actual_date'     => null,
                'actual_time'     => null,
                'status'          => $fms_status,
                'is_delayed'      => $is_delayed_fms,
                'delay_duration'  => $delay_duration_fms_str,
                'duration'        => $fms_task['duration'],
                'doer_name'       => $fms_task['doer_name'],
                'department_name' => $fms_task['sheet_label'],
                'client_name'     => $fms_task['client_name'] ?? 'N/A',
                'priority'        => (int)($fms_task['priority'] ?? 0),
                'task_type'       => 'FMS',
                'task_link_fms'   => $fms_task['task_link'],
                'raw_planned'     => $planned_datetime_str,
                'fms_original_status' => $fms_status_raw,
                'planned_timestamp' => $planned_timestamp,
            ];
        }
        log_activity("Successfully fetched " . count($fms_tasks_list) . " FMS tasks from database for doer: " . $current_user_name);
    } else {
        log_activity("No FMS tasks found in database for doer: " . $current_user_name);
    }
    mysqli_stmt_close($fms_tasks_stmt);
} else {
    log_activity("Error preparing FMS tasks query: " . mysqli_error($conn));
}
// --- END: Fetching FMS Tasks from Database (Updated for Real-time) ---

// Filter FMS tasks to exclude those with status 'completed', 'not_done', or 'can_not_be_done'
$fms_tasks_list = array_filter($fms_tasks_list, function($task) {
    $status = strtolower($task['status']);
    // Also exclude FMS-specific completed statuses
    return !in_array($status, ['completed', 'not_done', 'can_not_be_done', 'done', 'yes', 'no']);
});

// Get session messages
$success_msg = null;
$error_msg = null;
if (isset($_SESSION['my_task_success_msg'])) {
    $success_msg = $_SESSION['my_task_success_msg'];
    unset($_SESSION['my_task_success_msg']);
}
if (isset($_SESSION['my_task_error_msg'])) {
    $error_msg = $_SESSION['my_task_error_msg'];
    unset($_SESSION['my_task_error_msg']);
}

// Sorting parameters - Default: ASC by Planned time
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'planned_date';
$sort_direction = isset($_GET['dir']) ? $_GET['dir'] : 'asc';

// Validate sort parameters
$allowed_sort_columns = ['unique_id', 'description', 'assigned_by', 'planned_date', 'status', 'task_type'];
$sort_column = validateSortColumn($sort_column, $allowed_sort_columns, 'planned_date');
$sort_direction = validateSortDirection($sort_direction);

// Merge all tasks
$all_my_tasks = array_merge($delegation_tasks, $checklist_tasks_list, $fms_tasks_list);

// Sort tasks: Priority tasks first, then by selected column using smart sorting
usort($all_my_tasks, function($a, $b) use ($sort_column, $sort_direction) {
    // First, always sort by priority (priority = 1 comes first)
    $priority_a = (int)($a['priority'] ?? 0);
    $priority_b = (int)($b['priority'] ?? 0);
    if ($priority_a != $priority_b) {
        return $priority_b <=> $priority_a; // Descending: 1 comes before 0
    }
    
    // Then sort by selected column if sorting is applied
    if (!empty($sort_column)) {
        $val_a = $a[$sort_column] ?? null;
        $val_b = $b[$sort_column] ?? null;
        $direction = strtolower($sort_direction) === 'desc' ? -1 : 1;
        
        // Handle status sorting
        if ($sort_column === 'status') {
            $priority_a = getStatusPriority($val_a);
            $priority_b = getStatusPriority($val_b);
            return ($priority_a <=> $priority_b) * $direction;
        }
        
        // Handle task type sorting
        if ($sort_column === 'task_type') {
            $priority_a = getTaskTypePriority($val_a);
            $priority_b = getTaskTypePriority($val_b);
            return ($priority_a <=> $priority_b) * $direction;
        }
        
        // Handle date sorting
        if ($sort_column === 'planned_date') {
            $timestamp_a = parseDateForSorting($val_a, $a['planned_time'] ?? null);
            $timestamp_b = parseDateForSorting($val_b, $b['planned_time'] ?? null);
            return ($timestamp_a <=> $timestamp_b) * $direction;
        }
        
        // Handle unique_id sorting
        if ($sort_column === 'unique_id') {
            preg_match('/\d+/', $val_a ?? '', $matches_a);
            preg_match('/\d+/', $val_b ?? '', $matches_b);
            $num_a = !empty($matches_a) ? (int)$matches_a[0] : 0;
            $num_b = !empty($matches_b) ? (int)$matches_b[0] : 0;
            if ($num_a != $num_b) {
                return ($num_a <=> $num_b) * $direction;
            }
            return strcmp(strtolower($val_a ?? ''), strtolower($val_b ?? '')) * $direction;
        }
        
        // Default: string sorting (case-insensitive)
        $str_a = strtolower($val_a ?? '');
        $str_b = strtolower($val_b ?? '');
        return strcmp($str_a, $str_b) * $direction;
    }
    
    // Default sort when no sorting is applied: by planned_date ASC, then planned_time ASC, then id ASC
    $date_a = parseDateForSorting($a['planned_date'] ?? '', $a['planned_time'] ?? null);
    $date_b = parseDateForSorting($b['planned_date'] ?? '', $b['planned_time'] ?? null);
    if ($date_a != $date_b) {
        return $date_a <=> $date_b; // Ascending by default
    }
    return ($a['task_id'] ?? 0) <=> ($b['task_id'] ?? 0); // Ascending by id
});

// Filter tasks to show only pending and shifted status
$filtered_count = 0;
$all_my_tasks = array_filter($all_my_tasks, function($task) use (&$filtered_count) {
    $status = strtolower($task['status'] ?? '');
    
    // Only show pending and shifted tasks
    if (!in_array($status, ['pending', 'shifted'])) {
        $filtered_count++;
        return false;
    }
    
    // For shifted delegation tasks, filter out those with actual time set
    if ($task['task_type'] === 'delegation' && $task['status'] === 'shifted') {
        if (!empty($task['actual_date']) && !empty($task['actual_time'])) {
            $filtered_count++;
            return false;
        }
    }
    
    return true;
});

// Log how many tasks were filtered out
log_activity("Filtered out $filtered_count shifted tasks with actual time set from my task page");

// --- Update delay status for delegation tasks (this was moved from inside loop for performance) ---
foreach ($all_my_tasks as $key => $task) {
    if ($task['task_type'] === 'delegation' && $task['status'] === 'pending') {
        updateTaskDelayStatus($conn, $task['task_id']); // This function updates DB
        // Reload task to get the possibly updated delay status
        $reloaded_task_sql = "SELECT is_delayed, delay_duration FROM tasks WHERE id = ?";
        if($reloaded_task_stmt = mysqli_prepare($conn, $reloaded_task_sql)) {
            mysqli_stmt_bind_param($reloaded_task_stmt, "i", $task['task_id']);
            mysqli_stmt_execute($reloaded_task_stmt);
            $reloaded_task_result = mysqli_stmt_get_result($reloaded_task_stmt);
            if($reloaded_task_data = mysqli_fetch_assoc($reloaded_task_result)) {
                $all_my_tasks[$key]['is_delayed'] = $reloaded_task_data['is_delayed'];
                $all_my_tasks[$key]['delay_duration'] = $reloaded_task_data['delay_duration'];
            }
            mysqli_stmt_close($reloaded_task_stmt);
        }
    }
}
log_activity("User {$current_user_name} (ID: {$current_user_id}) my task page loaded. Delegation: " . count($delegation_tasks) . ", Checklist: " . count($checklist_tasks_list) . ", FMS: " . count($fms_tasks_list) . ". Total: " . count($all_my_tasks) . ". Filtered out: " . $filtered_count . " completed shifted tasks.");

// Debug: Log final task count for display
log_activity("Final task count for display: " . count($all_my_tasks) . " tasks");
?>

<?php
// --- START: FETCH LAST 7 DAYS HISTORY DATA ---
$history_tasks = [];
$seven_days_ago = date('Y-m-d', strtotime('-7 days'));
$today_date = date('Y-m-d');

// 1. Fetch Delegation History with correct aliases
$sql_delegation_history = "SELECT t.id as task_id, t.unique_id, t.description, t.planned_date, t.planned_time, t.actual_date, t.actual_time, t.status, t.delay_duration, t.duration, t.assigned_by, u.username as assigned_by_name, 'delegation' as task_type FROM tasks t LEFT JOIN users u ON t.assigned_by = u.id WHERE t.doer_id = ? AND t.status = 'completed' AND t.actual_date BETWEEN ? AND ?";
if ($stmt_dh = mysqli_prepare($conn, $sql_delegation_history)) {
    mysqli_stmt_bind_param($stmt_dh, "iss", $current_user_id, $seven_days_ago, $today_date);
    if (mysqli_stmt_execute($stmt_dh)) {
        $result = mysqli_stmt_get_result($stmt_dh);
        while ($row = mysqli_fetch_assoc($result)) {
            $history_tasks[] = $row;
        }
    }
    mysqli_stmt_close($stmt_dh);
}

// 2. Fetch Checklist History with correct aliases
$sql_checklist_history = "SELECT id as task_id, task_code as unique_id, task_description as description, task_date as planned_date, duration, actual_date, actual_time, status, delay_duration, assigned_by, 'checklist' as task_type FROM checklist_subtasks WHERE (assignee = ? OR doer_name = ?) AND status = 'completed' AND actual_date BETWEEN ? AND ?";
if ($stmt_ch = mysqli_prepare($conn, $sql_checklist_history)) {
    mysqli_stmt_bind_param($stmt_ch, "ssss", $current_user_name, $current_user_name, $seven_days_ago, $today_date);
    if (mysqli_stmt_execute($stmt_ch)) {
        $result = mysqli_stmt_get_result($stmt_ch);
        while ($row = mysqli_fetch_assoc($result)) {
            $row['planned_time'] = null; // Checklist tasks don't have a planned time
            $history_tasks[] = $row;
        }
    }
    mysqli_stmt_close($stmt_ch);
}

// 3. Fetch FMS History (and filter/format in PHP)
$sql_fms_history = "SELECT * FROM fms_tasks WHERE doer_name = ?";
if ($stmt_fh = mysqli_prepare($conn, $sql_fms_history)) {
    mysqli_stmt_bind_param($stmt_fh, "s", $current_user_name);
    if (mysqli_stmt_execute($stmt_fh)) {
        $result = mysqli_stmt_get_result($stmt_fh);
        while ($row = mysqli_fetch_assoc($result)) {
            $actual_timestamp = parseFMSDateTimeString_my_task($row['actual']);
            if ($actual_timestamp) {
                $actual_date = date('Y-m-d', $actual_timestamp);
                if ($actual_date >= $seven_days_ago && $actual_date <= $today_date) {
                    $planned_timestamp = parseFMSDateTimeString_my_task($row['planned']);
                    $history_tasks[] = [
                        'task_id' => $row['id'],
                        'unique_id' => $row['unique_key'],
                        'description' => $row['step_name'],
                        'client_name' => $row['client_name'] ?? 'N/A', // Make sure client_name is available
                        'planned_date' => $planned_timestamp ? date('Y-m-d', $planned_timestamp) : null,
                        'planned_time' => $planned_timestamp ? date('H:i:s', $planned_timestamp) : null,
                        'actual_date' => $actual_date,
                        'actual_time' => date('H:i:s', $actual_timestamp),
                        'status' => $row['status'],
                        'delay_duration' => $row['delay_duration'],
                        'duration' => $row['duration'],
                        'task_type' => 'FMS',
                    ];
                }
            }
        }
    }
    mysqli_stmt_close($stmt_fh);
}

// Sort history tasks by actual date, most recent first
usort($history_tasks, function($a, $b) {
    $dateA = strtotime(($a['actual_date'] ?? '1970-01-01') . ' ' . ($a['actual_time'] ?? '00:00:00'));
    $dateB = strtotime(($b['actual_date'] ?? '1970-01-01') . ' ' . ($b['actual_time'] ?? '00:00:00'));
    return $dateB <=> $dateA; // Sort descending
});
// --- END: FETCH LAST 7 DAYS HISTORY DATA ---

?>

<style>
/* Background colors for shifted tasks - Dark Theme */
.bg-warning-light {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.3) 0%, rgba(245, 158, 11, 0.1) 100%) !important;
    color: #fbbf24 !important; /* Bright amber text */
    border-left: 3px solid #f59e0b !important;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.2) !important;
}

.bg-danger-light {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.3) 0%, rgba(239, 68, 68, 0.1) 100%) !important;
    color: #fca5a5 !important; /* Light red text */
    border-left: 3px solid #ef4444 !important;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.2) !important;
}

/* FMS Task Processing State - Dark Theme */
.processing-fms-task {
    background: linear-gradient(135deg, rgba(107, 114, 128, 0.2) 0%, rgba(75, 85, 99, 0.1) 100%) !important;
    opacity: 0.7 !important;
    pointer-events: none !important; /* Makes entire row non-clickable */
    cursor: not-allowed !important;
    border-left: 3px solid #6b7280 !important;
    box-shadow: 0 2px 8px rgba(107, 114, 128, 0.1) !important;
}

/* Ensure table cells maintain their structure */
.processing-fms-task td {
    background: transparent !important;
    opacity: 0.7 !important;
    color: var(--dark-text-muted) !important;
}

.processing-fms-task .fms-mark-done-btn {
    background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%) !important;
    border-color: #6b7280 !important;
    cursor: not-allowed !important;
    opacity: 0.6 !important;
    color: var(--dark-text-muted) !important;
}

/* Delay hover styling - Dark Theme */
.delay-hover {
    cursor: pointer;
    border-bottom: 1px dotted #fca5a5;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 80px;
    display: inline-block;
    position: relative;
    color: #fca5a5 !important;
}

.delay-hover:hover {
    text-decoration: none;
    color: #ef4444 !important;
}

/* Custom tooltip - Dark Theme */
.delay-hover::after {
    content: attr(data-full-delay);
    position: absolute;
    bottom: 125%;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%);
    color: var(--dark-text-primary);
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 13px;
    white-space: nowrap;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    pointer-events: none;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.delay-hover::before {
    content: '';
    position: absolute;
    bottom: 115%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: #1a1a1a;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    pointer-events: none;
}

.delay-hover:hover::after,
.delay-hover:hover::before {
    opacity: 1;
    visibility: visible;
}

/* Description hover styling - Dark Theme */
.description-hover {
    cursor: pointer;
    max-width: 100%;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    position: relative;
    width: 100%;
    color: var(--dark-text-secondary);
    line-height: 1.4;
    word-wrap: break-word;
}

/* Ensure description column has fixed width */
.table {
    table-layout: fixed;
}

/* Column width optimization for my-pending-tasks-table */
#my-pending-tasks-table {
    table-layout: fixed;
    width: 100%;
}

/* Priority column (1st) - minimal width for star icon */
#my-pending-tasks-table th:nth-child(1),
#my-pending-tasks-table td:nth-child(1) {
    width: 3%;
    min-width: 40px;
    max-width: 50px;
    white-space: nowrap;
    overflow: visible !important;
    text-overflow: clip !important;
}
}

/* ID column (2nd) - minimal width, no wrapping */
#my-pending-tasks-table th:nth-child(2),
#my-pending-tasks-table td:nth-child(2) {
    width: 12%;
    min-width: 140px;
    max-width: 160px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Description column (3rd) - flexible, takes remaining space, max 2 lines */
#my-pending-tasks-table th:nth-child(3),
#my-pending-tasks-table td:nth-child(3) {
    width: auto;
    min-width: 300px;
    word-wrap: break-word;
    overflow: hidden;
    line-height: 1.4;
}

/* Assigner column (4th) - minimal width, no wrapping */
#my-pending-tasks-table th:nth-child(4),
#my-pending-tasks-table td:nth-child(4) {
    width: 14%;
    min-width: 100px;
    max-width: 120px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-wrap: wrap;
}

/* Planned column (5th) - reasonable width, no wrapping */
#my-pending-tasks-table th:nth-child(5),
#my-pending-tasks-table td:nth-child(5) {
    width: 13%;
    min-width: 150px;
    max-width: 180px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-wrap: wrap;
}

/* Status column (6th) - minimal width, centered */
#my-pending-tasks-table th:nth-child(6),
#my-pending-tasks-table td:nth-child(6) {
    width: 10%;
    min-width: 70px;
    max-width: 90px;
    text-align: center;
    white-space: nowrap;
}

/* Actions column (7th) - minimal width, centered */
#my-pending-tasks-table th:nth-child(7),
#my-pending-tasks-table td:nth-child(7) {
    width: 10%;
    min-width: 80px;
    max-width: 100px;
    text-align: center;
    white-space: nowrap;
}

/* History table column width optimization - same percentages as main table */
#history-table-container table {
    table-layout: fixed;
    width: 100%;
}

/* ID column (1st) - same as main table */
#history-table-container table th:nth-child(1),
#history-table-container table td:nth-child(1) {
    width: 12%;
    min-width: 140px;
    max-width: 160px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Description column (2nd) - flexible, takes remaining space, max 2 lines */
#history-table-container table th:nth-child(2),
#history-table-container table td:nth-child(2) {
    width: auto;
    min-width: 300px;
    word-wrap: break-word;
    overflow: hidden;
    line-height: 1.4;
}

/* Assigner column (3rd) - same as main table */
#history-table-container table th:nth-child(3),
#history-table-container table td:nth-child(3) {
    width: 10%;
    min-width: 100px;
    max-width: 120px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Planned column (4th) - allow text wrapping */
#history-table-container table th:nth-child(4),
#history-table-container table td:nth-child(4) {
    width: 12%;
    min-width: 150px;
    max-width: 180px;
    word-wrap: break-word;
    overflow-wrap: break-word;
    white-space: normal;
    line-height: 1.4;
}

/* Actual column (5th) - allow text wrapping */
#history-table-container table th:nth-child(5),
#history-table-container table td:nth-child(5) {
    width: 12%;
    min-width: 150px;
    max-width: 180px;
    word-wrap: break-word;
    overflow-wrap: break-word;
    white-space: normal;
    line-height: 1.4;
}

/* Status column (6th) - same as main table */
#history-table-container table th:nth-child(6),
#history-table-container table td:nth-child(6) {
    width: 8%;
    min-width: 70px;
    max-width: 90px;
    text-align: center;
    white-space: nowrap;
}

/* History table description - 2 lines max with ellipsis */
#history-table-container table td:nth-child(2) span {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.4;
    word-wrap: break-word;
    width: 100%;
}

.description-hover:hover {
    text-decoration: none;
    color: var(--dark-text-primary);
}

/* Custom tooltip for description - Dark Theme */
.description-hover::after {
    content: attr(data-full-description);
    position: absolute;
    bottom: 125%;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%);
    color: var(--dark-text-primary);
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 13px;
    white-space: nowrap;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    pointer-events: none;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.description-hover::before {
    content: '';
    position: absolute;
    bottom: 115%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: #1a1a1a;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    pointer-events: none;
}

.description-hover:hover::after,
.description-hover:hover::before {
    opacity: 1;
    visibility: visible;
}

/* Delayed task row styling - Dark Theme */
.delayed-task-row {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%) !important;
    border-left: 3px solid #ef4444 !important;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.1) !important;
}

.delayed-task-row td {
    background: transparent !important;
    color: var(--dark-text-secondary) !important;
}

.delayed-task-row:hover {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(239, 68, 68, 0.08) 100%) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2) !important;
}

/* Priority Star Styling - Golden Yellow */
.my-tasks-page .priority-star {
    color: #fbbf24;
    font-size: 1.1rem;
    transition: all 0.3s ease;
}

.my-tasks-page .priority-star.active {
    color: #fbbf24;
    filter: drop-shadow(0 0 6px rgba(251, 191, 36, 0.8));
    animation: priority-star-pulse 2s ease-in-out infinite;
}

@keyframes priority-star-pulse {
    0%, 100% {
        filter: drop-shadow(0 0 6px rgba(251, 191, 36, 0.8));
    }
    50% {
        filter: drop-shadow(0 0 10px rgba(251, 191, 36, 1));
    }
}

.my-tasks-page .priority-star-cell {
    min-width: 40px;
}

/* Additional Dark Theme Enhancements for My Tasks Table */

.my-tasks-page .table thead th {
    font-size: .83rem; /* Increased from .7rem */
    font-weight: 500;
    text-align: center;
    color: var(--dark-text-primary);
}

.my-tasks-page .table tbody tr {
    background: var(--dark-bg-card);
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    transition: all 0.3s ease;
}

.my-tasks-page .table tbody tr:hover {
    background: var(--dark-bg-glass-hover);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.my-tasks-page .table tbody tr:nth-child(even) {
    background: rgba(255, 255, 255, 0.02);
}

.my-tasks-page .table tbody tr:nth-child(odd) {
    background: var(--dark-bg-card);
}

.my-tasks-page .table td {
    color: var(--dark-text-secondary);
    border-color: rgba(255, 255, 255, 0.05);
    padding: 0.75rem;
    font-size: 0.9rem !important; /* Increased text size */
}

.my-tasks-page .table td strong,
.my-tasks-page .table td .font-weight-bold {
    color: var(--dark-text-primary);
    font-weight: 600;
}

/* Status column styling */
.my-tasks-page .status-column {
    text-align: center;
}

.my-tasks-page .status-icon {
    font-size: 1.2rem;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
}

/* Badge styling for task types */
.my-tasks-page .badge {
    background: var(--gradient-primary);
    color: var(--dark-text-primary);
    font-weight: 500;
    border-radius: var(--radius-sm);
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

/* Button styling improvements */
.my-tasks-page .btn {
    border-radius: var(--radius-sm);
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.my-tasks-page .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
}

.my-tasks-page .btn-primary {
    background: var(--gradient-primary);
    border: none;
}

.my-tasks-page .btn-info {
    background: var(--gradient-secondary);
    border: none;
}

.my-tasks-page .btn-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border: none;
}

/* FMS Mark Done button - match FMS badge color (orange) */
.my-tasks-page .fms-mark-done-btn {
    background: var(--gradient-accent) !important;
    border: none !important;
    color: white !important;
}

.my-tasks-page .fms-mark-done-btn:hover {
    background: linear-gradient(135deg, #ef4444 0%, #f59e0b 100%) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
}

/* Modal CSS removed - using browser confirmation instead */

/* Sort icon styles */
.sort-icon {
    font-size: 0.55em;
    opacity: 0.4;
    margin-left: 4px;
    display: inline-block;
    transition: opacity 0.2s ease;
    vertical-align: middle;
}

.sortable-header:hover .sort-icon,
.sort-icon.active {
    opacity: 1;
}

.sort-icon.active {
    color: #7b07ff; 
    font-weight: bold;
}

.sortable-header {
    cursor: pointer;
    user-select: none;
    transition: opacity 0.2s ease;
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sortable-header:hover {
    opacity: 0.8;
}

/* Prevent wrapping in table headers */
.table thead th {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 0.85rem !important;
}

.table thead th a {
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
    font-size: 0.85rem !important;
}
</style>
<div class="my-tasks-page">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>My Tasks</h2>
                <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION["username"]); ?></strong>!</p>
            </div>
            
            <?php if(!empty($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_msg); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_msg); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
            <?php endif; ?>
            
            <?php 
            // Debug: Log task count for troubleshooting
            error_log("My Task Debug - Total tasks: " . count($all_my_tasks) . ", Delegation: " . count($delegation_tasks) . ", Checklist: " . count($checklist_tasks_list) . ", FMS: " . count($fms_tasks_list));
            ?>
            <?php if(empty($all_my_tasks)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    No tasks assigned to you.
                </div>
            <?php else: ?>
                <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo count($all_my_tasks); ?> Pending Tasks</h5>
                        <div>
                            <button id="toggle-history-btn" class="btn btn-light btn-sm" title="Toggle History">
                                <i class="fas fa-history"></i> Show History
                            </button>
                            <button id="refresh-tasks-btn" class="btn btn-light btn-sm" title="Refresh Tasks">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="loading-spinner" class="text-center py-3" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-2">Refreshing tasks...</p>
                        </div>
                        <div class="table-responsive" id="tasks-table-container" style="overflow-x: hidden; overflow-y: visible; transform: scale(0.97); transform-origin: top left; width: 103.09%;">
                            <table id="my-pending-tasks-table" class="table table-bordered table-striped table-hover table-sm" style="font-size: 100%; width: 100%;">
                                <thead>
                                    <tr>
                                        <th scope="col" style="width: 4%; text-align: center; overflow: visible !important; text-overflow: clip !important;">
                                            <i class="fas fa-star text-warning" title="Priority"></i>
                                        </th>
                                        <th scope="col">
                                            <a href="<?php echo buildSortUrl('unique_id', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                               class="text-dark text-decoration-none sortable-header" data-column="unique_id">
                                                ID <?php echo getSortIcon('unique_id', $sort_column, $sort_direction); ?>
                                            </a>
                                        </th>
                                        <th scope="col">
                                            <a href="<?php echo buildSortUrl('description', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                               class="text-dark text-decoration-none sortable-header" data-column="description">
                                                Description <?php echo getSortIcon('description', $sort_column, $sort_direction); ?>
                                            </a>
                                        </th>
                                        <th scope="col">
                                            <a href="<?php echo buildSortUrl('assigned_by', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                               class="text-dark text-decoration-none sortable-header" data-column="assigned_by">
                                                ASSIGNER <?php echo getSortIcon('assigned_by', $sort_column, $sort_direction); ?>
                                            </a>
                                        </th>
                                        <th scope="col">
                                            <a href="<?php echo buildSortUrl('planned_date', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                               class="text-dark text-decoration-none sortable-header" data-column="planned_date">
                                                Planned <?php echo getSortIcon('planned_date', $sort_column, $sort_direction); ?>
                                            </a>
                                        </th>
                                        <th scope="col">
                                            <a href="<?php echo buildSortUrl('status', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                               class="text-dark text-decoration-none sortable-header" data-column="status">
                                                Status <?php echo getSortIcon('status', $sort_column, $sort_direction); ?>
                                            </a>
                                        </th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                    <tbody>
                                        <?php foreach($all_my_tasks as $task): ?>
                                            <?php 
                                                $cell_class = '';
                                                $row_class = '';
                                                
                                                // Determine cell highlighting class based on shifted_count for delegation tasks
                                                if ($task['task_type'] === 'delegation' && isset($task['shifted_count'])) {
                                                    if ($task['shifted_count'] == 1) {
                                                        $cell_class = 'bg-warning-light';
                                                    } elseif ($task['shifted_count'] >= 2) {
                                                        $cell_class = 'bg-danger-light';
                                                    }
                                                }
                                                
                                                // Simplified logic: check if the Delayed column has a value or if planned time is in the past
                                                $delay_display_html = 'N/A';
                                                $is_delayed = false;
                                                $current_time = time();
                                                
                                                // For shifted delegation tasks, apply different logic based on shifted_count
                                                if ($task['task_type'] === 'delegation' && $task['status'] === 'shifted') {
                                                    // Check shifted_count value
                                                    $shifted_count = isset($task['shifted_count']) ? (int)$task['shifted_count'] : 0;
                                                    
                                                    if ($shifted_count == 1) {
                                                        // Same week shift: Clear delayed time from database and show N/A
                                                        $delay_display_html = 'N/A';
                                                        $is_delayed = false;
                                                        
                                                        // Clear delay from database if it exists
                                                        if (isset($task['task_id']) && !empty($task['task_id'])) {
                                                            $clear_delay_sql = "UPDATE tasks SET is_delayed = 0, delay_duration = NULL WHERE id = ?";
                                                            if ($clear_stmt = mysqli_prepare($conn, $clear_delay_sql)) {
                                                                mysqli_stmt_bind_param($clear_stmt, "i", $task['task_id']);
                                                                mysqli_stmt_execute($clear_stmt);
                                                                mysqli_stmt_close($clear_stmt);
                                                            }
                                                        }
                                                        
                                                    } elseif ($shifted_count >= 2) {
                                                        // Different week shift: Calculate delayed time using normal logic
                                                        if (isset($task['actual_date']) && isset($task['actual_time']) && 
                                                            isset($task['planned_date']) && isset($task['planned_time'])) {
                                                            
                                                            $planned_timestamp = strtotime($task['planned_date'] . ' ' . $task['planned_time']);
                                                            $actual_timestamp = strtotime($task['actual_date'] . ' ' . $task['actual_time']);
                                                            
                                                            if ($planned_timestamp && $actual_timestamp && $actual_timestamp > $planned_timestamp) {
                                                                // Task was completed late - show delay
                                                                if (isset($task['delay_duration']) && !empty($task['delay_duration'])) {
                                                                    // Convert old format "X days Y hours Z minutes" to new format "X D Y h Z m"
                                                                    $delay_text = $task['delay_duration'];
                                                                    if (preg_match('/(\d+)\s+days?\s+(\d+)\s+hours?\s+(\d+)\s+minutes?/', $delay_text, $matches)) {
                                                                        $days = (int)$matches[1];
                                                                        $hours = (int)$matches[2];
                                                                        $minutes = (int)$matches[3];
                                                                        $full_delay = $days . ' D ' . $hours . ' h ' . $minutes . ' m';
                                                                        $truncated_delay = $days . ' D ' . $hours . ' h..';
                                                                    } else {
                                                                        $full_delay = formatDelayForDisplay($task['delay_duration']);
                                                                        $truncated_delay = strlen($full_delay) > 12 ? substr($full_delay, 0, 12) . '..' : $full_delay;
                                                                    }
                                                                    $delay_display_html = '<span class="text-danger delay-hover" data-full-delay="' . htmlspecialchars($full_delay) . '">' . htmlspecialchars($truncated_delay) . '</span>';
                                                                } else {
                                                                    // Calculate delay if not stored
                                                                    $delay_seconds = $actual_timestamp - $planned_timestamp;
                                                                    $delay_duration_str = formatDelayWithDays($delay_seconds);
                                                                    $full_delay = formatDelayForDisplay($delay_duration_str);
                                                                    $truncated_delay = strlen($full_delay) > 12 ? substr($full_delay, 0, 12) . '..' : $full_delay;
                                                                    $delay_display_html = '<span class="text-danger delay-hover" data-full-delay="' . htmlspecialchars($full_delay) . '">' . htmlspecialchars($truncated_delay) . '</span>';
                                                                }
                                                                $is_delayed = true;
                                                            } else {
                                                                // Task was completed on time or early
                                                                $delay_display_html = '<span class="text-success">On Time</span>';
                                                                $is_delayed = false;
                                                            }
                                                        } else {
                                                            // No actual values - show N/A
                                                            $delay_display_html = 'N/A';
                                                            $is_delayed = false;
                                                        }
                                                    } else {
                                                        // Default case for shifted_count = 0 or unknown
                                                        $delay_display_html = 'N/A';
                                                        $is_delayed = false;
                                                    }
                                                }
                                                // DIRECT APPROACH: If the task has a delay value in the Delayed column, mark it as delayed
                                                elseif (!empty($task['delay_duration']) && $task['delay_duration'] != 'N/A' && $task['delay_duration'] != 'On Time') {
                                                    // Convert old format "X days Y hours Z minutes" to new format "X D Y h Z m"
                                                    $delay_text = $task['delay_duration'];
                                                    if (preg_match('/(\d+)\s+days?\s+(\d+)\s+hours?\s+(\d+)\s+minutes?/', $delay_text, $matches)) {
                                                        $days = (int)$matches[1];
                                                        $hours = (int)$matches[2];
                                                        $minutes = (int)$matches[3];
                                                        $full_delay = $days . ' D ' . $hours . ' h ' . $minutes . ' m';
                                                        $truncated_delay = $days . ' D ' . $hours . ' h..';
                                                    } else {
                                                        $full_delay = formatDelayForDisplay($task['delay_duration']);
                                                        $truncated_delay = strlen($full_delay) > 12 ? substr($full_delay, 0, 12) . '..' : $full_delay;
                                                    }
                                                    $delay_display_html = '<span class="text-danger delay-hover" data-full-delay="' . htmlspecialchars($full_delay) . '">' . htmlspecialchars($truncated_delay) . '</span>';
                                                    $is_delayed = true;
                                                } 
                                                // For completed tasks with delay info
                                                elseif (isset($task['status']) && (strtolower($task['status']) === 'completed' || strtolower($task['status']) === 'done')) {
                                                    if (($task['is_delayed'] ?? 0) == 1 && !empty($task['delay_duration'])) {
                                                        // Convert old format "X days Y hours Z minutes" to new format "X D Y h Z m"
                                                        $delay_text = $task['delay_duration'];
                                                        if (preg_match('/(\d+)\s+days?\s+(\d+)\s+hours?\s+(\d+)\s+minutes?/', $delay_text, $matches)) {
                                                            $days = (int)$matches[1];
                                                            $hours = (int)$matches[2];
                                                            $minutes = (int)$matches[3];
                                                            $full_delay = $days . ' D ' . $hours . ' h ' . $minutes . ' m';
                                                            $truncated_delay = $days . ' D ' . $hours . ' h..';
                                                        } else {
                                                            $full_delay = formatDelayForDisplay($task['delay_duration']);
                                                            $truncated_delay = strlen($full_delay) > 12 ? substr($full_delay, 0, 12) . '..' : $full_delay;
                                                        }
                                                        $delay_display_html = '<span class="text-danger delay-hover" data-full-delay="' . htmlspecialchars($full_delay) . '">' . htmlspecialchars($truncated_delay) . '</span>';
                                                        $is_delayed = true;
                                                    } else {
                                                        $delay_display_html = '<span class="text-success">On Time</span>';
                                                    }
                                                }
                                                // For pending tasks, check if planned time has passed
                                                else {
                                                    $planned_ts = null;
                                                    
                                                    // Get planned timestamp based on task type
                                                    if ($task['task_type'] === 'delegation') {
                                                        if (!empty($task['planned_date']) && !empty($task['planned_time'])) {
                                                            $planned_ts = strtotime($task['planned_date'] . ' ' . $task['planned_time']);
                                                        }
                                                    } elseif ($task['task_type'] === 'checklist') {
                                                        if (!empty($task['planned_date'])) {
                                                            $planned_ts = strtotime($task['planned_date'] . ' 23:59:59');
                                                        }
                                                    } elseif ($task['task_type'] === 'FMS') {
                                                        $planned_ts = parseFMSDateTimeString_doer($task['raw_planned'] ?? '');
                                                    }
                                                    
                                                    if ($planned_ts && $current_time > $planned_ts) {
                                                        // Calculate current delay
                                                        $delay_secs = $current_time - $planned_ts;
                                                        $delay_duration_str = formatSecondsToHHMMSS($delay_secs);
                                                        $full_delay = formatDelayForDisplay($delay_duration_str);
                                                        $truncated_delay = strlen($full_delay) > 12 ? substr($full_delay, 0, 12) . '..' : $full_delay;
                                                        $delay_display_html = '<span class="text-danger delay-hover" data-full-delay="' . htmlspecialchars($full_delay) . '">' . htmlspecialchars($truncated_delay) . '</span>';
                                                        $is_delayed = true;
                                                    }
                                                }
                                                
                                                // ADDITIONAL CHECK: If the Delayed column in the table shows a value, always mark as delayed
                                                if (strpos($delay_display_html, 'text-danger') !== false) {
                                                    $is_delayed = true;
                                                }
                                                
                                                // Add delayed-task-row class if task is delayed
                                                if ($is_delayed) {
                                                    $row_class .= ($row_class ? ' ' : '') . 'delayed-task-row';
                                                }
                                            ?>
                                            <tr id="task-row-<?php echo htmlspecialchars($task['task_type'] . '-' . $task['task_id']); ?>"
                                            class="<?php echo $row_class; ?>"
                                            data-task-type="<?php echo strtolower($task['task_type']); ?>">

<td class="text-center priority-star-cell" style="vertical-align: middle;">
    <?php 
    $is_priority = (int)($task['priority'] ?? 0) === 1;
    if ($is_priority): ?>
        <i class="fas fa-star priority-star active" title="Priority Task"></i>
    <?php endif; ?>
</td>
<td>
        <div style="display: flex; flex-direction: column; align-items: flex-start; gap: 0.25rem;">
            <small class="badge badge-info badge-sm" style="margin-bottom: 0.125rem;"><?php echo ucfirst($task['task_type']); ?></small>
            <small class="text-muted" style="font-size: 0.75rem; font-weight: 500;"><?php echo htmlspecialchars($task['unique_id'] ?? ''); ?></small>
        </div>
</td>
                                                <td>
                                                    <?php 
                                                    $description = $task['description'] ?? 'N/A';
                                                    $full_description = htmlspecialchars($description);
                                                    ?>
                                                    <span class="description-hover" data-full-description="<?php echo htmlspecialchars($full_description); ?>">
                                                        <?php echo nl2br(htmlspecialchars($description)); ?>
                                                    </span>
                                                </td>
                                                <td> <!--Assigner-->
                                                    <?php 
                                                    if ($task['task_type'] === 'delegation') {
                                                        // For delegation tasks, use assigned_by_name if available, otherwise fallback
                                                        $assigned_by_name = $task['assigned_by_name'] ?? $task['assigned_by'] ?? 'N/A';
                                                        echo htmlspecialchars($assigned_by_name);
                                                    } elseif ($task['task_type'] === 'FMS') {
                                                        // For FMS tasks, show client_name
                                                        echo htmlspecialchars($task['client_name'] ?? 'N/A');
                                                    } elseif ($task['task_type'] === 'checklist') {
                                                        // For checklist tasks, show assigned_by
                                                        echo htmlspecialchars($task['assigned_by'] ?? 'N/A');
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="<?php echo $cell_class; ?>"> <!-- Planned -->
                                                    <?php 
                                                    if ($task['task_type'] === 'delegation') {
                                                        // For delegation tasks, format date and time properly
                                                        if (!empty($task['planned_date']) && !empty($task['planned_time'])) {
                                                            $planned_timestamp = strtotime($task['planned_date'] . ' ' . $task['planned_time']);
                                                            if ($planned_timestamp) {
                                                                echo htmlspecialchars(date("d M Y", $planned_timestamp) . " " . date("h:i A", $planned_timestamp));
                                                            } else {
                                                                echo 'N/A';
                                                            }
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                    } elseif ($task['task_type'] === 'FMS') {
                                                        // FMS uses raw_planned for original string, delegation uses formatted.
                                                        // For FMS, planned_date and planned_time are already parsed from raw_planned.
                                                        echo formatDateTime($task['planned_date'] ?? null, $task['planned_time'] ?? null, $task['raw_planned'] ?? null, $task['task_type'] ?? 'delegation'); 
                                                    } elseif ($task['task_type'] === 'checklist') { 
                                                        // Checklist: planned_date is cs.task_date, planned_time is fixed '23:00:00' for display/delay calc, but show only date.
                                                        echo isset($task['planned_date']) ? htmlspecialchars(date("d M Y", strtotime($task['planned_date']))) : 'N/A';
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <?php 
                                                // Custom FMS status logic: ✅ for tasks with actual data, ⏳ for pending tasks without actual data
                                                if ($task['task_type'] === 'FMS') {
                                                    $has_actual_data = !empty($task['actual_date']) || !empty($task['actual_time']);
                                                    $status_icon = $has_actual_data ? '✅' : '⏳';
                                                    $status_text = $has_actual_data ? 'Completed' : 'Pending';
                                                    ?>
                                                    <td class="status-column" data-status="<?php echo htmlspecialchars($task['status'] ?? 'pending'); ?>">
                                                        <span class="status-icon" title="<?php echo htmlspecialchars($status_text); ?>"><?php echo $status_icon; ?></span>
                                                    </td>
                                                    <?php
                                                } else {
                                                    echo get_status_column_cell($task['status'] ?? 'pending');
                                                }
                                                ?>
                                                <td> <!-- Actions -->
                                                    <?php if ($task['task_type'] === 'FMS'): ?>
                                                        <?php if (!empty($task['task_link_fms'])): ?>
                                                            <a href="<?php echo htmlspecialchars($task['task_link_fms']); ?>" 
                                                               target="_blank" 
                                                               class="btn btn-sm btn-success fms-mark-done-btn"
                                                               data-task-id="<?php echo htmlspecialchars($task['unique_id']); ?>">Done</a>
                                                        <?php else: ?>
                                                            <span>N/A (No Link)</span>
                                                        <?php endif; ?>
                                                    <?php else: // Delegation or Checklist ?>
                                                        <button class="btn <?php echo $task['task_type'] === 'checklist' ? 'btn-info' : 'btn-primary'; ?> btn-sm mark-completed-btn" 
                                                                data-task-id="<?php echo $task['task_id']; ?>" 
                                                                data-task-type="<?php echo $task['task_type']; ?>"
                                                                data-task-unique-id="<?php echo htmlspecialchars($task['unique_id'] ?? 'N/A'); ?>"
                                                                title="Task ID: <?php echo $task['task_id']; ?>, Type: <?php echo $task['task_type']; ?>">
                                                            Done
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card mb-4" id="history-table-container" style="display: none;">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">Last 7 Days History</h5>
            </div>
            <div class="card-body">
                <?php if(empty($history_tasks)): ?>
                    <div class="alert alert-info">No completed tasks found in the last 7 days.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover table-sm">
                            <thead>
                                <tr>
                                    <th scope="col">
                                        <a href="<?php echo buildSortUrl('unique_id', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                           class="text-dark text-decoration-none sortable-header" data-column="unique_id">
                                            ID <?php echo getSortIcon('unique_id', $sort_column, $sort_direction); ?>
                                        </a>
                                    </th>
                                    <th scope="col">
                                        <a href="<?php echo buildSortUrl('description', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                           class="text-dark text-decoration-none sortable-header" data-column="description">
                                            Description <?php echo getSortIcon('description', $sort_column, $sort_direction); ?>
                                        </a>
                                    </th>
                                    <th scope="col">
                                        <a href="<?php echo buildSortUrl('assigned_by', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                           class="text-dark text-decoration-none sortable-header" data-column="assigned_by">
                                            ASSIGNER <?php echo getSortIcon('assigned_by', $sort_column, $sort_direction); ?>
                                        </a>
                                    </th>
                                    <th scope="col">
                                        <a href="<?php echo buildSortUrl('planned_date', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                           class="text-dark text-decoration-none sortable-header" data-column="planned_date">
                                            Planned <?php echo getSortIcon('planned_date', $sort_column, $sort_direction); ?>
                                        </a>
                                    </th>
                                    <th scope="col">
                                        <a href="<?php echo buildSortUrl('actual_date', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                           class="text-dark text-decoration-none sortable-header" data-column="actual_date">
                                            Actual <?php echo getSortIcon('actual_date', $sort_column, $sort_direction); ?>
                                        </a>
                                    </th> 
                                    <th scope="col">
                                        <a href="<?php echo buildSortUrl('status', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                           class="text-dark text-decoration-none sortable-header" data-column="status">
                                            Status <?php echo getSortIcon('status', $sort_column, $sort_direction); ?>
                                        </a>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($history_tasks as $task): ?>
                                    <tr data-task-type="<?php echo strtolower($task['task_type']); ?>">
                                        <td>
                                            <div style="display: flex; flex-direction: column; align-items: flex-start; gap: 0.25rem;">
                                                <small class="badge badge-info badge-sm" style="margin-bottom: 0.125rem;"><?php echo ucfirst($task['task_type']); ?></small>
                                                <small class="text-muted" style="font-size: 0.75rem; font-weight: 500;"><?php echo htmlspecialchars($task['unique_id'] ?? ''); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span title="<?php echo htmlspecialchars($task['description'] ?? 'N/A'); ?>">
                                                <?php echo nl2br(htmlspecialchars($task['description'] ?? 'N/A')); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if (strtolower($task['task_type']) === 'delegation') {
                                                // For delegation tasks, use assigned_by_name if available, otherwise fallback
                                                $assigned_by_name = $task['assigned_by_name'] ?? $task['assigned_by'] ?? 'N/A';
                                                echo htmlspecialchars($assigned_by_name);
                                            } elseif (strtolower($task['task_type']) === 'fms') {
                                                // For FMS tasks, show client_name
                                                echo htmlspecialchars($task['client_name'] ?? 'N/A');
                                            } elseif (strtolower($task['task_type']) === 'checklist') {
                                                // For checklist tasks, show assigned_by
                                                echo htmlspecialchars($task['assigned_by'] ?? 'N/A');
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            if (strtolower($task['task_type']) === 'checklist') {
                                                echo isset($task['planned_date']) ? htmlspecialchars(date("d M Y", strtotime($task['planned_date']))) : 'N/A';
                                            } else {
                                                echo formatDateTime($task['planned_date'] ?? null, $task['planned_time'] ?? null);
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo formatDateTime($task['actual_date'] ?? null, $task['actual_time'] ?? null); ?></td>
                                        <?php 
                                        if (strtolower($task['task_type']) === 'fms') {
                                            ?>
                                            <td class="status-column"><span class="status-icon" title="Completed">✅</span></td>
                                            <?php
                                        } else {
                                            echo get_status_column_cell($task['status'] ?? 'completed');
                                        }
                                        ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

<!-- Modal removed - using browser confirmation instead -->

<!-- Toast container -->
<div aria-live="polite" aria-atomic="true" style="position: fixed; top: 20px; right: 20px; z-index: 1050;">
    <div id="toast-container" style="position: absolute; top: 0; right: 0;">
        <!-- Toasts will be appended here -->
    </div>
</div>

<script>
// Define showToast function globally
function showToast(message, type = 'success') {
    // ... function content ...
}

// Optimized my_task.php functionality
if (!window.myTaskInitialized) {
    window.myTaskInitialized = true;
    
    $(document).ready(function() {
        // --- AUTO-SCROLL CODE ---
        $('#toggle-history-btn').on('click', function() {
            var historyTable = $('#history-table-container');
            var mainTasksHeader = $('.card-header.bg-primary');
            var icon = $(this).find('i');
            var textNode = this.childNodes[2];
            var isOpening = historyTable.is(':hidden');

            if (isOpening) {
                icon.removeClass('fa-history').addClass('fa-times');
                textNode.nodeValue = ' Hide History';
            } else {
                icon.removeClass('fa-times').addClass('fa-history');
                textNode.nodeValue = ' Show History';
            }
            
            historyTable.slideToggle(400, function() {
                if (isOpening) {
                    $('html, body').animate({
                        scrollTop: historyTable.offset().top - 70
                    }, 500);
                } else {
                    $('html, body').animate({
                        scrollTop: mainTasksHeader.offset().top - 20
                    }, 500);
                }
            });
        });
        // --- END OF AUTO-SCROLL CODE ---
    });
}




// Define showToast function globally
function showToast(message, type = 'success') {
    var toastId = 'toast-' + new Date().getTime();
    var bgColor = type === 'success' ? 'bg-success' : 'bg-danger';
    if (type === 'warning') bgColor = 'bg-warning';
    if (type === 'info') bgColor = 'bg-info';
    
    var toastHtml = 
        '<div id="' + toastId + '" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-delay="5000">' +
            '<div class="toast-header ' + bgColor + ' text-white">' +
                '<strong class="mr-auto">Notification</strong>' +
                '<button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">' +
                    '<span aria-hidden="true">&times;</span>' +
                '</button>' +
            '</div>' +
            '<div class="toast-body">' +
                message +
            '</div>' +
        '</div>';
    $('#toast-container').append(toastHtml);
    $('#' + toastId).toast('show');
    $('#' + toastId).on('hidden.bs.toast', function () {
        $(this).remove();
    });
}

// Main functionality (optimized to prevent duplicates)
if (!window.myTaskMainInitialized) {
    window.myTaskMainInitialized = true;
    
    $(document).ready(function() {
        // Tooltips are already initialized in script.js
    
    var currentTaskId, currentTaskType, currentTaskRowId, currentTaskUniqueId;
    var pollingIntervalMs = 15000; // 15 seconds
var latestCreatedAt = $('#tasks-table-container').data('latest-created-at') || '';
    var sseEnabled = !!window.EventSource && !!latestCreatedAt;    var sseEnabled = !!window.EventSource && !!latestCreatedAt;
    
    // Restore processing states for FMS tasks on page load
    restoreFmsProcessingStates();
    
    // Clear any expired greyed states on page load
    clearExpiredFmsProcessingStates();
    
    // Handle mark completed button clicks (Delegation and Checklist)
    $(document).on('click', '.mark-completed-btn', function(e) {
        e.preventDefault(); 
        e.stopPropagation();
        
        var button = $(this);
        currentTaskId = button.data('task-id');
        currentTaskType = button.data('task-type');
        currentTaskUniqueId = button.data('task-unique-id');
        currentTaskRowId = '#task-row-' + String(currentTaskType).toLowerCase() + '-' + currentTaskId;
        
        // Validate required data
        if (!currentTaskId || !currentTaskType) {
            showToast('Error: Missing task information. Please refresh the page and try again.', 'danger');
            return;
        }

        // Use browser confirmation dialog
        var confirmed = confirm('Are you sure you want to mark task "' + currentTaskUniqueId + '" as completed?');
        
        if (confirmed) {
            // User confirmed, proceed with task completion
            completeTask();
        }
        // If user cancels, do nothing
    });

    // Function to complete the task
    function completeTask() {
        if (!currentTaskId || !currentTaskType) {
            showToast('Error: Task details not found for completion.', 'danger');
            return;
        }

        var ajaxUrl = '';
        if (currentTaskType === 'delegation') {
            ajaxUrl = 'action_update_task_status.php'; 
        } else if (currentTaskType === 'checklist') {
            ajaxUrl = 'action_update_checklist_status.php';
        } else {
            showToast('Invalid task type for this action.', 'danger');
            return;
        }

        // Show loading indicator
        var $button = $('button[data-task-id="' + currentTaskId + '"][data-task-type="' + currentTaskType + '"]');
        var originalButtonText = $button.html();
        $button.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...');
        $button.prop('disabled', true);

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                task_id: parseInt(currentTaskId, 10),
                status: 'completed'
            },
            dataType: 'json',
            timeout: 30000,
            success: function(response) {
                if (response.status === 'success') {
                    showToast(response.message || 'Task marked as completed!', 'success');
                    
                    // Update status icon in real-time instead of reloading
                    var $row = $(currentTaskRowId);
                    if ($row.length) {
                        var $statusCell = $row.find('td.status-column');
                        if ($statusCell.length) {
                            if (response.new_status_icon) {
                                // Use the status icon HTML returned from the server
                                $statusCell.html(response.new_status_icon);
                            } else {
                                // Fallback: generate status icon using JavaScript function
                                var statusIcon = getStatusIcon('completed');
                                $statusCell.html(statusIcon);
                            }
                        }
                        
                        // Note: my_task.php table doesn't have an Actual column, only Planned
                        // The Actual date/time is shown in the Planned column when task is completed
                        // No need to update Actual column as it doesn't exist in this table
                    }
                    
                    // Remove the task row from the table
                    var $row = $(currentTaskRowId);
                    if ($row.length) {
                        $row.fadeOut(500, function() {
                            $(this).remove();
                            
                            // Update task count in the main table's header only
                            var $taskCount = $('.card-header.bg-primary h5'); // This selector is now specific to the blue header
                            if ($taskCount.length) {
                                var currentText = $taskCount.text();
                                var match = currentText.match(/(\d+)/); // Find the first number
                                if (match) {
                                    var currentCount = parseInt(match[1]) || 0;
                                    var newCount = currentCount > 0 ? currentCount - 1 : 0;
                                    
                                    if (newCount > 0) {
                                        $taskCount.text(newCount + (newCount === 1 ? ' Pending Task' : ' Pending Tasks'));
                                    } else {
                                        $taskCount.text('No Pending Tasks');
                                    }
                                }
                            }
                            
                            // Check if no tasks left and show message
                            var remainingTasks = $('#my-pending-tasks-table tbody tr').length;
                            if (remainingTasks === 0) {
                                $('#my-pending-tasks-table tbody').html(
                                    '<tr><td colspan="7" class="text-center text-muted py-4">' +
                                    '<i class="fas fa-check-circle fa-2x mb-2 text-success"></i><br>' +
                                    'All tasks completed! Great job!' +
                                    '</td></tr>'
                                );
                            }
                        });
                    }
                } else {
                    // Reset button
                    $button.html(originalButtonText);
                    $button.prop('disabled', false);
                    showToast(response.message || 'Could not update task status.', 'danger');
                }
            },
            error: function(xhr, status, error) {
                // Reset button
                $button.html(originalButtonText);
                $button.prop('disabled', false);
                
                var errorMsg = 'An error occurred while updating the task.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                
                showToast('Error: ' + errorMsg, 'danger');
            },
            complete: function() {
                // Clear stored values
                currentTaskId = null;
                currentTaskType = null;
                currentTaskRowId = null;
                currentTaskUniqueId = null;
            }
        });
    }
    });
}

// Function to restore FMS processing states from localStorage
function restoreFmsProcessingStates() {
    try {
        var processingTasks = JSON.parse(localStorage.getItem('fmsProcessingTasks') || '[]');
        var currentTime = Date.now();
        var validTasks = [];
        
        processingTasks.forEach(function(taskData) {
            var taskId, clickTime;
            
            if (typeof taskData === 'string') {
                taskId = taskData;
                clickTime = currentTime - 120000; // Default to 2 minutes ago for old format
            } else if (taskData && taskData.taskId && taskData.clickTime) {
                taskId = taskData.taskId;
                clickTime = taskData.clickTime;
            } else {
                return; // Skip invalid entries
            }
            
            // Check if task is still within 2-minute greyed period
            var timeElapsed = currentTime - clickTime;
            var twoMinutesMs = 2 * 60 * 1000; // 2 minutes in milliseconds
            
            if (timeElapsed < twoMinutesMs) {
                var $btn = $('.fms-mark-done-btn[data-task-id="' + taskId + '"]');
                if ($btn.length > 0) {
                    var $row = $btn.closest('tr');
                    $row.addClass('processing-fms-task');
                    $btn.prop('disabled', true);
                    $btn.attr('data-processing', 'true');
                }
                
                // Set up a timeout to clear this specific task after its remaining time
                var remainingTime = twoMinutesMs - timeElapsed;
                if (remainingTime > 0) {
                    setTimeout(function() {
                        clearFmsProcessingState(taskId);
                    }, remainingTime);
                }
                
                validTasks.push({
                    taskId: taskId,
                    clickTime: clickTime
                });
            }
        });
        
        // Update localStorage with only valid tasks
        localStorage.setItem('fmsProcessingTasks', JSON.stringify(validTasks));
        
    } catch (err) {
        console.error('Failed to restore FMS processing states:', err);
    }
}

// Function to clear expired FMS processing states on page load
function clearExpiredFmsProcessingStates() {
    try {
        var processingTasks = JSON.parse(localStorage.getItem('fmsProcessingTasks') || '[]');
        var currentTime = Date.now();
        var validTasks = [];
        var twoMinutesMs = 2 * 60 * 1000; // 2 minutes in milliseconds
        
        processingTasks.forEach(function(taskData) {
            var taskId, clickTime;
            
            if (typeof taskData === 'string') {
                taskId = taskData;
                clickTime = currentTime - 120000;
            } else if (taskData && taskData.taskId && taskData.clickTime) {
                taskId = taskData.taskId;
                clickTime = taskData.clickTime;
            } else {
                return;
            }
            
            var timeElapsed = currentTime - clickTime;
            
            if (timeElapsed < twoMinutesMs) {
                validTasks.push({
                    taskId: taskId,
                    clickTime: clickTime
                });
                
                // Restore the visual state for this task
                var $btn = $('.fms-mark-done-btn[data-task-id="' + taskId + '"]');
                if ($btn.length > 0) {
                    var $row = $btn.closest('tr');
                    $row.addClass('processing-fms-task');
                    $btn.prop('disabled', true);
                    $btn.attr('data-processing', 'true');
                    
                    // Set up a timeout to clear this specific task after its remaining time
                    var remainingTime = twoMinutesMs - timeElapsed;
                    if (remainingTime > 0) {
                        setTimeout(function() {
                            clearFmsProcessingState(taskId);
                        }, remainingTime);
                    }
                }
            } else {
                // Task has expired, remove it from processing state
                var $btn = $('.fms-mark-done-btn[data-task-id="' + taskId + '"]');
                if ($btn.length > 0) {
                    var $row = $btn.closest('tr');
                    $row.removeClass('processing-fms-task');
                    $btn.prop('disabled', false);
                    $btn.removeAttr('data-processing');
                }
            }
        });
        
        // Update localStorage with only valid tasks
        localStorage.setItem('fmsProcessingTasks', JSON.stringify(validTasks));
        
    } catch (err) {
        console.error('Failed to clear expired FMS processing states:', err);
    }
}

// Function to clear processing state for a specific FMS task
function clearFmsProcessingState(taskId) {
    try {
        var processingTasks = JSON.parse(localStorage.getItem('fmsProcessingTasks') || '[]');
        var updatedTasks = processingTasks.filter(function(taskData) {
            var taskIdToCheck = (typeof taskData === 'string') ? taskData : taskData.taskId;
            return taskIdToCheck !== taskId;
        });
        localStorage.setItem('fmsProcessingTasks', JSON.stringify(updatedTasks));
        
        var $btn = $('.fms-mark-done-btn[data-task-id="' + taskId + '"]');
        if ($btn.length > 0) {
            var $row = $btn.closest('tr');
            $row.removeClass('processing-fms-task');
            $btn.prop('disabled', false);
            $btn.removeAttr('data-processing');
        }
    } catch (err) {
        console.error('Failed to clear FMS processing state:', err);
    }
}

// Handle FMS Mark Done button clicks
$(document).on('click', '.fms-mark-done-btn', function(e) {
    var $btn = $(this);
    var taskId = $btn.data('task-id');
    var $row = $btn.closest('tr');
    
    // Check if this task is already being processed
    if ($row.hasClass('processing-fms-task')) {
        e.preventDefault();
        e.stopPropagation();
        return false;
    }
    
    // Immediately apply processing state
    $row.addClass('processing-fms-task');
    $btn.prop('disabled', true);
    $btn.attr('data-processing', 'true');
    
    // Store processing state in localStorage to persist across page refreshes
    try {
        var processingTasks = JSON.parse(localStorage.getItem('fmsProcessingTasks') || '[]');
        var currentTime = Date.now();
        
        // Check if task is already in processing state
        var existingTask = processingTasks.find(function(taskData) {
            var taskIdToCheck = (typeof taskData === 'string') ? taskData : taskData.taskId;
            return taskIdToCheck === taskId;
        });
        
        if (!existingTask) {
            processingTasks.push({
                taskId: taskId,
                clickTime: currentTime
            });
            localStorage.setItem('fmsProcessingTasks', JSON.stringify(processingTasks));
        }
    } catch (err) {
        console.error('Failed to store processing state:', err);
    }
    
    // Create a persistent schedule across reloads for sync
    try {
        var schedule = {
            taskId: String(taskId),
            startAt: Date.now(),
            attemptsMs: [30000, 60000, 120000],
            currentIndex: 0
        };
        localStorage.setItem('fmsSyncSchedule', JSON.stringify(schedule));
        console.log('Initialized FMS sync schedule:', schedule);
        scheduleNextFmsAttempt();
    } catch (err) {
        console.error('Failed to initialize FMS sync schedule:', err);
    }
    
    // Show notification
    showToast('FMS task processing started. Row will be greyed out during processing.', 'info');
    
    // Set up auto-clear after 2 minutes
    setTimeout(function() {
        clearFmsProcessingState(taskId);
        showToast('FMS task processing period ended. Row is now available again.', 'info');
    }, 2 * 60 * 1000); // 2 minutes
    
    // Allow the link to open in a new tab after applying processing state
    // The link will open in new tab while the row remains greyed out
});

// Tooltip hover functionality
$('.delay-hover').each(function() {
    var fullDelay = $(this).attr('data-full-delay');
    if (fullDelay) {
        var fullFormatDelay = convertDelayToFullFormat(fullDelay);
        $(this).attr('title', fullFormatDelay);
    }
});

$('.description-hover').each(function() {
    var fullDescription = $(this).attr('data-full-description');
    if (fullDescription) {
        var tooltipDescription = convertDescriptionForTooltip(fullDescription);
        $(this).attr('title', tooltipDescription);
    }
});

// Convert abbreviated delay format to full format
function convertDelayToFullFormat(delay) {
    if (!delay || delay === 'N/A' || delay === 'On Time') {
        return delay;
    }
    return delay
        .replace(/(\d+)\s*D\b/g, '$1 days')
        .replace(/(\d+)\s*h\b/g, '$1 hours')
        .replace(/(\d+)\s*m\b/g, '$1 minutes');
}

// Convert description format for tooltip
function convertDescriptionForTooltip(description) {
    if (!description || description === 'N/A') {
        return description;
    }
    return description.replace(/\n/g, '<br>');
}

// Refresh button functionality
$('#refresh-tasks-btn').click(function() {
    // Add spinning animation to the refresh icon
    $(this).find('i').addClass('fa-spin');
    $(this).prop('disabled', true);
    
    // Show the loading spinner
    $('#loading-spinner').fadeIn();
    $('.table-responsive').fadeOut();
    
    // Reload the page after a short delay to show the animation
    setTimeout(function() {
        window.location.reload();
    }, 1000);
});

// Simple polling to detect newly assigned delegation tasks and refresh
function pollNewDelegationTasks() {
    try {
        $.ajax({
            url: '../ajax/get_new_delegation_tasks.php',
            method: 'GET',
            dataType: 'json',
            data: {
                since: latestCreatedAt
            },
            timeout: 10000,
            success: function(res) {
                if (res && res.status === 'success' && Array.isArray(res.tasks)) {
                    if (res.tasks.length > 0) {
                        var newest = res.tasks.reduce(function(max, t){
                            return (!max || (t.created_at > max)) ? t.created_at : max;
                        }, latestCreatedAt);
                        latestCreatedAt = newest || latestCreatedAt;
                        showToast('New task(s) assigned to you. Refreshing...', 'info');
                        setTimeout(function(){ window.location.reload(); }, 800);
                    }
                }
            }
        });
    } catch (e) {}
}

// Try SSE first; fallback to polling if not available
if (sseEnabled) {
    try {
        var sseUrl = '../ajax/delegation_tasks_sse.php?since=' + encodeURIComponent(latestCreatedAt);
        var es = new EventSource(sseUrl);

        es.addEventListener('new_tasks', function(ev) {
            try {
                var payload = JSON.parse(ev.data || '{}');
                if (payload && payload.count > 0) {
                    showToast('New task(s) assigned to you. Refreshing...', 'info');
                    setTimeout(function(){ window.location.reload(); }, 500);
                }
            } catch (e) {
                // ignore
            }
        });

        es.addEventListener('ping', function(_) { /* keep-alive */ });
        es.addEventListener('error', function(_) { /* fallback below will handle */ });
    } catch (e) {
        // If SSE fails, fallback to polling
        setInterval(pollNewDelegationTasks, pollingIntervalMs);
    }
} else if (latestCreatedAt) {
    setInterval(pollNewDelegationTasks, pollingIntervalMs);
}

// Auto-dismiss alerts from PHP session messages
window.setTimeout(function() {
    $(".alert-dismissible").not('.toast').fadeTo(1500, 0).slideUp(500, function(){
        $(this).remove(); 
    });
}, 7000);


function scheduleNextFmsAttempt() {
    try {
        var raw = localStorage.getItem('fmsSyncSchedule');
        if (!raw) { return; }
        var schedule = JSON.parse(raw);
        if (!schedule || !schedule.taskId || !Array.isArray(schedule.attemptsMs)) { return; }
        if (schedule.currentIndex >= schedule.attemptsMs.length) { 
            localStorage.removeItem('fmsSyncSchedule');
            return; 
        }
        var now = Date.now();
        var targetTime = schedule.startAt + schedule.attemptsMs[schedule.currentIndex];
        var delay = Math.max(0, targetTime - now);
        var attemptNum = schedule.currentIndex + 1;
        console.log('Scheduling FMS attempt', attemptNum, 'in', delay, 'ms for task', schedule.taskId);
        setTimeout(function() {
            // Invoke AJAX for this attempt
            refreshFMSData(schedule.taskId, attemptNum);
        }, delay);
    } catch (err) {
        console.error('Error scheduling next FMS attempt:', err);
    }
}

// Function to refresh FMS data
function refreshFMSData(taskId, attemptNumber) {
    console.log("Refreshing FMS data for task ID:", taskId, "Attempt:", attemptNumber);
    
    // Show a notification that refresh is in progress
    //showToast('Starting FMS data refresh #' + attemptNumber + '...', 'info');
    
    // Make sure taskId is clean (no HTML entities or special characters)
    taskId = taskId.toString().trim();
    
    // Call the PHP function via AJAX
    $.ajax({
        url: '../ajax/ajax_fetch_sheet_data.php', 
        method: 'POST',
        data: {
            action: 'fetch_sheet_data',
            task_id: taskId,
            attempt: attemptNumber
        },
        dataType: 'json',
        success: function(response) {
            console.log("FMS refresh response:", response);
            console.log("Raw tasks payload:", response.tasks);

            // Display debug logs if available
            if (response.debug_enabled && response.logs && response.logs.length > 0) {
                console.group('AJAX Sheet Data Fetch - ' + (response.console_time || new Date().toISOString()));
                console.log('Response status:', response.status);
                console.log('Message:', response.message);
                console.log('Timestamp:', response.timestamp || '');
                
                console.groupCollapsed('Detailed Logs (' + response.logs.length + ' entries)');
                response.logs.forEach(function(log) {
                    console.log('[' + log.timestamp + ']', log.message);
                });
                console.groupEnd();
                console.groupEnd();
            }
            
            if (response.status === 'success') {
                // Advance schedule to ensure next attempt will run post-reload
                try {
                    var raw = localStorage.getItem('fmsSyncSchedule');
                    if (raw) {
                        var schedule = JSON.parse(raw);
                        if (schedule && typeof schedule.currentIndex === 'number') {
                            schedule.currentIndex += 1;
                            localStorage.setItem('fmsSyncSchedule', JSON.stringify(schedule));
                            console.log('Advanced FMS sync schedule to index', schedule.currentIndex);
                        }
                    }
                } catch (e) { console.warn('Could not advance FMS schedule:', e); }

                // Always reload the page to reflect latest DB state
                console.log('FMS sync successful; reloading page to reflect updates.');
                window.location.reload();
            } else {
                // For warnings, use warning toast
                if (response.status === 'warning') {
                    showToast('FMS data refresh #' + attemptNumber + ' warning: ' + response.message, 'warning');
                } else {
                    // For errors, show more details
                    var errorMsg = response.message || 'Unknown error';
                    showToast('FMS data refresh #' + attemptNumber + ' error: ' + errorMsg, 'danger');
                    console.error('FMS refresh error:', response);
                }
            }
        },
        error: function(xhr, status, error) {
            console.error("AJAX error:", status, error);
            
            // Try to parse the response if it's JSON
            var errorMsg = 'Connection error. Please try again later.';
            
            try {
                if (xhr.responseText) {
                    // First try to parse as JSON
                    var jsonResponse = JSON.parse(xhr.responseText);
                    if (jsonResponse && jsonResponse.message) {
                        errorMsg = jsonResponse.message;
                    }
                    
                    // Display debug logs if available in the error response
                    if (jsonResponse && jsonResponse.debug_enabled && jsonResponse.logs && jsonResponse.logs.length > 0) {
                        console.group('AJAX Sheet Data Fetch Error - ' + (jsonResponse.console_time || new Date().toISOString()));
                        console.log('Response status:', jsonResponse.status);
                        console.log('Error message:', jsonResponse.message);
                        
                        console.groupCollapsed('Detailed Error Logs (' + jsonResponse.logs.length + ' entries)');
                        jsonResponse.logs.forEach(function(log) {
                            console.log('[' + log.timestamp + ']', log.message);
                        });
                        console.groupEnd();
                        console.groupEnd();
                    }
                }
            } catch (e) {
                console.error("Error parsing JSON response:", e);
                console.error("Raw response:", xhr.responseText);
                // If it's not valid JSON, check for common error patterns
                if (xhr.responseText && xhr.responseText.includes("SyntaxError")) {
                    errorMsg = "Server returned invalid data format. Please contact support.";
                } else if (xhr.status === 404) {
                    errorMsg = "API endpoint not found. Please check the URL.";
                } else if (xhr.status === 500) {
                    errorMsg = "Server error. Please contact support.";
                }
            }
            
            showToast('FMS data refresh #' + attemptNumber + ' failed: ' + errorMsg, 'danger');
        },
        // Add timeout to prevent long-hanging requests
        timeout: 30000 // 30 seconds
    });
}

// Resume FMS sync schedule after reload if present
try {
    var rawSchedule = localStorage.getItem('fmsSyncSchedule');
    if (rawSchedule) {
        var schedule = JSON.parse(rawSchedule);
        if (schedule && typeof schedule.currentIndex === 'number') {
            console.log('Resuming FMS sync schedule after reload:', schedule);
            scheduleNextFmsAttempt();
        }
    }
} catch (err) { console.warn('Could not resume FMS schedule:', err); }

// Add debug info to console
console.log('My task page JavaScript initialized successfully');
console.log('Current user session info:', {
    userId: <?php echo isset($_SESSION['id']) ? $_SESSION['id'] : 'null'; ?>,
    username: <?php echo isset($_SESSION['username']) ? '"' . $_SESSION['username'] . '"' : 'null'; ?>,
    userType: <?php echo isset($_SESSION['user_type']) ? '"' . $_SESSION['user_type'] . '"' : 'null'; ?>
});
</script>

<?php require_once "../includes/footer.php"; ?>
