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

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$current_user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;

try {
    $users = [];
    
    if (isAdmin()) {
        // Admin: Can view any user (active or inactive) from Manage Users page
        $sql = "SELECT u.id, u.username, u.name, u.user_type, u.manager_id, u.department_id, 
                       COALESCE(u.Status, 'Active') as status, d.name as department_name
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE u.user_type IN ('admin', 'manager', 'doer')
                ORDER BY u.user_type, u.name";
        $result = mysqli_query($conn, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $users[] = $row;
            }
        }
    } else if (isManager()) {
        // Manager: Can view own performance + direct doers (active or inactive)
        // First, add themselves
        $sql_self = "SELECT u.id, u.username, u.name, u.user_type, u.manager_id, u.department_id,
                            COALESCE(u.Status, 'Active') as status, d.name as department_name
                     FROM users u
                     LEFT JOIN departments d ON u.department_id = d.id
                     WHERE u.id = ?";
        if ($stmt = mysqli_prepare($conn, $sql_self)) {
            mysqli_stmt_bind_param($stmt, "i", $current_user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $users[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
        
        // Then add their direct doers
        $team_members = getManagerTeamMembers($conn, $current_user_id);
        if (is_array($team_members)) {
            foreach ($team_members as $member) {
                // Get status for each team member
                $sql_status = "SELECT COALESCE(Status, 'Active') as status FROM users WHERE id = ?";
                if ($stmt_status = mysqli_prepare($conn, $sql_status)) {
                    mysqli_stmt_bind_param($stmt_status, "i", $member['id']);
                    mysqli_stmt_execute($stmt_status);
                    $result_status = mysqli_stmt_get_result($stmt_status);
                    if ($row_status = mysqli_fetch_assoc($result_status)) {
                        $member['status'] = $row_status['status'];
                    } else {
                        $member['status'] = 'Active';
                    }
                    mysqli_stmt_close($stmt_status);
                } else {
                    $member['status'] = 'Active';
                }
                $users[] = $member;
            }
        }
    } else if (isDoer()) {
        // Doer: Can only view own performance (must be active)
        $sql_self = "SELECT u.id, u.username, u.name, u.user_type, u.manager_id, u.department_id,
                            COALESCE(u.Status, 'Active') as status, d.name as department_name
                     FROM users u
                     LEFT JOIN departments d ON u.department_id = d.id
                     WHERE u.id = ? AND COALESCE(u.Status, 'Active') = 'Active'";
        if ($stmt = mysqli_prepare($conn, $sql_self)) {
            mysqli_stmt_bind_param($stmt, "i", $current_user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $users[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    echo json_encode(['success' => true, 'data' => $users]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>

