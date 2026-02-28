<?php
// Turn off error reporting and display for production
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/ajax_errors.log');

require_once '../includes/config.php';
require_once '../includes/functions.php'; // Ensure formatSecondsToHHMMSS is available
require_once '../includes/status_column_helpers.php'; // Include status column helpers
session_start();

header('Content-Type: application/json'); // Set this at the very beginning

$response = ['status' => 'error', 'message' => 'Invalid request.']; // Default response

// Log the request for debugging
error_log("action_update_checklist_status.php called with POST data: " . json_encode($_POST));

if (!isset($_SESSION['id'])) {
    error_log("User not authenticated in action_update_checklist_status.php");
    $response['message'] = 'User not authenticated.';
    echo json_encode($response);
    exit;
}

// Log user session info
error_log("User session info - ID: " . $_SESSION['id'] . ", Username: " . ($_SESSION['username'] ?? 'N/A') . ", User Type: " . ($_SESSION['user_type'] ?? 'N/A'));

// --- MODIFIED Authorization Check ---
$is_completing_action = (isset($_POST['status']) && $_POST['status'] === 'completed');

if (!$is_completing_action && !isAdmin() && !isManager()) {
    error_log("User not authorized for general status update - User Type: " . ($_SESSION['user_type'] ?? 'N/A') . ", Status: " . ($_POST['status'] ?? 'N/A'));
    $response['message'] = 'User not authorized to perform this general status update.';
    echo json_encode($response);
    exit;
}
// If it IS a completing action by a non-admin/manager (i.e., a Doer), specific checks will be done later.
// Admins or Managers can proceed for any status.

if (!isset($conn)) {
    error_log("Database connection failed in action_update_checklist_status.php");
    $response['message'] = 'Database connection failed.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id']) && isset($_POST['status'])) {
    $task_id = (int)$_POST['task_id'];
    $new_status = $_POST['status'];
    $allowed_statuses = ['pending', 'completed', 'not_done', 'cant_be_done', 'can_not_be_done'];

    error_log("Processing checklist task update - Task ID: $task_id, New Status: $new_status");

    if (!in_array($new_status, $allowed_statuses)) {
        error_log("Invalid status value provided: $new_status");
        $response['message'] = 'Invalid status value provided.';
        echo json_encode($response);
        exit;
    }

    // Normalize status value for consistency (convert cant_be_done to can_not_be_done for database)
    $db_status = $new_status;
    if ($new_status === 'cant_be_done') {
        $db_status = 'can_not_be_done';
    }

    // Initialize variables for database update
    $actual_date_val = null;
    $actual_time_val = null;
    $is_delayed_val = 0;
    $delay_duration_val = null;

    if ($new_status === 'completed') {
        // Fetch task details to get planned date for delay calculation
        $sql_get_task = "SELECT task_date, assignee FROM checklist_subtasks WHERE id = ?";
        $stmt_get_task = mysqli_prepare($conn, $sql_get_task);

        if (!$stmt_get_task) {
            $response['message'] = 'Error preparing statement to fetch task: ' . mysqli_error($conn);
            echo json_encode($response);
            exit;
        }
        mysqli_stmt_bind_param($stmt_get_task, "i", $task_id);
        mysqli_stmt_execute($stmt_get_task);
        $result_get_task = mysqli_stmt_get_result($stmt_get_task);
        $task_details = mysqli_fetch_assoc($result_get_task);
        mysqli_stmt_close($stmt_get_task);

        if (!$task_details) {
            $response['message'] = 'Task not found.';
            echo json_encode($response);
            exit;
        }

        date_default_timezone_set('Asia/Kolkata'); // Set the timezone
        $actual_date = date('Y-m-d'); // Get current date based on timezone
        $actual_time = date('H:i:s'); // Get current time based on timezone

        // $current_datetime is still useful for $actual_timestamp comparison.
        $current_datetime = new DateTime(); // This will now respect Asia/Kolkata

        $actual_date_val = $actual_date; // Use the new variable
        $actual_time_val = $actual_time; // Use the new variable

        $planned_datetime_str = $task_details['task_date'] . ' 23:59:59'; // Planned end of day for checklist items
        $planned_timestamp = strtotime($planned_datetime_str);
        $actual_timestamp = $current_datetime->getTimestamp();

        if ($actual_timestamp > $planned_timestamp) {
            $is_delayed_val = 1;
            $delay_seconds = $actual_timestamp - $planned_timestamp;
            if (function_exists('formatSecondsToHHMMSS')) {
                $delay_duration_val = formatSecondsToHHMMSS($delay_seconds);
            } else {
                // Fallback if function is somehow not available, though it should be via functions.php
                $delay_duration_val = gmdate("H:i:s", $delay_seconds); 
            }
        } else {
            $is_delayed_val = 0;
            $delay_duration_val = null; // Or '00:00:00' or 'On Time' if preferred for on-time completion
        }
    } else {
        // For 'pending', 'not_done', 'cant_be_done'
        // actual_date, actual_time, is_delayed, delay_duration are reset/nulled
        $actual_date_val = null;
        $actual_time_val = null;
        $is_delayed_val = 0; // Not considered delayed if not completed
        $delay_duration_val = null;
    }

    $sql_update = "UPDATE checklist_subtasks 
                   SET status = ?, actual_date = ?, actual_time = ?, is_delayed = ?, delay_duration = ? 
                   WHERE id = ?";
    $stmt_update = mysqli_prepare($conn, $sql_update);
    
    if (!$stmt_update) {
        $response['message'] = 'Error preparing update statement: ' . mysqli_error($conn);
    } else {
        mysqli_stmt_bind_param($stmt_update, "sssisi", $db_status, $actual_date_val, $actual_time_val, $is_delayed_val, $delay_duration_val, $task_id);
        
        if (mysqli_stmt_execute($stmt_update)) {
            if (mysqli_stmt_affected_rows($stmt_update) > 0) {
                $response['status'] = 'success';
                $response['message'] = 'Checklist task status updated successfully to ' . htmlspecialchars(ucfirst(str_replace('_', ' ', $new_status))) . '.';
                $response['new_status_icon'] = get_status_icon($new_status);
                // Pass back the updated values for live UI update if needed by JS, though current JS reloads
                $response['updated_actual_display'] = $new_status === 'completed' && $actual_date_val ? date("d M Y", strtotime($actual_date_val)) . " " . date("h:i A", strtotime($actual_time_val)) : 'N/A';
                $response['updated_delay_display_html'] = 'N/A'; // Default
                if ($new_status === 'completed') {
                    if ($is_delayed_val == 1 && !empty($delay_duration_val)) {
                        $response['updated_delay_display_html'] = '<span class="text-danger">' . htmlspecialchars($delay_duration_val) . '</span>';
                    } else {
                         $response['updated_delay_display_html'] = '<span class="text-success">On Time</span>';
                    }
                } elseif ($new_status === 'pending') {
                    // For pending, delay is calculated on page load; JS can show "N/A (Updated)"
                     $response['updated_delay_display_html'] = 'N/A (Recalculating)';
                }
            } else {
                $response['message'] = 'Task not found or no changes made to status.';
            }
        } else {
            $response['message'] = 'Error executing task update: ' . mysqli_stmt_error($stmt_update);
        }
        mysqli_stmt_close($stmt_update);
    }
} else {
    // If conditions for POST, task_id, status are not met
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response['message'] = 'Invalid request method.';
    } elseif (!isset($_POST['task_id'])) {
        $response['message'] = 'Task ID not provided.';
    } elseif (!isset($_POST['status'])) {
        $response['message'] = 'Status not provided.';
    }
}

echo json_encode($response);
exit; // Ensure no further output
