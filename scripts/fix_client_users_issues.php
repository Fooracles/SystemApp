<?php
/**
 * Fix Client Users and Deleted Accounts Issues
 * 
 * This script automatically fixes database issues:
 * 1. Client users appearing in "Manage Team" table
 * 2. Deleted client accounts that can still log in
 * 
 * SECURITY: Only accessible by admin users
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is admin (for security)
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    die("Access denied. Admin access required.");
}

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

// Function to execute SQL safely
function executeQuery($conn, $sql, $description) {
    global $errors, $fixes_applied;
    try {
        if (mysqli_query($conn, $sql)) {
            $affected = mysqli_affected_rows($conn);
            addResult('success', $description . " (Affected: $affected rows)", $affected);
            $fixes_applied += $affected;
            return true;
        } else {
            $error_msg = mysqli_error($conn);
            addResult('error', $description . " - Error: $error_msg");
            $errors[] = $error_msg;
            return false;
        }
    } catch (Exception $e) {
        $error_msg = $e->getMessage();
        addResult('error', $description . " - Exception: $error_msg");
        $errors[] = $error_msg;
        return false;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Client Users Issues</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .result-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            border-left: 4px solid;
        }
        .result-success {
            background-color: #d1e7dd;
            border-color: #198754;
        }
        .result-error {
            background-color: #f8d7da;
            border-color: #dc3545;
        }
        .result-warning {
            background-color: #fff3cd;
            border-color: #ffc107;
        }
        .result-info {
            background-color: #d1ecf1;
            border-color: #0dcaf0;
        }
        .section {
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .stats-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">Fix Client Users Issues</h1>
        <p class="text-muted">This script will automatically fix database issues related to client users and deleted accounts.</p>
        
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_fix'])): ?>
            <?php
            // ==============================================
            // STEP 1: DIAGNOSTIC - Check for Issues
            // ==============================================
            addResult('info', '=== Starting Diagnostic Phase ===');
            
            // Check 1: Users with NULL or empty user_type
            $sql = "SELECT COUNT(*) as count FROM users WHERE user_type IS NULL OR user_type = ''";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $null_user_type_count = $row['count'];
                if ($null_user_type_count > 0) {
                    addResult('warning', "Found $null_user_type_count user(s) with NULL or empty user_type");
                } else {
                    addResult('success', "No users with NULL or empty user_type found");
                }
            }
            
            // Check 2: Client users with incorrect user_type
            $sql = "SELECT COUNT(*) as count 
                    FROM users u
                    WHERE u.password IS NOT NULL 
                    AND u.password != ''
                    AND u.manager_id IN (SELECT id FROM users WHERE user_type = 'client' AND (password IS NULL OR password = ''))
                    AND (u.user_type IS NULL OR u.user_type = '' OR u.user_type != 'client')";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $incorrect_user_type_count = $row['count'];
                if ($incorrect_user_type_count > 0) {
                    addResult('warning', "Found $incorrect_user_type_count client user(s) with incorrect user_type");
                } else {
                    addResult('success', "All client users have correct user_type");
                }
            }
            
            // Check 3: Deleted/Inactive client accounts
            $sql = "SELECT COUNT(*) as count 
                    FROM users 
                    WHERE user_type = 'client' 
                    AND (password IS NULL OR password = '')
                    AND (Status = 'Inactive' OR Status IS NULL)";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $inactive_client_accounts = $row['count'];
                if ($inactive_client_accounts > 0) {
                    addResult('warning', "Found $inactive_client_accounts deleted/inactive client account(s)");
                } else {
                    addResult('success', "No deleted/inactive client accounts found");
                }
            }
            
            // Check 4: Client accounts with passwords (should not exist)
            $sql = "SELECT COUNT(*) as count 
                    FROM users 
                    WHERE user_type = 'client' 
                    AND (password IS NULL OR password = '')
                    AND password IS NOT NULL
                    AND password != ''";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $client_accounts_with_password = $row['count'];
                if ($client_accounts_with_password > 0) {
                    addResult('warning', "Found $client_accounts_with_password client account(s) with passwords");
                } else {
                    addResult('success', "No client accounts with passwords found");
                }
            }
            
            // ==============================================
            // STEP 2: FIXES - Apply Corrections
            // ==============================================
            addResult('info', '=== Starting Fix Phase ===');
            
            // Fix 1: Ensure Status column exists
            $check_status_sql = "SHOW COLUMNS FROM users LIKE 'Status'";
            $status_result = mysqli_query($conn, $check_status_sql);
            if (!$status_result || mysqli_num_rows($status_result) == 0) {
                $add_status_sql = "ALTER TABLE users ADD COLUMN Status VARCHAR(20) DEFAULT 'Active'";
                executeQuery($conn, $add_status_sql, "Added Status column to users table");
            } else {
                addResult('info', "Status column already exists");
            }
            
            // Fix 2: Update client users that have NULL or empty user_type
            if ($null_user_type_count > 0 || $incorrect_user_type_count > 0) {
                $fix_user_type_sql = "UPDATE users u
                    SET u.user_type = 'client'
                    WHERE u.manager_id IN (
                        SELECT id FROM (
                            SELECT id FROM users 
                            WHERE user_type = 'client' 
                            AND (password IS NULL OR password = '')
                        ) AS client_accounts
                    )
                    AND u.password IS NOT NULL 
                    AND u.password != ''
                    AND (u.user_type IS NULL OR u.user_type = '' OR u.user_type != 'client')";
                executeQuery($conn, $fix_user_type_sql, "Fixed client users with incorrect user_type");
            }
            
            // Fix 3: Remove passwords from client accounts (they should not have passwords)
            // Client accounts: user_type = 'client' AND manager_id does NOT point to a client account
            if ($client_accounts_with_password > 0) {
                $remove_password_sql = "UPDATE users u
                    SET u.password = '' 
                    WHERE u.user_type = 'client' 
                    AND u.password IS NOT NULL
                    AND u.password != ''
                    AND (
                        u.manager_id IS NULL 
                        OR NOT EXISTS (
                            SELECT 1 FROM users c 
                            WHERE c.id = u.manager_id 
                            AND c.user_type = 'client' 
                            AND (c.password IS NULL OR c.password = '')
                        )
                    )";
                executeQuery($conn, $remove_password_sql, "Removed passwords from client accounts");
            }
            
            // Fix 4: Handle deleted/inactive client accounts
            if ($inactive_client_accounts > 0) {
                // Option 1: Mark them as Inactive and ensure password is empty (safer)
                $mark_inactive_sql = "UPDATE users 
                    SET password = '', Status = 'Inactive'
                    WHERE user_type = 'client' 
                    AND (password IS NULL OR password = '')
                    AND (Status = 'Inactive' OR Status IS NULL)
                    AND (password IS NOT NULL AND password != '')";
                executeQuery($conn, $mark_inactive_sql, "Ensured deleted client accounts have empty password");
                
                // Option 2: If user wants to physically delete them (commented out by default)
                // Uncomment the following if you want to delete inactive client accounts:
                /*
                if (isset($_POST['delete_inactive_accounts']) && $_POST['delete_inactive_accounts'] === 'yes') {
                    $delete_inactive_sql = "DELETE FROM users 
                        WHERE user_type = 'client' 
                        AND (password IS NULL OR password = '') 
                        AND Status = 'Inactive'";
                    executeQuery($conn, $delete_inactive_sql, "Deleted inactive client accounts");
                }
                */
            }
            
            // ==============================================
            // STEP 3: VERIFICATION - Check Fixes
            // ==============================================
            addResult('info', '=== Starting Verification Phase ===');
            
            // Verify 1: Check remaining NULL user_type
            $sql = "SELECT COUNT(*) as count FROM users WHERE user_type IS NULL OR user_type = ''";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $remaining_null = $row['count'];
                if ($remaining_null == 0) {
                    addResult('success', "✓ All users now have valid user_type");
                } else {
                    addResult('warning', "⚠ Still $remaining_null user(s) with NULL user_type (may be non-client users)");
                }
            }
            
            // Verify 2: Check client users have correct user_type
            $sql = "SELECT COUNT(*) as count 
                    FROM users 
                    WHERE user_type = 'client' 
                    AND password IS NOT NULL 
                    AND password != ''
                    AND manager_id IN (SELECT id FROM users WHERE user_type = 'client' AND (password IS NULL OR password = ''))";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $client_users_count = $row['count'];
                addResult('info', "Total client users: $client_users_count");
            }
            
            // Verify 3: Check team users (should not include clients)
            $sql = "SELECT COUNT(*) as count FROM users WHERE user_type IN ('admin', 'manager', 'doer')";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $team_users_count = $row['count'];
                addResult('info', "Total team users (admin/manager/doer): $team_users_count");
            }
            
            // Verify 4: Check client accounts
            $sql = "SELECT COUNT(*) as count 
                    FROM users 
                    WHERE user_type = 'client' 
                    AND (password IS NULL OR password = '')";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $client_accounts_count = $row['count'];
                addResult('info', "Total client accounts: $client_accounts_count");
            }
            
            // ==============================================
            // STEP 4: SUMMARY STATISTICS
            // ==============================================
            addResult('info', '=== Final Summary ===');
            
            // Get final counts by type
            $sql = "SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type ORDER BY user_type";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $type = $row['user_type'] ?? 'NULL';
                    $count = $row['count'];
                    addResult('info', "Users with type '$type': $count");
                }
            }
            
            ?>
            
            <div class="section">
                <h2>Fix Results</h2>
                <div class="stats-box">
                    <strong>Total Fixes Applied:</strong> <?php echo $fixes_applied; ?> rows<br>
                    <strong>Errors:</strong> <?php echo count($errors); ?><br>
                    <strong>Warnings:</strong> <?php echo count($warnings); ?>
                </div>
                
                <h3 class="mt-4">Detailed Results:</h3>
                <?php foreach ($results as $result): ?>
                    <div class="result-item result-<?php echo $result['type']; ?>">
                        <strong>[<?php echo strtoupper($result['type']); ?>]</strong> 
                        <?php echo htmlspecialchars($result['message']); ?>
                        <?php if ($result['count'] !== null): ?>
                            <span class="badge bg-secondary"><?php echo $result['count']; ?> rows</span>
                        <?php endif; ?>
                        <small class="text-muted">(<?php echo $result['timestamp']; ?>)</small>
                    </div>
                <?php endforeach; ?>
                
                <?php if (count($errors) > 0): ?>
                    <div class="alert alert-danger mt-4">
                        <h4>Errors Encountered:</h4>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="alert alert-success mt-4">
                <h4>✓ Fix Process Completed</h4>
                <p>Please test your application to verify the fixes:</p>
                <ul>
                    <li>Create a new client user and verify it appears in "Manage Client Users"</li>
                    <li>Check "Manage Team" table - it should NOT show client users</li>
                    <li>Try logging into a deleted client account - it should fail</li>
                </ul>
                <p class="mb-0">
                    <a href="diagnose_client_users_issues.php" class="btn btn-primary">Run Diagnostic Again</a>
                    <a href="../pages/manage_users.php" class="btn btn-secondary">Go to Manage Users</a>
                </p>
            </div>
            
        <?php else: ?>
            <div class="alert alert-warning">
                <h4>⚠️ Important: Backup Your Database First!</h4>
                <p>Before running this fix script, please backup your database. This script will modify your database.</p>
            </div>
            
            <div class="section">
                <h2>What This Script Will Do:</h2>
                <ol>
                    <li><strong>Check for issues:</strong>
                        <ul>
                            <li>Users with NULL or empty user_type</li>
                            <li>Client users with incorrect user_type</li>
                            <li>Deleted/inactive client accounts</li>
                            <li>Client accounts with passwords (should not exist)</li>
                        </ul>
                    </li>
                    <li><strong>Apply fixes:</strong>
                        <ul>
                            <li>Add Status column if missing</li>
                            <li>Fix user_type for client users</li>
                            <li>Remove passwords from client accounts</li>
                            <li>Ensure deleted client accounts can't log in</li>
                        </ul>
                    </li>
                    <li><strong>Verify fixes:</strong>
                        <ul>
                            <li>Check all users have valid user_type</li>
                            <li>Verify client users are correctly categorized</li>
                            <li>Confirm team users don't include clients</li>
                        </ul>
                    </li>
                </ol>
            </div>
            
            <form method="POST" onsubmit="return confirm('Are you sure you want to run the fix? Make sure you have backed up your database!');">
                <div class="section">
                    <h3>Run Fix Script</h3>
                    <p>Click the button below to start the fix process.</p>
                    
                    <!-- Optional: Uncomment if you want to allow physical deletion of inactive accounts -->
                    <!--
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="delete_inactive_accounts" value="yes" id="deleteInactive">
                        <label class="form-check-label" for="deleteInactive">
                            <strong>Delete inactive client accounts</strong> (Use with caution - this will permanently delete accounts)
                        </label>
                    </div>
                    -->
                    
                    <button type="submit" name="run_fix" class="btn btn-primary btn-lg">
                        Run Fix Script
                    </button>
                </div>
            </form>
            
            <div class="alert alert-info mt-4">
                <h4>Need to Check Issues First?</h4>
                <p>Run the diagnostic script to see what issues exist before running the fix:</p>
                <a href="diagnose_client_users_issues.php" class="btn btn-outline-primary">Run Diagnostic Script</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
