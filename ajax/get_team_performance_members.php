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

// Set appropriate content type header
if (!headers_sent()) {
    header('Content-Type: application/json');
}

// Check if user is logged in
if (!isLoggedIn()) {
    jsonError('Not authenticated', 500);
}

$current_user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
$members = [];

try {
    if (isAdmin()) {
        // Admin can view all members (Managers + Doers)
        $sql = "SELECT u.id, u.username, u.name, u.user_type, u.department_id, d.name as department_name
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE u.user_type IN ('manager', 'doer')
                ORDER BY u.user_type, u.name";
        $result = mysqli_query($conn, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $members[] = [
                    'id' => $row['id'],
                    'username' => $row['username'],
                    'name' => $row['name'],
                    'user_type' => $row['user_type'],
                    'department_id' => $row['department_id'],
                    'department_name' => $row['department_name'] ?? 'N/A'
                ];
            }
        }
    } else if (isManager()) {
        // Manager can view their own performance + only their direct Doers
        // First, add the manager themselves
        $sql_self = "SELECT u.id, u.username, u.name, u.user_type, u.department_id, d.name as department_name
                     FROM users u
                     LEFT JOIN departments d ON u.department_id = d.id
                     WHERE u.id = ?";
        if ($stmt = mysqli_prepare($conn, $sql_self)) {
            mysqli_stmt_bind_param($stmt, "i", $current_user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $members[] = [
                    'id' => $row['id'],
                    'username' => $row['username'],
                    'name' => $row['name'],
                    'user_type' => $row['user_type'],
                    'department_id' => $row['department_id'],
                    'department_name' => $row['department_name'] ?? 'N/A'
                ];
            }
            mysqli_stmt_close($stmt);
        }
        
        // Then add their direct doers
        $team_members = getManagerTeamMembers($conn, $current_user_id);
        if (is_array($team_members)) {
            foreach ($team_members as $member) {
                $members[] = [
                    'id' => $member['id'],
                    'username' => $member['username'],
                    'name' => $member['name'],
                    'user_type' => $member['user_type'],
                    'department_id' => $member['department_id'],
                    'department_name' => $member['department_name'] ?? 'N/A'
                ];
            }
        }
    } else if (isDoer()) {
        // Doer can view only their own performance
        $sql_self = "SELECT u.id, u.username, u.name, u.user_type, u.department_id, d.name as department_name
                     FROM users u
                     LEFT JOIN departments d ON u.department_id = d.id
                     WHERE u.id = ?";
        if ($stmt = mysqli_prepare($conn, $sql_self)) {
            mysqli_stmt_bind_param($stmt, "i", $current_user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $members[] = [
                    'id' => $row['id'],
                    'username' => $row['username'],
                    'name' => $row['name'],
                    'user_type' => $row['user_type'],
                    'department_id' => $row['department_id'],
                    'department_name' => $row['department_name'] ?? 'N/A'
                ];
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Clean any stray output before sending JSON
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    echo json_encode(['success' => true, 'data' => $members]);
    
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    handleException($e, 'get_team_performance_members');
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
