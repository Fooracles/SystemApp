<?php
/**
 * Test Pending Leave Requests Visibility
 * Quick test to verify pending requests are visible
 */

session_start();
require_once '../includes/config.php';

$is_cli = php_sapi_name() === 'cli';

function printMsg($msg, $type = 'info') {
    global $is_cli;
    if ($is_cli) {
        echo "[$type] $msg\n";
    } else {
        $color = ['success' => 'green', 'error' => 'red', 'warning' => 'orange', 'info' => 'blue'][$type] ?? 'black';
        echo "<div style='color: $color; padding: 5px;'>$msg</div>";
    }
}

if (!$is_cli) {
    echo "<!DOCTYPE html><html><head><title>Pending Visibility Test</title></head><body>";
    echo "<h1>Pending Leave Requests Visibility Test</h1>";
}

// Test 1: Check database connection
if (!$conn) {
    printMsg("✗ Database connection failed", 'error');
    exit;
}
printMsg("✓ Database connected", 'success');

// Test 2: Count total pending records
$pending_query = "SELECT COUNT(*) as count FROM Leave_request WHERE (status = 'PENDING' OR status = '' OR status IS NULL)";
$result = mysqli_query($conn, $pending_query);
$row = mysqli_fetch_assoc($result);
$pending_count = (int)$row['count'];

printMsg("Total pending records in database: <strong>$pending_count</strong>", 'info');

// Test 3: Test the exact query used by leave_fetch_pending.php
printMsg("\nTesting leave_fetch_pending.php query logic...", 'info');

$where_conditions = ["(status = 'PENDING' OR status = '' OR status IS NULL)"];
$where_params = [];
$where_types = '';

$sql_where = " WHERE " . implode(" AND ", $where_conditions);
$sql_count = "SELECT COUNT(*) as total_count FROM Leave_request" . $sql_where;

$stmt = mysqli_prepare($conn, $sql_count);
if ($stmt) {
    if (!empty($where_types) && !empty($where_params)) {
        mysqli_stmt_bind_param($stmt, $where_types, ...$where_params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $count = (int)$row['total_count'];
    mysqli_stmt_close($stmt);
    
    printMsg("Query result: $count pending records", $count > 0 ? 'success' : 'warning');
}

// Test 4: Fetch sample records
printMsg("\nFetching sample pending records...", 'info');

$sample_query = "SELECT unique_service_no, employee_name, status, leave_type, start_date 
                 FROM Leave_request 
                 WHERE (status = 'PENDING' OR status = '' OR status IS NULL)
                 LIMIT 10";
$result = mysqli_query($conn, $sample_query);

if ($result && mysqli_num_rows($result) > 0) {
    printMsg("✓ Sample records found:", 'success');
    
    if (!$is_cli) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Service No</th><th>Employee</th><th>Status</th><th>Leave Type</th><th>Start Date</th></tr>";
    } else {
        echo "\n";
    }
    
    while ($row = mysqli_fetch_assoc($result)) {
        if (!$is_cli) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['unique_service_no']) . "</td>";
            echo "<td>" . htmlspecialchars($row['employee_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['status'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['leave_type'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($row['start_date'] ?? 'N/A') . "</td>";
            echo "</tr>";
        } else {
            echo sprintf("%-20s %-30s %-10s %-15s %s\n",
                $row['unique_service_no'],
                $row['employee_name'],
                $row['status'] ?? 'NULL',
                $row['leave_type'] ?? 'N/A',
                $row['start_date'] ?? 'N/A'
            );
        }
    }
    
    if (!$is_cli) {
        echo "</table>";
    }
    
    printMsg("✓ Pending requests are queryable and should be visible in the table", 'success');
} else {
    printMsg("⚠ No pending records found in database", 'warning');
    printMsg("   → Make sure you have synced data from Google Sheets", 'warning');
    printMsg("   → Check that some records have status = 'PENDING' or NULL", 'warning');
}

// Test 5: Check status values distribution
printMsg("\nStatus values distribution:", 'info');
$status_query = "SELECT status, COUNT(*) as count FROM Leave_request GROUP BY status ORDER BY count DESC";
$result = mysqli_query($conn, $status_query);

if (!$is_cli) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Status</th><th>Count</th><th>Will Appear In</th></tr>";
} else {
    echo "\n";
}

while ($row = mysqli_fetch_assoc($result)) {
    $status = $row['status'] ?? 'NULL';
    $count = $row['count'];
    $appears_in = '';
    
    if ($status === 'PENDING' || $status === '' || $status === null) {
        $appears_in = 'Pending Table ✓';
    } elseif (in_array($status, ['Approve', 'Reject', 'Cancelled'])) {
        $appears_in = 'Total Table ✓';
    } else {
        $appears_in = 'Unknown ⚠';
    }
    
    if (!$is_cli) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($status) . "</td>";
        echo "<td>$count</td>";
        echo "<td>$appears_in</td>";
        echo "</tr>";
    } else {
        echo sprintf("%-15s %-10s %s\n", $status, $count, $appears_in);
    }
}

if (!$is_cli) {
    echo "</table>";
}

printMsg("\n✓ Test completed", 'success');
printMsg("If pending_count > 0, pending requests should be visible in the table", 'info');

mysqli_close($conn);

if (!$is_cli) {
    echo "</body></html>";
}
?>

