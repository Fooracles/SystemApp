<?php
// Turn off error reporting and display for production
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/ajax_errors.log');

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Re-suppress error display AFTER includes (error_handler.php enables it in dev)
ini_set('display_errors', 0);

header('Content-Type: application/json'); // Set this at the very beginning

$response = ['status' => 'error', 'message' => 'Invalid request.']; // Default response

// Log the request for debugging
error_log("action_update_task_edit.php called with POST data: " . json_encode($_POST));

if (!isset($_SESSION['id'])) {
    error_log("User not authenticated in action_update_task_edit.php");
    $response['message'] = 'User not authenticated.';
    echo json_encode($response);
    exit;
}

// Log user session info
error_log("User session info - ID: " . $_SESSION['id'] . ", Username: " . ($_SESSION['username'] ?? 'N/A') . ", User Type: " . ($_SESSION['user_type'] ?? 'N/A'));

// Authorization Check - Only Admin can edit tasks
if (!isAdmin()) {
    error_log("User not authorized for task editing - User Type: " . ($_SESSION['user_type'] ?? 'N/A'));
    $response['message'] = 'Only administrators can edit tasks.';
    echo json_encode($response);
    exit;
}

if (!isset($conn)) {
    error_log("Database connection failed in action_update_task_edit.php");
    $response['message'] = 'Database connection failed.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id'])) {
    $task_id = (int)$_POST['task_id'];
    $task_type = trim($_POST['task_type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $doer = trim($_POST['doer'] ?? '');
    $duration = trim($_POST['duration'] ?? '');

    error_log("Processing task edit - Task ID: $task_id, Type: $task_type, Description: $description, Doer: $doer, Duration: $duration");

    // Validate required fields (only the 3 editable fields)
    if (empty($description)) {
        $response['message'] = 'Task description is required.';
        echo json_encode($response);
        exit;
    }

    if (empty($doer)) {
        $response['message'] = 'Doer is required.';
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

    // Handle different task types
    if ($task_type === 'delegation') {
        // Check if task exists
        $sql_check = "SELECT id FROM tasks WHERE id = ?";
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

        // Get doer ID and department
        $doer_id = null;
        $department_id = null;
        $sql_get_doer = "SELECT id, department_id FROM users WHERE username = ?";
        $stmt_doer = mysqli_prepare($conn, $sql_get_doer);
        
        if ($stmt_doer) {
            mysqli_stmt_bind_param($stmt_doer, "s", $doer);
            mysqli_stmt_execute($stmt_doer);
            $result_doer = mysqli_stmt_get_result($stmt_doer);
            $doer_row = mysqli_fetch_assoc($result_doer);
            if ($doer_row) {
                $doer_id = $doer_row['id'];
                $department_id = $doer_row['department_id'];
            }
            mysqli_stmt_close($stmt_doer);
        }

        // Convert duration from HH:MM:SS to minutes for storage
        $duration_parts = explode(':', $duration);
        $duration_minutes = (int)$duration_parts[0] * 60 + (int)$duration_parts[1] + ((int)$duration_parts[2] / 60);
        $duration_minutes = round($duration_minutes);

        // Also convert to decimal hours for backward compatibility
        $duration_hours = (int)$duration_parts[0] + ((int)$duration_parts[1] / 60) + ((int)$duration_parts[2] / 3600);
        $duration_decimal = round($duration_hours, 2);

        // Update the delegation task - only the 3 editable fields (like checklist task)
        $sql_update = "UPDATE tasks 
                       SET description = ?, doer_id = ?, doer_name = ?, department_id = ?, duration = ?, duration_minutes = ? 
                       WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        
        if (!$stmt_update) {
            $response['message'] = 'Error preparing update statement: ' . mysqli_error($conn);
            echo json_encode($response);
            exit;
        }
        
        mysqli_stmt_bind_param($stmt_update, "sisidii", $description, $doer_id, $doer, $department_id, $duration_decimal, $duration_minutes, $task_id);
        
        if (mysqli_stmt_execute($stmt_update)) {
            $response['status'] = 'success';
            $response['message'] = 'Delegation task updated successfully.';
            
            // Log the successful update
            error_log("Delegation task updated successfully - Task ID: $task_id, Updated by: " . ($_SESSION['username'] ?? 'N/A'));
        } else {
            $response['message'] = 'Error executing task update: ' . mysqli_stmt_error($stmt_update);
            error_log("Error updating delegation task - Task ID: $task_id, Error: " . mysqli_stmt_error($stmt_update));
        }
        
        mysqli_stmt_close($stmt_update);
        
    } elseif ($task_type === 'checklist') {
        // Handle checklist task edit (redirect to checklist edit endpoint)
        $response['message'] = 'Checklist task editing should use the checklist edit endpoint.';
        echo json_encode($response);
        exit;
        
    } else {
        $response['message'] = 'Invalid task type: ' . $task_type;
        echo json_encode($response);
        exit;
    }
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
