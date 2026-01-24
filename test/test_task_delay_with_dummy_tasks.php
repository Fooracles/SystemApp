<?php
/**
 * Test Task Delay Notifications with Dummy Tasks
 * 
 * This file creates dummy tasks and tests if delay notifications are created correctly.
 * 
 * Usage:
 * Access via browser: http://your-domain/test/test_task_delay_with_dummy_tasks.php
 */

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notification_triggers.php';

// Force MySQL timezone
if (isset($conn) && $conn) {
    mysqli_query($conn, "SET time_zone = '+05:30'");
} else {
    die("ERROR: Database connection not available");
}

// Set output for browser
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Task Delay Notifications - Dummy Tasks</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            background: #1e1e1e;
            color: #d4d4d4;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #4ec9b0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        h2 {
            color: #569cd6;
            margin-top: 30px;
        }
        .success {
            color: #4ec9b0;
            background: rgba(78, 201, 176, 0.1);
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            color: #f48771;
            background: rgba(244, 135, 113, 0.1);
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .warning {
            color: #dcdcaa;
            background: rgba(220, 220, 170, 0.1);
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            color: #569cd6;
            background: rgba(86, 156, 214, 0.1);
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .section {
            margin: 20px 0;
            padding: 15px;
            background: #252526;
            border-left: 3px solid #007acc;
            border-radius: 5px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin: 15px 0;
            background: #1e1e1e;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #3e3e42;
        }
        th {
            background: #2d2d30;
            color: #4ec9b0;
            font-weight: 600;
        }
        tr:nth-child(even) {
            background: #252526;
        }
        tr:hover {
            background: #2d2d30;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px 5px;
            background: #007acc;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn:hover {
            background: #005a9e;
        }
        .btn-danger {
            background: #d32f2f;
        }
        .btn-danger:hover {
            background: #b71c1c;
        }
        .btn-success {
            background: #4caf50;
        }
        .btn-success:hover {
            background: #388e3c;
        }
        .form-group {
            margin: 15px 0;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #569cd6;
        }
        .form-group input, .form-group select {
            width: 100%;
            max-width: 300px;
            padding: 8px;
            background: #2a2a2a;
            color: #d4d4d4;
            border: 1px solid #3e3e42;
            border-radius: 4px;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success {
            background: #4caf50;
            color: white;
        }
        .badge-error {
            background: #d32f2f;
            color: white;
        }
        .badge-warning {
            background: #ff9800;
            color: white;
        }
        .badge-info {
            background: #2196f3;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test Task Delay Notifications with Dummy Tasks</h1>

        <?php
        $action = $_GET['action'] ?? '';
        $message = '';
        $error = '';

        // Get current time
        $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        $current_time = $now->format('Y-m-d H:i:s');
        $warning_time = (clone $now)->modify('+5 minutes')->format('Y-m-d H:i:s');

        // Get available users
        $users_query = "SELECT id, name, username, user_type FROM users WHERE user_type IN ('doer', 'manager', 'admin') ORDER BY name LIMIT 20";
        $users_result = mysqli_query($conn, $users_query);
        $users = [];
        while ($row = mysqli_fetch_assoc($users_result)) {
            $users[] = $row;
        }

        // Handle actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            if ($action === 'create_dummy_tasks') {
                $selected_user_id = intval($_POST['user_id'] ?? 0);
                $task_count = intval($_POST['task_count'] ?? 1);
                $minutes_ahead = intval($_POST['minutes_ahead'] ?? 3); // Default 3 minutes ahead

                if ($selected_user_id > 0 && $task_count > 0) {
                    $created_count = 0;
                    $errors = [];

                    // Get user info
                    $user_query = "SELECT id, name, username FROM users WHERE id = ?";
                    $user_stmt = mysqli_prepare($conn, $user_query);
                    mysqli_stmt_bind_param($user_stmt, 'i', $selected_user_id);
                    mysqli_stmt_execute($user_stmt);
                    $user_result = mysqli_stmt_get_result($user_stmt);
                    $user = mysqli_fetch_assoc($user_result);
                    mysqli_stmt_close($user_stmt);

                    if ($user) {
                        // Calculate planned datetime (minutes_ahead minutes from now)
                        $planned_datetime = (clone $now)->modify("+{$minutes_ahead} minutes");
                        $planned_date = $planned_datetime->format('Y-m-d');
                        $planned_time = $planned_datetime->format('H:i:s');

                        for ($i = 1; $i <= $task_count; $i++) {
                            $unique_id = generateUniqueId();
                            $description = "Test Task #{$i} - Delay Notification Test (Planned: {$planned_datetime->format('d/m/Y H:i')})";

                            $insert_query = "INSERT INTO tasks (unique_id, description, planned_date, planned_time, duration, duration_minutes, doer_id, doer_name, status, created_at) 
                                           VALUES (?, ?, ?, ?, 1.0, 60, ?, ?, 'pending', NOW())";
                            
                            $stmt = mysqli_prepare($conn, $insert_query);
                            if ($stmt) {
                                mysqli_stmt_bind_param($stmt, 'ssssis', 
                                    $unique_id, 
                                    $description, 
                                    $planned_date, 
                                    $planned_time, 
                                    $selected_user_id,
                                    $user['username']
                                );
                                
                                if (mysqli_stmt_execute($stmt)) {
                                    $created_count++;
                                } else {
                                    $errors[] = "Failed to create task #{$i}: " . mysqli_error($conn);
                                }
                                mysqli_stmt_close($stmt);
                            } else {
                                $errors[] = "Failed to prepare statement for task #{$i}: " . mysqli_error($conn);
                            }
                        }

                        if ($created_count > 0) {
                            $message = "✓ Successfully created {$created_count} dummy task(s) for user: {$user['name']} ({$user['username']})";
                            $message .= "<br>Planned Date/Time: {$planned_datetime->format('d/m/Y H:i:s')}";
                            $message .= "<br>Current Time: {$now->format('d/m/Y H:i:s')}";
                            $message .= "<br>Time Difference: {$minutes_ahead} minutes";
                        }

                        if (!empty($errors)) {
                            $error = "Errors occurred:<br>" . implode("<br>", $errors);
                        }
                    } else {
                        $error = "User not found!";
                    }
                } else {
                    $error = "Please select a user and specify number of tasks!";
                }
            } elseif ($action === 'run_notification_check') {
                // Run the notification check with debugging
                try {
                    // Get count before
                    $before_query = "SELECT COUNT(*) as count FROM notifications WHERE type = 'task_delay' AND DATE(created_at) = CURDATE()";
                    $before_result = mysqli_query($conn, $before_query);
                    $before_row = mysqli_fetch_assoc($before_result);
                    $before_count = $before_row['count'];
                    
                    // Check how many tasks are eligible before running
                    $php_now = date('Y-m-d H:i:s');
                    $php_warning = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                    $eligible_check = "SELECT COUNT(*) as count FROM tasks t 
                                      WHERE t.status NOT IN ('completed', 'not done', 'can not be done', 'shifted')
                                      AND t.planned_date IS NOT NULL 
                                      AND t.planned_time IS NOT NULL
                                      AND t.doer_id IS NOT NULL
                                      AND TIMESTAMP(t.planned_date, t.planned_time) > ?
                                      AND TIMESTAMP(t.planned_date, t.planned_time) <= ?";
                    $eligible_stmt = mysqli_prepare($conn, $eligible_check);
                    mysqli_stmt_bind_param($eligible_stmt, 'ss', $php_now, $php_warning);
                    mysqli_stmt_execute($eligible_stmt);
                    $eligible_check_result = mysqli_stmt_get_result($eligible_stmt);
                    $eligible_count = mysqli_fetch_assoc($eligible_check_result)['count'];
                    mysqli_stmt_close($eligible_stmt);
                    
                    // Run the check
                    checkTaskDelayWarnings($conn);
                    
                    // Get count after
                    $after_query = "SELECT COUNT(*) as count FROM notifications WHERE type = 'task_delay' AND DATE(created_at) = CURDATE()";
                    $after_result = mysqli_query($conn, $after_query);
                    $after_row = mysqli_fetch_assoc($after_result);
                    $after_count = $after_row['count'];
                    
                    $created = $after_count - $before_count;
                    
                    if ($created > 0) {
                        $message = "✓ Notification check completed! Created {$created} new notification(s).";
                    } else {
                        $message = "✓ Notification check completed, but no new notifications were created.<br>";
                        $message .= "<strong>Diagnostics:</strong><br>";
                        $message .= "- Tasks eligible for notification: {$eligible_count}<br>";
                        $message .= "- Notifications before check: {$before_count}<br>";
                        $message .= "- Notifications after check: {$after_count}<br><br>";
                        $message .= "<strong>Possible reasons:</strong><br>";
                        $message .= "- No tasks are within the 5-minute warning window (NOW to NOW+5min)<br>";
                        $message .= "- Notifications already exist for eligible tasks today<br>";
                        $message .= "- Tasks don't meet the criteria (status must be 'pending', doer_id must be set)<br>";
                        $message .= "- Timezone mismatch between PHP and MySQL";
                    }
                } catch (Exception $e) {
                    $error = "Error running notification check: " . $e->getMessage();
                }
            } elseif ($action === 'cleanup_test_tasks') {
                // Delete test tasks
                $delete_query = "DELETE FROM tasks WHERE description LIKE 'Test Task% - Delay Notification Test%'";
                if (mysqli_query($conn, $delete_query)) {
                    $deleted_count = mysqli_affected_rows($conn);
                    $message = "✓ Cleaned up {$deleted_count} test task(s)";
                } else {
                    $error = "Error cleaning up: " . mysqli_error($conn);
                }
            } elseif ($action === 'cleanup_test_notifications') {
                // Delete test notifications
                $delete_query = "DELETE FROM notifications WHERE message LIKE 'Task may get delayed soon. Test Task%'";
                if (mysqli_query($conn, $delete_query)) {
                    $deleted_count = mysqli_affected_rows($conn);
                    $message = "✓ Cleaned up {$deleted_count} test notification(s)";
                } else {
                    $error = "Error cleaning up notifications: " . mysqli_error($conn);
                }
            }
        }

        // Display messages
        if ($message) {
            echo "<div class='success'>{$message}</div>";
        }
        if ($error) {
            echo "<div class='error'>{$error}</div>";
        }
        ?>

        <!-- Current Time Info -->
        <div class="section">
            <h2>⏰ Current Time Information</h2>
            <p><strong>Current Time:</strong> <?php echo $now->format('d/m/Y H:i:s'); ?> (Asia/Kolkata)</p>
            <p><strong>Warning Window End:</strong> <?php echo (clone $now)->modify('+5 minutes')->format('d/m/Y H:i:s'); ?></p>
            <p class="info">Tasks with planned time between <strong>now</strong> and <strong>+5 minutes</strong> will trigger delay notifications.</p>
        </div>

        <!-- Create Dummy Tasks Form -->
        <div class="section">
            <h2>➕ Create Dummy Tasks</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_dummy_tasks">
                
                <div class="form-group">
                    <label for="user_id">Select User:</label>
                    <select name="user_id" id="user_id" required>
                        <option value="">-- Select User --</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>">
                                <?php echo htmlspecialchars($u['name']); ?> (<?php echo htmlspecialchars($u['username']); ?>) - <?php echo $u['user_type']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="task_count">Number of Tasks:</label>
                    <input type="number" name="task_count" id="task_count" value="1" min="1" max="10" required>
                </div>

                <div class="form-group">
                    <label for="minutes_ahead">Minutes Ahead (from now):</label>
                    <input type="number" name="minutes_ahead" id="minutes_ahead" value="3" min="1" max="10" required>
                    <small style="color: #888;">
                        <strong>Important:</strong> Notifications trigger when tasks are within 5 minutes of planned time.<br>
                        - 1-5 minutes: Will trigger immediately<br>
                        - 6-10 minutes: Will trigger when they enter the 5-minute window
                    </small>
                </div>

                <button type="submit" class="btn btn-success">Create Dummy Tasks</button>
            </form>
            
            <div style="margin-top: 20px; padding: 15px; background: #2d2d30; border-radius: 5px;">
                <h3 style="color: #4ec9b0; margin-top: 0;">Quick Test (1-3 minutes ahead)</h3>
                <p style="color: #888; margin-bottom: 15px;">Create tasks that will trigger notifications immediately:</p>
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="action" value="create_dummy_tasks">
                    <input type="hidden" name="task_count" value="1">
                    <input type="hidden" name="minutes_ahead" value="2">
                    <select name="user_id" required style="padding: 8px; background: #2a2a2a; color: #d4d4d4; border: 1px solid #3e3e42; border-radius: 4px; margin-right: 10px;">
                        <option value="">-- Select User --</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>">
                                <?php echo htmlspecialchars($u['name']); ?> (<?php echo htmlspecialchars($u['username']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-success">Create Task (2 min ahead - Will Trigger)</button>
                </form>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="section">
            <h2>🔧 Actions</h2>
            <form method="POST" action="" style="display: inline;">
                <input type="hidden" name="action" value="run_notification_check">
                <button type="submit" class="btn">Run Notification Check</button>
            </form>

            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete all test tasks?');">
                <input type="hidden" name="action" value="cleanup_test_tasks">
                <button type="submit" class="btn btn-danger">Cleanup Test Tasks</button>
            </form>

            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete all test notifications?');">
                <input type="hidden" name="action" value="cleanup_test_notifications">
                <button type="submit" class="btn btn-danger">Cleanup Test Notifications</button>
            </form>
        </div>

        <!-- Test Tasks List -->
        <div class="section">
            <h2>📋 Test Tasks Created</h2>
            <?php
            $test_tasks_query = "SELECT t.id, t.unique_id, t.description, t.planned_date, t.planned_time, 
                                t.doer_id, t.doer_name, t.status, u.name as user_name,
                                TIMESTAMP(t.planned_date, t.planned_time) as planned_datetime
                                FROM tasks t
                                LEFT JOIN users u ON t.doer_id = u.id
                                WHERE t.description LIKE 'Test Task% - Delay Notification Test%'
                                ORDER BY t.created_at DESC
                                LIMIT 50";
            $test_tasks_result = mysqli_query($conn, $test_tasks_query);
            
            if ($test_tasks_result && mysqli_num_rows($test_tasks_result) > 0):
            ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Unique ID</th>
                            <th>Description</th>
                            <th>Planned Date</th>
                            <th>Planned Time</th>
                            <th>Planned DateTime</th>
                            <th>Doer</th>
                            <th>Status</th>
                            <th>Time Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $current_check_time = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
                        $warning_end_time = (clone $current_check_time)->modify('+5 minutes');
                        
                        while ($task = mysqli_fetch_assoc($test_tasks_result)):
                            $planned_dt = new DateTime($task['planned_datetime'], new DateTimeZone('Asia/Kolkata'));
                            $time_diff = $current_check_time->diff($planned_dt);
                            $minutes_diff = ($time_diff->days * 24 * 60) + ($time_diff->h * 60) + $time_diff->i;
                            
                            if ($planned_dt > $current_check_time && $planned_dt <= $warning_end_time) {
                                $time_status = '<span class="badge badge-warning">Within Warning Window</span>';
                            } elseif ($planned_dt <= $current_check_time) {
                                $time_status = '<span class="badge badge-error">Past Due</span>';
                            } else {
                                $time_status = '<span class="badge badge-info">Future</span>';
                            }
                        ?>
                            <tr>
                                <td><?php echo $task['id']; ?></td>
                                <td><?php echo htmlspecialchars($task['unique_id']); ?></td>
                                <td><?php echo htmlspecialchars($task['description']); ?></td>
                                <td><?php echo $task['planned_date']; ?></td>
                                <td><?php echo $task['planned_time']; ?></td>
                                <td><?php echo $planned_dt->format('d/m/Y H:i:s'); ?></td>
                                <td><?php echo htmlspecialchars($task['user_name'] ?? $task['doer_name']); ?></td>
                                <td><?php echo $task['status']; ?></td>
                                <td><?php echo $time_status; ?> (<?php echo $minutes_diff; ?> min)</td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="info">No test tasks found. Create some dummy tasks above.</p>
            <?php endif; ?>
        </div>

        <!-- Notifications Created -->
        <div class="section">
            <h2>🔔 Notifications Created</h2>
            <?php
            $notifications_query = "SELECT n.id, n.user_id, n.type, n.title, n.message, n.related_id, 
                                   n.related_type, n.created_at, u.name as user_name
                                   FROM notifications n
                                   LEFT JOIN users u ON n.user_id = u.id
                                   WHERE n.message LIKE 'Task may get delayed soon. Test Task%'
                                   ORDER BY n.created_at DESC
                                   LIMIT 50";
            $notifications_result = mysqli_query($conn, $notifications_query);
            
            if ($notifications_result && mysqli_num_rows($notifications_result) > 0):
            ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Title</th>
                            <th>Message</th>
                            <th>Related ID</th>
                            <th>Related Type</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        while ($notif = mysqli_fetch_assoc($notifications_result)):
                        ?>
                            <tr>
                                <td><?php echo $notif['id']; ?></td>
                                <td><?php echo htmlspecialchars($notif['user_name']); ?> (ID: <?php echo $notif['user_id']; ?>)</td>
                                <td><span class="badge badge-info"><?php echo $notif['type']; ?></span></td>
                                <td><?php echo htmlspecialchars($notif['title']); ?></td>
                                <td><?php echo htmlspecialchars($notif['message']); ?></td>
                                <td><?php echo $notif['related_id']; ?></td>
                                <td><?php echo $notif['related_type']; ?></td>
                                <td><?php echo $notif['created_at']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="info">No test notifications found. Create tasks and run the notification check.</p>
            <?php endif; ?>
        </div>

        <!-- Diagnostic Information -->
        <div class="section">
            <h2>🔍 Diagnostic Information</h2>
            <?php
            // Show what the query is checking (using same logic as notification function)
            $php_now = date('Y-m-d H:i:s');
            $php_warning = date('Y-m-d H:i:s', strtotime('+5 minutes'));
            
            echo "<p><strong>PHP Current Time (used in query):</strong> {$php_now}</p>";
            echo "<p><strong>PHP Warning Window End (used in query):</strong> {$php_warning}</p>";
            
            // Check MySQL time
            $mysql_time_result = mysqli_query($conn, "SELECT NOW() as mysql_now, DATE_ADD(NOW(), INTERVAL 5 MINUTE) as mysql_warning");
            if ($mysql_time_result) {
                $mysql_time = mysqli_fetch_assoc($mysql_time_result);
                echo "<p><strong>MySQL Current Time:</strong> {$mysql_time['mysql_now']}</p>";
                echo "<p><strong>MySQL Warning Window End:</strong> {$mysql_time['mysql_warning']}</p>";
            }
            
            // Check timezone
            $tz_result = mysqli_query($conn, "SELECT @@session.time_zone as tz");
            if ($tz_result) {
                $tz = mysqli_fetch_assoc($tz_result);
                echo "<p><strong>MySQL Timezone:</strong> {$tz['tz']}</p>";
            }
            echo "<p><strong>PHP Timezone:</strong> " . date_default_timezone_get() . "</p>";
            
            // Test a direct query to see what tasks would match
            echo "<h3 style='color: #569cd6; margin-top: 20px;'>Test Query Results</h3>";
            $test_query = "SELECT COUNT(*) as count FROM tasks t 
                          WHERE t.status NOT IN ('completed', 'not done', 'can not be done', 'shifted')
                          AND t.planned_date IS NOT NULL 
                          AND t.planned_time IS NOT NULL
                          AND t.doer_id IS NOT NULL
                          AND TIMESTAMP(t.planned_date, t.planned_time) > '{$php_now}'
                          AND TIMESTAMP(t.planned_date, t.planned_time) <= '{$php_warning}'";
            $test_result = mysqli_query($conn, $test_query);
            if ($test_result) {
                $test_row = mysqli_fetch_assoc($test_result);
                echo "<p><strong>Tasks matching criteria:</strong> {$test_row['count']}</p>";
            }
            ?>
        </div>

        <!-- Tasks Eligible for Notification -->
        <div class="section">
            <h2>✅ Tasks Eligible for Delay Notification (Right Now)</h2>
            <?php
            // Use the same logic as the notification function
            $php_now = date('Y-m-d H:i:s');
            $php_warning = date('Y-m-d H:i:s', strtotime('+5 minutes'));
            
            $eligible_query = "SELECT t.id, t.unique_id, t.description, t.planned_date, t.planned_time, 
                              t.doer_id, COALESCE(t.doer_name, u.name, u.username) as user_name,
                              TIMESTAMP(t.planned_date, t.planned_time) as planned_datetime,
                              t.status
                              FROM tasks t 
                              LEFT JOIN users u ON t.doer_id = u.id 
                              WHERE t.status NOT IN ('completed', 'not done', 'can not be done', 'shifted')
                              AND t.planned_date IS NOT NULL 
                              AND t.planned_time IS NOT NULL
                              AND t.doer_id IS NOT NULL
                              AND TIMESTAMP(t.planned_date, t.planned_time) > ?
                              AND TIMESTAMP(t.planned_date, t.planned_time) <= ?
                              ORDER BY TIMESTAMP(t.planned_date, t.planned_time) ASC";
            
            echo "<p class='info'><strong>Query Parameters:</strong><br>";
            echo "NOW: {$php_now}<br>";
            echo "WARNING END: {$php_warning}</p>";
            
            $stmt = mysqli_prepare($conn, $eligible_query);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ss', $php_now, $php_warning);
                mysqli_stmt_execute($stmt);
                $eligible_result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($eligible_result) > 0):
            ?>
                    <table>
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Unique ID</th>
                            <th>Description</th>
                            <th>Planned DateTime</th>
                            <th>Doer</th>
                            <th>Status</th>
                            <th>Has Notification Today?</th>
                        </tr>
                        </thead>
                        <tbody>
                            <?php
                            while ($task = mysqli_fetch_assoc($eligible_result)):
                                $planned_dt = new DateTime($task['planned_datetime'], new DateTimeZone('Asia/Kolkata'));
                                
                                // Check if notification exists
                                $notif_check = "SELECT id FROM notifications 
                                               WHERE type = 'task_delay' 
                                               AND related_id = CAST(? AS CHAR)
                                               AND user_id = ?
                                               AND related_type = 'task'
                                               AND DATE(created_at) = CURDATE()";
                                $notif_stmt = mysqli_prepare($conn, $notif_check);
                                mysqli_stmt_bind_param($notif_stmt, 'si', $task['id'], $task['doer_id']);
                                mysqli_stmt_execute($notif_stmt);
                                $notif_result = mysqli_stmt_get_result($notif_stmt);
                                $has_notification = mysqli_num_rows($notif_result) > 0;
                                mysqli_stmt_close($notif_stmt);
                            ?>
                                <tr>
                                    <td><?php echo $task['id']; ?></td>
                                    <td><?php echo htmlspecialchars($task['unique_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($task['description']); ?></td>
                                    <td><?php echo $planned_dt->format('d/m/Y H:i:s'); ?></td>
                                    <td><?php echo htmlspecialchars($task['user_name']); ?></td>
                                    <td><?php echo $task['status']; ?></td>
                                    <td>
                                        <?php if ($has_notification): ?>
                                            <span class="badge badge-success">Yes</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">No</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="info">No tasks are currently eligible for delay notifications (within the 5-minute warning window).</p>
                <?php endif;
                mysqli_stmt_close($stmt);
            } else {
                echo "<p class='error'>Error preparing query: " . mysqli_error($conn) . "</p>";
            }
            ?>
        </div>

    </div>
</body>
</html>
