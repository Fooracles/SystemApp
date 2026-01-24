<?php
/**
 * Session Logging System - Setup & Diagnostic Script
 * 
 * This script will:
 * 1. Create the user_sessions table if it doesn't exist
 * 2. Add any missing columns
 * 3. Verify data integrity
 * 4. Display diagnostic information
 */

// Start session
session_start();

// Include config
require_once "includes/config.php";
require_once "includes/functions.php";

// Set content type for browser display
header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Logging System - Setup & Diagnostics</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #1a1a2e;
            color: #eee;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        h1, h2, h3 { color: #00d4ff; }
        .card {
            background: #16213e;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .success { color: #00ff88; }
        .error { color: #ff4757; }
        .warning { color: #ffa502; }
        .info { color: #00d4ff; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #333;
        }
        th { background: #0f3460; }
        tr:hover { background: #1f4068; }
        code {
            background: #0f0f23;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Consolas', monospace;
        }
        pre {
            background: #0f0f23;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-success { background: #00ff88; color: #000; }
        .badge-error { background: #ff4757; color: #fff; }
        .badge-warning { background: #ffa502; color: #000; }
    </style>
</head>
<body>
    <h1>🔧 Session Logging System - Setup & Diagnostics</h1>
    
    <?php
    $errors = [];
    $warnings = [];
    $success = [];
    
    // ========================================
    // STEP 1: Check Database Connection
    // ========================================
    echo '<div class="card">';
    echo '<h2>1. Database Connection</h2>';
    
    if ($conn && !mysqli_connect_error()) {
        echo '<p class="success">✅ Database connection successful</p>';
        echo '<p>Server: <code>' . DB_SERVER . '</code> | Database: <code>' . DB_NAME . '</code></p>';
        $success[] = "Database connection OK";
    } else {
        echo '<p class="error">❌ Database connection failed: ' . mysqli_connect_error() . '</p>';
        $errors[] = "Database connection failed";
        echo '</div></body></html>';
        exit;
    }
    echo '</div>';
    
    // ========================================
    // STEP 2: Check/Create user_sessions Table
    // ========================================
    echo '<div class="card">';
    echo '<h2>2. Table: user_sessions</h2>';
    
    // Check if table exists
    $table_exists = false;
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'user_sessions'");
    if (mysqli_num_rows($check_table) > 0) {
        $table_exists = true;
        echo '<p class="success">✅ Table <code>user_sessions</code> exists</p>';
        $success[] = "Table user_sessions exists";
    } else {
        echo '<p class="warning">⚠️ Table <code>user_sessions</code> does not exist. Creating...</p>';
        
        // Create the table
        $create_sql = "CREATE TABLE IF NOT EXISTS user_sessions (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        if (mysqli_query($conn, $create_sql)) {
            echo '<p class="success">✅ Table created successfully!</p>';
            $table_exists = true;
            $success[] = "Table user_sessions created";
        } else {
            echo '<p class="error">❌ Failed to create table: ' . mysqli_error($conn) . '</p>';
            $errors[] = "Failed to create user_sessions table";
        }
    }
    echo '</div>';
    
    // ========================================
    // STEP 3: Verify Table Structure
    // ========================================
    if ($table_exists) {
        echo '<div class="card">';
        echo '<h2>3. Table Structure Verification</h2>';
        
        // Expected columns
        $expected_columns = [
            'id' => 'int',
            'username' => 'varchar(50)',
            'login_time' => 'datetime',
            'logout_time' => 'datetime',
            'duration_seconds' => 'int',
            'ip_address' => 'varchar(45)',
            'device_info' => 'text',
            'logout_reason' => 'varchar(50)',
            'is_active' => 'tinyint(1)'
        ];
        
        // Get actual columns
        $columns_result = mysqli_query($conn, "DESCRIBE user_sessions");
        $actual_columns = [];
        while ($col = mysqli_fetch_assoc($columns_result)) {
            $actual_columns[$col['Field']] = strtolower($col['Type']);
        }
        
        echo '<table>';
        echo '<tr><th>Column</th><th>Expected Type</th><th>Actual Type</th><th>Status</th></tr>';
        
        foreach ($expected_columns as $col_name => $expected_type) {
            $status = '';
            $actual_type = $actual_columns[$col_name] ?? 'MISSING';
            
            if (!isset($actual_columns[$col_name])) {
                $status = '<span class="badge badge-error">MISSING</span>';
                $errors[] = "Column '$col_name' is missing";
                
                // Try to add missing column
                $add_sql = getAddColumnSQL($col_name);
                if ($add_sql && mysqli_query($conn, $add_sql)) {
                    $status .= ' <span class="badge badge-success">ADDED</span>';
                    $success[] = "Column '$col_name' added";
                }
            } else {
                $status = '<span class="badge badge-success">OK</span>';
            }
            
            echo "<tr><td><code>$col_name</code></td><td>$expected_type</td><td>$actual_type</td><td>$status</td></tr>";
        }
        
        echo '</table>';
        echo '</div>';
        
        // ========================================
        // STEP 4: Check Indexes
        // ========================================
        echo '<div class="card">';
        echo '<h2>4. Indexes</h2>';
        
        $indexes_result = mysqli_query($conn, "SHOW INDEX FROM user_sessions");
        $indexes = [];
        while ($idx = mysqli_fetch_assoc($indexes_result)) {
            $indexes[$idx['Key_name']][] = $idx['Column_name'];
        }
        
        echo '<table>';
        echo '<tr><th>Index Name</th><th>Columns</th></tr>';
        foreach ($indexes as $name => $cols) {
            echo "<tr><td><code>$name</code></td><td>" . implode(', ', $cols) . "</td></tr>";
        }
        echo '</table>';
        echo '</div>';
        
        // ========================================
        // STEP 5: Data Statistics
        // ========================================
        echo '<div class="card">';
        echo '<h2>5. Data Statistics</h2>';
        
        // Total sessions
        $total_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM user_sessions");
        $total = mysqli_fetch_assoc($total_result)['total'];
        
        // Active sessions
        $active_result = mysqli_query($conn, "SELECT COUNT(*) as active FROM user_sessions WHERE is_active = 1");
        $active = mysqli_fetch_assoc($active_result)['active'];
        
        // Inactive/History sessions
        $history = $total - $active;
        
        // Unique users
        $users_result = mysqli_query($conn, "SELECT COUNT(DISTINCT username) as users FROM user_sessions");
        $unique_users = mysqli_fetch_assoc($users_result)['users'];
        
        echo '<table>';
        echo '<tr><th>Metric</th><th>Value</th></tr>';
        echo "<tr><td>Total Session Records</td><td><strong>$total</strong></td></tr>";
        echo "<tr><td>Active Sessions (is_active = 1)</td><td><strong class='success'>$active</strong></td></tr>";
        echo "<tr><td>Session History (is_active = 0)</td><td><strong>$history</strong></td></tr>";
        echo "<tr><td>Unique Users</td><td><strong>$unique_users</strong></td></tr>";
        echo '</table>';
        
        if ($total == 0) {
            $warnings[] = "No session data found. Sessions will be recorded after users login.";
            echo '<p class="warning">⚠️ No session data yet. Data will be recorded when users login.</p>';
        }
        echo '</div>';
        
        // ========================================
        // STEP 6: Sample Data (Last 10 sessions)
        // ========================================
        echo '<div class="card">';
        echo '<h2>6. Recent Sessions (Last 10)</h2>';
        
        $recent_result = mysqli_query($conn, "SELECT * FROM user_sessions ORDER BY login_time DESC LIMIT 10");
        
        if (mysqli_num_rows($recent_result) > 0) {
            echo '<table>';
            echo '<tr><th>ID</th><th>User</th><th>Login</th><th>Logout</th><th>Duration</th><th>IP</th><th>Device</th><th>Reason</th><th>Active</th></tr>';
            
            while ($row = mysqli_fetch_assoc($recent_result)) {
                $duration = $row['duration_seconds'] ? formatSessionDuration($row['duration_seconds']) : '-';
                $is_active = $row['is_active'] ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-warning">No</span>';
                $logout = $row['logout_time'] ?? '-';
                $reason = $row['logout_reason'] ?? '-';
                $device = htmlspecialchars($row['device_info'] ?? 'Unknown');
                
                echo "<tr>";
                echo "<td>{$row['id']}</td>";
                echo "<td><strong>{$row['username']}</strong></td>";
                echo "<td>{$row['login_time']}</td>";
                echo "<td>$logout</td>";
                echo "<td>$duration</td>";
                echo "<td>{$row['ip_address']}</td>";
                echo "<td>$device</td>";
                echo "<td>$reason</td>";
                echo "<td>$is_active</td>";
                echo "</tr>";
            }
            echo '</table>';
        } else {
            echo '<p class="info">No session records found yet.</p>';
        }
        echo '</div>';
        
        // ========================================
        // STEP 7: Check for Issues
        // ========================================
        echo '<div class="card">';
        echo '<h2>7. Issue Detection</h2>';
        
        // Check for orphan sessions (active but login > 10 hours ago)
        $orphan_result = mysqli_query($conn, "SELECT COUNT(*) as orphans FROM user_sessions 
            WHERE is_active = 1 AND login_time < DATE_SUB(NOW(), INTERVAL 10 HOUR)");
        $orphans = mysqli_fetch_assoc($orphan_result)['orphans'];
        
        if ($orphans > 0) {
            $warnings[] = "$orphans orphan session(s) found (active but login > 10 hours ago)";
            echo "<p class='warning'>⚠️ Found $orphans orphan session(s) - Active sessions older than 10 hours.</p>";
            echo "<p>These will be auto-cleaned when users access any page or admin views Logged-In page.</p>";
        }
        
        // Check for sessions with NULL username
        $null_user_result = mysqli_query($conn, "SELECT COUNT(*) as nulls FROM user_sessions WHERE username IS NULL OR username = ''");
        $null_users = mysqli_fetch_assoc($null_user_result)['nulls'];
        
        if ($null_users > 0) {
            $errors[] = "$null_users session(s) with NULL/empty username";
            echo "<p class='error'>❌ Found $null_users session(s) with NULL or empty username.</p>";
        }
        
        // Check if Status column exists in users table
        $status_col_result = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'Status'");
        if (mysqli_num_rows($status_col_result) == 0) {
            $warnings[] = "Users table might not have 'Status' column - auto-logout for inactive users won't work";
            echo "<p class='warning'>⚠️ The 'Status' column in users table was not found. Auto-logout for inactive users may not work.</p>";
        } else {
            echo "<p class='success'>✅ Users table has 'Status' column for inactive user detection.</p>";
        }
        
        if (empty($errors) && $orphans == 0 && $null_users == 0) {
            echo "<p class='success'>✅ No critical issues detected!</p>";
        }
        echo '</div>';
    }
    
    // ========================================
    // STEP 8: Function Tests
    // ========================================
    echo '<div class="card">';
    echo '<h2>8. Function Tests</h2>';
    
    // Test getClientIpAddress
    echo '<p><strong>getClientIpAddress():</strong> <code>' . getClientIpAddress() . '</code></p>';
    
    // Test getDeviceInfo
    echo '<p><strong>getDeviceInfo():</strong> <code>' . getDeviceInfo() . '</code></p>';
    
    // Test isSessionExpired
    if (isset($_SESSION['login_time'])) {
        $expired = isSessionExpired() ? 'Yes (Expired)' : 'No (Valid)';
        $login_time = date('Y-m-d H:i:s', $_SESSION['login_time']);
        echo "<p><strong>Current Session Login Time:</strong> <code>$login_time</code></p>";
        echo "<p><strong>isSessionExpired():</strong> <code>$expired</code></p>";
    } else {
        echo '<p class="warning">⚠️ No active PHP session login_time found. Login to test session expiration.</p>';
    }
    
    // Test formatSessionDuration
    echo '<p><strong>formatSessionDuration(3665):</strong> <code>' . formatSessionDuration(3665) . '</code> (expected: 1h 1m)</p>';
    
    echo '</div>';
    
    // ========================================
    // SUMMARY
    // ========================================
    echo '<div class="card">';
    echo '<h2>📋 Summary</h2>';
    
    if (!empty($errors)) {
        echo '<h3 class="error">Errors (' . count($errors) . ')</h3>';
        echo '<ul>';
        foreach ($errors as $err) {
            echo "<li class='error'>❌ $err</li>";
        }
        echo '</ul>';
    }
    
    if (!empty($warnings)) {
        echo '<h3 class="warning">Warnings (' . count($warnings) . ')</h3>';
        echo '<ul>';
        foreach ($warnings as $warn) {
            echo "<li class='warning'>⚠️ $warn</li>";
        }
        echo '</ul>';
    }
    
    if (!empty($success)) {
        echo '<h3 class="success">Success (' . count($success) . ')</h3>';
        echo '<ul>';
        foreach ($success as $succ) {
            echo "<li class='success'>✅ $succ</li>";
        }
        echo '</ul>';
    }
    
    if (empty($errors)) {
        echo '<p class="success" style="font-size: 18px; margin-top: 20px;">✅ Session logging system is ready to use!</p>';
        echo '<p>Visit <a href="pages/logged_in.php" style="color: #00d4ff;">Logged-In Page</a> to view sessions (Admin only).</p>';
    } else {
        echo '<p class="error" style="font-size: 18px; margin-top: 20px;">❌ Please fix the errors above before using the session logging system.</p>';
    }
    
    echo '</div>';
    
    // Helper function to get ADD COLUMN SQL
    function getAddColumnSQL($col_name) {
        $columns = [
            'username' => "ALTER TABLE user_sessions ADD COLUMN username VARCHAR(50) NOT NULL AFTER id",
            'login_time' => "ALTER TABLE user_sessions ADD COLUMN login_time DATETIME NOT NULL AFTER username",
            'logout_time' => "ALTER TABLE user_sessions ADD COLUMN logout_time DATETIME NULL AFTER login_time",
            'duration_seconds' => "ALTER TABLE user_sessions ADD COLUMN duration_seconds INT NULL AFTER logout_time",
            'ip_address' => "ALTER TABLE user_sessions ADD COLUMN ip_address VARCHAR(45) NOT NULL AFTER duration_seconds",
            'device_info' => "ALTER TABLE user_sessions ADD COLUMN device_info TEXT NULL AFTER ip_address",
            'logout_reason' => "ALTER TABLE user_sessions ADD COLUMN logout_reason VARCHAR(50) NULL AFTER device_info",
            'is_active' => "ALTER TABLE user_sessions ADD COLUMN is_active BOOLEAN DEFAULT 1 AFTER logout_reason"
        ];
        return $columns[$col_name] ?? null;
    }
    ?>
    
    <div class="card" style="margin-top: 30px; text-align: center; opacity: 0.7;">
        <p>Session Logging System Diagnostic Tool | Generated: <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
</body>
</html>

