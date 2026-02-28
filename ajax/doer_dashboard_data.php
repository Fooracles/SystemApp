<?php
// Start output buffering first to catch any stray output
if (ob_get_level() == 0) {
    ob_start();
} else {
    ob_clean();
}

// Include core files (functions.php auto-starts session)
require_once "../includes/config.php";
require_once "../includes/functions.php";
require_once "../includes/dashboard_components.php";

// Re-suppress error display AFTER config loads (error_handler.php overrides it in dev)
ini_set('display_errors', 0);

// Performance: increase execution time for large datasets
set_time_limit(120);

// Set proper headers for JSON response
if (!headers_sent()) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
}

// Check if user is logged in
if (!isLoggedIn()) {
    jsonError('Not logged in', 401);
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
                AND (u.username IS NULL OR LOWER(u.username) <> 'admin')
                AND (u.name IS NULL OR LOWER(u.name) <> 'admin')
                AND COALESCE(u.Status, 'Active') = 'Active'
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
                        u.profile_photo,
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
                    AND (u.username IS NULL OR LOWER(u.username) <> 'admin')
                    AND (u.name IS NULL OR LOWER(u.name) <> 'admin')
                    AND COALESCE(u.Status, 'Active') = 'Active'
                    GROUP BY u.id, u.username, u.name, u.user_type, u.profile_photo";
    
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
                        'profile_photo' => $row['profile_photo'] ?? '',
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

// Close database connection
if (isset($conn) && $conn instanceof mysqli) {
    mysqli_close($conn);
}
