<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/notification_triggers.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

$page_title = 'Test Notification Actions';
require_once '../includes/header.php';

$user_id = $_SESSION['id'] ?? null;
$user_type = $_SESSION['user_type'] ?? 'doer';
$user_name = $_SESSION['name'] ?? 'User';
$message = '';
$error = '';

// Get user info for testing
$user_query = "SELECT id, name, email, user_type FROM users WHERE id = ?";
$user_stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($user_stmt, 'i', $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$current_user = mysqli_fetch_assoc($user_result);

// Get all users for testing different roles
$all_users_query = "SELECT id, name, email, user_type FROM users ORDER BY user_type, name LIMIT 10";
$all_users_result = mysqli_query($conn, $all_users_query);
$all_users = [];
while ($row = mysqli_fetch_assoc($all_users_result)) {
    $all_users[] = $row;
}

// Get admin user
$admin_user = null;
foreach ($all_users as $u) {
    if ($u['user_type'] === 'admin') {
        $admin_user = $u;
        break;
    }
}

// Get manager user
$manager_user = null;
foreach ($all_users as $u) {
    if ($u['user_type'] === 'manager') {
        $manager_user = $u;
        break;
    }
}

// Get doer user
$doer_user = null;
foreach ($all_users as $u) {
    if ($u['user_type'] === 'doer') {
        $doer_user = $u;
        break;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_all_test_notifications':
            $result = createAllTestNotifications($conn, $user_id, $all_users, $admin_user, $manager_user, $doer_user);
            if ($result['success']) {
                $message = "All test notifications created successfully! Created {$result['count']} notifications.";
            } else {
                $error = "Failed to create notifications: " . $result['error'];
            }
            break;
        
        case 'clear_all':
            $clear_query = "DELETE FROM notifications";
            mysqli_query($conn, $clear_query);
            $message = 'All notifications cleared!';
            break;
        
        case 'create_meeting_request':
            if ($doer_user && $admin_user) {
                // Create a mock meeting request
                $meeting_query = "INSERT INTO meeting_requests (doer_name, doer_email, preferred_date, preferred_time, status, created_at) 
                                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY), '10:00:00', 'Pending', NOW())";
                $meeting_stmt = mysqli_prepare($conn, $meeting_query);
                mysqli_stmt_bind_param($meeting_stmt, 'ss', $doer_user['name'], $doer_user['email']);
                mysqli_stmt_execute($meeting_stmt);
                $meeting_id = mysqli_insert_id($conn);
                
                // Trigger notification
                triggerMeetingRequestNotification($conn, $admin_user['id'], $doer_user['name']);
                $message = "Meeting request notification created for admin!";
            } else {
                $error = "Need doer and admin users for this test";
            }
            break;
        
        case 'create_leave_request':
            if ($doer_user && $manager_user) {
                // Create a mock leave request
                $leave_id = 'TEST-' . time();
                $leave_query = "INSERT INTO Leave_request (unique_service_no, employee_name, employee_email, leave_type, start_date, end_date, status, created_at) 
                               VALUES (?, ?, ?, 'Sick Leave', DATE_ADD(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 2 DAY), 'Pending', NOW())";
                $leave_stmt = mysqli_prepare($conn, $leave_query);
                mysqli_stmt_bind_param($leave_stmt, 'sss', $leave_id, $doer_user['name'], $doer_user['email']);
                mysqli_stmt_execute($leave_stmt);
                
                // Trigger notification
                $action_data = json_encode([
                    'actions' => [
                        ['type' => 'approve_leave', 'label' => 'Approve', 'icon' => 'fa-check', 'color' => 'success'],
                        ['type' => 'reject_leave', 'label' => 'Reject', 'icon' => 'fa-times', 'color' => 'danger']
                    ]
                ]);
                
                createNotification($conn, $manager_user['id'], 'leave_request',
                    'Leave Request',
                    "{$doer_user['name']} has applied for leave",
                    $leave_id, 'leave', true, $action_data);
                
                $message = "Leave request notification created for manager!";
            } else {
                $error = "Need doer and manager users for this test";
            }
            break;
        
        case 'trigger_day_special':
            triggerDaySpecialNotifications($conn);
            $message = 'Day special notifications triggered!';
            break;
        
        case 'trigger_task_delay':
            checkTaskDelayWarnings($conn);
            $message = 'Task delay warnings checked!';
            break;
    }
}

// Get notification counts
$total_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
$total_stmt = mysqli_prepare($conn, $total_query);
mysqli_stmt_bind_param($total_stmt, 'i', $user_id);
mysqli_stmt_execute($total_stmt);
$total_result = mysqli_stmt_get_result($total_stmt);
$total_count = mysqli_fetch_assoc($total_result)['total'] ?? 0;

$unread_query = "SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0";
$unread_stmt = mysqli_prepare($conn, $unread_query);
mysqli_stmt_bind_param($unread_stmt, 'i', $user_id);
mysqli_stmt_execute($unread_stmt);
$unread_result = mysqli_stmt_get_result($unread_stmt);
$unread_count = mysqli_fetch_assoc($unread_result)['unread'] ?? 0;

function createAllTestNotifications($conn, $current_user_id, $all_users, $admin_user, $manager_user, $doer_user) {
    $count = 0;
    $errors = [];
    
    try {
        // 1. Task Delay Warning
        if ($doer_user) {
            $action_data = null; // No actions for task delay
            $result = createNotification($conn, $doer_user['id'], 'task_delay',
                'Task Delay Warning',
                'Task #123 may get delayed soon. Planned time: ' . date('H:i'),
                123, 'task', false, $action_data);
            if ($result) $count++;
        }
        
        // 2. Meeting Request (for admin)
        if ($admin_user && $doer_user) {
            $meeting_id = 999; // Mock meeting ID
            $action_data = json_encode([
                'actions' => [
                    ['type' => 'approve_meeting', 'label' => 'Approve', 'icon' => 'fa-check', 'color' => 'success'],
                    ['type' => 'reschedule_meeting', 'label' => 'Reschedule', 'icon' => 'fa-calendar-alt', 'color' => 'primary']
                ]
            ]);
            $result = createNotification($conn, $admin_user['id'], 'meeting_request',
                'New Meeting Request',
                "New meeting request received from {$doer_user['name']}",
                $meeting_id, 'meeting', true, $action_data);
            if ($result) $count++;
        }
        
        // 3. Meeting Approved (for user)
        if ($doer_user) {
            $meeting_id = 999;
            $result = createNotification($conn, $doer_user['id'], 'meeting_approved',
                'Meeting Approved',
                'Your meeting has been approved. Scheduled for ' . date('d/m/Y') . ' at 10:00 AM',
                $meeting_id, 'meeting', false, null);
            if ($result) $count++;
        }
        
        // 4. Meeting Rescheduled (for user)
        if ($doer_user) {
            $meeting_id = 999;
            $result = createNotification($conn, $doer_user['id'], 'meeting_rescheduled',
                'Meeting Rescheduled',
                'Your meeting has been rescheduled to ' . date('d/m/Y', strtotime('+2 days')) . ' at 2:00 PM',
                $meeting_id, 'meeting', false, null);
            if ($result) $count++;
        }
        
        // 5. Day Special - Birthday
        foreach ($all_users as $user) {
            $result = createNotification($conn, $user['id'], 'day_special',
                'Birthday Celebration',
                "Today is {$user['name']}'s Birthday 🎉",
                null, 'day_special', false, null);
            if ($result) $count++;
        }
        
        // 6. Day Special - Work Anniversary
        foreach ($all_users as $user) {
            $result = createNotification($conn, $user['id'], 'day_special',
                'Work Anniversary',
                "Today is {$user['name']}'s Work Anniversary 🎉",
                null, 'day_special', false, null);
            if ($result) $count++;
        }
        
        // 7. Notes Reminder
        if ($doer_user) {
            $result = createNotification($conn, $doer_user['id'], 'notes_reminder',
                'Note Reminder',
                'Reminder: Important Meeting Notes',
                null, 'note', false, null);
            if ($result) $count++;
        }
        
        // 8. Leave Request (for manager)
        if ($manager_user && $doer_user) {
            $leave_id = 'TEST-' . time();
            $action_data = json_encode([
                'actions' => [
                    ['type' => 'approve_leave', 'label' => 'Approve', 'icon' => 'fa-check', 'color' => 'success'],
                    ['type' => 'reject_leave', 'label' => 'Reject', 'icon' => 'fa-times', 'color' => 'danger']
                ]
            ]);
            $result = createNotification($conn, $manager_user['id'], 'leave_request',
                'Leave Request',
                "{$doer_user['name']} has applied for leave",
                $leave_id, 'leave', true, $action_data);
            if ($result) $count++;
        }
        
        // 9. Leave Approved (for user)
        if ($doer_user) {
            $leave_id = 'TEST-APPROVED';
            $result = createNotification($conn, $doer_user['id'], 'leave_approved',
                'Leave Approved',
                'Your leave has been approved',
                $leave_id, 'leave', false, null);
            if ($result) $count++;
        }
        
        // 10. Leave Rejected (for user)
        if ($doer_user) {
            $leave_id = 'TEST-REJECTED';
            $result = createNotification($conn, $doer_user['id'], 'leave_rejected',
                'Leave Rejected',
                'Your leave has been rejected',
                $leave_id, 'leave', false, null);
            if ($result) $count++;
        }
        
        return ['success' => true, 'count' => $count, 'errors' => $errors];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage(), 'count' => $count];
    }
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <h2 class="mb-4">Test Notification Actions</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Notification Stats -->
            <div class="card mb-4" style="background: var(--glass-bg); border: 1px solid var(--glass-border);">
                <div class="card-body">
                    <h5 class="card-title">Notification Statistics</h5>
                    <p class="mb-0">
                        <strong>Total Notifications:</strong> <?php echo $total_count; ?><br>
                        <strong>Unread Notifications:</strong> <span class="text-warning"><?php echo $unread_count; ?></span>
                    </p>
                </div>
            </div>
            
            <!-- Test Actions -->
            <div class="card mb-4" style="background: var(--glass-bg); border: 1px solid var(--glass-border);">
                <div class="card-header">
                    <h5 class="mb-0">Create Test Notifications</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="mb-3">
                        <input type="hidden" name="action" value="create_all_test_notifications">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Create All Test Notifications
                        </button>
                        <small class="d-block text-muted mt-2">
                            Creates one notification of each type with proper action buttons
                        </small>
                    </form>
                    
                    <hr>
                    
                    <h6>Individual Notification Tests</h6>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <form method="POST" class="mb-2">
                                <input type="hidden" name="action" value="create_meeting_request">
                                <button type="submit" class="btn btn-info btn-sm w-100">
                                    <i class="fas fa-calendar-plus"></i> Create Meeting Request (Admin)
                                </button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="POST" class="mb-2">
                                <input type="hidden" name="action" value="create_leave_request">
                                <button type="submit" class="btn btn-warning btn-sm w-100">
                                    <i class="fas fa-calendar-times"></i> Create Leave Request (Manager)
                                </button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="POST" class="mb-2">
                                <input type="hidden" name="action" value="trigger_day_special">
                                <button type="submit" class="btn btn-danger btn-sm w-100">
                                    <i class="fas fa-birthday-cake"></i> Trigger Day Special
                                </button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="POST" class="mb-2">
                                <input type="hidden" name="action" value="trigger_task_delay">
                                <button type="submit" class="btn btn-warning btn-sm w-100">
                                    <i class="fas fa-exclamation-triangle"></i> Check Task Delays
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Clear All -->
            <div class="card mb-4" style="background: var(--glass-bg); border: 1px solid var(--glass-border);">
                <div class="card-body">
                    <form method="POST" onsubmit="return confirm('Are you sure you want to clear ALL notifications?');">
                        <input type="hidden" name="action" value="clear_all">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Clear All Notifications
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Test Instructions -->
            <div class="card" style="background: var(--glass-bg); border: 1px solid var(--glass-border);">
                <div class="card-header">
                    <h5 class="mb-0">Testing Instructions</h5>
                </div>
                <div class="card-body">
                    <ol>
                        <li><strong>Click "Create All Test Notifications"</strong> to generate all notification types</li>
                        <li><strong>Click the bell icon</strong> in the header to open the notification dropdown</li>
                        <li><strong>Test Action Buttons:</strong>
                            <ul>
                                <li>For <strong>Meeting Requests</strong> (Admin): Click "Approve" or "Reschedule" buttons</li>
                                <li>For <strong>Leave Requests</strong> (Manager): Click "Approve" or "Reject" buttons</li>
                                <li>Verify that buttons show loading states and update the UI</li>
                                <li>Check that success/error messages appear in the dropdown</li>
                            </ul>
                        </li>
                        <li><strong>Test Reschedule:</strong>
                            <ul>
                                <li>Click "Reschedule" on a meeting request notification</li>
                                <li>An inline date-time picker should appear</li>
                                <li>Select a new date and time, then click "Reschedule"</li>
                                <li>Verify the meeting is rescheduled and notification updates</li>
                            </ul>
                        </li>
                        <li><strong>Test Audio:</strong>
                            <ul>
                                <li>Create new notifications and verify sound plays</li>
                                <li>Sound should only play for NEW notifications (not when refreshing)</li>
                            </ul>
                        </li>
                        <li><strong>Test UI/UX:</strong>
                            <ul>
                                <li>Verify smooth dropdown animations</li>
                                <li>Check proper spacing and dark theme alignment</li>
                                <li>Ensure action buttons are aligned on the right</li>
                                <li>Test scrolling when notifications exceed container height</li>
                                <li>Verify hover tooltips on action buttons</li>
                            </ul>
                        </li>
                    </ol>
                    
                    <div class="alert alert-info mt-3">
                        <strong>Current User:</strong> <?php echo htmlspecialchars($current_user['name'] ?? 'Unknown'); ?> 
                        (<?php echo htmlspecialchars($current_user['user_type'] ?? 'unknown'); ?>)<br>
                        <strong>Available Test Users:</strong>
                        <?php if ($admin_user): ?>Admin: <?php echo htmlspecialchars($admin_user['name']); ?> | <?php endif; ?>
                        <?php if ($manager_user): ?>Manager: <?php echo htmlspecialchars($manager_user['name']); ?> | <?php endif; ?>
                        <?php if ($doer_user): ?>Doer: <?php echo htmlspecialchars($doer_user['name']); ?><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-play notification sound after creating test notifications
$(document).ready(function() {
    <?php if ($message && strpos($message, 'created successfully') !== false): ?>
        // Wait for NotificationsManager to be ready
        setTimeout(function() {
            if (typeof NotificationsManager !== 'undefined' && NotificationsManager.playNotificationSound) {
                NotificationsManager.playNotificationSound();
            }
        }, 500);
    <?php endif; ?>
});
</script>

<?php require_once '../includes/footer.php'; ?>

