<?php
/**
 * Session Logging System Setup & Verification Script
 * 
 * This script verifies and sets up the session logging system:
 * - Creates user_sessions table if missing
 * - Verifies all required columns exist
 * - Tests session logging functionality
 * - Verifies Logged-In page accessibility
 * - Checks auto-logout configuration
 * - Provides detailed success/error report
 * 
 * Usage: Access via browser or run from command line
 * URL: http://yourdomain.com/setup_session_logging.php
 */

// Prevent direct access in production (optional - remove if you want to keep it accessible)
// Uncomment the line below to restrict access to localhost only
// if ($_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
//     die('Access denied. This script should only run on localhost.');
// }

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "includes/config.php";
require_once "includes/functions.php";

// Start output buffering for clean HTML
ob_start();

$report = [
    'status' => 'success',
    'checks' => [],
    'errors' => [],
    'warnings' => [],
    'info' => []
];

/**
 * Add a check result to the report
 */
function addCheck($name, $status, $message, $details = '') {
    global $report;
    $report['checks'][] = [
        'name' => $name,
        'status' => $status, // 'pass', 'fail', 'warning'
        'message' => $message,
        'details' => $details
    ];
    
    if ($status === 'fail') {
        $report['errors'][] = $name . ': ' . $message;
        $report['status'] = 'error';
    } elseif ($status === 'warning') {
        $report['warnings'][] = $name . ': ' . $message;
        if ($report['status'] === 'success') {
            $report['status'] = 'warning';
        }
    }
}

/**
 * Add info message
 */
function addInfo($message) {
    global $report;
    $report['info'][] = $message;
}

// ============================================
// CHECK 1: Database Connection
// ============================================
addInfo("Starting session logging system verification...");

if (!$conn) {
    addCheck('Database Connection', 'fail', 'Cannot connect to database', mysqli_connect_error());
    outputReport();
    exit;
}
addCheck('Database Connection', 'pass', 'Database connection successful');

// ============================================
// CHECK 2: Verify user_sessions Table Exists
// ============================================
$table_exists = false;
$check_table_sql = "SHOW TABLES LIKE 'user_sessions'";
$table_result = mysqli_query($conn, $check_table_sql);

if ($table_result && mysqli_num_rows($table_result) > 0) {
    $table_exists = true;
    addCheck('Table: user_sessions', 'pass', 'Table exists');
} else {
    addCheck('Table: user_sessions', 'fail', 'Table does not exist', 'Will attempt to create...');
}

// ============================================
// CHECK 3: Create user_sessions Table if Missing
// ============================================
if (!$table_exists) {
    $create_table_sql = "CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL COMMENT 'Username (not user_id)',
        login_time DATETIME NOT NULL,
        logout_time DATETIME NULL,
        duration_seconds INT NULL COMMENT 'Total session duration in seconds',
        ip_address VARCHAR(45) NOT NULL COMMENT 'IPv4 or IPv6 address',
        device_info TEXT NULL COMMENT 'Device and browser information',
        logout_reason VARCHAR(50) NULL COMMENT 'manual, auto, admin_forced, user_inactive',
        is_active BOOLEAN DEFAULT 1 COMMENT 'Whether session is currently active',
        INDEX (username),
        INDEX (login_time),
        INDEX (logout_time),
        INDEX (is_active),
        INDEX (ip_address)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $create_table_sql)) {
        addCheck('Table Creation', 'pass', 'user_sessions table created successfully');
        $table_exists = true;
    } else {
        addCheck('Table Creation', 'fail', 'Failed to create user_sessions table', mysqli_error($conn));
    }
} else {
    // Check and fix collation mismatch if table exists
    $check_collation_sql = "SELECT TABLE_COLLATION FROM information_schema.TABLES 
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_sessions'";
    $collation_result = mysqli_query($conn, $check_collation_sql);
    if ($collation_result && $row = mysqli_fetch_assoc($collation_result)) {
        $current_collation = $row['TABLE_COLLATION'];
        if ($current_collation !== 'utf8mb4_unicode_ci') {
            // Fix table collation
            $fix_collation_sql = "ALTER TABLE user_sessions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            if (mysqli_query($conn, $fix_collation_sql)) {
                addCheck('Table Collation Fix', 'pass', 'Fixed table collation to utf8mb4_unicode_ci', 
                         'Changed from ' . $current_collation);
            } else {
                addCheck('Table Collation Fix', 'warning', 'Could not fix table collation', 
                         mysqli_error($conn) . ' - Manual fix may be required');
            }
        } else {
            addCheck('Table Collation', 'pass', 'Table collation is correct', 'utf8mb4_unicode_ci');
        }
    }
    
    // Check and fix username column collation specifically
    $check_username_collation_sql = "SELECT COLLATION_NAME FROM information_schema.COLUMNS 
                                    WHERE TABLE_SCHEMA = DATABASE() 
                                    AND TABLE_NAME = 'user_sessions' 
                                    AND COLUMN_NAME = 'username'";
    $username_collation_result = mysqli_query($conn, $check_username_collation_sql);
    if ($username_collation_result && $row = mysqli_fetch_assoc($username_collation_result)) {
        $username_collation = $row['COLLATION_NAME'];
        if ($username_collation !== 'utf8mb4_unicode_ci') {
            // Fix username column collation
            $fix_username_collation_sql = "ALTER TABLE user_sessions 
                                          MODIFY COLUMN username VARCHAR(50) NOT NULL 
                                          COLLATE utf8mb4_unicode_ci 
                                          COMMENT 'Username (not user_id)'";
            if (mysqli_query($conn, $fix_username_collation_sql)) {
                addCheck('Username Column Collation Fix', 'pass', 'Fixed username column collation', 
                         'Changed from ' . $username_collation);
            } else {
                addCheck('Username Column Collation Fix', 'warning', 'Could not fix username collation', 
                         mysqli_error($conn) . ' - Manual fix may be required');
            }
        } else {
            addCheck('Username Column Collation', 'pass', 'Username column collation is correct');
        }
    }
}

// ============================================
// CHECK 4: Verify All Required Columns Exist
// ============================================
if ($table_exists) {
    $required_columns = [
        'id' => 'INT',
        'username' => 'VARCHAR(50)',
        'login_time' => 'DATETIME',
        'logout_time' => 'DATETIME',
        'duration_seconds' => 'INT',
        'ip_address' => 'VARCHAR(45)',
        'device_info' => 'TEXT',
        'logout_reason' => 'VARCHAR(50)',
        'is_active' => 'BOOLEAN'
    ];
    
    $columns_sql = "SHOW COLUMNS FROM user_sessions";
    $columns_result = mysqli_query($conn, $columns_sql);
    $existing_columns = [];
    
    if ($columns_result) {
        while ($row = mysqli_fetch_assoc($columns_result)) {
            $existing_columns[$row['Field']] = $row['Type'];
        }
    }
    
    $missing_columns = [];
    foreach ($required_columns as $col_name => $col_type) {
        if (!isset($existing_columns[$col_name])) {
            $missing_columns[] = $col_name;
        }
    }
    
    if (empty($missing_columns)) {
        addCheck('Table Columns', 'pass', 'All required columns exist', count($existing_columns) . ' columns found');
    } else {
        addCheck('Table Columns', 'fail', 'Missing columns: ' . implode(', ', $missing_columns));
    }
    
    // Check for indexes
    $indexes_sql = "SHOW INDEXES FROM user_sessions";
    $indexes_result = mysqli_query($conn, $indexes_sql);
    $existing_indexes = [];
    
    if ($indexes_result) {
        while ($row = mysqli_fetch_assoc($indexes_result)) {
            if ($row['Key_name'] !== 'PRIMARY') {
                $existing_indexes[] = $row['Key_name'];
            }
        }
    }
    
    $required_indexes = ['username', 'login_time', 'logout_time', 'is_active', 'ip_address'];
    $missing_indexes = array_diff($required_indexes, $existing_indexes);
    
    if (empty($missing_indexes)) {
        addCheck('Table Indexes', 'pass', 'All required indexes exist');
    } else {
        addCheck('Table Indexes', 'warning', 'Some indexes missing: ' . implode(', ', $missing_indexes), 
                 'Performance may be affected but functionality will work');
    }
}

// ============================================
// CHECK 5: Verify Required Functions Exist
// ============================================
$required_functions = [
    'getClientIpAddress',
    'getDeviceInfo',
    'isNewDevice',
    'logUserLogin',
    'logUserLogout',
    'forceLogoutUser',
    'isSessionExpired',
    'formatSessionDuration'
];

$missing_functions = [];
foreach ($required_functions as $func_name) {
    if (!function_exists($func_name)) {
        $missing_functions[] = $func_name;
    }
}

if (empty($missing_functions)) {
    addCheck('Required Functions', 'pass', 'All required functions exist', count($required_functions) . ' functions found');
} else {
    addCheck('Required Functions', 'fail', 'Missing functions: ' . implode(', ', $missing_functions));
}

// ============================================
// CHECK 6: Test Session Logging Functions
// ============================================
if (empty($missing_functions)) {
    // Test getClientIpAddress
    $test_ip = getClientIpAddress();
    if (!empty($test_ip) && $test_ip !== '0.0.0.0') {
        addCheck('Function: getClientIpAddress', 'pass', 'Returns IP address', 'IP: ' . $test_ip);
    } else {
        addCheck('Function: getClientIpAddress', 'warning', 'Returns default/empty IP', 'May be normal on localhost');
    }
    
    // Test getDeviceInfo
    $test_device = getDeviceInfo();
    if (!empty($test_device) && $test_device !== 'Unknown / Unknown') {
        addCheck('Function: getDeviceInfo', 'pass', 'Returns device info', 'Device: ' . $test_device);
    } else {
        addCheck('Function: getDeviceInfo', 'warning', 'Returns unknown device', 'May be normal if User-Agent is missing');
    }
    
    // Test formatSessionDuration
    $test_duration = formatSessionDuration(3665); // 1 hour, 1 minute, 5 seconds
    if ($test_duration === '1h 1m') {
        addCheck('Function: formatSessionDuration', 'pass', 'Formats duration correctly', '3665 seconds = ' . $test_duration);
    } else {
        addCheck('Function: formatSessionDuration', 'fail', 'Incorrect duration format', 'Expected: 1h 1m, Got: ' . $test_duration);
    }
}

// ============================================
// CHECK 7: Verify Logged-In Page Exists
// ============================================
$logged_in_page = 'pages/logged_in.php';
if (file_exists($logged_in_page)) {
    addCheck('Logged-In Page File', 'pass', 'File exists', $logged_in_page);
    
    // Check if page has required content
    $page_content = file_get_contents($logged_in_page);
    $required_strings = [
        'Logged-In Users',
        'get_sessions',
        'sessions_handler.php',
        'isAdmin()'
    ];
    
    $missing_strings = [];
    foreach ($required_strings as $str) {
        if (strpos($page_content, $str) === false) {
            $missing_strings[] = $str;
        }
    }
    
    if (empty($missing_strings)) {
        addCheck('Logged-In Page Content', 'pass', 'Page contains required functionality');
    } else {
        addCheck('Logged-In Page Content', 'warning', 'Missing expected content: ' . implode(', ', $missing_strings));
    }
} else {
    addCheck('Logged-In Page File', 'fail', 'File does not exist', $logged_in_page);
}

// ============================================
// CHECK 8: Verify AJAX Handler Exists
// ============================================
$ajax_handler = 'ajax/sessions_handler.php';
if (file_exists($ajax_handler)) {
    addCheck('AJAX Handler File', 'pass', 'File exists', $ajax_handler);
    
    // Check if handler has required functions
    $handler_content = file_get_contents($ajax_handler);
    $required_actions = ['get_sessions', 'export_sessions', 'autoLogoutInactiveUsers'];
    
    $missing_actions = [];
    foreach ($required_actions as $action) {
        if (strpos($handler_content, $action) === false) {
            $missing_actions[] = $action;
        }
    }
    
    if (empty($missing_actions)) {
        addCheck('AJAX Handler Content', 'pass', 'Handler contains required actions');
    } else {
        addCheck('AJAX Handler Content', 'warning', 'Missing expected actions: ' . implode(', ', $missing_actions));
    }
} else {
    addCheck('AJAX Handler File', 'fail', 'File does not exist', $ajax_handler);
}

// ============================================
// CHECK 9: Verify Sidebar Link
// ============================================
$sidebar_file = 'includes/sidebar.php';
if (file_exists($sidebar_file)) {
    $sidebar_content = file_get_contents($sidebar_file);
    if (strpos($sidebar_content, 'logged_in.php') !== false && strpos($sidebar_content, 'Logged-In') !== false) {
        addCheck('Sidebar Link', 'pass', 'Logged-In link found in sidebar');
    } else {
        addCheck('Sidebar Link', 'warning', 'Logged-In link may be missing from sidebar');
    }
} else {
    addCheck('Sidebar Link', 'warning', 'Cannot verify sidebar (file not found)');
}

// ============================================
// CHECK 10: Verify Login.php Has Session Logging
// ============================================
$login_file = 'login.php';
if (file_exists($login_file)) {
    $login_content = file_get_contents($login_file);
    if (strpos($login_content, 'logUserLogin') !== false) {
        addCheck('Login Session Logging', 'pass', 'login.php includes session logging');
    } else {
        addCheck('Login Session Logging', 'fail', 'login.php missing session logging call');
    }
} else {
    addCheck('Login Session Logging', 'warning', 'Cannot verify login.php (file not found)');
}

// ============================================
// CHECK 11: Verify Logout.php Has Session Logging
// ============================================
$logout_file = 'logout.php';
if (file_exists($logout_file)) {
    $logout_content = file_get_contents($logout_file);
    if (strpos($logout_content, 'logUserLogout') !== false) {
        addCheck('Logout Session Logging', 'pass', 'logout.php includes session logging');
    } else {
        addCheck('Logout Session Logging', 'fail', 'logout.php missing session logging call');
    }
} else {
    addCheck('Logout Session Logging', 'warning', 'Cannot verify logout.php (file not found)');
}

// ============================================
// CHECK 12: Verify Header Has Auto-Logout Check
// ============================================
$header_file = 'includes/header.php';
if (file_exists($header_file)) {
    $header_content = file_get_contents($header_file);
    $header_checks = [
        'isSessionExpired' => 'Auto-logout (8:00 PM) check',
        'autoLogoutAllSessionsAt8PM' => '8:00 PM batch logout check',
        'user_inactive' => 'Inactive user auto-logout check'
    ];
    
    foreach ($header_checks as $check_str => $check_name) {
        if (strpos($header_content, $check_str) !== false) {
            addCheck('Header: ' . $check_name, 'pass', 'Check found in header');
        } else {
            addCheck('Header: ' . $check_name, 'warning', 'Check may be missing from header');
        }
    }
} else {
    addCheck('Header Auto-Logout', 'warning', 'Cannot verify header.php (file not found)');
}

// ============================================
// CHECK 13: Test Database Operations (Read-Only)
// ============================================
if ($table_exists) {
    // Count total sessions
    $count_sql = "SELECT COUNT(*) as total FROM user_sessions";
    $count_result = mysqli_query($conn, $count_sql);
    if ($count_result) {
        $count_row = mysqli_fetch_assoc($count_result);
        $total_sessions = (int)$count_row['total'];
        addCheck('Database Read Test', 'pass', 'Can read from user_sessions table', $total_sessions . ' total sessions found');
        
        // Count active sessions
        $active_sql = "SELECT COUNT(*) as active FROM user_sessions WHERE is_active = 1";
        $active_result = mysqli_query($conn, $active_sql);
        if ($active_result) {
            $active_row = mysqli_fetch_assoc($active_result);
            $active_sessions = (int)$active_row['active'];
            addInfo("Active sessions: $active_sessions");
        }
    } else {
        addCheck('Database Read Test', 'fail', 'Cannot read from user_sessions table', mysqli_error($conn));
    }
}

// ============================================
// CHECK 14: Verify Access Control
// ============================================
$functions_file = 'includes/functions.php';
if (file_exists($functions_file)) {
    $functions_content = file_get_contents($functions_file);
    if (strpos($functions_content, "pages/logged_in.php' => ['admin']") !== false) {
        addCheck('Access Control', 'pass', 'Logged-In page restricted to admins');
    } else {
        addCheck('Access Control', 'warning', 'Access control may not be configured for Logged-In page');
    }
} else {
    addCheck('Access Control', 'warning', 'Cannot verify access control');
}

// ============================================
// CHECK 15: Verify Session Expiration Time (8:00 PM)
// ============================================
if (function_exists('isSessionExpired')) {
    // Check if 8:00 PM auto-logout is implemented
    $footer_file = 'includes/footer.php';
    if (file_exists($footer_file)) {
        $footer_content = file_get_contents($footer_file);
        if (strpos($footer_content, 'logoutHour = 20') !== false || strpos($footer_content, '8:00 PM') !== false) {
            addCheck('Session Expiration (8:00 PM)', 'pass', '8:00 PM auto-logout configured');
        } else {
            addCheck('Session Expiration (8:00 PM)', 'warning', '8:00 PM auto-logout may not be configured in footer');
        }
    }
    
    // Check if autoLogoutAllSessionsAt8PM function exists
    if (function_exists('autoLogoutAllSessionsAt8PM')) {
        addCheck('Function: autoLogoutAllSessionsAt8PM', 'pass', '8:00 PM batch logout function exists');
    } else {
        addCheck('Function: autoLogoutAllSessionsAt8PM', 'fail', '8:00 PM batch logout function missing');
    }
}

// ============================================
// OUTPUT REPORT
// ============================================
function outputReport() {
    global $report;
    
    $status_class = [
        'success' => 'success',
        'warning' => 'warning',
        'error' => 'danger'
    ];
    
    $status_icon = [
        'success' => '✓',
        'warning' => '⚠',
        'error' => '✗'
    ];
    
    $status_text = [
        'success' => 'All checks passed!',
        'warning' => 'Some warnings found',
        'error' => 'Errors detected - please fix'
    ];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Session Logging System - Setup & Verification</title>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
        <style>
            body {
                background: #f5f5f5;
                padding: 20px;
            }
            .report-card {
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                padding: 30px;
                margin-bottom: 20px;
            }
            .status-badge {
                font-size: 18px;
                padding: 10px 20px;
                border-radius: 5px;
                margin-bottom: 20px;
            }
            .check-item {
                padding: 12px;
                margin: 8px 0;
                border-left: 4px solid #ddd;
                background: #f9f9f9;
                border-radius: 4px;
            }
            .check-item.pass {
                border-left-color: #28a745;
                background: #d4edda;
            }
            .check-item.fail {
                border-left-color: #dc3545;
                background: #f8d7da;
            }
            .check-item.warning {
                border-left-color: #ffc107;
                background: #fff3cd;
            }
            .check-name {
                font-weight: 600;
                font-size: 16px;
            }
            .check-message {
                margin-top: 5px;
                color: #555;
            }
            .check-details {
                margin-top: 5px;
                font-size: 12px;
                color: #777;
                font-style: italic;
            }
            .summary-box {
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
            }
            .summary-box.success {
                background: #d4edda;
                border: 1px solid #28a745;
            }
            .summary-box.warning {
                background: #fff3cd;
                border: 1px solid #ffc107;
            }
            .summary-box.error {
                background: #f8d7da;
                border: 1px solid #dc3545;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="report-card">
                <h1 class="mb-4">
                    <i class="fas fa-clipboard-check"></i> Session Logging System - Setup & Verification
                </h1>
                
                <div class="summary-box <?php echo $status_class[$report['status']]; ?>">
                    <h3>
                        <?php echo $status_icon[$report['status']]; ?> 
                        Status: <?php echo strtoupper($report['status']); ?>
                    </h3>
                    <p><?php echo $status_text[$report['status']]; ?></p>
                </div>
                
                <h3 class="mt-4 mb-3">Verification Results</h3>
                
                <?php
                $pass_count = 0;
                $fail_count = 0;
                $warning_count = 0;
                
                foreach ($report['checks'] as $check) {
                    if ($check['status'] === 'pass') $pass_count++;
                    if ($check['status'] === 'fail') $fail_count++;
                    if ($check['status'] === 'warning') $warning_count++;
                    
                    $icon = [
                        'pass' => '<i class="fas fa-check-circle text-success"></i>',
                        'fail' => '<i class="fas fa-times-circle text-danger"></i>',
                        'warning' => '<i class="fas fa-exclamation-triangle text-warning"></i>'
                    ];
                    ?>
                    <div class="check-item <?php echo $check['status']; ?>">
                        <div class="check-name">
                            <?php echo $icon[$check['status']]; ?> 
                            <?php echo htmlspecialchars($check['name']); ?>
                        </div>
                        <div class="check-message">
                            <?php echo htmlspecialchars($check['message']); ?>
                        </div>
                        <?php if (!empty($check['details'])): ?>
                            <div class="check-details">
                                <?php echo htmlspecialchars($check['details']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php } ?>
                
                <div class="mt-4">
                    <h4>Summary</h4>
                    <ul>
                        <li><strong>Passed:</strong> <?php echo $pass_count; ?></li>
                        <li><strong>Failed:</strong> <?php echo $fail_count; ?></li>
                        <li><strong>Warnings:</strong> <?php echo $warning_count; ?></li>
                        <li><strong>Total Checks:</strong> <?php echo count($report['checks']); ?></li>
                    </ul>
                </div>
                
                <?php if (!empty($report['info'])): ?>
                    <div class="mt-4">
                        <h4>Additional Information</h4>
                        <ul>
                            <?php foreach ($report['info'] as $info): ?>
                                <li><?php echo htmlspecialchars($info); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($report['errors'])): ?>
                    <div class="mt-4">
                        <h4 class="text-danger">Errors to Fix</h4>
                        <ul>
                            <?php foreach ($report['errors'] as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($report['warnings'])): ?>
                    <div class="mt-4">
                        <h4 class="text-warning">Warnings</h4>
                        <ul>
                            <?php foreach ($report['warnings'] as $warning): ?>
                                <li><?php echo htmlspecialchars($warning); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="mt-4">
                    <p class="text-muted">
                        <small>
                            <i class="fas fa-info-circle"></i> 
                            Run this script after deployment to verify the session logging system is working correctly.
                            You can safely delete this file after verification.
                        </small>
                    </p>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

// Output the report
outputReport();
ob_end_flush();
?>

