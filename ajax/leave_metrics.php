<?php
/**
 * Leave Metrics AJAX Endpoint
 * Returns dynamic metrics based on current filters
 */

header('Content-Type: application/json');
require_once '../includes/config.php';

// Get user information from request (passed from JavaScript)
$user_role = $_GET['user_role'] ?? 'admin';
$user_name = $_GET['user_name'] ?? '';

// Debug: Log received parameters (only in development)
if (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) {
    error_log("Metrics - User Role: " . $user_role);
    error_log("Metrics - User Name: " . $user_name);
    error_log("Metrics - All GET params: " . json_encode($_GET));
}

// Debug: Add debug info to response
$debug_info = [
    'user_role' => $user_role,
    'user_name' => $user_name,
    'all_get_params' => $_GET,
    'where_conditions' => $where_conditions ?? [],
    'params' => $params ?? [],
    'param_types' => $param_types ?? ''
];

try {
    // Get filter parameters
    $status_filter = $_GET['status'] ?? '';
    $employee_filter = $_GET['employee'] ?? '';
    $leave_type_filter = $_GET['leave_type'] ?? '';
    $duration_filter = $_GET['duration'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    
    // Build common filter conditions that apply to all metrics (employee, leave_type, duration, dates)
    // NOTE: Status filter is intentionally NOT included here - it only filters the table, not scorecards
    // Scorecards show breakdown by status (Pending/Approved/Rejected/Cancelled) with other filters applied
    $common_filter_conditions = [];
    $common_filter_params = [];
    $common_filter_types = '';
    
    // Employee filter - applies to all scorecard counts
    if (!empty($employee_filter)) {
        $common_filter_conditions[] = "employee_name LIKE ?";
        $common_filter_params[] = "%$employee_filter%";
        $common_filter_types .= 's';
    }
    
    // Leave type filter - applies to all scorecard counts
    if (!empty($leave_type_filter)) {
        $common_filter_conditions[] = "leave_type = ?";
        $common_filter_params[] = $leave_type_filter;
        $common_filter_types .= 's';
    }
    
    // Duration filter - applies to all scorecard counts
    if (!empty($duration_filter)) {
        $common_filter_conditions[] = "duration = ?";
        $common_filter_params[] = $duration_filter;
        $common_filter_types .= 's';
    }
    
    // Date range filter (start_date) - applies to all scorecard counts
    if (!empty($date_from)) {
        $common_filter_conditions[] = "start_date >= ?";
        $common_filter_params[] = $date_from;
        $common_filter_types .= 's';
    }
    
    // Date range filter (end_date) - applies to all scorecard counts
    if (!empty($date_to)) {
        $common_filter_conditions[] = "end_date <= ?";
        $common_filter_params[] = $date_to;
        $common_filter_types .= 's';
    }
    
    // Get metrics for each status
    $metrics = [];
    
    // Helper function to build and execute count query
    $executeCountQuery = function($status_condition, $user_where_conditions, $user_params, $user_types) use ($conn, $common_filter_conditions, $common_filter_params, $common_filter_types) {
        // Merge user role conditions with status condition and common filters
        $all_conditions = array_merge($user_where_conditions, [$status_condition], $common_filter_conditions);
        $all_params = array_merge($user_params, $common_filter_params);
        $all_types = $user_types . $common_filter_types;
        
        $where_clause = !empty($all_conditions) ? 'WHERE ' . implode(' AND ', $all_conditions) : '';
        $query = "SELECT COUNT(*) as count FROM Leave_request " . $where_clause;
        
        $stmt = mysqli_prepare($conn, $query);
        if ($stmt) {
            if (!empty($all_types) && !empty($all_params)) {
                mysqli_stmt_bind_param($stmt, $all_types, ...$all_params);
            }
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            $count = (int)$row['count'];
            mysqli_stmt_close($stmt);
            return $count;
        }
        return 0;
    };
    
    // Pending count - Use Pending table user filtering logic + common filters
    $pending_user_conditions = [];
    $pending_user_params = [];
    $pending_user_types = '';
    
    if ($user_role === 'doer' && !empty($user_name)) {
        $pending_user_conditions[] = "employee_name = ?";
        $pending_user_params[] = $user_name;
        $pending_user_types .= 's';
    } elseif ($user_role === 'manager' && !empty($user_name)) {
        $pending_user_conditions[] = "manager_name = ?";
        $pending_user_params[] = $user_name;
        $pending_user_types .= 's';
    }
    
    $metrics['pending'] = $executeCountQuery(
        "(status = '' OR status IS NULL OR status = 'PENDING')",
        $pending_user_conditions,
        $pending_user_params,
        $pending_user_types
    );
    
    // Approved count - Use Total table user filtering logic + common filters
    $approved_user_conditions = [];
    $approved_user_params = [];
    $approved_user_types = '';
    
    if ($user_role === 'doer' && !empty($user_name)) {
        $approved_user_conditions[] = "employee_name = ?";
        $approved_user_params[] = $user_name;
        $approved_user_types .= 's';
    }
    // Note: Admins and Managers see all requests (no user filtering)
    
    $metrics['approved'] = $executeCountQuery(
        "status = 'Approve'",
        $approved_user_conditions,
        $approved_user_params,
        $approved_user_types
    );
    
    // Rejected count - Use Total table user filtering logic + common filters
    $rejected_user_conditions = [];
    $rejected_user_params = [];
    $rejected_user_types = '';
    
    if ($user_role === 'doer' && !empty($user_name)) {
        $rejected_user_conditions[] = "employee_name = ?";
        $rejected_user_params[] = $user_name;
        $rejected_user_types .= 's';
    }
    
    $metrics['rejected'] = $executeCountQuery(
        "status = 'Reject'",
        $rejected_user_conditions,
        $rejected_user_params,
        $rejected_user_types
    );
    
    // Cancelled count - Use Total table user filtering logic + common filters
    $cancelled_user_conditions = [];
    $cancelled_user_params = [];
    $cancelled_user_types = '';
    
    if ($user_role === 'doer' && !empty($user_name)) {
        $cancelled_user_conditions[] = "employee_name = ?";
        $cancelled_user_params[] = $user_name;
        $cancelled_user_types .= 's';
    }
    
    $metrics['cancelled'] = $executeCountQuery(
        "status = 'Cancelled'",
        $cancelled_user_conditions,
        $cancelled_user_params,
        $cancelled_user_types
    );
    
    // Total count - Use Total table user filtering logic + common filters (excluding status)
    $total_user_conditions = [];
    $total_user_params = [];
    $total_user_types = '';
    
    if ($user_role === 'doer' && !empty($user_name)) {
        $total_user_conditions[] = "employee_name = ?";
        $total_user_params[] = $user_name;
        $total_user_types .= 's';
    }
    
    // For total, exclude pending status (same as Total table logic)
    $total_status_condition = "(status != 'PENDING' AND status != '' AND status IS NOT NULL)";
    $all_total_conditions = array_merge($total_user_conditions, [$total_status_condition], $common_filter_conditions);
    $all_total_params = array_merge($total_user_params, $common_filter_params);
    $all_total_types = $total_user_types . $common_filter_types;
    
    $total_where = !empty($all_total_conditions) ? 'WHERE ' . implode(' AND ', $all_total_conditions) : '';
    $total_query = "SELECT COUNT(*) as count FROM Leave_request " . $total_where;
    
    $total_stmt = mysqli_prepare($conn, $total_query);
    if ($total_stmt) {
        if (!empty($all_total_types) && !empty($all_total_params)) {
            mysqli_stmt_bind_param($total_stmt, $all_total_types, ...$all_total_params);
        }
        mysqli_stmt_execute($total_stmt);
        $result = mysqli_stmt_get_result($total_stmt);
        $row = mysqli_fetch_assoc($result);
        $metrics['total'] = (int)$row['count'];
        mysqli_stmt_close($total_stmt);
    }
    
    // Update debug info with actual filter conditions
    $debug_info['common_filter_conditions'] = $common_filter_conditions;
    $debug_info['common_filter_params'] = $common_filter_params;
    
    echo json_encode([
        'success' => true,
        'metrics' => $metrics,
        'filters' => [
            'status' => $status_filter,
            'employee' => $employee_filter,
            'leave_type' => $leave_type_filter,
            'duration' => $duration_filter,
            'date_from' => $date_from,
            'date_to' => $date_to
        ],
        'debug' => $debug_info
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
