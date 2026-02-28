<?php
// pages/action_doer_complete_task.php
require_once '../includes/config.php';
require_once '../includes/functions.php'; // For calculateCompletionDelay, formatSecondsToHHMMSS etc.

session_start();

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

// Check if user is logged in and is a Doer
if (!isLoggedIn()) {
    $response['message'] = 'Authentication required. Please log in.';
    echo json_encode($response);
    exit;
}

if (!isDoer()) {
    $response['message'] = 'Authorization denied. This action is for Doers only.';
    echo json_encode($response);
    exit;
}

if (!isset($conn)) {
    $response['message'] = 'Database connection failed.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id']) && isset($_POST['task_type']) && $_POST['status'] === 'completed') {
    $task_id = (int)$_POST['task_id'];
    $task_type = $_POST['task_type'];
    $new_status = 'completed'; // Explicitly set for this action script

    $current_doer_id = $_SESSION['id'];
    $current_doer_name = $_SESSION['username']; // Used for checklist assignee matching

    if ($task_type === 'delegation') {
        $sql_get_task = "SELECT planned_date, planned_time FROM tasks WHERE id = ? AND doer_id = ?";
        $stmt_get_task = mysqli_prepare($conn, $sql_get_task);
        if ($stmt_get_task) {
            mysqli_stmt_bind_param($stmt_get_task, "ii", $task_id, $current_doer_id);
            mysqli_stmt_execute($stmt_get_task);
            $result_get_task = mysqli_stmt_get_result($stmt_get_task);
            $task_details = mysqli_fetch_assoc($result_get_task);
            mysqli_stmt_close($stmt_get_task);

            if ($task_details) {
                $actual_date_val = date('Y-m-d');
                $actual_time_val = date('H:i:s');
                $is_delayed_val = 0;
                $delay_duration_val = '00:00:00'; // Default for on-time

                if (!empty($task_details['planned_date']) && !empty($task_details['planned_time'])) {
                    $delay_info = calculateCompletionDelay($task_details['planned_date'], $task_details['planned_time'], $actual_date_val, $actual_time_val);
                    if ($delay_info && $delay_info !== 'On Time' && $delay_info !== '00:00:00') {
                        $is_delayed_val = 1;
                        $delay_duration_val = $delay_info;
                    }
                }
                
                $sql_update = "UPDATE tasks SET status = ?, actual_date = ?, actual_time = ?, is_delayed = ?, delay_duration = ? WHERE id = ? AND doer_id = ?";
                if ($stmt_update = mysqli_prepare($conn, $sql_update)) {
                    mysqli_stmt_bind_param($stmt_update, "sssisii", $new_status, $actual_date_val, $actual_time_val, $is_delayed_val, $delay_duration_val, $task_id, $current_doer_id);
                    if (mysqli_stmt_execute($stmt_update)) {
                        if (mysqli_stmt_affected_rows($stmt_update) > 0) {
                            $response['status'] = 'success';
                            $response['message'] = 'Delegation task marked as completed.';
                        } else {
                            $response['message'] = 'Could not update delegation task. It might have been already updated or you do not own this task.';
                        }
                    } else {
                        $response['message'] = 'Error executing delegation task update: ' . mysqli_stmt_error($stmt_update);
                    }
                    mysqli_stmt_close($stmt_update);
                } else {
                    $response['message'] = 'DB error preparing delegation task update: ' . mysqli_error($conn);
                }
            } else {
                $response['message'] = 'Delegation task not found or not assigned to you.';
            }
        } else {
             $response['message'] = 'DB error preparing to fetch delegation task: ' . mysqli_error($conn);
        }

    } elseif ($task_type === 'checklist') {
        $sql_get_task = "SELECT task_date FROM checklist_subtasks WHERE id = ? AND LOWER(TRIM(assignee)) = LOWER(TRIM(?))";
        $stmt_get_task = mysqli_prepare($conn, $sql_get_task);
        if($stmt_get_task){
            mysqli_stmt_bind_param($stmt_get_task, "is", $task_id, $current_doer_name);
            mysqli_stmt_execute($stmt_get_task);
            $result_get_task = mysqli_stmt_get_result($stmt_get_task);
            $task_details = mysqli_fetch_assoc($result_get_task);
            mysqli_stmt_close($stmt_get_task);

            if($task_details){
                $actual_date_val = date('Y-m-d');
                $actual_time_val = date('H:i:s');
                $is_delayed_val = 0;
                $delay_duration_val = null;

                // Calculate delay for checklist task
                // Checklist tasks planned time is considered end of day (23:59:59) for delay calculation.
                $planned_datetime_str = $task_details['task_date'] . ' 23:59:59';
                $planned_timestamp = strtotime($planned_datetime_str);
                $actual_timestamp = time(); // Current time for comparison

                if ($planned_timestamp && $actual_timestamp > $planned_timestamp) {
                    $is_delayed_val = 1;
                    $delay_seconds = $actual_timestamp - $planned_timestamp;
                    $delay_duration_val = formatSecondsToHHMMSS($delay_seconds);
                } else {
                    $is_delayed_val = 0;
                    $delay_duration_val = '00:00:00'; // Or NULL if preferred for on-time
                }

                $sql_update = "UPDATE checklist_subtasks SET status = ?, actual_date = ?, actual_time = ?, is_delayed = ?, delay_duration = ? WHERE id = ? AND LOWER(TRIM(assignee)) = LOWER(TRIM(?))";
                if ($stmt_update = mysqli_prepare($conn, $sql_update)) {
                    mysqli_stmt_bind_param($stmt_update, "sssisis", $new_status, $actual_date_val, $actual_time_val, $is_delayed_val, $delay_duration_val, $task_id, $current_doer_name);
                    if (mysqli_stmt_execute($stmt_update)) {
                        if (mysqli_stmt_affected_rows($stmt_update) > 0) {
                            $response['status'] = 'success';
                            $response['message'] = 'Checklist task marked as completed.';
                        } else {
                            $response['message'] = 'Could not update checklist task. It might have been already updated or not assigned to you.';
                        }
                    } else {
                        $response['message'] = 'Error executing checklist task update: ' . mysqli_stmt_error($stmt_update);
                    }
                    mysqli_stmt_close($stmt_update);
                } else {
                    $response['message'] = 'DB error preparing checklist task update: ' . mysqli_error($conn);
                }
            } else {
                 $response['message'] = 'Checklist task not found or not assigned to you.';
            }
        } else {
            $response['message'] = 'DB error preparing to fetch checklist task: ' . mysqli_error($conn);
        }
    } else {
        $response['message'] = 'Invalid task type specified.';
    }
} else {
    $response['message'] = 'Invalid request. Missing parameters or wrong method.';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') $response['message'] = 'Invalid request method.';
    elseif (!isset($_POST['task_id'])) $response['message'] = 'Task ID not provided.';
    elseif (!isset($_POST['task_type'])) $response['message'] = 'Task Type not provided.';
    elseif ($_POST['status'] !== 'completed') $response['message'] = 'Invalid status for this action.';
}

echo json_encode($response);
exit;
?>
