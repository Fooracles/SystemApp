<?php
// Start output buffering to prevent header issues
ob_start();

session_start();

// Include required files first
require_once "../includes/config.php";
require_once "../includes/functions.php";

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Check if user has admin role - CRITICAL SECURITY CHECK
if (!isAdmin()) {
    header("Location: ../index.php");
    exit();
}

// Handle API endpoint status changes BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $endpoint_id = $_POST['endpoint_id'] ?? '';
    
    if ($action === 'toggle_status') {
        // For now, we'll just show a success message
        // In a real implementation, you'd update a database table
        $_SESSION['manage_apis_success_msg'] = "API endpoint status updated successfully!";
        header("Location: manage_apis.php");
        exit();
    }
}

// Session-based success/error message handling
$success_message = '';
$error_message = '';

if (isset($_SESSION['manage_apis_success_msg'])) {
    $success_message = $_SESSION['manage_apis_success_msg'];
    unset($_SESSION['manage_apis_success_msg']);
}

if (isset($_SESSION['manage_apis_error_msg'])) {
    $error_message = $_SESSION['manage_apis_error_msg'];
    unset($_SESSION['manage_apis_error_msg']);
}

$page_title = "Manage APIs";
require_once "../includes/header.php";

// Define available API endpoints
$api_endpoints = [
    [
        'id' => 'tasks_api',
        'name' => 'Tasks API',
        'endpoint' => '/api/tasks.php',
        'description' => 'Dynamic task fetching with support for delegation, FMS, and checklist tasks',
        'method' => 'GET',
        'status' => 'active',
        'created_date' => '2024-01-15',
        'last_used' => '2024-01-20 14:30:00',
        'usage_count' => 45,
        'parameters' => [
            'doer_id' => 'Filter by specific user ID',
            'team_id' => 'Filter by department/team ID',
            'username' => 'Filter by specific username (exact match)',
            'status' => 'Filter by task status (pending, completed, etc.)',
            'type' => 'Filter by task type (delegation, fms, checklist)',
            'date_from' => 'Filter tasks from specific date (YYYY-MM-DD)',
            'date_to' => 'Filter tasks to specific date (YYYY-MM-DD)',
            'page' => 'Page number for pagination (default: 1)',
            'per_page' => 'Items per page (default: 15, max: 100)',
            'sort' => 'Sort column (unique_id, description, planned_date, etc.)',
            'dir' => 'Sort direction (asc, desc)'
        ],
        'authentication' => 'Session-based (requires login)',
        'rate_limit' => '100 requests per hour per user',
        'response_format' => 'JSON with success/error structure'
    ],
    [
        'id' => 'users_api',
        'name' => 'Users API',
        'endpoint' => '/api/users.php',
        'description' => 'User management and profile information',
        'method' => 'GET',
        'status' => 'development',
        'created_date' => '2024-01-18',
        'last_used' => null,
        'usage_count' => 0,
        'parameters' => [
            'department_id' => 'Filter by department',
            'user_type' => 'Filter by user type (admin, manager, doer)',
            'active' => 'Filter by active status'
        ],
        'authentication' => 'Session-based (admin only)',
        'rate_limit' => '50 requests per hour per user',
        'response_format' => 'JSON with user data'
    ],
    [
        'id' => 'reports_api',
        'name' => 'Reports API',
        'endpoint' => '/api/reports.php',
        'description' => 'Generate and fetch system reports',
        'method' => 'GET',
        'status' => 'planned',
        'created_date' => null,
        'last_used' => null,
        'usage_count' => 0,
        'parameters' => [
            'report_type' => 'Type of report to generate',
            'date_range' => 'Date range for report data',
            'format' => 'Output format (json, csv, pdf)'
        ],
        'authentication' => 'Session-based (manager/admin only)',
        'rate_limit' => '20 requests per hour per user',
        'response_format' => 'JSON, CSV, or PDF based on format parameter'
    ],
    [
        'id' => 'debug_api',
        'name' => 'Debug API',
        'endpoint' => '/api/debug.php',
        'description' => 'Simple debug endpoint to test API functionality',
        'method' => 'GET',
        'status' => 'active',
        'created_date' => '2024-01-20',
        'last_used' => null,
        'usage_count' => 0,
        'parameters' => [],
        'authentication' => 'Public (no authentication required)',
        'rate_limit' => 'Unlimited',
        'response_format' => 'JSON with debug information'
    ],
    [
        'id' => 'check_api',
        'name' => 'File Checker API',
        'endpoint' => '/api/check.php',
        'description' => 'Check if API files exist and server configuration',
        'method' => 'GET',
        'status' => 'active',
        'created_date' => '2024-01-20',
        'last_used' => null,
        'usage_count' => 0,
        'parameters' => [],
        'authentication' => 'Public (no authentication required)',
        'rate_limit' => 'Unlimited',
        'response_format' => 'JSON with file existence and server info'
    ],
    [
        'id' => 'test_username_api',
        'name' => 'Username Filter Test API',
        'endpoint' => '/api/test_username.php',
        'description' => 'Test username filtering across all task types',
        'method' => 'GET',
        'status' => 'active',
        'created_date' => '2024-01-20',
        'last_used' => null,
        'usage_count' => 0,
        'parameters' => [
            'username' => 'Username to test filtering (required)'
        ],
        'authentication' => 'Session-based (requires login)',
        'rate_limit' => '50 requests per hour',
        'response_format' => 'JSON with username filter test results'
    ]
];

// Calculate statistics
$total_endpoints = count($api_endpoints);
$active_endpoints = count(array_filter($api_endpoints, function($ep) { return $ep['status'] === 'active'; }));
$development_endpoints = count(array_filter($api_endpoints, function($ep) { return $ep['status'] === 'development'; }));
$planned_endpoints = count(array_filter($api_endpoints, function($ep) { return $ep['status'] === 'planned'; }));
$total_usage = array_sum(array_column($api_endpoints, 'usage_count'));

?>

<style>
    /* Custom styling for Manage APIs page - Dark Theme */
    .manage-apis-page {
        color: var(--dark-text-primary);
    }

    .manage-apis-page .summary-card {
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--glass-shadow);
        transition: var(--transition-normal);
        position: relative;
        overflow: hidden;
        background: var(--dark-bg-card);
        backdrop-filter: var(--glass-blur);
    }

    .manage-apis-page .summary-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
        border-color: var(--brand-primary);
    }

    .manage-apis-page .summary-card.bg-primary {
        background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-secondary) 100%) !important;
        border-color: var(--brand-primary);
    }

    .manage-apis-page .summary-card.bg-success {
        background: linear-gradient(135deg, var(--brand-success) 0%, #059669 100%) !important;
        border-color: var(--brand-success);
    }

    .manage-apis-page .summary-card.bg-warning {
        background: linear-gradient(135deg, var(--brand-warning) 0%, #d97706 100%) !important;
        border-color: var(--brand-warning);
    }

    .manage-apis-page .summary-card.bg-info {
        background: linear-gradient(135deg, var(--brand-accent) 0%, #0891b2 100%) !important;
        border-color: var(--brand-accent);
    }

    .manage-apis-page .card {
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--glass-shadow);
        background: var(--dark-bg-card);
        backdrop-filter: var(--glass-blur);
        color: var(--dark-text-primary);
    }

    .manage-apis-page .card-header {
        border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        border-bottom: 1px solid var(--glass-border);
        background: var(--gradient-dark);
        color: var(--dark-text-primary);
    }

    .manage-apis-page .card-header.bg-primary {
        background: var(--gradient-primary) !important;
        color: white !important;
    }

    .manage-apis-page .card-body {
        background: var(--dark-bg-card);
        color: var(--dark-text-primary);
    }

    .manage-apis-page .card-body p,
    .manage-apis-page .card-body .text-muted,
    .manage-apis-page .card-body small {
        color: var(--dark-text-secondary);
    }

    .manage-apis-page .table {
        border-radius: var(--radius-md);
        overflow: hidden;
        box-shadow: none;
        border: 1px solid var(--glass-border);
        background: var(--dark-bg-card);
    }

    .manage-apis-page .table tbody tr:hover {
        background-color: rgba(99, 102, 241, 0.1);
        transform: none;
    }

    .status-badge {
        font-size: 0.75rem;
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: inline-block;
    }

    .status-active {
        background: linear-gradient(135deg, var(--brand-success) 0%, #20c997 100%);
        color: white;
        box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
        border: none;
    }

    .status-development {
        background: linear-gradient(135deg, var(--brand-warning) 0%, #fd7e14 100%);
        color: #212529;
        box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
        border: none;
    }

    .status-planned {
        background: linear-gradient(135deg, var(--brand-accent) 0%, #6f42c1 100%);
        color: white;
        box-shadow: 0 2px 4px rgba(6, 182, 212, 0.3);
        border: none;
    }

    .status-inactive {
        background: linear-gradient(135deg, var(--brand-danger) 0%, #e83e8c 100%);
        color: white;
        box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
        border: none;
    }

    /* Enhanced Table Styling - Dark Theme */
    .manage-apis-page .table-responsive {
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--glass-shadow);
        border: 1px solid var(--glass-border);
        background: var(--dark-bg-card);
    }

    .manage-apis-page .table {
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
        background: var(--dark-bg-card);
        color: var(--dark-text-primary);
    }

    .manage-apis-page .table thead th {
        background: var(--gradient-dark);
        border-bottom: 3px solid var(--brand-primary);
        font-weight: 700;
        color: var(--dark-text-primary);
        padding: 1.25rem 1rem;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 1px;
        position: relative;
        white-space: nowrap;
    }

    .manage-apis-page .table thead th:first-child {
        border-top-left-radius: var(--radius-lg);
    }

    .manage-apis-page .table thead th:last-child {
        border-top-right-radius: var(--radius-lg);
    }

    .manage-apis-page .table tbody td {
        padding: 1.25rem 1rem;
        vertical-align: middle;
        border-bottom: 1px solid var(--glass-border);
        transition: var(--transition-normal);
        color: var(--dark-text-secondary);
    }

    .manage-apis-page .table tbody tr {
        transition: var(--transition-normal);
        position: relative;
        background: var(--dark-bg-card);
    }

    .manage-apis-page .table tbody tr:hover {
        background: rgba(99, 102, 241, 0.15);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
    }

    .manage-apis-page .table tbody tr:hover td {
        border-color: rgba(99, 102, 241, 0.3);
        color: var(--dark-text-primary);
    }

    .manage-apis-page .table tbody tr:last-child td {
        border-bottom: none;
    }

    .manage-apis-page .table tbody tr:last-child td:first-child {
        border-bottom-left-radius: var(--radius-lg);
    }

    .manage-apis-page .table tbody tr:last-child td:last-child {
        border-bottom-right-radius: var(--radius-lg);
    }

    /* Enhanced row styling */
    .manage-apis-page .table tbody tr:nth-child(even) {
        background-color: rgba(26, 26, 26, 0.5);
    }

    .manage-apis-page .table tbody tr:nth-child(even):hover {
        background: rgba(99, 102, 241, 0.15);
    }

    /* Button group styling - Dark Theme */
    .manage-apis-page .btn-group {
        box-shadow: var(--glass-shadow);
        border-radius: var(--radius-md);
        overflow: hidden;
        background: var(--dark-bg-card);
        border: 1px solid var(--glass-border);
    }

    .manage-apis-page .btn-group .btn {
        border: none;
        font-weight: 600;
        transition: var(--transition-normal);
        position: relative;
        overflow: hidden;
        background: transparent;
        color: var(--dark-text-secondary);
    }

    .manage-apis-page .btn-group .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        color: var(--dark-text-primary);
        background: rgba(99, 102, 241, 0.1);
    }

    .manage-apis-page .btn-group .btn.btn-outline-primary {
        color: var(--brand-primary);
        border-color: var(--brand-primary);
    }

    .manage-apis-page .btn-group .btn.btn-outline-primary:hover {
        background: var(--brand-primary);
        color: white;
    }

    .manage-apis-page .btn-group .btn.btn-outline-info {
        color: var(--brand-accent);
        border-color: var(--brand-accent);
    }

    .manage-apis-page .btn-group .btn.btn-outline-info:hover {
        background: var(--brand-accent);
        color: white;
    }

    .manage-apis-page .btn-group .btn.btn-outline-success {
        color: var(--brand-success);
        border-color: var(--brand-success);
    }

    .manage-apis-page .btn-group .btn.btn-outline-success:hover {
        background: var(--brand-success);
        color: white;
    }

    .manage-apis-page .btn-group .btn.btn-outline-danger {
        color: var(--brand-danger);
        border-color: var(--brand-danger);
    }

    .manage-apis-page .btn-group .btn.btn-outline-danger:hover {
        background: var(--brand-danger);
        color: white;
    }

    /* Endpoint Details Section - Dark Theme */
    #endpointDetailsSection {
        animation: slideDown 0.3s ease-out;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .manage-apis-page .endpoint-details .table th {
        background-color: var(--dark-bg-secondary);
        font-weight: 600;
        border-top: none;
        color: var(--dark-text-primary);
    }

    .manage-apis-page .endpoint-details .table td {
        border-top: 1px solid var(--glass-border);
        vertical-align: middle;
        color: var(--dark-text-secondary);
    }

    .manage-apis-page .endpoint-details code {
        background-color: var(--dark-bg-secondary);
        color: var(--brand-accent);
        padding: 0.2rem 0.4rem;
        border-radius: var(--radius-sm);
        font-size: 0.875rem;
        border: 1px solid var(--glass-border);
    }

    .manage-apis-page .endpoint-details .bg-light {
        background-color: var(--dark-bg-secondary) !important;
        border: 1px solid var(--glass-border) !important;
        color: var(--dark-text-primary) !important;
    }

    .manage-apis-page .endpoint-details h6 {
        color: var(--dark-text-primary);
    }

    .manage-apis-page .endpoint-details p {
        color: var(--dark-text-secondary);
    }

    /* Enhanced content styling - Dark Theme */
    .manage-apis-page .table .fw-bold {
        font-size: 1rem;
        font-weight: 700;
        color: var(--dark-text-primary);
    }

    .manage-apis-page .table .text-primary {
        color: var(--brand-primary) !important;
    }

    .manage-apis-page .table .text-muted {
        color: var(--dark-text-muted) !important;
        font-size: 0.85rem;
    }

    .manage-apis-page .table .text-success {
        color: var(--brand-success) !important;
    }

    .manage-apis-page .table .text-info {
        color: var(--brand-accent) !important;
    }

    .manage-apis-page .table .text-warning {
        color: var(--brand-warning) !important;
    }

    .manage-apis-page .table .small {
        font-size: 0.8rem;
        line-height: 1.4;
        color: var(--dark-text-secondary);
    }

    /* Method badge styling - Dark Theme */
    .manage-apis-page .badge {
        font-size: 0.7rem;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        border: none;
    }

    .manage-apis-page .badge.bg-success {
        background: linear-gradient(135deg, var(--brand-success) 0%, #20c997 100%) !important;
        color: white;
    }

    .manage-apis-page .badge.bg-primary {
        background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-secondary) 100%) !important;
        color: white;
    }

    /* Code styling in table - Dark Theme */
    .manage-apis-page .table code {
        background: var(--dark-bg-secondary);
        color: var(--brand-accent);
        padding: 0.3rem 0.6rem;
        border-radius: var(--radius-sm);
        font-size: 0.8rem;
        font-weight: 600;
        border: 1px solid var(--glass-border);
        font-family: 'Courier New', monospace;
    }

    /* Additional button styling for manage-apis-page */
    .manage-apis-page .btn {
        transition: var(--transition-normal);
    }

    .manage-apis-page .btn-light {
        background: var(--dark-bg-secondary);
        color: var(--dark-text-primary);
        border: 1px solid var(--glass-border);
    }

    .manage-apis-page .btn-light:hover {
        background: var(--dark-bg-glass-hover);
        color: var(--dark-text-primary);
        border-color: var(--brand-primary);
    }

    .manage-apis-page .btn-outline-primary,
    .manage-apis-page .btn-outline-info,
    .manage-apis-page .btn-outline-success,
    .manage-apis-page .btn-outline-danger,
    .manage-apis-page .btn-outline-warning,
    .manage-apis-page .btn-outline-secondary {
        background: transparent;
    }

    .manage-apis-page .btn-outline-primary:hover,
    .manage-apis-page .btn-outline-info:hover,
    .manage-apis-page .btn-outline-success:hover,
    .manage-apis-page .btn-outline-danger:hover,
    .manage-apis-page .btn-outline-warning:hover {
        color: white;
    }

    /* Status indicators - Dark Theme */
    .manage-apis-page .table .status-indicator {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .manage-apis-page .table .usage-stats {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .manage-apis-page .table .usage-stats .small {
        font-weight: 600;
        color: var(--dark-text-secondary);
    }

    .manage-apis-page .table .usage-stats .text-muted {
        font-size: 0.75rem;
        color: var(--dark-text-muted);
    }

    /* Table loading animation */
    .manage-apis-page .table tbody tr {
        animation: fadeInUp 0.5s ease-out;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Enhanced table header - Dark Theme */
    .manage-apis-page .table thead th::after {
        content: '';
        position: absolute;
        bottom: -3px;
        left: 0;
        width: 0;
        height: 3px;
        background: var(--gradient-primary);
        transition: width 0.3s ease;
    }

    .manage-apis-page .table thead th:hover::after {
        width: 100%;
    }

    /* Table empty state - Dark Theme */
    .manage-apis-page .table-empty {
        text-align: center;
        padding: 3rem;
        color: var(--dark-text-muted);
    }

    .manage-apis-page .table-empty i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
        color: var(--dark-text-muted);
    }

    /* Enhanced scrollbar for table - Dark Theme */
    .manage-apis-page .table-responsive::-webkit-scrollbar {
        height: 8px;
    }

    .manage-apis-page .table-responsive::-webkit-scrollbar-track {
        background: var(--dark-bg-secondary);
        border-radius: 4px;
    }

    .manage-apis-page .table-responsive::-webkit-scrollbar-thumb {
        background: var(--gradient-primary);
        border-radius: 4px;
    }

    .manage-apis-page .table-responsive::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(90deg, var(--brand-secondary), var(--brand-primary));
    }

    /* Alert styling - Dark Theme */
    .manage-apis-page .alert {
        border-radius: var(--radius-md);
        border: 1px solid var(--glass-border);
        backdrop-filter: var(--glass-blur);
    }

    .manage-apis-page .alert-success {
        background: rgba(16, 185, 129, 0.1);
        color: var(--brand-success);
        border-color: var(--brand-success);
    }

    .manage-apis-page .alert-danger {
        background: rgba(239, 68, 68, 0.1);
        color: var(--brand-danger);
        border-color: var(--brand-danger);
    }

    .manage-apis-page .alert-info {
        background: rgba(6, 182, 212, 0.1);
        color: var(--brand-accent);
        border-color: var(--brand-accent);
    }

    .manage-apis-page .alert-warning {
        background: rgba(245, 158, 11, 0.1);
        color: var(--brand-warning);
        border-color: var(--brand-warning);
    }

    /* Modal styling - Dark Theme */
    .manage-apis-page .modal-content {
        background: var(--dark-bg-card);
        border: 1px solid var(--glass-border);
        backdrop-filter: var(--glass-blur);
        color: var(--dark-text-primary);
    }

    .manage-apis-page .modal-header {
        background: var(--gradient-dark);
        border-bottom: 1px solid var(--glass-border);
        color: var(--dark-text-primary);
    }

    .manage-apis-page .modal-body {
        background: var(--dark-bg-card);
        color: var(--dark-text-primary);
    }

    .manage-apis-page .modal-footer {
        background: var(--dark-bg-secondary);
        border-top: 1px solid var(--glass-border);
    }

    .manage-apis-page .form-control,
    .manage-apis-page .form-select {
        background: var(--dark-bg-secondary);
        border: 1px solid var(--glass-border);
        color: var(--dark-text-primary);
    }

    .manage-apis-page .form-control:focus,
    .manage-apis-page .form-select:focus {
        background: var(--dark-bg-glass-hover);
        border-color: var(--brand-primary);
        color: var(--dark-text-primary);
        box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
    }

    .manage-apis-page .form-control::placeholder,
    .manage-apis-page .form-select::placeholder {
        color: var(--dark-text-muted);
    }

    .manage-apis-page .form-label {
        color: var(--dark-text-primary);
    }

    /* Progress bar styling - Dark Theme */
    .manage-apis-page .progress {
        background: var(--dark-bg-secondary);
        border: 1px solid var(--glass-border);
        height: 1.5rem;
        border-radius: var(--radius-md);
    }

    .manage-apis-page .progress-bar {
        background: var(--gradient-primary);
        color: white;
    }

    /* Responsive table improvements - Dark Theme */
    @media (max-width: 768px) {
        .manage-apis-page .table-responsive {
            font-size: 0.875rem;
            border-radius: var(--radius-md);
        }
        
        .manage-apis-page .table thead th {
            padding: 1rem 0.75rem;
            font-size: 0.75rem;
        }
        
        .manage-apis-page .table tbody td {
            padding: 1rem 0.75rem;
        }
        
        .manage-apis-page .btn-group .btn {
            padding: 0.4rem 0.6rem;
            font-size: 0.75rem;
        }

        .manage-apis-page .table .fw-bold {
            font-size: 0.9rem;
        }

        .manage-apis-page .table .small {
            font-size: 0.75rem;
        }
    }

    @media (max-width: 576px) {
        .manage-apis-page .table thead th {
            padding: 0.75rem 0.5rem;
            font-size: 0.7rem;
        }
        
        .manage-apis-page .table tbody td {
            padding: 0.75rem 0.5rem;
        }

        .manage-apis-page .btn-group {
            flex-direction: column;
            gap: 0.25rem;
        }

        .manage-apis-page .btn-group .btn {
            border-radius: var(--radius-sm) !important;
            margin-right: 0 !important;
        }
    }
</style>

<div class="manage-apis-page">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-code"></i> Manage API Endpoints
                            </h5>
                            <div class="d-flex gap-2">
                                <button class="btn btn-light btn-sm" onclick="refreshPage()">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </button>
                                <button class="btn btn-light btn-sm" onclick="showAddEndpointModal()">
                                    <i class="fas fa-plus"></i> Add Endpoint
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Summary Cards -->
                        <div class="row mb-4">
                            <div class="col-md-3 mb-3">
                                <div class="stats-card total-tasks">
                                    <div class="metric-icon">
                                        <i class="fas fa-code"></i>
                                    </div>
                                    <div class="metric-content">
                                        <div class="metric-value"><?php echo $total_endpoints; ?></div>
                                        <div class="metric-label">Total Endpoints</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stats-card completed-tasks">
                                    <div class="metric-icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="metric-content">
                                        <div class="metric-value"><?php echo $active_endpoints; ?></div>
                                        <div class="metric-label">Active Endpoints</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stats-card pending-tasks">
                                    <div class="metric-icon">
                                        <i class="fas fa-tools"></i>
                                    </div>
                                    <div class="metric-content">
                                        <div class="metric-value"><?php echo $development_endpoints; ?></div>
                                        <div class="metric-label">In Development</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stats-card total-users">
                                    <div class="metric-icon">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <div class="metric-content">
                                        <div class="metric-value"><?php echo $total_usage; ?></div>
                                        <div class="metric-label">Total API Calls</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- API Endpoints Table -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-list"></i> API Endpoints
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width: 20%;">Name & Endpoint</th>
                                                <th style="width: 15%;">Method</th>
                                                <th style="width: 10%;">Status</th>
                                                <th style="width: 20%;">Description</th>
                                                <th style="width: 15%;">Usage Stats</th>
                                                <th style="width: 20%;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($api_endpoints as $endpoint): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex flex-column">
                                                            <div class="fw-bold text-primary d-flex align-items-center">
                                                                <i class="fas fa-<?php echo $endpoint['method'] === 'GET' ? 'download' : 'upload'; ?> me-2"></i>
                                                                <?php echo htmlspecialchars($endpoint['name']); ?>
                                                            </div>
                                                            <code class="text-muted small mt-1"><?php echo htmlspecialchars($endpoint['endpoint']); ?></code>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $endpoint['method'] === 'GET' ? 'success' : 'primary'; ?>">
                                                            <?php echo $endpoint['method']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge status-<?php echo $endpoint['status']; ?>">
                                                            <?php echo ucfirst($endpoint['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="text-muted small mb-2">
                                                            <?php echo htmlspecialchars(substr($endpoint['description'], 0, 80)) . (strlen($endpoint['description']) > 80 ? '...' : ''); ?>
                                                        </div>
                                                        <div class="small text-muted d-flex align-items-center">
                                                            <i class="fas fa-shield-alt me-1 text-info"></i>
                                                            <span class="fw-semibold"><?php echo htmlspecialchars($endpoint['authentication']); ?></span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="usage-stats">
                                                            <div class="d-flex align-items-center mb-1">
                                                                <i class="fas fa-chart-line me-2 text-success"></i>
                                                                <span class="small fw-bold"><?php echo number_format($endpoint['usage_count']); ?> calls</span>
                                                            </div>
                                                            <?php if ($endpoint['last_used']): ?>
                                                                <div class="small text-muted d-flex align-items-center">
                                                                    <i class="fas fa-clock me-1"></i>
                                                                    <span>Last: <?php echo date('M d, Y', strtotime($endpoint['last_used'])); ?></span>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="small text-muted d-flex align-items-center">
                                                                    <i class="fas fa-times-circle me-1 text-warning"></i>
                                                                    <span>Never used</span>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button class="btn btn-sm btn-outline-primary" 
                                                                    onclick="viewEndpointDetails('<?php echo $endpoint['id']; ?>')"
                                                                    title="View Details">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            
                                                            <?php if ($endpoint['status'] === 'active'): ?>
                                                                <button class="btn btn-sm btn-outline-info" 
                                                                        onclick="testEndpoint('<?php echo $endpoint['id']; ?>')"
                                                                        title="Test API">
                                                                    <i class="fas fa-play"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger" 
                                                                        onclick="toggleEndpoint('<?php echo $endpoint['id']; ?>', 'inactive')"
                                                                        title="Disable">
                                                                    <i class="fas fa-pause"></i>
                                                                </button>
                                                            <?php elseif ($endpoint['status'] === 'inactive'): ?>
                                                                <button class="btn btn-sm btn-outline-success" 
                                                                        onclick="toggleEndpoint('<?php echo $endpoint['id']; ?>', 'active')"
                                                                        title="Enable">
                                                                    <i class="fas fa-play"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <button class="btn btn-sm btn-outline-secondary" disabled
                                                                        title="<?php echo ucfirst($endpoint['status']); ?>">
                                                                    <i class="fas fa-clock"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas fa-tools"></i> Quick Actions
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-3 mb-2">
                                                <button class="btn btn-outline-primary btn-sm w-100" onclick="exportAPIList()">
                                                    <i class="fas fa-download"></i> Export API List
                                                </button>
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <button class="btn btn-outline-info btn-sm w-100" onclick="viewAPIDocumentation()">
                                                    <i class="fas fa-book"></i> API Documentation
                                                </button>
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <button class="btn btn-outline-warning btn-sm w-100" onclick="viewUsageAnalytics()">
                                                    <i class="fas fa-chart-bar"></i> Usage Analytics
                                                </button>
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <button class="btn btn-outline-success btn-sm w-100" onclick="testAllEndpoints()">
                                                    <i class="fas fa-check-double"></i> Test All Active
                                                </button>
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <button class="btn btn-outline-warning btn-sm w-100" onclick="runPathDiagnostic()">
                                                    <i class="fas fa-bug"></i> Path Diagnostic
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Endpoint Details Section (Inline) -->
<div class="row mt-4" id="endpointDetailsSection" style="display: none;">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle"></i> API Endpoint Details
                </h5>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="hideEndpointDetails()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            <div class="card-body" id="endpointDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function refreshPage() {
    location.reload();
}

function toggleEndpoint(endpointId, newStatus) {
    if (confirm(`Are you sure you want to ${newStatus} this endpoint?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="endpoint_id" value="${endpointId}">
            <input type="hidden" name="new_status" value="${newStatus}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function viewEndpointDetails(endpointId) {
    // In a real implementation, this would fetch detailed information
    const endpoints = <?php echo json_encode($api_endpoints); ?>;
    const endpoint = endpoints.find(ep => ep.id === endpointId);
    
    if (endpoint) {
        let content = `
            <div class="endpoint-details">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Endpoint Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Name:</strong></td><td>${endpoint.name}</td></tr>
                            <tr><td><strong>URL:</strong></td><td><code>${endpoint.endpoint}</code></td></tr>
                            <tr><td><strong>Method:</strong></td><td><span class="badge badge-primary">${endpoint.method}</span></td></tr>
                            <tr><td><strong>Status:</strong></td><td><span class="status-badge status-${endpoint.status}">${endpoint.status}</span></td></tr>
                            <tr><td><strong>Authentication:</strong></td><td>${endpoint.authentication}</td></tr>
                            <tr><td><strong>Rate Limit:</strong></td><td>${endpoint.rate_limit}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Description</h6>
                        <p>${endpoint.description}</p>
                        
                        <h6 class="mt-3">Example Usage</h6>
                        <div class="bg-light p-3 rounded" style="background-color: var(--dark-bg-secondary) !important; border: 1px solid var(--glass-border) !important; color: var(--dark-text-primary) !important;">
                            <code>GET ${endpoint.endpoint}?doer_id=123&status=pending</code>
                            <button class="btn btn-sm btn-outline-primary ms-2" onclick="copyToClipboard('GET ${endpoint.endpoint}?doer_id=123&status=pending')">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-12">
                        <h6>Parameters</h6>
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>Parameter</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
        `;
        
        Object.entries(endpoint.parameters).forEach(([param, desc]) => {
            content += `
                <tr>
                    <td><code>${param}</code></td>
                    <td>${desc}</td>
                </tr>
            `;
        });
        
        content += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('endpointDetailsContent').innerHTML = content;
        
        // Show the details section
        document.getElementById('endpointDetailsSection').style.display = 'block';
        
        // Scroll to the details section
        document.getElementById('endpointDetailsSection').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
    }
}

function hideEndpointDetails() {
    document.getElementById('endpointDetailsSection').style.display = 'none';
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Show temporary success message
        const btn = event.target.closest('button');
        const originalContent = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        btn.classList.add('btn-success');
        btn.classList.remove('btn-outline-primary');
        
        setTimeout(() => {
            btn.innerHTML = originalContent;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-primary');
        }, 2000);
    }).catch(err => {
        console.error('Failed to copy: ', err);
        alert('Failed to copy to clipboard');
    });
}

function testEndpoint(endpointId) {
    if (endpointId === 'tasks_api') {
        // Try different possible paths for the API
        const possiblePaths = [
            '/api/tasks.php',
            '/FMS-4.31/api/tasks.php',
            'api/tasks.php',
            '../api/tasks.php',
            // Live server paths
            window.location.pathname.replace('/pages/manage_apis.php', '') + '/api/tasks.php',
            window.location.origin + '/api/tasks.php',
            window.location.origin + '/FMS-4.31/api/tasks.php'
        ];
        
        testAPIWithMultiplePaths(possiblePaths, 'Tasks API');
    } else if (endpointId === 'debug_api') {
        // Test debug endpoint
        const debugPaths = [
            '/api/debug.php',
            '/FMS-4.31/api/debug.php',
            'api/debug.php',
            '../api/debug.php',
            // Live server paths
            window.location.pathname.replace('/pages/manage_apis.php', '') + '/api/debug.php',
            window.location.origin + '/api/debug.php',
            window.location.origin + '/FMS-4.31/api/debug.php'
        ];
        
        testAPIWithMultiplePaths(debugPaths, 'Debug API');
    } else {
        alert('This endpoint is not yet implemented for testing.');
    }
}

// Diagnostic function to help identify correct paths
function runPathDiagnostic() {
    const currentUrl = window.location.href;
    const currentPath = window.location.pathname;
    const origin = window.location.origin;
    
    const diagnosticInfo = `
🔍 PATH DIAGNOSTIC INFORMATION

Current URL: ${currentUrl}
Current Path: ${currentPath}
Origin: ${origin}

📁 EXPECTED API PATHS:
1. ${origin}/api/tasks.php
2. ${origin}/FMS-4.31/api/tasks.php
3. ${currentPath.replace('/pages/manage_apis.php', '')}/api/tasks.php
4. ${currentPath.replace('/pages/manage_apis.php', '')}/../api/tasks.php

🧪 MANUAL TESTING:
Try these URLs directly in your browser:
- ${origin}/api/tasks.php
- ${origin}/FMS-4.31/api/tasks.php

📋 TROUBLESHOOTING STEPS:
1. Check if /api/ directory exists on your server
2. Verify api/tasks.php file is uploaded
3. Check file permissions (644 for files, 755 for directories)
4. Test direct URL access in browser
5. Check server error logs for PHP errors

💡 COMMON LIVE SERVER ISSUES:
- Different domain structure than localhost
- Case-sensitive file names
- Missing .htaccess or server configuration
- PHP version differences
- File upload issues
    `;
    
    alert(diagnosticInfo);
    console.log('Path Diagnostic:', {
        currentUrl,
        currentPath,
        origin,
        expectedPaths: [
            `${origin}/api/tasks.php`,
            `${origin}/FMS-4.31/api/tasks.php`,
            `${currentPath.replace('/pages/manage_apis.php', '')}/api/tasks.php`
        ]
    });
}

async function testAPIWithMultiplePaths(paths, apiName) {
    let lastError = null;
    let workingPath = null;
    let debugInfo = [];
    
    for (const path of paths) {
        try {
            console.log(`Trying path: ${path}`);
            debugInfo.push(`Trying: ${path}`);
            
            const response = await fetch(`${path}?per_page=5&username=test`);
            debugInfo.push(`${path} - Status: ${response.status} ${response.statusText}`);
            
            // Check if response is HTML (error page) instead of JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.log(`Path ${path} returned HTML:`, text.substring(0, 200));
                debugInfo.push(`${path} - HTML Response: ${text.substring(0, 100)}...`);
                lastError = `Server returned HTML instead of JSON. Content-Type: ${contentType}`;
                continue;
            }
            
            if (response.ok) {
                const data = await response.json();
                workingPath = path;
                
                if (data.success) {
                    alert(`✅ ${apiName} Test Successful!\n\nWorking Path: ${path}\n\nResponse: ${JSON.stringify(data, null, 2)}`);
                    return;
                } else {
                    alert(`❌ ${apiName} Test Failed!\n\nWorking Path: ${path}\n\nError: ${data.error.message}`);
                    return;
                }
            } else {
                lastError = `HTTP ${response.status}: ${response.statusText}`;
                debugInfo.push(`${path} - HTTP Error: ${response.status}`);
            }
        } catch (error) {
            console.log(`Path ${path} failed:`, error.message);
            debugInfo.push(`${path} - Error: ${error.message}`);
            lastError = `Fetch error: ${error.message}`;
        }
    }
    
    // If we get here, all paths failed - show detailed debug info
    const debugInfoText = debugInfo.join('\n');
    alert(`❌ ${apiName} Test Failed!\n\nDebug Information:\n${debugInfoText}\n\nLast error: ${lastError}\n\nCurrent URL: ${window.location.href}\nBase Path: ${window.location.pathname}\nOrigin: ${window.location.origin}\n\nPlease check:\n1. API file exists at /api/tasks.php\n2. Server is running\n3. No PHP errors in the API file\n4. Check browser console for more details`);
}

function showAddEndpointModal() {
    const modalHtml = `
        <div class="modal fade" id="addEndpointModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New API Endpoint</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addEndpointForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Endpoint Name</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Endpoint URL</label>
                                    <input type="text" class="form-control" name="endpoint" placeholder="/api/example.php" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3" required></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">HTTP Method</label>
                                    <select class="form-control" name="method" required>
                                        <option value="GET">GET</option>
                                        <option value="POST">POST</option>
                                        <option value="PUT">PUT</option>
                                        <option value="DELETE">DELETE</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-control" name="status" required>
                                        <option value="development">Development</option>
                                        <option value="planned">Planned</option>
                                        <option value="active">Active</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Authentication</label>
                                    <select class="form-control" name="authentication" required>
                                        <option value="Session-based (requires login)">Session-based</option>
                                        <option value="Token-based">Token-based</option>
                                        <option value="Public">Public</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Rate Limit</label>
                                    <input type="text" class="form-control" name="rate_limit" placeholder="100 requests per hour per user" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Response Format</label>
                                    <input type="text" class="form-control" name="response_format" placeholder="JSON with success/error structure" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Parameters (JSON Format)</label>
                                <textarea class="form-control" name="parameters" rows="4" placeholder='{"param1": "Description", "param2": "Description"}'></textarea>
                                <small class="form-text text-muted">Enter parameters in JSON format</small>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="saveNewEndpoint()">Save Endpoint</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('addEndpointModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    new bootstrap.Modal(document.getElementById('addEndpointModal')).show();
}

function saveNewEndpoint() {
    const form = document.getElementById('addEndpointForm');
    const formData = new FormData(form);
    
    // Validate required fields
    const requiredFields = ['name', 'endpoint', 'description', 'method', 'status', 'authentication', 'rate_limit', 'response_format'];
    for (let field of requiredFields) {
        if (!formData.get(field)) {
            alert(`Please fill in the ${field} field.`);
            return;
        }
    }
    
    // Validate JSON parameters
    const parametersText = formData.get('parameters');
    if (parametersText) {
        try {
            JSON.parse(parametersText);
        } catch (e) {
            alert('Parameters must be in valid JSON format.');
            return;
        }
    }
    
    // Simulate saving (in real implementation, this would make an AJAX call)
    alert('New endpoint saved successfully!\\n\\nNote: This is a demo. In a real implementation, this would save to the database and refresh the page.');
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('addEndpointModal'));
    modal.hide();
}

function exportAPIList() {
    const endpoints = <?php echo json_encode($api_endpoints); ?>;
    
    // Create CSV content
    let csvContent = "Name,Endpoint,Method,Status,Description,Authentication,Rate Limit,Usage Count,Last Used\n";
    
    endpoints.forEach(endpoint => {
        const row = [
            `"${endpoint.name}"`,
            `"${endpoint.endpoint}"`,
            `"${endpoint.method}"`,
            `"${endpoint.status}"`,
            `"${endpoint.description.replace(/"/g, '""')}"`,
            `"${endpoint.authentication}"`,
            `"${endpoint.rate_limit}"`,
            endpoint.usage_count,
            endpoint.last_used || 'Never'
        ].join(',');
        csvContent += row + '\n';
    });
    
    // Create and download file
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `api_endpoints_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Also create JSON export
    const jsonContent = JSON.stringify(endpoints, null, 2);
    const jsonBlob = new Blob([jsonContent], { type: 'application/json;charset=utf-8;' });
    const jsonLink = document.createElement('a');
    const jsonUrl = URL.createObjectURL(jsonBlob);
    jsonLink.setAttribute('href', jsonUrl);
    jsonLink.setAttribute('download', `api_endpoints_${new Date().toISOString().split('T')[0]}.json`);
    jsonLink.style.visibility = 'hidden';
    document.body.appendChild(jsonLink);
    jsonLink.click();
    document.body.removeChild(jsonLink);
    
    alert('API endpoints exported successfully!\n\nFiles downloaded:\n- api_endpoints_[date].csv\n- api_endpoints_[date].json');
}

function viewAPIDocumentation() {
    window.open('api_documentation.php', '_blank');
}

function viewUsageAnalytics() {
    window.open('api_analytics.php', '_blank');
}

function testAllEndpoints() {
    if (confirm('This will test all active endpoints. Continue?')) {
        const endpoints = <?php echo json_encode($api_endpoints); ?>;
        const activeEndpoints = endpoints.filter(ep => ep.status === 'active');
        
        if (activeEndpoints.length === 0) {
            alert('No active endpoints to test.');
            return;
        }
        
        // Show progress modal
        const progressModal = `
            <div class="modal fade" id="testProgressModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Testing API Endpoints</h5>
                        </div>
                        <div class="modal-body">
                            <div class="progress mb-3">
                                <div class="progress-bar" id="testProgress" role="progressbar" style="width: 0%"></div>
                            </div>
                            <div id="testResults"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('testProgressModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', progressModal);
        const modal = new bootstrap.Modal(document.getElementById('testProgressModal'));
        modal.show();
        
        // Test endpoints
        let completedTests = 0;
        const totalTests = activeEndpoints.length;
        const results = [];
        
        activeEndpoints.forEach((endpoint, index) => {
            setTimeout(() => {
                testEndpointAsync(endpoint).then(result => {
                    results.push(result);
                    completedTests++;
                    
                    // Update progress
                    const progress = (completedTests / totalTests) * 100;
                    document.getElementById('testProgress').style.width = progress + '%';
                    
                    // Add result to display
                    const resultHtml = `
                        <div class="alert alert-${result.success ? 'success' : 'danger'} alert-sm">
                            <strong>${endpoint.name}:</strong> ${result.success ? 'PASS' : 'FAIL'}
                            ${result.message ? `<br><small>${result.message}</small>` : ''}
                        </div>
                    `;
                    document.getElementById('testResults').insertAdjacentHTML('beforeend', resultHtml);
                    
                    // If all tests completed, show summary
                    if (completedTests === totalTests) {
                        const passedTests = results.filter(r => r.success).length;
                        const summaryHtml = `
                            <div class="alert alert-info mt-3">
                                <strong>Test Summary:</strong> ${passedTests}/${totalTests} endpoints passed
                            </div>
                        `;
                        document.getElementById('testResults').insertAdjacentHTML('beforeend', summaryHtml);
                    }
                });
            }, index * 1000); // Stagger tests by 1 second
        });
    }
}

async function testEndpointAsync(endpoint) {
    try {
        if (endpoint.id === 'tasks_api') {
            // Try different possible paths for the API
            const possiblePaths = [
                '/api/tasks.php',
                '/FMS-4.31/api/tasks.php',
                'api/tasks.php',
                '../api/tasks.php',
                // Live server paths
                window.location.pathname.replace('/pages/manage_apis.php', '') + '/api/tasks.php',
                window.location.origin + '/api/tasks.php',
                window.location.origin + '/FMS-4.31/api/tasks.php'
            ];
            
            let response = null;
            let workingPath = null;
            
            for (const path of possiblePaths) {
                try {
                    response = await fetch(`${path}?per_page=5`);
                    if (response.ok) {
                        workingPath = path;
                        break;
                    }
                } catch (e) {
                    // Continue to next path
                }
            }
            
            if (!response || !response.ok) {
                return {
                    success: false,
                    message: `API endpoint not found. Tried paths: ${possiblePaths.join(', ')}`
                };
            }
            
            // Check if response is HTML (error page) instead of JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                return {
                    success: false,
                    message: `Server returned HTML instead of JSON. Content-Type: ${contentType}. First 200 chars: ${text.substring(0, 200)}`
                };
            }
            
            const data = await response.json();
            
            if (data.success) {
                return {
                    success: true,
                    message: `Returned ${data.data.tasks.length} tasks`
                };
            } else {
                return {
                    success: false,
                    message: data.error.message
                };
            }
        } else if (endpoint.id === 'debug_api') {
            // Test debug endpoint
            const debugPaths = [
                '/api/debug.php',
                '/FMS-4.31/api/debug.php',
                'api/debug.php',
                '../api/debug.php',
                // Live server paths
                window.location.pathname.replace('/pages/manage_apis.php', '') + '/api/debug.php',
                window.location.origin + '/api/debug.php',
                window.location.origin + '/FMS-4.31/api/debug.php'
            ];
            
            let response = null;
            let workingPath = null;
            
            for (const path of debugPaths) {
                try {
                    response = await fetch(`${path}`);
                    if (response.ok) {
                        workingPath = path;
                        break;
                    }
                } catch (e) {
                    // Continue to next path
                }
            }
            
            if (!response || !response.ok) {
                return {
                    success: false,
                    message: `Debug API endpoint not found. Tried paths: ${debugPaths.join(', ')}`
                };
            }
            
            // Check if response is HTML (error page) instead of JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                return {
                    success: false,
                    message: `Server returned HTML instead of JSON. Content-Type: ${contentType}. First 200 chars: ${text.substring(0, 200)}`
                };
            }
            
            const data = await response.json();
            
            if (data.success) {
                return {
                    success: true,
                    message: `Debug API working. Session: ${data.session_status}, Logged in: ${data.is_logged_in}`
                };
            } else {
                return {
                    success: false,
                    message: data.error?.message || 'Debug API returned error'
                };
            }
        } else if (endpoint.id === 'check_api') {
            // Test file checker endpoint
            const checkPaths = [
                '/api/check.php',
                '/FMS-4.31/api/check.php',
                'api/check.php',
                '../api/check.php',
                // Live server paths
                window.location.pathname.replace('/pages/manage_apis.php', '') + '/api/check.php',
                window.location.origin + '/api/check.php',
                window.location.origin + '/FMS-4.31/api/check.php'
            ];
            
            let response = null;
            let workingPath = null;
            
            for (const path of checkPaths) {
                try {
                    response = await fetch(`${path}`);
                    if (response.ok) {
                        workingPath = path;
                        break;
                    }
                } catch (e) {
                    // Continue to next path
                }
            }
            
            if (!response || !response.ok) {
                return {
                    success: false,
                    message: `File Checker API endpoint not found. Tried paths: ${checkPaths.join(', ')}`
                };
            }
            
            // Check if response is HTML (error page) instead of JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                return {
                    success: false,
                    message: `Server returned HTML instead of JSON. Content-Type: ${contentType}. First 200 chars: ${text.substring(0, 200)}`
                };
            }
            
            const data = await response.json();
            
            if (data.success) {
                const fileStatus = Object.entries(data.api_files).map(([file, exists]) => `${file}: ${exists ? '✅' : '❌'}`).join(', ');
                return {
                    success: true,
                    message: `File Checker working. Files: ${fileStatus}`
                };
            } else {
                return {
                    success: false,
                    message: data.error?.message || 'File Checker API returned error'
                };
            }
        } else if (endpoint.id === 'test_username_api') {
            // Test username filter endpoint
            const usernamePaths = [
                '/api/test_username.php',
                '/FMS-4.31/api/test_username.php',
                'api/test_username.php',
                '../api/test_username.php',
                // Live server paths
                window.location.pathname.replace('/pages/manage_apis.php', '') + '/api/test_username.php',
                window.location.origin + '/api/test_username.php',
                window.location.origin + '/FMS-4.31/api/test_username.php'
            ];
            
            let response = null;
            let workingPath = null;
            
            for (const path of usernamePaths) {
                try {
                    response = await fetch(`${path}?username=test`);
                    if (response.ok) {
                        workingPath = path;
                        break;
                    }
                } catch (e) {
                    // Continue to next path
                }
            }
            
            if (!response || !response.ok) {
                return {
                    success: false,
                    message: `Username Test API endpoint not found. Tried paths: ${usernamePaths.join(', ')}`
                };
            }
            
            // Check if response is HTML (error page) instead of JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                return {
                    success: false,
                    message: `Server returned HTML instead of JSON. Content-Type: ${contentType}. First 200 chars: ${text.substring(0, 200)}`
                };
            }
            
            const data = await response.json();
            
            if (data.success) {
                const summary = data.data.summary;
                return {
                    success: true,
                    message: `Username Test working. Total tasks: ${summary.total_tasks} (Delegation: ${summary.delegation_tasks}, FMS: ${summary.fms_tasks}, Checklist: ${summary.checklist_tasks})`
                };
            } else {
                return {
                    success: false,
                    message: data.error?.message || 'Username Test API returned error'
                };
            }
        } else {
            return {
                success: false,
                message: 'Endpoint not implemented for testing'
            };
        }
    } catch (error) {
        return {
            success: false,
            message: `Fetch error: ${error.message}`
        };
    }
}


// Auto-dismiss alerts
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('fade');
            setTimeout(() => {
                alert.remove();
            }, 150);
        }, 5000);
    });
});
</script>

<?php 
// Clean output buffer and include footer
ob_end_flush();
require_once "../includes/footer.php"; 
?>
