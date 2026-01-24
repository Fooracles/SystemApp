<?php
/**
 * Test file for Task & Ticket functionality
 * This file helps diagnose errors when adding tasks, tickets, or required inputs
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Task & Ticket Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #1a1a1a; color: #fff; }
        .test-section { background: #2a2a2a; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .success { color: #4ade80; }
        .error { color: #f87171; }
        .warning { color: #fbbf24; }
        .info { color: #60a5fa; }
        pre { background: #1a1a1a; padding: 10px; border-radius: 4px; overflow-x: auto; }
        button { background: #3b82f6; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        button:hover { background: #2563eb; }
        input, textarea { width: 100%; padding: 8px; margin: 5px 0; background: #1a1a1a; color: #fff; border: 1px solid #444; border-radius: 4px; }
        label { display: block; margin-top: 10px; }
    </style>
</head>
<body>
    <h1>Task & Ticket System Test</h1>";

// Include required files
require_once "includes/config.php";
require_once "includes/functions.php";

// Check if user is logged in
if (!isLoggedIn()) {
    echo "<div class='test-section error'>
        <h2>❌ Authentication Error</h2>
        <p>You must be logged in to run this test.</p>
        <p><a href='login.php'>Go to Login</a></p>
    </div>";
    exit;
}

$user_id = $_SESSION['id'] ?? $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? 'Unknown';

echo "<div class='test-section info'>
    <h2>ℹ️ Test Information</h2>
    <p><strong>User ID:</strong> $user_id</p>
    <p><strong>Username:</strong> $username</p>
    <p><strong>User Type:</strong> " . ($_SESSION['user_type'] ?? 'Unknown') . "</p>
    <p><strong>Is Manager:</strong> " . (isManager() ? 'Yes' : 'No') . "</p>
    <p><strong>Is Client:</strong> " . (isClient() ? 'Yes' : 'No') . "</p>
</div>";

// Test 1: Database Connection
echo "<div class='test-section'>
    <h2>Test 1: Database Connection</h2>";

if (isset($conn) && $conn) {
    echo "<p class='success'>✅ Database connection successful</p>";
    echo "<p><strong>Database:</strong> " . DB_NAME . "</p>";
} else {
    echo "<p class='error'>❌ Database connection failed</p>";
    exit;
}

echo "</div>";

// Test 2: Table Existence
echo "<div class='test-section'>
    <h2>Test 2: Table Existence</h2>";

$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'client_taskflow'");
if ($table_check && mysqli_num_rows($table_check) > 0) {
    echo "<p class='success'>✅ Table 'client_taskflow' exists</p>";
    
    // Check table structure
    $columns = mysqli_query($conn, "SHOW COLUMNS FROM client_taskflow");
    if ($columns) {
        echo "<h3>Table Structure:</h3><pre>";
        while ($col = mysqli_fetch_assoc($columns)) {
            echo $col['Field'] . " - " . $col['Type'] . " - " . ($col['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . "\n";
        }
        echo "</pre>";
    }
} else {
    echo "<p class='error'>❌ Table 'client_taskflow' does not exist</p>";
    echo "<p class='warning'>⚠️ The table will be created automatically on first use</p>";
}

echo "</div>";

// Test 3: Users Table Check (for JOIN)
echo "<div class='test-section'>
    <h2>Test 3: Users Table Check</h2>";

$users_check = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
if ($users_check && mysqli_num_rows($users_check) > 0) {
    echo "<p class='success'>✅ Table 'users' exists</p>";
    
    // Check if user exists
    $user_check = mysqli_query($conn, "SELECT id, username, name, user_type FROM users WHERE id = $user_id");
    if ($user_check && mysqli_num_rows($user_check) > 0) {
        $user_data = mysqli_fetch_assoc($user_check);
        echo "<p class='success'>✅ Current user found in database:</p>";
        echo "<pre>";
        print_r($user_data);
        echo "</pre>";
    } else {
        echo "<p class='error'>❌ Current user not found in database</p>";
    }
} else {
    echo "<p class='error'>❌ Table 'users' does not exist</p>";
}

echo "</div>";

// Test 4: Test Query
echo "<div class='test-section'>
    <h2>Test 4: Test Query (getItems)</h2>";

try {
    // Try with JOIN
    $sql = "SELECT ct.*, 
            u1.name as created_by_name, 
            u1.user_type as created_by_user_type,
            u2.name as assigned_to_name
            FROM client_taskflow ct
            LEFT JOIN users u1 ON ct.created_by = u1.id
            LEFT JOIN users u2 ON ct.assigned_to = u2.id
            ORDER BY ct.created_at DESC
            LIMIT 5";
    
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        echo "<p class='success'>✅ JOIN query successful</p>";
        $count = mysqli_num_rows($result);
        echo "<p><strong>Rows returned:</strong> $count</p>";
        
        if ($count > 0) {
            echo "<h3>Sample Data:</h3><pre>";
            $row = mysqli_fetch_assoc($result);
            print_r($row);
            echo "</pre>";
        }
    } else {
        echo "<p class='error'>❌ JOIN query failed: " . mysqli_error($conn) . "</p>";
        
        // Try simple query
        $sql_simple = "SELECT * FROM client_taskflow ORDER BY created_at DESC LIMIT 5";
        $result_simple = mysqli_query($conn, $sql_simple);
        
        if ($result_simple) {
            echo "<p class='warning'>⚠️ Simple query (without JOIN) works</p>";
        } else {
            echo "<p class='error'>❌ Simple query also failed: " . mysqli_error($conn) . "</p>";
        }
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Exception: " . $e->getMessage() . "</p>";
}

echo "</div>";

// Test 5: Generate Unique ID
echo "<div class='test-section'>
    <h2>Test 5: Generate Unique ID</h2>";

function testGenerateClientTaskflowUniqueId($conn, $type) {
    $prefixMap = [
        'Task' => 'TAS',
        'Ticket' => 'TKT',
        'Required' => 'REQ'
    ];
    $prefix = $prefixMap[$type] ?? 'ITM';
    
    $like_pattern = mysqli_real_escape_string($conn, $prefix . '%');
    $sql = "SELECT unique_id FROM client_taskflow WHERE unique_id LIKE '$like_pattern' ORDER BY unique_id DESC LIMIT 1";
    
    $result = mysqli_query($conn, $sql);
    
    $maxNum = 0;
    if ($result) {
        if ($row = mysqli_fetch_assoc($result)) {
            $numStr = str_replace($prefix, '', $row['unique_id']);
            $maxNum = intval($numStr) ?: 0;
        }
        mysqli_free_result($result);
    }
    
    $nextNum = $maxNum + 1;
    return $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
}

$test_types = ['Task', 'Ticket', 'Required'];
foreach ($test_types as $type) {
    $unique_id = testGenerateClientTaskflowUniqueId($conn, $type);
    echo "<p><strong>$type:</strong> <span class='success'>$unique_id</span></p>";
}

echo "</div>";

// Test 6: Create Item Test Form
echo "<div class='test-section'>
    <h2>Test 6: Create Item Test</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_create'])) {
    echo "<h3>Test Results:</h3>";
    
    $type = $_POST['type'] ?? '';
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    
    try {
        // Generate unique ID
        $unique_id = testGenerateClientTaskflowUniqueId($conn, $type);
        echo "<p class='info'>Generated Unique ID: <strong>$unique_id</strong></p>";
        
        $created_by_type = isManager() ? 'Manager' : 'Client';
        $status = 'Assigned';
        $attachments_json = null;
        
        // Prepare SQL
        $sql = "INSERT INTO client_taskflow (unique_id, type, title, description, status, created_by, created_by_type, attachments) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $sql);
        
        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
        }
        
        echo "<p class='success'>✅ Statement prepared successfully</p>";
        
        mysqli_stmt_bind_param($stmt, 'sssssiss', $unique_id, $type, $title, $description, $status, $user_id, $created_by_type, $attachments_json);
        
        echo "<p class='success'>✅ Parameters bound successfully</p>";
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Failed to execute statement: ' . mysqli_error($conn));
        }
        
        $item_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        echo "<p class='success'>✅ Item created successfully!</p>";
        echo "<p><strong>Database ID:</strong> $item_id</p>";
        echo "<p><strong>Unique ID:</strong> $unique_id</p>";
        echo "<p><strong>Type:</strong> $type</p>";
        echo "<p><strong>Title:</strong> $title</p>";
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
}

echo "<form method='POST'>
    <label>Type:</label>
    <select name='type' required>
        <option value='Task'>Task</option>
        <option value='Ticket'>Ticket</option>
        <option value='Required'>Required</option>
    </select>
    
    <label>Title:</label>
    <input type='text' name='title' value='Test Item " . date('Y-m-d H:i:s') . "' required>
    
    <label>Description:</label>
    <textarea name='description' rows='3'>This is a test item created at " . date('Y-m-d H:i:s') . "</textarea>
    
    <button type='submit' name='test_create'>Test Create Item</button>
</form>";

echo "</div>";

// Test 7: Check Handler File
echo "<div class='test-section'>
    <h2>Test 7: Handler File Check</h2>";

$handler_file = 'ajax/task_ticket_handler.php';
if (file_exists($handler_file)) {
    echo "<p class='success'>✅ Handler file exists</p>";
    
    // Check if file is readable
    if (is_readable($handler_file)) {
        echo "<p class='success'>✅ Handler file is readable</p>";
    } else {
        echo "<p class='error'>❌ Handler file is not readable</p>";
    }
    
    // Check file permissions
    $perms = fileperms($handler_file);
    echo "<p><strong>File permissions:</strong> " . substr(sprintf('%o', $perms), -4) . "</p>";
} else {
    echo "<p class='error'>❌ Handler file does not exist: $handler_file</p>";
}

echo "</div>";

// Test 8: Upload Directory Check
echo "<div class='test-section'>
    <h2>Test 8: Upload Directory Check</h2>";

$upload_dir = 'uploads/task_ticket/';
if (!file_exists($upload_dir)) {
    if (mkdir($upload_dir, 0755, true)) {
        echo "<p class='success'>✅ Upload directory created: $upload_dir</p>";
    } else {
        echo "<p class='error'>❌ Failed to create upload directory: $upload_dir</p>";
    }
} else {
    echo "<p class='success'>✅ Upload directory exists: $upload_dir</p>";
}

if (is_writable($upload_dir)) {
    echo "<p class='success'>✅ Upload directory is writable</p>";
} else {
    echo "<p class='error'>❌ Upload directory is not writable</p>";
}

echo "</div>";

// Test 9: Simulate AJAX Request
echo "<div class='test-section'>
    <h2>Test 9: Simulate AJAX Request</h2>";

if (isset($_GET['test_ajax'])) {
    echo "<h3>Simulating get_items request:</h3>";
    
    // Simulate the handler
    $_GET['action'] = 'get_items';
    
    ob_start();
    try {
        require_once 'ajax/task_ticket_handler.php';
    } catch (Exception $e) {
        echo "<p class='error'>❌ Exception: " . $e->getMessage() . "</p>";
    }
    $output = ob_get_clean();
    
    echo "<h4>Response:</h4>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
    $json = json_decode($output, true);
    if ($json) {
        echo "<p class='success'>✅ Valid JSON response</p>";
        echo "<pre>";
        print_r($json);
        echo "</pre>";
    } else {
        echo "<p class='error'>❌ Invalid JSON response</p>";
        echo "<p>JSON Error: " . json_last_error_msg() . "</p>";
    }
} else {
    echo "<p><a href='?test_ajax=1'><button>Test AJAX Handler</button></a></p>";
}

echo "</div>";

echo "</body></html>";
?>

