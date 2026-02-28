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

// Check if user is logged in and is an admin
if (!isLoggedIn() || !isAdmin()) {
    jsonError('Unauthorized', 401);
}

$current_user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
$current_username = $_SESSION['username'] ?? '';

// Get date range parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : null;

// Get personal date range parameters (for Personal Overview section)
$personal_date_from = isset($_GET['personal_date_from']) ? $_GET['personal_date_from'] : null;
$personal_date_to = isset($_GET['personal_date_to']) ? $_GET['personal_date_to'] : null;
$personal_date_range = isset($_GET['personal_date_range']) ? $_GET['personal_date_range'] : null;

// Handle "all" option for system overview - set dates to null to return all data
if ($date_range === 'all') {
    $date_from = null;
    $date_to = null;
} elseif ($date_range && (!$date_from || !$date_to)) {
    $range_map = [
        '7d' => 7,
        '14d' => 14,
        '28d' => 28
    ];
    if (isset($range_map[$date_range])) {
        $end = new DateTime(); // today
        $start = clone $end;
        $start->modify('-' . ($range_map[$date_range] - 1) . ' days');
        $date_from = $start->format('Y-m-d');
        $date_to = $end->format('Y-m-d');
    }
}

// Handle "all" option for personal overview - set dates to null to return all data
if ($personal_date_range === 'all') {
    $personal_date_from = null;
    $personal_date_to = null;
} elseif ($personal_date_range && (!$personal_date_from || !$personal_date_to)) {
    $range_map = [
        '7d' => 7,
        '14d' => 14,
        '28d' => 28
    ];
    if (isset($range_map[$personal_date_range])) {
        $end = new DateTime(); // today
        $start = clone $end;
        $start->modify('-' . ($range_map[$personal_date_range] - 1) . ' days');
        $personal_date_from = $start->format('Y-m-d');
        $personal_date_to = $end->format('Y-m-d');
    }
}

try {
    // Validate connection
    if (!$conn || mysqli_connect_errno()) {
        throw new Exception('Database connection failed');
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
    
    // Get personal stats (admin's own tasks)
    $personal_stats = calculatePersonalStats($conn, $current_user_id, $current_username, $personal_date_from, $personal_date_to);
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
    // For 7D (default), show last updated score (no date filter)
    // For other ranges, apply date range filter
    if ($personal_date_range === '7d' || empty($personal_date_range)) {
        // Default 7D: Get most recent RQC score (auto-fetch last updated)
        $personal_rqc_score = getRqcScore($conn, $current_user_name, null, null);
    } else {
        // Other ranges: Apply date range filter
        $personal_rqc_score = getRqcScore($conn, $current_user_name, $personal_date_from, $personal_date_to);
    }
    
    // Get system-wide stats using shared helper (honors date filters)
    $system_stats = calculateGlobalTaskStats($conn, $date_from, $date_to);
    $system_stats['completion_rate'] = 0;
    if (!empty($system_stats['total_tasks'])) {
        $system_stats['completion_rate'] = round(($system_stats['completed_tasks'] / $system_stats['total_tasks']) * 100, 2);
    }
    
    // Get user counts
    $user_counts = [
        'total_users' => 0,
        'admins' => 0,
        'managers' => 0,
        'doers' => 0
    ];
    
    $user_sql = "SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type";
    $user_result = mysqli_query($conn, $user_sql);
    if ($user_result) {
        while ($row = mysqli_fetch_assoc($user_result)) {
            $user_counts['total_users'] += $row['count'];
            switch($row['user_type']) {
                case 'admin':
                    $user_counts['admins'] = $row['count'];
                    break;
                case 'manager':
                    $user_counts['managers'] = $row['count'];
                    break;
                case 'doer':
                    $user_counts['doers'] = $row['count'];
                    break;
            }
        }
    }
    
    // Get all managers with their stats
    $managers = [];
    $manager_sql = "SELECT u.id, u.username, u.name, u.department_id, d.name as department_name
                    FROM users u
                    LEFT JOIN departments d ON u.department_id = d.id
                    WHERE u.user_type = 'manager'
                    ORDER BY u.name";
    
    $manager_result = mysqli_query($conn, $manager_sql);
    if ($manager_result) {
        while ($manager_row = mysqli_fetch_assoc($manager_result)) {
            $manager_team = getManagerTeamMembers($conn, $manager_row['id']);
            if (!is_array($manager_team)) {
                $manager_team = [];
            }
            $manager_team_ids = array_column($manager_team, 'id');
            $manager_team_stats = calculateTeamStats($conn, $manager_row['id'], $manager_team_ids);
            if (!is_array($manager_team_stats)) {
                $manager_team_stats = [
                    'total_tasks' => 0,
                    'completed_tasks' => 0,
                    'delayed_tasks' => 0,
                    'completion_rate' => 0
                ];
            }
            
            $managers[] = [
                'id' => $manager_row['id'],
                'name' => $manager_row['name'],
                'username' => $manager_row['username'],
                'department' => $manager_row['department_name'] ?? 'N/A',
                'team_size' => count($manager_team),
                'team_stats' => $manager_team_stats
            ];
        }
    }
    
    // Get all doers with their stats
    $doers = [];
    $doer_sql = "SELECT u.id, u.username, u.name, u.department_id, d.name as department_name,
                         u.manager_id, m.name as manager_name
                  FROM users u
                  LEFT JOIN departments d ON u.department_id = d.id
                  LEFT JOIN users m ON u.manager_id = m.id
                  WHERE u.user_type = 'doer'
                  ORDER BY u.name";
    
    $doer_result = mysqli_query($conn, $doer_sql);
    if ($doer_result) {
        while ($doer_row = mysqli_fetch_assoc($doer_result)) {
            $doer_stats = calculatePersonalStats($conn, $doer_row['id'], $doer_row['username'], $date_from, $date_to);
            $doer_completion_rate = 0;
            if ($doer_stats['total_tasks'] > 0) {
                $doer_completion_rate = round(($doer_stats['completed_on_time'] / $doer_stats['total_tasks']) * 100, 2);
            }
            
            $doers[] = [
                'id' => $doer_row['id'],
                'name' => $doer_row['name'],
                'username' => $doer_row['username'],
                'department' => $doer_row['department_name'] ?? 'N/A',
                'manager_name' => $doer_row['manager_name'] ?? 'Unassigned',
                'stats' => $doer_stats,
                'completion_rate' => $doer_completion_rate
            ];
        }
    }
    
    // Sort doers by completion rate
    usort($doers, function($a, $b) {
        return $b['completion_rate'] <=> $a['completion_rate'];
    });
    
    // Get leaderboard (all users) - support date range filtering
    // Use 0 for unlimited (show all users)
    $leaderboard_limit = isset($_GET['leaderboard_limit']) ? intval($_GET['leaderboard_limit']) : 0;
    if ($leaderboard_limit <= 0) {
        $leaderboard_limit = 0; // 0 means no limit - show all users
    }
    // Admin can see all ranks
    $leaderboard = getLeaderboardData($conn, $leaderboard_limit, null, $date_from, $date_to, true);
    if (!is_array($leaderboard)) {
        $leaderboard = [];
    }
    
    // Get team availability (all users)
    $team_availability = getTeamAvailabilityData($conn);
    if (!is_array($team_availability)) {
        $team_availability = [];
    }
    
    // Get recent tasks (last 10)
    $recent_tasks = [];
    $recent_tasks_sql = "SELECT t.id, t.unique_id, t.description, t.planned_date, t.planned_time, 
                                t.actual_date, t.actual_time, t.status, t.is_delayed,
                                COALESCE(t.doer_name, u.username, 'N/A') as doer_name,
                                d.name as department_name, 'delegation' as task_type
                         FROM tasks t
                         LEFT JOIN users u ON t.doer_id = u.id
                         LEFT JOIN departments d ON t.department_id = d.id
                         ORDER BY t.planned_date DESC, t.planned_time DESC
                         LIMIT 10";
    
    $recent_tasks_result = mysqli_query($conn, $recent_tasks_sql);
    if ($recent_tasks_result) {
        while ($task_row = mysqli_fetch_assoc($recent_tasks_result)) {
            $recent_tasks[] = $task_row;
        }
    }
    
    // Get department breakdown
    $department_stats = [];
    $dept_sql = "SELECT d.id, d.name, 
                        COUNT(DISTINCT t.id) as total_tasks,
                        COUNT(DISTINCT CASE WHEN t.status = 'completed' THEN t.id END) as completed_tasks
                 FROM departments d
                 LEFT JOIN tasks t ON d.id = t.department_id
                 GROUP BY d.id, d.name
                 ORDER BY d.name";
    
    $dept_result = mysqli_query($conn, $dept_sql);
    if ($dept_result) {
        while ($dept_row = mysqli_fetch_assoc($dept_result)) {
            $dept_completion_rate = 0;
            $total_tasks = intval($dept_row['total_tasks'] ?? 0);
            $completed_tasks = intval($dept_row['completed_tasks'] ?? 0);
            if ($total_tasks > 0) {
                $dept_completion_rate = round(($completed_tasks / $total_tasks) * 100, 2);
            }
            
            $department_stats[] = [
                'id' => intval($dept_row['id'] ?? 0),
                'name' => $dept_row['name'] ?? 'Unknown',
                'total_tasks' => $total_tasks,
                'completed_tasks' => $completed_tasks,
                'completion_rate' => $dept_completion_rate
            ];
        }
    }
    if (!is_array($department_stats)) {
        $department_stats = [];
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
            'system_stats' => $system_stats,
            'user_counts' => $user_counts,
            'managers' => $managers,
            'doers' => $doers,
            'leaderboard' => $leaderboard,
            'team_availability' => $team_availability,
            'recent_tasks' => $recent_tasks,
            'department_stats' => $department_stats,
            'last_updated' => date('M d, Y H:i')
        ]
    ]);
    
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    handleException($e, 'admin_dashboard_data');
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
