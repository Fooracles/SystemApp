<?php
// Suppress all output except JSON
error_reporting(0);
ini_set('display_errors', 0);
ob_clean();

require_once "../includes/config.php";
require_once "../includes/functions.php";
require_once "../includes/dashboard_components.php";

session_start();

// Set proper headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$current_user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
$target_username = isset($_GET['username']) ? trim($_GET['username']) : '';

if(empty($target_username)) {
    echo json_encode(['success' => false, 'error' => 'Username parameter is required']);
    exit;
}

// Parse date range parameters
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;

// Validate dates
if ($date_from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $date_from = null;
}
if ($date_to && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $date_to = null;
}

try {
    // Get target user information
    $target_user = null;
    $sql_user = "SELECT id, username, name, user_type, manager_id, department_id FROM users WHERE username = ?";
    if($stmt_user = mysqli_prepare($conn, $sql_user)) {
        mysqli_stmt_bind_param($stmt_user, "s", $target_username);
        mysqli_stmt_execute($stmt_user);
        $result_user = mysqli_stmt_get_result($stmt_user);
        if($row_user = mysqli_fetch_assoc($result_user)) {
            $target_user = $row_user;
        }
        mysqli_stmt_close($stmt_user);
    }
    
    if(!$target_user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    // Access control based on role
    if(isAdmin()) {
        // Admin can view anyone (no additional check needed)
    } else if(isManager()) {
        // Manager can view their own performance + only their direct Doers
        if($target_user['id'] != $current_user_id) {
            // If not viewing self, must be a doer under their management
            if($target_user['user_type'] !== 'doer' || $target_user['manager_id'] != $current_user_id) {
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit;
            }
        }
    } else if(isDoer()) {
        // Doer can only view their own performance
        if($target_user['id'] != $current_user_id) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    // Get user's department name
    $department_name = 'N/A';
    if(!empty($target_user['department_id'])) {
        $sql_dept = "SELECT name FROM departments WHERE id = ?";
        if($stmt_dept = mysqli_prepare($conn, $sql_dept)) {
            mysqli_stmt_bind_param($stmt_dept, "i", $target_user['department_id']);
            mysqli_stmt_execute($stmt_dept);
            $result_dept = mysqli_stmt_get_result($stmt_dept);
            if($row_dept = mysqli_fetch_assoc($result_dept)) {
                $department_name = $row_dept['name'];
            }
            mysqli_stmt_close($stmt_dept);
        }
    }
    
    // Calculate user's performance stats with date filtering
    $user_stats = calculatePersonalStats($conn, $target_user['id'], $target_user['username'], $date_from, $date_to);
    if (!is_array($user_stats)) {
        $user_stats = [
            'completed_on_time' => 0,
            'current_pending' => 0,
            'current_delayed' => 0,
            'delayed_completed_tasks' => 0,
            'all_delayed_tasks' => 0,
            'total_tasks' => 0,
            'wnd' => 0,
            'wnd_on_time' => 0
        ];
    } else {
        // Map current_delayed to all_delayed_tasks for frontend compatibility
        $user_stats['all_delayed_tasks'] = $user_stats['current_delayed'] ?? 0;
    }
    
    $completion_rate = 0;
    if (isset($user_stats['total_tasks']) && $user_stats['total_tasks'] > 0) {
        $completion_rate = round(($user_stats['completed_on_time'] / $user_stats['total_tasks']) * 100, 2);
    }
    
    // Calculate previous period stats for comparison
    $previous_stats = null;
    $previous_completion_rate = 0;
    if ($date_from && $date_to) {
        $from_date = new DateTime($date_from);
        $to_date = new DateTime($date_to);
        $period_days = $from_date->diff($to_date)->days;
        
        $prev_to_date = clone $from_date;
        $prev_to_date->modify('-1 day');
        $prev_from_date = clone $prev_to_date;
        $prev_from_date->modify('-' . $period_days . ' days');
        
        $previous_stats = calculatePersonalStats($conn, $target_user['id'], $target_user['username'], 
            $prev_from_date->format('Y-m-d'), $prev_to_date->format('Y-m-d'));
        if (is_array($previous_stats) && isset($previous_stats['total_tasks']) && $previous_stats['total_tasks'] > 0) {
            $previous_completion_rate = round(($previous_stats['completed_on_time'] / $previous_stats['total_tasks']) * 100, 2);
        }
    }
    
    // Get weekly trend data with WND, WND On Time, and Performance Score
    $weekly_trend = [];
    if ($date_from && $date_to) {
        $start = new DateTime($date_from);
        $end = new DateTime($date_to);
        $current = clone $start;
        
        while ($current <= $end) {
            $week_start = clone $current;
            $week_end = clone $current;
            $week_end->modify('+6 days');
            if ($week_end > $end) {
                $week_end = clone $end;
            }
            
            $week_start_str = $week_start->format('Y-m-d');
            $week_end_str = $week_end->format('Y-m-d');
            
            $week_stats = calculatePersonalStats($conn, $target_user['id'], $target_user['username'],
                $week_start_str, $week_end_str);
            
            // Get RQC score for this week
            $week_rqc = getRqcScore($conn, $target_user['name'], $week_start_str, $week_end_str);
            
            // Calculate Performance Score for this week
            $week_wnd = $week_stats['wnd'] ?? 0;
            $week_wnd_on_time = $week_stats['wnd_on_time'] ?? 0;
            
            $week_wnd_score = ($week_wnd !== null && $week_wnd != 0) ? 100 - abs($week_wnd) : null;
            $week_wnd_on_time_score = ($week_wnd_on_time !== null && $week_wnd_on_time != 0) ? 100 - abs($week_wnd_on_time) : null;
            $week_rqc_valid = ($week_rqc !== null && $week_rqc > 0);
            
            $week_available_scores = [];
            if ($week_rqc_valid) $week_available_scores[] = $week_rqc;
            if ($week_wnd_score !== null) $week_available_scores[] = $week_wnd_score;
            if ($week_wnd_on_time_score !== null) $week_available_scores[] = $week_wnd_on_time_score;
            
            $week_performance_score = count($week_available_scores) > 0 
                ? round(array_sum($week_available_scores) / count($week_available_scores), 2)
                : 0;
            
            $weekly_trend[] = [
                'week' => $week_start->format('M d') . ' - ' . $week_end->format('M d'),
                'completion_rate' => isset($week_stats['total_tasks']) && $week_stats['total_tasks'] > 0 
                    ? round(($week_stats['completed_on_time'] / $week_stats['total_tasks']) * 100, 2) 
                    : 0,
                'completed' => $week_stats['completed_on_time'] ?? 0,
                'total' => $week_stats['total_tasks'] ?? 0,
                'wnd' => $week_wnd,
                'wnd_on_time' => $week_wnd_on_time,
                'rqc' => $week_rqc !== null && $week_rqc > 0 ? $week_rqc : 0
            ];
            
            $current->modify('+7 days');
        }
    }
    
    // Get task breakdown by type
    $task_breakdown = [
        'delegation' => 0,
        'checklist' => 0,
        'fms' => 0
    ];
    
    // Count delegation tasks
    $delegation_sql = "SELECT COUNT(*) as count FROM tasks WHERE doer_id = ?";
    if ($date_from && $date_to) {
        $delegation_sql .= " AND planned_date >= ? AND planned_date <= ?";
    }
    if ($stmt = mysqli_prepare($conn, $delegation_sql)) {
        if ($date_from && $date_to) {
            mysqli_stmt_bind_param($stmt, "iss", $target_user['id'], $date_from, $date_to);
        } else {
            mysqli_stmt_bind_param($stmt, "i", $target_user['id']);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $task_breakdown['delegation'] = (int)$row['count'];
        }
        mysqli_stmt_close($stmt);
    }
    
    // Count checklist tasks
    $checklist_sql = "SELECT COUNT(*) as count FROM checklist_subtasks WHERE assignee = ?";
    if ($date_from && $date_to) {
        $checklist_sql .= " AND task_date >= ? AND task_date <= ?";
    }
    if ($stmt = mysqli_prepare($conn, $checklist_sql)) {
        if ($date_from && $date_to) {
            mysqli_stmt_bind_param($stmt, "sss", $target_user['username'], $date_from, $date_to);
        } else {
            mysqli_stmt_bind_param($stmt, "s", $target_user['username']);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $task_breakdown['checklist'] = (int)$row['count'];
        }
        mysqli_stmt_close($stmt);
    }
    
    // Count FMS tasks
    $fms_sql = "SELECT COUNT(*) as count FROM fms_tasks WHERE doer_name = ?";
    if ($date_from && $date_to) {
        // Parse FMS dates - this is simplified, actual implementation may need more complex parsing
        $fms_sql .= " AND (planned >= ? OR actual >= ?)";
    }
    if ($stmt = mysqli_prepare($conn, $fms_sql)) {
        if ($date_from && $date_to) {
            mysqli_stmt_bind_param($stmt, "sss", $target_user['username'], $date_from, $date_from);
        } else {
            mysqli_stmt_bind_param($stmt, "s", $target_user['username']);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $task_breakdown['fms'] = (int)$row['count'];
        }
        mysqli_stmt_close($stmt);
    }
    
    // Get team average (for comparison)
    $team_average = null;
    if (isManager() || isAdmin()) {
        $team_members = [];
        if (isAdmin()) {
            $team_sql = "SELECT id, username FROM users WHERE user_type = 'doer'";
            $result = mysqli_query($conn, $team_sql);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $team_members[] = $row;
                }
            }
        } else {
            $team_members = getManagerTeamMembers($conn, $current_user_id);
            if (!is_array($team_members)) {
                $team_members = [];
            }
        }
        
        $total_completion_rates = [];
        foreach ($team_members as $member) {
            $member_stats = calculatePersonalStats($conn, $member['id'], $member['username'], $date_from, $date_to);
            if (is_array($member_stats) && isset($member_stats['total_tasks']) && $member_stats['total_tasks'] > 0) {
                $member_rate = ($member_stats['completed_on_time'] / $member_stats['total_tasks']) * 100;
                $total_completion_rates[] = $member_rate;
            }
        }
        
        if (count($total_completion_rates) > 0) {
            $team_average = round(array_sum($total_completion_rates) / count($total_completion_rates), 2);
        }
    }
    
    // Get recent activity (last 10 tasks)
    $recent_activity = [];
    $activity_sql = "SELECT description, planned_date, planned_time, actual_date, actual_time, status, 'delegation' as task_type
                     FROM tasks WHERE doer_id = ? ORDER BY COALESCE(actual_date, planned_date) DESC, id DESC LIMIT 10";
    if ($stmt = mysqli_prepare($conn, $activity_sql)) {
        mysqli_stmt_bind_param($stmt, "i", $target_user['id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $recent_activity[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
    
    // Get team members list (for user selector)
    $team_members_list = [];
    if (isAdmin()) {
        $sql = "SELECT u.id, u.username, u.name, u.user_type, u.department_id, d.name as department_name
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE u.user_type IN ('manager', 'doer')
                ORDER BY u.name";
        $result = mysqli_query($conn, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $team_members_list[] = $row;
            }
        }
    } else if (isManager()) {
        // Manager can see themselves + their doers
        $sql_self = "SELECT u.id, u.username, u.name, u.user_type, u.department_id, d.name as department_name
                     FROM users u
                     LEFT JOIN departments d ON u.department_id = d.id
                     WHERE u.id = ?";
        if ($stmt = mysqli_prepare($conn, $sql_self)) {
            mysqli_stmt_bind_param($stmt, "i", $current_user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $team_members_list[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
        
        $team_members = getManagerTeamMembers($conn, $current_user_id);
        if (is_array($team_members)) {
            $team_members_list = array_merge($team_members_list, $team_members);
        }
    } else if (isDoer()) {
        // Doer can only see themselves
        $sql_self = "SELECT u.id, u.username, u.name, u.user_type, u.department_id, d.name as department_name
                     FROM users u
                     LEFT JOIN departments d ON u.department_id = d.id
                     WHERE u.id = ?";
        if ($stmt = mysqli_prepare($conn, $sql_self)) {
            mysqli_stmt_bind_param($stmt, "i", $target_user['id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $team_members_list[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Get RQC score
    $rqc_score = getRqcScore($conn, $target_user['name'], $date_from, $date_to);
    
    // Prepare response
    $response = [
        'success' => true,
        'data' => [
            'user' => [
                'id' => $target_user['id'],
                'username' => $target_user['username'],
                'name' => $target_user['name'],
                'department' => $department_name,
                'user_type' => $target_user['user_type']
            ],
            'stats' => $user_stats,
            'completion_rate' => $completion_rate,
            'rqc_score' => $rqc_score,
            'previous_stats' => $previous_stats ?: [],
            'previous_completion_rate' => $previous_completion_rate,
            'weekly_trend' => $weekly_trend,
            'task_breakdown' => $task_breakdown,
            'team_average' => $team_average,
            'recent_activity' => $recent_activity,
            'team_members' => $team_members_list
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>

