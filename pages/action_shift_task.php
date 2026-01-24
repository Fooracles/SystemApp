<?php
require_once "../includes/config.php";
require_once "../includes/functions.php";

// Check if the user is logged in
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

// Initialize response array
$response = [
    'status' => 'error',
    'message' => 'Invalid request'
];

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get task ID and new planned date/time
    $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
    $new_planned_date = isset($_POST['new_planned_date']) ? $_POST['new_planned_date'] : '';
    $new_planned_time = isset($_POST['new_planned_time']) ? $_POST['new_planned_time'] : '';
    
    // Validate inputs
    if ($task_id <= 0 || empty($new_planned_date) || empty($new_planned_time)) {
        $response['message'] = 'Missing required parameters';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Get current task details
    $sql_get_task = "SELECT * FROM tasks WHERE id = ?";
    if ($stmt_get = mysqli_prepare($conn, $sql_get_task)) {
        mysqli_stmt_bind_param($stmt_get, "i", $task_id);
        if (mysqli_stmt_execute($stmt_get)) {
            $result_task = mysqli_stmt_get_result($stmt_get);
            if ($task = mysqli_fetch_assoc($result_task)) {
                // Task found, proceed with shifting logic
                
                // Determine if the new date is within the same week as the task's original planned date
                // Get the Monday and Sunday of the original task's planned date week
                $original_planned_date = $task['planned_date'];
                
                // Calculate the start (Monday) and end (Sunday) of the week for the original planned date
                $timestamp = strtotime($original_planned_date);
                $day_of_week = date('N', $timestamp); // 1 (Monday) to 7 (Sunday)
                
                // Calculate days to Monday (start of week)
                $days_to_monday = $day_of_week - 1;
                $task_week_start = date('Y-m-d', strtotime("-{$days_to_monday} days", $timestamp));
                
                // Calculate days to Sunday (end of week)
                $days_to_sunday = 7 - $day_of_week;
                $task_week_end = date('Y-m-d', strtotime("+{$days_to_sunday} days", $timestamp));
                
                // Check if the new date falls within the same Monday-Sunday week
                $is_within_same_week = ($new_planned_date >= $task_week_start && $new_planned_date <= $task_week_end);
                
                // Add debug information to the response
                $response['debug'] = [
                    'original_planned_date' => $original_planned_date,
                    'task_week_start' => $task_week_start,
                    'task_week_end' => $task_week_end,
                    'new_planned_date' => $new_planned_date,
                    'is_within_same_week' => $is_within_same_week ? 'true' : 'false'
                ];
                
                if ($is_within_same_week) {
                    // Update the current task's planned date and increment shifted_count by 1
                    $sql_update = "UPDATE tasks SET planned_date = ?, planned_time = ?, status = 'shifted', shifted_count = shifted_count + 1 WHERE id = ?";
                    if ($stmt_update = mysqli_prepare($conn, $sql_update)) {
                        mysqli_stmt_bind_param($stmt_update, "ssi", $new_planned_date, $new_planned_time, $task_id);
                        if (mysqli_stmt_execute($stmt_update)) {
                            $response['status'] = 'success';
                            $response['message'] = 'Task shifted successfully to new date/time within the same week.';
                            $response['shifted_count'] = ($task['shifted_count'] ?? 0) + 1;
                            $response['task_id'] = $task_id;
                            $response['new_planned_date'] = $new_planned_date;
                            $response['new_planned_time'] = $new_planned_time;
                        } else {
                            $response['message'] = 'Failed to update task: ' . mysqli_stmt_error($stmt_update);
                        }
                        mysqli_stmt_close($stmt_update);
                    } else {
                        $response['message'] = 'Failed to prepare update statement: ' . mysqli_error($conn);
                    }
                } else {
                    // Create a new task for the next week and update the original task
                    
                    // Start a transaction
                    mysqli_begin_transaction($conn);
                    try {
                        // 1. Update the original task
                        // Set timezone to ensure correct time
                        date_default_timezone_set('Asia/Kolkata'); // Use your local timezone
                        
                        // Get current date and time separately to ensure accuracy
                        $current_date = date('Y-m-d');
                        $current_time = date('H:i:s');
                        
                        $sql_update_original = "UPDATE tasks SET status = 'shifted', actual_date = ?, actual_time = ?, shifted_count = shifted_count + 2 WHERE id = ?";
                        $stmt_update_original = mysqli_prepare($conn, $sql_update_original);
                        mysqli_stmt_bind_param($stmt_update_original, "ssi", $current_date, $current_time, $task_id);
                        mysqli_stmt_execute($stmt_update_original);
                        
                        // 2. Create a new task
                        $new_unique_id = generateUniqueId();
                        
                        // Get the username for the doer to store in the new task
                        $doer_username = "";
                        $get_username_sql = "SELECT username FROM users WHERE id = ?";
                        if($get_username_stmt = mysqli_prepare($conn, $get_username_sql)) {
                            mysqli_stmt_bind_param($get_username_stmt, "i", $task['doer_id']);
                            mysqli_stmt_execute($get_username_stmt);
                            $username_result = mysqli_stmt_get_result($get_username_stmt);
                            if($username_row = mysqli_fetch_assoc($username_result)) {
                                $doer_username = $username_row['username'];
                            }
                            mysqli_stmt_close($get_username_stmt);
                        }
                        
                        $sql_insert = "INSERT INTO tasks (unique_id, description, planned_date, planned_time, duration, doer_id, doer_name, manager_id, assigned_by, department_id) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt_insert = mysqli_prepare($conn, $sql_insert);
                        mysqli_stmt_bind_param(
                            $stmt_insert, 
                            "ssssdisisi", 
                            $new_unique_id, 
                            $task['description'], 
                            $new_planned_date, 
                            $new_planned_time, 
                            $task['duration'], 
                            $task['doer_id'], 
                            $doer_username, 
                            $task['manager_id'], 
                            $task['assigned_by'], 
                            $task['department_id']
                        );
                        mysqli_stmt_execute($stmt_insert);
                        
                        // Commit the transaction
                        mysqli_commit($conn);
                        
                        $response['status'] = 'success';
                        $response['message'] = 'Task shifted to a different week. A new task has been created.';
                        $response['shifted_count'] = ($task['shifted_count'] ?? 0) + 2;
                        $response['original_task_id'] = $task_id;
                        $response['new_task_id'] = $new_unique_id;
                        $response['new_planned_date'] = $new_planned_date;
                        $response['new_planned_time'] = $new_planned_time;
                    } catch (Exception $e) {
                        // Roll back the transaction in case of error
                        mysqli_rollback($conn);
                        $response['message'] = 'Transaction failed: ' . $e->getMessage();
                    }
                }
            } else {
                $response['message'] = 'Task not found';
            }
        } else {
            $response['message'] = 'Failed to execute query: ' . mysqli_stmt_error($stmt_get);
        }
        mysqli_stmt_close($stmt_get);
    } else {
        $response['message'] = 'Failed to prepare statement: ' . mysqli_error($conn);
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?> 