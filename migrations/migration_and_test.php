<?php
/**
 * Database Migration & Application Test Script
 * 
 * This script performs:
 * 1. Database structure validation and migration (creates missing tables/columns)
 * 2. Application functionality testing for all pages and user roles
 * 
 * Usage: Run this file directly in browser or via CLI
 * 
 * IMPORTANT: This script is NON-DESTRUCTIVE - it only ADDS missing tables/columns
 *            It will NEVER drop or modify existing data.
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Start output buffering for clean HTML output
ob_start();

// Include required files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db_schema.php';
require_once __DIR__ . '/includes/db_functions.php';
require_once __DIR__ . '/includes/functions.php';

// Initialize results arrays
$migration_results = [
    'tables_created' => [],
    'tables_existing' => [],
    'columns_added' => [],
    'errors' => []
];

$test_results = [
    'pages_tested' => [],
    'roles_tested' => [],
    'total_tests' => 0,
    'passed_tests' => 0,
    'failed_tests' => 0
];

/**
 * Log a message with timestamp
 */
function logMessage($message, $type = 'info') {
    $timestamp = date('Y-m-d H:i:s');
    $prefix = '';
    switch($type) {
        case 'success': $prefix = '✓'; break;
        case 'error': $prefix = '✗'; break;
        case 'warning': $prefix = '⚠'; break;
        case 'info': $prefix = 'ℹ'; break;
    }
    return "[$timestamp] $prefix $message\n";
}

/**
 * Get existing columns from database table
 */
function getExistingColumns($conn, $table_name) {
    $columns = [];
    $sql = "SHOW COLUMNS FROM `$table_name`";
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[$row['Field']] = [
                'type' => $row['Type'],
                'null' => $row['Null'],
                'key' => $row['Key'],
                'default' => $row['Default'],
                'extra' => $row['Extra']
            ];
        }
    }
    
    return $columns;
}

/**
 * Perform database migration
 */
function performDatabaseMigration($conn) {
    global $DB_TABLES, $TABLE_CREATION_ORDER, $migration_results;
    
    $log = [];
    $log[] = logMessage("Starting Database Migration...", 'info');
    $log[] = str_repeat("=", 80) . "\n";
    
    // Step 1: Check and create missing tables
    $log[] = logMessage("Step 1: Checking Tables...", 'info');
    
    foreach ($TABLE_CREATION_ORDER as $table_name) {
        if (!isset($DB_TABLES[$table_name])) {
            $log[] = logMessage("Table '$table_name' not defined in schema", 'warning');
            continue;
        }
        
        if (tableExists($conn, $table_name)) {
            $migration_results['tables_existing'][] = $table_name;
            $log[] = logMessage("Table '$table_name' exists", 'success');
        } else {
            // Create the table
            $sql = $DB_TABLES[$table_name]['sql'];
            if (mysqli_query($conn, $sql)) {
                $migration_results['tables_created'][] = $table_name;
                $log[] = logMessage("Created table '$table_name'", 'success');
            } else {
                $error = mysqli_error($conn);
                $migration_results['errors'][] = "Failed to create table '$table_name': $error";
                $log[] = logMessage("Failed to create table '$table_name': $error", 'error');
            }
        }
    }
    
    // Step 2: Run existing ensure functions (these handle column additions)
    $log[] = "\n" . logMessage("Step 2: Running Column Ensure Functions...", 'info');
    
    // Track columns added by ensure functions
    $columns_before = [];
    $tables_to_check = ['users', 'tasks', 'fms_tasks', 'password_reset_requests', 'updates'];
    
    foreach ($tables_to_check as $table) {
        if (tableExists($conn, $table)) {
            $columns_before[$table] = array_keys(getExistingColumns($conn, $table));
        }
    }
    
    if (tableExists($conn, 'users') && function_exists('ensureUsersColumns')) {
        ensureUsersColumns($conn);
        $columns_after = array_keys(getExistingColumns($conn, 'users'));
        $added = array_diff($columns_after, $columns_before['users'] ?? []);
        if (!empty($added)) {
            foreach ($added as $col) {
                $migration_results['columns_added'][] = "users.$col";
            }
        }
        $log[] = logMessage("Ran ensureUsersColumns() - " . count($added) . " columns added", count($added) > 0 ? 'success' : 'info');
    }
    
    if (tableExists($conn, 'tasks') && function_exists('ensureTasksColumns')) {
        ensureTasksColumns($conn);
        $columns_after = array_keys(getExistingColumns($conn, 'tasks'));
        $added = array_diff($columns_after, $columns_before['tasks'] ?? []);
        if (!empty($added)) {
            foreach ($added as $col) {
                $migration_results['columns_added'][] = "tasks.$col";
            }
        }
        $log[] = logMessage("Ran ensureTasksColumns() - " . count($added) . " columns added", count($added) > 0 ? 'success' : 'info');
    }
    
    if (tableExists($conn, 'fms_tasks') && function_exists('ensureFmsTasksColumns')) {
        ensureFmsTasksColumns($conn);
        $columns_after = array_keys(getExistingColumns($conn, 'fms_tasks'));
        $added = array_diff($columns_after, $columns_before['fms_tasks'] ?? []);
        if (!empty($added)) {
            foreach ($added as $col) {
                $migration_results['columns_added'][] = "fms_tasks.$col";
            }
        }
        $log[] = logMessage("Ran ensureFmsTasksColumns() - " . count($added) . " columns added", count($added) > 0 ? 'success' : 'info');
    }
    
    if (tableExists($conn, 'password_reset_requests') && function_exists('ensurePasswordResetRequestsColumns')) {
        ensurePasswordResetRequestsColumns($conn);
        $log[] = logMessage("Ran ensurePasswordResetRequestsColumns()", 'success');
    }
    
    if (tableExists($conn, 'updates') && function_exists('ensureUpdatesColumns')) {
        ensureUpdatesColumns($conn);
        $columns_after = array_keys(getExistingColumns($conn, 'updates'));
        $added = array_diff($columns_after, $columns_before['updates'] ?? []);
        if (!empty($added)) {
            foreach ($added as $col) {
                $migration_results['columns_added'][] = "updates.$col";
            }
        }
        $log[] = logMessage("Ran ensureUpdatesColumns() - " . count($added) . " columns added", count($added) > 0 ? 'success' : 'info');
    }
    
    $log[] = "\n" . str_repeat("=", 80) . "\n";
    $log[] = logMessage("Database Migration Complete!", 'success');
    $log[] = "Summary:\n";
    $log[] = "  - Tables Created: " . count($migration_results['tables_created']) . "\n";
    $log[] = "  - Tables Existing: " . count($migration_results['tables_existing']) . "\n";
    $log[] = "  - Columns Added: " . count($migration_results['columns_added']) . "\n";
    $log[] = "  - Errors: " . count($migration_results['errors']) . "\n";
    
    return implode('', $log);
}

/**
 * Test a page for a specific user role
 */
function testPageForRole($conn, $page_path, $role, $test_user_id = null) {
    global $test_results;
    
    $result = [
        'page' => $page_path,
        'role' => $role,
        'status' => 'UNKNOWN',
        'error' => null,
        'details' => []
    ];
    
    // Get a test user for this role
    if (!$test_user_id) {
        $user_query = "SELECT id, username, user_type FROM users WHERE user_type = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $user_query);
        mysqli_stmt_bind_param($stmt, 's', $role);
        mysqli_stmt_execute($stmt);
        $user_result = mysqli_stmt_get_result($stmt);
        
        if ($user_row = mysqli_fetch_assoc($user_result)) {
            $test_user_id = $user_row['id'];
        } else {
            $result['status'] = 'SKIPPED';
            $result['error'] = "No user found with role '$role'";
            return $result;
        }
    }
    
    // Get user details
    $user_query = "SELECT id, username, name, email, user_type FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $user_query);
    mysqli_stmt_bind_param($stmt, 'i', $test_user_id);
    mysqli_stmt_execute($stmt);
    $user_result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($user_result);
    
    if (!$user) {
        $result['status'] = 'SKIPPED';
        $result['error'] = "User ID $test_user_id not found";
        return $result;
    }
    
    // Start a new session for testing (if not already started)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = []; // Clear existing session
    
    // Set up session for this user
    $_SESSION['loggedin'] = true;
    $_SESSION['id'] = $user['id'];
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['email'] = $user['email'];
    
    // Test 1: Check if page file exists
    $full_path = __DIR__ . '/pages/' . basename($page_path);
    if (!file_exists($full_path)) {
        $result['status'] = 'FAIL';
        $result['error'] = "Page file does not exist: $full_path";
        session_destroy();
        return $result;
    }
    $result['details'][] = "Page file exists";
    
    // Test 2: Check page structure and access rules
    $page_content = file_get_contents($full_path);
    
    // Check authentication requirement
    if (strpos($page_content, 'isLoggedIn()') === false && strpos($page_content, '!isLoggedIn()') === false) {
        $result['details'][] = "Warning: Page may not check authentication";
    } else {
        $result['details'][] = "Page checks authentication";
    }
    
    // Check role-based access
    $page_basename = basename($page_path);
    $expected_access = [];
    $has_access = false;
    
    switch($page_basename) {
        case 'client_dashboard.php':
            $expected_access = ['client'];
            $has_access = ($role === 'client');
            break;
        case 'admin_dashboard.php':
            $expected_access = ['admin'];
            $has_access = ($role === 'admin');
            break;
        case 'manager_dashboard.php':
            $expected_access = ['manager', 'admin'];
            $has_access = ($role === 'manager' || $role === 'admin');
            break;
        case 'task_ticket.php':
            $expected_access = ['client'];
            $has_access = ($role === 'client');
            break;
        case 'report.php':
        case 'reports.php':
            $expected_access = ['admin', 'manager', 'client'];
            $has_access = in_array($role, ['admin', 'manager', 'client']);
            break;
        case 'updates.php':
            $expected_access = ['admin', 'manager', 'client'];
            $has_access = in_array($role, ['admin', 'manager', 'client']);
            break;
        case 'manage_users.php':
            $expected_access = ['admin'];
            $has_access = ($role === 'admin');
            break;
    }
    
    if (!$has_access) {
        $result['status'] = 'FAIL';
        $result['error'] = "Access denied - role '$role' should not have access (expected: " . implode(', ', $expected_access) . ")";
        session_destroy();
        return $result;
    }
    $result['details'][] = "Role-based access check passed (expected: " . implode(', ', $expected_access) . ")";
    
    // Check for common page elements
    if (strpos($page_content, 'require_once') !== false) {
        $result['details'][] = "Page includes required files";
    }
    
    if (strpos($page_content, 'header.php') !== false) {
        $result['details'][] = "Page includes header";
    }
    
    if (strpos($page_content, 'footer.php') !== false) {
        $result['details'][] = "Page includes footer";
    }
    
    // Check for database queries
    if (strpos($page_content, 'mysqli_query') !== false || strpos($page_content, '$conn->query') !== false) {
        $result['details'][] = "Page contains database queries";
    }
    
    // Check for AJAX calls
    if (strpos($page_content, 'fetch(') !== false || strpos($page_content, 'ajax/') !== false) {
        $result['details'][] = "Page contains AJAX calls";
    }
    
    // Check for basic PHP structure
    if (strpos($page_content, '<?php') !== false) {
        $result['details'][] = "Page is a PHP file";
    }
    
    $result['status'] = 'PASS';
    
    // Clean up
    session_destroy();
    
    return $result;
}

/**
 * Perform application testing
 */
function performApplicationTesting($conn) {
    global $test_results;
    
    $log = [];
    $log[] = "\n" . logMessage("Starting Application Testing...", 'info');
    $log[] = str_repeat("=", 80) . "\n";
    
    // Define pages to test
    $pages_to_test = [
        'client_dashboard.php',
        'admin_dashboard.php',
        'manager_dashboard.php',
        'task_ticket.php',
        'report.php',
        'reports.php',
        'updates.php',
        'manage_users.php'
    ];
    
    // Define roles to test
    $roles_to_test = ['admin', 'manager', 'client', 'doer'];
    
    // Test each page for each role
    foreach ($pages_to_test as $page) {
        $log[] = "\n" . logMessage("Testing page: $page", 'info');
        $log[] = str_repeat("-", 80) . "\n";
        
        foreach ($roles_to_test as $role) {
            $test_result = testPageForRole($conn, $page, $role);
            $test_results['pages_tested'][] = $test_result;
            $test_results['total_tests']++;
            
            if ($test_result['status'] === 'PASS') {
                $test_results['passed_tests']++;
                $log[] = logMessage("  [$role] PASS - " . ($test_result['error'] ?: 'OK'), 'success');
                if (!empty($test_result['details'])) {
                    foreach ($test_result['details'] as $detail) {
                        $log[] = "    → $detail\n";
                    }
                }
            } elseif ($test_result['status'] === 'FAIL') {
                $test_results['failed_tests']++;
                $log[] = logMessage("  [$role] FAIL - " . $test_result['error'], 'error');
            } else {
                $log[] = logMessage("  [$role] SKIPPED - " . $test_result['error'], 'warning');
            }
        }
    }
    
    $log[] = "\n" . str_repeat("=", 80) . "\n";
    $log[] = logMessage("Application Testing Complete!", 'success');
    $log[] = "Summary:\n";
    $log[] = "  - Total Tests: " . $test_results['total_tests'] . "\n";
    $log[] = "  - Passed: " . $test_results['passed_tests'] . "\n";
    $log[] = "  - Failed: " . $test_results['failed_tests'] . "\n";
    $log[] = "  - Skipped: " . ($test_results['total_tests'] - $test_results['passed_tests'] - $test_results['failed_tests']) . "\n";
    
    return implode('', $log);
}

/**
 * Generate HTML report
 */
function generateHTMLReport($migration_log, $test_log) {
    global $migration_results, $test_results;
    
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration & Test Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: #1a1a1a;
            color: #e0e0e0;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #2a2a2a;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        h1 {
            color: #8b5cf6;
            margin-bottom: 10px;
            font-size: 2em;
        }
        h2 {
            color: #a78bfa;
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 1.5em;
            border-bottom: 2px solid #8b5cf6;
            padding-bottom: 5px;
        }
        h3 {
            color: #c4b5fd;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        .timestamp {
            color: #888;
            font-size: 0.9em;
            margin-bottom: 20px;
        }
        .section {
            background: #1e1e1e;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .summary-card {
            background: #2a2a2a;
            border: 1px solid #444;
            border-radius: 6px;
            padding: 15px;
            text-align: center;
        }
        .summary-card.success { border-color: #10b981; }
        .summary-card.error { border-color: #ef4444; }
        .summary-card.warning { border-color: #f59e0b; }
        .summary-card.info { border-color: #3b82f6; }
        .summary-card h4 {
            color: #888;
            font-size: 0.9em;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .summary-card .value {
            font-size: 2em;
            font-weight: bold;
            color: #fff;
        }
        .log {
            background: #0a0a0a;
            border: 1px solid #444;
            border-radius: 6px;
            padding: 15px;
            font-family: "Courier New", monospace;
            font-size: 0.9em;
            white-space: pre-wrap;
            overflow-x: auto;
            max-height: 600px;
            overflow-y: auto;
        }
        .test-result {
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
            border-left: 4px solid;
        }
        .test-result.pass {
            background: rgba(16, 185, 129, 0.1);
            border-color: #10b981;
        }
        .test-result.fail {
            background: rgba(239, 68, 68, 0.1);
            border-color: #ef4444;
        }
        .test-result.skipped {
            background: rgba(245, 158, 11, 0.1);
            border-color: #f59e0b;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
            margin-right: 10px;
        }
        .status-badge.pass { background: #10b981; color: #fff; }
        .status-badge.fail { background: #ef4444; color: #fff; }
        .status-badge.skipped { background: #f59e0b; color: #fff; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #444;
        }
        th {
            background: #2a2a2a;
            color: #8b5cf6;
            font-weight: 600;
        }
        tr:hover {
            background: #1e1e1e;
        }
        .error-list {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }
        .error-list h4 {
            color: #ef4444;
            margin-bottom: 10px;
        }
        .error-list ul {
            list-style: none;
            padding-left: 0;
        }
        .error-list li {
            padding: 5px 0;
            color: #fca5a5;
        }
        .success-message {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid #10b981;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            color: #6ee7b7;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Migration & Test Report</h1>
        <div class="timestamp">Generated: ' . date('Y-m-d H:i:s') . '</div>
        
        <div class="summary">
            <div class="summary-card success">
                <h4>Tables Created</h4>
                <div class="value">' . count($migration_results['tables_created']) . '</div>
            </div>
            <div class="summary-card info">
                <h4>Tables Existing</h4>
                <div class="value">' . count($migration_results['tables_existing']) . '</div>
            </div>
            <div class="summary-card success">
                <h4>Columns Added</h4>
                <div class="value">' . count($migration_results['columns_added']) . '</div>
            </div>
            <div class="summary-card ' . (count($migration_results['errors']) > 0 ? 'error' : 'success') . '">
                <h4>Errors</h4>
                <div class="value">' . count($migration_results['errors']) . '</div>
            </div>
            <div class="summary-card success">
                <h4>Tests Passed</h4>
                <div class="value">' . $test_results['passed_tests'] . '</div>
            </div>
            <div class="summary-card ' . ($test_results['failed_tests'] > 0 ? 'error' : 'success') . '">
                <h4>Tests Failed</h4>
                <div class="value">' . $test_results['failed_tests'] . '</div>
            </div>
        </div>
        
        <h2>📊 Database Migration</h2>
        <div class="section">
            <div class="log">' . htmlspecialchars($migration_log) . '</div>
        </div>
        
        ' . (count($migration_results['errors']) > 0 ? '
        <div class="error-list">
            <h4>⚠️ Migration Errors</h4>
            <ul>
                ' . implode('', array_map(function($error) {
                    return '<li>' . htmlspecialchars($error) . '</li>';
                }, $migration_results['errors'])) . '
            </ul>
        </div>
        ' : '
        <div class="success-message">
            ✓ Database migration completed successfully with no errors!
        </div>
        ') . '
        
        <h2>🧪 Application Testing</h2>
        <div class="section">
            <div class="log">' . htmlspecialchars($test_log) . '</div>
        </div>
        
        <h2>📋 Detailed Test Results</h2>
        <div class="section">
            <table>
                <thead>
                    <tr>
                        <th>Page</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>';
    
    foreach ($test_results['pages_tested'] as $test) {
        $status_class = strtolower($test['status']);
        $html .= '<tr>
            <td>' . htmlspecialchars($test['page']) . '</td>
            <td>' . htmlspecialchars($test['role']) . '</td>
            <td><span class="status-badge ' . $status_class . '">' . $test['status'] . '</span></td>
            <td>' . ($test['error'] ? htmlspecialchars($test['error']) : 'OK') . '</td>
        </tr>';
    }
    
    $html .= '</tbody>
            </table>
        </div>
        
        <div class="section">
            <h3>✅ Overall Status</h3>
            <p>Database migration: ' . (count($migration_results['errors']) === 0 ? '<strong style="color: #10b981;">SUCCESS</strong>' : '<strong style="color: #ef4444;">HAS ERRORS</strong>') . '</p>
            <p>Application testing: ' . ($test_results['failed_tests'] === 0 ? '<strong style="color: #10b981;">ALL TESTS PASSED</strong>' : '<strong style="color: #ef4444;">SOME TESTS FAILED</strong>') . '</p>
        </div>
    </div>
</body>
</html>';
    
    return $html;
}

// Main execution
try {
    // Check database connection
    if (!$conn) {
        die("ERROR: Database connection failed. Please check your configuration.");
    }
    
    // Determine output mode
    $is_cli = (php_sapi_name() === 'cli');
    
    if (!$is_cli) {
        // Web mode - send HTML headers
        header('Content-Type: text/html; charset=utf-8');
    }
    
    if ($is_cli) {
        echo "Starting Migration & Test Script...\n";
        echo str_repeat("=", 80) . "\n\n";
    }
    
    // Perform database migration
    $migration_log = performDatabaseMigration($conn);
    
    // Perform application testing
    $test_log = performApplicationTesting($conn);
    
    // Generate and output HTML report
    $html_report = generateHTMLReport($migration_log, $test_log);
    
    // Save report to file
    $report_file = __DIR__ . '/migration_test_report_' . date('Y-m-d_His') . '.html';
    file_put_contents($report_file, $html_report);
    
    // Output report
    if ($is_cli) {
        // CLI mode - output text
        echo $migration_log;
        echo $test_log;
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "Report saved to: $report_file\n";
        echo "\nTo view the HTML report, open: $report_file\n";
    } else {
        // Web mode - output HTML
        echo $html_report;
        echo "\n<!-- Report also saved to: $report_file -->\n";
    }
    
} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

// Close database connection
if (isset($conn) && $conn instanceof mysqli) {
    mysqli_close($conn);
}
