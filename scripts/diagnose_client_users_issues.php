<?php
/**
 * Diagnostic Script for Client Users Issues
 * 
 * This script helps identify issues with client users and deleted accounts
 * Run this on your live server to see what needs to be fixed
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is admin (for security)
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    die("Access denied. Admin access required.");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Users Diagnostic Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .diagnostic-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .issue-found {
            background-color: #fff3cd;
            border-color: #ffc107;
        }
        .no-issue {
            background-color: #d1e7dd;
            border-color: #198754;
        }
        .critical-issue {
            background-color: #f8d7da;
            border-color: #dc3545;
        }
        table {
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">Client Users Diagnostic Report</h1>
        <p class="text-muted">Generated on: <?php echo date('Y-m-d H:i:s'); ?></p>

        <?php
        // Diagnostic 1: Users with NULL or empty user_type
        echo '<div class="diagnostic-section">';
        echo '<h3>1. Users with NULL or Empty user_type</h3>';
        $sql = "SELECT id, username, name, user_type, password, manager_id, Status 
                FROM users 
                WHERE user_type IS NULL OR user_type = ''";
        $result = mysqli_query($conn, $sql);
        if ($result && mysqli_num_rows($result) > 0) {
            echo '<div class="alert alert-warning">⚠️ Issue Found: ' . mysqli_num_rows($result) . ' user(s) with NULL or empty user_type</div>';
            echo '<table class="table table-sm table-bordered">';
            echo '<thead><tr><th>ID</th><th>Username</th><th>Name</th><th>user_type</th><th>Has Password</th><th>manager_id</th><th>Status</th></tr></thead><tbody>';
            while ($row = mysqli_fetch_assoc($result)) {
                $has_password = !empty($row['password']) ? 'Yes' : 'No';
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                echo '<td>' . htmlspecialchars($row['username']) . '</td>';
                echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                echo '<td>' . htmlspecialchars($row['user_type'] ?? 'NULL') . '</td>';
                echo '<td>' . $has_password . '</td>';
                echo '<td>' . htmlspecialchars($row['manager_id'] ?? 'NULL') . '</td>';
                echo '<td>' . htmlspecialchars($row['Status'] ?? 'NULL') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="alert alert-success">✓ No issues found</div>';
        }
        echo '</div>';

        // Diagnostic 2: Client users with incorrect user_type
        echo '<div class="diagnostic-section">';
        echo '<h3>2. Client Users with Incorrect user_type</h3>';
        $sql = "SELECT u.id, u.username, u.name, u.user_type, u.password, u.manager_id, u.Status,
                       c.id as client_account_id, c.username as client_account_username
                FROM users u
                LEFT JOIN users c ON u.manager_id = c.id AND c.user_type = 'client' AND (c.password IS NULL OR c.password = '')
                WHERE u.password IS NOT NULL 
                AND u.password != ''
                AND u.manager_id IN (SELECT id FROM users WHERE user_type = 'client' AND (password IS NULL OR password = ''))
                AND (u.user_type IS NULL OR u.user_type = '' OR u.user_type != 'client')";
        $result = mysqli_query($conn, $sql);
        if ($result && mysqli_num_rows($result) > 0) {
            echo '<div class="alert alert-warning">⚠️ Issue Found: ' . mysqli_num_rows($result) . ' client user(s) with incorrect user_type</div>';
            echo '<table class="table table-sm table-bordered">';
            echo '<thead><tr><th>ID</th><th>Username</th><th>Name</th><th>Current user_type</th><th>Client Account</th><th>Status</th></tr></thead><tbody>';
            while ($row = mysqli_fetch_assoc($result)) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                echo '<td>' . htmlspecialchars($row['username']) . '</td>';
                echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                echo '<td>' . htmlspecialchars($row['user_type'] ?? 'NULL') . '</td>';
                echo '<td>' . htmlspecialchars($row['client_account_username'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars($row['Status'] ?? 'NULL') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="alert alert-success">✓ No issues found</div>';
        }
        echo '</div>';

        // Diagnostic 3: Deleted/Inactive client accounts
        echo '<div class="diagnostic-section">';
        echo '<h3>3. Deleted/Inactive Client Accounts</h3>';
        $sql = "SELECT id, username, name, user_type, password, Status, created_at
                FROM users 
                WHERE user_type = 'client' 
                AND (password IS NULL OR password = '')
                AND (Status = 'Inactive' OR Status IS NULL)";
        $result = mysqli_query($conn, $sql);
        if ($result && mysqli_num_rows($result) > 0) {
            echo '<div class="alert alert-danger">🚨 Critical Issue: ' . mysqli_num_rows($result) . ' deleted/inactive client account(s) still exist</div>';
            echo '<table class="table table-sm table-bordered">';
            echo '<thead><tr><th>ID</th><th>Username</th><th>Name</th><th>Status</th><th>Created At</th></tr></thead><tbody>';
            while ($row = mysqli_fetch_assoc($result)) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                echo '<td>' . htmlspecialchars($row['username']) . '</td>';
                echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                echo '<td>' . htmlspecialchars($row['Status'] ?? 'NULL') . '</td>';
                echo '<td>' . htmlspecialchars($row['created_at'] ?? 'N/A') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="alert alert-success">✓ No issues found</div>';
        }
        echo '</div>';

        // Diagnostic 4: Client accounts with passwords (should not exist)
        echo '<div class="diagnostic-section">';
        echo '<h3>4. Client Accounts with Passwords (Should Not Exist)</h3>';
        $sql = "SELECT id, username, name, user_type, password, Status
                FROM users 
                WHERE user_type = 'client' 
                AND (password IS NULL OR password = '')
                AND password IS NOT NULL
                AND password != ''";
        $result = mysqli_query($conn, $sql);
        if ($result && mysqli_num_rows($result) > 0) {
            echo '<div class="alert alert-danger">🚨 Critical Issue: ' . mysqli_num_rows($result) . ' client account(s) have passwords</div>';
            echo '<table class="table table-sm table-bordered">';
            echo '<thead><tr><th>ID</th><th>Username</th><th>Name</th><th>Status</th></tr></thead><tbody>';
            while ($row = mysqli_fetch_assoc($result)) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                echo '<td>' . htmlspecialchars($row['username']) . '</td>';
                echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                echo '<td>' . htmlspecialchars($row['Status'] ?? 'NULL') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="alert alert-success">✓ No issues found</div>';
        }
        echo '</div>';

        // Diagnostic 5: Summary Statistics
        echo '<div class="diagnostic-section">';
        echo '<h3>5. Summary Statistics</h3>';
        
        // Total users by type
        $sql = "SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type ORDER BY user_type";
        $result = mysqli_query($conn, $sql);
        echo '<h5>Users by Type:</h5>';
        echo '<table class="table table-sm table-bordered">';
        echo '<thead><tr><th>User Type</th><th>Count</th></tr></thead><tbody>';
        while ($row = mysqli_fetch_assoc($result)) {
            echo '<tr><td>' . htmlspecialchars($row['user_type'] ?? 'NULL') . '</td><td>' . $row['count'] . '</td></tr>';
        }
        echo '</tbody></table>';

        // Client accounts count
        $sql = "SELECT COUNT(*) as count FROM users WHERE user_type = 'client' AND (password IS NULL OR password = '')";
        $result = mysqli_query($conn, $sql);
        $row = mysqli_fetch_assoc($result);
        echo '<p><strong>Client Accounts (no password):</strong> ' . $row['count'] . '</p>';

        // Client users count
        $sql = "SELECT COUNT(*) as count FROM users 
                WHERE user_type = 'client' 
                AND password IS NOT NULL 
                AND password != ''
                AND manager_id IN (SELECT id FROM users WHERE user_type = 'client' AND (password IS NULL OR password = ''))";
        $result = mysqli_query($conn, $sql);
        $row = mysqli_fetch_assoc($result);
        echo '<p><strong>Client Users (with password):</strong> ' . $row['count'] . '</p>';

        // Team users count
        $sql = "SELECT COUNT(*) as count FROM users WHERE user_type IN ('admin', 'manager', 'doer')";
        $result = mysqli_query($conn, $sql);
        $row = mysqli_fetch_assoc($result);
        echo '<p><strong>Team Users (admin/manager/doer):</strong> ' . $row['count'] . '</p>';

        echo '</div>';

        // Diagnostic 6: Users that would appear in team table but shouldn't
        echo '<div class="diagnostic-section">';
        echo '<h3>6. Users Incorrectly Appearing in Team Table</h3>';
        $sql = "SELECT u.*, d.name as department_name 
                FROM users u 
                LEFT JOIN departments d ON u.department_id = d.id 
                WHERE u.user_type = 'client'
                AND u.password IS NOT NULL 
                AND u.password != ''
                AND u.manager_id IN (SELECT id FROM users WHERE user_type = 'client' AND (password IS NULL OR password = ''))";
        $result = mysqli_query($conn, $sql);
        if ($result && mysqli_num_rows($result) > 0) {
            echo '<div class="alert alert-warning">⚠️ Issue Found: ' . mysqli_num_rows($result) . ' client user(s) that might appear in team table</div>';
            echo '<p class="text-muted">These users have user_type = "client" but the query might not be filtering them correctly.</p>';
            echo '<table class="table table-sm table-bordered">';
            echo '<thead><tr><th>ID</th><th>Username</th><th>Name</th><th>user_type</th><th>Client Account ID</th></tr></thead><tbody>';
            while ($row = mysqli_fetch_assoc($result)) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                echo '<td>' . htmlspecialchars($row['username']) . '</td>';
                echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                echo '<td>' . htmlspecialchars($row['user_type']) . '</td>';
                echo '<td>' . htmlspecialchars($row['manager_id'] ?? 'NULL') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="alert alert-success">✓ No issues found</div>';
        }
        echo '</div>';
        ?>

        <div class="alert alert-info mt-4">
            <h5>Next Steps:</h5>
            <ol>
                <li>Review the issues found above</li>
                <li>Run the SQL migration script: <code>migrations/fix_client_users_issues.sql</code></li>
                <li>Verify the fixes by running this diagnostic script again</li>
                <li>Test the application to ensure everything works correctly</li>
            </ol>
        </div>
    </div>
</body>
</html>
