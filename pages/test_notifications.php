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

$page_title = 'Test Notifications';
require_once '../includes/header.php';

$user_id = $_SESSION['id'] ?? null;
$user_type = $_SESSION['user_type'] ?? 'doer';
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_test_notifications':
            $result = createTestNotifications($conn, $user_id);
            if ($result['success']) {
                $message = "Test notifications created successfully! Created {$result['count']} notifications.";
            } else {
                $error = "Failed to create notifications: " . $result['error'];
            }
            break;
        
        case 'clear_all':
            $clear_query = "DELETE FROM notifications WHERE user_id = ?";
            $clear_stmt = mysqli_prepare($conn, $clear_query);
            mysqli_stmt_bind_param($clear_stmt, 'i', $user_id);
            mysqli_stmt_execute($clear_stmt);
            $message = 'All notifications cleared!';
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

function createTestNotifications($conn, $user_id) {
    $created_count = 0;
    $errors = [];
    
    // 1. Task Delay Warning
    $result = createNotification(
        $conn,
        $user_id,
        'task_delay',
        'Task Delay Warning',
        'Task may get delayed soon. Task ID: 123, Planned time: 02:30 PM',
        123,
        'task',
        false,
        null
    );
    if ($result) {
        $created_count++;
    } else {
        $errors[] = "Failed to create task_delay notification: " . mysqli_error($conn);
    }
    
    // 2. Meeting Request (for admin)
    if ($_SESSION['user_type'] === 'admin') {
        $result = createNotification(
            $conn,
            $user_id,
            'meeting_request',
            'New Meeting Request',
            'New meeting request received from John Doe',
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
    }
    
    // 3. Meeting Approved
    $result = createNotification(
        $conn,
        $user_id,
        'meeting_approved',
        'Meeting Approved',
        'Your meeting has been approved. Scheduled for 15/12/2024 at 10:00 AM',
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
    
    // 4. Meeting Rescheduled
    $result = createNotification(
        $conn,
        $user_id,
        'meeting_rescheduled',
        'Meeting Rescheduled',
        'Your meeting has been rescheduled to 20/12/2024 at 02:00 PM',
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
    
    // 5. Day Special - Birthday
    $result = createNotification(
        $conn,
        $user_id,
        'day_special',
        'Birthday Today 🎉',
        "Today is Jane Smith's Birthday 🎉",
        5,
        'user',
        false,
        null
    );
    if ($result) {
        $created_count++;
    } else {
        $errors[] = "Failed to create day_special (birthday) notification";
    }
    
    // 6. Day Special - Work Anniversary
    $result = createNotification(
        $conn,
        $user_id,
        'day_special',
        'Work Anniversary 🎉',
        "Today is John Doe's Work Anniversary 🎉 (3 years)",
        6,
        'user',
        false,
        null
    );
    if ($result) {
        $created_count++;
    } else {
        $errors[] = "Failed to create day_special (anniversary) notification";
    }
    
    // 7. Notes Reminder
    $result = createNotification(
        $conn,
        $user_id,
        'notes_reminder',
        'Reminder',
        'Reminder: Important Meeting Notes',
        10,
        'note',
        false,
        null
    );
    if ($result) {
        $created_count++;
    } else {
        $errors[] = "Failed to create notes_reminder notification";
    }
    
    // 8. Leave Request (for manager)
    if ($_SESSION['user_type'] === 'manager' || $_SESSION['user_type'] === 'admin') {
        $result = createNotification(
            $conn,
            $user_id,
            'leave_request',
            'Leave Request',
            'John Doe has applied for leave',
            'LEAVE001',
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
    }
    
    // 9. Leave Approved
    $result = createNotification(
        $conn,
        $user_id,
        'leave_approved',
        'Leave Approved',
        'Your leave has been approved',
        'LEAVE001',
        'leave',
        false,
        null
    );
    if ($result) {
        $created_count++;
    } else {
        $errors[] = "Failed to create leave_approved notification";
    }
    
    // 10. Leave Rejected
    $result = createNotification(
        $conn,
        $user_id,
        'leave_rejected',
        'Leave Rejected',
        'Your leave has been rejected',
        'LEAVE002',
        'leave',
        false,
        null
    );
    if ($result) {
        $created_count++;
    } else {
        $errors[] = "Failed to create leave_rejected notification";
    }
    
    return [
        'success' => $created_count > 0,
        'count' => $created_count,
        'error' => !empty($errors) ? implode(', ', $errors) : null
    ];
}

// Get current notifications count
$count_query = "SELECT COUNT(*) as total, SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread FROM notifications WHERE user_id = ?";
$count_stmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($count_stmt, 'i', $user_id);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$counts = mysqli_fetch_assoc($count_result);
?>

<style>
.test-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 2rem;
}

.test-section {
    background: var(--dark-bg-card);
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid var(--glass-border);
}

.test-section h3 {
    color: var(--brand-primary);
    margin-bottom: 1rem;
    font-size: 1.3rem;
}

.test-section p {
    color: var(--dark-text-secondary);
    margin-bottom: 1rem;
}

.btn-test {
    background: var(--brand-primary);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
    margin-right: 0.5rem;
    margin-bottom: 0.5rem;
}

.btn-test:hover {
    background: var(--brand-primary-hover);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.btn-test.danger {
    background: #ef4444;
}

.btn-test.danger:hover {
    background: #dc2626;
}

.alert {
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1rem;
}

.alert-success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid #22c55e;
    color: #22c55e;
}

.alert-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid #ef4444;
    color: #ef4444;
}

.stats {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.stat-card {
    flex: 1;
    background: var(--dark-bg);
    padding: 1rem;
    border-radius: 6px;
    border: 1px solid var(--glass-border);
    text-align: center;
}

.stat-card .stat-value {
    font-size: 2rem;
    font-weight: 600;
    color: var(--brand-primary);
}

.stat-card .stat-label {
    font-size: 0.85rem;
    color: var(--dark-text-secondary);
    margin-top: 0.5rem;
}
</style>

<div class="container-fluid">
    <div class="test-container">
        <h1 class="mb-4">Test Notifications System</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success" id="successMessage" data-message="<?php echo htmlspecialchars($message); ?>">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-value"><?php echo $counts['total'] ?? 0; ?></div>
                <div class="stat-label">Total Notifications</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $counts['unread'] ?? 0; ?></div>
                <div class="stat-label">Unread Notifications</div>
            </div>
        </div>
        
        <!-- Debug Info -->
        <?php if (isset($_GET['debug'])): ?>
        <div class="test-section" style="background: #1a1a1a; border: 1px solid #444;">
            <h3><i class="fas fa-bug"></i> Debug Information</h3>
            <p><strong>User ID:</strong> <?php echo $user_id; ?></p>
            <p><strong>User Type:</strong> <?php echo $user_type; ?></p>
            <?php
            // Check if table exists
            $table_check = "SHOW TABLES LIKE 'notifications'";
            $table_result = mysqli_query($conn, $table_check);
            $table_exists = mysqli_num_rows($table_result) > 0;
            ?>
            <p><strong>Notifications Table Exists:</strong> <?php echo $table_exists ? 'Yes' : 'No'; ?></p>
            <?php if ($table_exists): ?>
                <?php
                // Check columns
                $cols_query = "SHOW COLUMNS FROM notifications";
                $cols_result = mysqli_query($conn, $cols_query);
                $columns = [];
                while ($col = mysqli_fetch_assoc($cols_result)) {
                    $columns[] = $col['Field'] . ' (' . $col['Type'] . ')';
                }
                ?>
                <p><strong>Columns:</strong></p>
                <ul style="color: var(--dark-text-secondary); font-family: monospace; font-size: 0.85rem;">
                    <?php foreach ($columns as $col): ?>
                        <li><?php echo htmlspecialchars($col); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Test Actions -->
        <div class="test-section">
            <h3><i class="fas fa-flask"></i> Test Actions</h3>
            <p>Click the buttons below to generate test notifications of different types.</p>
            
            <form method="POST" id="createTestNotificationsForm" style="display: inline;">
                <input type="hidden" name="action" value="create_test_notifications">
                <button type="submit" class="btn-test">
                    <i class="fas fa-plus-circle"></i> Create All Test Notifications
                </button>
            </form>
            
            <a href="?debug=1" class="btn-test" style="text-decoration: none; display: inline-block;">
                <i class="fas fa-bug"></i> Show Debug Info
            </a>
            
            <a href="setup_notifications_database.php" class="btn-test" style="text-decoration: none; display: inline-block; background: #10b981;">
                <i class="fas fa-database"></i> Setup Database
            </a>
            
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="trigger_day_special">
                <button type="submit" class="btn-test">
                    <i class="fas fa-birthday-cake"></i> Trigger Day Special Notifications
                </button>
            </form>
            
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="trigger_task_delay">
                <button type="submit" class="btn-test">
                    <i class="fas fa-exclamation-triangle"></i> Check Task Delay Warnings
                </button>
            </form>
            
            <a href="test_checklist_overdue.php" class="btn-test" style="text-decoration: none; display: inline-block; background: #8b5cf6;">
                <i class="fas fa-clipboard-check"></i> Test Checklist Overdue Notifications
            </a>
            
            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to clear ALL notifications?');">
                <input type="hidden" name="action" value="clear_all">
                <button type="submit" class="btn-test danger">
                    <i class="fas fa-trash"></i> Clear All Notifications
                </button>
            </form>
            
            <button type="button" class="btn-test" onclick="testNotificationSound()" style="background: #8b5cf6;">
                <i class="fas fa-volume-up"></i> Test Audio Sound
            </button>
        </div>
        
        <!-- Notification Types -->
        <div class="test-section">
            <h3><i class="fas fa-list"></i> Notification Types</h3>
            <p>The following notification types are supported:</p>
            <ul style="color: var(--dark-text-secondary); line-height: 2;">
                <li><strong>Task Delay Warning</strong> - 5 minutes before a task becomes delayed</li>
                <li><strong>Meeting Request</strong> - When a user books a meeting (Admin receives)</li>
                <li><strong>Meeting Approved</strong> - When admin approves a meeting (User receives)</li>
                <li><strong>Meeting Rescheduled</strong> - When admin reschedules a meeting (User receives)</li>
                <li><strong>Day Special</strong> - Birthdays and work anniversaries (All users receive)</li>
                <li><strong>Notes Reminder</strong> - When a note reminder is triggered</li>
                <li><strong>Leave Request</strong> - When a user applies for leave (Manager receives)</li>
                <li><strong>Leave Approved</strong> - When leave is approved (User receives)</li>
                <li><strong>Leave Rejected</strong> - When leave is rejected (User receives)</li>
                <li><strong>Checklist Overdue</strong> - When a checklist task is not completed on its due date (Manager receives)</li>
            </ul>
        </div>
        
        <!-- Action Buttons -->
        <div class="test-section">
            <h3><i class="fas fa-mouse-pointer"></i> Action Buttons</h3>
            <p>Action buttons are displayed for notifications that require user interaction:</p>
            <ul style="color: var(--dark-text-secondary); line-height: 2;">
                <li><strong>Meeting Request (Admin)</strong> - Approve / Re-schedule buttons</li>
                <li><strong>Leave Request (Manager)</strong> - Approve / Reject buttons</li>
                <li>All buttons have hover tooltips and smooth animations</li>
            </ul>
        </div>
        
        <!-- Testing Instructions -->
        <div class="test-section">
            <h3><i class="fas fa-info-circle"></i> Testing Instructions</h3>
            <ol style="color: var(--dark-text-secondary); line-height: 2;">
                <li>Click "Create All Test Notifications" to generate sample notifications</li>
                <li>Click the bell icon in the header to view notifications</li>
                <li>Test action buttons (Approve/Reject/Re-schedule) on relevant notifications</li>
                <li>Check that notification sound plays when new notifications arrive</li>
                <li>Verify that unread count badge appears on the bell icon</li>
                <li>Test "Mark all as read" functionality</li>
                <li>Verify timestamps display correctly (Just now, 2 mins ago, Yesterday, etc.)</li>
            </ol>
        </div>
    </div>
</div>

<script>
function testNotificationSound() {
    if (typeof NotificationsManager !== 'undefined') {
        console.log('Testing notification sound...');
        NotificationsManager.playNotificationSound();
        
        // Show feedback
        const btn = event.target.closest('button');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Sound Tested';
        btn.style.background = '#22c55e';
        
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.style.background = '#8b5cf6';
        }, 2000);
    } else {
        alert('NotificationsManager not loaded. Please refresh the page.');
    }
}

// Handle form submission and play sound after notifications are created
$(document).ready(function() {
    // Play sound if we just created notifications (check for success message)
    const successMessage = $('#successMessage');
    if (successMessage.length > 0) {
        // Get message text from data attribute or text content
        const messageText = successMessage.data('message') || successMessage.text() || '';
        console.log('Success message detected:', messageText);
        
        if (messageText && messageText.toLowerCase().includes('test notifications created')) {
            console.log('✓ Detected notification creation success, will play sound...');
            
            // Wait a bit for NotificationsManager to be ready, then play sound
            let retryCount = 0;
            const maxRetries = 10;
            
            const playSound = function() {
                if (typeof NotificationsManager !== 'undefined') {
                    console.log('✓ Playing notification sound after creating test notifications...');
                    NotificationsManager.playNotificationSound();
                } else {
                    retryCount++;
                    if (retryCount < maxRetries) {
                        console.log('NotificationsManager not available yet, retrying... (' + retryCount + '/' + maxRetries + ')');
                        setTimeout(playSound, 300);
                    } else {
                        console.error('NotificationsManager not available after ' + maxRetries + ' retries');
                    }
                }
            };
            
            // Start trying to play after a short delay to ensure page is fully loaded
            setTimeout(playSound, 800);
        }
    }
    
    // Log audio status on page load
    setTimeout(function() {
        if (typeof NotificationsManager !== 'undefined') {
            console.log('=== Audio Debug Info ===');
            console.log('Audio enabled:', NotificationsManager.audioEnabled);
            console.log('Audio element:', NotificationsManager.audioElement);
            if (NotificationsManager.audioElement) {
                console.log('Audio src:', NotificationsManager.audioElement.src);
                console.log('Audio readyState:', NotificationsManager.audioElement.readyState);
            }
        }
    }, 1000);
});
</script>

<?php require_once '../includes/footer.php'; ?>

