<?php
/**
 * Test Notification Redirect Button
 * 
 * This file tests if the notification redirect button is working correctly.
 * It creates test notifications (both read and unread) and verifies:
 * 1. Redirect button appears for notifications with redirect URLs
 * 2. Redirect button works for both read and unread notifications
 * 3. Each notification type redirects to the correct page
 * 
 * Usage:
 * Access via browser: http://your-domain/test/test_notification_redirect_button.php
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
            $result = createTestRedirectButtonNotifications($conn, $user_id);
            if ($result['success']) {
                $message = "✓ Test notifications created successfully! Created {$result['count']} notifications ({$result['unread_count']} unread, {$result['read_count']} read).";
            } else {
                $error = "Failed to create notifications: " . $result['error'];
            }
            break;
        
        case 'clear_test_notifications':
            $clear_query = "DELETE FROM notifications WHERE user_id = ? AND message LIKE 'TEST REDIRECT BUTTON:%'";
            $clear_stmt = mysqli_prepare($conn, $clear_query);
            mysqli_stmt_bind_param($clear_stmt, 'i', $user_id);
            mysqli_stmt_execute($clear_stmt);
            $deleted = mysqli_affected_rows($conn);
            $message = "✓ Cleared {$deleted} test notification(s)!";
            break;
    }
}

function createTestRedirectButtonNotifications($conn, $user_id) {
    $created_count = 0;
    $unread_count = 0;
    $read_count = 0;
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
    
    // Create UNREAD notifications (should show redirect button)
    
    // 1. Task Delay Notification (UNREAD)
    $result = createNotification(
        $conn,
        $user_id,
        'task_delay',
        'Task Delay Warning',
        'TEST REDIRECT BUTTON: Task may get delayed soon. DELG-12345 - Planned: 02:30 PM',
        '12345',
        'task',
        false,
        null
    );
    if ($result) {
        $created_count++;
        $unread_count++;
    } else {
        $errors[] = "Failed to create task_delay notification (unread)";
    }
    
    // 2. Meeting Request Notification (UNREAD)
    $result = createNotification(
        $conn,
        $user_id,
        'meeting_request',
        'New Meeting Request',
        'TEST REDIRECT BUTTON: New meeting request received from John Doe',
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
                ]
            ]
        ]
    );
    if ($result) {
        $created_count++;
        $unread_count++;
    } else {
        $errors[] = "Failed to create meeting_request notification (unread)";
    }
    
    // 3. Meeting Approved Notification (UNREAD)
    $result = createNotification(
        $conn,
        $user_id,
        'meeting_approved',
        'Meeting Approved',
        'TEST REDIRECT BUTTON: Your meeting request has been approved',
        1,
        'meeting',
        false,
        null
    );
    if ($result) {
        $created_count++;
        $unread_count++;
    } else {
        $errors[] = "Failed to create meeting_approved notification (unread)";
    }
    
    // 4. Leave Request Notification (UNREAD)
    $result = createNotification(
        $conn,
        $user_id,
        'leave_request',
        'Leave Request',
        'TEST REDIRECT BUTTON: John Doe has applied for leave',
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
                ]
            ]
        ]
    );
    if ($result) {
        $created_count++;
        $unread_count++;
    } else {
        $errors[] = "Failed to create leave_request notification (unread)";
    }
    
    // 5. Notes Reminder Notification (UNREAD)
    $result = createNotification(
        $conn,
        $user_id,
        'notes_reminder',
        'Reminder',
        'TEST REDIRECT BUTTON: Reminder: Important meeting notes',
        1,
        'note',
        false,
        null
    );
    if ($result) {
        $created_count++;
        $unread_count++;
    } else {
        $errors[] = "Failed to create notes_reminder notification (unread)";
    }
    
    // Now create READ notifications (should also show redirect button)
    
    // 6. Task Delay Notification (READ)
    $result = createNotification(
        $conn,
        $user_id,
        'task_delay',
        'Task Delay Warning',
        'TEST REDIRECT BUTTON: Task may get delayed soon. DELG-67890 - Planned: 04:00 PM',
        '67890',
        'task',
        false,
        null
    );
    if ($result) {
        // Mark as read
        $mark_read_query = "UPDATE notifications SET is_read = 1 WHERE id = ?";
        $mark_read_stmt = mysqli_prepare($conn, $mark_read_query);
        mysqli_stmt_bind_param($mark_read_stmt, 'i', $result);
        mysqli_stmt_execute($mark_read_stmt);
        mysqli_stmt_close($mark_read_stmt);
        
        $created_count++;
        $read_count++;
    } else {
        $errors[] = "Failed to create task_delay notification (read)";
    }
    
    // 7. Meeting Approved Notification (READ)
    $result = createNotification(
        $conn,
        $user_id,
        'meeting_approved',
        'Meeting Approved',
        'TEST REDIRECT BUTTON: Your meeting request has been approved (read)',
        2,
        'meeting',
        false,
        null
    );
    if ($result) {
        // Mark as read
        $mark_read_query = "UPDATE notifications SET is_read = 1 WHERE id = ?";
        $mark_read_stmt = mysqli_prepare($conn, $mark_read_query);
        mysqli_stmt_bind_param($mark_read_stmt, 'i', $result);
        mysqli_stmt_execute($mark_read_stmt);
        mysqli_stmt_close($mark_read_stmt);
        
        $created_count++;
        $read_count++;
    } else {
        $errors[] = "Failed to create meeting_approved notification (read)";
    }
    
    // 8. Leave Approved Notification (READ)
    $result = createNotification(
        $conn,
        $user_id,
        'leave_approved',
        'Leave Approved',
        'TEST REDIRECT BUTTON: Your leave request has been approved (read)',
        1,
        'leave',
        false,
        null
    );
    if ($result) {
        // Mark as read
        $mark_read_query = "UPDATE notifications SET is_read = 1 WHERE id = ?";
        $mark_read_stmt = mysqli_prepare($conn, $mark_read_query);
        mysqli_stmt_bind_param($mark_read_stmt, 'i', $result);
        mysqli_stmt_execute($mark_read_stmt);
        mysqli_stmt_close($mark_read_stmt);
        
        $created_count++;
        $read_count++;
    } else {
        $errors[] = "Failed to create leave_approved notification (read)";
    }
    
    // 9. Notes Reminder Notification (READ)
    $result = createNotification(
        $conn,
        $user_id,
        'notes_reminder',
        'Reminder',
        'TEST REDIRECT BUTTON: Reminder: Important meeting notes (read)',
        2,
        'note',
        false,
        null
    );
    if ($result) {
        // Mark as read
        $mark_read_query = "UPDATE notifications SET is_read = 1 WHERE id = ?";
        $mark_read_stmt = mysqli_prepare($conn, $mark_read_query);
        mysqli_stmt_bind_param($mark_read_stmt, 'i', $result);
        mysqli_stmt_execute($mark_read_stmt);
        mysqli_stmt_close($mark_read_stmt);
        
        $created_count++;
        $read_count++;
    } else {
        $errors[] = "Failed to create notes_reminder notification (read)";
    }
    
    // 10. Day Special Notification (no redirect button expected)
    $result = createNotification(
        $conn,
        $user_id,
        'day_special',
        'Birthday Today 🎉',
        'TEST REDIRECT BUTTON: Today is John Doe\'s Birthday 🎉',
        $user_id,
        'user',
        false,
        null
    );
    if ($result) {
        $created_count++;
        $unread_count++;
    } else {
        $errors[] = "Failed to create day_special notification";
    }
    
    return [
        'success' => $created_count > 0,
        'count' => $created_count,
        'unread_count' => $unread_count,
        'read_count' => $read_count,
        'error' => !empty($errors) ? implode(', ', $errors) : null
    ];
}

// Get test notifications
$test_notifications_query = "SELECT id, type, title, message, related_id, related_type, is_read, created_at 
                            FROM notifications 
                            WHERE user_id = ? AND message LIKE 'TEST REDIRECT BUTTON:%'
                            ORDER BY is_read ASC, created_at DESC";
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Notification Redirect Button</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .test-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
        }
        .test-section {
            background: var(--dark-bg-card);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid var(--glass-border);
        }
        .test-section h2 {
            color: var(--dark-text-primary);
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        .test-section h3 {
            color: var(--dark-text-primary);
            margin-bottom: 0.75rem;
            font-size: 1.2rem;
        }
        .notification-test-item {
            background: var(--dark-bg-hover);
            border: 1px solid var(--glass-border);
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .notification-test-item.unread {
            border-left: 3px solid var(--brand-primary);
            background: rgba(99, 102, 241, 0.05);
        }
        .test-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .test-info strong {
            color: var(--dark-text-primary);
        }
        .test-info .badge {
            font-size: 0.85rem;
        }
        .expected-url {
            color: var(--brand-primary);
            font-family: monospace;
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }
        .no-redirect {
            color: #9ca3af;
            font-style: italic;
        }
        .test-button {
            margin-top: 1rem;
        }
        .instructions {
            background: rgba(99, 102, 241, 0.1);
            border-left: 3px solid var(--brand-primary);
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
        }
        .instructions h4 {
            color: var(--brand-primary);
            margin-bottom: 0.5rem;
        }
        .instructions ul {
            margin: 0.5rem 0;
            padding-left: 1.5rem;
        }
        .instructions li {
            color: var(--dark-text-secondary);
            margin-bottom: 0.25rem;
        }
        .redirect-button-test {
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: rgba(99, 102, 241, 0.1);
            border-radius: 4px;
            font-size: 0.85rem;
        }
        .redirect-button-test strong {
            color: var(--brand-primary);
        }
    </style>
</head>
<body>
    <div class="test-container">
        <div class="test-section">
            <h1>🧪 Test Notification Redirect Button</h1>
            <p class="text-muted">This page tests if the redirect button appears and works correctly for all notification types (both read and unread).</p>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="instructions">
                <h4>📋 Test Instructions</h4>
                <ul>
                    <li><strong>Important:</strong> Clear your browser cache (Ctrl+F5 or Cmd+Shift+R) to ensure you're using the latest JavaScript code</li>
                    <li>Click "Create Test Notifications" to generate test notifications (both read and unread)</li>
                    <li>Check the notification bell icon in the header to see the test notifications</li>
                    <li>Verify that each notification with a redirect URL shows a redirect button (external link icon) instead of the bell icon</li>
                    <li>Click the redirect button on each notification to verify it redirects to the correct page</li>
                    <li>Verify that both read and unread notifications show the redirect button</li>
                    <li>Verify that day_special notifications show the bell icon (no redirect button)</li>
                    <li>Open browser console (F12) to see debug logs if redirect buttons are not showing</li>
                </ul>
            </div>
            
            <form method="POST" class="mb-4">
                <input type="hidden" name="action" value="create_test_notifications">
                <button type="submit" class="btn btn-primary test-button">
                    <i class="fas fa-plus"></i> Create Test Notifications
                </button>
            </form>
            
            <?php if (count($test_notifications) > 0): ?>
                <form method="POST" class="mb-4">
                    <input type="hidden" name="action" value="clear_test_notifications">
                    <button type="submit" class="btn btn-danger test-button">
                        <i class="fas fa-trash"></i> Clear Test Notifications
                    </button>
                </form>
            <?php endif; ?>
        </div>
        
        <?php if (count($test_notifications) > 0): ?>
            <div class="test-section">
                <h2>🔔 Test Notifications Created</h2>
                <p class="text-muted">Found <?php echo count($test_notifications); ?> test notification(s). Check the notification bell in the header to see them with redirect buttons.</p>
                
                <div class="row">
                    <?php foreach ($test_notifications as $notif): ?>
                        <?php
                        $expected_url = $expected_redirects[$notif['type']] ?? null;
                        $status_class = $notif['is_read'] ? 'read' : 'unread';
                        $status_badge = $notif['is_read'] ? '<span class="badge badge-secondary">READ</span>' : '<span class="badge badge-primary">UNREAD</span>';
                        ?>
                        <div class="col-md-6 mb-3">
                            <div class="notification-test-item <?php echo $status_class; ?>">
                                <div class="test-info">
                                    <div>
                                        <strong><?php echo htmlspecialchars($notif['title']); ?></strong>
                                        <?php echo $status_badge; ?>
                                    </div>
                                </div>
                                <div class="text-muted" style="font-size: 0.9rem; margin-top: 0.5rem;">
                                    <?php echo htmlspecialchars($notif['message']); ?>
                                </div>
                                <div style="margin-top: 0.5rem; font-size: 0.8rem; color: #9ca3af;">
                                    Type: <strong><?php echo htmlspecialchars($notif['type']); ?></strong> | 
                                    Created: <?php echo date('Y-m-d H:i:s', strtotime($notif['created_at'])); ?>
                                </div>
                                <div class="redirect-button-test">
                                    <?php if ($expected_url): ?>
                                        <strong>✓ Should show redirect button</strong><br>
                                        <span class="expected-url">Expected URL: <?php echo htmlspecialchars($expected_url); ?></span>
                                    <?php else: ?>
                                        <span class="no-redirect">No redirect button expected (static notification)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="test-section">
                <h2>✅ Expected Behavior</h2>
                <table class="table table-dark table-striped">
                    <thead>
                        <tr>
                            <th>Notification Type</th>
                            <th>Redirect Button</th>
                            <th>Expected URL</th>
                            <th>Works for Read</th>
                            <th>Works for Unread</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expected_redirects as $type => $url): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($type); ?></code></td>
                                <td>
                                    <?php if ($url): ?>
                                        <span class="badge badge-success">Yes</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($url): ?>
                                        <code style="font-size: 0.85rem;"><?php echo htmlspecialchars($url); ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($url): ?>
                                        <span class="badge badge-success">✓ Yes</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($url): ?>
                                        <span class="badge badge-success">✓ Yes</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="test-section">
                <h3>No Test Notifications</h3>
                <p class="text-muted">Click "Create Test Notifications" to generate test notifications for testing the redirect button functionality.</p>
            </div>
        <?php endif; ?>
        
        <div class="test-section">
            <h2>🔍 Manual Testing Steps</h2>
            <ol>
                <li>Click "Create Test Notifications" button above</li>
                <li>Click on the notification bell icon in the header</li>
                <li>Verify that notifications with redirect URLs show a redirect button (external link icon) instead of bell icon</li>
                <li>Verify that both read and unread notifications show the redirect button</li>
                <li>Click the redirect button on each notification and verify it redirects to the correct page:
                    <ul>
                        <li><strong>task_delay</strong> → Should redirect to <code>my_task.php</code></li>
                        <li><strong>meeting_request/approved/rescheduled</strong> → Should redirect to <code>admin_my_meetings.php</code></li>
                        <li><strong>leave_request/approved/rejected</strong> → Should redirect to <code>leave_request.php</code></li>
                        <li><strong>notes_reminder</strong> → Should redirect to <code>my_notes.php</code></li>
                        <li><strong>day_special</strong> → Should show bell icon (no redirect button)</li>
                    </ul>
                </li>
                <li>Verify that clicking the redirect button marks unread notifications as read</li>
            </ol>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set current user type for notifications
        window.currentUserType = '<?php echo htmlspecialchars($user_type, ENT_QUOTES); ?>';
    </script>
    <script src="../assets/js/notifications.js?v=<?php echo filemtime('../assets/js/notifications.js'); ?>"></script>
</body>
</html>

