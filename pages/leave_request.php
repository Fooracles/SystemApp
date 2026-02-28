<?php
// Set page title and include header (which handles session and config)
$page_title = 'Leave Requests';
require_once '../includes/header.php';
require_once '../includes/dashboard_components.php';
require_once '../includes/sorting_helpers.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

// Get user information from session
$user_role = $_SESSION["user_type"] ?? 'doer';
$user_name = $_SESSION["username"] ?? 'User';
$user_id = $_SESSION["id"] ?? null;
$user_display_name = $_SESSION["name"] ?? $user_name;
$user_email = $_SESSION["username"] . '@company.com'; // Use username as email for now


// --- START: Pagination Logic (like checklist_task.php) ---
$items_per_page = 30; // 30 records per page
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $items_per_page;

// Get filter parameters (support both old and new parameter names for backward compatibility)
$filter_status = isset($_GET['filter_status']) ? trim($_GET['filter_status']) : '';
$filter_employee = isset($_GET['filter_employee']) ? trim($_GET['filter_employee']) : (isset($_GET['filter_name']) ? trim($_GET['filter_name']) : '');
$filter_leave_type = isset($_GET['filter_leave_type']) ? trim($_GET['filter_leave_type']) : '';
$filter_duration = isset($_GET['filter_duration']) ? trim($_GET['filter_duration']) : '';
$filter_start_date = isset($_GET['filter_start_date']) ? trim($_GET['filter_start_date']) : (isset($_GET['start_date']) ? trim($_GET['start_date']) : '');
$filter_end_date = isset($_GET['filter_end_date']) ? trim($_GET['filter_end_date']) : (isset($_GET['end_date']) ? trim($_GET['end_date']) : '');

// Build WHERE conditions for pending requests
$pending_where_conditions = ["(status = '' OR status IS NULL OR status = 'PENDING')"];
$pending_params = [];
$pending_types = '';

// Build WHERE conditions for total requests
$total_where_conditions = [];
$total_params = [];
$total_types = '';

// Add user filtering based on user role
if ($user_role === 'doer' && !empty($user_name)) {
    // Doer users can only see their own requests
    $pending_where_conditions[] = "employee_name = ?";
    $pending_params[] = $user_name;
    $pending_types .= 's';
    
    $total_where_conditions[] = "employee_name = ?";
    $total_params[] = $user_name;
    $total_types .= 's';
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
        $pending_where_conditions[] = "employee_name IN ($placeholders)";
        $pending_params = array_merge($pending_params, $manager_identifiers);
        $pending_types .= str_repeat('s', count($manager_identifiers));
    } else {
        // If no identifiers found, show no results
        $pending_where_conditions[] = "1 = 0";
    }
    
    // For Total requests: No filtering - managers see all requests like admin
    // This allows managers to see all leave requests in the system
}

// Apply filters to both queries
if (!empty($filter_status)) {
    $total_where_conditions[] = "status = ?";
    $total_params[] = $filter_status;
    $total_types .= 's';
}

if (!empty($filter_employee)) {
    $pending_where_conditions[] = "employee_name LIKE ?";
    $pending_params[] = '%' . $filter_employee . '%';
    $pending_types .= 's';
    
    $total_where_conditions[] = "employee_name LIKE ?";
    $total_params[] = '%' . $filter_employee . '%';
    $total_types .= 's';
}

if (!empty($filter_leave_type)) {
    $pending_where_conditions[] = "leave_type = ?";
    $pending_params[] = $filter_leave_type;
    $pending_types .= 's';
    
    $total_where_conditions[] = "leave_type = ?";
    $total_params[] = $filter_leave_type;
    $total_types .= 's';
}

if (!empty($filter_duration)) {
    $pending_where_conditions[] = "duration = ?";
    $pending_params[] = $filter_duration;
    $pending_types .= 's';
    
    $total_where_conditions[] = "duration = ?";
    $total_params[] = $filter_duration;
    $total_types .= 's';
}

if (!empty($filter_start_date)) {
    $pending_where_conditions[] = "start_date >= ?";
    $pending_params[] = $filter_start_date;
    $pending_types .= 's';
    
    $total_where_conditions[] = "start_date >= ?";
    $total_params[] = $filter_start_date;
    $total_types .= 's';
}

if (!empty($filter_end_date)) {
    $pending_where_conditions[] = "end_date <= ?";
    $pending_params[] = $filter_end_date;
    $pending_types .= 's';
    
    $total_where_conditions[] = "end_date <= ?";
    $total_params[] = $filter_end_date;
    $total_types .= 's';
}

// Build WHERE clauses
$pending_sql_where = " WHERE " . implode(" AND ", $pending_where_conditions);
$total_sql_where = !empty($total_where_conditions) ? " WHERE " . implode(" AND ", $total_where_conditions) : "";

// Get total counts for pagination
$pending_sql_count = "SELECT COUNT(*) as total_count FROM Leave_request" . $pending_sql_where;
$total_sql_count = "SELECT COUNT(*) as total_count FROM Leave_request" . $total_sql_where;

$pending_total_items = 0;
$total_total_items = 0;

// Count pending requests
if ($stmt_count = mysqli_prepare($conn, $pending_sql_count)) {
    if (!empty($pending_types) && !empty($pending_params)) {
        mysqliBindParams($stmt_count, $pending_types, $pending_params);
    }
    if (mysqli_stmt_execute($stmt_count)) {
        $result_count = mysqli_stmt_get_result($stmt_count);
        $row_count = mysqli_fetch_assoc($result_count);
        $pending_total_items = $row_count['total_count'] ?? 0;
    }
    mysqli_stmt_close($stmt_count);
}

// Count total requests
if ($stmt_count = mysqli_prepare($conn, $total_sql_count)) {
    if (!empty($total_types) && !empty($total_params)) {
        mysqliBindParams($stmt_count, $total_types, $total_params);
    }
    if (mysqli_stmt_execute($stmt_count)) {
        $result_count = mysqli_stmt_get_result($stmt_count);
        $row_count = mysqli_fetch_assoc($result_count);
        $total_total_items = $row_count['total_count'] ?? 0;
    }
    mysqli_stmt_close($stmt_count);
}

// Sorting parameters - two-state sorting (asc/desc only)
// Default: Sort by unique_service_no (Leave Request ID) in DESC order
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'unique_service_no';
$sort_direction = isset($_GET['dir']) ? $_GET['dir'] : 'desc';

// Validate sort parameters
$allowed_columns = ['employee_name', 'leave_type', 'duration', 'start_date', 'end_date', 'reason', 'manager_name', 'status', 'created_at', 'unique_service_no'];
$sort_column = validateSortColumn($sort_column, $allowed_columns, 'unique_service_no');
$sort_direction = validateSortDirection($sort_direction); // This will return 'asc' if empty or invalid, but we default to 'desc' above

// Calculate pagination info
$pending_total_pages = ceil($pending_total_items / $items_per_page);
$total_total_pages = ceil($total_total_items / $items_per_page);

if ($current_page > $pending_total_pages && $pending_total_pages > 0) $current_page = $pending_total_pages;
if ($current_page > $total_total_pages && $total_total_pages > 0) $current_page = $total_total_pages;

$offset = ($current_page - 1) * $items_per_page;
// --- END: Pagination Logic ---
?>

<!-- <link rel="stylesheet" href="/assets/css/universal.css"> // FUTURE: global theme hook -->
<link rel="stylesheet" href="../assets/css/leave_request.css">
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- EMERGENCY FIX: Remove double scrollbar with inline CSS -->
<style>
/* ULTRA AGGRESSIVE - Remove ALL inner scrollbars */
.table-responsive {
    overflow: visible !important;
    max-height: none !important;
    height: auto !important;
    scrollbar-width: none !important;
    -ms-overflow-style: none !important;
}

.table-responsive::-webkit-scrollbar {
    display: none !important;
    width: 0 !important;
    height: 0 !important;
}

.card-body {
    overflow: visible !important;
    max-height: none !important;
    height: auto !important;
}

.card {
    overflow: visible !important;
    max-height: none !important;
    height: auto !important;
}

.container-fluid {
    overflow: visible !important;
    max-height: none !important;
    height: auto !important;
}

/* Ensure only main content scrolls */
html body .app-frame .main-content {
    overflow-y: auto !important;
    overflow-x: hidden !important;
}

/* NUCLEAR OPTION - COMPLETELY REMOVE ALL INNER SCROLLBARS */
.table-container {
    width: 100%;
    overflow: visible !important;
    max-height: none !important;
    height: auto !important;
    scrollbar-width: none !important;
    -ms-overflow-style: none !important;
}

.table-container::-webkit-scrollbar {
    display: none !important;
    width: 0 !important;
    height: 0 !important;
}

/* Force ALL containers to have NO scrollbars */
.card-body,
.card,
.container-fluid,
.tab-content,
.tabs-container,
.content-area {
    overflow: visible !important;
    max-height: none !important;
    height: auto !important;
    scrollbar-width: none !important;
    -ms-overflow-style: none !important;
}

.card-body::-webkit-scrollbar,
.card::-webkit-scrollbar,
.container-fluid::-webkit-scrollbar,
.tab-content::-webkit-scrollbar,
.tabs-container::-webkit-scrollbar,
.content-area::-webkit-scrollbar {
    display: none !important;
    width: 0 !important;
    height: 0 !important;
}

/* Ensure table fits properly without any scrolling */
.table-container {
    width: 100%;
    overflow-x: visible;
    overflow-y: visible;
}

.table-container table {
    width: 100%;
    margin-bottom: 0;
    table-layout: auto;
    font-size: 0.875rem;
}

/* Sort icon styles */
.sort-icon {
    font-size: 0.55em;
    opacity: 0.4;
    margin-left: 4px;
    display: inline-block;
    transition: opacity 0.2s ease;
    vertical-align: middle;
}

.sortable-header:hover .sort-icon,
.sort-icon.active {
    opacity: 1;
}

.sort-icon.active {
    color: #7b07ff;
    font-weight: bold;
}

.sortable-header {
    cursor: pointer;
    user-select: none;
    transition: opacity 0.2s ease;
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
    overflow: visible;
    text-overflow: clip;
    color: inherit !important; /* Keep original text color (white/light) */
}

.sortable-header,
.sortable-header:hover,
.sortable-header:active,
.sortable-header:focus {
    color: inherit !important; /* Always keep original text color */
    text-decoration: none !important;
}

.sortable-header:hover {
    opacity: 0.8;
}

/* Prevent wrapping in table headers - ensure full visibility */
.table thead th {
    white-space: nowrap;
    overflow: visible;
    text-overflow: clip;
    font-size: 0.85rem !important;
    padding: 0.75rem 0.5rem !important;
}

.table thead th a {
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
    overflow: visible;
    text-overflow: clip;
    max-width: none;
    font-size: 0.85rem !important;
}

/* Total Leaves Table - Fixed Layout with Column Widths (Matching Pending Requests) */
#totalLeavesTable {
    table-layout: fixed !important;
    width: 100% !important;
    font-size: 0.85rem !important;
}

/* Column widths for Total Leaves Table - Admin/Manager (8 columns) - Matching Pending */
#totalLeavesTable th:nth-child(1),
#totalLeavesTable td:nth-child(1) { /* Employee Name */
    width: 12% !important;
    min-width: 120px;
}

#totalLeavesTable th:nth-child(2),
#totalLeavesTable td:nth-child(2) { /* Leave Type */
    width: 10% !important;
    min-width: 90px;
}

#totalLeavesTable th:nth-child(3),
#totalLeavesTable td:nth-child(3) { /* Duration */
    width: 10% !important;
    min-width: 90px;
}

#totalLeavesTable th:nth-child(4),
#totalLeavesTable td:nth-child(4) { /* Start Date */
    width: 10% !important;
    min-width: 100px;
}

#totalLeavesTable th:nth-child(5),
#totalLeavesTable td:nth-child(5) { /* End Date */
    width: 10% !important;
    min-width: 100px;
}

#totalLeavesTable th:nth-child(6),
#totalLeavesTable td:nth-child(6) { /* Reason - Matching Pending (20%) */
    width: 20% !important;
    min-width: 150px;
    white-space: normal !important;
    word-wrap: break-word !important;
    overflow: visible !important;
}

#totalLeavesTable th:nth-child(7),
#totalLeavesTable td:nth-child(7) { /* No. of Leaves */
    width: 14% !important;
    min-width: 100px;
    text-align: center;
}

#totalLeavesTable th:nth-child(8),
#totalLeavesTable td:nth-child(8) { /* Status */
    width: 14% !important;
    min-width: 100px;
    text-align: center;
}

/* Pending Requests Table - Fixed Layout with Column Widths */
#pendingRequestsTable {
    table-layout: fixed !important;
    width: 100% !important;
    font-size: 0.85rem !important;
}

/* Column widths for Pending Requests Table - Admin/Manager (8 columns) */
#pendingRequestsTable th:nth-child(1),
#pendingRequestsTable td:nth-child(1) { /* Employee */
    width: 12% !important;
    min-width: 120px;
}

#pendingRequestsTable th:nth-child(2),
#pendingRequestsTable td:nth-child(2) { /* Leave Type */
    width: 10% !important;
    min-width: 90px;
}

#pendingRequestsTable th:nth-child(3),
#pendingRequestsTable td:nth-child(3) { /* Duration */
    width: 10% !important;
    min-width: 90px;
}

#pendingRequestsTable th:nth-child(4),
#pendingRequestsTable td:nth-child(4) { /* Start Date */
    width: 10% !important;
    min-width: 100px;
}

#pendingRequestsTable th:nth-child(5),
#pendingRequestsTable td:nth-child(5) { /* End Date */
    width: 10% !important;
    min-width: 100px;
}

#pendingRequestsTable th:nth-child(6),
#pendingRequestsTable td:nth-child(6) { /* Reason */
    width: 20% !important;
    min-width: 150px;
    white-space: normal !important;
    word-wrap: break-word !important;
    overflow: visible !important;
}

#pendingRequestsTable th:nth-child(7),
#pendingRequestsTable td:nth-child(7) { /* Manager */
    width: 12% !important;
    min-width: 100px;
}

#pendingRequestsTable th:nth-child(8),
#pendingRequestsTable td:nth-child(8) { /* Actions */
    width: 16% !important;
    min-width: 120px;
    text-align: center;
}
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Dynamic Metrics Cards -->
            <div class="row mb-4" id="metricsSection">
                <div class="col-md-3 mb-3">
                    <div class="stats-card pending-tasks">
                        <div class="metric-icon">
                            <i class="fas fa-clock"></i>
                                </div>
                        <div class="metric-content">
                            <div class="metric-value" id="totalPendingCount">-</div>
                            <div class="metric-label">Pending</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card completed-tasks">
                        <div class="metric-icon">
                            <i class="fas fa-check-circle"></i>
                                </div>
                        <div class="metric-content">
                            <div class="metric-value" id="totalApprovedCount">-</div>
                            <div class="metric-label">Approved</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card delayed-tasks">
                        <div class="metric-icon">
                            <i class="fas fa-times-circle"></i>
                                </div>
                        <div class="metric-content">
                            <div class="metric-value" id="totalRejectedCount">-</div>
                            <div class="metric-label">Rejected</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card total-users">
                        <div class="metric-icon">
                            <i class="fas fa-ban"></i>
                                </div>
                        <div class="metric-content">
                            <div class="metric-value" id="totalCancelledCount">-</div>
                            <div class="metric-label">Cancelled</div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Clear Leave Data Button (Admin Only) -->
            <?php if ($user_role === 'admin'): ?>
            <div class="row mb-4">
                <div class="col-12 text-center">
                    <button class="btn btn-danger btn-sm" id="clearLeaveDataBtn" style="background-color: #e74c3c; border-color: #e74c3c; color: white; padding: 8px 20px; border-radius: 4px;">
                        <i class="fas fa-trash-alt me-1"></i> Clear Leave Data
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Leave Requests Tabs Section -->
    <div class="row mb-4">
        <div class="col-12">
            <!-- Tabs Container -->
            <div class="tabs-container">
                <div class="tabs">
                    <?php if ($user_role === 'admin' || $user_role === 'manager' || $user_role === 'doer'): ?>
                    <div class="tab <?php echo ($user_role === 'doer') ? '' : 'active'; ?>" onclick="switchLeaveTab('pending')">
                        <i class="fas fa-clock"></i> Pending Leave Requests
                    </div>
                    <?php endif; ?>
                    <div class="tab <?php echo ($user_role === 'doer') ? 'active' : ''; ?>" onclick="switchLeaveTab('total')">
                        <i class="fas fa-calendar-alt"></i> Total Leave Requests
                    </div>
                </div>
                
                <!-- Pending Leave Requests Tab Content (Admin, Manager & Doer) -->
                <?php if ($user_role === 'admin' || $user_role === 'manager' || $user_role === 'doer'): ?>
                <div class="tab-content <?php echo ($user_role === 'doer') ? '' : 'active'; ?>" id="pendingRequestsTab">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #007bff; color: white;">
                            <h5 class="mb-0">
                                Pending Leave Requests
                            </h5>
                            <div class="d-flex align-items-center gap-2">
                                <span class="last-refresh-text" id="pendingLastRefresh" style="display: none;">
                                    Last refreshed: <span id="pendingLastRefreshTime">-</span>
                                </span>
                                <button class="btn btn-light btn-sm" id="refreshPendingRequests" title="Refresh Data">
                                    <i class="fas fa-sync-alt me-1"></i>Refresh
                                </button>
                            </div>
                        </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table table-hover table-sm" id="pendingRequestsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>
                                        <a href="javascript:void(0)" onclick="sortTable('pending', 'employee_name')" class="text-decoration-none sortable-header">
                                            Employee <?php echo getSortIcon('employee_name', $sort_column, $sort_direction); ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="javascript:void(0)" onclick="sortTable('pending', 'leave_type')" class="text-decoration-none sortable-header">
                                            Leave Type <?php echo getSortIcon('leave_type', $sort_column, $sort_direction); ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="javascript:void(0)" onclick="sortTable('pending', 'duration')" class="text-decoration-none sortable-header">
                                            Duration <?php echo getSortIcon('duration', $sort_column, $sort_direction); ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="javascript:void(0)" onclick="sortTable('pending', 'start_date')" class="text-decoration-none sortable-header">
                                            Start Date <?php echo getSortIcon('start_date', $sort_column, $sort_direction); ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="javascript:void(0)" onclick="sortTable('pending', 'end_date')" class="text-decoration-none sortable-header">
                                            End Date <?php echo getSortIcon('end_date', $sort_column, $sort_direction); ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="javascript:void(0)" onclick="sortTable('pending', 'reason')" class="text-decoration-none sortable-header">
                                            Reason <?php echo getSortIcon('reason', $sort_column, $sort_direction); ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="javascript:void(0)" onclick="sortTable('pending', 'manager_name')" class="text-decoration-none sortable-header">
                                            Manager <?php echo getSortIcon('manager_name', $sort_column, $sort_direction); ?>
                                        </a>
                                    </th>
                                    <?php if ($user_role === 'admin' || $user_role === 'manager'): ?>
                                    <th>Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data will be loaded via AJAX -->
                                <tr id="loadingPendingRow">
                                    <td colspan="<?php echo ($user_role === 'admin' || $user_role === 'manager') ? '8' : '7'; ?>" class="text-center">
                                        <div class="spinner-border text-warning" role="status">
                                            <span class="sr-only">Loading...</span>
                                        </div>
                                        <p class="mt-2">Loading pending requests...</p>
                                    </td>
                                </tr>
                                <!-- Empty state (hidden initially) -->
                                <tr id="emptyPendingRow" style="display: none;">
                                    <td colspan="<?php echo ($user_role === 'admin' || $user_role === 'manager') ? '8' : '7'; ?>" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                        <p>All leave requests have been processed.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination Controls for Pending Requests -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="pagination-info">
                            <span class="text-muted">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $pending_total_items); ?> of <?php echo $pending_total_items; ?> entries</span>
                        </div>
                        <?php if ($pending_total_pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination mb-0">
                                <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&filter_status=<?php echo urlencode($filter_status); ?>&filter_employee=<?php echo urlencode($filter_employee); ?>&filter_leave_type=<?php echo urlencode($filter_leave_type); ?>&filter_duration=<?php echo urlencode($filter_duration); ?>&filter_start_date=<?php echo urlencode($filter_start_date); ?>&filter_end_date=<?php echo urlencode($filter_end_date); ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $pending_total_pages; $i++): ?>
                                    <?php 
                                    // Page number jumping logic: show first, last, current, and pages around current
                                    $show_page = false;
                                    if ($i == 1 || $i == $pending_total_pages || ($i >= $current_page - 2 && $i <= $current_page + 2)) {
                                        $show_page = true;
                                    }
                                    ?>
                                    <?php if ($show_page): ?>
                                        <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&filter_status=<?php echo urlencode($filter_status); ?>&filter_employee=<?php echo urlencode($filter_employee); ?>&filter_leave_type=<?php echo urlencode($filter_leave_type); ?>&filter_duration=<?php echo urlencode($filter_duration); ?>&filter_start_date=<?php echo urlencode($filter_start_date); ?>&filter_end_date=<?php echo urlencode($filter_end_date); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php elseif (($i == $current_page - 3 && $current_page > 4) || ($i == $current_page + 3 && $current_page < $pending_total_pages - 3 )) : ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>    
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($current_page >= $pending_total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&filter_status=<?php echo urlencode($filter_status); ?>&filter_employee=<?php echo urlencode($filter_employee); ?>&filter_leave_type=<?php echo urlencode($filter_leave_type); ?>&filter_duration=<?php echo urlencode($filter_duration); ?>&filter_start_date=<?php echo urlencode($filter_start_date); ?>&filter_end_date=<?php echo urlencode($filter_end_date); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
                
                <!-- Total Leave Requests Tab Content (All Roles) -->
                <div class="tab-content <?php echo ($user_role === 'doer') ? 'active' : ''; ?>" id="totalLeaveRequestsTab">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #007bff; color: white;">
                            <h5 class="mb-0">
                                
                                <?php echo ($user_role === 'doer') ? 'My Leave History' : 'Total Leave Requests'; ?>
                            </h5>
                            <div class="d-flex align-items-center">
                                <button class="btn btn-sm filter-toggle-btn me-2" type="button" id="totalToggleFilters">
                                    <i class="fas fa-chevron-down" id="totalFilterToggleIcon"></i> Show Filters
                                </button>
                                <span class="last-refresh-text me-2" id="totalLastRefresh" style="display: none;">
                                    Last refreshed: <span id="totalLastRefreshTime">-</span>
                                </span>
                                <button class="btn btn-light btn-sm" id="refreshTotalLeaves" title="Refresh Data">
                                    <i class="fas fa-sync-alt me-1"> </i> Refresh
                                </button>
                            </div>
                        </div>
                        
                        <!-- Collapsible Filter Section -->
                        <div class="total-filter-content collapsed" id="totalFilterContent">
                            <div class="card-body border-bottom">
                                <!-- Enhanced Filter Form -->
                                <form method="GET" class="filter-form">
                                    <div class="row">
                                        <?php if ($user_role === 'manager' || $user_role === 'admin'): ?>
                                        <div class="col-md-4 mb-3">
                                            <label for="doerFilter" class="form-label">Filter by Name:</label>
                                            <select class="form-control" id="doerFilter" name="filter_name">
                                                <option value="">Search or select a name...</option>
                                                <!-- Options will be populated via AJAX -->
                                            </select>
                                        </div>
                                        <?php endif; ?>
                                        <div class="col-md-4 mb-3">
                                            <label for="statusFilter" class="form-label">Filter by Status:</label>
                                            <select class="form-control" id="statusFilter" name="filter_status">
                                                <option value="">All Status</option>
                                                <option value="Approve">Approved</option>
                                                <option value="Reject">Rejected</option>
                                                <option value="Cancelled">Cancelled</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="leaveTypeFilter" class="form-label">Filter by Leave Type:</label>
                                            <select class="form-control" id="leaveTypeFilter" name="filter_leave_type">
                                                <option value="">All Leave Types</option>
                                                <option value="Casual Leave">Casual Leave</option>
                                                <option value="Sick Leave">Sick Leave</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <label for="durationFilter" class="form-label">Filter by Duration:</label>
                                            <select class="form-control" id="durationFilter" name="filter_duration">
                                                <option value="">All Durations</option>
                                                <option value="Full day">Full day</option>
                                                <option value="Short Leave">Short Leave</option>
                                                <option value="Full Day WFH">Full Day WFH</option>
                                                <option value="Half Day">Half Day</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="startDateFilter" class="form-label">Start Date:</label>
                                            <input type="date" class="form-control" id="startDateFilter" name="start_date" autocomplete="off">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="endDateFilter" class="form-label">End Date:</label>
                                            <input type="date" class="form-control" id="endDateFilter" name="end_date" autocomplete="off">
                                        </div>
                                        <div class="col-md-3 mb-3 d-flex align-items-end">
                                            <div class="btn-group w-100">
                                                <button type="submit" class="btn btn-primary" id="applyFilters">
                                                    <i class="fas fa-filter"></i> Apply Filters
                                                </button>
                                                <a href="leave_request.php" class="btn btn-secondary" id="clearFilters">
                                                    <i class="fas fa-times"></i> Reset
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <div class="table-container">
                        <table class="table table-hover table-sm" id="totalLeavesTable">
                            <thead class="table-light">
                                <tr>
                                    <?php if ($user_role === 'manager' || $user_role === 'admin'): ?>
                                    <th>
                                        <a href="javascript:void(0)" onclick="sortTable('total', 'employee_name')" class="text-decoration-none sortable-header">
                                            Employee <?php echo getSortIcon('employee_name', $sort_column, $sort_direction); ?>
                                        </a>
                                    </th>
                                    <?php endif; ?>
                                    <th>
                                        <a href="javascript:void(0)" onclick="sortTable('total', 'leave_type')" class="text-decoration-none sortable-header">
                                            Leave Type <?php echo getSortIcon('leave_type', $sort_column, $sort_direction); ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="javascript:void(0)" onclick="sortTable('total', 'duration')" class="text-decoration-none sortable-header">
                                            Duration <?php echo getSortIcon('duration', $sort_column, $sort_direction); ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="javascript:void(0)" onclick="sortTable('total', 'start_date')" class="text-decoration-none sortable-header">
                                            Start Date <?php echo getSortIcon('start_date', $sort_column, $sort_direction); ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="javascript:void(0)" onclick="sortTable('total', 'end_date')" class="text-decoration-none sortable-header">
                                            End Date <?php echo getSortIcon('end_date', $sort_column, $sort_direction); ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="javascript:void(0)" onclick="sortTable('total', 'reason')" class="text-decoration-none sortable-header">
                                            Reason <?php echo getSortIcon('reason', $sort_column, $sort_direction); ?>
                                        </a>
                                    </th>
                                    <th>No. of Leaves</th>
                                    <th>
                                        <a href="javascript:void(0)" onclick="sortTable('total', 'status')" class="text-decoration-none sortable-header">
                                            Status <?php echo getSortIcon('status', $sort_column, $sort_direction); ?>
                                        </a>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data will be loaded via AJAX -->
                                <tr id="loadingTotalRow">
                                    <td colspan="<?php echo ($user_role === 'manager' || $user_role === 'admin') ? '8' : '7'; ?>" class="text-center">
                                        <div class="spinner-border text-success" role="status">
                                            <span class="sr-only">Loading...</span>
                                        </div>
                                        <p class="mt-2">Loading leave data...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination Controls -->
                    <div class="d-flex justify-content-between align-items-center mt-3" id="totalLeavesPagination">
                        <div class="pagination-info">
                            <span class="text-muted">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_total_items); ?> of <?php echo $total_total_items; ?> entries</span>
                        </div>
                        <?php if ($total_total_pages > 1): ?>
                        <nav aria-label="Page navigation" id="totalLeavesPaginationNav">
                            <ul class="pagination mb-0" id="totalLeavesPaginationList">
                                <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo !empty($filter_status) ? '&filter_status=' . urlencode($filter_status) : ''; ?><?php echo !empty($filter_employee) ? '&filter_name=' . urlencode($filter_employee) : ''; ?><?php echo !empty($filter_leave_type) ? '&filter_leave_type=' . urlencode($filter_leave_type) : ''; ?><?php echo !empty($filter_duration) ? '&filter_duration=' . urlencode($filter_duration) : ''; ?><?php echo !empty($filter_start_date) ? '&start_date=' . urlencode($filter_start_date) : ''; ?><?php echo !empty($filter_end_date) ? '&end_date=' . urlencode($filter_end_date) : ''; ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_total_pages; $i++): ?>
                                    <?php 
                                    // Page number jumping logic: show first, last, current, and pages around current
                                    $show_page = false;
                                    if ($i == 1 || $i == $total_total_pages || ($i >= $current_page - 2 && $i <= $current_page + 2)) {
                                        $show_page = true;
                                    }
                                    ?>
                                    <?php if ($show_page): ?>
                                        <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($filter_status) ? '&filter_status=' . urlencode($filter_status) : ''; ?><?php echo !empty($filter_employee) ? '&filter_name=' . urlencode($filter_employee) : ''; ?><?php echo !empty($filter_leave_type) ? '&filter_leave_type=' . urlencode($filter_leave_type) : ''; ?><?php echo !empty($filter_duration) ? '&filter_duration=' . urlencode($filter_duration) : ''; ?><?php echo !empty($filter_start_date) ? '&start_date=' . urlencode($filter_start_date) : ''; ?><?php echo !empty($filter_end_date) ? '&end_date=' . urlencode($filter_end_date) : ''; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php elseif (($i == $current_page - 3 && $current_page > 4) || ($i == $current_page + 3 && $current_page < $total_total_pages - 3 )) : ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>    
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($current_page >= $total_total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo !empty($filter_status) ? '&filter_status=' . urlencode($filter_status) : ''; ?><?php echo !empty($filter_employee) ? '&filter_name=' . urlencode($filter_employee) : ''; ?><?php echo !empty($filter_leave_type) ? '&filter_leave_type=' . urlencode($filter_leave_type) : ''; ?><?php echo !empty($filter_duration) ? '&filter_duration=' . urlencode($filter_duration) : ''; ?><?php echo !empty($filter_start_date) ? '&start_date=' . urlencode($filter_start_date) : ''; ?><?php echo !empty($filter_end_date) ? '&end_date=' . urlencode($filter_end_date) : ''; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    </div>
</div>

<!-- Confirmation Modal - Moved to body level to escape stacking context -->

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="leaveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <i class="fas fa-info-circle text-primary me-2"></i>
            <strong class="me-auto">Leave Request</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toastMessage">
            <!-- Message will be inserted here -->
        </div>
    </div>
</div>

<!-- Select2 JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// Inject user data for JavaScript
window.LEAVE = {
    role: '<?php echo $user_role; ?>',
    name: '<?php echo addslashes($user_name); ?>',
    displayName: '<?php echo addslashes($user_display_name); ?>',
    id: <?php echo $user_id ? (int)$user_id : 'null'; ?>,
    email: '<?php echo addslashes($user_email); ?>'
};

// Debug: Log user data to console (only in development)
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    console.log('User Data:', window.LEAVE);
    console.log('User Role:', window.LEAVE.role);
    console.log('User Name:', window.LEAVE.name);
}

// Debug: Add user data to page for troubleshooting
console.log('=== USER FILTERING DEBUG ===');
console.log('PHP User Role: <?php echo $user_role; ?>');
console.log('PHP User Name: <?php echo $user_name; ?>');
console.log('JavaScript User Role:', window.LEAVE.role);
console.log('JavaScript User Name:', window.LEAVE.name);
console.log('=== END DEBUG ===');

// Sorting state - Initialize from URL parameters
const urlParams = new URLSearchParams(window.location.search);
let currentSortColumn = urlParams.get('sort') || '<?php echo $sort_column; ?>';
let currentSortDirection = urlParams.get('dir') || '<?php echo $sort_direction; ?>';
// Normalize sort direction - ensure it's always 'asc' or 'desc', never 'default' or invalid
if (currentSortDirection === 'default' || (currentSortDirection !== 'asc' && currentSortDirection !== 'desc')) {
    currentSortDirection = 'desc'; // Default to 'desc' for unique_service_no
}
// Ensure default sort column is set if empty
if (!currentSortColumn || currentSortColumn === '') {
    currentSortColumn = 'unique_service_no';
    currentSortDirection = 'desc';
}
let currentTableType = 'pending'; // 'pending' or 'total'

// Sort table function
function sortTable(tableType, column) {
    currentTableType = tableType;
    
    // Get next sort direction (two-state: asc → desc → asc)
    if (currentSortColumn === column && currentTableType === tableType) {
        if (currentSortDirection === 'asc') {
            currentSortDirection = 'desc';
        } else {
            currentSortDirection = 'asc';
        }
    } else {
        currentSortColumn = column;
        currentSortDirection = 'asc';
    }
    
    // Update sort icons IMMEDIATELY for instant feedback (before AJAX call)
    updateSortIcons();
    
    // Update URL without page reload
    const params = new URLSearchParams(window.location.search);
    params.set('sort', currentSortColumn);
    params.set('dir', currentSortDirection);
    window.history.pushState({}, '', '?' + params.toString());
    
    // Reload the appropriate table (AJAX call happens asynchronously)
    // Check both possible variable names (leaveManager and leaveRequestManager)
    const manager = window.leaveManager || window.leaveRequestManager;
    if (typeof manager !== 'undefined' && manager) {
        if (tableType === 'pending' && typeof manager.loadPendingRequests === 'function') {
            manager.loadPendingRequests();
        }
        if (tableType === 'total' && typeof manager.loadTotalLeaves === 'function') {
            manager.loadTotalLeaves();
        }
    }
}

// Update sort icons in headers
function updateSortIcons() {
    document.querySelectorAll('.sortable-header').forEach(header => {
        const link = header.querySelector('a') || header;
        const onclick = link.getAttribute('onclick');
        if (onclick) {
            const match = onclick.match(/sortTable\(['"]([^'"]+)['"],\s*['"]([^'"]+)['"]\)/);
            if (match) {
                const tableType = match[1];
                const column = match[2];
                
                // Find or create icon
                let icon = header.querySelector('.sort-icon');
                if (!icon) {
                    icon = document.createElement('i');
                    icon.className = 'fas fa-chevron-up sort-icon sort-icon-inactive';
                    header.appendChild(icon);
                }
                
                // Remove all icon classes first
                icon.classList.remove('fa-chevron-up', 'fa-chevron-down', 'sort-icon-active', 'sort-icon-inactive');
                
                if (currentSortColumn === column && currentTableType === tableType) {
                    // Active column
                    icon.classList.add('sort-icon-active');
                    icon.style.opacity = '1';
                    
                    if (currentSortDirection === 'asc') {
                        icon.classList.add('fa-chevron-up');
                        icon.title = 'Sorted Ascending - Click to sort descending';
                    } else {
                        icon.classList.add('fa-chevron-down');
                        icon.title = 'Sorted Descending - Click to sort ascending';
                    }
                } else {
                    // Inactive column
                    icon.classList.add('fa-chevron-up', 'sort-icon-inactive');
                    icon.style.opacity = '0.35';
                    icon.title = 'Click to sort';
                }
            }
        }
    });
}

// Initialize sort icons on page load and ensure default sort is set
setTimeout(function() {
    // If no sort parameters in URL, set default sort (unique_service_no DESC)
    const urlParams = new URLSearchParams(window.location.search);
    const sortParam = urlParams.get('sort');
    const dirParam = urlParams.get('dir');
    
    // Check if sort parameters are valid
    const isValidSort = sortParam && dirParam && (dirParam === 'asc' || dirParam === 'desc');
    
    if (!isValidSort) {
        // Update URL with default sort without reloading page
        urlParams.set('sort', 'unique_service_no');
        urlParams.set('dir', 'desc');
        window.history.replaceState({}, '', '?' + urlParams.toString());
        // Update JavaScript variables
        currentSortColumn = 'unique_service_no';
        currentSortDirection = 'desc';
    } else {
        // Update JavaScript variables from URL
        currentSortColumn = sortParam;
        currentSortDirection = dirParam;
    }
    updateSortIcons();
}, 50); // Reduced timeout to run earlier
</script>
<script src="../assets/js/leave_request.js"></script>

<?php require_once '../includes/footer.php'; ?>
