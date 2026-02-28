<?php
// Always keep JSON responses clean and sessions consistent.
// IMPORTANT: include config BEFORE starting/reading session (config sets session ini values)
ob_start();
ini_set('display_errors', '0');
ini_set('html_errors', '0');

require_once "../includes/config.php";
require_once "../includes/functions.php"; // functions.php auto-starts session safely
require_once "../includes/dashboard_components.php";

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Check if user is logged in
if (!isLoggedIn()) {
    if (ob_get_length()) {
        ob_clean();
    }
    jsonError('Unauthorized', 401);
}

$current_user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;

try {
    $users = [];
    
    if (isAdmin()) {
        // Admin: Only show active users in performance dropdown
        $sql = "SELECT u.id, u.username, u.name, u.user_type, u.manager_id, u.department_id, 
                       COALESCE(u.Status, 'Active') as status, d.name as department_name
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE u.user_type IN ('admin', 'manager', 'doer')
                  AND COALESCE(u.Status, 'Active') = 'Active'
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
        
        // Then add their direct doers (active only)
        $team_members = getManagerTeamMembers($conn, $current_user_id);
        if (is_array($team_members)) {
            foreach ($team_members as $member) {
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
                if ($member['status'] === 'Active') {
                    $users[] = $member;
                }
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
    
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode(['success' => true, 'data' => $users]);
    
} catch (Throwable $e) {
    if (ob_get_length()) {
        ob_clean();
    }
    handleException($e, 'get_performance_users');
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
    if (ob_get_level()) {
        ob_end_flush();
    }
}
?>

