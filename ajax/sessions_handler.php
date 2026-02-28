<?php
header('Content-Type: application/json');
require_once "../includes/config.php";
require_once "../includes/functions.php";

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    jsonError('Unauthorized', 401);
}

// CSRF protection for POST requests
csrfProtect();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Auto-logout inactive users before processing any request
autoLogoutInactiveUsers($conn);

try {
    switch ($action) {
        case 'get_sessions':
            getSessions();
            break;
        case 'export_sessions':
            exportSessions();
            break;
        case 'get_users':
            getUsers();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    handleException($e, 'sessions_handler');
}

/**
 * Automatically logout users who are marked as 'Inactive' in the users table
 */
function autoLogoutInactiveUsers($conn) {
    $logout_time = date('Y-m-d H:i:s');
    
    // Find all active sessions where the user is marked as 'Inactive' in the users table
    // Use COLLATE to handle collation mismatch between tables
    $sql = "UPDATE user_sessions us
            INNER JOIN users u ON us.username COLLATE utf8mb4_unicode_ci = u.username COLLATE utf8mb4_unicode_ci
            SET us.logout_time = ?,
                us.duration_seconds = TIMESTAMPDIFF(SECOND, us.login_time, ?),
                us.logout_reason = 'user_inactive',
                us.is_active = 0
            WHERE us.is_active = 1 
            AND (u.Status = 'Inactive' OR u.status = 'Inactive')";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ss", $logout_time, $logout_time);
        mysqli_stmt_execute($stmt);
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        if ($affected_rows > 0) {
            error_log("Auto-logged out $affected_rows session(s) for inactive users");
        }
    }
}

function getSessions() {
    global $conn;
    
    $view = $_POST['view'] ?? 'active';
    $username_filter = $_POST['username'] ?? '';
    $date_from = $_POST['date_from'] ?? '';
    $date_to = $_POST['date_to'] ?? '';
    $ip_filter = $_POST['ip'] ?? '';
    $device_filter = $_POST['device'] ?? '';
    $page = (int)($_POST['page'] ?? 1);
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    
    // Build WHERE clause
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if ($view === 'active') {
        $where_conditions[] = "is_active = 1";
    } else {
        $where_conditions[] = "is_active = 0";
    }
    
    if (!empty($username_filter)) {
        $where_conditions[] = "username LIKE ?";
        $params[] = '%' . $username_filter . '%';
        $types .= 's';
    }
    
    if (!empty($date_from)) {
        $where_conditions[] = "DATE(login_time) >= ?";
        $params[] = $date_from;
        $types .= 's';
    }
    
    if (!empty($date_to)) {
        $where_conditions[] = "DATE(login_time) <= ?";
        $params[] = $date_to;
        $types .= 's';
    }
    
    if (!empty($ip_filter)) {
        $where_conditions[] = "ip_address LIKE ?";
        $params[] = '%' . $ip_filter . '%';
        $types .= 's';
    }
    
    if (!empty($device_filter)) {
        $where_conditions[] = "device_info LIKE ?";
        $params[] = '%' . $device_filter . '%';
        $types .= 's';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM user_sessions $where_clause";
    $count_stmt = mysqli_prepare($conn, $count_sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($count_stmt, $types, ...$params);
    }
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $count_row = mysqli_fetch_assoc($count_result);
    $total = (int)$count_row['total'];
    $total_pages = ceil($total / $per_page);
    mysqli_stmt_close($count_stmt);
    
    // Get sessions
    $sql = "SELECT id, username, login_time, logout_time, duration_seconds, ip_address, device_info, is_active, logout_reason 
            FROM user_sessions 
            $where_clause 
            ORDER BY login_time DESC 
            LIMIT ? OFFSET ?";
    
    $types .= 'ii';
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $sessions = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $sessions[] = $row;
        }
        
        mysqli_stmt_close($stmt);
        
        echo json_encode([
            'success' => true,
            'sessions' => $sessions,
            'total' => $total,
            'total_pages' => $total_pages,
            'current_page' => $page
        ]);
    } else {
        handleDbError($conn, 'C:/xampp/htdocs/app-v5.5-new/ajax/sessions_handler.php');
    }
}

function exportSessions() {
    global $conn;
    
    $view = $_GET['view'] ?? 'history';
    $username_filter = $_GET['username'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $ip_filter = $_GET['ip'] ?? '';
    $device_filter = $_GET['device'] ?? '';
    
    // Build WHERE clause (same as getSessions)
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if ($view === 'active') {
        $where_conditions[] = "is_active = 1";
    } else {
        $where_conditions[] = "is_active = 0";
    }
    
    if (!empty($username_filter)) {
        $where_conditions[] = "username LIKE ?";
        $params[] = '%' . $username_filter . '%';
        $types .= 's';
    }
    
    if (!empty($date_from)) {
        $where_conditions[] = "DATE(login_time) >= ?";
        $params[] = $date_from;
        $types .= 's';
    }
    
    if (!empty($date_to)) {
        $where_conditions[] = "DATE(login_time) <= ?";
        $params[] = $date_to;
        $types .= 's';
    }
    
    if (!empty($ip_filter)) {
        $where_conditions[] = "ip_address LIKE ?";
        $params[] = '%' . $ip_filter . '%';
        $types .= 's';
    }
    
    if (!empty($device_filter)) {
        $where_conditions[] = "device_info LIKE ?";
        $params[] = '%' . $device_filter . '%';
        $types .= 's';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get all sessions (no pagination for export)
    $sql = "SELECT username, login_time, logout_time, duration_seconds, ip_address, device_info, logout_reason 
            FROM user_sessions 
            $where_clause 
            ORDER BY login_time DESC";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sessions_' . date('Y-m-d_His') . '.csv"');
    
    // Output CSV
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, ['Username', 'Login Time', 'Logout Time', 'Duration', 'IP Address', 'Device Info', 'Logout Reason']);
    
    // CSV data
    while ($row = mysqli_fetch_assoc($result)) {
        $duration = $row['duration_seconds'] !== null ? formatSessionDuration($row['duration_seconds']) : 'N/A';
        fputcsv($output, [
            $row['username'],
            $row['login_time'],
            $row['logout_time'] ?? 'N/A',
            $duration,
            $row['ip_address'],
            $row['device_info'] ?? 'Unknown',
            $row['logout_reason'] ?? 'N/A'
        ]);
    }
    
    mysqli_stmt_close($stmt);
    fclose($output);
    exit;
}

/**
 * Get all users for dropdown - only show usernames that exist in user_sessions table
 */
function getUsers() {
    global $conn;
    
    $users = array();
    // Get distinct usernames from user_sessions table and join with users table to get name
    $sql = "SELECT DISTINCT us.username, 
                   COALESCE(u.name, us.username) as name,
                   COALESCE(u.user_type, '') as user_type
            FROM user_sessions us
            LEFT JOIN users u ON us.username = u.username
            WHERE us.username IS NOT NULL 
              AND us.username != ''
            ORDER BY COALESCE(u.name, us.username), us.username";
    
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $users[] = [
                'username' => $row['username'],
                'name' => $row['name'],
                'user_type' => $row['user_type']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
    exit;
}

// Close database connection
if (isset($conn) && $conn instanceof mysqli) {
    mysqli_close($conn);
}
?>
