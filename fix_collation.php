<?php
/**
 * Collation Fix Script
 * 
 * This script checks and fixes collation mismatches in the database
 * that cause "Illegal mix of collations" errors.
 * 
 * Usage:
 * 1. Run via browser: http://yourdomain.com/fix_collation.php
 * 2. Run via command line: php fix_collation.php
 * 
 * Options:
 * - ?check=1 : Only check, don't fix
 * - ?fix=1 : Fix all collation issues automatically
 */

// Include database configuration
require_once __DIR__ . '/includes/config.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if running from CLI or web
$is_cli = php_sapi_name() === 'cli';

// Get action parameter
$action = isset($_GET['action']) ? $_GET['action'] : ($is_cli ? 'check' : 'check');
$auto_fix = isset($_GET['fix']) && $_GET['fix'] == '1';

// Output function
function output($message, $type = 'info') {
    global $is_cli;
    
    if ($is_cli) {
        $prefix = '';
        switch ($type) {
            case 'error': $prefix = '[ERROR] '; break;
            case 'success': $prefix = '[SUCCESS] '; break;
            case 'warning': $prefix = '[WARNING] '; break;
            default: $prefix = '[INFO] ';
        }
        echo $prefix . $message . "\n";
    } else {
        $color = '';
        switch ($type) {
            case 'error': $color = 'color: red;'; break;
            case 'success': $color = 'color: green;'; break;
            case 'warning': $color = 'color: orange;'; break;
            default: $color = 'color: blue;';
        }
        echo "<div style='$color margin: 5px 0;'>$message</div>";
    }
}

// HTML header for web output
if (!$is_cli) {
    echo "<!DOCTYPE html>
<html>
<head>
    <title>Collation Fix Script</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #4CAF50; color: white; }
        tr:hover { background: #f5f5f5; }
        .btn { display: inline-block; padding: 10px 20px; margin: 5px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; }
        .btn:hover { background: #45a049; }
        .btn-danger { background: #f44336; }
        .btn-danger:hover { background: #da190b; }
        .status-ok { color: green; font-weight: bold; }
        .status-fix { color: orange; font-weight: bold; }
        .status-error { color: red; font-weight: bold; }
    </style>
</head>
<body>
<div class='container'>";
    echo "<h1>Database Collation Fix Script</h1>";
    echo "<p><a href='?action=check' class='btn'>Check Collations</a> <a href='?action=fix' class='btn btn-danger'>Fix All Collations</a></p>";
}

// Check database connection
if (!$conn) {
    output("ERROR: Database connection failed: " . mysqli_connect_error(), 'error');
    if (!$is_cli) echo "</div></body></html>";
    exit(1);
}

output("Database connected successfully", 'success');

// Target collation (we'll use utf8mb4_unicode_ci as it's more accurate)
$target_collation = 'utf8mb4_unicode_ci';

// Tables and columns that need to be checked
$tables_to_check = [
    'users' => ['name', 'username'],
    'Leave_request' => ['employee_name'],
    'tasks' => ['status'],
    'fms_tasks' => ['doer_name', 'status'],
    'checklist_subtasks' => ['assignee', 'status']
];

// Function to get column collation
function getColumnCollation($conn, $table, $column) {
    $query = "SELECT COLLATION_NAME 
              FROM INFORMATION_SCHEMA.COLUMNS 
              WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = ? 
              AND COLUMN_NAME = ?";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, "ss", $table, $column);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['COLLATION_NAME'];
        }
        mysqli_stmt_close($stmt);
    }
    return null;
}

// Function to fix column collation
function fixColumnCollation($conn, $table, $column, $target_collation) {
    // Get column type first
    $query = "SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
              FROM INFORMATION_SCHEMA.COLUMNS 
              WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = ? 
              AND COLUMN_NAME = ?";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, "ss", $table, $column);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $column_type = $row['COLUMN_TYPE'];
            $is_nullable = $row['IS_NULLABLE'] === 'YES' ? 'NULL' : 'NOT NULL';
            $default = $row['COLUMN_DEFAULT'] !== null ? "DEFAULT '" . mysqli_real_escape_string($conn, $row['COLUMN_DEFAULT']) . "'" : '';
            $extra = $row['EXTRA'];
            
            // Build ALTER TABLE statement
            $alter_query = "ALTER TABLE `$table` MODIFY COLUMN `$column` $column_type COLLATE $target_collation $is_nullable $default $extra";
            
            if (mysqli_query($conn, $alter_query)) {
                return true;
            } else {
                return mysqli_error($conn);
            }
        }
        mysqli_stmt_close($stmt);
    }
    return "Could not retrieve column information";
}

// Check collations
output("Checking collations...", 'info');

$issues = [];
$checked = [];

if (!$is_cli) {
    echo "<div class='section'>";
    echo "<h2>Collation Check Results</h2>";
    echo "<table>";
    echo "<tr><th>Table</th><th>Column</th><th>Current Collation</th><th>Target Collation</th><th>Status</th></tr>";
}

foreach ($tables_to_check as $table => $columns) {
    // Check if table exists
    $table_check = "SHOW TABLES LIKE '$table'";
    $table_result = mysqli_query($conn, $table_check);
    
    if (mysqli_num_rows($table_result) == 0) {
        output("Table '$table' does not exist, skipping...", 'warning');
        continue;
    }
    
    foreach ($columns as $column) {
        $current_collation = getColumnCollation($conn, $table, $column);
        
        if ($current_collation === null) {
            output("Column '$table.$column' does not exist, skipping...", 'warning');
            continue;
        }
        
        $checked[] = [
            'table' => $table,
            'column' => $column,
            'current' => $current_collation,
            'target' => $target_collation
        ];
        
        if ($current_collation !== $target_collation) {
            $issues[] = [
                'table' => $table,
                'column' => $column,
                'current' => $current_collation,
                'target' => $target_collation
            ];
            
            if ($is_cli) {
                output("ISSUE: $table.$column - Current: $current_collation, Target: $target_collation", 'error');
            } else {
                $status_class = 'status-fix';
                echo "<tr>";
                echo "<td>$table</td>";
                echo "<td>$column</td>";
                echo "<td>$current_collation</td>";
                echo "<td>$target_collation</td>";
                echo "<td class='$status_class'>Needs Fix</td>";
                echo "</tr>";
            }
        } else {
            if ($is_cli) {
                output("OK: $table.$column - Collation: $current_collation", 'success');
            } else {
                $status_class = 'status-ok';
                echo "<tr>";
                echo "<td>$table</td>";
                echo "<td>$column</td>";
                echo "<td>$current_collation</td>";
                echo "<td>$target_collation</td>";
                echo "<td class='$status_class'>OK</td>";
                echo "</tr>";
            }
        }
    }
}

if (!$is_cli) {
    echo "</table>";
    echo "</div>";
}

// Summary
output("", 'info');
output("=== SUMMARY ===", 'info');
output("Total columns checked: " . count($checked), 'info');
output("Issues found: " . count($issues), count($issues) > 0 ? 'error' : 'success');

// Fix issues if requested
if ($action === 'fix' || $auto_fix) {
    if (count($issues) > 0) {
        output("", 'info');
        output("Fixing collation issues...", 'info');
        
        if (!$is_cli) {
            echo "<div class='section'>";
            echo "<h2>Fix Results</h2>";
            echo "<table>";
            echo "<tr><th>Table</th><th>Column</th><th>Status</th><th>Message</th></tr>";
        }
        
        $fixed = 0;
        $failed = 0;
        
        foreach ($issues as $issue) {
            $result = fixColumnCollation($conn, $issue['table'], $issue['column'], $issue['target']);
            
            if ($result === true) {
                $fixed++;
                if ($is_cli) {
                    output("FIXED: {$issue['table']}.{$issue['column']}", 'success');
                } else {
                    echo "<tr>";
                    echo "<td>{$issue['table']}</td>";
                    echo "<td>{$issue['column']}</td>";
                    echo "<td class='status-ok'>Fixed</td>";
                    echo "<td>Collation changed to {$issue['target']}</td>";
                    echo "</tr>";
                }
            } else {
                $failed++;
                if ($is_cli) {
                    output("FAILED: {$issue['table']}.{$issue['column']} - $result", 'error');
                } else {
                    echo "<tr>";
                    echo "<td>{$issue['table']}</td>";
                    echo "<td>{$issue['column']}</td>";
                    echo "<td class='status-error'>Failed</td>";
                    echo "<td>$result</td>";
                    echo "</tr>";
                }
            }
        }
        
        if (!$is_cli) {
            echo "</table>";
            echo "</div>";
        }
        
        output("", 'info');
        output("=== FIX SUMMARY ===", 'info');
        output("Fixed: $fixed", 'success');
        output("Failed: $failed", $failed > 0 ? 'error' : 'success');
        
        if ($fixed > 0) {
            output("", 'info');
            output("Collation fixes applied successfully! The error should be resolved.", 'success');
        }
    } else {
        output("No issues to fix. All collations are correct!", 'success');
    }
} else {
    if (count($issues) > 0) {
        output("", 'info');
        output("To fix these issues, run: ?action=fix", 'warning');
        if ($is_cli) {
            output("Or run: php fix_collation.php (with fix parameter)", 'warning');
        }
    }
}

// Close connection
mysqli_close($conn);

if (!$is_cli) {
    echo "</div></body></html>";
}

