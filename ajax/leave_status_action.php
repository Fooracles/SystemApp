<?php
// Suppress all output except JSON
ob_start();

// Disable error reporting to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../includes/config.php';
require_once '../includes/google_sheets_client.php';
require_once '../includes/functions.php';

// Clear any output buffer
ob_clean();

// Set JSON header
header('Content-Type: application/json');

// No authentication required - simplified for testing
if (!isset($_SESSION['user_type'])) {
    $_SESSION['user_type'] = 'admin';
    $_SESSION['user_name'] = 'Test Admin';
    $_SESSION['user_email'] = 'admin@test.com';
}

// Validate POST data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    ob_end_flush();
    exit;
}

$unique_service_no = $_POST['unique_service_no'] ?? '';
$action = $_POST['action'] ?? '';
$note = $_POST['note'] ?? '';

if (empty($unique_service_no) || empty($action)) {
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    ob_end_flush();
    exit;
}

if (!in_array($action, ['Approve', 'Reject'])) {
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Invalid action. Must be Approve or Reject']);
    ob_end_flush();
    exit;
}

try {
    // Start transaction
    mysqli_begin_transaction($conn);

    // Get the leave request details
    $query = "SELECT * FROM Leave_request WHERE unique_service_no = ? AND (status = '' OR status IS NULL OR status = 'PENDING')";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, 's', $unique_service_no);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $leave_request = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$leave_request) {
        throw new Exception('Leave request not found or already processed');
    }

    // Determine new status
    $new_status = ($action === 'Approve') ? 'Approve' : 'Reject';

    // Update the leave request status
    $update_query = "UPDATE Leave_request SET status = ? WHERE unique_service_no = ?";
    $update_stmt = mysqli_prepare($conn, $update_query);
    if (!$update_stmt) {
        throw new Exception('Database prepare error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($update_stmt, 'ss', $new_status, $unique_service_no);
    if (!mysqli_stmt_execute($update_stmt)) {
        throw new Exception('Failed to update leave request status: ' . mysqli_stmt_error($update_stmt));
    }
    mysqli_stmt_close($update_stmt);

    // Insert into audit table
    $audit_query = "INSERT INTO leave_status_actions (unique_service_no, employee_name, manager_email, actor_email, action, note, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $audit_stmt = mysqli_prepare($conn, $audit_query);
    if (!$audit_stmt) {
        throw new Exception('Database prepare error: ' . mysqli_error($conn));
    }

    $actor_email = 'admin@system.com'; // Default system admin
    mysqli_stmt_bind_param($audit_stmt, 'ssssss', 
        $unique_service_no, 
        $leave_request['employee_name'], 
        $leave_request['manager_email'], 
        $actor_email, 
        $action, 
        $note
    );

    if (!mysqli_stmt_execute($audit_stmt)) {
        throw new Exception('Failed to insert audit record: ' . mysqli_stmt_error($audit_stmt));
    }
    mysqli_stmt_close($audit_stmt);
    
    // Append to Google Sheets (optional - don't fail if this fails)
    $google_sheets_success = false;
    try {
        $gs_client = new GoogleSheetsClient();

        // Prepare data for Google Sheets
        $sheet_data = [
                $unique_service_no,   // Leave request number
                $leave_request['employee_name'], // Name of employee
                strtoupper($action),  // Status (APPROVED or REJECTED)
                $note,               // Comments
                date('Y-m-d H:i:s')  // Timestamp
        ];
        
        // Append to Leave_Status tab using constants
        $result = $gs_client->gs_append(LEAVE_SHEET_ID, LEAVE_TAB_STATUS_APP, $sheet_data);
        $google_sheets_success = true;

    } catch (Exception $e) {
        // Log the error but don't fail the transaction
        error_log("Google Sheets append failed: " . $e->getMessage());
        $google_sheets_success = false;
    }

    // Create notification for employee (reuse logic from notification_actions.php)
    $employee_notification_created = false;
    try {
        // Get employee user ID
        $emp_query = "SELECT id FROM users WHERE name = ? OR email = ?";
        $emp_stmt = mysqli_prepare($conn, $emp_query);
        $employee_email = $leave_request['employee_email'] ?? '';
        mysqli_stmt_bind_param($emp_stmt, 'ss', $leave_request['employee_name'], $employee_email);
        mysqli_stmt_execute($emp_stmt);
        $emp_result = mysqli_stmt_get_result($emp_stmt);
        $employee = mysqli_fetch_assoc($emp_result);
        mysqli_stmt_close($emp_stmt);
        
        if ($employee) {
            $notification_type = ($action === 'Approve') ? 'leave_approved' : 'leave_rejected';
            $notification_title = ($action === 'Approve') ? 'Leave Approved' : 'Leave Rejected';
            $notification_message = ($action === 'Approve') ? 'Your leave has been approved' : 'Your leave has been rejected';
            
            $notification_id = createNotification(
                $conn, 
                $employee['id'], 
                $notification_type,
                $notification_title, 
                $notification_message,
                $unique_service_no, 
                'leave'
            );
            
            if ($notification_id) {
                $employee_notification_created = true;
            }
        }
    } catch (Exception $e) {
        // Log error but don't fail the transaction
        error_log("Failed to create employee notification: " . $e->getMessage());
    }

    // Commit transaction
    mysqli_commit($conn);

    // Ensure clean JSON output
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => "Leave request " . strtolower($action) . "d successfully" . ($google_sheets_success ? "" : " (Google Sheets update failed)"),
        'data' => [
            'unique_service_no' => $unique_service_no,
            'action' => $action,
            'new_status' => $new_status,
            'employee_name' => $leave_request['employee_name'],
            'google_sheets_success' => $google_sheets_success,
            'employee_notification_created' => $employee_notification_created
        ]
    ]);
    ob_end_flush();

} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);

    error_log("Error processing leave status action: " . $e->getMessage());
    http_response_code(500);
    
    // Ensure clean JSON output for errors too
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Failed to process action: ' . $e->getMessage()
    ]);
    ob_end_flush();
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
?>