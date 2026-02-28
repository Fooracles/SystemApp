<?php
// Always keep JSON responses clean (no PHP warning HTML in output)
ob_start();
ini_set('html_errors', '0');

// IMPORTANT: include config BEFORE starting session (config sets session ini values)
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Get user information from request (passed from JavaScript)
$user_role = $_GET['user_role'] ?? 'admin';
$user_name = $_GET['user_name'] ?? '';


try {
    // Pagination parameters - like checklist_task.php
    $items_per_page = 30; // 30 records per page like leave_request.php
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($current_page < 1) $current_page = 1;
    $offset = ($current_page - 1) * $items_per_page;
    
    // Build WHERE conditions array
    $where_conditions = [];
    $params = [];
    $types = '';
    
    // EXCLUDE PENDING status records for Total Leave Request table
    $where_conditions[] = "(status != 'PENDING' AND status != '' AND status IS NOT NULL)";
    
    // Add user filtering based on user role
    if ($user_role === 'doer' && !empty($user_name)) {
        // Doer users can only see their own requests
        $where_conditions[] = "employee_name = ?";
        $params[] = $user_name;
        $types .= 's';
    }
    // Note: Managers see ALL requests in Total table (like Admin) - no filtering needed
    
    // Apply filters
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $where_conditions[] = "status = ?";
        $params[] = $_GET['status'];
        $types .= 's';
    }
    
    if (isset($_GET['employee']) && !empty($_GET['employee'])) {
        $where_conditions[] = "employee_name LIKE ?";
        $params[] = '%' . $_GET['employee'] . '%';
        $types .= 's';
    }
    
    if (isset($_GET['leave_type']) && !empty($_GET['leave_type'])) {
        $where_conditions[] = "leave_type = ?";
        $params[] = $_GET['leave_type'];
        $types .= 's';
    }
    
    if (isset($_GET['duration']) && !empty($_GET['duration'])) {
        $where_conditions[] = "duration = ?";
        $params[] = $_GET['duration'];
        $types .= 's';
    }
    
    if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
        $where_conditions[] = "start_date >= ?";
        $params[] = $_GET['start_date'];
        $types .= 's';
    }
    
    if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
        $where_conditions[] = "end_date <= ?";
        $params[] = $_GET['end_date'];
        $types .= 's';
    }
    
    // Build WHERE clause
    $sql_where = "";
    if (!empty($where_conditions)) {
        $sql_where = " WHERE " . implode(" AND ", $where_conditions);
    }
    
    
    // Get total count for pagination - like checklist_task.php
    $sql_count = "SELECT COUNT(*) as total_count FROM Leave_request" . $sql_where;
    $total_items = 0;
    if ($stmt_count = mysqli_prepare($conn, $sql_count)) {
        if (!empty($types) && !empty($params)) {
            mysqliBindParams($stmt_count, $types, $params);
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
    $allowed_columns = ['employee_name', 'leave_type', 'duration', 'start_date', 'end_date', 'reason', 'manager_name', 'status', 'created_at', 'unique_service_no', 'leave_count'];
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
    } elseif ($sort_column === 'duration' || $sort_column === 'leave_count') {
        // Numeric columns
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
    
    $leaves = [];
    if ($stmt_select = mysqli_prepare($conn, $sql_select)) {
        $bind_types = $types . "ii";
        $bind_params = array_merge($params, [$items_per_page, $offset]);
        mysqliBindParams($stmt_select, $bind_types, $bind_params);

        if (mysqli_stmt_execute($stmt_select)) {
            $result_subtasks = mysqli_stmt_get_result($stmt_select);
            while ($row = mysqli_fetch_assoc($result_subtasks)) {
                $leaves[] = [
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
        'data' => $leaves,
        'count' => count($leaves),
        'pagination' => [
            'current_page' => $current_page,
            'total_pages' => $total_pages,
            'total_records' => $total_items,
            'records_per_page' => $items_per_page,
            'start' => $start_record,
            'end' => $end_record
        ],
        'filters' => [
            'status' => $_GET['status'] ?? '',
            'employee' => $_GET['employee'] ?? '',
            'leave_type' => $_GET['leave_type'] ?? '',
            'duration' => $_GET['duration'] ?? '',
            'start_date' => $_GET['start_date'] ?? '',
            'end_date' => $_GET['end_date'] ?? ''
        ]
    ]);
    
} catch (Throwable $e) {
    error_log("Error fetching total leaves: " . $e->getMessage());
    http_response_code(500);
    if (ob_get_length()) {
        ob_clean();
    }
    handleException($e, 'leave_fetch_totals');
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
    if (ob_get_level()) {
        ob_end_flush();
    }
}
?>