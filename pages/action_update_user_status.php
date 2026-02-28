<?php
// Start output buffering to catch any stray output or PHP errors
ob_start();

// Turn off error display for clean JSON responses
ini_set('display_errors', '0');
ini_set('html_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/ajax_errors.log');

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Fallback: define jsonError/jsonSuccess if error_handler.php was not loaded
if (!function_exists('jsonError')) {
    function jsonError($message = 'An error occurred', $code = 500) {
        if (ob_get_length()) ob_clean();
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'status' => 'error', 'message' => $message]);
        exit;
    }
}
if (!function_exists('jsonSuccess')) {
    function jsonSuccess($data = [], $message = 'Success') {
        if (ob_get_length()) ob_clean();
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $response = ['success' => true, 'status' => 'success', 'message' => $message];
        if (is_array($data) && !empty($data)) {
            $response = array_merge($response, $data);
        }
        echo json_encode($response);
        exit;
    }
}

// Re-force display_errors off (error_handler.php overrides it to 1 on localhost)
ini_set('display_errors', '0');
ini_set('html_errors', '0');

// Clean any buffered output from includes and set JSON header
if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');

// Check if user is logged in
if (!isLoggedIn()) {
    jsonError('You must be logged in to perform this action.', 401);
}

// Allow Admin and Manager (but with restrictions for managers)
if (!isAdmin() && !isManager()) {
    jsonError('You do not have permission to perform this action.', 403);
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Invalid request method.', 400);
}

// Get and validate input
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$status = isset($_POST['Status']) ? trim($_POST['Status']) : 'Active';

// Validate user_id
if ($user_id <= 0) {
    jsonError('Invalid user ID.', 400);
}

// Validate Status (should be 'Active' or 'Inactive')
if ($status !== 'Active' && $status !== 'Inactive') {
    jsonError('Invalid status value. Status must be "Active" or "Inactive".', 400);
}

// Prevent users from deactivating themselves
if ($user_id == $_SESSION['id'] && $status == 'Inactive') {
    jsonError('You cannot deactivate your own account.', 403);
}

try {

// Check if manager is trying to update a client account (managers can only update client users, not client accounts)
if (isManager() && !isAdmin()) {
    $check_user_sql = "SELECT password, user_type FROM users WHERE id = ?";
    if ($check_stmt = mysqli_prepare($conn, $check_user_sql)) {
        mysqli_stmt_bind_param($check_stmt, "i", $user_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        if ($user_row = mysqli_fetch_assoc($check_result)) {
            if (($user_row['password'] === null || $user_row['password'] === '') && $user_row['user_type'] === 'client') {
                jsonError('Managers cannot change the status of client accounts. You can only view their status.', 403);
            }
        }
        mysqli_stmt_close($check_stmt);
    }
}

// Update user status
    $sql = "UPDATE users SET Status = ? WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "si", $status, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $response_data = ['user_id' => $user_id, 'Status' => $status];
                $message = 'User status updated successfully.';
                
                // Cascade status to Client Users if this is a Client Account
                // Client Account = user_type 'client' AND password is NULL or empty
                $check_client_sql = "SELECT user_type, password FROM users WHERE id = ?";
                if ($check_client_stmt = mysqli_prepare($conn, $check_client_sql)) {
                    mysqli_stmt_bind_param($check_client_stmt, "i", $user_id);
                    mysqli_stmt_execute($check_client_stmt);
                    $check_client_result = mysqli_stmt_get_result($check_client_stmt);
                    $target_user = mysqli_fetch_assoc($check_client_result);
                    mysqli_stmt_close($check_client_stmt);
                    
                    if ($target_user && $target_user['user_type'] === 'client' && 
                        ($target_user['password'] === null || $target_user['password'] === '')) {
                        // This is a Client Account — cascade status to all its Client Users
                        // Client Users have user_type='client', manager_id = this account's id, and a hashed password
                        $cascade_sql = "UPDATE users SET Status = ? WHERE user_type = 'client' AND manager_id = ? AND password IS NOT NULL AND password != ''";
                        if ($cascade_stmt = mysqli_prepare($conn, $cascade_sql)) {
                            mysqli_stmt_bind_param($cascade_stmt, "si", $status, $user_id);
                            mysqli_stmt_execute($cascade_stmt);
                            $affected_client_users = mysqli_stmt_affected_rows($cascade_stmt);
                            mysqli_stmt_close($cascade_stmt);
                            
                            if ($affected_client_users > 0) {
                                $response_data['client_users_updated'] = $affected_client_users;
                                $message = "Client account set to {$status}. {$affected_client_users} client user(s) also set to {$status}.";
                            }
                        } else {
                            error_log("[action_update_user_status] Cascade prepare failed: " . mysqli_error($conn));
                        }
                    }
                }
                
                jsonSuccess($response_data, $message);
            } else {
                jsonError('No changes were made. User may not exist or status is already set to this value.', 400);
            }
        } else {
            error_log("[action_update_user_status] Execute failed: " . mysqli_stmt_error($stmt));
            jsonError('Database error.', 500);
        }
        
        mysqli_stmt_close($stmt);
    } else {
        error_log("[action_update_user_status] Prepare failed: " . mysqli_error($conn));
        jsonError('Database error.', 500);
    }
} catch (\Throwable $e) {
    error_log("[action_update_user_status] Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    if (ob_get_length()) ob_clean();
    jsonError('An error occurred while updating user status.', 500);
}
?>

