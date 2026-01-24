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
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    send_ajax_json_response([
        'status' => 'error',
        'message' => 'You must be logged in to perform this action.'
    ]);
}

// Allow Admin and Manager (but with restrictions for managers)
if (!isAdmin() && !isManager()) {
    send_ajax_json_response([
        'status' => 'error',
        'message' => 'You do not have permission to perform this action.'
    ]);
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_ajax_json_response([
        'status' => 'error',
        'message' => 'Invalid request method.'
    ]);
}

// Get and validate input
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$status = isset($_POST['Status']) ? trim($_POST['Status']) : 'Active';

// Validate user_id
if ($user_id <= 0) {
    send_ajax_json_response([
        'status' => 'error',
        'message' => 'Invalid user ID.'
    ]);
}

// Validate Status (should be 'Active' or 'Inactive')
if ($status !== 'Active' && $status !== 'Inactive') {
    send_ajax_json_response([
        'status' => 'error',
        'message' => 'Invalid status value. Status must be "Active" or "Inactive".'
    ]);
}

// Prevent users from deactivating themselves
if ($user_id == $_SESSION['id'] && $status == 'Inactive') {
    send_ajax_json_response([
        'status' => 'error',
        'message' => 'You cannot deactivate your own account.'
    ]);
}

// Check if manager is trying to update a client account (managers can only update client users, not client accounts)
if (isManager() && !isAdmin()) {
    // Check if the user being updated is a client account (password is NULL or empty)
    $check_user_sql = "SELECT password, user_type FROM users WHERE id = ?";
    if ($check_stmt = mysqli_prepare($conn, $check_user_sql)) {
        mysqli_stmt_bind_param($check_stmt, "i", $user_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        if ($user_row = mysqli_fetch_assoc($check_result)) {
            // If password is NULL or empty, it's a client account (not a client user)
            if (($user_row['password'] === null || $user_row['password'] === '') && $user_row['user_type'] === 'client') {
                send_ajax_json_response([
                    'status' => 'error',
                    'message' => 'Managers cannot change the status of client accounts. You can only view their status.'
                ]);
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
        // Check if any rows were affected
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            send_ajax_json_response([
                'status' => 'success',
                'message' => 'User status updated successfully.',
                'user_id' => $user_id,
                'Status' => $status
            ]);
        } else {
            send_ajax_json_response([
                'status' => 'error',
                'message' => 'No changes were made. User may not exist or status is already set to this value.'
            ]);
        }
    } else {
        error_log("Database error: " . mysqli_stmt_error($stmt));
        send_ajax_json_response([
            'status' => 'error',
            'message' => 'Database error: ' . mysqli_stmt_error($stmt)
        ]);
    }
    
    mysqli_stmt_close($stmt);
} else {
    error_log("Prepare error: " . mysqli_error($conn));
    send_ajax_json_response([
        'status' => 'error',
        'message' => 'Database error: ' . mysqli_error($conn)
    ]);
}

// If we reach here, something went wrong
send_ajax_json_response([
    'status' => 'error',
    'message' => 'An unexpected error occurred.'
]);
?>

