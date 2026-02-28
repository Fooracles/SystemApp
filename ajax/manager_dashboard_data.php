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

// Check if user is logged in and is a manager
if (!isLoggedIn() || (!isManager() && !isAdmin())) {
    jsonError('Unauthorized', 401);
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
    // Validate user ID
    if (empty($current_user_id) || !is_numeric($current_user_id)) {
        throw new Exception('Invalid user ID');
    }
    
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
    
    // Get personal stats (manager's own tasks)
    $personal_stats = calculatePersonalStats($conn, $current_user_id, $current_username, $date_from, $date_to);
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
    
    // Get RQC score instead of completion rate
    // For this_week (default), show last updated score (no date filter)
    // For other ranges, apply date range filter
    if ($date_range === 'this_week' || empty($date_range)) {
        // Default this_week: Get most recent RQC score (auto-fetch last updated)
        $personal_rqc_score = getRqcScore($conn, $current_user_name, null, null);
    } else {
        // Other ranges: Apply date range filter
        $personal_rqc_score = getRqcScore($conn, $current_user_name, $date_from, $date_to);
    }
    
    // Get team members
    $team_members = getManagerTeamMembers($conn, $current_user_id);
    if (!is_array($team_members)) {
        $team_members = [];
    }
    $team_member_ids = array_column($team_members, 'id');
    
    // Calculate team stats
    $team_stats = calculateTeamStats($conn, $current_user_id, $team_member_ids);
    if (!is_array($team_stats)) {
        $team_stats = [
            'total_tasks' => 0,
            'completed_tasks' => 0,
            'delayed_tasks' => 0,
            'completion_rate' => 0
        ];
    }
    
    // Get individual doer stats
    $doer_performance = [];
    foreach ($team_members as $member) {
        $doer_stats = calculatePersonalStats($conn, $member['id'], $member['username'], $date_from, $date_to);
        $doer_completion_rate = 0;
        if ($doer_stats['total_tasks'] > 0) {
            $doer_completion_rate = round(($doer_stats['completed_on_time'] / $doer_stats['total_tasks']) * 100, 2);
        }
        
        $doer_performance[] = [
            'id' => $member['id'],
            'name' => $member['name'],
            'username' => $member['username'],
            'department' => $member['department_name'] ?? 'N/A',
            'stats' => $doer_stats,
            'completion_rate' => $doer_completion_rate
        ];
    }
    
    // Sort doers by completion rate
    usort($doer_performance, function($a, $b) {
        return $b['completion_rate'] <=> $a['completion_rate'];
    });
    
    // Get leaderboard - Non-admin users will only see top 3 + current user
    // Pass date range if provided for period filtering
    $is_admin = isAdmin();
    $team_leaderboard = getLeaderboardData($conn, 0, null, $date_from, $date_to, $is_admin);
    if (!is_array($team_leaderboard)) {
        $team_leaderboard = [];
    }
    
    // Get team availability - Show all users (same as doer dashboard)
    $team_availability = getTeamAvailabilityData($conn);
    if (!is_array($team_availability)) {
        $team_availability = [];
    }
    
    // Get recent tasks for team (last 10)
    $recent_tasks = [];
    if (!empty($team_member_ids) && is_array($team_member_ids)) {
        $ids_str = implode(',', array_map('intval', $team_member_ids));
        if (!empty($ids_str)) {
            $tasks_sql = "SELECT t.id, t.unique_id, t.description, t.planned_date, t.planned_time, 
                                 t.actual_date, t.actual_time, t.status, t.is_delayed,
                                 COALESCE(t.doer_name, u.username, 'N/A') as doer_name,
                                 d.name as department_name, 'delegation' as task_type
                          FROM tasks t
                          LEFT JOIN users u ON t.doer_id = u.id
                          LEFT JOIN departments d ON t.department_id = d.id
                          WHERE t.doer_id IN ($ids_str)
                          ORDER BY t.planned_date DESC, t.planned_time DESC
                          LIMIT 10";
            
            $tasks_result = mysqli_query($conn, $tasks_sql);
            if ($tasks_result) {
                while ($task_row = mysqli_fetch_assoc($tasks_result)) {
                    $recent_tasks[] = $task_row;
                }
            }
        }
    }
    
    // Clean any stray output before sending JSON
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'personal_stats' => $personal_stats,
            'personal_completion_rate' => $personal_rqc_score,
            'personal_rqc_score' => $personal_rqc_score,
            'team_stats' => $team_stats,
            'team_members' => $team_members,
            'team_member_ids' => $team_member_ids,
            'doer_performance' => $doer_performance,
            'team_leaderboard' => $team_leaderboard,
            'team_availability' => $team_availability,
            'recent_tasks' => $recent_tasks,
            'last_updated' => date('M d, Y H:i')
        ]
    ]);
    
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    handleException($e, 'manager_dashboard_data');
} catch (Error $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}

// Close database connection
if (isset($conn) && $conn instanceof mysqli) {
    mysqli_close($conn);
}
?>
