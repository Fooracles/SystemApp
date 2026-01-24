<?php
/**
 * Test Notification Redirects
 * 
 * This file tests if notification redirects are working correctly.
 * It creates test notifications and verifies redirect URLs.
 * 
 * Usage:
 * Access via browser: http://your-domain/test/test_notification_redirects.php
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notification_triggers.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

$user_id = $_SESSION['id'] ?? null;
$user_type = $_SESSION['user_type'] ?? 'doer';
$user_name = $_SESSION['name'] ?? 'User';
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_test_notifications':
            $result = createTestRedirectNotifications($conn, $user_id);
            if ($result['success']) {
                $message = "✓ Test notifications created successfully! Created {$result['count']} notifications.";
            } else {
                $error = "Failed to create notifications: " . $result['error'];
            }
            break;
        
        case 'clear_test_notifications':
            $clear_query = "DELETE FROM notifications WHERE user_id = ? AND message LIKE 'TEST REDIRECT:%'";
            $clear_stmt = mysqli_prepare($conn, $clear_query);
            mysqli_stmt_bind_param($clear_stmt, 'i', $user_id);
            mysqli_stmt_execute($clear_stmt);
            $deleted = mysqli_affected_rows($conn);
            $message = "✓ Cleared {$deleted} test notification(s)!";
            break;
    }
}

function createTestRedirectNotifications($conn, $user_id) {
    $created_count = 0;
    $errors = [];
    
    // Expected redirect URLs based on notification type
    $expected_redirects = [
        'task_delay' => '../pages/my_task.php',
        'meeting_request' => '../pages/admin_my_meetings.php',
        'meeting_approved' => '../pages/admin_my_meetings.php',
        'meeting_rescheduled' => '../pages/admin_my_meetings.php',
        'leave_request' => '../pages/leave_request.php',
        'leave_approved' => '../pages/leave_request.php',
        'leave_rejected' => '../pages/leave_request.php',
        'notes_reminder' => '../pages/my_notes.php',
        'day_special' => null // No redirect
    ];
    
    // 1. Task Delay Notification
    $result = createNotification(
        $conn,
        $user_id,
        'task_delay',
        'Task Delay Warning',
        'TEST REDIRECT: Task may get delayed soon. DELG-12345 - Planned: 02:30 PM',
        '12345',
        'task',
        false,
        null
    );
    if ($result) {
        $created_count++;
    } else {
        $errors[] = "Failed to create task_delay notification";
    }
    
    // 2. Meeting Request Notification
    $result = createNotification(
        $conn,
        $user_id,
        'meeting_request',
        'New Meeting Request',
        'TEST REDIRECT: New meeting request received from John Doe',
        1,
        'meeting',
        true,
        [
            'actions' => [
                [
                    'type' => 'approve_meeting',
                    'label' => 'Approve',
                    'icon' => 'fas fa-check',
                    'color' => 'success',
                    'tooltip' => 'Approve Meeting'
                ],
                [
                    'type' => 'reschedule_meeting',
                    'label' => 'Re-schedule',
                    'icon' => 'fas fa-calendar-alt',
                    'color' => 'primary',
                    'tooltip' => 'Re-schedule Meeting'
                ]
            ]
        ]
    );
    if ($result) {
        $created_count++;
    } else {
        $errors[] = "Failed to create meeting_request notification";
    }
    
    // 3. Meeting Approved Notification
    $result = createNotification(
        $conn,
        $user_id,
        'meeting_approved',
        'Meeting Approved',
        'TEST REDIRECT: Your meeting request has been approved',
        1,
        'meeting',
        false,
        null
    );
    if ($result) {
        $created_count++;
    } else {
        $errors[] = "Failed to create meeting_approved notification";
    }
    
    // 4. Meeting Rescheduled Notification
    $result = createNotification(
        $conn,
        $user_id,
        'meeting_rescheduled',
        'Meeting Rescheduled',
        'TEST REDIRECT: Your meeting has been rescheduled to tomorrow at 3:00 PM',
        1,
        'meeting',
        false,
        null
    );
    if ($result) {
        $created_count++;
    } else {
        $errors[] = "Failed to create meeting_rescheduled notification";
    }
    
    // 5. Leave Request Notification
    $result = createNotification(
        $conn,
        $user_id,
        'leave_request',
        'Leave Request',
        'TEST REDIRECT: John Doe has applied for leave',
        1,
        'leave',
        true,
        [
            'actions' => [
                [
                    'type' => 'approve_leave',
                    'label' => 'Approve',
                    'icon' => 'fas fa-check',
                    'color' => 'success',
                    'tooltip' => 'Approve Leave'
                ],
                [
                    'type' => 'reject_leave',
                    'label' => 'Reject',
                    'icon' => 'fas fa-times',
                    'color' => 'danger',
                    'tooltip' => 'Reject Leave'
                ]
            ]
        ]
    );
    if ($result) {
        $created_count++;
    } else {
        $errors[] = "Failed to create leave_request notification";
    }
    
    // 6. Leave Approved Notification
    $result = createNotification(
        $conn,
        $user_id,
        'leave_approved',
        'Leave Approved',
        'TEST REDIRECT: Your leave request has been approved',
        1,
        'leave',
        false,
        null
    );
    if ($result) {
        $created_count++;
    } else {
        $errors[] = "Failed to create leave_approved notification";
    }
    
    // 7. Leave Rejected Notification
    $result = createNotification(
        $conn,
        $user_id,
        'leave_rejected',
        'Leave Rejected',
        'TEST REDIRECT: Your leave request has been rejected',
        1,
        'leave',
        false,
        null
    );
    if ($result) {
        $created_count++;
    } else {
        $errors[] = "Failed to create leave_rejected notification";
    }
    
    // 8. Notes Reminder Notification
    $result = createNotification(
        $conn,
        $user_id,
        'notes_reminder',
        'Reminder',
        'TEST REDIRECT: Reminder: Important meeting notes',
        1,
        'note',
        false,
        null
    );
    if ($result) {
        $created_count++;
    } else {
        $errors[] = "Failed to create notes_reminder notification";
    }
    
    // 9. Day Special Notification (no redirect)
    $result = createNotification(
        $conn,
        $user_id,
        'day_special',
        'Birthday Today 🎉',
        'TEST REDIRECT: Today is John Doe\'s Birthday 🎉',
        $user_id,
        'user',
        false,
        null
    );
    if ($result) {
        $created_count++;
    } else {
        $errors[] = "Failed to create day_special notification";
    }
    
    return [
        'success' => $created_count > 0,
        'count' => $created_count,
        'error' => !empty($errors) ? implode(', ', $errors) : null
    ];
}

// Get test notifications
$test_notifications_query = "SELECT id, type, title, message, related_id, related_type, is_read, created_at 
                            FROM notifications 
                            WHERE user_id = ? AND message LIKE 'TEST REDIRECT:%'
                            ORDER BY created_at DESC";
$test_notifications_stmt = mysqli_prepare($conn, $test_notifications_query);
mysqli_stmt_bind_param($test_notifications_stmt, 'i', $user_id);
mysqli_stmt_execute($test_notifications_stmt);
$test_notifications_result = mysqli_stmt_get_result($test_notifications_stmt);
$test_notifications = [];
while ($row = mysqli_fetch_assoc($test_notifications_result)) {
    $test_notifications[] = $row;
}
mysqli_stmt_close($test_notifications_stmt);

// Expected redirect URLs
$expected_redirects = [
    'task_delay' => '../pages/my_task.php',
    'meeting_request' => '../pages/admin_my_meetings.php',
    'meeting_approved' => '../pages/admin_my_meetings.php',
    'meeting_rescheduled' => '../pages/admin_my_meetings.php',
    'leave_request' => '../pages/leave_request.php',
    'leave_approved' => '../pages/leave_request.php',
    'leave_rejected' => '../pages/leave_request.php',
    'notes_reminder' => '../pages/my_notes.php',
    'day_special' => null // No redirect
];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Notification Redirects</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            background: #1e1e1e;
            color: #d4d4d4;
            line-height: 1.6;
        }
        .container {
            max-width: 1400px;
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
        .btn-success {
            background: #4caf50;
        }
        .btn-success:hover {
            background: #388e3c;
        }
        .btn-danger {
            background: #d32f2f;
        }
        .btn-danger:hover {
            background: #b71c1c;
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
        .badge-info {
            background: #2196f3;
            color: white;
        }
        .badge-warning {
            background: #ff9800;
            color: white;
        }
        .test-notification-item {
            padding: 15px;
            margin: 10px 0;
            background: #2d2d30;
            border: 1px solid #3e3e42;
            border-radius: 5px;
            cursor: pointer;
        }
        .test-notification-item:hover {
            background: #3e3e42;
        }
        .test-notification-item.unread {
            border-left: 3px solid #4ec9b0;
            background: rgba(78, 201, 176, 0.05);
        }
        .notification-title {
            font-weight: 600;
            color: #4ec9b0;
            margin-bottom: 5px;
        }
        .notification-message {
            color: #d4d4d4;
            margin-bottom: 10px;
        }
        .notification-meta {
            font-size: 12px;
            color: #888;
        }
        .redirect-info {
            margin-top: 10px;
            padding: 8px;
            background: rgba(86, 156, 214, 0.1);
            border-radius: 3px;
            font-size: 12px;
        }
        .redirect-url {
            color: #569cd6;
            font-family: monospace;
        }
        .no-redirect {
            color: #888;
            font-style: italic;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container">
        <h1>🧪 Test Notification Redirects</h1>
        
        <?php if ($message): ?>
            <div class="success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Actions -->
        <div class="section">
            <h2>🔧 Actions</h2>
            <form method="POST" action="" style="display: inline;">
                <input type="hidden" name="action" value="create_test_notifications">
                <button type="submit" class="btn btn-success">Create Test Notifications</button>
            </form>
            
            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to clear all test notifications?');">
                <input type="hidden" name="action" value="clear_test_notifications">
                <button type="submit" class="btn btn-danger">Clear Test Notifications</button>
            </form>
        </div>
        
        <!-- Expected Redirects Reference -->
        <div class="section">
            <h2>📋 Expected Redirect URLs</h2>
            <table>
                <thead>
                    <tr>
                        <th>Notification Type</th>
                        <th>Expected Redirect URL</th>
                        <th>Target Page</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expected_redirects as $type => $url): ?>
                        <tr>
                            <td><span class="badge badge-info"><?php echo htmlspecialchars($type); ?></span></td>
                            <td>
                                <?php if ($url): ?>
                                    <span class="redirect-url"><?php echo htmlspecialchars($url); ?></span>
                                <?php else: ?>
                                    <span class="no-redirect">No redirect (static notification)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $page_map = [
                                    '../pages/my_task.php' => 'My Tasks',
                                    '../pages/admin_my_meetings.php' => 'My Meetings',
                                    '../pages/leave_request.php' => 'Leave Requests',
                                    '../pages/my_notes.php' => 'My Notes'
                                ];
                                echo $url ? ($page_map[$url] ?? 'Unknown') : 'N/A';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Test Notifications -->
        <div class="section">
            <h2>🔔 Test Notifications (Click to Test Redirect)</h2>
            <?php if (empty($test_notifications)): ?>
                <p class="info">No test notifications found. Click "Create Test Notifications" to create test notifications.</p>
            <?php else: ?>
                <p class="info">Found <?php echo count($test_notifications); ?> test notification(s). Click on a notification to test the redirect.</p>
                
                <div id="testNotificationsContainer">
                    <?php foreach ($test_notifications as $notif): ?>
                        <?php
                        $expected_url = $expected_redirects[$notif['type']] ?? null;
                        $is_unread = !$notif['is_read'];
                        ?>
                        <div class="test-notification-item <?php echo $is_unread ? 'unread' : ''; ?>" 
                             data-notification-id="<?php echo $notif['id']; ?>"
                             data-notification-type="<?php echo htmlspecialchars($notif['type']); ?>"
                             data-expected-url="<?php echo htmlspecialchars($expected_url ?? ''); ?>"
                             style="cursor: <?php echo $expected_url ? 'pointer' : 'default'; ?>;">
                            <div class="notification-title">
                                <?php echo htmlspecialchars($notif['title']); ?>
                                <?php if ($is_unread): ?>
                                    <span class="badge badge-warning">Unread</span>
                                <?php else: ?>
                                    <span class="badge badge-info">Read</span>
                                <?php endif; ?>
                            </div>
                            <div class="notification-message">
                                <?php echo htmlspecialchars($notif['message']); ?>
                            </div>
                            <div class="notification-meta">
                                Type: <span class="badge badge-info"><?php echo htmlspecialchars($notif['type']); ?></span> | 
                                Created: <?php echo date('d/m/Y H:i:s', strtotime($notif['created_at'])); ?>
                            </div>
                            <div class="redirect-info">
                                <?php if ($expected_url): ?>
                                    <strong>Expected Redirect:</strong> <span class="redirect-url"><?php echo htmlspecialchars($expected_url); ?></span>
                                <?php else: ?>
                                    <span class="no-redirect">No redirect expected (static notification)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Test Results -->
        <div class="section">
            <h2>📊 Test Instructions</h2>
            <ol style="line-height: 2;">
                <li>Click "Create Test Notifications" to create test notifications of all types</li>
                <li>Click on each notification's title or message to test the redirect</li>
                <li>Verify that you are redirected to the correct page as shown in the "Expected Redirect URLs" table</li>
                <li>Test both read and unread notifications</li>
                <li>Verify that clicking action buttons (Approve, Reject, Reschedule) does NOT trigger redirect</li>
                <li>Check that the cursor changes to pointer when hovering over clickable notifications</li>
            </ol>
        </div>
        
        <!-- JavaScript Test -->
        <div class="section">
            <h2>🔍 JavaScript Redirect Test</h2>
            <p class="info">This section tests the JavaScript redirect logic directly.</p>
            <button class="btn" onclick="testRedirectLogic()">Test Redirect Logic</button>
            <div id="redirectTestResults" style="margin-top: 15px;"></div>
        </div>
    </div>
    
    <script>
        // Test notification redirects
        $(document).ready(function() {
            // Handle clicks on test notification items
            $('.test-notification-item').on('click', function(e) {
                const $item = $(this);
                const expectedUrl = $item.data('expected-url');
                const notificationId = $item.data('notification-id');
                const notificationType = $item.data('notification-type');
                
                if (!expectedUrl) {
                    alert('This notification type does not have a redirect URL.');
                    return;
                }
                
                // Confirm before redirecting
                if (confirm(`Test redirect for ${notificationType}?\n\nExpected URL: ${expectedUrl}\n\nClick OK to redirect, Cancel to stay on page.`)) {
                    // Mark as read if unread
                    if ($item.hasClass('unread')) {
                        $.ajax({
                            url: '../ajax/notifications_handler.php',
                            method: 'POST',
                            data: {
                                action: 'mark_read',
                                notification_id: notificationId
                            },
                            dataType: 'json',
                            async: true
                        });
                    }
                    
                    // Redirect
                    window.location.href = expectedUrl;
                }
            });
        });
        
        // Test redirect logic function
        function testRedirectLogic() {
            const results = [];
            const currentPath = window.location.pathname;
            const isInPages = currentPath.includes('/pages/');
            const basePath = isInPages ? '' : '../pages/';
            
            const testCases = [
                { type: 'task_delay', expected: basePath + 'my_task.php' },
                { type: 'meeting_request', expected: basePath + 'admin_my_meetings.php' },
                { type: 'meeting_approved', expected: basePath + 'admin_my_meetings.php' },
                { type: 'meeting_rescheduled', expected: basePath + 'admin_my_meetings.php' },
                { type: 'leave_request', expected: basePath + 'leave_request.php' },
                { type: 'leave_approved', expected: basePath + 'leave_request.php' },
                { type: 'leave_rejected', expected: basePath + 'leave_request.php' },
                { type: 'notes_reminder', expected: basePath + 'my_notes.php' },
                { type: 'day_special', expected: null }
            ];
            
            // Test if NotificationsManager is available
            if (typeof NotificationsManager !== 'undefined' && NotificationsManager.getNotificationRedirectUrl) {
                testCases.forEach(testCase => {
                    const mockNotification = { type: testCase.type };
                    const actual = NotificationsManager.getNotificationRedirectUrl(mockNotification);
                    const passed = actual === testCase.expected;
                    
                    results.push({
                        type: testCase.type,
                        expected: testCase.expected,
                        actual: actual,
                        passed: passed
                    });
                });
            } else {
                $('#redirectTestResults').html('<div class="error">NotificationsManager.getNotificationRedirectUrl() function not available. Make sure notifications.js is loaded.</div>');
                return;
            }
            
            // Display results
            let html = '<table><thead><tr><th>Type</th><th>Expected</th><th>Actual</th><th>Result</th></tr></thead><tbody>';
            results.forEach(result => {
                const statusClass = result.passed ? 'badge-success' : 'badge-error';
                const statusText = result.passed ? '✓ PASS' : '✗ FAIL';
                html += `<tr>
                    <td><span class="badge badge-info">${result.type}</span></td>
                    <td>${result.expected || 'null'}</td>
                    <td>${result.actual || 'null'}</td>
                    <td><span class="badge ${statusClass}">${statusText}</span></td>
                </tr>`;
            });
            html += '</tbody></table>';
            
            const allPassed = results.every(r => r.passed);
            if (allPassed) {
                html = '<div class="success">✓ All redirect tests passed!</div>' + html;
            } else {
                html = '<div class="error">✗ Some redirect tests failed. Check the table below.</div>' + html;
            }
            
            $('#redirectTestResults').html(html);
        }
    </script>
</body>
</html>
