<?php
/**
 * Test file for Raise Ticket functionality
 * This file helps diagnose why tickets raised via header button are not displaying
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Raise Ticket Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #1a1a1a; color: #fff; }
        .test-section { background: #2a2a2a; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .success { color: #4ade80; }
        .error { color: #f87171; }
        .warning { color: #fbbf24; }
        .info { color: #60a5fa; }
        pre { background: #1a1a1a; padding: 10px; border-radius: 4px; overflow-x: auto; max-height: 400px; overflow-y: auto; }
        button { background: #3b82f6; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        button:hover { background: #2563eb; }
        input, textarea, select { width: 100%; padding: 8px; margin: 5px 0; background: #1a1a1a; color: #fff; border: 1px solid #444; border-radius: 4px; }
        label { display: block; margin-top: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 8px; text-align: left; border: 1px solid #444; }
        th { background: #1e293b; }
    </style>
</head>
<body>
    <h1>Raise Ticket Test & Diagnostics</h1>";

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
    <p><strong>Is Client:</strong> " . (isClient() ? 'Yes' : 'No') . "</p>
    <p><strong>Is Manager:</strong> " . (isManager() ? 'Yes' : 'No') . "</p>
</div>";

// Test 1: Database Connection
echo "<div class='test-section'>
    <h2>Test 1: Database Connection</h2>";

if (isset($conn) && $conn) {
    echo "<p class='success'>✅ Database connection successful</p>";
} else {
    echo "<p class='error'>❌ Database connection failed</p>";
    exit;
}

echo "</div>";

// Test 2: Check All Items in Database
echo "<div class='test-section'>
    <h2>Test 2: All Items in Database</h2>";

$sql = "SELECT * FROM client_taskflow ORDER BY created_at DESC LIMIT 20";
$result = mysqli_query($conn, $sql);

if ($result) {
    $count = mysqli_num_rows($result);
    echo "<p class='info'>Total items found: <strong>$count</strong></p>";
    
    if ($count > 0) {
        echo "<table>
            <tr>
                <th>ID</th>
                <th>Unique ID</th>
                <th>Type</th>
                <th>Title</th>
                <th>Status</th>
                <th>Created By</th>
                <th>Created By Type</th>
                <th>Created At</th>
            </tr>";
        
        while ($row = mysqli_fetch_assoc($result)) {
            $type_class = '';
            if ($row['type'] === 'Ticket') $type_class = 'info';
            if ($row['type'] === 'Task') $type_class = 'warning';
            if ($row['type'] === 'Required') $type_class = 'error';
            
            echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['unique_id']}</td>
                <td class='$type_class'><strong>{$row['type']}</strong></td>
                <td>{$row['title']}</td>
                <td>{$row['status']}</td>
                <td>{$row['created_by']}</td>
                <td>{$row['created_by_type']}</td>
                <td>{$row['created_at']}</td>
            </tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>⚠️ No items found in database</p>";
    }
} else {
    echo "<p class='error'>❌ Query failed: " . mysqli_error($conn) . "</p>";
}

echo "</div>";

// Test 3: Check Tickets Specifically
echo "<div class='test-section'>
    <h2>Test 3: Tickets Only</h2>";

$sql = "SELECT * FROM client_taskflow WHERE type = 'Ticket' ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);

if ($result) {
    $count = mysqli_num_rows($result);
    echo "<p class='info'>Total tickets found: <strong>$count</strong></p>";
    
    if ($count > 0) {
        echo "<table>
            <tr>
                <th>ID</th>
                <th>Unique ID</th>
                <th>Title</th>
                <th>Status</th>
                <th>Created By</th>
                <th>Created By Type</th>
                <th>Created At</th>
            </tr>";
        
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['unique_id']}</td>
                <td>{$row['title']}</td>
                <td>{$row['status']}</td>
                <td>{$row['created_by']}</td>
                <td>{$row['created_by_type']}</td>
                <td>{$row['created_at']}</td>
            </tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>⚠️ No tickets found in database</p>";
    }
} else {
    echo "<p class='error'>❌ Query failed: " . mysqli_error($conn) . "</p>";
}

echo "</div>";

// Test 4: Test getItems API Response
echo "<div class='test-section'>
    <h2>Test 4: getItems API Response</h2>";

// Simulate the getItems function
$_GET['action'] = 'get_items';
ob_start();
try {
    require_once 'ajax/task_ticket_handler.php';
} catch (Exception $e) {
    echo "<p class='error'>❌ Exception: " . $e->getMessage() . "</p>";
}
$output = ob_get_clean();

echo "<h3>API Response:</h3>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

$json = json_decode($output, true);
if ($json) {
    echo "<p class='success'>✅ Valid JSON response</p>";
    echo "<p><strong>Success:</strong> " . ($json['success'] ? 'Yes' : 'No') . "</p>";
    echo "<p><strong>Items Count:</strong> " . (isset($json['items']) ? count($json['items']) : 0) . "</p>";
    
    if (isset($json['items']) && count($json['items']) > 0) {
        $tickets = array_filter($json['items'], function($item) {
            return $item['type'] === 'Ticket';
        });
        echo "<p><strong>Tickets Count:</strong> " . count($tickets) . "</p>";
        
        if (count($tickets) > 0) {
            echo "<h3>Ticket Items:</h3>";
            echo "<pre>";
            print_r($tickets);
            echo "</pre>";
        } else {
            echo "<p class='warning'>⚠️ No tickets in API response</p>";
        }
    }
} else {
    echo "<p class='error'>❌ Invalid JSON response</p>";
    echo "<p>JSON Error: " . json_last_error_msg() . "</p>";
}

echo "</div>";

// Test 5: Create Test Ticket
echo "<div class='test-section'>
    <h2>Test 5: Create Test Ticket</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_create_ticket'])) {
    echo "<h3>Test Results:</h3>";
    
    $title = $_POST['title'] ?? 'Test Ticket ' . date('Y-m-d H:i:s');
    $description = $_POST['description'] ?? 'This is a test ticket created at ' . date('Y-m-d H:i:s');
    
    try {
        // Generate unique ID
        $prefix = 'TKT';
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
        $unique_id = $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
        
        echo "<p class='info'>Generated Unique ID: <strong>$unique_id</strong></p>";
        
        $created_by_type = isClient() ? 'Client' : 'Manager';
        $status = 'Raised';
        $type = 'Ticket';
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
        
        echo "<p class='success'>✅ Ticket created successfully!</p>";
        echo "<p><strong>Database ID:</strong> $item_id</p>";
        echo "<p><strong>Unique ID:</strong> $unique_id</p>";
        echo "<p><strong>Type:</strong> $type</p>";
        echo "<p><strong>Status:</strong> $status</p>";
        echo "<p><strong>Title:</strong> $title</p>";
        echo "<p><strong>Created By Type:</strong> $created_by_type</p>";
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
}

echo "<form method='POST'>
    <label>Title:</label>
    <input type='text' name='title' value='Test Ticket " . date('Y-m-d H:i:s') . "' required>
    
    <label>Description:</label>
    <textarea name='description' rows='3' required>This is a test ticket created at " . date('Y-m-d H:i:s') . "</textarea>
    
    <button type='submit' name='test_create_ticket'>Create Test Ticket</button>
</form>";

echo "</div>";

// Test 6: Test AJAX Handler Directly
echo "<div class='test-section'>
    <h2>Test 6: Test AJAX Handler (Simulate Raise Ticket)</h2>";

if (isset($_GET['test_ajax'])) {
    echo "<h3>Simulating create_item request:</h3>";
    
    // Set up POST data
    $_POST['action'] = 'create_item';
    $_POST['type'] = 'Ticket';
    $_POST['title'] = 'Test Ticket via AJAX ' . date('Y-m-d H:i:s');
    $_POST['description'] = 'This ticket was created via AJAX handler test';
    
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
        
        // Check if ticket was actually created
        if ($json['success'] && isset($json['item'])) {
            $unique_id = $json['item']['id'] ?? '';
            echo "<p class='info'>Checking if ticket exists in database...</p>";
            
            $check_sql = "SELECT * FROM client_taskflow WHERE unique_id = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, 's', $unique_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            $check_row = mysqli_fetch_assoc($check_result);
            
            if ($check_row) {
                echo "<p class='success'>✅ Ticket found in database!</p>";
                echo "<table>
                    <tr><th>Field</th><th>Value</th></tr>
                    <tr><td>ID</td><td>{$check_row['id']}</td></tr>
                    <tr><td>Unique ID</td><td>{$check_row['unique_id']}</td></tr>
                    <tr><td>Type</td><td>{$check_row['type']}</td></tr>
                    <tr><td>Title</td><td>{$check_row['title']}</td></tr>
                    <tr><td>Status</td><td>{$check_row['status']}</td></tr>
                    <tr><td>Created By</td><td>{$check_row['created_by']}</td></tr>
                    <tr><td>Created By Type</td><td>{$check_row['created_by_type']}</td></tr>
                    <tr><td>Created At</td><td>{$check_row['created_at']}</td></tr>
                </table>";
            } else {
                echo "<p class='error'>❌ Ticket NOT found in database after creation!</p>";
            }
        }
    } else {
        echo "<p class='error'>❌ Invalid JSON response</p>";
        echo "<p>JSON Error: " . json_last_error_msg() . "</p>";
    }
} else {
    echo "<p><a href='?test_ajax=1'><button>Test AJAX Handler (Create Ticket)</button></a></p>";
}

echo "</div>";

// Test 7: Check Handler File Path
echo "<div class='test-section'>
    <h2>Test 7: Handler File Check</h2>";

$handler_file = 'ajax/task_ticket_handler.php';
if (file_exists($handler_file)) {
    echo "<p class='success'>✅ Handler file exists: $handler_file</p>";
    echo "<p><strong>Absolute path:</strong> " . realpath($handler_file) . "</p>";
} else {
    echo "<p class='error'>❌ Handler file does not exist: $handler_file</p>";
}

// Check from different locations
$paths_to_check = [
    'ajax/task_ticket_handler.php',
    '../ajax/task_ticket_handler.php',
    './ajax/task_ticket_handler.php'
];

echo "<h3>Path Resolution:</h3>";
foreach ($paths_to_check as $path) {
    if (file_exists($path)) {
        echo "<p class='success'>✅ Exists: $path</p>";
    } else {
        echo "<p class='error'>❌ Not found: $path</p>";
    }
}

echo "</div>";

// Test 8: Check Recent Tickets Created by Current User
echo "<div class='test-section'>
    <h2>Test 8: Recent Tickets Created by Current User</h2>";

$sql = "SELECT * FROM client_taskflow 
        WHERE type = 'Ticket' 
        AND created_by = $user_id 
        ORDER BY created_at DESC 
        LIMIT 10";
$result = mysqli_query($conn, $sql);

if ($result) {
    $count = mysqli_num_rows($result);
    echo "<p class='info'>Tickets created by you: <strong>$count</strong></p>";
    
    if ($count > 0) {
        echo "<table>
            <tr>
                <th>Unique ID</th>
                <th>Title</th>
                <th>Status</th>
                <th>Created At</th>
            </tr>";
        
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>
                <td>{$row['unique_id']}</td>
                <td>{$row['title']}</td>
                <td>{$row['status']}</td>
                <td>{$row['created_at']}</td>
            </tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>⚠️ You haven't created any tickets yet</p>";
    }
} else {
    echo "<p class='error'>❌ Query failed: " . mysqli_error($conn) . "</p>";
}

echo "</div>";

// Test 9: Test Frontend Page Load
echo "<div class='test-section'>
    <h2>Test 9: Frontend Page Load Test</h2>";
    
echo "<p class='info'>This test simulates what happens when the task_ticket.php page loads.</p>";
echo "<p><strong>Test Steps:</strong></p>";
echo "<ol>
    <li>Open browser console (F12)</li>
    <li>Navigate to: <a href='pages/task_ticket.php' target='_blank' style='color: #60a5fa;'>pages/task_ticket.php</a></li>
    <li>Check console for any errors</li>
    <li>Check Network tab for the get_items request</li>
    <li>Verify the response contains tickets</li>
</ol>";

echo "<h3>Direct API Test:</h3>";
echo "<p>Test the get_items API directly:</p>";
echo "<p><a href='ajax/task_ticket_handler.php?action=get_items' target='_blank' style='color: #60a5fa;'>Open API Response</a></p>";

echo "<h3>JavaScript Test Code:</h3>";
echo "<pre style='background: #1a1a1a; padding: 15px; border-radius: 4px; overflow-x: auto;'>
// Run this in browser console on task_ticket.php page:
fetch('../ajax/task_ticket_handler.php?action=get_items')
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers.get('content-type'));
        return response.json();
    })
    .then(data => {
        console.log('API Response:', data);
        console.log('Total items:', data.items ? data.items.length : 0);
        const tickets = data.items ? data.items.filter(item => item.type === 'Ticket') : [];
        console.log('Tickets found:', tickets.length);
        console.log('Tickets:', tickets);
    })
    .catch(error => {
        console.error('Error:', error);
    });
</pre>";

echo "</div>";

// Test 10: Check for Common Issues
echo "<div class='test-section'>
    <h2>Test 10: Common Issues Check</h2>";

// Check if table has any constraints
$constraints_sql = "SELECT 
    CONSTRAINT_NAME, 
    TABLE_NAME, 
    COLUMN_NAME, 
    REFERENCED_TABLE_NAME, 
    REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'client_taskflow'";
$constraints_result = mysqli_query($conn, $constraints_sql);

if ($constraints_result) {
    $constraint_count = mysqli_num_rows($constraints_result);
    echo "<p class='info'>Foreign key constraints: <strong>$constraint_count</strong></p>";
    
    if ($constraint_count > 0) {
        echo "<table>
            <tr>
                <th>Constraint Name</th>
                <th>Column</th>
                <th>References</th>
            </tr>";
        while ($row = mysqli_fetch_assoc($constraints_result)) {
            echo "<tr>
                <td>{$row['CONSTRAINT_NAME']}</td>
                <td>{$row['COLUMN_NAME']}</td>
                <td>{$row['REFERENCED_TABLE_NAME']}.{$row['REFERENCED_COLUMN_NAME']}</td>
            </tr>";
        }
        echo "</table>";
    }
}

// Check for orphaned records (created_by doesn't exist in users table)
$orphaned_sql = "SELECT ct.* FROM client_taskflow ct
    LEFT JOIN users u ON ct.created_by = u.id
    WHERE u.id IS NULL";
$orphaned_result = mysqli_query($conn, $orphaned_sql);

if ($orphaned_result) {
    $orphaned_count = mysqli_num_rows($orphaned_result);
    if ($orphaned_count > 0) {
        echo "<p class='warning'>⚠️ Found $orphaned_count orphaned records (created_by user doesn't exist)</p>";
        echo "<table>
            <tr>
                <th>ID</th>
                <th>Unique ID</th>
                <th>Type</th>
                <th>Title</th>
                <th>Created By (ID)</th>
            </tr>";
        while ($row = mysqli_fetch_assoc($orphaned_result)) {
            echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['unique_id']}</td>
                <td>{$row['type']}</td>
                <td>{$row['title']}</td>
                <td>{$row['created_by']}</td>
            </tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='success'>✅ No orphaned records found</p>";
    }
}

echo "</div>";

echo "</body></html>";
?>

