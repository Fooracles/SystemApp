<?php
// Turn off error reporting and display for production
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/ajax_errors.log');

require_once '../includes/config.php';
require_once '../includes/functions.php';
session_start();

header('Content-Type: application/json'); // Set this at the very beginning

$response = ['status' => 'error', 'message' => 'Invalid request.']; // Default response

// Log the request for debugging
error_log("action_update_checklist_edit.php called with POST data: " . json_encode($_POST));

if (!isset($_SESSION['id'])) {
    error_log("User not authenticated in action_update_checklist_edit.php");
    $response['message'] = 'User not authenticated.';
    echo json_encode($response);
    exit;
}

// Log user session info
error_log("User session info - ID: " . $_SESSION['id'] . ", Username: " . ($_SESSION['username'] ?? 'N/A') . ", User Type: " . ($_SESSION['user_type'] ?? 'N/A'));

// Authorization Check - Only Admin can edit tasks
if (!isAdmin()) {
    error_log("User not authorized for task editing - User Type: " . ($_SESSION['user_type'] ?? 'N/A'));
    $response['message'] = 'Only administrators can edit checklist tasks.';
    echo json_encode($response);
    exit;
}

if (!isset($conn)) {
    error_log("Database connection failed in action_update_checklist_edit.php");
    $response['message'] = 'Database connection failed.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id'])) {
    $task_id = (int)$_POST['task_id'];
    $task_description = trim($_POST['task_description'] ?? '');
    $assignee = trim($_POST['assignee'] ?? '');
    $duration = trim($_POST['duration'] ?? '');

    error_log("Processing checklist task edit - Task ID: $task_id, Description: $task_description, Assignee: $assignee, Duration: $duration");

    // Validate required fields
    if (empty($task_description)) {
        $response['message'] = 'Task description is required.';
        echo json_encode($response);
        exit;
    }

    if (empty($assignee)) {
        $response['message'] = 'Assignee is required.';
        echo json_encode($response);
        exit;
    }

    if (empty($duration)) {
        $response['message'] = 'Duration is required.';
        echo json_encode($response);
        exit;
    }

    // Validate duration format (HH:MM:SS)
    if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $duration)) {
        $response['message'] = 'Invalid duration format.';
        echo json_encode($response);
        exit;
    }

    // Check if task exists
    $sql_check = "SELECT id FROM checklist_subtasks WHERE id = ?";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    
    if (!$stmt_check) {
        $response['message'] = 'Error preparing check statement: ' . mysqli_error($conn);
        echo json_encode($response);
        exit;
    }
    
    mysqli_stmt_bind_param($stmt_check, "i", $task_id);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    
    if (mysqli_num_rows($result_check) == 0) {
        $response['message'] = 'Task not found.';
        echo json_encode($response);
        exit;
    }
    
    mysqli_stmt_close($stmt_check);

    // Get department for the assignee
    $department = 'N/A';
    $sql_get_dept = "SELECT d.name as department_name FROM users u JOIN departments d ON u.department_id = d.id WHERE u.username = ?";
    $stmt_dept = mysqli_prepare($conn, $sql_get_dept);
    
    if ($stmt_dept) {
        mysqli_stmt_bind_param($stmt_dept, "s", $assignee);
        mysqli_stmt_execute($stmt_dept);
        $result_dept = mysqli_stmt_get_result($stmt_dept);
        $dept_row = mysqli_fetch_assoc($result_dept);
        if ($dept_row) {
            $department = $dept_row['department_name'] ?: 'N/A';
        }
        mysqli_stmt_close($stmt_dept);
    }

    // Update the task - only the 3 editable fields
    $sql_update = "UPDATE checklist_subtasks 
                   SET task_description = ?, assignee = ?, duration = ? 
                   WHERE id = ?";
    $stmt_update = mysqli_prepare($conn, $sql_update);
    
    if (!$stmt_update) {
        $response['message'] = 'Error preparing update statement: ' . mysqli_error($conn);
        echo json_encode($response);
        exit;
    }
    
    mysqli_stmt_bind_param($stmt_update, "sssi", $task_description, $assignee, $duration, $task_id);
    
    if (mysqli_stmt_execute($stmt_update)) {
        $response['status'] = 'success';
        $response['message'] = 'Checklist task updated successfully.';
        
        // Log the successful update
        error_log("Checklist task updated successfully - Task ID: $task_id, Updated by: " . ($_SESSION['username'] ?? 'N/A'));
    } else {
        $response['message'] = 'Error executing task update: ' . mysqli_stmt_error($stmt_update);
        error_log("Error updating checklist task - Task ID: $task_id, Error: " . mysqli_stmt_error($stmt_update));
    }
    
    mysqli_stmt_close($stmt_update);
} else {
    // If conditions for POST, task_id are not met
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response['message'] = 'Invalid request method.';
    } elseif (!isset($_POST['task_id'])) {
        $response['message'] = 'Task ID not provided.';
    }
}

echo json_encode($response);
exit; // Ensure no further output
?>

