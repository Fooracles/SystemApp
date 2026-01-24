<?php
/**
 * Fix Task and Ticket assigned_to Field for Existing Data
 * 
 * This script updates existing Task and Ticket items created by clients
 * to set the assigned_to field to their manager's ID.
 * 
 * SECURITY: Only accessible by admin users
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is admin (for security)
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    die("Access denied. Admin access required.");
}

// Only run the fix if ?run=1 is in the URL
$run_fix = isset($_GET['run']) && $_GET['run'] == '1';

$results = [];
$errors = [];
$warnings = [];
$fixes_applied = 0;

// Function to log results
function addResult($type, $message, $count = null) {
    global $results;
    $results[] = [
        'type' => $type,
        'message' => $message,
        'count' => $count,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

if ($run_fix) {
    // Database connection is available from config.php as $conn
    if (!isset($conn) || !$conn) {
        die("Database connection failed.");
    }
    
    // Check if client_taskflow table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'client_taskflow'");
    if (mysqli_num_rows($table_check) == 0) {
        addResult('error', 'client_taskflow table does not exist');
        mysqli_close($conn);
        $show_results = true;
    } else {
        // Step 1: Find all Task and Ticket items with NULL assigned_to created by clients
        addResult('info', 'Scanning for Task and Ticket items with NULL assigned_to...');
        
        $find_items_sql = "SELECT ct.id, ct.unique_id, ct.type, ct.created_by, ct.created_by_type
                           FROM client_taskflow ct
                           WHERE ct.type IN ('Task', 'Ticket')
                           AND ct.assigned_to IS NULL
                           AND ct.created_by_type = 'Client'";
        
        $find_result = mysqli_query($conn, $find_items_sql);
        
        if (!$find_result) {
            $error_msg = mysqli_error($conn);
            addResult('error', "Error finding items: $error_msg");
            mysqli_close($conn);
            $show_results = true;
        } else {
            $items_to_fix = [];
            $items_fixed = 0;
            $items_skipped = 0;
            $items_with_errors = 0;
            
            while ($row = mysqli_fetch_assoc($find_result)) {
                $item_id = $row['id'];
                $unique_id = $row['unique_id'];
                $item_type = $row['type'];
                $created_by = $row['created_by'];
                
                // Get client user's manager_id (which points to their client account)
                $client_user_sql = "SELECT manager_id FROM users WHERE id = ?";
                $client_user_stmt = mysqli_prepare($conn, $client_user_sql);
                
                if (!$client_user_stmt) {
                    addResult('error', "Failed to prepare statement for item $unique_id: " . mysqli_error($conn));
                    $items_with_errors++;
                    continue;
                }
                
                mysqli_stmt_bind_param($client_user_stmt, 'i', $created_by);
                mysqli_stmt_execute($client_user_stmt);
                $client_user_result = mysqli_stmt_get_result($client_user_stmt);
                $client_user_data = mysqli_fetch_assoc($client_user_result);
                mysqli_stmt_close($client_user_stmt);
                
                if (!$client_user_data || empty($client_user_data['manager_id'])) {
                    addResult('warning', "Item $unique_id: Client user (ID: $created_by) has no manager_id (client account)");
                    $items_skipped++;
                    continue;
                }
                
                $client_account_id = $client_user_data['manager_id'];
                
                // Get the manager assigned to this client account
                $account_sql = "SELECT manager_id FROM users 
                               WHERE id = ? 
                               AND user_type = 'client' 
                               AND (password IS NULL OR password = '')";
                $account_stmt = mysqli_prepare($conn, $account_sql);
                
                if (!$account_stmt) {
                    addResult('error', "Failed to prepare statement for client account $client_account_id: " . mysqli_error($conn));
                    $items_with_errors++;
                    continue;
                }
                
                mysqli_stmt_bind_param($account_stmt, 'i', $client_account_id);
                mysqli_stmt_execute($account_stmt);
                $account_result = mysqli_stmt_get_result($account_stmt);
                $account_data = mysqli_fetch_assoc($account_result);
                mysqli_stmt_close($account_stmt);
                
                if (!$account_data || empty($account_data['manager_id'])) {
                    addResult('warning', "Item $unique_id: Client account (ID: $client_account_id) has no manager assigned");
                    $items_skipped++;
                    continue;
                }
                
                $manager_id = $account_data['manager_id'];
                
                // Verify the manager exists and is actually a manager
                $manager_check_sql = "SELECT id, name FROM users WHERE id = ? AND user_type = 'manager'";
                $manager_check_stmt = mysqli_prepare($conn, $manager_check_sql);
                
                if (!$manager_check_stmt) {
                    addResult('error', "Failed to verify manager $manager_id: " . mysqli_error($conn));
                    $items_with_errors++;
                    continue;
                }
                
                mysqli_stmt_bind_param($manager_check_stmt, 'i', $manager_id);
                mysqli_stmt_execute($manager_check_stmt);
                $manager_check_result = mysqli_stmt_get_result($manager_check_stmt);
                $manager_data = mysqli_fetch_assoc($manager_check_result);
                mysqli_stmt_close($manager_check_stmt);
                
                if (!$manager_data) {
                    addResult('warning', "Item $unique_id: Manager (ID: $manager_id) not found or not a manager");
                    $items_skipped++;
                    continue;
                }
                
                // Update the item with the manager ID
                $update_sql = "UPDATE client_taskflow SET assigned_to = ? WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                
                if (!$update_stmt) {
                    addResult('error', "Failed to prepare update statement for item $unique_id: " . mysqli_error($conn));
                    $items_with_errors++;
                    continue;
                }
                
                mysqli_stmt_bind_param($update_stmt, 'ii', $manager_id, $item_id);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    $items_fixed++;
                    addResult('success', "Item $unique_id ($item_type): Assigned to manager " . $manager_data['name'] . " (ID: $manager_id)");
                } else {
                    $error_msg = mysqli_error($conn);
                    addResult('error', "Failed to update item $unique_id: $error_msg");
                    $items_with_errors++;
                }
                
                mysqli_stmt_close($update_stmt);
            }
            
            mysqli_free_result($find_result);
            
            // Summary
            addResult('info', "=== SUMMARY ===");
            addResult('success', "Items fixed: $items_fixed");
            if ($items_skipped > 0) {
                addResult('warning', "Items skipped (no manager found): $items_skipped");
            }
            if ($items_with_errors > 0) {
                addResult('error', "Items with errors: $items_with_errors");
            }
            
            $fixes_applied = $items_fixed;
        }
        
        mysqli_close($conn);
        $show_results = true;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Task/Ticket assigned_to Field</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #1e1e2e;
            color: #cdd6f4;
        }
        h1 {
            color: #89b4fa;
            border-bottom: 2px solid #45475a;
            padding-bottom: 10px;
        }
        .result {
            padding: 12px;
            margin: 8px 0;
            border-radius: 6px;
            border-left: 4px solid;
        }
        .result.success {
            background: #1e3a2e;
            border-color: #a6e3a1;
            color: #a6e3a1;
        }
        .result.error {
            background: #3a1e1e;
            border-color: #f38ba8;
            color: #f38ba8;
        }
        .result.warning {
            background: #3a2e1e;
            border-color: #f9e2af;
            color: #f9e2af;
        }
        .result.info {
            background: #1e1e3a;
            border-color: #89b4fa;
            color: #89b4fa;
        }
        .summary {
            margin-top: 30px;
            padding: 20px;
            background: #313244;
            border-radius: 8px;
        }
        .count {
            font-weight: bold;
            font-size: 1.2em;
        }
    </style>
</head>
<body>
    <h1>Fix Task/Ticket assigned_to Field for Existing Data</h1>
    
    <?php if (isset($show_results) && $show_results): ?>
        <div class="summary">
            <h2>Results</h2>
            <?php foreach ($results as $result): ?>
                <div class="result <?php echo htmlspecialchars($result['type']); ?>">
                    <strong><?php echo htmlspecialchars(ucfirst($result['type'])); ?>:</strong>
                    <?php echo htmlspecialchars($result['message']); ?>
                    <?php if ($result['count'] !== null): ?>
                        <span class="count">(<?php echo htmlspecialchars($result['count']); ?>)</span>
                    <?php endif; ?>
                    <small style="float: right; opacity: 0.7;"><?php echo htmlspecialchars($result['timestamp']); ?></small>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div style="margin-top: 30px; padding: 20px; background: #313244; border-radius: 8px;">
            <h2>Total Fixes Applied: <span class="count" style="color: #a6e3a1;"><?php echo $fixes_applied; ?></span></h2>
            <p style="margin-top: 15px;">
                <a href="fix_task_ticket_assigned_to.php" style="color: #89b4fa; text-decoration: none; padding: 8px 16px; background: #45475a; border-radius: 4px; display: inline-block;">
                    Run Again
                </a>
            </p>
        </div>
    <?php else: ?>
        <div class="result info">
            <p>This script will update existing Task and Ticket items created by clients to set the <code>assigned_to</code> field to their manager's ID.</p>
            <p><strong>What it does:</strong></p>
            <ul>
                <li>Finds all Task and Ticket items where <code>assigned_to</code> is NULL and <code>created_by_type</code> is 'Client'</li>
                <li>For each item, gets the client user's manager (client account), then gets that account's manager (actual manager)</li>
                <li>Updates the <code>assigned_to</code> field with the manager's ID</li>
            </ul>
            <p style="margin-top: 20px;">
                <a href="?run=1" style="color: #89b4fa; text-decoration: none; padding: 10px 20px; background: #45475a; border-radius: 4px; display: inline-block; font-weight: bold;">
                    Run Fix Script
                </a>
            </p>
        </div>
    <?php endif; ?>
</body>
</html>
