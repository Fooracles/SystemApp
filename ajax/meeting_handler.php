<?php
/**
 * Meeting Handler AJAX Endpoint
 * Handles CRUD operations for meeting requests
 */

// Suppress all output except JSON
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/sorting_helpers.php';
require_once '../includes/notification_triggers.php';

session_start();

// Clear any output buffer
ob_clean();

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    ob_end_flush();
    exit;
}

// Check database connection
if (!isset($conn) || !$conn) {
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed'
    ]);
    ob_end_flush();
    exit;
}

// Set timezone
mysqli_query($conn, "SET time_zone = '+05:30'");

// Ensure meeting_requests table exists
$table_check = "SHOW TABLES LIKE 'meeting_requests'";
$table_result = mysqli_query($conn, $table_check);
if (mysqli_num_rows($table_result) == 0) {
    // Table doesn't exist, create it
    $create_table = "CREATE TABLE IF NOT EXISTS meeting_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        doer_name VARCHAR(255) NOT NULL,
        doer_email VARCHAR(255) NOT NULL,
        reason TEXT NOT NULL,
        duration VARCHAR(20) NOT NULL,
        urgency VARCHAR(50) NULL,
        preferred_date DATE NULL,
        preferred_time TIME NULL,
        status ENUM('Pending', 'Approved', 'Scheduled', 'Completed') DEFAULT 'Pending',
        scheduled_date DATETIME NULL,
        schedule_comment TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (doer_email),
        INDEX (status),
        INDEX (scheduled_date),
        INDEX (preferred_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if (!mysqli_query($conn, $create_table)) {
        http_response_code(500);
        ob_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Failed to create meeting_requests table: ' . mysqli_error($conn)
        ]);
        ob_end_flush();
        exit;
    }
} else {
    // Table exists, check if preferred_date column exists, if not add it
    $column_check = "SHOW COLUMNS FROM meeting_requests LIKE 'preferred_date'";
    $column_result = mysqli_query($conn, $column_check);
    if (mysqli_num_rows($column_result) == 0) {
        // Add preferred_date column
        $alter_table = "ALTER TABLE meeting_requests ADD COLUMN preferred_date DATE NULL AFTER urgency";
        mysqli_query($conn, $alter_table);
        // Also make urgency nullable for backward compatibility
        $alter_urgency = "ALTER TABLE meeting_requests MODIFY COLUMN urgency VARCHAR(50) NULL";
        mysqli_query($conn, $alter_urgency);
    }
    
    // Check if schedule_comment column exists, if not add it
    $comment_check = "SHOW COLUMNS FROM meeting_requests LIKE 'schedule_comment'";
    $comment_result = mysqli_query($conn, $comment_check);
    if (mysqli_num_rows($comment_result) == 0) {
        // Add schedule_comment column
        $alter_comment = "ALTER TABLE meeting_requests ADD COLUMN schedule_comment TEXT NULL AFTER scheduled_date";
        mysqli_query($conn, $alter_comment);
    }
    
    // Check if preferred_time column exists, if not add it
    $time_check = "SHOW COLUMNS FROM meeting_requests LIKE 'preferred_time'";
    $time_result = mysqli_query($conn, $time_check);
    if (mysqli_num_rows($time_result) == 0) {
        // Add preferred_time column
        $alter_time = "ALTER TABLE meeting_requests ADD COLUMN preferred_time TIME NULL AFTER preferred_date";
        mysqli_query($conn, $alter_time);
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            // Create a new meeting request
            $doer_name = $_SESSION['username'] ?? '';
            // Try to get actual email from users table
            $doer_email = $_SESSION['username'] . '@company.com'; // Default fallback
            if (!empty($_SESSION['id'])) {
                $user_query = "SELECT email FROM users WHERE id = ?";
                $user_stmt = mysqli_prepare($conn, $user_query);
                if ($user_stmt) {
                    mysqli_stmt_bind_param($user_stmt, 'i', $_SESSION['id']);
                    mysqli_stmt_execute($user_stmt);
                    $user_result = mysqli_stmt_get_result($user_stmt);
                    if ($user_row = mysqli_fetch_assoc($user_result)) {
                        $doer_email = $user_row['email'] ?? $doer_email;
                    }
                    mysqli_stmt_close($user_stmt);
                }
            }
            $reason = trim($_POST['reason'] ?? '');
            $duration = $_POST['duration'] ?? '';
            $preferred_datetime = $_POST['preferred_datetime'] ?? '';
            
            if (empty($doer_name) || empty($reason) || empty($duration) || empty($preferred_datetime)) {
                throw new Exception('All fields are required');
            }
            
            // Validate duration
            $valid_durations = ['00:15:00', '00:30:00', '00:45:00', '01:00:00', '02:00:00'];
            if (!in_array($duration, $valid_durations)) {
                throw new Exception('Invalid duration');
            }
            
            // Parse datetime-local format (YYYY-MM-DDTHH:mm) to separate date and time
            $datetime_parts = explode('T', $preferred_datetime);
            if (count($datetime_parts) !== 2) {
                throw new Exception('Invalid datetime format. Expected: Y-m-dTH:i');
            }
            
            $preferred_date = $datetime_parts[0];
            $preferred_time = $datetime_parts[1] . ':00'; // Add seconds to make it HH:mm:ss format
            
            // Validate preferred date
            $date_obj = DateTime::createFromFormat('Y-m-d', $preferred_date);
            if (!$date_obj || $date_obj->format('Y-m-d') !== $preferred_date) {
                throw new Exception('Invalid date format. Expected: Y-m-d');
            }
            
            // Validate preferred time
            $time_obj = DateTime::createFromFormat('H:i:s', $preferred_time);
            if (!$time_obj || $time_obj->format('H:i:s') !== $preferred_time) {
                throw new Exception('Invalid time format. Expected: H:i');
            }
            
            // Check if datetime is not in the past
            $now = new DateTime();
            $preferred_datetime_obj = DateTime::createFromFormat('Y-m-d H:i:s', $preferred_date . ' ' . $preferred_time);
            if ($preferred_datetime_obj < $now) {
                throw new Exception('Preferred date and time cannot be in the past');
            }
            
            // Calculate urgency based on preferred datetime (optional, for backward compatibility)
            $days_diff = $now->diff($preferred_datetime_obj)->days;
            $urgency = null;
            if ($days_diff <= 1) {
                $urgency = 'High (within 1 day)';
            } elseif ($days_diff <= 3) {
                $urgency = 'Medium (within 3 days)';
            } else {
                $urgency = 'Low (within a week)';
            }
            
            $insert_query = "INSERT INTO meeting_requests (doer_name, doer_email, reason, duration, urgency, preferred_date, preferred_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')";
            $stmt = mysqli_prepare($conn, $insert_query);
            
            if (!$stmt) {
                throw new Exception('Database prepare error: ' . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($stmt, 'sssssss', $doer_name, $doer_email, $reason, $duration, $urgency, $preferred_date, $preferred_time);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to create meeting request: ' . mysqli_stmt_error($stmt));
            }
            
            $meeting_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            
            // Trigger notification for admin
            triggerMeetingRequestNotification($conn, $meeting_id, $doer_name, $doer_email);
            
            echo json_encode([
                'success' => true,
                'message' => 'Your meeting request has been sent to the Admin.',
                'meeting_id' => $meeting_id
            ]);
            break;
            
        case 'get_scheduled_meetings':
            // Fetch scheduled meetings (Pending + Scheduled future/today only) with auto-move logic
            if (!isAdmin()) {
                throw new Exception('Unauthorized: Admin access required');
            }
            
            // First, auto-move past scheduled meetings to Completed
            $today = date('Y-m-d');
            $update_query = "UPDATE meeting_requests 
                            SET status = 'Completed' 
                            WHERE status = 'Scheduled' 
                            AND DATE(scheduled_date) < ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            if ($update_stmt) {
                mysqli_stmt_bind_param($update_stmt, 's', $today);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
            }
            
            // Get sorting parameters
            $sort_column = isset($_GET['sort']) ? $_GET['sort'] : '';
            $sort_direction = isset($_GET['dir']) ? $_GET['dir'] : 'default';
            $allowed_sort_columns = ['doer_name', 'reason', 'duration', 'scheduled_date', 'status', 'created_at'];
            $sort_column = validateSortColumn($sort_column, $allowed_sort_columns, '');
            $sort_direction = validateSortDirection($sort_direction);
            
            // Fetch all Pending, Approved meetings and Scheduled meetings (today or future)
            $query = "SELECT * FROM meeting_requests 
                     WHERE (status IN ('Pending', 'Approved') OR (status = 'Scheduled' AND DATE(scheduled_date) >= ?))";
            
            // Add sorting
            if (!empty($sort_column) && $sort_direction !== 'default') {
                $direction = strtoupper($sort_direction) === 'DESC' ? 'DESC' : 'ASC';
                $query .= " ORDER BY ";
                
                // Handle special sorting cases
                if ($sort_column === 'scheduled_date') {
                    $query .= "COALESCE(scheduled_date, '9999-12-31 23:59:59') $direction";
                } elseif ($sort_column === 'status') {
                    $query .= "CASE LOWER(COALESCE(status, ''))
                        WHEN 'pending' THEN 1
                        WHEN 'approved' THEN 2
                        WHEN 'scheduled' THEN 3
                        WHEN 'completed' THEN 4
                        ELSE 99
                    END $direction";
                } elseif ($sort_column === 'doer_name') {
                    $query .= "LOWER(COALESCE(doer_name, '')) $direction";
                } elseif ($sort_column === 'reason') {
                    $query .= "LOWER(COALESCE(reason, '')) $direction";
                } elseif ($sort_column === 'duration') {
                    $query .= "CAST(COALESCE(duration, '00:00:00') AS TIME) $direction";
                } else {
                    $query .= "$sort_column $direction";
                }
            } else {
                $query .= " ORDER BY created_at DESC";
            }
            
            $stmt = mysqli_prepare($conn, $query);
            if (!$stmt) {
                throw new Exception('Database prepare error: ' . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($stmt, 's', $today);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $meetings = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $meetings[] = $row;
            }
            mysqli_stmt_close($stmt);
            
            echo json_encode([
                'success' => true,
                'meetings' => $meetings
            ]);
            break;
            
        case 'get_pending':
        case 'fetch_pending':
            // Legacy support - redirect to get_scheduled_meetings
            // Fetch pending meeting requests (for admin)
            if (!isAdmin()) {
                throw new Exception('Unauthorized: Admin access required');
            }
            
            $query = "SELECT * FROM meeting_requests WHERE status = 'Pending' ORDER BY created_at DESC";
            $result = mysqli_query($conn, $query);
            
            if (!$result) {
                throw new Exception('Database query error: ' . mysqli_error($conn));
            }
            
            $meetings = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $meetings[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'meetings' => $meetings
            ]);
            break;
            
        case 'get_scheduled':
        case 'fetch_scheduled':
            // Fetch scheduled meeting requests (for admin)
            if (!isAdmin()) {
                throw new Exception('Unauthorized: Admin access required');
            }
            
            $query = "SELECT * FROM meeting_requests WHERE status = 'Scheduled' ORDER BY scheduled_date ASC";
            $result = mysqli_query($conn, $query);
            
            if (!$result) {
                throw new Exception('Database query error: ' . mysqli_error($conn));
            }
            
            $meetings = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $meetings[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'meetings' => $meetings
            ]);
            break;
            
        case 'get_history':
        case 'fetch_history':
            // Fetch completed and past scheduled meeting requests (for admin)
            if (!isAdmin()) {
                throw new Exception('Unauthorized: Admin access required');
            }
            
            // Get sorting parameters
            $sort_column = isset($_GET['sort']) ? $_GET['sort'] : '';
            $sort_direction = isset($_GET['dir']) ? $_GET['dir'] : 'default';
            $allowed_sort_columns = ['doer_name', 'reason', 'duration', 'scheduled_date', 'status', 'updated_at'];
            $sort_column = validateSortColumn($sort_column, $allowed_sort_columns, '');
            $sort_direction = validateSortDirection($sort_direction);
            
            $today = date('Y-m-d');
            // Get Completed meetings and Scheduled meetings that are in the past
            $query = "SELECT * FROM meeting_requests 
                     WHERE status = 'Completed' 
                     OR (status = 'Scheduled' AND DATE(scheduled_date) < ?)";
            
            // Add sorting
            if (!empty($sort_column) && $sort_direction !== 'default') {
                $direction = strtoupper($sort_direction) === 'DESC' ? 'DESC' : 'ASC';
                $query .= " ORDER BY ";
                
                if ($sort_column === 'scheduled_date') {
                    $query .= "COALESCE(scheduled_date, '9999-12-31 23:59:59') $direction";
                } elseif ($sort_column === 'updated_at') {
                    $query .= "COALESCE(updated_at, '1970-01-01 00:00:00') $direction";
                } elseif ($sort_column === 'status') {
                    $query .= "CASE LOWER(COALESCE(status, ''))
                        WHEN 'pending' THEN 1
                        WHEN 'approved' THEN 2
                        WHEN 'scheduled' THEN 3
                        WHEN 'completed' THEN 4
                        ELSE 99
                    END $direction";
                } elseif ($sort_column === 'doer_name') {
                    $query .= "LOWER(COALESCE(doer_name, '')) $direction";
                } elseif ($sort_column === 'reason') {
                    $query .= "LOWER(COALESCE(reason, '')) $direction";
                } elseif ($sort_column === 'duration') {
                    $query .= "CAST(COALESCE(duration, '00:00:00') AS TIME) $direction";
                } else {
                    $query .= "$sort_column $direction";
                }
            } else {
                $query .= " ORDER BY scheduled_date DESC, created_at DESC";
            }
            
            $stmt = mysqli_prepare($conn, $query);
            if (!$stmt) {
                throw new Exception('Database prepare error: ' . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($stmt, 's', $today);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $meetings = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $meetings[] = $row;
            }
            mysqli_stmt_close($stmt);
            
            echo json_encode([
                'success' => true,
                'meetings' => $meetings
            ]);
            break;
            
        case 'schedule':
            // Schedule or Re-schedule a meeting (admin only)
            if (!isAdmin()) {
                throw new Exception('Unauthorized: Admin access required');
            }
            
            $meeting_id = $_POST['meeting_id'] ?? 0;
            $scheduled_date = $_POST['scheduled_date'] ?? '';
            $schedule_comment = trim($_POST['schedule_comment'] ?? '');
            
            if (empty($meeting_id) || empty($scheduled_date)) {
                throw new Exception('Missing required parameters');
            }
            
            // Validate date format
            $date_obj = DateTime::createFromFormat('Y-m-d H:i:s', $scheduled_date);
            if (!$date_obj || $date_obj->format('Y-m-d H:i:s') !== $scheduled_date) {
                throw new Exception('Invalid date format. Expected: Y-m-d H:i:s');
            }
            
            // Check if meeting exists and get current status
            $check_query = "SELECT status FROM meeting_requests WHERE id = ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($check_stmt, 'i', $meeting_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            $existing_meeting = mysqli_fetch_assoc($check_result);
            mysqli_stmt_close($check_stmt);
            
            if (!$existing_meeting) {
                throw new Exception('Meeting request not found');
            }
            
            $is_reschedule = ($existing_meeting['status'] === 'Scheduled');
            
            // Update meeting request (works for both new schedule and re-schedule)
            // Include schedule_comment if provided
            if (!empty($schedule_comment)) {
                $update_query = "UPDATE meeting_requests SET status = 'Scheduled', scheduled_date = ?, schedule_comment = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                
                if (!$stmt) {
                    throw new Exception('Database prepare error: ' . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($stmt, 'ssi', $scheduled_date, $schedule_comment, $meeting_id);
            } else {
                $update_query = "UPDATE meeting_requests SET status = 'Scheduled', scheduled_date = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                
                if (!$stmt) {
                    throw new Exception('Database prepare error: ' . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($stmt, 'si', $scheduled_date, $meeting_id);
            }
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to schedule meeting: ' . mysqli_stmt_error($stmt));
            }
            
            if (mysqli_stmt_affected_rows($stmt) === 0) {
                throw new Exception('Failed to update meeting request');
            }
            
            // Get meeting details for notification
            $get_query = "SELECT doer_name, doer_email, scheduled_date FROM meeting_requests WHERE id = ?";
            $get_stmt = mysqli_prepare($conn, $get_query);
            mysqli_stmt_bind_param($get_stmt, 'i', $meeting_id);
            mysqli_stmt_execute($get_stmt);
            $meeting_result = mysqli_stmt_get_result($get_stmt);
            $meeting = mysqli_fetch_assoc($meeting_result);
            mysqli_stmt_close($get_stmt);
            
            mysqli_stmt_close($stmt);
            
            // Format date for notification
            $formatted_date = date('F j, Y', strtotime($scheduled_date));
            $formatted_time = date('g:i A', strtotime($scheduled_date));
            
            $action_text = $is_reschedule ? 're-scheduled' : 'scheduled';
            
            // Get user ID from email
            $user_query = "SELECT id FROM users WHERE email = ?";
            $user_stmt = mysqli_prepare($conn, $user_query);
            mysqli_stmt_bind_param($user_stmt, 's', $meeting['doer_email']);
            mysqli_stmt_execute($user_stmt);
            $user_result = mysqli_stmt_get_result($user_stmt);
            $user = mysqli_fetch_assoc($user_result);
            mysqli_stmt_close($user_stmt);
            
            // Update original meeting request notification to remove action buttons
            // This handles both approve and reschedule actions
            $meeting_id_str = (string)$meeting_id;
            $notif_update_query = "UPDATE notifications SET action_required = 0, action_data = NULL WHERE related_id = ? AND type = 'meeting_request'";
            $notif_update_stmt = mysqli_prepare($conn, $notif_update_query);
            if ($notif_update_stmt) {
                mysqli_stmt_bind_param($notif_update_stmt, 's', $meeting_id_str);
                mysqli_stmt_execute($notif_update_stmt);
                mysqli_stmt_close($notif_update_stmt);
            }
            
            // Trigger notification for user
            if ($user) {
                if ($is_reschedule) {
                    triggerMeetingRescheduledNotification($conn, $meeting_id, $user['id'], $scheduled_date, date('H:i:s', strtotime($scheduled_date)));
                } else {
                    // For new schedule, create approved notification
                    $date_part = date('d/m/Y', strtotime($scheduled_date));
                    $time_part = date('h:i A', strtotime($scheduled_date));
                    createNotification(
                        $conn,
                        $user['id'],
                        'meeting_approved',
                        'Meeting Approved',
                        "Your meeting has been approved. Scheduled for {$date_part} at {$time_part}",
                        $meeting_id,
                        'meeting',
                        false,
                        null
                    );
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Meeting {$action_text} successfully",
                'notification' => "Your meeting has been {$action_text} for {$formatted_date} at {$formatted_time}.",
                'meeting' => $meeting
            ]);
            break;
            
        case 'get_my_scheduled':
            // Get scheduled meeting for current doer
            $doer_email = $_SESSION['username'] . '@company.com'; // Default fallback
            // Try to get actual email from users table
            if (!empty($_SESSION['id'])) {
                $user_query = "SELECT email FROM users WHERE id = ?";
                $user_stmt = mysqli_prepare($conn, $user_query);
                if ($user_stmt) {
                    mysqli_stmt_bind_param($user_stmt, 'i', $_SESSION['id']);
                    mysqli_stmt_execute($user_stmt);
                    $user_result = mysqli_stmt_get_result($user_stmt);
                    if ($user_row = mysqli_fetch_assoc($user_result)) {
                        $doer_email = $user_row['email'] ?? $doer_email;
                    }
                    mysqli_stmt_close($user_stmt);
                }
            }
            
            $query = "SELECT * FROM meeting_requests WHERE doer_email = ? AND status = 'Scheduled' ORDER BY scheduled_date ASC LIMIT 1";
            $stmt = mysqli_prepare($conn, $query);
            
            if (!$stmt) {
                throw new Exception('Database prepare error: ' . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($stmt, 's', $doer_email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $meeting = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            
            echo json_encode([
                'success' => true,
                'meeting' => $meeting
            ]);
            break;
            
        case 'get_meeting_details':
            // Get meeting details by ID (for re-schedule modal)
            if (!isAdmin()) {
                throw new Exception('Unauthorized: Admin access required');
            }
            
            $meeting_id = $_GET['meeting_id'] ?? 0;
            if (empty($meeting_id)) {
                throw new Exception('Meeting ID is required');
            }
            
            $query = "SELECT * FROM meeting_requests WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'i', $meeting_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $meeting = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            
            if (!$meeting) {
                throw new Exception('Meeting not found');
            }
            
            echo json_encode([
                'success' => true,
                'meeting' => $meeting
            ]);
            break;
            
        case 'get_my_all':
            // Get all meetings for current doer (pending, scheduled, completed)
            $doer_email = $_SESSION['username'] . '@company.com'; // Default fallback
            // Try to get actual email from users table
            if (!empty($_SESSION['id'])) {
                $user_query = "SELECT email FROM users WHERE id = ?";
                $user_stmt = mysqli_prepare($conn, $user_query);
                if ($user_stmt) {
                    mysqli_stmt_bind_param($user_stmt, 'i', $_SESSION['id']);
                    mysqli_stmt_execute($user_stmt);
                    $user_result = mysqli_stmt_get_result($user_stmt);
                    if ($user_row = mysqli_fetch_assoc($user_result)) {
                        $doer_email = $user_row['email'] ?? $doer_email;
                    }
                    mysqli_stmt_close($user_stmt);
                }
            }
            
            // Get sorting parameters
            $sort_column = isset($_GET['sort']) ? $_GET['sort'] : '';
            $sort_direction = isset($_GET['dir']) ? $_GET['dir'] : 'default';
            $allowed_sort_columns = ['reason', 'duration', 'scheduled_date', 'status'];
            $sort_column = validateSortColumn($sort_column, $allowed_sort_columns, '');
            $sort_direction = validateSortDirection($sort_direction);
            
            $sql = "SELECT * FROM meeting_requests 
                    WHERE doer_email = ?";
            
            // Add sorting
            if (!empty($sort_column) && $sort_direction !== 'default') {
                $direction = strtoupper($sort_direction) === 'DESC' ? 'DESC' : 'ASC';
                $sql .= " ORDER BY ";
                
                if ($sort_column === 'scheduled_date') {
                    $sql .= "COALESCE(scheduled_date, '9999-12-31 23:59:59') $direction";
                } elseif ($sort_column === 'status') {
                    $sql .= "CASE LOWER(COALESCE(status, ''))
                        WHEN 'pending' THEN 1
                        WHEN 'approved' THEN 2
                        WHEN 'scheduled' THEN 3
                        WHEN 'completed' THEN 4
                        ELSE 99
                    END $direction";
                } elseif ($sort_column === 'reason') {
                    $sql .= "LOWER(COALESCE(reason, '')) $direction";
                } elseif ($sort_column === 'duration') {
                    $sql .= "CAST(COALESCE(duration, '00:00:00') AS TIME) $direction";
                } else {
                    $sql .= "$sort_column $direction";
                }
            } else {
                $sql .= " ORDER BY created_at DESC";
            }
            
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 's', $doer_email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $meetings = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $meetings[] = $row;
            }
            mysqli_stmt_close($stmt);
            
            echo json_encode([
                'success' => true,
                'meetings' => $meetings
            ]);
            break;
            
        case 'approve':
            // Approve a meeting request (admin only) - Auto-schedule with preferred date and time
            if (!isAdmin()) {
                throw new Exception('Unauthorized: Admin access required');
            }
            
            $meeting_id = $_POST['meeting_id'] ?? 0;
            
            if (empty($meeting_id)) {
                throw new Exception('Meeting ID is required');
            }
            
            // Check if meeting exists and is pending, get preferred_date and preferred_time
            $check_query = "SELECT status, preferred_date, preferred_time FROM meeting_requests WHERE id = ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($check_stmt, 'i', $meeting_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            $existing_meeting = mysqli_fetch_assoc($check_result);
            mysqli_stmt_close($check_stmt);
            
            if (!$existing_meeting) {
                throw new Exception('Meeting request not found');
            }
            
            if ($existing_meeting['status'] !== 'Pending') {
                throw new Exception('Only pending meetings can be approved');
            }
            
            // Check if preferred_date and preferred_time are available
            if (empty($existing_meeting['preferred_date'])) {
                throw new Exception('Preferred date is required to approve and schedule the meeting');
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
                    throw new Exception('Invalid date/time format');
                }
            }
            
            // Update meeting status to Scheduled and set scheduled_date
            $update_query = "UPDATE meeting_requests SET status = 'Scheduled', scheduled_date = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            
            if (!$stmt) {
                throw new Exception('Database prepare error: ' . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($stmt, 'si', $scheduled_date, $meeting_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to approve meeting: ' . mysqli_stmt_error($stmt));
            }
            
            if (mysqli_stmt_affected_rows($stmt) === 0) {
                throw new Exception('Failed to update meeting request');
            }
            
            mysqli_stmt_close($stmt);
            
            // Get meeting details for notification
            $get_query = "SELECT doer_email, scheduled_date FROM meeting_requests WHERE id = ?";
            $get_stmt = mysqli_prepare($conn, $get_query);
            mysqli_stmt_bind_param($get_stmt, 'i', $meeting_id);
            mysqli_stmt_execute($get_stmt);
            $meeting_result = mysqli_stmt_get_result($get_stmt);
            $meeting_data = mysqli_fetch_assoc($meeting_result);
            mysqli_stmt_close($get_stmt);
            
            // Get user ID and create notification
            if ($meeting_data) {
                $user_query = "SELECT id FROM users WHERE email = ?";
                $user_stmt = mysqli_prepare($conn, $user_query);
                mysqli_stmt_bind_param($user_stmt, 's', $meeting_data['doer_email']);
                mysqli_stmt_execute($user_stmt);
                $user_result = mysqli_stmt_get_result($user_stmt);
                $user = mysqli_fetch_assoc($user_result);
                mysqli_stmt_close($user_stmt);
                
                if ($user && $meeting_data['scheduled_date']) {
                    $date_part = date('d/m/Y', strtotime($meeting_data['scheduled_date']));
                    $time_part = date('h:i A', strtotime($meeting_data['scheduled_date']));
                    createNotification(
                        $conn,
                        $user['id'],
                        'meeting_approved',
                        'Meeting Approved',
                        "Your meeting has been approved. Scheduled for {$date_part} at {$time_part}",
                        $meeting_id,
                        'meeting',
                        false,
                        null
                    );
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Meeting approved and scheduled successfully'
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    ob_end_flush();
    exit;
} catch (Error $e) {
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
    ob_end_flush();
    exit;
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
?>

