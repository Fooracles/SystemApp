<?php
// Start output buffering first to catch any errors
if (ob_get_level() == 0) {
    ob_start();
} else {
    ob_clean();
}

// Enable error reporting for debugging (but don't display)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set error handler to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Clean any output
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        // Set headers
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        echo json_encode([
            'success' => false,
            'error' => $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line'],
            'type' => 'Fatal Error'
        ]);
        exit;
    }
});

// Set custom error handler for non-fatal errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Only handle fatal errors, let others pass through
    if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_level() > 0) {
            ob_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        echo json_encode([
            'success' => false,
            'error' => $errstr,
            'file' => basename($errfile),
            'line' => $errline,
            'type' => 'Error'
        ]);
        exit;
    }
    return false; // Let PHP handle other errors
});

try {
    session_start();
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => 'Session error: ' . $e->getMessage()]);
    exit;
}

try {
    require_once "../includes/config.php";
    // Include functions.php which contains isLoggedIn() and other helper functions
    require_once "../includes/functions.php";
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => 'Config error: ' . $e->getMessage()]);
    exit;
} catch (Error $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => 'Config fatal error: ' . $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
    exit;
}

// Set proper headers for JSON response
if (!headers_sent()) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
}

// Check if user is logged in
if (!function_exists('isLoggedIn')) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    echo json_encode(['success' => false, 'error' => 'isLoggedIn function not found. functions.php may not be loaded.']);
    exit;
}

if (!isLoggedIn()) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$current_user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
$current_username = $_SESSION['username'] ?? '';

// Get date range parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : null;

// Handle date range calculation
if ($date_range && (!$date_from || !$date_to)) {
    // Calculate week-based date ranges
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    // Get Monday of current week (week runs Monday to Sunday)
    $dayOfWeek = (int)$today->format('N'); // 1 = Monday, 7 = Sunday
    $mondayOfThisWeek = clone $today;
    if ($dayOfWeek == 7) { // Sunday
        $mondayOfThisWeek->modify('-6 days');
    } else {
        $mondayOfThisWeek->modify('-' . ($dayOfWeek - 1) . ' days');
    }
    
    switch($date_range) {
        case 'this_week':
            // Monday of current week to today (inclusive)
            $date_from = $mondayOfThisWeek->format('Y-m-d');
            $date_to = $today->format('Y-m-d');
            break;
            
        case 'last_week':
            // Monday to Sunday of last week
            $lastWeekMonday = clone $mondayOfThisWeek;
            $lastWeekMonday->modify('-7 days');
            $lastWeekSunday = clone $lastWeekMonday;
            $lastWeekSunday->modify('+6 days');
            $date_from = $lastWeekMonday->format('Y-m-d');
            $date_to = $lastWeekSunday->format('Y-m-d');
            break;
            
        case 'last_2_weeks':
            // Monday of 2 weeks ago to Sunday of last week
            $twoWeeksAgoMonday = clone $mondayOfThisWeek;
            $twoWeeksAgoMonday->modify('-14 days');
            $lastWeekSunday = clone $mondayOfThisWeek;
            $lastWeekSunday->modify('-1 day');
            $date_from = $twoWeeksAgoMonday->format('Y-m-d');
            $date_to = $lastWeekSunday->format('Y-m-d');
            break;
            
        case 'last_4_weeks':
            // Monday of 4 weeks ago to Sunday of last week
            $fourWeeksAgoMonday = clone $mondayOfThisWeek;
            $fourWeeksAgoMonday->modify('-28 days');
            $lastWeekSunday = clone $mondayOfThisWeek;
            $lastWeekSunday->modify('-1 day');
            $date_from = $fourWeeksAgoMonday->format('Y-m-d');
            $date_to = $lastWeekSunday->format('Y-m-d');
            break;
            
            
        default:
            // Default to this week if unknown range
            $date_from = $mondayOfThisWeek->format('Y-m-d');
            $date_to = $today->format('Y-m-d');
    }
}


try {
    // Check if database connection exists
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection not available');
    }
    
    // Use the shared calculatePersonalStats function from dashboard_components.php
    require_once "../includes/dashboard_components.php";
    
    // Calculate personal stats using the fixed function
    $personal_stats = calculatePersonalStats($conn, $current_user_id, $current_username, $date_from, $date_to);
    
    // Ensure personal_stats is an array
    if (!is_array($personal_stats)) {
        $personal_stats = [
            'completed_on_time' => 0,
            'current_pending' => 0,
            'current_delayed' => 0,
            'total_tasks' => 0,
            'total_tasks_all' => 0,
            'shifted_tasks' => 0,
            'wnd' => 0,
            'wnd_on_time' => 0
        ];
    }
    
    // Map to the format expected by doer dashboard frontend
    $stats = [
        'tasks_completed' => $personal_stats['completed_on_time'] ?? 0,  // TASK COMPLETED
        'task_pending' => $personal_stats['current_pending'] ?? 0,        // TASK PENDING
        'delayed_task' => $personal_stats['current_delayed'] ?? 0,       // DELAYED TASK
        'total' => $personal_stats['total_tasks'] ?? 0,
        'total_tasks_all' => $personal_stats['total_tasks_all'] ?? 0,      // Total Tasks (all statuses except "can't be done")
        'shifted_tasks' => $personal_stats['shifted_tasks'] ?? 0,         // Shifted Tasks
        'wnd_percent' => $personal_stats['wnd'] ?? 0,                    // WND as percentage
        'wnd_on_time_percent' => $personal_stats['wnd_on_time'] ?? 0   // WND On-Time as percentage
    ];
    
    // Get user's name for RQC score lookup
    $current_user_name = '';
    $user_name_query = "SELECT name FROM users WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $user_name_query)) {
        mysqli_stmt_bind_param($stmt, "i", $current_user_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $current_user_name = trim($row['name'] ?? '');
                if (!empty($current_user_name)) {
                    $name_parts = preg_split('/\s+/', $current_user_name);
                    $current_user_name = $name_parts[0] ?? $current_user_name;
                }
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // Get RQC score instead of completion rate
    // For this_week (default), show last updated score (no date filter)
    // For other ranges, apply date range filter
    if ($date_range === 'this_week' || empty($date_range)) {
        // Default this_week: Get most recent RQC score (auto-fetch last updated)
        $rqc_score = getRqcScore($conn, $current_user_name, null, null);
    } else {
        // Other ranges: Apply date range filter
        $rqc_score = getRqcScore($conn, $current_user_name, $date_from, $date_to);
    }
    
    // Get team members - both available and on leave today
    $team_members = [];
    $today = date('Y-m-d');
    
    // Step 1: Get users who are on leave today
    $on_leave_user_ids = [];
    $sql_on_leave = "SELECT DISTINCT u.id
            FROM users u
            INNER JOIN Leave_request lr ON (
                lr.employee_name = u.name OR lr.employee_name = u.username
            )
            WHERE u.user_type IN ('doer', 'manager')
                AND lr.status IN ('PENDING', 'Approve')
                AND lr.status NOT IN ('Reject', 'Cancelled')
                AND (
                    (lr.end_date IS NOT NULL AND lr.start_date <= ? AND lr.end_date >= ?)
                    OR
                    (lr.end_date IS NULL AND lr.start_date = ?)
                )";
    
    if ($stmt_on_leave = mysqli_prepare($conn, $sql_on_leave)) {
        mysqli_stmt_bind_param($stmt_on_leave, "sss", $today, $today, $today);
        if (mysqli_stmt_execute($stmt_on_leave)) {
            $result_on_leave = mysqli_stmt_get_result($stmt_on_leave);
            while($row = mysqli_fetch_assoc($result_on_leave)) {
                $on_leave_user_ids[] = $row['id'];
            }
        }
        mysqli_stmt_close($stmt_on_leave);
    }
    
    // Step 2: Get all users with their leave details (if on leave)
    $leave_query = "SELECT 
                        u.id, 
                        u.username, 
                        u.name, 
                        u.user_type,
                        MIN(lr.leave_type) as leave_type,
                        MIN(lr.duration) as duration,
                        MIN(lr.start_date) as start_date,
                        MAX(lr.end_date) as end_date,
                        MIN(lr.status) as leave_status
                    FROM users u
                    LEFT JOIN Leave_request lr ON (
                        (lr.employee_name = u.name OR lr.employee_name = u.username)
                        AND lr.status IN ('PENDING', 'Approve')
                        AND lr.status NOT IN ('Reject', 'Cancelled')
                        AND (
                            (lr.end_date IS NOT NULL AND lr.start_date <= ? AND lr.end_date >= ?)
                            OR
                            (lr.end_date IS NULL AND lr.start_date = ?)
                        )
                    )
                    WHERE u.user_type IN ('doer', 'manager')
                    GROUP BY u.id, u.username, u.name, u.user_type";
    
    // Build ORDER BY clause based on on-leave users
    if (count($on_leave_user_ids) > 0) {
        $on_leave_ids_str = implode(',', array_map('intval', $on_leave_user_ids));
        $leave_query .= " ORDER BY 
                    CASE WHEN u.id IN ({$on_leave_ids_str}) THEN 0 ELSE 1 END,
                    u.name
                LIMIT 100";
    } else {
        $leave_query .= " ORDER BY u.name LIMIT 100";
    }
    
    if ($stmt_leave = mysqli_prepare($conn, $leave_query)) {
        mysqli_stmt_bind_param($stmt_leave, "sss", $today, $today, $today);
        if (mysqli_stmt_execute($stmt_leave)) {
            $leave_result = mysqli_stmt_get_result($stmt_leave);
            if ($leave_result) {
                while ($row = mysqli_fetch_assoc($leave_result)) {
                    $user_id = $row['id'];
                    $is_on_leave = in_array($user_id, $on_leave_user_ids);
                    
                    $team_members[] = [
                        'id' => $user_id,
                        'name' => $row['name'],
                        'username' => $row['username'],
                        'status' => $is_on_leave ? 'on-leave' : 'available',
                        'leave_type' => $is_on_leave ? ($row['leave_type'] ?? '') : '',
                        'duration' => $is_on_leave ? ($row['duration'] ?? '') : '',
                        'start_date' => $is_on_leave ? ($row['start_date'] ?? '') : '',
                        'end_date' => $is_on_leave ? ($row['end_date'] ?? '') : '',
                        'leave_status' => $is_on_leave ? ($row['leave_status'] ?? '') : '',
                        'is_current_user' => ($user_id == $current_user_id)
                    ];
                }
            }
        }
        mysqli_stmt_close($stmt_leave);
    }
    
    // Get leaderboard data (using function from dashboard_components.php)
    // Non-admin users will only see top 3 + current user
    $is_admin = isAdmin();
    $leaderboard_data = getLeaderboardData($conn, 0, null, $date_from, $date_to, $is_admin);
    if (!is_array($leaderboard_data)) {
        $leaderboard_data = [];
    }
    
    // Clean any output before sending JSON
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'stats' => $stats,
            'completion_rate' => $rqc_score,
            'rqc_score' => $rqc_score,
            'trends' => [
                'completed_on_time' => 12,  // Calculate based on previous period
                'current_pending' => 0,
                'current_delayed' => -25,
                'completion_rate' => 8
            ],
            'team' => $team_members,  // Users on leave today
            'leaderboard' => $leaderboard_data,  // Leaderboard data
            'last_updated' => date('M d, Y H:i')
        ]
    ]);
    
    exit;
    
} catch (Exception $e) {
    // Clean any output before sending error
    if (ob_get_level() > 0) {
        ob_clean();
    }
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
    exit;
} catch (Error $e) {
    // Catch PHP 7+ errors (TypeError, ParseError, etc.)
    if (ob_get_level() > 0) {
        ob_clean();
    }
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'type' => get_class($e)
    ]);
    exit;
}
