<?php
$page_title = "Manager Dashboard";
require_once "../includes/header.php";
require_once "../includes/status_column_helpers.php";
require_once "../includes/dashboard_components.php";

// Performance optimization: Increase execution time limit for large datasets
set_time_limit(120); // 2 minutes instead of default 30 seconds
ini_set('memory_limit', '256M'); // Increase memory limit if needed

// Helper function for FMS date formatting in this page
function formatFMSDateTime($planned, $actual = null) {
    if (!empty($actual)) {
        return date("d M Y h:i A", strtotime($actual));
    } elseif (!empty($planned)) {
        return date("d M Y h:i A", strtotime($planned));
    }
    return "N/A";
}

// Use global log_activity function from includes/functions.php

// Date parsing function is now available globally from includes/functions.php

// Update all pending tasks delay status
updateAllTasksDelayStatus($conn);

// Check if the user is logged in and is a manager
if(!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

if(!isManager() && !isAdmin()) {
    header("location: doer_dashboard.php");
    exit;
}

// Update task status if requested
if(isset($_POST['update_status']) && !empty($_POST['task_id']) && !empty($_POST['status']) && !empty($_POST['task_type'])) {
    $task_id = $_POST['task_id'];
    $new_status = $_POST['status'];
    $task_type = $_POST['task_type'];
    
    $valid_statuses = ['pending', 'completed', 'shifted', 'not done', 'can not be done'];

    if(in_array($new_status, $valid_statuses)) {
        if ($task_type === 'delegation') {
        // If completing the task, set actual date and time
        if($new_status == 'completed') {
            $sql = "UPDATE tasks SET status = ?, actual_date = CURDATE(), actual_time = CURTIME() WHERE id = ?";
        } else {
            $sql = "UPDATE tasks SET status = ? WHERE id = ?";
        }
        
        if($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "si", $new_status, $task_id);
            
            if(mysqli_stmt_execute($stmt)) {
                // Check if task was completed late and update delay info
                if($new_status == 'completed') {
                    $task_sql = "SELECT planned_date, planned_time FROM tasks WHERE id = ?";
                    if($task_stmt = mysqli_prepare($conn, $task_sql)) {
                        mysqli_stmt_bind_param($task_stmt, "i", $task_id);
                        mysqli_stmt_execute($task_stmt);
                        $task_result = mysqli_stmt_get_result($task_stmt);
                        
                        if($task_row = mysqli_fetch_assoc($task_result)) {
                            $planned_date = $task_row['planned_date'];
                            $planned_time = $task_row['planned_time'];
                            
                            $delay = calculateCompletionDelay($planned_date, $planned_time, date('Y-m-d'), date('H:i:s'));
                            if($delay) {
                                $update_delay_sql = "UPDATE tasks SET is_delayed = 1, delay_duration = ? WHERE id = ?";
                                if($update_stmt = mysqli_prepare($conn, $update_delay_sql)) {
                                    mysqli_stmt_bind_param($update_stmt, "si", $delay, $task_id);
                                    mysqli_stmt_execute($update_stmt);
                                    mysqli_stmt_close($update_stmt);
                                }
                            }
                        }
                        mysqli_stmt_close($task_stmt);
                    }
                }
                    $success_msg = "Delegation Task status updated successfully!";
                } else {
                    $error_msg = "Something went wrong updating delegation task. Please try again later.";
                }
                mysqli_stmt_close($stmt);
            }
        } elseif ($task_type === 'checklist') {
            $sql_checklist_update = "UPDATE checklist_subtasks SET status = ? WHERE id = ?";
            if ($stmt_checklist_update = mysqli_prepare($conn, $sql_checklist_update)) {
                mysqli_stmt_bind_param($stmt_checklist_update, "si", $new_status, $task_id);
                if (mysqli_stmt_execute($stmt_checklist_update)) {
                    $success_msg = "Checklist Task status updated successfully!";
                } else {
                    error_log("[DB Error] " . mysqli_stmt_error($stmt)); $error_msg = "A database error occurred. Please try again.";
                }
                mysqli_stmt_close($stmt_checklist_update);
            } else {
                error_log("[DB Error] " . mysqli_error($conn)); $error_msg = "A database error occurred. Please try again.";
            }
        }
    } else {
        $error_msg = "Invalid status selected.";
    }
}

// -- Scorecard Counts Initialisation --
$total_tasks_in_view = 0;
$completed_tasks_count_total = 0;
$delayed_tasks_count_total = 0;
$completion_rate = "N/A"; // Default value for completion rate

// --- START: Get All Tasks for Static Metrics ---
// Get all Delegation Tasks
$all_delegation_tasks = array();
$sql_delegation_all = "SELECT id, status, is_delayed, planned_date, planned_time, actual_date, actual_time FROM tasks"; 
$result_delegation_all = mysqli_query($conn, $sql_delegation_all);
if($result_delegation_all) {
    while($row = mysqli_fetch_assoc($result_delegation_all)) {
        $all_delegation_tasks[] = $row;
    }
}

// Get all Checklist Tasks
$all_checklist_tasks = array();
$sql_checklist_all = "SELECT id, task_date as planned_date, COALESCE(status, 'pending') as status, actual_date, actual_time FROM checklist_subtasks";
$result_checklist_all = mysqli_query($conn, $sql_checklist_all);
if ($result_checklist_all) {
    while ($row = mysqli_fetch_assoc($result_checklist_all)) {
        // Add planned_time field for consistent structure (same as Admin Dashboard)
        $row['planned_time'] = '23:00:00'; // Default time for checklist tasks
        $all_checklist_tasks[] = $row;
    }
}

// Get all FMS Tasks - IMPORTANT: No LIMIT here to match Manage Tasks page
$all_fms_tasks = array();

// Add these functions to match the Manage Tasks page
function fetchCsvData_dashboard($url) {
    // Check if cURL is available
    if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For development only
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($error) {
        return ['error' => "cURL Error: $error"];
    }
    
    if ($http_code != 200) {
        return ['error' => "HTTP Error: $http_code"];
    }
    
    return ['data' => $response];
    }
    
    // Fallback to file_get_contents if allow_url_fopen is enabled
    if (ini_get('allow_url_fopen')) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return ['error' => "file_get_contents failed to fetch URL"];
        }
        
        return ['data' => $response];
    }
    
    // Final fallback - return error message
    return ['error' => "Neither cURL nor file_get_contents are available. Please enable PHP cURL extension or allow_url_fopen in php.ini"];
}

// parseFMSDateTimeString_my_task function is now replaced by parseFMSDateTimeString_my_task in includes/functions.php

// Fetch FMS tasks from database instead of Google Sheets (Performance fix)
$fms_tasks_query = "SELECT * FROM fms_tasks";
$fms_tasks_result = mysqli_query($conn, $fms_tasks_query);

if ($fms_tasks_result && mysqli_num_rows($fms_tasks_result) > 0) {
    while ($fms_task = mysqli_fetch_assoc($fms_tasks_result)) {
        // Parse the planned and actual datetime strings using same logic as Manage Tasks
        $planned_datetime_str = $fms_task['planned'];
        $actual_datetime_str = $fms_task['actual'];
        $fms_status_raw = $fms_task['status'];
        
        $fms_status = !empty($fms_status_raw) ? $fms_status_raw : 'Pending'; // Default to Pending if blank

        // Use the same parsing function as Manage Tasks
        $planned_timestamp = parseFMSDateTimeString_my_task($planned_datetime_str);
        $actual_timestamp = parseFMSDateTimeString_my_task($actual_datetime_str);
        
        // Prepare date and time strings for consistent structure
        $planned_date_val = $planned_timestamp ? date('Y-m-d', $planned_timestamp) : null;
        $planned_time_val = $planned_timestamp ? date('H:i:s', $planned_timestamp) : null;
        $actual_date_val = $actual_timestamp ? date('Y-m-d', $actual_timestamp) : null;
        $actual_time_val = $actual_timestamp ? date('H:i:s', $actual_timestamp) : null;
        
                $all_fms_tasks[] = [
            'id' => $fms_task['unique_key'] ?? $fms_task['id'], // Handle both column names
            'planned_date' => $planned_date_val,
            'planned_time' => $planned_time_val,
            'actual_date' => $actual_date_val,
            'actual_time' => $actual_time_val,
            'status' => $fms_status,
            'doer_name' => $fms_task['doer_name'] ?? null,
            'sheet_id' => $fms_task['sheet_id'] ?? null,
            'sheet_label' => $fms_task['sheet_label'] ?? null
        ];
    }
    log_activity("Successfully fetched " . count($all_fms_tasks) . " FMS tasks from database");
} else {
    log_activity("No FMS tasks found in database or error occurred");
}

// Calculate total tasks count (static)
$total_tasks_in_view = count($all_delegation_tasks) + count($all_checklist_tasks) + count($all_fms_tasks);

// --- START: Calculate Completed Tasks Count (static) ---
// Count completed delegation tasks
foreach ($all_delegation_tasks as $task) {
    if (strtolower($task['status']) === 'completed') {
        $completed_tasks_count_total++;
    }
}

// Count completed checklist tasks
foreach ($all_checklist_tasks as $task) {
    if (strtolower($task['status']) === 'completed' || strtolower($task['status']) === 'done') {
        $completed_tasks_count_total++;
    }
}

// Count completed FMS tasks - Exactly match Manage Tasks page logic
foreach ($all_fms_tasks as $task) {
    $completed_statuses_fms = ['completed', 'done']; // Same as Manage Tasks
    $is_completed_fms = false;
    foreach($completed_statuses_fms as $comp_stat){
        if(isset($task['status']) && strtolower($task['status']) === $comp_stat){
            $is_completed_fms = true;
            break;
        }
    }
    if ($is_completed_fms) {
        $completed_tasks_count_total++;
    }
}
// --- END: Calculate Completed Tasks Count ---

// --- START: Calculate Delayed Tasks Count (static) ---
$delayed_tasks_count_total = 0;
$current_timestamp = time(); // Get timestamp once, same as Admin Dashboard

// Unified delayed tasks logic for all task types (same as Admin Dashboard and Manage Tasks)
foreach (array_merge($all_delegation_tasks, $all_checklist_tasks, $all_fms_tasks) as $task) {
    $is_delayed = false;
    
    // Condition 1: Actual time > planned time (task completed late)
    if (isset($task['actual_date']) && isset($task['actual_time']) && isset($task['planned_date']) && isset($task['planned_time'])) {
        $planned_timestamp = strtotime($task['planned_date'] . ' ' . $task['planned_time']);
        $actual_timestamp = strtotime($task['actual_date'] . ' ' . $task['actual_time']);
        if ($planned_timestamp && $actual_timestamp && $actual_timestamp > $planned_timestamp) {
            $is_delayed = true;
        }
    }
    
    // Condition 2: Planned time is past but actual time is missing (task not completed)
    if (!$is_delayed && isset($task['planned_date']) && isset($task['planned_time'])) {
        $planned_timestamp = strtotime($task['planned_date'] . ' ' . $task['planned_time']);
        if ($planned_timestamp && $current_timestamp > $planned_timestamp) {
            // Check if actual time is missing (task not completed)
            if (empty($task['actual_date']) || empty($task['actual_time'])) {
                $is_delayed = true;
            }
        }
    }
    
    if ($is_delayed) {
        $delayed_tasks_count_total++;
    }
}

// Debug logging for delayed tasks calculation
log_activity("=== DELAYED TASKS DEBUG ===");
log_activity("Total Delegation Tasks: " . count($all_delegation_tasks));
log_activity("Total Checklist Tasks: " . count($all_checklist_tasks));
log_activity("Total FMS Tasks: " . count($all_fms_tasks));

// Sample some FMS tasks to see their structure
$fms_sample_count = min(5, count($all_fms_tasks));
for ($i = 0; $i < $fms_sample_count; $i++) {
    $task = $all_fms_tasks[$i];
    log_activity("FMS Task Sample " . ($i+1) . ": planned_date=" . ($task['planned_date'] ?? 'NULL') . 
                   ", planned_time=" . ($task['planned_time'] ?? 'NULL') . 
                   ", actual_date=" . ($task['actual_date'] ?? 'NULL') . 
                   ", actual_time=" . ($task['actual_time'] ?? 'NULL') . 
                   ", status=" . ($task['status'] ?? 'NULL'));
}

log_activity("Final Delayed Tasks Count: " . $delayed_tasks_count_total);

// Additional debug info to compare with Admin Dashboard
log_activity("=== COMPARISON WITH ADMIN DASHBOARD ===");
log_activity("Manager Dashboard - Delegation: " . count($all_delegation_tasks) . ", Checklist: " . count($all_checklist_tasks) . ", FMS: " . count($all_fms_tasks));
log_activity("Manager Dashboard - Total: " . $total_tasks_in_view . ", Delayed: " . $delayed_tasks_count_total);

// Check for any tasks with missing fields that might cause counting differences
$missing_planned_date = 0;
$missing_planned_time = 0;
$missing_actual_date = 0;
$missing_actual_time = 0;

foreach (array_merge($all_delegation_tasks, $all_checklist_tasks, $all_fms_tasks) as $task) {
    if (empty($task['planned_date'])) $missing_planned_date++;
    if (empty($task['planned_time'])) $missing_planned_time++;
    if (empty($task['actual_date'])) $missing_actual_date++;
    if (empty($task['actual_time'])) $missing_actual_time++;
}

log_activity("Missing planned_date: " . $missing_planned_date);
log_activity("Missing planned_time: " . $missing_planned_time);
log_activity("Missing actual_date: " . $missing_actual_date);
log_activity("Missing actual_time: " . $missing_actual_time);

// Detailed task analysis to find the 5 task difference
log_activity("=== DETAILED TASK ANALYSIS ===");
$delayed_delegation = 0;
$delayed_checklist = 0;
$delayed_fms = 0;

foreach ($all_delegation_tasks as $task) {
    $is_delayed = false;
    
    // Condition 1: Actual time > planned time (task completed late)
    if (isset($task['actual_date']) && isset($task['actual_time']) && isset($task['planned_date']) && isset($task['planned_time'])) {
        $planned_timestamp = strtotime($task['planned_date'] . ' ' . $task['planned_time']);
        $actual_timestamp = strtotime($task['actual_date'] . ' ' . $task['actual_time']);
        if ($planned_timestamp && $actual_timestamp && $actual_timestamp > $planned_timestamp) {
            $is_delayed = true;
        }
    }
    
    // Condition 2: Planned time is past but actual time is missing (task not completed)
    if (!$is_delayed && isset($task['planned_date']) && isset($task['planned_time'])) {
        $planned_timestamp = strtotime($task['planned_date'] . ' ' . $task['planned_time']);
        if ($planned_timestamp && $current_timestamp > $planned_timestamp) {
            if (empty($task['actual_date']) || empty($task['actual_time'])) {
                $is_delayed = true;
            }
        }
    }
    
    if ($is_delayed) {
        $delayed_delegation++;
    }
}

foreach ($all_checklist_tasks as $task) {
    $is_delayed = false;
    
    // Condition 1: Actual time > planned time (task completed late)
    if (isset($task['actual_date']) && isset($task['actual_time']) && isset($task['planned_date']) && isset($task['planned_time'])) {
        $planned_timestamp = strtotime($task['planned_date'] . ' ' . $task['planned_time']);
        $actual_timestamp = strtotime($task['actual_date'] . ' ' . $task['actual_time']);
        if ($planned_timestamp && $actual_timestamp && $actual_timestamp > $planned_timestamp) {
            $is_delayed = true;
        }
    }
    
    // Condition 2: Planned time is past but actual time is missing (task not completed)
    if (!$is_delayed && isset($task['planned_date']) && isset($task['planned_time'])) {
        $planned_timestamp = strtotime($task['planned_date'] . ' ' . $task['planned_time']);
        if ($planned_timestamp && $current_timestamp > $planned_timestamp) {
            if (empty($task['actual_date']) || empty($task['actual_time'])) {
                $is_delayed = true;
            }
        }
    }
    
    if ($is_delayed) {
        $delayed_checklist++;
    }
}

foreach ($all_fms_tasks as $task) {
    $is_delayed = false;
    
    // Condition 1: Actual time > planned time (task completed late)
    if (isset($task['actual_date']) && isset($task['actual_time']) && isset($task['planned_date']) && isset($task['planned_time'])) {
        $planned_timestamp = strtotime($task['planned_date'] . ' ' . $task['planned_time']);
        $actual_timestamp = strtotime($task['actual_date'] . ' ' . $task['actual_time']);
        if ($planned_timestamp && $actual_timestamp && $actual_timestamp > $planned_timestamp) {
            $is_delayed = true;
        }
    }
    
    // Condition 2: Planned time is past but actual time is missing (task not completed)
    if (!$is_delayed && isset($task['planned_date']) && isset($task['planned_time'])) {
        $planned_timestamp = strtotime($task['planned_date'] . ' ' . $task['planned_time']);
        if ($planned_timestamp && $current_timestamp > $planned_timestamp) {
            if (empty($task['actual_date']) || empty($task['actual_time'])) {
                $is_delayed = true;
            }
        }
    }
    
    if ($is_delayed) {
        $delayed_fms++;
    }
}

log_activity("Delayed by type - Delegation: " . $delayed_delegation . ", Checklist: " . $delayed_checklist . ", FMS: " . $delayed_fms);
log_activity("Sum of individual counts: " . ($delayed_delegation + $delayed_checklist + $delayed_fms));
log_activity("Unified loop count: " . $delayed_tasks_count_total);
log_activity("=== END DETAILED TASK ANALYSIS ===");

log_activity("=== END COMPARISON DEBUG ===");

log_activity("=== END DELAYED TASKS DEBUG ===");

// --- END: Calculate Delayed Tasks Count ---

// --- START: Calculate Completion Rate (static) ---
if ($total_tasks_in_view > 0) {
    $completion_percentage = ($completed_tasks_count_total / $total_tasks_in_view) * 100;
    $remaining_percentage = 100 - $completion_percentage;
    // Format to 2 decimal places with negative sign
    $completion_rate = sprintf("%.2f%%", -$remaining_percentage);
} else {
    $completion_rate = "N/A"; // No tasks in system
}
// --- END: Calculate Completion Rate ---

// Log debug information about the metrics
log_activity("Total Tasks: " . $total_tasks_in_view);
log_activity("Completed Tasks: " . $completed_tasks_count_total);
log_activity("Delayed Tasks: " . $delayed_tasks_count_total);
log_activity("Completion Rate: " . $completion_rate);
log_activity("Delegation Tasks Count: " . count($all_delegation_tasks));
log_activity("Checklist Tasks Count: " . count($all_checklist_tasks));
log_activity("FMS Tasks Count: " . count($all_fms_tasks));

// Count tasks per sheet for debugging
$sheet_counts = [];
foreach ($all_fms_tasks as $task) {
    $sheet_id = $task['sheet_id'] ?? 'unknown';
    if (!isset($sheet_counts[$sheet_id])) {
        $sheet_counts[$sheet_id] = 0;
    }
    $sheet_counts[$sheet_id]++;
}
foreach ($sheet_counts as $sheet_id => $count) {
    log_activity("  Sheet ID: " . $sheet_id . " - Count: " . $count);
}

// Get all tasks (Delegation Tasks) for the main table display
$delegation_tasks = array();

// Add manager filtering for delegation tasks
$delegation_filter_conditions = "";
$delegation_filter_params = [];
$delegation_filter_param_types = "";

if (isManager() && !isAdmin()) {
    $current_manager_id = $_SESSION["id"]; // Get current manager's ID
    $current_manager_name = $_SESSION["name"] ?? $_SESSION["username"]; // Get current manager's name, fallback to username
    $delegation_filter_conditions = " WHERE (t.manager_id = ? OR t.doer_manager_id = ? OR COALESCE(t.doer_name, u.username, 'N/A') IN (SELECT username FROM users WHERE manager_id = ? OR manager = ?))";
    $delegation_filter_params[] = $current_manager_id;
    $delegation_filter_params[] = $current_manager_id;
    $delegation_filter_params[] = $current_manager_id;
    $delegation_filter_params[] = $current_manager_name;
    $delegation_filter_param_types = "iiis";
}

$sql_delegation = "SELECT t.id, t.id as task_id, t.unique_id, t.description, t.planned_date, t.planned_time, t.actual_date, t.actual_time, t.status, t.is_delayed, t.delay_duration, t.duration, COALESCE(t.doer_name, u.username, 'N/A') as doer_name, d.name as department_name, 'delegation' as task_type
        FROM tasks t 
        LEFT JOIN users u ON t.doer_id = u.id
        LEFT JOIN departments d ON t.department_id = d.id" . $delegation_filter_conditions . "
        LIMIT 1000"; // Performance optimization: Limit results to prevent memory issues
if (!empty($delegation_filter_param_types) && !empty($delegation_filter_params)) {
    // Use prepared statement for filtered query
    if ($stmt_delegation = mysqli_prepare($conn, $sql_delegation)) {
        mysqli_stmt_bind_param($stmt_delegation, $delegation_filter_param_types, ...$delegation_filter_params);
        if (mysqli_stmt_execute($stmt_delegation)) {
            $result_delegation = mysqli_stmt_get_result($stmt_delegation);
            if ($result_delegation) {
                while ($row = mysqli_fetch_assoc($result_delegation)) {
                    $delegation_tasks[] = $row;
                }
            }
        }
        mysqli_stmt_close($stmt_delegation);
    }
} else {
    // Use regular query for admin or no filters
    $result_delegation = mysqli_query($conn, $sql_delegation);
    if($result_delegation) {
        while($row = mysqli_fetch_assoc($result_delegation)) {
            $delegation_tasks[] = $row;
        }
    }
}

// Get Checklist Tasks (task_date <= end of the current week - Sunday) for the main table display
$checklist_tasks_list = array();
$today = new DateTime();
$end_of_week = (clone $today)->modify('next sunday'); // Or 'sunday this week' if today is Sunday
if ($today->format('N') == 7) { // If today is Sunday
    $end_of_week = $today;
}
$end_of_week_str = $end_of_week->format('Y-m-d');

$sql_checklist = "SELECT 
                        cs.id as task_id, 
                        cs.task_code as unique_id, 
                        cs.task_description as description, 
                        cs.task_date as planned_date, 
                        NULL as planned_time, 
                        cs.actual_date, 
                        cs.actual_time, 
                        COALESCE(cs.status, 'pending') as status, /* Fetch actual status, default NULL to pending */
                        0 as is_delayed, 
                        NULL as delay_duration, 
                        cs.duration, 
                        cs.assignee as doer_name, 
                        cs.department as department_name, 
                        'checklist' as task_type
                    FROM checklist_subtasks cs 
                    WHERE cs.task_date <= ? ";
                    // ORDER BY cs.task_date ASC"; // Sorting will be done after merge

if($stmt_checklist = mysqli_prepare($conn, $sql_checklist)) {
    mysqli_stmt_bind_param($stmt_checklist, "s", $end_of_week_str);
    if(mysqli_stmt_execute($stmt_checklist)) {
        $result_checklist = mysqli_stmt_get_result($stmt_checklist);
        while($row = mysqli_fetch_assoc($result_checklist)) {
            $checklist_tasks_list[] = $row;
        }
    }
    mysqli_stmt_close($stmt_checklist);
}


// Merge tasks for table display
$tasks_for_table = array_merge($delegation_tasks, $checklist_tasks_list);

// --- START: Fetch FMS Tasks from Database (Updated for Real-time) ---
$fms_tasks_list = array();

log_activity("Fetching FMS tasks from database for real-time updates.");

// Fetch FMS tasks from database instead of Google Sheets
$fms_tasks_query = "SELECT * FROM fms_tasks";
$fms_tasks_result = mysqli_query($conn, $fms_tasks_query);

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

        $is_delayed_fms = 0;
        $delay_duration_fms_str = null;

        // Determine if task is completed based on FMS status
        $completed_statuses_fms = ['completed', 'done'];
        $is_completed_fms = false;
        foreach($completed_statuses_fms as $comp_stat){
            if(strtolower($fms_status) === $comp_stat){
                $is_completed_fms = true;
                break;
            }
        }

        if ($is_completed_fms) {
            if ($actual_timestamp && $planned_timestamp && $actual_timestamp > $planned_timestamp) {
                $is_delayed_fms = 1;
                $delay_duration_seconds = $actual_timestamp - $planned_timestamp;
                $delay_duration_fms_str = formatSecondsToHHMMSS($delay_duration_seconds);
            }
        } else { // Not completed (e.g. pending, shifted) or actual time not set
            if ($planned_timestamp && $current_timestamp > $planned_timestamp) {
                $is_delayed_fms = 1;
                $delay_duration_seconds = $current_timestamp - $planned_timestamp;
                $delay_duration_fms_str = formatSecondsToHHMMSS($delay_duration_seconds);
            }
        }
        
        // Prepare date and time strings for the table display
        $planned_date_val = $planned_timestamp ? date('Y-m-d', $planned_timestamp) : null;
        $planned_time_val = $planned_timestamp ? date('H:i:s', $planned_timestamp) : null;
        $actual_date_val = $actual_timestamp ? date('Y-m-d', $actual_timestamp) : null;
        $actual_time_val = $actual_timestamp ? date('H:i:s', $actual_timestamp) : null;

        $fms_tasks_list[] = [
            'task_id'         => $fms_task['unique_key'],
            'unique_id'       => $fms_task['unique_key'],
            'description'     => $fms_task['step_name'],
            'planned_date'    => $planned_date_val,
            'planned_time'    => $planned_time_val,
                    'actual_date'     => $actual_date_val,
                    'actual_time'     => $actual_time_val,
                    'status'          => $fms_status,
                    'is_delayed'      => $is_delayed_fms,
                    'delay_duration'  => $delay_duration_fms_str,
                    'duration'        => $fms_task['duration'],
                    'doer_name'       => $fms_task['doer_name'],
                    'department_name' => $fms_task['sheet_label'],
                    'task_type'       => 'FMS',
                    'task_link_fms'   => $fms_task['task_link'],
                    'raw_planned'     => $planned_datetime_str,
                    'raw_actual'      => $actual_datetime_str,
                    'fms_original_status' => $fms_status_raw,
                    'planned_timestamp' => $planned_timestamp,
                    'created_at'      => date('Y-m-d H:i:s') // Use current timestamp as fallback
                ];
            }
            log_activity("Successfully fetched " . count($fms_tasks_list) . " FMS tasks from database.");
        } else {
            log_activity("No FMS tasks found in database or database query failed.");
        }
// --- END: Fetch FMS Tasks from Database (Updated for Real-time) ---

// Log the count after all FMS tasks are fetched
log_activity("FMS Tasks List (display) final count: " . count($fms_tasks_list));

// Update the merged tasks to include FMS tasks
$tasks_for_table = array_merge($tasks_for_table, $fms_tasks_list);

// ---- START: Prepare data for Filter Dropdowns (uses $tasks_for_table) ----
$distinct_doers_filter = [];
$distinct_departments_filter = [];
if (!empty($tasks_for_table)) {
    foreach ($tasks_for_table as $task_item) {
        if (!empty($task_item['doer_name']) && !in_array($task_item['doer_name'], $distinct_doers_filter)) {
            $distinct_doers_filter[] = $task_item['doer_name'];
        }
        if (!empty($task_item['department_name']) && !in_array($task_item['department_name'], $distinct_departments_filter)) {
            $distinct_departments_filter[] = $task_item['department_name'];
        }
    }
    sort($distinct_doers_filter);
    sort($distinct_departments_filter);
}
// ---- END: Prepare data for Filter Dropdowns ----


// ---- START: Filtering Logic (updates $tasks_for_table) ----
$filter_doer = isset($_GET['filter_doer']) ? trim($_GET['filter_doer']) : '';
$filter_department = isset($_GET['filter_department']) ? trim($_GET['filter_department']) : '';
$filter_date_from = isset($_GET['filter_date_from']) ? trim($_GET['filter_date_from']) : '';
$filter_date_to = isset($_GET['filter_date_to']) ? trim($_GET['filter_date_to']) : '';

$filtered_tasks = $tasks_for_table; // Start with all merged tasks

if (!empty($filter_doer)) {
    $filtered_tasks = array_filter($filtered_tasks, function($task_item) use ($filter_doer) {
        return isset($task_item['doer_name']) && $task_item['doer_name'] == $filter_doer;
    });
}

if (!empty($filter_department)) {
    $filtered_tasks = array_filter($filtered_tasks, function($task_item) use ($filter_department) {
        return isset($task_item['department_name']) && $task_item['department_name'] == $filter_department;
    });
}

// Date range filtering - Match dashboard logic: check both planned_date AND actual_date
if (!empty($filter_date_from) || !empty($filter_date_to)) {
    $filtered_tasks = array_filter($filtered_tasks, function($task_item) use ($filter_date_from, $filter_date_to) {
        // Match dashboard isTaskInDateRange() logic: include task if EITHER planned_date OR actual_date falls in range
        $from_ts = !empty($filter_date_from) ? strtotime($filter_date_from . ' 00:00:00') : 0;
        $to_ts = !empty($filter_date_to) ? strtotime($filter_date_to . ' 23:59:59') : PHP_INT_MAX;
        
        $in_range = false;
        
        // Check planned_date
        if (!empty($task_item['planned_date'])) {
            $planned_ts = strtotime($task_item['planned_date'] . ' 00:00:00');
            if ($planned_ts >= $from_ts && $planned_ts <= $to_ts) {
                $in_range = true;
            }
        }
        
        // Check actual_date (dashboard includes tasks based on actual_date too)
        if (!$in_range && !empty($task_item['actual_date'])) {
            $actual_ts = strtotime($task_item['actual_date'] . ' 00:00:00');
            if ($actual_ts >= $from_ts && $actual_ts <= $to_ts) {
                $in_range = true;
            }
        }
        
        return $in_range;
    });
}
$tasks_for_table = $filtered_tasks; // Replace original $tasks with filtered results
// ---- END: Filtering Logic ----


// After filtering, before sorting, update Total Tasks for scorecard
// This line should NOT update $total_tasks_in_view as it's already set correctly above
// $total_tasks_in_view = count($tasks_for_table);

// Get only the 10 most recently added tasks
// Sort all tasks by task_id in descending order (most recent first)
usort($tasks_for_table, function($a, $b) {
    // Sort by task_id in descending order (most recent first)
        return $b['task_id'] <=> $a['task_id'];
});

// Limit to 10 tasks
$recent_tasks = array_slice($tasks_for_table, 0, 10);

// For backward compatibility with the rest of the code
$paginated_tasks = $recent_tasks;
$total_pages = 1; // No pagination needed anymore
$current_page = 1;
// ---- END: Pagination Logic ----

?>

<style>
        /* Adjust Select2 height to match Bootstrap form-control-sm */
        .select2-container .select2-selection--single {
            height: calc(1.5em + .3rem + 2px) !important; /* For form-control-sm */
            padding: .15rem .3rem !important; /* For form-control-sm */
            font-size: .875rem !important; /* For form-control-sm */
            line-height: 1.5 !important; /* For form-control-sm */
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: calc(1.5em + .3rem) !important; /* Adjust arrow height */
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: calc(1.5em + .3rem) !important; /* Adjust rendered text line height */
        }
    
/* Description hover styling */
.description-hover {
    cursor: pointer;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 150px;
    display: inline-block;
    position: relative;
    width: 100%;
}

/* Ensure description column has fixed width */
.table {
    table-layout: fixed;
}

.table td:nth-child(2) {
    max-width: 150px;
    width: 150px;
    word-wrap: break-word;
    overflow: hidden;
}

.description-hover:hover {
    text-decoration: none;
}

/* Custom tooltip for description */
.description-hover::after {
    content: attr(data-full-description);
    position: absolute;
    bottom: 125%;
    left: 50%;
    transform: translateX(-50%);
    background-color: #000;
    color: #fff;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 13px;
    white-space: nowrap;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    pointer-events: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    border: 1px solid #333;
}

.description-hover::before {
    content: '';
    position: absolute;
    bottom: 115%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: #000;
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

/* Delay hover styling */
.delay-hover {
    cursor: pointer;
    border-bottom: 1px dotted #dc3545;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 80px;
    display: inline-block;
    position: relative;
}

.delay-hover:hover {
    text-decoration: none;
}

/* Custom tooltip for delay */
.delay-hover::after {
    content: attr(data-full-delay);
    position: absolute;
    bottom: 125%;
    left: 50%;
    transform: translateX(-50%);
    background-color: #000;
    color: #fff;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 13px;
    white-space: nowrap;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    pointer-events: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    border: 1px solid #333;
}

.delay-hover::before {
    content: '';
    position: absolute;
    bottom: 115%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: #000;
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

        .tooltip-inner {
            max-width: 300px;
            text-align: left;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        /* Enhanced Stats Cards Styling */
        .card.bg-primary, .card.bg-success, .card.bg-warning, .card.bg-info, .card.bg-danger {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .card.bg-primary:hover, .card.bg-success:hover, .card.bg-warning:hover, .card.bg-info:hover, .card.bg-danger:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .card.bg-primary::before, .card.bg-success::before, .card.bg-warning::before, .card.bg-info::before, .card.bg-danger::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .card.bg-primary:hover::before, .card.bg-success:hover::before, .card.bg-warning:hover::before, .card.bg-info:hover::before, .card.bg-danger:hover::before {
            left: 100%;
        }

        .text-white-75 {
            color: rgba(255, 255, 255, 0.75) !important;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .text-lg {
            font-size: 2rem;
            font-weight: 700;
        }

        .card-footer {
            background: rgba(0, 0, 0, 0.1);
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            padding: 0.75rem 1rem;
        }

        .card-footer a {
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .card-footer a:hover {
            text-decoration: underline;
        }

        .card-footer i {
            transition: transform 0.2s ease;
        }

        .card:hover .card-footer i {
            transform: translateX(3px);
        }

        /* Icon animations */
        .card i {
            transition: all 0.3s ease;
        }

        .card:hover i {
            transform: scale(1.1);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .text-lg {
                font-size: 1.5rem;
            }
            
            .card i {
                font-size: 1.5rem !important;
            }
        }

        /* Pulse animation for delayed tasks */
        .card.bg-danger {
            animation: pulse-danger 2s infinite;
        }

        @keyframes pulse-danger {
            0% { box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); }
            50% { box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3); }
            100% { box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); }
        }

        /* Success pulse for completed tasks */
        .card.bg-success {
            animation: pulse-success 3s infinite;
        }

        @keyframes pulse-success {
            0% { box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); }
            50% { box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3); }
            100% { box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); }
        }

        /* Dashboard background enhancement */
        .main-content {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .main-content::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120, 219, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }

        /* Enhanced card styling */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        /* Recent Tasks card enhancement */
        .card-header.bg-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border: none;
        }

        .card-header h5 {
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        /* Table enhancements */
        .table {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .table thead th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            color: #495057;
        }

        /* Dark theme table headers for Recent Tasks */
        .table-dark thead th {
            background: linear-gradient(135deg, rgba(26, 26, 26, 0.95) 0%, rgba(42, 42, 42, 0.95) 100%) !important;
            color: #ffffff !important;
            border-bottom: 2px solid rgba(102, 126, 234, 0.3) !important;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            padding: 1rem 0.75rem;
        }

        .table-dark {
            background-color: rgba(26, 26, 26, 0.6) !important;
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }

        .table-dark tbody tr {
            background-color: rgba(26, 26, 26, 0.4) !important;
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.05) !important;
        }

        .table-dark tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.15) !important;
            color: #ffffff !important;
        }

        .table-dark td {
            color: rgba(255, 255, 255, 0.9) !important;
            border-color: rgba(255, 255, 255, 0.05) !important;
            padding: 0.75rem;
        }

        /* Status badge styling for dark theme table */
        .table-dark .badge {
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .table-dark .badge-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.3) 0%, rgba(5, 150, 105, 0.3) 100%) !important;
            color: #10b981 !important;
            border: 1px solid rgba(16, 185, 129, 0.4) !important;
        }

        .table-dark .badge-info {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.3) 0%, rgba(37, 99, 235, 0.3) 100%) !important;
            color: #3b82f6 !important;
            border: 1px solid rgba(59, 130, 246, 0.4) !important;
        }

        .table-dark .badge-warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.3) 0%, rgba(217, 119, 6, 0.3) 100%) !important;
            color: #f59e0b !important;
            border: 1px solid rgba(245, 158, 11, 0.4) !important;
        }

        .table-dark .badge-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.3) 0%, rgba(220, 38, 38, 0.3) 100%) !important;
            color: #ef4444 !important;
            border: 1px solid rgba(239, 68, 68, 0.4) !important;
        }

        .table-dark .badge-secondary {
            background: linear-gradient(135deg, rgba(107, 114, 128, 0.3) 0%, rgba(75, 85, 99, 0.3) 100%) !important;
            color: #9ca3af !important;
            border: 1px solid rgba(107, 114, 128, 0.4) !important;
        }

        .table tbody tr {
            transition: all 0.2s ease;
        }

        .table tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
            transform: scale(1.01);
        }

        /* Button enhancements */
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            padding: 0.75rem 2rem;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        /* Alert enhancements */
        .alert {
            border: none;
            border-radius: 10px;
            font-weight: 500;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }

        /* Leave Details Modal Styles - Same as doer dashboard */
        .team-availability-section .leave-details-modal {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            pointer-events: none;
        }

        .team-availability-section .leave-details-modal.show {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .team-availability-section .leave-details-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            z-index: 1001;
            border-radius: 16px;
        }

        .team-availability-section .leave-details-card {
            position: relative;
            z-index: 1002;
            background: linear-gradient(135deg, rgba(26, 26, 26, 0.98) 0%, rgba(42, 42, 42, 0.98) 100%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.6),
                0 0 0 1px rgba(255, 255, 255, 0.1) inset;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            width: 90%;
            max-width: 270px;
            max-height: 55vh;
            overflow: hidden;
            transform: scale(0.9) translateY(20px);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .team-availability-section .leave-details-modal.show .leave-details-card {
            transform: scale(1) translateY(0);
        }

        .team-availability-section .leave-details-header {
            padding: 1.25rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .team-availability-section .leave-details-title {
            margin: 0;
            color: #ffffff;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .team-availability-section .leave-details-title i {
            color: #667eea;
            font-size: 1.2rem;
        }

        .team-availability-section .leave-details-close {
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1rem;
            cursor: pointer;
            padding: 0.25rem;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .team-availability-section .leave-details-close:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            transform: rotate(90deg);
        }

        .team-availability-section .leave-details-body {
            padding: 1rem;
            max-height: calc(85vh - 100px);
            overflow-y: auto;
            scroll-behavior: smooth;
        }

        .team-availability-section .leave-details-body::-webkit-scrollbar {
            width: 4px;
        }

        .team-availability-section .leave-details-body::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 6px;
        }

        .team-availability-section .leave-details-body::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 6px;
        }

        .team-availability-section .leave-details-body::-webkit-scrollbar-thumb:hover {
            background: #667eea;
        }

        .team-availability-section .leave-detail-item {
            display: flex;
            flex-direction: row;
            gap: 0.25rem;
            padding: 0.5rem 0;
            border-bottom: 2px solid rgba(255, 255, 255, 0.05);
        }

        .team-availability-section .leave-detail-item:last-child {
            border-bottom: none;
        }

        .team-availability-section .leave-detail-label {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .team-availability-section .leave-detail-label i {
            color: #667eea;
            font-size: 0.9rem;
            width: 18px;
            text-align: center;
        }

        .team-availability-section .leave-detail-value {
            color: #ffffff;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            margin-top: 0.25rem;
            padding-left: calc(18px + 0.5rem);
        }

        .leave-status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .leave-status-badge.status-approved {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.2) 100%);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
        }

        .leave-status-badge.status-pending {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.2) 0%, rgba(217, 119, 6, 0.2) 100%);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.3);
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.2);
        }

        @media (max-width: 768px) {
            .team-availability-section .leave-details-card {
                width: 95%;
                max-width: none;
            }
        }

        /* Loading Overlay Styles */
        #dashboardLoadingOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.3s ease;
            transform: none !important;
        }

        #dashboardLoadingOverlay .loading-spinner {
            text-align: center;
            color: white;
            transform: none !important;
            animation: none !important;
            width: auto;
            height: auto;
            border: none !important;
        }

        #dashboardLoadingOverlay .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255, 255, 255, 0.3) !important;
            border-top-color: #667eea !important;
            border-right-color: rgba(255, 255, 255, 0.3) !important;
            border-bottom-color: rgba(255, 255, 255, 0.3) !important;
            border-left-color: rgba(255, 255, 255, 0.3) !important;
            border-radius: 50%;
            animation: dashboard-spin 1s linear infinite;
            margin: 0 auto 1rem;
            transform-origin: center center;
            display: block;
            background: none !important;
        }

        @keyframes dashboard-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        #dashboardLoadingOverlay .loading-spinner p {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 500;
            transform: none !important;
            animation: none !important;
            display: block;
            position: relative;
        }

        /* Hide sections initially until data loads */
        .stats-section,
        .chart-section,
        .leaderboard-section,
        .motivation-section {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .stats-section.loaded,
        .chart-section.loaded,
        .leaderboard-section.loaded,
        .motivation-section.loaded {
            opacity: 1;
            transform: translateY(0);
        }

        /* Consistent Section Spacing */
        .stats-section {
            margin-bottom: 2rem;
        }

        .stats-section:last-of-type {
            margin-bottom: 0;
        }

        .dashboard-grid {
            margin-top: 2rem;
        }

        .chart-section.full-width {
            grid-column: 1 / -1;
        }

        .chart-section.recent-tasks {
            margin-top: 2rem;
            margin-left: auto;
            margin-right: auto;
            max-width: 100%;
        }

        /* Ensure Recent Tasks section has same container padding as dashboard-grid */
        .doer-dashboard > .chart-section.recent-tasks {
            margin-left: 2rem;
            margin-right: 2rem;
        }

        /* Consistent Section Headers */
        .stats-section .stats-title,
        .chart-section .section-title,
        .leaderboard-section .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-section .section-title,
        .leaderboard-section .section-title {
            font-size: 1.3rem;
        }

        .stats-section .stats-title i,
        .chart-section .section-title i,
        .leaderboard-section .section-title i {
            color: #667eea;
            font-size: 1.1rem;
        }

        .stats-caption {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 0.25rem;
            font-weight: 400;
            line-height: 1.4;
        }

        /* Consistent Section Padding - Override doer_dashboard.css for consistency */
        .chart-section {
            padding: 1.5rem !important;
        }

        .leaderboard-section {
            padding: 1.5rem !important;
        }

        .team-availability-section {
            padding: 1.5rem !important;
        }

        /* Purple active state for date range buttons */
        .date-range-btn.active {
            background: linear-gradient(135deg, #8b5cf6 0%, #a855f7 100%) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4) !important;
            border: 1px solid rgba(139, 92, 246, 0.5) !important;
        }
        
        .date-range-btn.active:hover {
            background: linear-gradient(135deg, #7c3aed 0%, #9333ea 100%) !important;
            box-shadow: 0 6px 16px rgba(139, 92, 246, 0.5) !important;
        }
        
        .date-range-dropdown-item.active {
            background: linear-gradient(135deg, #8b5cf6 0%, #a855f7 100%) !important;
            color: #ffffff !important;
        }

        .motivation-section {
            padding: 1.5rem !important;
            margin-top: 2rem;
            margin-left: auto;
            margin-right: auto;
            max-width: 100%;
        }

        /* Ensure Motivation section has same container margins as other sections */
        .doer-dashboard > .motivation-section {
            margin-left: 2rem;
            margin-right: 2rem;
        }

        /* Ensure consistent alignment */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .stats-header {
            margin-bottom: 1.5rem;
        }

        /* Stats grid - 4 columns for 8 cards (4x2 layout) */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
</style>

                <!-- Modern Manager Dashboard -->
                <link rel="stylesheet" href="../assets/css/doer_dashboard.css">
                <div class="doer-dashboard" id="managerDashboard">
                    <!-- Dashboard Header -->
                    <div class="dashboard-header">
                        <div class="welcome-section">
                            <h1 class="welcome-title">
                                Welcome back, <span class="username-highlight"><?php echo htmlspecialchars($_SESSION["username"]); ?></span>
                            </h1>
                            <p class="welcome-subtitle">Manage your team and track performance!</p>
                        </div>
                        <div class="Daily-Quotes">
                            <div class="quote-container">
                                <div class="quote-icon">
                                    <i class="fas fa-quote-left"></i>
                                </div>
                                <div class="quote-content">
                                    <p class="daily-quote" id="dailyQuote">
                                        "Leadership is not about being in charge. It's about taking care of those in your charge."
                                    </p>
                                    <div class="quote-author" id="quoteAuthor">
                                        — Simon Sinek
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
<br><br>
                    <!-- Personal Stats Section (Manager's Own Tasks) -->
                    <div class="stats-section">
                        <div class="stats-header">
                            <div>
                                <h6 class="stats-title">
                                    <i class="fas fa-user"></i>
                                    <span id="personalOverviewTitle">Personal Overview</span>
                                </h6>
                                <div id="personalOverviewCaption" class="stats-caption">This Week Overview</div>
                            </div>
                            <div class="date-range-selector">
                                <button class="date-range-btn active" data-range="this_week" title="This Week">This Week</button>
                                <button class="date-range-btn" data-range="last_week" title="Last Week">Last Week</button>
                                <div class="date-range-dropdown">
                                    <button class="date-range-btn dropdown-toggle" id="personalDateRangeDropdownBtn" title="More Options">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                    <div class="date-range-dropdown-menu" id="personalDateRangeDropdownMenu" style="display: none;">
                                        <button class="date-range-dropdown-item" data-range="last_2_weeks">Last 2 Weeks</button>
                                        <button class="date-range-dropdown-item" data-range="last_4_weeks">Last 4 Weeks</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="stats-container-wrapper">
                            <div class="stats-grid" id="personalStatsGrid">
                                <div class="stat-card completed" onclick="window.location.href='my_task.php'">
                                    <div class="stat-icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-value" data-target="0">0</div>
                                        <div class="stat-label">Completed Tasks</div>
                                    </div>
                                </div>
                                <div class="stat-card pending" onclick="window.location.href='my_task.php'">
                                    <div class="stat-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-value" data-target="0">0</div>
                                        <div class="stat-label">Pending Tasks</div>
                                    </div>
                                </div>
                                <div class="stat-card" data-stat="wnd" onclick="window.location.href='my_task.php'">
                                    <div class="stat-icon">
                                        <i class="fas fa-times-circle"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-value" data-target="0">0%</div>
                                        <div class="stat-label">WND</div>
                                    </div>
                                </div>
                                <div class="stat-card" data-stat="wnd_on_time" onclick="window.location.href='my_task.php'">
                                    <div class="stat-icon">
                                        <i class="fas fa-hourglass-half"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-value" data-target="0">0%</div>
                                        <div class="stat-label">WND on Time</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content Grid -->
                    <div class="dashboard-grid">
                        <!-- Team Leaderboard Section -->
                        <div class="leaderboard-section">
                            <div class="section-header">
                                <h3 class="section-title">
                                    <i class="fas fa-trophy"></i>
                                    Team Leaderboard
                                </h3>
                                <div class="leaderboard-controls">
                                    <div class="time-period-selector">
                                        <button class="period-btn active" data-period="last_week" title="Last Week">
                                            <i class="fas fa-calendar-week"></i> Last Week
                                        </button>
                                        <button class="period-btn" data-period="last_2_weeks" title="Last 2 Week">
                                            <i class="fas fa-calendar"></i> Last 2 Week
                                        </button>
                                        <button class="period-btn" data-period="last_4_weeks" title="Last 4 Week">
                                            <i class="fas fa-calendar-alt"></i> Last 4 Week
                                        </button>
                                    </div>
                                    
                                </div>
                            </div>
                            <div class="leaderboard-content">
                                <div class="leaderboard-list" id="teamLeaderboardList">
                                    <!-- Leaderboard items will be populated by JavaScript -->
                                </div>
                                <div class="leaderboard-pagination" id="leaderboardPagination">
                                    <!-- Pagination controls will be populated by JavaScript -->
                                </div>
                                <div style="text-align: center; margin-top: 1.5rem;">
                                    <button class="btn btn-primary" id="viewPerformanceBtn" onclick="viewPerformance()">
                                        <i class="fas fa-chart-line"></i> View Performance
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Team Availability Section -->
                        <div class="chart-section team-availability-section" id="teamAvailabilitySection">
                            <style>
                                .team-availability-section .section-header {
                                    display: flex;
                                    align-items: center;
                                    justify-content: space-between;
                                    gap: 1rem;
                                    flex-wrap: wrap;
                                    margin-bottom: 1.5rem;
                                    padding-bottom: 1rem;
                                    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                                }

                                .team-availability-section .section-title {
                                    font-size: 1.375rem;
                                    font-weight: 600;
                                    color: var(--dark-text-primary);
                                    margin: 0;
                                    display: flex;
                                    align-items: center;
                                    gap: 0.625rem;
                                    line-height: 1.4;
                                }

                                .team-availability-section .section-title i {
                                    color: #667eea;
                                    font-size: 1.125rem;
                                }

                                .team-availability-section .availability-stats {
                                    display: flex;
                                    align-items: center;
                                    gap: 0.875rem;
                                    flex-wrap: nowrap !important;
                                }

                                .team-availability-section .stat-item {
                                    display: flex;
                                    align-items: center;
                                    gap: 0.625rem;
                                    padding: 0.625rem 1rem;
                                    background: rgba(255, 255, 255, 0.05);
                                    backdrop-filter: blur(10px);
                                    border: 1px solid rgba(255, 255, 255, 0.1);
                                    border-radius: 0.625rem;
                                    font-size: 0.875rem;
                                    font-weight: 500;
                                    color: var(--dark-text-primary);
                                    line-height: 1.4;
                                    transition: all 0.3s ease;
                                    position: relative;
                                    white-space: nowrap !important;
                                    flex-shrink: 0 !important;
                                    overflow: hidden;
                                }

                                .team-availability-section .stat-item::before {
                                    content: '';
                                    position: absolute;
                                    left: 0;
                                    top: 0;
                                    bottom: 0;
                                    width: 3px;
                                    border-radius: 0.625rem 0 0 0.625rem;
                                    transition: width 0.3s ease;
                                }

                                .team-availability-section .stat-item:hover {
                                    background: rgba(255, 255, 255, 0.08);
                                    transform: translateY(-2px);
                                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
                                }

                                .team-availability-section .stat-item.available {
                                    border-left: 3px solid #10b981;
                                }

                                .team-availability-section .stat-item.available::before {
                                    background: #10b981;
                                }

                                .team-availability-section .stat-item.on-wfh {
                                    border-left: 3px solid #3b82f6;
                                }

                                .team-availability-section .stat-item.on-wfh::before {
                                    background: #3b82f6;
                                }

                                .team-availability-section .stat-item.on-leave {
                                    border-left: 3px solid #ef4444;
                                }

                                .team-availability-section .stat-item.on-leave::before {
                                    background: #ef4444;
                                }

                                @media (max-width: 768px) {
                                    .team-availability-section .section-header {
                                        flex-direction: column;
                                        align-items: flex-start;
                                    }

                                    .team-availability-section .availability-stats {
                                        flex-wrap: nowrap !important;
                                        gap: 0.625rem;
                                    }

                                    .team-availability-section .stat-item {
                                        font-size: 0.8125rem;
                                        white-space: nowrap !important;
                                        padding: 0.5rem 0.875rem;
                                    }
                                }
                            </style>
                            <div class="section-header">
                                <h4 class="section-title">
                                    <i class="fas fa-users"></i>
                                    Team Availability
                                </h4>
                                <div class="availability-stats">
                                    <div class="stat-item available">
                                        <span class="stat-dot"></span>
                                        <span id="availableCount">0</span> Available
                                    </div>
                                    <div class="stat-item on-wfh">
                                        <span class="stat-dot"></span>
                                        <span id="onWfhCount">0</span> On WFH
                                    </div>
                                    <div class="stat-item on-leave">
                                        <span class="stat-dot"></span>
                                        <span id="onLeaveCount">0</span> On Leave
                                    </div>
                                </div>
                            </div>
                            <div class="team-grid" id="teamGrid">
                                <!-- Team members (available and on leave) will be populated by JavaScript -->
                            </div>

                            <!-- Leave Details Modal - Inside Team Availability Section -->
                            <div class="leave-details-modal" id="leaveDetailsModal">
                                <div class="leave-details-overlay" id="leaveDetailsOverlay"></div>
                                <div class="leave-details-card">
                                    <div class="leave-details-header">
                                        <h5 class="leave-details-title">
                                            <i class="fas fa-calendar-alt"></i> <span></span> <span></span>
                                            <span id="leaveDetailsMemberName">Leave Details</span>
                                        </h5>
                                        <button class="leave-details-close" id="leaveDetailsClose">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div class="leave-details-body">
                                        <div class="leave-detail-item">
                                            <div class="leave-detail-label">
                                                <i class="fas fa-tag"></i>
                                                Leave Type:
                                            </div>
                                            <div class="leave-detail-value" id="leaveDetailsType">-</div>
                                        </div>
                                        <div class="leave-detail-item">
                                            <div class="leave-detail-label">
                                                <i class="fas fa-clock"></i>
                                                Duration:
                                            </div>
                                            <div class="leave-detail-value" id="leaveDetailsDuration">-</div>
                                        </div>
                                        <div class="leave-detail-item">
                                            <div class="leave-detail-label">
                                                <i class="fas fa-calendar-check"></i>
                                                Start Date:
                                            </div>
                                            <div class="leave-detail-value" id="leaveDetailsStartDate">-</div>
                                        </div>
                                        <div class="leave-detail-item">
                                            <div class="leave-detail-label">
                                                <i class="fas fa-calendar-times"></i>
                                                End Date:
                                            </div>
                                            <div class="leave-detail-value" id="leaveDetailsEndDate">-</div>
                                        </div>
                                        <div class="leave-detail-item">
                                            <div class="leave-detail-label">
                                                <i class="fas fa-calendar-day"></i>
                                                No. of Days:
                                            </div>
                                            <div class="leave-detail-value" id="leaveDetailsDays">-</div>
                                        </div>
                                        <div class="leave-detail-item">
                                            <div class="leave-detail-label">
                                                <i class="fas fa-info-circle"></i>
                                                Status:
                                            </div>
                                            <div class="leave-detail-value">
                                                <span class="leave-status-badge" id="leaveDetailsStatus">-</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Motivation & Insights Panel -->
                    <div class="motivation-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-lightbulb"></i>
                                Performance Insights
                            </h3>
                        </div>
                        <div class="motivation-content" id="managerMotivationContent">
                            <div class="loading-motivation">
                                <i class="fas fa-spinner fa-spin"></i> Loading insights...
                            </div>
                        </div>
                    </div>

                    <!-- Recent Tasks Section -->
                    <div class="chart-section recent-tasks">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-list"></i>
                                Recent Team Tasks
                            </h3>
                            <button class="btn btn-sm btn-primary" onclick="window.location.href='manage_tasks.php'">
                                <i class="fas fa-external-link-alt"></i> View All
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover">
                                <thead>
                                    <tr>
                                        <th>Task ID</th>
                                        <th>Description</th>
                                        <th>Doer</th>
                                        <th>Department</th>
                                        <th>Planned Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="recentTasksTable">
                                    <tr>
                                        <td colspan="6" class="text-center">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>


                <style>
                /* Leaderboard clickable item styles */
                .leaderboard-item[style*="cursor: pointer"] {
                    transition: all 0.2s ease;
                }
                .leaderboard-item[style*="cursor: pointer"]:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
                    background: rgba(102, 126, 234, 0.05);
                }

                .loading-motivation {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.5rem;
                    padding: 1rem;
                    color: var(--dark-text-secondary);
                    font-size: 0.95rem;
                }

                .loading-motivation i {
                    color: #818cf8;
                }

                /* Leaderboard Pagination Styles */
                .leaderboard-pagination {
                    margin-top: 1rem;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    gap: 0.75rem;
                }

                .pagination-controls {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.5rem;
                }

                .pagination-btn {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 32px;
                    height: 32px;
                    padding: 0.375rem 0.625rem;
                    background: rgba(255, 255, 255, 0.05);
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 0.5rem;
                    color: var(--dark-text-primary);
                    font-size: 0.8125rem;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.3s ease;
                }

                .pagination-btn:hover:not(:disabled) {
                    background: rgba(255, 255, 255, 0.1);
                    border-color: rgba(255, 255, 255, 0.2);
                    transform: translateY(-1px);
                }

                .pagination-btn:disabled {
                    opacity: 0.4;
                    cursor: not-allowed;
                }

                .pagination-btn.page-number {
                    min-width: 36px;
                }

                .pagination-btn.active {
                    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
                    border-color: rgba(99, 102, 241, 0.5);
                    color: #fff;
                    font-weight: 600;
                }

                .pagination-btn.active:hover {
                    background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
                }

                .pagination-ellipsis {
                    color: var(--dark-text-secondary);
                    padding: 0 0.25rem;
                    font-size: 0.875rem;
                }

                .view-rank-btn {
                    width: auto;
                    min-width: 160px;
                    background: rgba(99, 102, 241, 0.1);
                    border: 1px solid rgba(99, 102, 241, 0.3);
                    color: #818cf8;
                    padding: 0.5rem 1rem;
                    border-radius: 0.5rem;
                    font-size: 0.8125rem;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.5rem;
                }

                .view-rank-btn:hover {
                    background: rgba(99, 102, 241, 0.2);
                    border-color: rgba(99, 102, 241, 0.5);
                    transform: translateY(-1px);
                    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
                }

                .view-rank-btn i {
                    font-size: 0.8125rem;
                }
                </style>

                <script>
                // Global variables
                const managerUserId = <?php echo (int)($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0); ?>;
                let currentDateRange = {
                    type: 'this_week',
                    fromDate: null,
                    toDate: null
                };

                // Helper function to get Monday of a given week (week runs Monday to Sunday)
                function getMondayOfWeek(date) {
                    const d = new Date(date);
                    d.setHours(0, 0, 0, 0);
                    const day = d.getDay(); // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
                    const diff = d.getDate() - day + (day === 0 ? -6 : 1); // Adjust when day is Sunday
                    const monday = new Date(d);
                    monday.setDate(diff);
                    return monday;
                }

                // Calculate date range based on week-based options
                function calculateWeekDateRange(rangeType) {
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    
                    let fromDate, toDate;
                    
                    switch(rangeType) {
                        case 'this_week':
                            // Monday of current week to today (inclusive)
                            fromDate = getMondayOfWeek(today);
                            toDate = new Date(today);
                            break;
                            
                        case 'last_week':
                            // Monday to Sunday of last week
                            const thisWeekMonday = getMondayOfWeek(today);
                            const lastWeekMonday = new Date(thisWeekMonday);
                            lastWeekMonday.setDate(thisWeekMonday.getDate() - 7);
                            fromDate = lastWeekMonday;
                            toDate = new Date(lastWeekMonday);
                            toDate.setDate(lastWeekMonday.getDate() + 6);
                            break;
                            
                        case 'last_2_weeks':
                            // Monday of 2 weeks ago to Sunday of last week
                            const thisWeekMonday2 = getMondayOfWeek(today);
                            const twoWeeksAgoMonday = new Date(thisWeekMonday2);
                            twoWeeksAgoMonday.setDate(thisWeekMonday2.getDate() - 14);
                            fromDate = twoWeeksAgoMonday;
                            const lastWeekSunday = new Date(thisWeekMonday2);
                            lastWeekSunday.setDate(thisWeekMonday2.getDate() - 1);
                            toDate = lastWeekSunday;
                            break;
                            
                        case 'last_4_weeks':
                            // Monday of 4 weeks ago to Sunday of last week
                            const thisWeekMonday4 = getMondayOfWeek(today);
                            const fourWeeksAgoMonday = new Date(thisWeekMonday4);
                            fourWeeksAgoMonday.setDate(thisWeekMonday4.getDate() - 28);
                            fromDate = fourWeeksAgoMonday;
                            const lastWeekSunday4 = new Date(thisWeekMonday4);
                            lastWeekSunday4.setDate(thisWeekMonday4.getDate() - 1);
                            toDate = lastWeekSunday4;
                            break;
                            
                            
                        default:
                            // Default to this week
                            fromDate = getMondayOfWeek(today);
                            toDate = new Date(today);
                    }
                    
                    // Format dates as YYYY-MM-DD
                    const formatDate = (date) => {
                        const year = date.getFullYear();
                        const month = String(date.getMonth() + 1).padStart(2, '0');
                        const day = String(date.getDate()).padStart(2, '0');
                        return `${year}-${month}-${day}`;
                    };
                    
                    return {
                        fromDate: formatDate(fromDate),
                        toDate: formatDate(toDate)
                    };
                }

                // Leaderboard variables (same as doer dashboard)
                let leaderboardData = [];
                let currentLeaderboardPage = 1;
                const leaderboardItemsPerPage = 4;
                let currentLeaderboardPeriod = 'last_week'; // 'last_week', 'last_2_weeks', 'last_4_weeks'
                let teamMemberIds = []; // Store team member IDs for access control

                // Initialize dashboard
                document.addEventListener('DOMContentLoaded', function() {
                    initializeDateRangeSelector();
                    // Set initial personal overview caption
                    updatePersonalOverviewCaption('this_week');
                    loadDashboardData();
                    initializeDailyQuotes();
                    initializeLeaveDetailsModal();
                    if (managerUserId) {
                        loadManagerMotivation();
                        window.addEventListener('motivationInsightsUpdated', function(event) {
                            if (event.detail && event.detail.userId === managerUserId) {
                                loadManagerMotivation();
                            }
                        });
                        setInterval(() => {
                            const updateKey = localStorage.getItem('motivation_insights_updated');
                            if (updateKey) {
                                const match = updateKey.match(/motivation_refresh_(\d+)_/);
                                if (match && parseInt(match[1], 10) === managerUserId) {
                                    loadManagerMotivation();
                                    localStorage.removeItem('motivation_insights_updated');
                                }
                            }
                        }, 1500);
                    }
                    
                    // Pagination is handled by goToLeaderboardPage function
                    
                    // Set up period button listeners for leaderboard
                    const periodButtons = document.querySelectorAll('.period-btn');
                    periodButtons.forEach(btn => {
                        btn.addEventListener('click', function() {
                            periodButtons.forEach(b => b.classList.remove('active'));
                            this.classList.add('active');
                            // Update current period and reload leaderboard data
                            currentLeaderboardPeriod = this.getAttribute('data-period');
                            loadLeaderboardData();
                        });
                    });
                    
                    // Load leaderboard data with default "Last Week" period
                    loadLeaderboardData();
                    
                    // Auto-refresh every 10 minutes
                    setInterval(() => loadDashboardData(), 600000);
                });

                // Track if this is the first load
                let isFirstLoad = true;

                // Loading overlay functions
                function showLoadingOverlay() {
                    // Remove any existing overlay first to prevent duplicates
                    const existingOverlay = document.getElementById('dashboardLoadingOverlay');
                    if (existingOverlay) {
                        existingOverlay.remove();
                    }
                    
                    const overlay = document.createElement('div');
                    overlay.id = 'dashboardLoadingOverlay';
                    overlay.innerHTML = `
                        <div class="loading-spinner">
                            <div class="spinner"></div>
                            <p>Loading dashboard...</p>
                        </div>
                    `;
                    document.body.appendChild(overlay);
                }

                function hideLoadingOverlay() {
                    const overlay = document.getElementById('dashboardLoadingOverlay');
                    if (overlay) {
                        overlay.style.opacity = '0';
                        setTimeout(() => overlay.remove(), 300);
                    }
                }

                // Load dashboard data
                async function loadDashboardData() {
                    try {
                        // Show loading overlay only on first load
                        if (isFirstLoad) {
                            showLoadingOverlay();
                        }
                        
                        let url = '../ajax/manager_dashboard_data.php';
                        const params = new URLSearchParams();
                        
                        if (currentDateRange.fromDate && currentDateRange.toDate) {
                            // Send calculated date range
                            params.append('date_from', currentDateRange.fromDate);
                            params.append('date_to', currentDateRange.toDate);
                            // Also send the range type for reference
                            params.append('date_range', currentDateRange.type);
                        } else {
                            // Fallback: calculate dates if not set
                            const dateRange = calculateWeekDateRange(currentDateRange.type);
                            if (dateRange.fromDate && dateRange.toDate) {
                                params.append('date_from', dateRange.fromDate);
                                params.append('date_to', dateRange.toDate);
                            }
                            params.append('date_range', currentDateRange.type);
                        }
                        
                        if (params.toString()) {
                            url += '?' + params.toString();
                        }
                        
                        const response = await fetch(url);
                        const result = await response.json();
                        
                        if (result.success) {
                            updateDashboard(result.data);
                        } else {
                            console.error('Failed to load dashboard data:', result.error);
                            hideLoadingOverlay();
                        }
                    } catch (error) {
                        console.error('Error loading dashboard data:', error);
                        hideLoadingOverlay();
                    }
                }

                // Update dashboard with data
                function updateDashboard(data) {
                    if (!data) {
                        console.error('No data received');
                        hideLoadingOverlay();
                        return;
                    }
                    
                    // Batch all updates together using requestAnimationFrame
                    requestAnimationFrame(() => {
                        // Update personal stats (instant on first load, animated on refresh)
                        if (data.personal_stats) {
                            const rqcScore = (data.personal_rqc_score !== undefined ? data.personal_rqc_score : data.personal_completion_rate);
                            console.log('RQC score from API:', rqcScore, 'personal_rqc_score:', data.personal_rqc_score, 'personal_completion_rate:', data.personal_completion_rate);
                            const numScore = parseFloat(rqcScore);
                            const validRqcScore = (!isNaN(numScore) && numScore > 0 && isFinite(numScore)) ? numScore : null;
                            console.log('Valid RQC score:', validRqcScore);
                            updatePersonalStats(data.personal_stats, validRqcScore, isFirstLoad);
                            // After first load, set isFirstLoad to false so subsequent updates are animated
                            if (isFirstLoad) {
                                isFirstLoad = false;
                            }
                        }
                        
                        // Leaderboard is updated separately via period buttons (This Week, This Month, Last Year)
                        // Store team member IDs but don't update leaderboard here
                        if (data.team_member_ids) {
                            teamMemberIds = data.team_member_ids || [];
                        }
                        
                        // Update team availability
                        if (data.team_availability && Array.isArray(data.team_availability)) {
                            updateTeamAvailability({ team_availability: data.team_availability });
                        }
                        
                        // Update recent tasks
                        if (data.recent_tasks && Array.isArray(data.recent_tasks)) {
                            updateRecentTasks(data.recent_tasks);
                        }
                        
                        // Show all sections with fade-in animation
                        requestAnimationFrame(() => {
                            const sections = document.querySelectorAll('.stats-section, .chart-section, .leaderboard-section, .motivation-section');
                            sections.forEach((section, index) => {
                                setTimeout(() => {
                                    section.classList.add('loaded');
                                }, index * 30); // Stagger by 30ms for smooth appearance
                            });
                            
                            // Hide loading overlay after all sections are visible
                            setTimeout(() => {
                                hideLoadingOverlay();
                                isFirstLoad = false; // Mark first load as complete
                            }, Math.max(300, sections.length * 30 + 100));
                        });
                    });
                }

                function loadManagerMotivation() {
                    const motivationContent = document.getElementById('managerMotivationContent');
                    if (!motivationContent || !managerUserId) {
                        return;
                    }

                    motivationContent.innerHTML = '<div class="loading-motivation"><i class="fas fa-spinner fa-spin"></i> Loading insights...</div>';

                    fetch(`../ajax/get_user_motivation.php?user_id=${managerUserId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                motivationContent.innerHTML = '<div class="insight-card"><div class="insight-text"><p style="color: var(--dark-text-muted);">Unable to load insights right now.</p></div></div>';
                                return;
                            }

                            const currentInsights = data.data.current_insights || '';
                            const areasOfImprovement = data.data.areas_of_improvement || '';

                            let html = '';
                            html += '<div class="insights-subsection">';
                            html += '<div class="subsection-title"><i class="fas fa-chart-line"></i> Current Insights</div>';
                            if (currentInsights.trim()) {
                                const insightLines = currentInsights.split('\n').filter(line => line.trim());
                                insightLines.forEach(insight => {
                                    html += '<div class="insight-card">';
                                    html += '<div class="insight-icon"><i class="fas fa-check-circle"></i></div>';
                                    html += '<div class="insight-text"><p>' + escapeHtml(insight.trim()) + '</p></div>';
                                    html += '</div>';
                                });
                            } else {
                                html += '<div class="insight-card">';
                                html += '<div class="insight-icon"><i class="fas fa-info-circle"></i></div>';
                                html += '<div class="insight-text"><p style="color: var(--dark-text-muted); font-style: italic;">No insights available yet. Add insights from the Admin dashboard.</p></div>';
                                html += '</div>';
                            }
                            html += '</div>';

                            html += '<div class="insights-subsection">';
                            html += '<div class="subsection-title"><i class="fas fa-target"></i> Areas of Improvement / Focus</div>';
                            if (areasOfImprovement.trim()) {
                                const improvementLines = areasOfImprovement.split('\n').filter(line => line.trim());
                                improvementLines.forEach(item => {
                                    html += '<div class="improvement-card">';
                                    html += '<div class="improvement-icon"><i class="fas fa-lightbulb"></i></div>';
                                    html += '<div class="improvement-text"><p>' + escapeHtml(item.trim()) + '</p></div>';
                                    html += '</div>';
                                });
                            } else {
                                html += '<div class="improvement-card">';
                                html += '<div class="improvement-icon"><i class="fas fa-info-circle"></i></div>';
                                html += '<div class="improvement-text"><p style="color: var(--dark-text-muted); font-style: italic;">No focus areas defined.</p></div>';
                                html += '</div>';
                            }
                            html += '</div>';

                            motivationContent.innerHTML = html;
                        })
                        .catch(error => {
                            console.error('Error loading motivation:', error);
                            motivationContent.innerHTML = '<div class="insight-card"><div class="insight-text"><p style="color: var(--dark-text-muted);">Error loading insights. Please refresh.</p></div></div>';
                        });
                }

                // Function to apply glow class based on WND value
                function applyWndGlow(statType, value) {
                    const card = document.querySelector(`.stat-card[data-stat="${statType}"]`);
                    if (!card) return;
                    
                    // Remove existing glow classes
                    card.classList.remove('orange-glow', 'red-glow');
                    
                    // Parse value to number
                    const numValue = parseFloat(value);
                    if (isNaN(numValue)) return;
                    
                    // Apply glow based on value
                    // If value > -10%: No glow (default GREY) - good/acceptable values
                    // If value is between -20.5% and -10.6% (inclusive): ORANGE glow - moderately bad
                    // If value ≤ -20.6%: RED glow - very bad (takes priority over ORANGE)
                    if (numValue <= -20.6) {
                        card.classList.add('red-glow');
                    } else if (numValue <= -10.6 && numValue >= -20.5) {
                        card.classList.add('orange-glow');
                    }
                    // Otherwise (value > -10%), no glow class (default GREY)
                }

                // Update personal stats
                function updatePersonalStats(stats, completionRate, instant = false) {
                    if (instant) {
                        // Instant update on first load (no animation)
                        const completedEl = document.querySelector('.stat-card.completed .stat-value');
                        const pendingEl = document.querySelector('.stat-card.pending .stat-value');
                        const wndEl = document.querySelector('.stat-card[data-stat="wnd"] .stat-value');
                        const wndOnTimeEl = document.querySelector('.stat-card[data-stat="wnd_on_time"] .stat-value');
                        
                        if (completedEl) completedEl.textContent = stats.completed_on_time || 0;
                        if (pendingEl) pendingEl.textContent = stats.current_pending || 0;
                        if (wndEl) {
                            const wndPercent = stats.wnd || 0;
                            wndEl.textContent = Math.round(wndPercent) + '%';
                            applyWndGlow('wnd', wndPercent);
                        }
                        if (wndOnTimeEl) {
                            const wndOnTimePercent = stats.wnd_on_time || 0;
                            wndOnTimeEl.textContent = Math.round(wndOnTimePercent) + '%';
                            applyWndGlow('wnd_on_time', wndOnTimePercent);
                        }
                    } else {
                        // Animated update on refresh
                        animateCounter('.stat-card.completed .stat-value', stats.completed_on_time);
                        animateCounter('.stat-card.pending .stat-value', stats.current_pending);
                        animateCounter('.stat-card[data-stat="wnd"] .stat-value', stats.wnd || 0, true);
                        animateCounter('.stat-card[data-stat="wnd_on_time"] .stat-value', stats.wnd_on_time || 0, true);
                        applyWndGlow('wnd', stats.wnd || 0);
                        applyWndGlow('wnd_on_time', stats.wnd_on_time || 0);
                    }
                }

                // Load leaderboard data based on selected time period
                async function loadLeaderboardData() {
                    try {
                        let dateFrom = null;
                        let dateTo = null;
                        
                        // Calculate week-based date ranges
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        const thisWeekMonday = getMondayOfWeek(today);
                        
                        if (currentLeaderboardPeriod === 'last_week') {
                            // Monday to Sunday of last week
                            const lastWeekMonday = new Date(thisWeekMonday);
                            lastWeekMonday.setDate(thisWeekMonday.getDate() - 7);
                            const lastWeekSunday = new Date(lastWeekMonday);
                            lastWeekSunday.setDate(lastWeekMonday.getDate() + 6);
                            dateFrom = lastWeekMonday.toISOString().split('T')[0];
                            dateTo = lastWeekSunday.toISOString().split('T')[0];
                        } else if (currentLeaderboardPeriod === 'last_2_weeks') {
                            // Monday of 2 weeks ago to Sunday of last week
                            const twoWeeksAgoMonday = new Date(thisWeekMonday);
                            twoWeeksAgoMonday.setDate(thisWeekMonday.getDate() - 14);
                            const lastWeekSunday = new Date(thisWeekMonday);
                            lastWeekSunday.setDate(thisWeekMonday.getDate() - 1);
                            dateFrom = twoWeeksAgoMonday.toISOString().split('T')[0];
                            dateTo = lastWeekSunday.toISOString().split('T')[0];
                        } else if (currentLeaderboardPeriod === 'last_4_weeks') {
                            // Monday of 4 weeks ago to Sunday of last week
                            const fourWeeksAgoMonday = new Date(thisWeekMonday);
                            fourWeeksAgoMonday.setDate(thisWeekMonday.getDate() - 28);
                            const lastWeekSunday = new Date(thisWeekMonday);
                            lastWeekSunday.setDate(thisWeekMonday.getDate() - 1);
                            dateFrom = fourWeeksAgoMonday.toISOString().split('T')[0];
                            dateTo = lastWeekSunday.toISOString().split('T')[0];
                        }
                        
                        let url = '../ajax/manager_dashboard_data.php';
                        const params = new URLSearchParams();
                        if (dateFrom) {
                            params.append('date_from', dateFrom);
                        }
                        if (dateTo) {
                            params.append('date_to', dateTo);
                        }
                        
                        if (params.toString()) {
                            url += '?' + params.toString();
                        }
                        
                        const response = await fetch(url);
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        
                        const responseText = await response.text();
                        if (responseText.trim().startsWith('<')) {
                            throw new Error('Server returned HTML instead of JSON. Check for PHP errors.');
                        }
                        
                        const result = JSON.parse(responseText);
                        
                        if (result.success && result.data && result.data.team_leaderboard) {
                            leaderboardData = result.data.team_leaderboard || [];
                            teamMemberIds = result.data.team_member_ids || [];
                            currentLeaderboardPage = 1; // Reset to first page
                            initializeLeaderboard();
                        } else {
                            console.error('Failed to load leaderboard data:', result);
                            // Show empty state
                            const list = document.getElementById('teamLeaderboardList');
                            if (list) {
                                list.innerHTML = '<div class="leaderboard-empty">No leaderboard data available</div>';
                            }
                        }
                    } catch (error) {
                        console.error('Error loading leaderboard data:', error);
                        // Show error state
                        const list = document.getElementById('teamLeaderboardList');
                        if (list) {
                            list.innerHTML = '<div class="leaderboard-empty">Error loading leaderboard data</div>';
                        }
                    }
                }

                // Initialize leaderboard (same as admin dashboard)
                function initializeLeaderboard() {
                    const list = document.getElementById('teamLeaderboardList');
                    if (!list) return;
                    
                    list.innerHTML = '';
                    
                    if (leaderboardData.length === 0) {
                        list.innerHTML = '<div class="leaderboard-empty">No leaderboard data available</div>';
                        return;
                    }
                    
                    // Get Top 3 performers
                    const top3 = leaderboardData.slice(0, 3);
                    
                    // Find current user
                    const currentUser = leaderboardData.find(user => user.is_current_user);
                    
                    // Build display data: Top 3 + current user (if not in Top 3)
                    let displayData = [...top3];
                    if (currentUser && currentUser.rank > 3) {
                        displayData.push(currentUser);
                    }
                    
                    if (displayData.length === 0) {
                        list.innerHTML = '<div class="leaderboard-empty">No leaderboard data available</div>';
                        return;
                    }
                    
                    // Helper function to get rank gradient class
                    function getRankGradientClass(rank) {
                        if (rank === 1) return 'rank-gold';
                        if (rank === 2) return 'rank-silver';
                        if (rank === 3) return 'rank-bronze';
                        return '';
                    }
                    
                    // Helper function to get user initials
                    function getUserInitials(name) {
                        if (!name) return '?';
                        const parts = name.trim().split(' ');
                        if (parts.length >= 2) {
                            return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
                        }
                        return name.substring(0, 2).toUpperCase();
                    }
                    
                    // Check if current user is Manager or Admin (passed from PHP)
                    const isManager = <?php echo isManager() ? 'true' : 'false'; ?>;
                    const isAdmin = <?php echo isAdmin() ? 'true' : 'false'; ?>;
                    
                    displayData.forEach((user, index) => {
                        const item = document.createElement('div');
                        const rankClass = getRankGradientClass(user.rank);
                        item.className = `leaderboard-item ${user.is_current_user ? 'current-user' : ''} ${rankClass}`;
                        item.style.animationDelay = `${index * 0.1}s`;
                        
                        // Get avatar emoji based on rank
                        let avatar = '-';
                        if (user.rank === 1) avatar = '🥇';
                        else if (user.rank === 2) avatar = '🥈';
                        else if (user.rank === 3) avatar = '🥉';
                        
                        // Get user initials for avatar fallback
                        const initials = getUserInitials(user.name);
                        
                        // Get Performance Rate (primary metric) or fallback to completion_rate for backward compatibility
                        const performanceRate = parseFloat(user.performance_rate) ?? parseFloat(user.completion_rate) ?? 0;
                        const rqcScoreRaw = parseFloat(user.rqc_score);
                        const rqcScore = (rqcScoreRaw && rqcScoreRaw > 0) ? rqcScoreRaw : null; // Show N/A if 0 or null
                        const wnd = parseFloat(user.wnd) || 0;
                        const wndOnTime = parseFloat(user.wnd_on_time) || 0;
                        
                        // Create tooltip content
                        const tooltipContent = `
                            <div class="leaderboard-tooltip-content">
                                <strong>${user.name}</strong><br>
                                <span>Rank: #${user.rank}</span><br>
                                <span>Performance Rate: ${performanceRate.toFixed(1)}%</span><br>
                                <span>RQC Score: ${rqcScore !== null ? rqcScore.toFixed(1) : 'N/A'}</span><br>
                                <span>WND: ${wnd.toFixed(1)}%</span><br>
                                <span>WND On-Time: ${wndOnTime.toFixed(1)}%</span><br>
                                <span>Tasks: ${user.completed_tasks || 0}/${user.total_tasks || 0}</span><br>
                                <span>User Type: ${user.user_type || 'N/A'}</span>
                            </div>
                        `;
                        
                        // Determine if entire item should be clickable
                        // Admin can click on anyone, Manager can only click on team members
                        let canClick = false;
                        if (isAdmin) {
                            canClick = true; // Admin can view anyone
                        } else if (isManager) {
                            // Manager can only view their team members (doers under them)
                            canClick = teamMemberIds.includes(user.id) && user.user_type === 'doer';
                        }
                        
                        // Make entire item clickable if allowed
                        if (canClick && user.username) {
                            item.style.cursor = 'pointer';
                            item.setAttribute('data-tooltip', 'true');
                            item.setAttribute('data-user-name', user.name);
                            item.setAttribute('data-user-rank', user.rank);
                            item.setAttribute('data-performance-rate', performanceRate);
                            item.setAttribute('data-rqc-score', rqcScore);
                            item.setAttribute('data-completed-tasks', user.completed_tasks || 0);
                            item.setAttribute('data-total-tasks', user.total_tasks || 0);
                            item.setAttribute('data-user-type', user.user_type || 'N/A');
                            
                            // Create tooltip element
                            const tooltip = document.createElement('div');
                            tooltip.className = 'leaderboard-tooltip';
                            tooltip.innerHTML = tooltipContent;
                            item.appendChild(tooltip);
                            
                            item.addEventListener('click', function(e) {
                                // Add click animation
                                item.classList.add('clicked');
                                setTimeout(() => {
                                    item.classList.remove('clicked');
                                    viewPerformanceForUser(user.username);
                                }, 200);
                            });
                        }
                        
                        // Progress ring calculation (radius = 33 for 75px wrapper)
                        // Use Performance Rate for the ring visualization
                        const ringRadius = 33;
                        const circumference = 2 * Math.PI * ringRadius;
                        const offset = circumference - (performanceRate / 100) * circumference;
                        
                        item.innerHTML = `
                            <div class="rank-badge ${rankClass}">
                                <span class="rank-number">${user.rank}</span>
                                <span class="rank-emoji">${avatar}</span>
                            </div>
                            <div class="user-info">
                                <div class="user-avatar-wrapper">
                                    <div class="user-avatar">
                                        <div class="avatar-initials" style="display: flex;">${initials}</div>
                                    </div>
                                </div>
                                <div class="user-details">
                                    <div class="user-name">${user.name}</div>
                                    <div class="user-scores">
                                        <span class="user-score performance-rate">
                                            <i class="fas fa-chart-line"></i> ${performanceRate.toFixed(1)}% Performance
                                        </span>
                                        
                                    </div>
                                    <div class="user-tasks">RQC Score: ${rqcScore !== null ? rqcScore.toFixed(1) : 'N/A'}</div>
                                </div>
                            </div>
                            <div class="performance-ring-wrapper">
                                <svg class="performance-ring" width="75" height="75">
                                    <circle class="ring-background" cx="37.5" cy="37.5" r="33" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="6"/>
                                    <circle class="ring-progress" cx="37.5" cy="37.5" r="33" fill="none" 
                                            stroke="url(#ringGradient${user.rank})" 
                                            stroke-width="6" 
                                            stroke-linecap="round"
                                            stroke-dasharray="${circumference}"
                                            stroke-dashoffset="${offset}"
                                            transform="rotate(-90 37.5 37.5)"/>
                                    <defs>
                                        <linearGradient id="ringGradient${user.rank}" x1="0%" y1="0%" x2="100%" y2="100%">
                                            ${user.rank === 1 ? `
                                            <stop offset="0%" style="stop-color:#FFD700;stop-opacity:1" />
                                            <stop offset="100%" style="stop-color:#FFA500;stop-opacity:1" />
                                            ` : user.rank === 2 ? `
                                            <stop offset="0%" style="stop-color:#C0C0C0;stop-opacity:1" />
                                            <stop offset="100%" style="stop-color:#808080;stop-opacity:1" />
                                            ` : user.rank === 3 ? `
                                            <stop offset="0%" style="stop-color:#CD7F32;stop-opacity:1" />
                                            <stop offset="100%" style="stop-color:#8B4513;stop-opacity:1" />
                                            ` : `
                                            <stop offset="0%" style="stop-color:#6366f1;stop-opacity:1" />
                                            <stop offset="100%" style="stop-color:#8b5cf6;stop-opacity:1" />
                                            `}
                                        </linearGradient>
                                    </defs>
                                    <text class="ring-text" x="37.5" y="42" text-anchor="middle" font-size="11" font-weight="600" fill="#fff">${performanceRate.toFixed(1)}%</text>
                                </svg>
                            </div>
                        `;
                        
                        list.appendChild(item);
                        
                        // Animate progress ring after a short delay
                        setTimeout(() => {
                            const ring = item.querySelector('.ring-progress');
                            if (ring) {
                                ring.style.transition = 'stroke-dashoffset 1.5s ease-out';
                            }
                        }, index * 100);
                    });
                    
                    // Hide pagination controls (no longer needed)
                    const paginationContainer = document.getElementById('leaderboardPagination');
                    if (paginationContainer) {
                        paginationContainer.innerHTML = '';
                    }
                }
                
                // View Performance functions
                function viewPerformance() {
                    // Directly open performance page - dropdown will be shown there
                    window.location.href = 'team_performance.php';
                }
                
                function viewPerformanceForUser(username) {
                    if (username) {
                        window.location.href = `team_performance.php?username=${encodeURIComponent(username)}`;
                    }
                }

                // Team Availability functionality - Shows both available and on-leave members
                function initializeTeamAvailability() {
                    // Team availability will be initialized from AJAX data
                    // This function is called on page load if we have initial data
                }

                function populateTeamGrid(teamMembers) {
                    const teamGrid = document.getElementById('teamGrid');
                    if (!teamGrid) return;
                    
                    teamGrid.innerHTML = '';
                    
                    teamMembers.forEach((member, index) => {
                        const memberElement = document.createElement('div');
                        
                        // Use helper function to determine status class
                        const statusClass = getDisplayStatus(member);
                        
                        memberElement.className = `team-member ${statusClass}`;
                        memberElement.style.animationDelay = `${index * 0.1}s`;
                        
                        // Make clickable if on leave or WFH (to show leave details)
                        if (statusClass === 'on-leave' || statusClass === 'remote') {
                            memberElement.style.cursor = 'pointer';
                            // Store member data for modal
                            memberElement.dataset.memberId = member.id;
                            memberElement.dataset.memberName = member.name;
                            memberElement.dataset.leaveType = member.leave_type || '';
                            memberElement.dataset.duration = member.duration || '';
                            memberElement.dataset.startDate = member.start_date || '';
                            memberElement.dataset.endDate = member.end_date || '';
                            memberElement.dataset.leaveStatus = member.leave_status || '';
                            
                            // Add click event to show leave details
                            memberElement.addEventListener('click', function() {
                                showLeaveDetails(member);
                            });
                        } else {
                            memberElement.style.cursor = 'default';
                        }
                        
                        // Get first letter of name for avatar
                        const firstLetter = member.name.split(' ')[0].charAt(0);
                        
                        memberElement.innerHTML = `
                            <div class="member-avatar">
                                <span style="display: flex;">${firstLetter}</span>
                            </div>
                            <div class="member-name">${member.name}</div>
                            <div class="member-status ${statusClass}"></div>
                        `;
                        
                        teamGrid.appendChild(memberElement);
                    });
                }

                function updateAvailabilityStats(teamMembers) {
                    // Count based on display status (using helper function)
                    let availableCount = 0;
                    let onWfhCount = 0;
                    let onLeaveCount = 0;
                    
                    teamMembers.forEach(member => {
                        const displayStatus = getDisplayStatus(member);
                        if (displayStatus === 'available') {
                            availableCount++;
                        } else if (displayStatus === 'remote') {
                            onWfhCount++;
                        } else if (displayStatus === 'on-leave') {
                            onLeaveCount++;
                        }
                    });
                    
                    const availableCountElement = document.getElementById('availableCount');
                    const onWfhCountElement = document.getElementById('onWfhCount');
                    const onLeaveCountElement = document.getElementById('onLeaveCount');
                    
                    if (availableCountElement) {
                        availableCountElement.textContent = availableCount;
                    }
                    
                    if (onWfhCountElement) {
                        onWfhCountElement.textContent = onWfhCount;
                    }
                    
                    if (onLeaveCountElement) {
                        onLeaveCountElement.textContent = onLeaveCount;
                    }
                }

                // Helper function to get display status based on leave_status and duration
                function getDisplayStatus(member) {
                    const leaveStatus = (member.leave_status || '').toLowerCase();
                    const duration = (member.duration || '').toLowerCase();
                    const leaveType = (member.leave_type || '').toLowerCase();
                    const hasLeaveRequest = member.leave_status || member.duration || member.leave_type;
                    
                    if (!hasLeaveRequest) {
                        return 'available'; // No leave request = available
                    }
                    
                    if (leaveStatus === 'pending') {
                        return 'available'; // Pending leave = show as available (green)
                    }
                    
                    if (leaveStatus === 'approve' || leaveStatus === 'approved') {
                        // Check if it's WFH (Full Day WFH or Half Day WFH)
                        if (duration.includes('wfh') || leaveType.includes('wfh') || 
                            duration.includes('work from home') || leaveType.includes('work from home')) {
                            return 'remote'; // Blue for WFH
                        }
                        // Check if it's Leave (Full Day Leave, Half Day Leave, or Short Leave)
                        else if (duration.includes('leave') || leaveType.includes('leave') || 
                                 duration.includes('short leave') || leaveType.includes('short leave')) {
                            return 'on-leave'; // Red for Leave
                        }
                    }
                    
                    return 'available'; // Default fallback
                }

                // Function to update team availability with new data
                function updateTeamAvailability(data) {
                    if (data && Array.isArray(data.team_availability)) {
                        const teamMembers = data.team_availability;
                        
                        // Sort team members: on-leave (red) first, then remote (blue/WFH), then available (green), then by name
                        const sortedTeam = teamMembers.sort((a, b) => {
                            const statusA = getDisplayStatus(a);
                            const statusB = getDisplayStatus(b);
                            
                            // Priority order: on-leave (0) > remote (1) > available (2)
                            const priority = { 'on-leave': 0, 'remote': 1, 'available': 2 };
                            const priorityA = priority[statusA] ?? 3;
                            const priorityB = priority[statusB] ?? 3;
                            
                            if (priorityA !== priorityB) {
                                return priorityA - priorityB;
                            }
                            
                            // If same priority, sort by name
                            return a.name.localeCompare(b.name);
                        });
                        
                        // Populate team grid
                        populateTeamGrid(sortedTeam);
                        
                        // Update availability stats
                        updateAvailabilityStats(sortedTeam);
                    }
                }

                function escapeHtml(text) {
                    if (text === undefined || text === null) {
                        return '';
                    }
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }

                // Update recent tasks
                function updateRecentTasks(tasks) {
                    const tbody = document.getElementById('recentTasksTable');
                    tbody.innerHTML = '';
                    
                    if (tasks.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center">No recent tasks</td></tr>';
                        return;
                    }
                    
                    tasks.forEach(task => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${task.unique_id || task.id}</td>
                            <td>${task.description ? (task.description.length > 50 ? task.description.substring(0, 50) + '...' : task.description) : 'N/A'}</td>
                            <td>${task.doer_name || 'N/A'}</td>
                            <td>${task.department_name || 'N/A'}</td>
                            <td>${task.planned_date || 'N/A'}</td>
                            <td>${getStatusBadge(task.status || 'pending')}</td>
                        `;
                        tbody.appendChild(row);
                    });
                }

                // Special function to update personal RQC score (handles N/A)
                function updatePersonalRqcScore(element, rqcScore) {
                    if (!element) return;
                    
                    // Debug logging
                    console.log('updatePersonalRqcScore called with:', rqcScore, 'type:', typeof rqcScore);
                    
                    // Handle null, undefined, empty string
                    if (rqcScore === null || rqcScore === undefined || rqcScore === '') {
                        element.setAttribute('data-is-na', 'true');
                        element.textContent = 'N/A';
                        return;
                    }
                    
                    const numScore = parseFloat(rqcScore);
                    if (!isNaN(numScore) && numScore > 0 && isFinite(numScore)) {
                        // Valid RQC score - update value (rounded, no decimals)
                        element.setAttribute('data-is-na', 'false');
                        animateCounter('.stat-card.em-score .stat-value', numScore, true);
                    } else {
                        // No RQC score - show N/A
                        console.log('RQC score is invalid:', numScore, 'original:', rqcScore);
                        element.setAttribute('data-is-na', 'true');
                        element.textContent = 'N/A';
                    }
                }

                // Helper functions
                // Store active timers to clear them when needed
                const activeTimers = new Map();

                function animateCounter(selector, targetValue, isPercentage = false) {
                    const element = document.querySelector(selector);
                    if (!element) return;
                    
                    // Clear any existing animation for this element
                    if (activeTimers.has(selector)) {
                        clearInterval(activeTimers.get(selector));
                        activeTimers.delete(selector);
                    }
                    
                    // Skip animation if element is marked as N/A
                    if (element.getAttribute('data-is-na') === 'true') {
                        return;
                    }
                    
                    // Skip if current value is "N/A"
                    const currentText = element.textContent.trim();
                    if (currentText === 'N/A') {
                        // If we have a valid target value, update it
                        const targetNum = parseFloat(targetValue);
                        if (!isNaN(targetNum) && targetNum > 0 && isFinite(targetNum)) {
                            element.setAttribute('data-is-na', 'false');
                        } else {
                            return; // Keep N/A if no valid target
                        }
                    }
                    
                    // Parse current value (remove % sign if present, handle N/A)
                    // Read from data-target attribute if available, otherwise parse from text
                    let currentValue = 0;
                    const dataTarget = element.getAttribute('data-target');
                    if (dataTarget && dataTarget !== 'N/A' && !isNaN(parseFloat(dataTarget))) {
                        currentValue = parseFloat(dataTarget);
                    } else {
                        currentValue = parseFloat(currentText.replace('%', '').replace('N/A', '0')) || 0;
                    }
                    
                    if (isNaN(currentValue)) {
                        currentValue = 0;
                    }
                    
                    // Ensure targetValue is a valid number
                    if (targetValue === null || targetValue === undefined) {
                        if (isPercentage) {
                            element.setAttribute('data-is-na', 'true');
                            element.textContent = 'N/A';
                        }
                        return;
                    }
                    
                    // Convert to number if it's a string
                    const targetNum = parseFloat(targetValue);
                    if (isNaN(targetNum) || !isFinite(targetNum)) {
                        if (isPercentage) {
                            element.setAttribute('data-is-na', 'true');
                            element.textContent = 'N/A';
                        }
                        return;
                    }
                    
                    // Use the parsed number
                    targetValue = targetNum;
                    
                    // Update data-target attribute with new target value
                    element.setAttribute('data-target', targetValue);
                    
                    // For percentages, allow negative values (WND and WND on-time can be negative)
                    if (!isPercentage) {
                        // For non-percentages (counts), values must be >= 0
                        if (targetValue < 0) {
                            return;
                        }
                    }
                    
                    // If current value equals target, just set it directly (no animation needed)
                    if (Math.abs(currentValue - targetValue) < 0.01) {
                        if (isPercentage) {
                            element.textContent = Math.round(targetValue) + '%';
                        } else {
                            element.textContent = Math.round(targetValue);
                        }
                        // Apply glow for WND and WND_On_Time even when no animation is needed
                        const card = element.closest('.stat-card');
                        if (card) {
                            const statType = card.getAttribute('data-stat');
                            if (statType === 'wnd' || statType === 'wnd_on_time') {
                                applyWndGlow(statType, targetValue);
                            }
                        }
                        return;
                    }
                    
                    const increment = (targetValue - currentValue) / 30;
                    let current = currentValue;
                    
                    const timer = setInterval(() => {
                        current += increment;
                        if ((increment > 0 && current >= targetValue) || (increment < 0 && current <= targetValue)) {
                            current = targetValue;
                            clearInterval(timer);
                            activeTimers.delete(selector);
                            
                            // Apply glow for WND and WND_On_Time when animation completes
                            const card = element.closest('.stat-card');
                            if (card) {
                                const statType = card.getAttribute('data-stat');
                                if (statType === 'wnd' || statType === 'wnd_on_time') {
                                    applyWndGlow(statType, targetValue);
                                }
                            }
                        }
                        
                        if (isPercentage) {
                            // Format as percentage with rounded value (no decimals)
                            element.textContent = Math.round(current) + '%';
                        } else {
                            // Format as integer count
                            element.textContent = Math.round(current);
                        }
                    }, 50);
                    
                    // Store the timer so we can clear it later
                    activeTimers.set(selector, timer);
                }
                
                // Apply initial glow on page load
                document.addEventListener('DOMContentLoaded', function() {
                    // Get initial WND values from data-target attributes
                    const wndElement = document.querySelector('.stat-card[data-stat="wnd"] .stat-value');
                    const wndOnTimeElement = document.querySelector('.stat-card[data-stat="wnd_on_time"] .stat-value');
                    
                    if (wndElement) {
                        const wndValue = parseFloat(wndElement.getAttribute('data-target') || wndElement.textContent.replace('%', ''));
                        if (!isNaN(wndValue)) {
                            applyWndGlow('wnd', wndValue);
                        }
                    }
                    
                    if (wndOnTimeElement) {
                        const wndOnTimeValue = parseFloat(wndOnTimeElement.getAttribute('data-target') || wndOnTimeElement.textContent.replace('%', ''));
                        if (!isNaN(wndOnTimeValue)) {
                            applyWndGlow('wnd_on_time', wndOnTimeValue);
                        }
                    }
                });

                function getStatusBadge(status) {
                    const statusLower = status.toLowerCase();
                    let badgeClass = 'badge-secondary';
                    let displayText = status;
                    let customStyle = '';
                    
                    if (statusLower === 'completed' || statusLower === 'done') {
                        // Green gradient matching personal stats
                        customStyle = 'background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: white; border: none;';
                        displayText = 'Completed';
                    } else if (statusLower === 'shifted') {
                        badgeClass = 'badge-info';
                        displayText = 'Shifted';
                    } else if (statusLower === 'pending') {
                        // Yellow/Golden Amber gradient matching personal stats
                        customStyle = 'background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%); color: white; border: none;';
                        displayText = 'Pending';
                    } else if (statusLower === 'delayed') {
                        badgeClass = 'badge-danger';
                        displayText = 'Delayed';
                    } else if (statusLower === 'not done' || statusLower === 'not_done') {
                        // Maroon Red gradient
                        customStyle = 'background: linear-gradient(135deg, #991b1b 0%, #7f1d1d 100%); color: white; border: none;';
                        displayText = 'Not Done';
                    } else if (statusLower === 'can not be done' || statusLower === 'cant_be_done' || statusLower === 'cannot be done') {
                        // Charcoal grey gradient
                        customStyle = 'background: linear-gradient(135deg, #37474F 0%, #455A64 100%); color: white; border: none;';
                        displayText = 'Can\'t be done';
                    }
                    
                    if (customStyle) {
                        return `<span class="badge" style="${customStyle}">${displayText}</span>`;
                    }
                    return `<span class="badge ${badgeClass}">${displayText}</span>`;
                }

                // Update filter context text based on date range - replaces "Personal Overview" with range text
                function updateFilterContextText(rangeType) {
                    // Keep title static - "Personal Overview" always stays the same
                    // This function is kept for backward compatibility but no longer changes the title
                }

                // Update personal overview caption based on date range
                function updatePersonalOverviewCaption(range, fromDate = null, toDate = null) {
                    const captionElement = document.getElementById('personalOverviewCaption');
                    if (!captionElement) return;
                    
                    let caption = '';
                    switch(range) {
                        case 'this_week':
                            caption = 'This Week Overview';
                            break;
                        case 'last_week':
                            caption = 'Last Week Overview';
                            break;
                        case 'last_2_weeks':
                            caption = 'Last 2 Weeks Overview';
                            break;
                        case 'last_4_weeks':
                            caption = 'Last 4 Weeks Overview';
                            break;
                        case 'custom':
                            caption = 'Custom Range Overview';
                            break;
                        default:
                            caption = 'This Week Overview';
                    }
                    
                    captionElement.textContent = caption;
                }
                
                // Date range selector
                function initializeDateRangeSelector() {
                    // Handle main date range buttons (This Week, Last Week)
                    document.querySelectorAll('.date-range-btn[data-range]').forEach(btn => {
                        if (!btn.classList.contains('dropdown-toggle')) {
                            btn.addEventListener('click', function() {
                                const range = this.getAttribute('data-range');
                                
                                // Remove active class from all buttons
                                document.querySelectorAll('.date-range-btn[data-range]').forEach(b => {
                                    if (!b.classList.contains('dropdown-toggle')) {
                                        b.classList.remove('active');
                                    }
                                });
                                
                                // Add active class to clicked button
                                this.classList.add('active');
                                
                                // Close dropdown if open
                                const dropdownMenu = document.getElementById('personalDateRangeDropdownMenu');
                                if (dropdownMenu) {
                                    dropdownMenu.style.display = 'none';
                                }
                                
                                // Update current date range
                                currentDateRange.type = range;
                                const dateRange = calculateWeekDateRange(range);
                                currentDateRange.fromDate = dateRange.fromDate;
                                currentDateRange.toDate = dateRange.toDate;
                                
                                // Update caption
                                updatePersonalOverviewCaption(range);
                                
                                // Reload dashboard data
                                loadDashboardData();
                            });
                        }
                    });
                    
                    // Handle dropdown toggle button
                    const dropdownBtn = document.getElementById('personalDateRangeDropdownBtn');
                    const dropdownMenu = document.getElementById('personalDateRangeDropdownMenu');
                    
                    if (dropdownBtn && dropdownMenu) {
                        dropdownBtn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            const isVisible = dropdownMenu.style.display !== 'none';
                            dropdownMenu.style.display = isVisible ? 'none' : 'block';
                        });
                        
                        // Close dropdown when clicking outside
                        document.addEventListener('click', function(e) {
                            if (!dropdownMenu.contains(e.target) && !dropdownBtn.contains(e.target)) {
                                dropdownMenu.style.display = 'none';
                            }
                        });
                    }
                    
                    // Handle dropdown menu items
                    if (dropdownMenu) {
                        dropdownMenu.querySelectorAll('.date-range-dropdown-item').forEach(item => {
                            item.addEventListener('click', function() {
                                const range = this.getAttribute('data-range');
                                
                                // Remove active class from all main buttons
                                document.querySelectorAll('.date-range-btn[data-range]').forEach(b => {
                                    if (!b.classList.contains('dropdown-toggle')) {
                                        b.classList.remove('active');
                                    }
                                });
                                
                                // Update current date range
                                currentDateRange.type = range;
                                const dateRange = calculateWeekDateRange(range);
                                currentDateRange.fromDate = dateRange.fromDate;
                                currentDateRange.toDate = dateRange.toDate;
                                
                                // Update caption
                                updatePersonalOverviewCaption(range);
                                
                                // Close dropdown
                                dropdownMenu.style.display = 'none';
                                
                                // Reload dashboard data
                                loadDashboardData();
                            });
                        });
                    }
                    
                    const customDateBtn = document.getElementById('customDateBtn');
                    const customDatePicker = document.getElementById('customDatePicker');
                    if (customDateBtn && customDatePicker) {
                        customDateBtn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            customDatePicker.style.display = customDatePicker.style.display === 'none' ? 'block' : 'none';
                        });
                    }
                    
                    const applyCustomDate = document.getElementById('applyCustomDate');
                    if (applyCustomDate) {
                        applyCustomDate.addEventListener('click', function() {
                            const dateFrom = document.getElementById('dateFrom').value;
                            const dateTo = document.getElementById('dateTo').value;
                            
                            if (!dateFrom || !dateTo) {
                                alert('Please select both from and to dates.');
                                return;
                            }
                            
                            if (new Date(dateFrom) > new Date(dateTo)) {
                                alert('From date cannot be greater than To date.');
                                return;
                            }
                            
                            document.querySelectorAll('.date-range-btn[data-range]').forEach(b => b.classList.remove('active'));
                            
                            currentDateRange.type = 'custom';
                            currentDateRange.fromDate = dateFrom;
                            currentDateRange.toDate = dateTo;
                            
                            // Update caption
                            updatePersonalOverviewCaption('custom');
                            
                            customDatePicker.style.display = 'none';
                            loadDashboardData();
                        });
                    }
                }

                // Daily quotes (reuse from doer dashboard)
                const dailyQuotes = [
                    { quote: "Leadership is not about being in charge. It's about taking care of those in your charge.", author: "Simon Sinek" },
                    { quote: "The best leaders are those most interested in surrounding themselves with assistants and associates stronger than they are.", author: "John C. Maxwell" },
                    { quote: "A leader is one who knows the way, goes the way, and shows the way.", author: "John C. Maxwell" }
                ];

                function initializeDailyQuotes() {
                    const today = new Date();
                    const dayOfYear = Math.floor((today - new Date(today.getFullYear(), 0, 0)) / (1000 * 60 * 60 * 24));
                    const quoteIndex = dayOfYear % dailyQuotes.length;
                    const selectedQuote = dailyQuotes[quoteIndex];
                    
                    const quoteElement = document.getElementById('dailyQuote');
                    const authorElement = document.getElementById('quoteAuthor');
                    
                    if (quoteElement && authorElement) {
                        quoteElement.textContent = selectedQuote.quote;
                        authorElement.textContent = `— ${selectedQuote.author}`;
                    }
                }

                // Leave details modal (same as doer dashboard)
                function initializeLeaveDetailsModal() {
                    const modal = document.getElementById('leaveDetailsModal');
                    const overlay = document.getElementById('leaveDetailsOverlay');
                    const closeBtn = document.getElementById('leaveDetailsClose');
                    
                    if (overlay) {
                        overlay.addEventListener('click', hideLeaveDetails);
                    }
                    
                    if (closeBtn) {
                        closeBtn.addEventListener('click', hideLeaveDetails);
                    }
                    
                    // Close on Escape key
                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape' && modal && modal.classList.contains('show')) {
                            hideLeaveDetails();
                        }
                    });
                }

                // Function to show leave details modal
                function showLeaveDetails(member) {
                    const modal = document.getElementById('leaveDetailsModal');
                    const memberName = document.getElementById('leaveDetailsMemberName');
                    const leaveType = document.getElementById('leaveDetailsType');
                    const duration = document.getElementById('leaveDetailsDuration');
                    const startDate = document.getElementById('leaveDetailsStartDate');
                    const endDate = document.getElementById('leaveDetailsEndDate');
                    const days = document.getElementById('leaveDetailsDays');
                    const status = document.getElementById('leaveDetailsStatus');
                    
                    if (!modal) return;
                    
                    // Populate modal with member data
                    memberName.textContent = member.name + "'s Leave Details";
                    leaveType.textContent = member.leave_type || '-';
                    duration.textContent = member.duration || '-';
                    startDate.textContent = formatDate(member.start_date);
                    endDate.textContent = member.end_date ? formatDate(member.end_date) : formatDate(member.start_date);
                    
                    // Calculate number of days
                    const numDays = calculateDays(member.start_date, member.end_date);
                    days.textContent = numDays + (numDays === 1 ? ' day' : ' days');
                    
                    // Set status badge
                    const statusText = member.leave_status || 'PENDING';
                    status.textContent = statusText;
                    status.className = 'leave-status-badge ' + (statusText.toLowerCase() === 'approve' ? 'status-approved' : 'status-pending');
                    
                    // Show modal
                    modal.classList.add('show');
                    // Don't prevent body scrolling since modal is contained within section
                }

                // Function to hide leave details modal
                function hideLeaveDetails() {
                    const modal = document.getElementById('leaveDetailsModal');
                    if (modal) {
                        modal.classList.remove('show');
                    }
                }

                // Function to format date for display
                function formatDate(dateString) {
                    if (!dateString) return '-';
                    const date = new Date(dateString);
                    return date.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric' 
                    });
                }

                // Function to calculate number of days between two dates
                function calculateDays(startDate, endDate) {
                    if (!startDate) return 0;
                    
                    const start = new Date(startDate);
                    const end = endDate ? new Date(endDate) : new Date(startDate);
                    
                    // Calculate difference in milliseconds
                    const diffTime = Math.abs(end - start);
                    // Convert to days (add 1 to include both start and end date)
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                    
                    return diffDays;
                }
                </script>
