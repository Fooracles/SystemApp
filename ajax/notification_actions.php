<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/notification_triggers.php';

session_start();

// Ensure createNotification function is available (from functions.php)
if (!function_exists('createNotification')) {
    // Fallback if function doesn't exist
    function createNotification($conn, $user_id, $type, $title, $message, $related_id = null, $related_type = null, $action_required = false, $action_data = null) {
        $action_data_json = $action_data ? json_encode($action_data) : null;
        $related_id_value = $related_id !== null ? (string)$related_id : null;
        $action_required_int = $action_required ? 1 : 0;
        
        $query = "INSERT INTO notifications (user_id, type, title, message, related_id, related_type, action_required, action_data) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            error_log("Failed to prepare notification query: " . mysqli_error($conn));
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, 'isssssis', $user_id, $type, $title, $message, $related_id_value, $related_type, $action_required_int, $action_data_json);
        
        if (!mysqli_stmt_execute($stmt)) {
            error_log("Failed to execute notification query: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return false;
        }
        
        $notification_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        return $notification_id;
    }
}

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    jsonError('Unauthorized', 401);
}

header('Content-Type: application/json');

// CSRF protection for POST requests
csrfProtect();

$action = $_POST['action'] ?? '';
$notification_id = $_POST['notification_id'] ?? null;
$user_id = $_SESSION['id'] ?? null;

if (!$user_id || !$notification_id) {
    jsonError('Missing required parameters', 400);
}

// Get notification details
$query = "SELECT * FROM notifications WHERE id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'ii', $notification_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$notification = mysqli_fetch_assoc($result);

if (!$notification) {
    jsonError('Notification not found', 404);
}

$action_data = $notification['action_data'] ? json_decode($notification['action_data'], true) : null;
$related_id = $notification['related_id'];
$type = $notification['type'];

// Use related_id from POST if provided (more reliable), otherwise use from notification
$related_id = $_POST['related_id'] ?? $related_id;

switch ($action) {
    case 'approve_meeting':
        approveMeeting($conn, $related_id, $user_id, $notification_id);
        break;
    
    case 'mark_read':
        // Mark notification as read (used after leave action from notification panel)
        markNotificationAsRead($conn, $notification_id, $user_id);
        break;
    
    case 'approve_leave':
    case 'reject_leave':
        // These actions are now handled via modal -> leave_status_action.php
        // Keeping for backward compatibility but should not be called directly
        echo json_encode(['success' => false, 'error' => 'This action should be performed via the confirmation modal']);
        break;
    
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

function approveMeeting($conn, $meeting_id, $user_id, $notification_id = null) {
    // Check if user is admin
    $user_query = "SELECT user_type FROM users WHERE id = ?";
    $user_stmt = mysqli_prepare($conn, $user_query);
    if (!$user_stmt) {
        handleDbError($conn, 'C:/xampp/htdocs/app-v5.5-new/ajax/notification_actions.php');
        exit;
    }
    mysqli_stmt_bind_param($user_stmt, 'i', $user_id);
    mysqli_stmt_execute($user_stmt);
    $user_result = mysqli_stmt_get_result($user_stmt);
    $user = mysqli_fetch_assoc($user_result);
    mysqli_stmt_close($user_stmt);
    
    if (!$user || $user['user_type'] !== 'admin') {
        jsonError('Only admins can approve meetings', 500);
    }
    
    // Convert meeting_id to integer if it's a string
    $meeting_id_int = is_numeric($meeting_id) ? (int)$meeting_id : $meeting_id;
    $meeting_id_str = (string)$meeting_id_int;
    
    // Check if meeting exists and is pending, get preferred_date and preferred_time
    $check_query = "SELECT status, preferred_date, preferred_time FROM meeting_requests WHERE id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    if (!$check_stmt) {
        handleDbError($conn, 'C:/xampp/htdocs/app-v5.5-new/ajax/notification_actions.php');
        exit;
    }
    mysqli_stmt_bind_param($check_stmt, 'i', $meeting_id_int);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $existing_meeting = mysqli_fetch_assoc($check_result);
    mysqli_stmt_close($check_stmt);
    
    if (!$existing_meeting) {
        jsonError('Meeting request not found', 404);
    }
    
    if ($existing_meeting['status'] !== 'Pending') {
        jsonError('Only pending meetings can be approved', 500);
    }
    
    // Check if preferred_date and preferred_time are available
    if (empty($existing_meeting['preferred_date'])) {
        jsonError('Preferred date is required to approve and schedule the meeting', 400);
    }
    
    // Use preferred_time if available, otherwise default to 09:00:00
    $preferred_time = !empty($existing_meeting['preferred_time']) ? $existing_meeting['preferred_time'] : '09:00:00';
    
    // Combine preferred_date and preferred_time into scheduled_date (DATETIME format)
    $scheduled_date = $existing_meeting['preferred_date'] . ' ' . $preferred_time;
    
    // Validate the combined datetime
    $date_obj = DateTime::createFromFormat('Y-m-d H:i:s', $scheduled_date);
    if (!$date_obj || $date_obj->format('Y-m-d H:i:s') !== $scheduled_date) {
        // Try with just time (HH:mm format)
        if (strlen($preferred_time) == 5) {
            $scheduled_date = $existing_meeting['preferred_date'] . ' ' . $preferred_time . ':00';
        } else {
            jsonError('Invalid date/time format', 400);
        }
    }
    
    // Update meeting status to Scheduled and set scheduled_date (matching meeting_handler.php logic)
    $update_query = "UPDATE meeting_requests SET status = 'Scheduled', scheduled_date = ? WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_query);
    if (!$update_stmt) {
        handleDbError($conn, 'C:/xampp/htdocs/app-v5.5-new/ajax/notification_actions.php');
        exit;
    }
    mysqli_stmt_bind_param($update_stmt, 'si', $scheduled_date, $meeting_id_int);
    if (!mysqli_stmt_execute($update_stmt)) {
        mysqli_stmt_close($update_stmt);
        handleDbError($conn, 'C:/xampp/htdocs/app-v5.5-new/ajax/notification_actions.php');
        exit;
    }
    
    if (mysqli_stmt_affected_rows($update_stmt) === 0) {
        mysqli_stmt_close($update_stmt);
        jsonError('Failed to update meeting request', 500);
    }
    mysqli_stmt_close($update_stmt);
    
    // Get meeting details for notification
    $get_query = "SELECT doer_email, scheduled_date FROM meeting_requests WHERE id = ?";
    $get_stmt = mysqli_prepare($conn, $get_query);
    if (!$get_stmt) {
        handleDbError($conn, 'C:/xampp/htdocs/app-v5.5-new/ajax/notification_actions.php');
        exit;
    }
    mysqli_stmt_bind_param($get_stmt, 'i', $meeting_id_int);
    mysqli_stmt_execute($get_stmt);
    $meeting_result = mysqli_stmt_get_result($get_stmt);
    $meeting_data = mysqli_fetch_assoc($meeting_result);
    mysqli_stmt_close($get_stmt);
    
    // Get user ID and create notification
    if ($meeting_data) {
        $user_query = "SELECT id FROM users WHERE email = ?";
        $user_stmt = mysqli_prepare($conn, $user_query);
        if ($user_stmt) {
            mysqli_stmt_bind_param($user_stmt, 's', $meeting_data['doer_email']);
            mysqli_stmt_execute($user_stmt);
            $user_result = mysqli_stmt_get_result($user_stmt);
            $doer = mysqli_fetch_assoc($user_result);
            mysqli_stmt_close($user_stmt);
            
            if ($doer && $meeting_data['scheduled_date']) {
                $date_part = date('d/m/Y', strtotime($meeting_data['scheduled_date']));
                $time_part = date('h:i A', strtotime($meeting_data['scheduled_date']));
                createNotification($conn, $doer['id'], 'meeting_approved', 
                    'Meeting Approved', 
                    "Your meeting has been approved. Scheduled for {$date_part} at {$time_part}",
                    $meeting_id_str, 'meeting');
            }
        }
    }
    
    // Mark original notification as read and remove action buttons
    // Use specific notification_id if provided, otherwise fallback to related_id lookup
    if ($notification_id) {
        $notif_query = "UPDATE notifications SET is_read = 1, read_at = NOW(), action_required = 0, action_data = NULL WHERE id = ? AND user_id = ?";
        $notif_stmt = mysqli_prepare($conn, $notif_query);
        if ($notif_stmt) {
            mysqli_stmt_bind_param($notif_stmt, 'ii', $notification_id, $user_id);
            mysqli_stmt_execute($notif_stmt);
            mysqli_stmt_close($notif_stmt);
        }
    } else {
        // Fallback: use related_id lookup (related_id is VARCHAR, so use string)
        $notif_query = "UPDATE notifications SET is_read = 1, read_at = NOW(), action_required = 0, action_data = NULL WHERE related_id = ? AND type = 'meeting_request' AND user_id = ?";
        $notif_stmt = mysqli_prepare($conn, $notif_query);
        if ($notif_stmt) {
            mysqli_stmt_bind_param($notif_stmt, 'si', $meeting_id_str, $user_id);
            mysqli_stmt_execute($notif_stmt);
            mysqli_stmt_close($notif_stmt);
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Meeting approved and scheduled successfully']);
}

function approveLeave($conn, $leave_id, $user_id) {
    // Get leave request details
    $leave_query = "SELECT * FROM Leave_request WHERE unique_service_no = ?";
    $leave_stmt = mysqli_prepare($conn, $leave_query);
    mysqli_stmt_bind_param($leave_stmt, 's', $leave_id);
    mysqli_stmt_execute($leave_stmt);
    $leave_result = mysqli_stmt_get_result($leave_stmt);
    $leave = mysqli_fetch_assoc($leave_result);
    
    if (!$leave) {
        jsonError('Leave request not found', 404);
    }
    
    // Update leave status
    $update_query = "UPDATE Leave_request SET status = 'Approve' WHERE unique_service_no = ?";
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, 's', $leave_id);
    mysqli_stmt_execute($update_stmt);
    
    // Get employee user ID
    $emp_query = "SELECT id FROM users WHERE name = ? OR email = ?";
    $emp_stmt = mysqli_prepare($conn, $emp_query);
    $employee_email = $leave['employee_email'] ?? '';
    mysqli_stmt_bind_param($emp_stmt, 'ss', $leave['employee_name'], $employee_email);
    mysqli_stmt_execute($emp_stmt);
    $emp_result = mysqli_stmt_get_result($emp_stmt);
    $employee = mysqli_fetch_assoc($emp_result);
    
    if ($employee) {
        createNotification($conn, $employee['id'], 'leave_approved',
            'Leave Approved',
            'Your leave has been approved',
            $leave_id, 'leave');
    }
    
    // Mark original notification as read and remove action buttons (related_id is VARCHAR, so use string)
    $notif_query = "UPDATE notifications SET is_read = 1, read_at = NOW(), action_required = 0, action_data = NULL WHERE related_id = ? AND type = 'leave_request' AND user_id = ?";
    $notif_stmt = mysqli_prepare($conn, $notif_query);
    if ($notif_stmt) {
        mysqli_stmt_bind_param($notif_stmt, 'si', $leave_id, $user_id);
        mysqli_stmt_execute($notif_stmt);
        mysqli_stmt_close($notif_stmt);
    }
    
    echo json_encode(['success' => true, 'message' => 'Leave approved successfully']);
}

function rejectLeave($conn, $leave_id, $user_id) {
    // Get leave request details
    $leave_query = "SELECT * FROM Leave_request WHERE unique_service_no = ?";
    $leave_stmt = mysqli_prepare($conn, $leave_query);
    mysqli_stmt_bind_param($leave_stmt, 's', $leave_id);
    mysqli_stmt_execute($leave_stmt);
    $leave_result = mysqli_stmt_get_result($leave_stmt);
    $leave = mysqli_fetch_assoc($leave_result);
    
    if (!$leave) {
        jsonError('Leave request not found', 404);
    }
    
    // Update leave status
    $update_query = "UPDATE Leave_request SET status = 'Reject' WHERE unique_service_no = ?";
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, 's', $leave_id);
    mysqli_stmt_execute($update_stmt);
    
    // Get employee user ID
    $emp_query = "SELECT id FROM users WHERE name = ? OR email = ?";
    $emp_stmt = mysqli_prepare($conn, $emp_query);
    $employee_email = $leave['employee_email'] ?? '';
    mysqli_stmt_bind_param($emp_stmt, 'ss', $leave['employee_name'], $employee_email);
    mysqli_stmt_execute($emp_stmt);
    $emp_result = mysqli_stmt_get_result($emp_stmt);
    $employee = mysqli_fetch_assoc($emp_result);
    
    if ($employee) {
        createNotification($conn, $employee['id'], 'leave_rejected',
            'Leave Rejected',
            'Your leave has been rejected',
            $leave_id, 'leave');
    }
    
    // Mark original notification as read and remove action buttons (related_id is VARCHAR, so use string)
    $notif_query = "UPDATE notifications SET is_read = 1, read_at = NOW(), action_required = 0, action_data = NULL WHERE related_id = ? AND type = 'leave_request' AND user_id = ?";
    $notif_stmt = mysqli_prepare($conn, $notif_query);
    if ($notif_stmt) {
        mysqli_stmt_bind_param($notif_stmt, 'si', $leave_id, $user_id);
        mysqli_stmt_execute($notif_stmt);
        mysqli_stmt_close($notif_stmt);
    }
    
    echo json_encode(['success' => true, 'message' => 'Leave rejected successfully']);
}

function markNotificationAsRead($conn, $notification_id, $user_id) {
    // Mark notification as read and remove action buttons
    $notif_query = "UPDATE notifications SET is_read = 1, read_at = NOW(), action_required = 0, action_data = NULL WHERE id = ? AND user_id = ?";
    $notif_stmt = mysqli_prepare($conn, $notif_query);
    if ($notif_stmt) {
        mysqli_stmt_bind_param($notif_stmt, 'ii', $notification_id, $user_id);
        if (mysqli_stmt_execute($notif_stmt)) {
            mysqli_stmt_close($notif_stmt);
            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
        } else {
            mysqli_stmt_close($notif_stmt);
            handleDbError($conn, 'C:/xampp/htdocs/app-v5.5-new/ajax/notification_actions.php');
        }
    } else {
        handleDbError($conn, 'C:/xampp/htdocs/app-v5.5-new/ajax/notification_actions.php');
    }
}

// Helper function to create notifications (using the one from functions.php)
// This function is kept for backward compatibility but should use the one from includes/functions.php

// Close database connection
if (isset($conn) && $conn instanceof mysqli) {
    mysqli_close($conn);
}
