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

$page_title = 'Test Leave Notifications';
require_once '../includes/header.php';

$user_id = $_SESSION['id'] ?? null;
$message = '';
$error = '';
$debug_info = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'test_leave_notification':
            // Manually test leave notification
            $employee_name = trim($_POST['employee_name'] ?? '');
            $leave_id = trim($_POST['leave_id'] ?? 'TEST-' . time());
            $manager_email = trim($_POST['manager_email'] ?? '');
            $manager_name = trim($_POST['manager_name'] ?? '');
            
            if (empty($employee_name)) {
                $error = 'Please provide an employee name.';
            } else {
                // Test the function with detailed logging
                ob_start();
                
                // Check if employee exists
                $employee_query = "SELECT id, username, name, manager_id, manager FROM users WHERE name = ? OR username = ? LIMIT 1";
                $employee_stmt = mysqli_prepare($conn, $employee_query);
                $employee_found = null;
                
                if ($employee_stmt) {
                    mysqli_stmt_bind_param($employee_stmt, 'ss', $employee_name, $employee_name);
                    mysqli_stmt_execute($employee_stmt);
                    $employee_result = mysqli_stmt_get_result($employee_stmt);
                    $employee_found = mysqli_fetch_assoc($employee_result);
                    mysqli_stmt_close($employee_stmt);
                }
                
                $debug_info['employee_search'] = $employee_found;
                
                // Call the notification function
                triggerLeaveRequestNotification($conn, $leave_id, $employee_name, $manager_email, $manager_name);
                
                $output = ob_get_clean();
                
                // Check if notifications were created
                $notif_check = "SELECT n.id, n.user_id, n.title, n.message, n.created_at, u.name as manager_name
                               FROM notifications n
                               LEFT JOIN users u ON n.user_id = u.id
                               WHERE n.type = 'leave_request' 
                               AND n.related_id = ?
                               ORDER BY n.created_at DESC";
                $notif_stmt = mysqli_prepare($conn, $notif_check);
                $notifications_created = [];
                if ($notif_stmt) {
                    mysqli_stmt_bind_param($notif_stmt, 's', $leave_id);
                    mysqli_stmt_execute($notif_stmt);
                    $notif_result = mysqli_stmt_get_result($notif_stmt);
                    while ($notif = mysqli_fetch_assoc($notif_result)) {
                        $notifications_created[] = $notif;
                    }
                    mysqli_stmt_close($notif_stmt);
                }
                
                $debug_info['notifications_created'] = $notifications_created;
                
                if (!empty($notifications_created)) {
                    $message = "Leave notification test completed! Created " . count($notifications_created) . " notification(s).";
                } else {
                    $error = "No notifications were created. Check debug info below.";
                }
            }
            break;
        
        case 'check_recent_leaves':
            // Check recent leave requests that should have triggered notifications
            $recent_leaves_query = "SELECT unique_service_no, employee_name, manager_name, manager_email, status, created_at
                                  FROM Leave_request
                                  WHERE status IN ('PENDING', 'Pending', '') OR status IS NULL
                                  ORDER BY created_at DESC
                                  LIMIT 20";
            $recent_leaves_result = mysqli_query($conn, $recent_leaves_query);
            $recent_leaves = [];
            if ($recent_leaves_result) {
                while ($leave = mysqli_fetch_assoc($recent_leaves_result)) {
                    // Check if notification exists
                    $notif_check = "SELECT COUNT(*) as count FROM notifications 
                                   WHERE type = 'leave_request' 
                                   AND related_id = ?";
                    $notif_check_stmt = mysqli_prepare($conn, $notif_check);
                    $has_notification = 0;
                    if ($notif_check_stmt) {
                        mysqli_stmt_bind_param($notif_check_stmt, 's', $leave['unique_service_no']);
                        mysqli_stmt_execute($notif_check_stmt);
                        $notif_check_result = mysqli_stmt_get_result($notif_check_stmt);
                        $notif_count = mysqli_fetch_assoc($notif_check_result);
                        $has_notification = $notif_count['count'] ?? 0;
                        mysqli_stmt_close($notif_check_stmt);
                    }
                    $leave['has_notification'] = $has_notification;
                    $recent_leaves[] = $leave;
                }
            }
            $debug_info['recent_leaves'] = $recent_leaves;
            $message = "Found " . count($recent_leaves) . " recent leave request(s).";
            break;
    }
}

// Get list of users with managers (for dropdown)
$users_query = "SELECT id, username, name, manager_id, manager, email 
                FROM users 
                WHERE Status = 'Active' 
                ORDER BY name ASC";
$users_result = mysqli_query($conn, $users_query);
$users = [];
if ($users_result) {
    while ($user = mysqli_fetch_assoc($users_result)) {
        $users[] = $user;
    }
}

// Get recent leave notifications
$recent_notifications_query = "SELECT n.id, n.user_id, n.title, n.message, n.related_id, n.created_at,
                                      u.name as manager_name, u.username as manager_username
                               FROM notifications n
                               LEFT JOIN users u ON n.user_id = u.id
                               WHERE n.type = 'leave_request'
                               ORDER BY n.created_at DESC
                               LIMIT 20";
$recent_notifications_result = mysqli_query($conn, $recent_notifications_query);
$recent_notifications = [];
if ($recent_notifications_result) {
    while ($notif = mysqli_fetch_assoc($recent_notifications_result)) {
        $recent_notifications[] = $notif;
    }
}
?>

<style>
.test-container {
    max-width: 1400px;
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
    display: flex;
    align-items: center;
    gap: 0.5rem;
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

.info-box {
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 6px;
    padding: 1rem;
    margin-bottom: 1rem;
    color: var(--dark-text-primary);
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--dark-text-primary);
    font-weight: 500;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 0.75rem;
    background: var(--dark-bg);
    border: 1px solid var(--glass-border);
    border-radius: 6px;
    color: var(--dark-text-primary);
    font-size: 0.9rem;
}

.table-container {
    overflow-x: auto;
    margin-top: 1rem;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: var(--dark-bg);
    border-radius: 6px;
    overflow: hidden;
}

table th {
    background: rgba(99, 102, 241, 0.2);
    color: var(--brand-primary);
    padding: 0.75rem;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid rgba(99, 102, 241, 0.3);
}

table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--glass-border);
    color: var(--dark-text-primary);
}

table tr:hover {
    background: rgba(99, 102, 241, 0.05);
}

.badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-success {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
}

.badge-danger {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.debug-box {
    background: rgba(251, 191, 36, 0.1);
    border: 1px solid rgba(251, 191, 36, 0.3);
    border-radius: 6px;
    padding: 1rem;
    margin-top: 1rem;
    font-family: monospace;
    font-size: 0.85rem;
    color: var(--dark-text-primary);
    max-height: 400px;
    overflow-y: auto;
}

.debug-box pre {
    margin: 0;
    white-space: pre-wrap;
    word-wrap: break-word;
}
</style>

<div class="container-fluid">
    <div class="test-container">
        <h1 class="mb-4"><i class="fas fa-calendar-times"></i> Test Leave Notifications</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Info Box -->
        <div class="info-box">
            <strong><i class="fas fa-info-circle"></i> How Leave Notifications Work:</strong><br>
            When a new leave request is synced from Google Sheets (with status PENDING), the system:
            <ol style="margin: 0.5rem 0 0 1.5rem; color: var(--dark-text-secondary);">
                <li>Finds the employee by name/username</li>
                <li>Gets their manager (from manager_id or manager field)</li>
                <li>Sends notification to: Direct manager, All admins, and Managers who have the employee in their team</li>
                <li>Prevents duplicate notifications (checks if unread notification already exists)</li>
            </ol>
        </div>
        
        <!-- Test Leave Notification -->
        <div class="test-section">
            <h3><i class="fas fa-flask"></i> Test Leave Notification</h3>
            
            <form method="POST">
                <input type="hidden" name="action" value="test_leave_notification">
                
                <div class="form-group">
                    <label for="employee_name"><i class="fas fa-user"></i> Employee Name/Username *</label>
                    <select name="employee_name" id="employee_name" required>
                        <option value="">Select an employee...</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo htmlspecialchars($user['name']); ?>">
                                <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)
                                <?php if ($user['manager_id'] || $user['manager']): ?>
                                    - Manager: <?php echo htmlspecialchars($user['manager'] ?? 'ID: ' . $user['manager_id']); ?>
                                <?php else: ?>
                                    <span style="color: #ef4444;"> - NO MANAGER</span>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="leave_id"><i class="fas fa-id-card"></i> Leave ID (optional)</label>
                    <input type="text" name="leave_id" id="leave_id" placeholder="Leave blank to auto-generate" value="TEST-<?php echo time(); ?>">
                </div>
                
                <div class="form-group">
                    <label for="manager_name"><i class="fas fa-user-tie"></i> Manager Name (optional - override)</label>
                    <input type="text" name="manager_name" id="manager_name" placeholder="Leave blank to use employee's manager">
                </div>
                
                <div class="form-group">
                    <label for="manager_email"><i class="fas fa-envelope"></i> Manager Email (optional - override)</label>
                    <input type="email" name="manager_email" id="manager_email" placeholder="Leave blank to use employee's manager">
                </div>
                
                <button type="submit" class="btn-test">
                    <i class="fas fa-paper-plane"></i> Test Leave Notification
                </button>
            </form>
            
            <?php if (!empty($debug_info)): ?>
                <div class="debug-box">
                    <strong>Debug Information:</strong>
                    <pre><?php echo htmlspecialchars(print_r($debug_info, true)); ?></pre>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Check Recent Leaves -->
        <div class="test-section">
            <h3><i class="fas fa-search"></i> Check Recent Leave Requests</h3>
            <p style="color: var(--dark-text-secondary); margin-bottom: 1rem;">
                Check recent leave requests and see if they have notifications.
            </p>
            
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="check_recent_leaves">
                <button type="submit" class="btn-test">
                    <i class="fas fa-search"></i> Check Recent Leaves
                </button>
            </form>
            
            <?php if (isset($debug_info['recent_leaves'])): ?>
                <div class="table-container" style="margin-top: 1rem;">
                    <table>
                        <thead>
                            <tr>
                                <th>Leave ID</th>
                                <th>Employee</th>
                                <th>Manager</th>
                                <th>Status</th>
                                <th>Has Notification</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($debug_info['recent_leaves'] as $leave): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($leave['unique_service_no']); ?></td>
                                    <td><?php echo htmlspecialchars($leave['employee_name']); ?></td>
                                    <td><?php echo htmlspecialchars($leave['manager_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($leave['status'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($leave['has_notification'] > 0): ?>
                                            <span class="badge badge-success">Yes (<?php echo $leave['has_notification']; ?>)</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($leave['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Recent Notifications -->
        <div class="test-section">
            <h3><i class="fas fa-bell"></i> Recent Leave Notifications</h3>
            <p style="color: var(--dark-text-secondary); margin-bottom: 1rem;">
                Leave request notifications that were created.
            </p>
            
            <?php if (empty($recent_notifications)): ?>
                <p style="color: var(--dark-text-secondary); text-align: center; padding: 2rem;">
                    <i class="fas fa-bell-slash" style="font-size: 2rem; opacity: 0.5; margin-bottom: 1rem; display: block;"></i>
                    No leave notifications found.
                </p>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Notification ID</th>
                                <th>Manager</th>
                                <th>Title</th>
                                <th>Message</th>
                                <th>Leave ID</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_notifications as $notif): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($notif['id']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($notif['manager_name'] ?? $notif['manager_username'] ?? 'N/A'); ?>
                                        <br><small style="color: var(--dark-text-secondary);">(ID: <?php echo htmlspecialchars($notif['user_id']); ?>)</small>
                                    </td>
                                    <td><?php echo htmlspecialchars($notif['title']); ?></td>
                                    <td><?php echo htmlspecialchars($notif['message']); ?></td>
                                    <td><?php echo htmlspecialchars($notif['related_id']); ?></td>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($notif['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Troubleshooting -->
        <div class="test-section">
            <h3><i class="fas fa-tools"></i> Troubleshooting</h3>
            <p style="color: var(--dark-text-secondary); margin-bottom: 1rem;">
                Common issues and solutions:
            </p>
            <ul style="color: var(--dark-text-secondary); line-height: 2;">
                <li><strong>Employee not found:</strong> Make sure the employee name in the leave request matches the name or username in the users table</li>
                <li><strong>No manager assigned:</strong> The employee must have a manager_id or manager field set in the users table</li>
                <li><strong>Notification already exists:</strong> The system prevents duplicate notifications. If a notification was already created (even if read), it won't create a new one</li>
                <li><strong>Leave sync not running:</strong> Check if the leave sync script is running (ajax/leave_auto_sync.php or scripts/smart_cron_leave_sync.php)</li>
                <li><strong>Status not PENDING:</strong> Notifications are only sent for new leave requests with status PENDING, empty, or NULL</li>
            </ul>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

