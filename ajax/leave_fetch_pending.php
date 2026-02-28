<?php
// Always keep JSON responses clean (no PHP warning HTML in output)
ob_start();
ini_set('display_errors', '0');
ini_set('html_errors', '0');

// IMPORTANT: include config BEFORE starting session (config sets session ini values)
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/dashboard_components.php';

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Get user information from request (passed from JavaScript)
$user_role = $_GET['user_role'] ?? 'admin';
$user_name = $_GET['user_name'] ?? '';
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : ($_SESSION['id'] ?? null);
$user_display_name = isset($_GET['user_display_name']) ? $_GET['user_display_name'] : ($_SESSION['name'] ?? $user_name);

// Debug: Log received parameters (only in development)
if (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) {
    error_log("Pending Requests - User Role: " . $user_role);
    error_log("Pending Requests - User Name: " . $user_name);
    error_log("Pending Requests - User ID: " . $user_id);
}

try {
    // Pagination parameters - like checklist_task.php
    $items_per_page = 30; // 30 records per page like leave_request.php
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($current_page < 1) $current_page = 1;
    $offset = ($current_page - 1) * $items_per_page;
    
    // Build WHERE clause for pending requests - ONLY PENDING status
    $where_conditions = ["(status = 'PENDING' OR status = '' OR status IS NULL)"];
    $where_params = [];
    $where_types = '';
    
    // Add user filtering based on user role
    if ($user_role === 'doer' && !empty($user_name)) {
        // Doer users can only see their own requests
        $where_conditions[] = "employee_name = ?";
        $where_params[] = $user_name;
        $where_types .= 's';
    } elseif ($user_role === 'manager' && !empty($user_name) && !empty($user_id)) {
        // Manager users should see:
        // 1. Their own leave requests (where employee_name matches manager's name/username)
        // 2. Team member leave requests (where employee_name matches team member's name/username)
        
        // Get manager's identifiers (name and username)
        $manager_identifiers = [];
        if (!empty($user_name)) {
            $manager_identifiers[] = trim($user_name);
        }
        if (!empty($user_display_name) && $user_display_name !== $user_name) {
            $manager_identifiers[] = trim($user_display_name);
        }
        
        // Get team member identifiers
        $team_members = getManagerTeamMembers($conn, (int)$user_id);
        foreach ($team_members as $member) {
            if (!empty($member['username'])) {
                $manager_identifiers[] = trim($member['username']);
            }
            if (!empty($member['name']) && $member['name'] !== ($member['username'] ?? '')) {
                $manager_identifiers[] = trim($member['name']);
            }
        }
        
        // Remove duplicates and empty values
        $manager_identifiers = array_unique(array_filter($manager_identifiers));
        
        if (!empty($manager_identifiers)) {
            // Build IN clause with placeholders
            $placeholders = implode(',', array_fill(0, count($manager_identifiers), '?'));
            $where_conditions[] = "employee_name IN ($placeholders)";
            $where_params = array_merge($where_params, $manager_identifiers);
            $where_types .= str_repeat('s', count($manager_identifiers));
        } else {
            // If no identifiers found, show no results
            $where_conditions[] = "1 = 0";
        }
    }
    
    $sql_where = " WHERE " . implode(" AND ", $where_conditions);
    
    
    // Get total count for pagination - like checklist_task.php
    $sql_count = "SELECT COUNT(*) as total_count FROM Leave_request" . $sql_where;
    $total_items = 0;
    if ($stmt_count = mysqli_prepare($conn, $sql_count)) {
        // Bind parameters for user filtering
        if (!empty($where_params) && !empty($where_types)) {
            mysqliBindParams($stmt_count, $where_types, $where_params);
        }
        
        if (mysqli_stmt_execute($stmt_count)) {
            $result_count = mysqli_stmt_get_result($stmt_count);
            $row_count = mysqli_fetch_assoc($result_count);
            $total_items = $row_count['total_count'] ?? 0;
        }
        mysqli_stmt_close($stmt_count);
    }
    $total_pages = ceil($total_items / $items_per_page);
    if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages; // Adjust if current page is out of bounds
    $offset = ($current_page - 1) * $items_per_page; // Recalculate offset
    
    // Get sorting parameters
    // Default: Sort by unique_service_no (Leave Request ID) in DESC order
    $sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'unique_service_no';
    $sort_direction = isset($_GET['dir']) ? strtoupper($_GET['dir']) : 'DESC';
    
    // Validate sort column
    $allowed_columns = ['employee_name', 'leave_type', 'duration', 'start_date', 'end_date', 'reason', 'manager_name', 'status', 'created_at', 'unique_service_no'];
    if (!in_array($sort_column, $allowed_columns)) {
        $sort_column = 'unique_service_no'; // Default: Leave Request ID
    }
    
    // Validate sort direction
    if ($sort_direction !== 'ASC' && $sort_direction !== 'DESC') {
        $sort_direction = 'DESC'; // Default: DESC order
    }
    
    // Build ORDER BY clause
    $order_by = "ORDER BY ";
    if ($sort_column === 'unique_service_no') {
        // Special handling for unique_service_no (numeric sort)
        $order_by .= "CAST(SUBSTRING_INDEX(unique_service_no, '-', -1) AS UNSIGNED) $sort_direction";
    } elseif ($sort_column === 'start_date' || $sort_column === 'end_date' || $sort_column === 'created_at') {
        // Date columns - handle NULL values
        $order_by .= "COALESCE($sort_column, '9999-12-31') $sort_direction";
    } elseif ($sort_column === 'duration') {
        // Duration - numeric sort
        $order_by .= "CAST(COALESCE($sort_column, 0) AS UNSIGNED) $sort_direction";
    } elseif ($sort_column === 'employee_name' || $sort_column === 'leave_type' || $sort_column === 'reason' || $sort_column === 'manager_name' || $sort_column === 'status') {
        // Text columns - case-insensitive
        $order_by .= "LOWER(COALESCE($sort_column, '')) $sort_direction";
    } else {
        $order_by .= "$sort_column $sort_direction";
    }
    
    // Fetch data for current page - like checklist_task.php
    $sql_select = "SELECT 
                unique_service_no,
                employee_name,
                leave_type,
                duration,
                start_date,
                end_date,
                reason,
                leave_count,
                manager_name,
                manager_email,
                department,
                status,
                created_at
              FROM Leave_request" . $sql_where . " " . $order_by . " LIMIT ? OFFSET ?";
    
    $requests = [];
    if ($stmt_select = mysqli_prepare($conn, $sql_select)) {
        // Bind parameters based on user role
        if (!empty($where_params) && !empty($where_types)) {
            // Manager or doer with filtering
            $bind_params = array_merge($where_params, [$items_per_page, $offset]);
            mysqliBindParams($stmt_select, $where_types . "ii", $bind_params);
        } else {
            // Admin or no filtering
            mysqli_stmt_bind_param($stmt_select, "ii", $items_per_page, $offset);
        }

        if (mysqli_stmt_execute($stmt_select)) {
            $result_subtasks = mysqli_stmt_get_result($stmt_select);
            while ($row = mysqli_fetch_assoc($result_subtasks)) {
                $requests[] = [
                    'unique_service_no' => $row['unique_service_no'],
                    'employee_name' => $row['employee_name'],
                    'leave_type' => $row['leave_type'],
                    'duration' => $row['duration'],
                    'start_date' => $row['start_date'],
                    'end_date' => $row['end_date'],
                    'reason' => $row['reason'],
                    'leave_count' => $row['leave_count'],
                    'manager_name' => $row['manager_name'],
                    'manager_email' => $row['manager_email'],
                    'department' => $row['department'],
                    'status' => $row['status'],
                    'created_at' => $row['created_at']
                ];
            }
        }
        mysqli_stmt_close($stmt_select);
    }
    
    // Calculate pagination info - like checklist_task.php
    $start_record = $offset + 1;
    $end_record = min($offset + $items_per_page, $total_items);
    
    // Ensure no stray output corrupts JSON
    if (ob_get_length()) {
        ob_clean();
    }

    echo json_encode([
        'success' => true,
        'data' => $requests,
        'count' => count($requests),
        'pagination' => [
            'current_page' => $current_page,
            'total_pages' => $total_pages,
            'total_records' => $total_items,
            'records_per_page' => $items_per_page,
            'start' => $start_record,
            'end' => $end_record
        ]
    ]);
    
} catch (Throwable $e) {
    error_log("Error fetching pending requests: " . $e->getMessage());
    http_response_code(500);
    if (ob_get_length()) {
        ob_clean();
    }
    handleException($e, 'leave_fetch_pending');
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
    if (ob_get_level()) {
        ob_end_flush();
    }
}
?>