<?php
// Suppress all output except JSON
error_reporting(0);
ini_set('display_errors', 0);
ob_clean();

session_start();
require_once "../includes/config.php";
require_once "../includes/functions.php";
require_once "../includes/dashboard_components.php";

// Set proper headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Check if user is logged in and is a manager or admin
if (!isLoggedIn() || (!isManager() && !isAdmin())) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$current_user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;

try {
    // Get team members
    $team_members = [];
    
    if (isAdmin()) {
        // Admin can see all doers
        $sql = "SELECT u.id, u.username, u.name, u.user_type, u.manager_id, u.department_id, d.name as department_name
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE u.user_type = 'doer'
                ORDER BY u.name";
        $result = mysqli_query($conn, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $team_members[] = $row;
            }
        }
    } else if (isManager()) {
        // Manager can only see their team members
        $team_members = getManagerTeamMembers($conn, $current_user_id);
        if (!is_array($team_members)) {
            $team_members = [];
        }
    }
    
    // Get performance data for each team member
    $team_performance = [];
    foreach ($team_members as $member) {
        $member_stats = calculatePersonalStats($conn, $member['id'], $member['username'], null, null);
        if (!is_array($member_stats)) {
            $member_stats = [
                'completed_on_time' => 0,
                'current_pending' => 0,
                'current_delayed' => 0,
                'total_tasks' => 0,
                'wnd' => 0,
                'wnd_on_time' => 0
            ];
        }
        
        $completion_rate = 0;
        if (isset($member_stats['total_tasks']) && $member_stats['total_tasks'] > 0) {
            $completion_rate = round(($member_stats['completed_on_time'] / $member_stats['total_tasks']) * 100, 2);
        }
        
        $team_performance[] = [
            'id' => $member['id'],
            'username' => $member['username'],
            'name' => $member['name'],
            'department' => $member['department_name'] ?? 'N/A',
            'stats' => $member_stats,
            'completion_rate' => $completion_rate
        ];
    }
    
    // Sort by completion rate (highest first)
    usort($team_performance, function($a, $b) {
        return $b['completion_rate'] <=> $a['completion_rate'];
    });
    
    // Prepare response
    $response = [
        'success' => true,
        'data' => $team_performance
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>

