<?php
/**
 * Fix Leave Status ENUM
 * Updates the Leave_request table status column to use correct ENUM values
 * 
 * Usage: 
 * - Browser: http://localhost/app-v4.7/test/fix_leave_status_enum.php
 * - CLI: php test/fix_leave_status_enum.php
 */

session_start();
require_once '../includes/config.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$is_cli = php_sapi_name() === 'cli';

function printMessage($message, $type = 'info') {
    global $is_cli;
    if ($is_cli) {
        $prefix = match($type) {
            'success' => '✓',
            'error' => '✗',
            'warning' => '⚠',
            default => 'ℹ'
        };
        echo "[$prefix] $message\n";
    } else {
        $color = match($type) {
            'success' => 'green',
            'error' => 'red',
            'warning' => 'orange',
            default => 'blue'
        };
        echo "<div style='color: $color; margin: 5px 0; padding: 5px; border-left: 3px solid $color; padding-left: 10px;'>$message</div>";
    }
}

if (!$is_cli) {
    echo "<!DOCTYPE html><html><head><title>Fix Leave Status ENUM</title>";
    echo "<style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #007bff; }
        .warning { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107; }
        .success { background: #d1e7dd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; }
    </style></head><body><div class='container'>";
    echo "<h1>🔧 Fix Leave Status ENUM</h1>";
}

if (!$conn) {
    printMessage("✗ Database connection failed", 'error');
    exit;
}

printMessage("✓ Database connected", 'success');

// Step 1: Check current ENUM definition
printMessage("Step 1: Checking current status column definition...", 'info');

try {
    $check_query = "SHOW COLUMNS FROM Leave_request WHERE Field = 'status'";
    $result = mysqli_query($conn, $check_query);
    
    if (!$result || mysqli_num_rows($result) == 0) {
        printMessage("✗ Status column not found!", 'error');
        exit;
    }
    
    $row = mysqli_fetch_assoc($result);
    $current_type = $row['Type'];
    $current_default = $row['Default'];
    
    printMessage("Current ENUM definition: <code>$current_type</code>", 'info');
    printMessage("Current default value: <code>" . ($current_default ?? 'NULL') . "</code>", 'info');
    
    // Check if it already has PENDING
    $needs_fix = true;
    if (strpos($current_type, "'PENDING'") !== false || strpos($current_type, "PENDING") !== false) {
        printMessage("✓ ENUM already contains 'PENDING'", 'success');
        $needs_fix = false;
    } else {
        printMessage("⚠ ENUM does not contain 'PENDING' - needs to be fixed", 'warning');
    }
    
    // Step 2: Check for data that needs migration
    if ($needs_fix) {
        printMessage("\nStep 2: Checking for data that needs migration...", 'info');
        
        // Count records with old 'Pending' status
        $old_pending_query = "SELECT COUNT(*) as count FROM Leave_request WHERE status = 'Pending'";
        $result = mysqli_query($conn, $old_pending_query);
        $row = mysqli_fetch_assoc($result);
        $old_pending_count = (int)$row['count'];
        
        // Count records with empty/null status
        $null_status_query = "SELECT COUNT(*) as count FROM Leave_request WHERE status IS NULL OR status = ''";
        $result = mysqli_query($conn, $null_status_query);
        $row = mysqli_fetch_assoc($result);
        $null_status_count = (int)$row['count'];
        
        printMessage("Records with 'Pending' status: $old_pending_count", 'info');
        printMessage("Records with NULL/empty status: $null_status_count", 'info');
        
        if ($old_pending_count > 0 || $null_status_count > 0) {
            printMessage("⚠ Found records that need status migration", 'warning');
        }
        
        // Step 3: Perform the fix
        printMessage("\nStep 3: Updating ENUM definition...", 'info');
        
        if (!$is_cli) {
            echo "<div class='warning'>";
            echo "<strong>⚠ Warning:</strong> This will modify the table structure. Make sure you have a backup!<br>";
            echo "The following changes will be made:<br>";
            echo "<ul>";
            echo "<li>Update status ENUM to: <code>ENUM('PENDING','Approve','Reject','Cancelled')</code></li>";
            echo "<li>Set default to: <code>'PENDING'</code></li>";
            if ($old_pending_count > 0) {
                echo "<li>Migrate $old_pending_count records from 'Pending' to 'PENDING'</li>";
            }
            if ($null_status_count > 0) {
                echo "<li>Set $null_status_count NULL/empty statuses to 'PENDING'</li>";
            }
            echo "</ul>";
            echo "</div>";
        }
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // First, update any 'Pending' values to 'PENDING' (if they exist)
            if ($old_pending_count > 0) {
                printMessage("Migrating 'Pending' to 'PENDING'...", 'info');
                // We need to temporarily allow invalid values, then update
                $update_query = "UPDATE Leave_request SET status = 'PENDING' WHERE status = 'Pending'";
                mysqli_query($conn, $update_query);
                printMessage("✓ Migrated $old_pending_count records", 'success');
            }
            
            // Update NULL/empty statuses to 'PENDING'
            if ($null_status_count > 0) {
                printMessage("Setting NULL/empty statuses to 'PENDING'...", 'info');
                $update_null_query = "UPDATE Leave_request SET status = 'PENDING' WHERE status IS NULL OR status = ''";
                mysqli_query($conn, $update_null_query);
                printMessage("✓ Updated $null_status_count records", 'success');
            }
            
            // Now alter the column to the correct ENUM
            printMessage("Altering status column ENUM...", 'info');
            $alter_query = "ALTER TABLE Leave_request 
                           MODIFY COLUMN status ENUM('PENDING','Approve','Reject','Cancelled') DEFAULT 'PENDING'";
            
            if (mysqli_query($conn, $alter_query)) {
                printMessage("✓ Status column ENUM updated successfully", 'success');
            } else {
                throw new Exception("Failed to alter column: " . mysqli_error($conn));
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            // Verify the change
            printMessage("\nStep 4: Verifying the fix...", 'info');
            $verify_query = "SHOW COLUMNS FROM Leave_request WHERE Field = 'status'";
            $verify_result = mysqli_query($conn, $verify_query);
            $verify_row = mysqli_fetch_assoc($verify_result);
            $new_type = $verify_row['Type'];
            $new_default = $verify_row['Default'];
            
            printMessage("New ENUM definition: <code>$new_type</code>", 'info');
            printMessage("New default value: <code>" . ($new_default ?? 'NULL') . "</code>", 'info');
            
            if (strpos($new_type, "'PENDING'") !== false) {
                printMessage("✓ Fix successful! ENUM now contains 'PENDING'", 'success');
                
                // Count final status distribution
                printMessage("\nFinal Status Distribution:", 'info');
                $status_query = "SELECT status, COUNT(*) as count FROM Leave_request GROUP BY status ORDER BY count DESC";
                $status_result = mysqli_query($conn, $status_query);
                
                if (!$is_cli) {
                    echo "<table style='width:100%; border-collapse: collapse; margin: 10px 0;'>";
                    echo "<tr style='background: #007bff; color: white;'><th style='padding: 8px; border: 1px solid #ddd;'>Status</th><th style='padding: 8px; border: 1px solid #ddd;'>Count</th></tr>";
                } else {
                    echo "\n";
                }
                
                while ($status_row = mysqli_fetch_assoc($status_result)) {
                    $status = $status_row['status'] ?? 'NULL';
                    $count = $status_row['count'];
                    if (!$is_cli) {
                        echo "<tr><td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($status) . "</td><td style='padding: 8px; border: 1px solid #ddd;'>$count</td></tr>";
                    } else {
                        echo "  $status: $count\n";
                    }
                }
                
                if (!$is_cli) {
                    echo "</table>";
                }
                
                if (!$is_cli) {
                    echo "<div class='success'>";
                    echo "<strong>✓ Success!</strong> The Leave_request table status ENUM has been fixed.<br>";
                    echo "You can now run the visibility test again to verify everything is working.";
                    echo "</div>";
                }
                
            } else {
                throw new Exception("ENUM still does not contain 'PENDING' after update");
            }
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            printMessage("✗ Error during fix: " . $e->getMessage(), 'error');
            printMessage("Transaction rolled back. No changes were made.", 'warning');
        }
        
    } else {
        printMessage("\n✓ No fix needed - ENUM is already correct", 'success');
    }
    
} catch (Exception $e) {
    printMessage("✗ Error: " . $e->getMessage(), 'error');
}

mysqli_close($conn);

if (!$is_cli) {
    echo "</div></body></html>";
} else {
    echo "\nFix completed at " . date('Y-m-d H:i:s') . "\n";
}
?>

