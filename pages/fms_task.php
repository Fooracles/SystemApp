<?php
$page_title = "FMS Task List (Enhanced)";
require_once "../includes/header.php";
require_once "../includes/status_column_helpers.php";
require_once "../includes/sorting_helpers.php";

// Check if the user is logged in
redirectIfNotLoggedIn();

// --- Access Control: Admin Only ---
if(!isAdmin()) {
    if (isManager()) {
        header("location: manager_dashboard.php");
    } elseif (isDoer()) {
        header("location: doer_dashboard.php");
    } else {
        header("location: ../index.php");
    }
    exit;
}

// --- OPTIMIZED PAGINATION & FILTERING ---
$allowed_page_sizes = [20, 50, 100, 200];
$items_per_page = (isset($_GET['limit']) && in_array((int)$_GET['limit'], $allowed_page_sizes)) ? (int)$_GET['limit'] : 50;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Get filter parameters from URL
$filter_unique_key = $_GET['unique_key'] ?? '';
$filter_step_name = $_GET['step_name'] ?? '';
$filter_planned_from = $_GET['planned_from'] ?? '';
$filter_planned_to = $_GET['planned_to'] ?? '';
$filter_doer = $_GET['doer'] ?? '';
$filter_sheet = $_GET['sheet'] ?? '';
$filter_status = $_GET['status'] ?? '';

// Get doer_status filter (default to 'Active', only for Admin/Manager)
$doer_status = 'Active'; // Default value
if (isAdmin() || isManager()) {
    $doer_status = isset($_GET['doer_status']) ? $_GET['doer_status'] : 'Active';
    // Validate doer_status value
    if ($doer_status !== 'Active' && $doer_status !== 'Inactive') {
        $doer_status = 'Active';
    }
}

// Sorting parameters
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'planned';
$sort_direction = isset($_GET['dir']) ? $_GET['dir'] : 'desc';

// Validate sort parameters
$allowed_sort_columns = ['unique_key', 'step_name', 'planned', 'actual', 'status', 'duration', 'doer_name', 'sheet_label', 'step_code'];
$sort_column = validateSortColumn($sort_column, $allowed_sort_columns, 'planned');
$sort_direction = validateSortDirection($sort_direction);

// Build the WHERE clause for server-side filtering
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($filter_unique_key)) {
    $where_conditions[] = "fms_tasks.unique_key LIKE ?";
    $params[] = "%{$filter_unique_key}%";
    $param_types .= 's';
}
if (!empty($filter_step_name)) {
    $where_conditions[] = "fms_tasks.step_name LIKE ?";
    $params[] = "%{$filter_step_name}%";
    $param_types .= 's';
}

// Date range filtering - handle VARCHAR date format "DD/MM/YY HH:mm am/pm"
if (!empty($filter_planned_from) || !empty($filter_planned_to)) {
    // Convert input dates to timestamps for comparison
    $from_timestamp = !empty($filter_planned_from) ? strtotime($filter_planned_from) : 0;
    $to_timestamp = !empty($filter_planned_to) ? strtotime($filter_planned_to . ' 23:59:59') : PHP_INT_MAX;
    
    if ($from_timestamp || $to_timestamp !== PHP_INT_MAX) {
        // We'll filter in PHP after fetching the data to avoid complex MySQL date parsing
        // This ensures reliable date comparison regardless of database date format
        $date_filter_active = true;
        $date_filter_from = $from_timestamp;
        $date_filter_to = $to_timestamp;
    }
}

if (!empty($filter_doer)) {
    $where_conditions[] = "fms_tasks.doer_name = ?";
    $params[] = $filter_doer;
    $param_types .= 's';
}
if (!empty($filter_sheet)) {
    $where_conditions[] = "fms_tasks.sheet_label = ?";
    $params[] = $filter_sheet;
    $param_types .= 's';
}
if (!empty($filter_status)) {
    if ($filter_status === 'Pending') {
        $where_conditions[] = "(fms_tasks.status IS NULL OR fms_tasks.status = '' OR fms_tasks.status = 'Pending')";
    } else {
        $where_conditions[] = "fms_tasks.status = ?";
        $params[] = $filter_status;
        $param_types .= 's';
    }
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Add doer_status filter to count query (only for Admin/Manager) - matching checklist_task.php
$count_doer_status_where = "";
$count_doer_status_param_types = "";
$count_doer_status_param_values = [];
if (isAdmin() || isManager()) {
    // For count query, use the join base and filter by doer_status
    $count_doer_status_where = (empty($where_clause) ? " WHERE " : " AND ") . "(u.id IS NOT NULL AND COALESCE(u.Status, 'Active') = ?)";
    $count_doer_status_param_types = "s";
    $count_doer_status_param_values = [$doer_status];
}

// Get total count for pagination (with filters including doer_status)
$count_query = "SELECT COUNT(*) as total 
                FROM fms_tasks 
                LEFT JOIN users u ON LOWER(TRIM(fms_tasks.doer_name)) = LOWER(TRIM(u.username)) OR LOWER(TRIM(fms_tasks.doer_name)) = LOWER(TRIM(u.name))
                {$where_clause}{$count_doer_status_where}";
$count_stmt = $conn->prepare($count_query);
$count_params = array_merge($params, $count_doer_status_param_values);
$count_param_types = $param_types . $count_doer_status_param_types;
if (!empty($count_param_types)) {
    $count_stmt->bind_param($count_param_types, ...$count_params);
}
$count_stmt->execute();
$total_items = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Adjust current page if out of bounds
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $items_per_page;
}

// Add doer_status filter to SQL WHERE clause (only for Admin/Manager) - matching checklist_task.php
$doer_status_where = "";
$doer_status_param_types = "";
$doer_status_param_values = [];
if (isAdmin() || isManager()) {
    // Filter by doer_status at SQL level: only include tasks where user exists and status matches
    // This ensures tasks are filtered correctly from the start
    $doer_status_where = (empty($where_clause) ? " WHERE " : " AND ") . "(u.id IS NOT NULL AND COALESCE(u.Status, 'Active') = ?)";
    $doer_status_param_types = "s";
    $doer_status_param_values = [$doer_status];
}

// Get paginated tasks from database (with filters including doer_status)
// Join users table to get doer status for filtering (only for Admin/Manager)
$task_query = "SELECT fms_tasks.*, 
                      COALESCE(u.Status, 'Active') as doer_user_status, 
                      (u.id IS NOT NULL) as doer_user_exists 
               FROM fms_tasks 
               LEFT JOIN users u ON LOWER(TRIM(fms_tasks.doer_name)) = LOWER(TRIM(u.username)) OR LOWER(TRIM(fms_tasks.doer_name)) = LOWER(TRIM(u.name))
               {$where_clause}{$doer_status_where} 
               " . buildSmartOrderBy($sort_column, $sort_direction, 'fms_tasks.', ['planned' => 'DESC']) . " LIMIT ? OFFSET ?";
$task_stmt = $conn->prepare($task_query);

$limit_params = array_merge($params, $doer_status_param_values);
$limit_params[] = $items_per_page;
$limit_params[] = $offset;
$limit_param_types = $param_types . $doer_status_param_types . 'ii';

$task_stmt->bind_param($limit_param_types, ...$limit_params);
$task_stmt->execute();
$task_result = $task_stmt->get_result();

$paginated_tasks = [];
while ($row = $task_result->fetch_assoc()) {
    $paginated_tasks[] = $row;
}

// Apply PHP-based date filtering if date filters are active
if (isset($date_filter_active) && $date_filter_active) {
    $filtered_tasks = [];
    
    foreach ($paginated_tasks as $task) {
        $planned_timestamp = null;
        $actual_timestamp = null;
        
        // Parse planned date (DD/MM/YY HH:mm am/pm)
        if (!empty($task['planned'])) {
            $planned_timestamp = parseFMSDateTimeString_my_task($task['planned']);
        }
        
        // Parse actual date (DD/MM/YY HH:mm am/pm)
        if (!empty($task['actual'])) {
            $actual_timestamp = parseFMSDateTimeString_my_task($task['actual']);
        }
        
        $include_task = false;
        
        // Check if task should be included based on date filters
        if ($date_filter_from > 0 && $date_filter_to !== PHP_INT_MAX) {
            // Both dates selected: check if task falls within range
            if (($planned_timestamp && $planned_timestamp >= $date_filter_from && $planned_timestamp <= $date_filter_to) ||
                ($actual_timestamp && $actual_timestamp >= $date_filter_from && $actual_timestamp <= $date_filter_to)) {
                $include_task = true;
            }
        } elseif ($date_filter_from > 0) {
            // Only from date: check if task is after the date
            if (($planned_timestamp && $planned_timestamp >= $date_filter_from) ||
                ($actual_timestamp && $actual_timestamp >= $date_filter_from)) {
                $include_task = true;
            }
        } elseif ($date_filter_to !== PHP_INT_MAX) {
            // Only to date: check if task is before the date
            if (($planned_timestamp && $planned_timestamp <= $date_filter_to) ||
                ($actual_timestamp && $actual_timestamp <= $date_filter_to)) {
                $include_task = true;
            }
        }
        
        if ($include_task) {
            $filtered_tasks[] = $task;
        }
    }
    
    // Update paginated tasks with filtered results
    $paginated_tasks = $filtered_tasks;
    
    // Recalculate total for pagination
    $total_items = count($filtered_tasks);
    $total_pages = ceil($total_items / $items_per_page);
}

// Filter tasks by doer_status (only for Admin/Manager)
// This filters the tasks list to only show tasks assigned to users with the selected status
// Only shows tasks from users that exist in the database and match the selected status
// Matching checklist_task.php logic: exclude non-existent users and invalid status
if (isAdmin() || isManager()) {
    $paginated_tasks = array_filter($paginated_tasks, function($task) use ($doer_status) {
        // Get the doer's user status and existence flag from the task
        $task_doer_status = $task['doer_user_status'] ?? null;
        $doer_user_exists = isset($task['doer_user_exists']) ? (int)$task['doer_user_exists'] : 0;
        
        // Exclude tasks from users that don't exist in the database
        // These users won't appear in the dropdown, so their tasks shouldn't be shown
        if ($doer_user_exists === 0) {
            return false; // Exclude unknown users
        }
        
        // User exists in database - normalize and check status
        if (!empty($task_doer_status)) {
            $task_doer_status = ucfirst(strtolower(trim($task_doer_status)));
            // Validate status value
            if ($task_doer_status !== 'Active' && $task_doer_status !== 'Inactive') {
                // Invalid status - exclude (user exists but has invalid status)
                return false;
            }
            // Check if status matches the selected filter
            return ($task_doer_status === $doer_status);
        } else {
            // Status is null but user exists - treat as Active (matches COALESCE logic in dropdown query)
            // Only include if Active filter is selected
            return ($doer_status === 'Active');
        }
    });
    // Re-index array after filtering
    $paginated_tasks = array_values($paginated_tasks);
    // Note: Total count is already accurate from SQL-level filtering, no need to recalculate
}

// parseFMSDateTimeString_my_task function is now replaced by parseFMSDateTimeString_my_taskString_my_task in includes/functions.php

// Get all unique values for filter dropdowns
$all_doers = [];
$all_sheet_labels = [];
$all_statuses = [];

// Get doers from users table (filtered by doer_status for Admin/Manager)
// Matching checklist_task.php - show doers based on selected doer_status toggle
if (isAdmin() || isManager()) {
    // Query users table directly, filtered by doer_status
    // Only fetch username (not name) to show in dropdown
    // Exclude client users from the filter dropdown
    $doers_query = "SELECT DISTINCT u.username 
                    FROM users u 
                    WHERE COALESCE(u.Status, 'Active') = ? 
                      AND u.username IS NOT NULL 
                      AND u.username != ''
                      AND u.user_type != 'client'
                    ORDER BY u.username ASC";
    $doers_stmt = $conn->prepare($doers_query);
    if ($doers_stmt) {
        $doers_stmt->bind_param("s", $doer_status);
        if ($doers_stmt->execute()) {
            $doers_result = $doers_stmt->get_result();
            if ($doers_result) {
                while ($row = $doers_result->fetch_assoc()) {
                    if (!empty($row['username']) && trim($row['username']) !== '') {
                        $all_doers[] = trim($row['username']);
                    }
                }
                sort($all_doers);
            }
        }
        $doers_stmt->close();
    }
} else {
    // For Doer users, use existing logic (from tasks)
    // Exclude client users from the filter dropdown
    $doers_query = "SELECT DISTINCT fms_tasks.doer_name 
                    FROM fms_tasks 
                    LEFT JOIN users u ON (LOWER(TRIM(fms_tasks.doer_name)) = LOWER(TRIM(u.username)) OR LOWER(TRIM(fms_tasks.doer_name)) = LOWER(TRIM(u.name)))
                    WHERE fms_tasks.doer_name IS NOT NULL 
                      AND fms_tasks.doer_name != ''
                      AND (u.user_type IS NULL OR u.user_type != 'client')
                    ORDER BY fms_tasks.doer_name";
    $doers_result = $conn->query($doers_query);
    if ($doers_result) {
        while ($row = $doers_result->fetch_assoc()) {
            $all_doers[] = $row['doer_name'];
        }
    }
}

$sheets_query = "SELECT DISTINCT sheet_label FROM fms_tasks WHERE sheet_label IS NOT NULL AND sheet_label != '' ORDER BY sheet_label";
$sheets_result = $conn->query($sheets_query);
if ($sheets_result) {
    while ($row = $sheets_result->fetch_assoc()) {
        $all_sheet_labels[] = $row['sheet_label'];
    }
}

$statuses_query = "SELECT DISTINCT status FROM fms_tasks WHERE status IS NOT NULL AND status != '' ORDER BY status";
$statuses_result = $conn->query($statuses_query);
if ($statuses_result) {
    while ($row = $statuses_result->fetch_assoc()) {
        $all_statuses[] = $row['status'];
    }
}

// Add 'Pending' status if there are tasks with null/empty status
$pending_count_query = "SELECT COUNT(*) as count FROM fms_tasks WHERE status IS NULL OR status = ''";
$pending_result = $conn->query($pending_count_query);
if ($pending_result && $pending_result->fetch_assoc()['count'] > 0) {
    $all_statuses[] = 'Pending';
}

// Helper function to build query string for pagination
function buildQueryString($page) {
    $params = $_GET;
    $params['page'] = $page;
    return http_build_query($params);
}

?>

<style>
        /* Enhanced styling for better UX */
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        /* Filter form styling */
        .form-control {
            font-size: 0.875rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .col-md-2 {
                margin-bottom: 1rem;
            }
            
            .form-label {
                font-size: 0.85rem;
            }
        }
        
        /* Info text styling */
        .text-muted small {
            font-size: 0.875rem;
            line-height: 1.4;
        }
        
        /* Table horizontal scrolling - smooth scroll */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scroll-behavior: smooth;
            width: 100%;
        }
        
        .table-responsive::-webkit-scrollbar {
            height: 8px;
        }
        
        .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .table-responsive::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* Table enhancements */
        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            white-space: nowrap !important;
            overflow: visible !important;
            text-overflow: clip !important;
        }
        
        .table thead th a {
            white-space: nowrap !important;
            overflow: visible !important;
            text-overflow: clip !important;
        }
        
        /* Ensure table doesn't shrink below content width */
        #fms-task-table {
            min-width: 100%;
            width: max-content;
        }
        
        /* Step Name column - word truncation styling */
        #fms-task-table td:nth-child(2) {
            max-width: 300px;
            word-wrap: break-word;
            word-break: break-word;
        }
        
        .badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
        
        /* Status badge specific styling */
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
            font-weight: 600;
        }
        
        .badge-success {
            background-color: #28a745;
            color: #ffffff;
            font-weight: 600;
        }
        
        .badge-primary {
            background-color: #007bff;
            color: #ffffff;
            font-weight: 600;
        }
        
        .badge-danger {
            background-color: #dc3545;
            color: #ffffff;
            font-weight: 600;
        }
        
        .badge-info {
            background-color: #17a2b8;
            color: #ffffff;
            font-weight: 600;
        }

        /* Native HTML5 date input enhancements (match add_task.php behavior) */
        input[type="date"] {
            position: relative;
            padding: 0.5rem 0.75rem;
            font-size: 0.9rem;
            line-height: 1.5;
            color: #495057;
            background-color: #fff;
            background-clip: padding-box;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        input[type="date"]:focus {
            color: #495057;
            background-color: #fff;
            border-color: #007bff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        input[type="date"]:invalid {
            border-color: #fff;
        }

        /* Make full input area open the calendar */
        input[type="date"]::-webkit-calendar-picker-indicator {
            background: transparent;
            bottom: 0;
            color: transparent;
            cursor: pointer;
            height: auto;
            left: 0;
            position: absolute;
            right: 0;
            top: 0;
            width: auto;
        }
        
        /* FMS Mark Done button - match FMS badge color (orange) - same as my_task.php */
        .btn-mark-complete {
            background: var(--gradient-accent) !important;
            border: none !important;
            color: white !important;
        }
        
        .btn-mark-complete:hover {
            background: linear-gradient(135deg, #ef4444 0%, #f59e0b 100%) !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
        }

        /* Cross-browser consistency */
        input[type="date"]::-webkit-datetime-edit { padding: 0; }
        input[type="date"]::-webkit-datetime-edit-fields-wrapper { padding: 0; }
        input[type="date"]::-webkit-datetime-edit-text { color: #6c757d; padding: 0 0.25rem; }

        /* Smart Filter area: grid + consistent sizing */
        .smart-filter-area .filter-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            grid-column-gap: 16px;
            grid-row-gap: 14px;
            align-items: end;
        }

        .smart-filter-area .filter-group label.form-label {
            margin-bottom: 6px;
        }

        .smart-filter-area .form-control {
            height: 40px;
            padding: .375rem .75rem;
            border-radius: .5rem;
            border-color: #dee2e6;
            box-shadow: none;
        }

        .smart-filter-area .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.15);
        }

        .smart-filter-area .btn {
            height: 40px;
            padding: .375rem .75rem;
            border-radius: .5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.06);
            transition: all .15s ease;
        }

        .smart-filter-area .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.12);
        }

        /* Explicit placement (>= lg): 2 aligned rows - matching Checklist Task layout */
        /* Row 1: Unique Key(1-3), Step Name(4-6), Doer(7-9), User Status(10-12) */
        .smart-filter-area .fg-unique { grid-column: 1 / span 3; grid-row: 1; }
        .smart-filter-area .fg-step   { grid-column: 4 / span 3; grid-row: 1; }
        .smart-filter-area .fg-doer   { grid-column: 7 / span 3; grid-row: 1; }
        .smart-filter-area .fg-user-status { grid-column: 10 / span 3; grid-row: 1; }
        /* Row 2: Sheet(1-2), Status(3-4), Date From(5-7, below Step Name), Date To(8-9, beside Date From), Filter/Reset(10-12, below User Status) */
        .smart-filter-area .fg-sheet  { grid-column: 1 / span 2; grid-row: 2; }
        .smart-filter-area .fg-status { grid-column: 3 / span 2; grid-row: 2; }
        .smart-filter-area .fg-from   { grid-column: 5 / span 3; grid-row: 2; }
        .smart-filter-area .fg-to     { grid-column: 8 / span 2; grid-row: 2; }
        /* Filter/Reset buttons positioned below User Status (same column position as User Status) */
        .smart-filter-area .fg-filter-buttons { 
            grid-column: 10 / span 3; 
            grid-row: 2; 
        }

        /* Button sizing - medium size, not full width */
        .smart-filter-area .fg-filter .btn,
        .smart-filter-area .fg-clear .btn { 
            width: auto;
            min-width: 100px;
            padding: 0.5rem 1.5rem;
        }
        
        /* Align buttons vertically with form fields */
        .smart-filter-area .fg-filter,
        .smart-filter-area .fg-clear {
            display: flex;
            align-items: flex-end;
        }

        /* Responsive: two columns on smaller screens */
        @media (max-width: 992px) {
            .smart-filter-area .filter-grid { grid-template-columns: repeat(2, 1fr); }
            .smart-filter-area .filter-group { grid-column: span 1 !important; grid-row: auto !important; }
            /* Buttons take full width on mobile */
            .smart-filter-area .fg-filter-buttons .btn { 
                width: 100%;
            }
        }

        /* Collapsible filter content - Fixed Lag */
        .fms-filter-content {
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .fms-filter-content.collapsed {
            max-height: 0;
            padding: 0;
            margin: 0;
        }
    
/* Tooltip hover styles */
.description-hover {
    cursor: help;
    border-bottom: 1px dotted #666;
}

.delay-hover {
    cursor: pointer;
    border-bottom: 1px dotted #dc3545;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 80px;
    display: inline-block;
    position: relative;
}

.delay-hover:hover {
    text-decoration: none;
}

/* Custom tooltip */
.delay-hover::after {
    content: attr(data-full-delay);
    position: absolute;
    bottom: 125%;
    left: 50%;
    transform: translateX(-50%);
    background-color: #000;
    color: #fff;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 13px;
    white-space: nowrap;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    pointer-events: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    border: 1px solid #333;
}

.delay-hover::before {
    content: '';
    position: absolute;
    bottom: 115%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: #000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    pointer-events: none;
}

.delay-hover:hover::after,
.delay-hover:hover::before {
    opacity: 1;
    visibility: visible;
}

/* Tooltip hover styles */
.description-hover {
    cursor: help;
    border-bottom: 1px dotted #666;
}

.tooltip-inner {
    max-width: 300px;
    text-align: left;
    white-space: pre-wrap;
    word-wrap: break-word;
}

/* User Status Toggle Styling - matching manage_tasks.php */
.doer-status-toggle {
    width: 100%;
}

.doer-status-toggle .btn-group {
    width: 100%;
    display: flex;
}

.doer-status-toggle .btn-group .btn {
    flex: 1;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    color: #495057;
    font-size: 0.875rem;
    padding: 0.5rem 0.75rem;
    transition: all 0.15s ease;
}

.doer-status-toggle .btn-group .btn:hover {
    background: #e9ecef;
    border-color: #007bff;
    color: #007bff;
}

.doer-status-toggle .btn-group .btn.active,
.doer-status-toggle .btn-group .btn-primary {
    background: #007bff;
    border-color: #007bff;
    color: white;
}

.doer-status-toggle .btn-group .btn input[type="radio"] {
    display: none;
}

/* Date input placeholder styling - browsers handle this natively, but ensure consistent appearance */
.smart-filter-area input[type="date"]::placeholder {
    color: #6c757d;
    opacity: 0.6;
}

/* Ensure User Status toggle maintains proper width on all screen sizes */
.smart-filter-area .fg-user-status {
    min-width: 0; /* Allow flex shrinking */
}

/* Responsive adjustments for User Status toggle */
@media (max-width: 992px) {
    .smart-filter-area .fg-user-status {
        grid-column: span 1 !important;
    }
}</style>

<div class="container-fluid mt-0.2">
    <div class="row mb-3">
        <div class="col-md-8">
            <h2 class="mb-0">
                <i class="fas fa-file-alt"></i> FMS Tasks
            </h2>
            <small class="text-muted">
                Showing <?php echo number_format($total_items); ?> total tasks
            </small>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #007bff; color: white;">
        <h5 class="mb-0">
            <i class="fas fa-filter"></i> Smart Filters & Search
        </h5>
        <button type="button"
                class="btn btn-sm filter-toggle-btn d-flex align-items-center"
                id="fmsToggleFilters">
            <i class="fas fa-chevron-down" id="fmsFilterToggleIcon"></i>
            Show Filters
        </button>
    </div>
        <div class="card-body smart-filter-area">
            <div class="fms-filter-content collapsed" id="fmsFilterContent">
                <form method="GET" action="fms_task.php" class="mb-3" id="fms-filter-form">
                <div class="filter-grid">
                    <!-- Row 1: Unique Key, Step Name, Doer, User Status -->
                    <div class="filter-group fg-unique">
                        <label for="unique_key" class="form-label">Unique Key</label>
                        <input type="text" class="form-control" id="unique_key" name="unique_key" 
                               value="<?php echo htmlspecialchars($filter_unique_key); ?>" 
                               placeholder="Unique Key" data-filter="text">
                    </div>
                    <div class="filter-group fg-step">
                        <label for="step_name" class="form-label">Step Name</label>
                        <input type="text" class="form-control" id="step_name" name="step_name" 
                               value="<?php echo htmlspecialchars($filter_step_name); ?>" 
                               placeholder="Step Name" data-filter="text">
                    </div>
                    <div class="filter-group fg-doer">
                        <label for="doer" class="form-label">Doer</label>
                        <select class="form-control" id="doer" name="doer" data-filter="select">
                            <option value="">All Doers</option>
                            <?php foreach($all_doers as $doer): ?>
                                <option value="<?php echo htmlspecialchars($doer); ?>" 
                                        <?php echo ($filter_doer === $doer) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($doer); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (isAdmin() || isManager()): ?>
                    <div class="filter-group fg-user-status">
                        <label class="form-label">User Status</label>
                        <div class="doer-status-toggle">
                            <div class="btn-group btn-group-toggle" data-toggle="buttons" style="width: 100%;">
                                <label class="btn btn-sm <?php echo ($doer_status === 'Active') ? 'btn-primary active' : 'btn-outline-primary'; ?>" style="flex: 1;">
                                    <input type="radio" name="doer_status" value="Active" autocomplete="off" <?php echo ($doer_status === 'Active') ? 'checked' : ''; ?>> Active
                                </label>
                                <label class="btn btn-sm <?php echo ($doer_status === 'Inactive') ? 'btn-primary active' : 'btn-outline-primary'; ?>" style="flex: 1;">
                                    <input type="radio" name="doer_status" value="Inactive" autocomplete="off" <?php echo ($doer_status === 'Inactive') ? 'checked' : ''; ?>> Inactive
                                </label>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Row 2: Sheet, Status, Date From, Date To, Filter/Reset (below User Status) -->
                    <div class="filter-group fg-sheet">
                        <label for="sheet" class="form-label">Sheet</label>
                        <select class="form-control" id="sheet" name="sheet" data-filter="select">
                            <option value="">All Sheets</option>
                            <?php foreach($all_sheet_labels as $label): ?>
                                <option value="<?php echo htmlspecialchars($label); ?>" 
                                        <?php echo ($filter_sheet === $label) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group fg-status">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-control" id="status" name="status" data-filter="select">
                            <option value="">All Statuses</option>
                            <?php foreach($all_statuses as $status): ?>
                                <option value="<?php echo htmlspecialchars($status); ?>" 
                                        <?php echo ($filter_status === $status) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group fg-from">
                        <label for="planned_from" class="form-label">Date From</label>
                        <input type="date" class="form-control date-picker-clickable" id="planned_from" name="planned_from" 
                               value="<?php echo htmlspecialchars($filter_planned_from); ?>" 
                               placeholder="dd - mm - yyyy" data-filter="date">
                    </div>
                    <div class="filter-group fg-to">
                        <label for="planned_to" class="form-label">Date To</label>
                        <input type="date" class="form-control date-picker-clickable" id="planned_to" name="planned_to" 
                               value="<?php echo htmlspecialchars($filter_planned_to); ?>" 
                               placeholder="dd - mm - yyyy" data-filter="date">
                    </div>
                    <div class="filter-group fg-filter-buttons">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="fms_task.php" class="btn btn-secondary" id="clear-filters">
                                <i class="fas fa-undo"></i> Reset
                            </a>
                        </div>
                    </div>
                </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover table-sm" id="fms-task-table">
                    <thead class="thead-light">
                        <tr>
                            <th>
                                <a href="<?php echo buildSortUrl('unique_key', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                   class="text-white text-decoration-none sortable-header" data-column="unique_key">
                                    Unique Key <?php echo getSortIcon('unique_key', $sort_column, $sort_direction); ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo buildSortUrl('step_name', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                   class="text-white text-decoration-none sortable-header" data-column="step_name">
                                    Step Name <?php echo getSortIcon('step_name', $sort_column, $sort_direction); ?>
                                </a>
                            </th>
                            <th>Assigner</th>
                            <th>
                                <a href="<?php echo buildSortUrl('planned', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                   class="text-white text-decoration-none sortable-header" data-column="planned">
                                    Planned <?php echo getSortIcon('planned', $sort_column, $sort_direction); ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo buildSortUrl('actual', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                   class="text-white text-decoration-none sortable-header" data-column="actual">
                                    Actual <?php echo getSortIcon('actual', $sort_column, $sort_direction); ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo buildSortUrl('status', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                   class="text-white text-decoration-none sortable-header" data-column="status">
                                    Status <?php echo getSortIcon('status', $sort_column, $sort_direction); ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo buildSortUrl('duration', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                   class="text-white text-decoration-none sortable-header" data-column="duration">
                                    Duration <?php echo getSortIcon('duration', $sort_column, $sort_direction); ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo buildSortUrl('doer_name', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                   class="text-white text-decoration-none sortable-header" data-column="doer_name">
                                    Doer <?php echo getSortIcon('doer_name', $sort_column, $sort_direction); ?>
                                </a>
                            </th>
                            <th>Action</th>
                            <th>
                                <a href="<?php echo buildSortUrl('step_code', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                   class="text-white text-decoration-none sortable-header" data-column="step_code">
                                    Step Code <?php echo getSortIcon('step_code', $sort_column, $sort_direction); ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo buildSortUrl('sheet_label', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                   class="text-white text-decoration-none sortable-header" data-column="sheet_label">
                                    Sheet <?php echo getSortIcon('sheet_label', $sort_column, $sort_direction); ?>
                                </a>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($paginated_tasks)): ?>
                            <?php foreach ($paginated_tasks as $task): ?>
                                <tr data-sheet-label="<?php echo htmlspecialchars($task['sheet_label'] ?? ''); ?>">
                                    <td><?php echo htmlspecialchars($task['unique_key'] ?? ''); ?></td>
                                    <td>
                                        <?php 
                                        $description = $task['step_name'] ?? 'N/A';
                                        $full_description = htmlspecialchars($description);
                                        
                                        // Truncate to 20 words
                                        $words = explode(' ', $description);
                                        if (count($words) > 20) {
                                            $truncated_words = array_slice($words, 0, 20);
                                            $truncated_description = implode(' ', $truncated_words) . '...';
                                        } else {
                                            $truncated_description = $description;
                                        }
                                        ?>
                                        <span title="<?php echo $full_description; ?>">
                                            <?php echo htmlspecialchars($truncated_description); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($task['client_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($task['planned'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($task['actual'] ?? ''); ?></td>
                                    <?php 
                                    // Custom FMS status logic: ✅ for tasks with actual data, ⏳ for pending tasks without actual data
                                    $has_actual_data = !empty($task['actual']);
                                    $status_icon = $has_actual_data ? '✅' : '⏳';
                                    $status_text = $has_actual_data ? 'Completed' : 'Pending';
                                    ?>
                                    <td class="status-column" data-status="<?php echo htmlspecialchars($task['status'] ?? 'pending'); ?>">
                                        <span class="status-icon" title="<?php echo htmlspecialchars($status_text); ?>"><?php echo $status_icon; ?></span>
                                    </td>
                                    <td><?php 
                                        $duration_display = "N/A";
                                        if (!empty($task['duration'])) {
                                            // For FMS tasks, assume duration is already in HH:MM:SS string format
                                            $duration_display = htmlspecialchars($task['duration']); 
                                        }
                                        echo $duration_display;
                                    ?></td>
                                    <td><?php echo htmlspecialchars($task['doer_name'] ?? ''); ?></td>
                                    <td>
                                        <?php if (!empty($task['task_link'])): ?>
                                            <a href="<?php echo htmlspecialchars($task['task_link']); ?>" 
                                               target="_blank" class="btn btn-success btn-sm btn-mark-complete">
                                                Done
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">No Link</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($task['step_code'] ?? ''); ?></td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($task['sheet_label'] ?? ''); ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="no-results-row">
                                <td colspan="11" class="text-center text-muted py-4">
                                    <i class="fas fa-search fa-3x mb-3"></i>
                                    <br>No tasks found matching your criteria
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Optimized Pagination and Page Size Selector -->
            <?php if ($total_pages > 0): ?>
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div>
                    <small class="text-muted">
                        Showing <strong><?php echo number_format(((int)$current_page - 1) * (int)$items_per_page + 1); ?></strong>
                        to <strong><?php echo number_format(min((int)$current_page * (int)$items_per_page, (int)$total_items)); ?></strong>
                        of <strong><?php echo number_format((int)$total_items); ?></strong> tasks
                    </small>
                </div>
                <div class="d-flex align-items-center">
                    <form method="GET" action="fms_task.php" class="form-inline mr-3">
                         <!-- Hidden fields to preserve filters -->
                        <?php foreach ($_GET as $key => $value): ?>
                            <?php if ($key != 'limit' && $key != 'page'): ?>
                                <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <label for="limit" class="mr-2 text-muted"><small>Rows:</small></label>
                        <select class="form-control form-control-sm" name="limit" id="limit" onchange="this.form.submit()">
                            <option value="20" <?php if ($items_per_page == 20) echo 'selected'; ?>>20</option>
                            <option value="50" <?php if ($items_per_page == 50) echo 'selected'; ?>>50</option>
                            <option value="100" <?php if ($items_per_page == 100) echo 'selected'; ?>>100</option>
                            <option value="200" <?php if ($items_per_page == 200) echo 'selected'; ?>>200</option>
                        </select>
                    </form>
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Task pagination">
                        <ul class="pagination mb-0">
                            <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo buildQueryString($current_page - 1); ?>">&laquo;</a>
                            </li>
                            
                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);

                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?' . buildQueryString(1) . '">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }

                            for ($i = $start_page; $i <= $end_page; $i++) {
                                $active_class = ($i == $current_page) ? 'active' : '';
                                echo '<li class="page-item ' . $active_class . '">';
                                echo '<a class="page-link" href="?' . buildQueryString($i) . '">' . $i . '</a>';
                                echo '</li>';
                            }

                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?' . buildQueryString($total_pages) . '">' . $total_pages . '</a></li>';
                            }
                            ?>
                            
                            <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo buildQueryString($current_page + 1); ?>">&raquo;</a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>

// --- FMS Filter Toggle (Fixed Lag) ---
$('#fmsToggleFilters').on('click', function() {
    var filterContent = $('#fmsFilterContent');
    var icon = $(this).find('i');
    var button = $(this);

    // Toggle visibility with smooth animation
    if (filterContent.hasClass('collapsed')) {
        // Show filters
        filterContent.removeClass('collapsed');
        icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
        button.html('<i class="fas fa-chevron-up" id="fmsFilterToggleIcon"></i> Hide Filters');
    } else {
        // Hide filters
        filterContent.addClass('collapsed');
        icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
        button.html('<i class="fas fa-chevron-down" id="fmsFilterToggleIcon"></i> Show Filters');
    }
});

    
    // Clear filters functionality
    const clearFiltersButton = document.getElementById('clear-filters');
    if (clearFiltersButton) {
        clearFiltersButton.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = 'fms_task.php';
        });
    }

    // Make User Active toggle apply instantly
    const doerStatusRadios = document.querySelectorAll('input[name="doer_status"]');
    doerStatusRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            // Submit the form automatically when toggle changes
            const form = this.closest('form');
            if (form) {
                form.submit();
            }
        });
    });
    
    // Set max selectable date to +2 years (same logic as add_task.php)
    try {
        const maxDate = new Date();
        maxDate.setFullYear(maxDate.getFullYear() + 2);
        const maxDateString = maxDate.toISOString().split('T')[0];
        const fromEl = document.getElementById('planned_from');
        const toEl = document.getElementById('planned_to');
        if (fromEl) fromEl.setAttribute('max', maxDateString);
        if (toEl) toEl.setAttribute('max', maxDateString);
    } catch (e) {}

    // Make the entire date input field open the native picker on click/focus/Enter
    const dateInputs = document.querySelectorAll('.date-picker-clickable');
    function openNativeDatePicker(el) {
        if (!el) return;
        if (typeof el.showPicker === 'function') {
            try { el.showPicker(); return; } catch (e) {}
        }
        // Fallback for browsers without showPicker: focus and synthetic click
        try { el.focus({ preventScroll: true }); } catch (e) { el.focus(); }
        try { el.dispatchEvent(new MouseEvent('mousedown', { bubbles: true })); } catch (e) {}
        try { el.dispatchEvent(new MouseEvent('click', { bubbles: true })); } catch (e) {}
    }
    dateInputs.forEach(el => {
        el.addEventListener('click', () => openNativeDatePicker(el));
        el.addEventListener('focus', () => openNativeDatePicker(el));
        el.addEventListener('keydown', (evt) => {
            if (evt.key === 'Enter' || evt.key === ' ') {
                evt.preventDefault();
                openNativeDatePicker(el);
            }
        });
    });

</script>
<?php
// Include the universal footer
require_once "../includes/footer.php";
?>
