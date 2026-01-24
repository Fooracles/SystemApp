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

$page_title = 'Test Checklist Overdue Notifications';
require_once '../includes/header.php';

$user_id = $_SESSION['id'] ?? null;
$message = '';
$error = '';
$test_results = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'trigger_check':
            // Manually trigger the overdue checklist check
            ob_start();
            checkOverdueChecklistTasks($conn);
            $output = ob_get_clean();
            
            $message = 'Overdue checklist tasks check completed! Check the results below.';
            break;
        
        case 'create_test_task':
            // Create a test checklist task with yesterday's date
            $assignee_username = trim($_POST['assignee_username'] ?? '');
            $task_description = trim($_POST['task_description'] ?? 'Test Overdue Checklist Task');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            
            if (empty($assignee_username)) {
                $error = 'Please provide an assignee username.';
            } else {
                // Verify user exists
                $user_check = "SELECT id, username, name, manager_id, manager FROM users WHERE username = ? LIMIT 1";
                $user_stmt = mysqli_prepare($conn, $user_check);
                if ($user_stmt) {
                    mysqli_stmt_bind_param($user_stmt, 's', $assignee_username);
                    mysqli_stmt_execute($user_stmt);
                    $user_result = mysqli_stmt_get_result($user_stmt);
                    $user = mysqli_fetch_assoc($user_result);
                    mysqli_stmt_close($user_stmt);
                    
                    if (!$user) {
                        $error = "User with username '{$assignee_username}' not found.";
                    } else {
                        // Create test task
                        $task_code = 'TEST-' . strtoupper(substr(md5($task_description . $yesterday . $assignee_username), 0, 8));
                        $insert_sql = "INSERT INTO checklist_subtasks (task_code, assignee, task_description, task_date, status, assigned_by, doer_id, doer_name) 
                                     VALUES (?, ?, ?, ?, 'pending', 'Test System', ?, ?)";
                        $insert_stmt = mysqli_prepare($conn, $insert_sql);
                        
                        if ($insert_stmt) {
                            mysqli_stmt_bind_param($insert_stmt, 'ssssis', 
                                $task_code,
                                $assignee_username,
                                $task_description,
                                $yesterday,
                                $user['id'],
                                $assignee_username
                            );
                            
                            if (mysqli_stmt_execute($insert_stmt)) {
                                $task_id = mysqli_insert_id($conn);
                                $message = "Test checklist task created successfully! Task ID: {$task_id}, Due Date: {$yesterday}";
                            } else {
                                $error = "Failed to create test task: " . mysqli_error($conn);
                            }
                            mysqli_stmt_close($insert_stmt);
                        } else {
                            $error = "Failed to prepare insert statement: " . mysqli_error($conn);
                        }
                    }
                } else {
                    $error = "Failed to check user: " . mysqli_error($conn);
                }
            }
            break;
        
        case 'clear_test_tasks':
            // Clear test tasks (those with assigned_by = 'Test System')
            $delete_sql = "DELETE FROM checklist_subtasks WHERE assigned_by = 'Test System'";
            if (mysqli_query($conn, $delete_sql)) {
                $deleted_count = mysqli_affected_rows($conn);
                $message = "Cleared {$deleted_count} test checklist task(s).";
            } else {
                $error = "Failed to clear test tasks: " . mysqli_error($conn);
            }
            break;
    }
}

// Get yesterday's date
$yesterday = date('Y-m-d', strtotime('-1 day'));

// Get overdue checklist tasks (for display)
$overdue_query = "SELECT cs.id, cs.task_code, cs.task_description, cs.task_date, cs.assignee, cs.status,
                         u.name as assignee_name, u.manager_id, u.manager as manager_name
                  FROM checklist_subtasks cs
                  LEFT JOIN users u ON cs.assignee = u.username
                  WHERE cs.task_date = ?
                  AND cs.status NOT IN ('Done', 'Completed', 'completed', 'done')
                  AND cs.assignee IS NOT NULL
                  AND cs.assignee != ''
                  ORDER BY cs.id DESC
                  LIMIT 50";
$overdue_stmt = mysqli_prepare($conn, $overdue_query);
$overdue_tasks = [];
if ($overdue_stmt) {
    mysqli_stmt_bind_param($overdue_stmt, 's', $yesterday);
    mysqli_stmt_execute($overdue_stmt);
    $overdue_result = mysqli_stmt_get_result($overdue_stmt);
    while ($task = mysqli_fetch_assoc($overdue_result)) {
        $overdue_tasks[] = $task;
    }
    mysqli_stmt_close($overdue_stmt);
}

// Get notifications created today for overdue checklist tasks
$notifications_query = "SELECT n.id, n.user_id, n.title, n.message, n.related_id, n.created_at,
                               u.name as manager_name, u.username as manager_username
                        FROM notifications n
                        LEFT JOIN users u ON n.user_id = u.id
                        WHERE n.type = 'checklist_overdue'
                        AND DATE(n.created_at) = CURDATE()
                        ORDER BY n.created_at DESC
                        LIMIT 50";
$notifications_result = mysqli_query($conn, $notifications_query);
$notifications = [];
if ($notifications_result) {
    while ($notif = mysqli_fetch_assoc($notifications_result)) {
        $notifications[] = $notif;
    }
}

// Get list of users with managers (for dropdown)
$users_query = "SELECT id, username, name, manager_id, manager 
                FROM users 
                WHERE Status = 'Active' 
                AND (manager_id IS NOT NULL OR manager IS NOT NULL)
                ORDER BY name ASC";
$users_result = mysqli_query($conn, $users_query);
$users = [];
if ($users_result) {
    while ($user = mysqli_fetch_assoc($users_result)) {
        $users[] = $user;
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

.test-section h3 i {
    font-size: 1.1rem;
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

.btn-test.success {
    background: #22c55e;
}

.btn-test.success:hover {
    background: #16a34a;
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

.info-box strong {
    color: var(--brand-primary);
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

.badge-pending {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
}

.badge-completed {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
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
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    background: var(--dark-bg);
    border: 1px solid var(--glass-border);
    border-radius: 6px;
    color: var(--dark-text-primary);
    font-size: 0.9rem;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--brand-primary);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
}

.empty-state {
    text-align: center;
    padding: 2rem;
    color: var(--dark-text-secondary);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}
</style>

<div class="container-fluid">
    <div class="test-container">
        <h1 class="mb-4"><i class="fas fa-clipboard-check"></i> Test Checklist Overdue Notifications</h1>
        
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
            <strong><i class="fas fa-info-circle"></i> How it works:</strong><br>
            The system checks for checklist tasks that were due <strong>yesterday</strong> (<?php echo date('d/m/Y', strtotime('-1 day')); ?>) 
            and are not completed. When found, it sends a notification to the manager of the user who owns the task.
            <br><br>
            <strong>Current Date:</strong> <?php echo date('d/m/Y'); ?><br>
            <strong>Checking for tasks due on:</strong> <?php echo date('d/m/Y', strtotime('-1 day')); ?>
        </div>
        
        <!-- Test Actions -->
        <div class="test-section">
            <h3><i class="fas fa-flask"></i> Test Actions</h3>
            
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="trigger_check">
                <button type="submit" class="btn-test success">
                    <i class="fas fa-play"></i> Run Overdue Checklist Check
                </button>
            </form>
            
            <button type="button" class="btn-test" onclick="document.getElementById('createTaskForm').style.display = document.getElementById('createTaskForm').style.display === 'none' ? 'block' : 'none';">
                <i class="fas fa-plus-circle"></i> Create Test Task
            </button>
            
            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete all test tasks?');">
                <input type="hidden" name="action" value="clear_test_tasks">
                <button type="submit" class="btn-test danger">
                    <i class="fas fa-trash"></i> Clear Test Tasks
                </button>
            </form>
        </div>
        
        <!-- Create Test Task Form -->
        <div class="test-section" id="createTaskForm" style="display: none;">
            <h3><i class="fas fa-plus"></i> Create Test Checklist Task</h3>
            <p style="color: var(--dark-text-secondary); margin-bottom: 1rem;">
                This will create a test checklist task with yesterday's date (<?php echo date('d/m/Y', strtotime('-1 day')); ?>) 
                and "pending" status, which should trigger the overdue notification when you run the check.
            </p>
            
            <form method="POST">
                <input type="hidden" name="action" value="create_test_task">
                
                <div class="form-group">
                    <label for="assignee_username"><i class="fas fa-user"></i> Assignee Username *</label>
                    <select name="assignee_username" id="assignee_username" required>
                        <option value="">Select a user...</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo htmlspecialchars($user['username']); ?>">
                                <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)
                                <?php if ($user['manager_id'] || $user['manager']): ?>
                                    - Manager: <?php echo htmlspecialchars($user['manager'] ?? 'ID: ' . $user['manager_id']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="task_description"><i class="fas fa-tasks"></i> Task Description</label>
                    <textarea name="task_description" id="task_description" rows="3" placeholder="Enter task description...">Test Overdue Checklist Task - Created on <?php echo date('d/m/Y H:i:s'); ?></textarea>
                </div>
                
                <button type="submit" class="btn-test">
                    <i class="fas fa-save"></i> Create Test Task
                </button>
            </form>
        </div>
        
        <!-- Overdue Tasks -->
        <div class="test-section">
            <h3><i class="fas fa-exclamation-triangle"></i> Overdue Checklist Tasks (Due: <?php echo date('d/m/Y', strtotime('-1 day')); ?>)</h3>
            <p style="color: var(--dark-text-secondary); margin-bottom: 1rem;">
                Tasks that were due yesterday and are not completed. These should trigger notifications to managers.
            </p>
            
            <?php if (empty($overdue_tasks)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>No overdue checklist tasks found for yesterday.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Task ID</th>
                                <th>Task Code</th>
                                <th>Description</th>
                                <th>Assignee</th>
                                <th>Status</th>
                                <th>Manager</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($overdue_tasks as $task): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($task['id']); ?></td>
                                    <td><?php echo htmlspecialchars($task['task_code'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($task['task_description']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($task['assignee_name'] ?? $task['assignee']); ?>
                                        <br><small style="color: var(--dark-text-secondary);">(<?php echo htmlspecialchars($task['assignee']); ?>)</small>
                                    </td>
                                    <td>
                                        <span class="badge badge-pending"><?php echo htmlspecialchars($task['status']); ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($task['manager_id']) {
                                            echo "ID: " . htmlspecialchars($task['manager_id']);
                                        } elseif ($task['manager_name']) {
                                            echo htmlspecialchars($task['manager_name']);
                                        } else {
                                            echo '<span style="color: #ef4444;">No Manager</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p style="margin-top: 1rem; color: var(--dark-text-secondary);">
                    <strong>Total:</strong> <?php echo count($overdue_tasks); ?> overdue task(s)
                </p>
            <?php endif; ?>
        </div>
        
        <!-- Notifications Created -->
        <div class="test-section">
            <h3><i class="fas fa-bell"></i> Notifications Created Today</h3>
            <p style="color: var(--dark-text-secondary); margin-bottom: 1rem;">
                Notifications sent to managers today for overdue checklist tasks.
            </p>
            
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <p>No overdue checklist notifications created today.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Notification ID</th>
                                <th>Manager</th>
                                <th>Title</th>
                                <th>Message</th>
                                <th>Task ID</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notifications as $notif): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($notif['id']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($notif['manager_name'] ?? $notif['manager_username'] ?? 'N/A'); ?>
                                        <br><small style="color: var(--dark-text-secondary);">(ID: <?php echo htmlspecialchars($notif['user_id']); ?>)</small>
                                    </td>
                                    <td><?php echo htmlspecialchars($notif['title']); ?></td>
                                    <td style="max-width: 400px; word-wrap: break-word;"><?php echo htmlspecialchars($notif['message']); ?></td>
                                    <td><?php echo htmlspecialchars($notif['related_id']); ?></td>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($notif['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p style="margin-top: 1rem; color: var(--dark-text-secondary);">
                    <strong>Total:</strong> <?php echo count($notifications); ?> notification(s) created today
                </p>
            <?php endif; ?>
        </div>
        
        <!-- Testing Instructions -->
        <div class="test-section">
            <h3><i class="fas fa-info-circle"></i> Testing Instructions</h3>
            <ol style="color: var(--dark-text-secondary); line-height: 2;">
                <li><strong>Create a test task:</strong> Use the "Create Test Task" button to create a checklist task with yesterday's date</li>
                <li><strong>Select an assignee:</strong> Choose a user who has a manager assigned</li>
                <li><strong>Run the check:</strong> Click "Run Overdue Checklist Check" to trigger the notification system</li>
                <li><strong>Verify notifications:</strong> Check the "Notifications Created Today" section to see if notifications were sent to managers</li>
                <li><strong>Check manager's notifications:</strong> Log in as the manager and check their notification bell to see the overdue task notification</li>
                <li><strong>Clean up:</strong> Use "Clear Test Tasks" to remove test tasks when done</li>
            </ol>
            
            <div style="margin-top: 1rem; padding: 1rem; background: rgba(251, 191, 36, 0.1); border: 1px solid rgba(251, 191, 36, 0.3); border-radius: 6px;">
                <strong style="color: #fbbf24;"><i class="fas fa-lightbulb"></i> Important Notes:</strong>
                <ul style="margin-top: 0.5rem; color: var(--dark-text-secondary); line-height: 1.8;">
                    <li>The system checks for tasks due on <strong>yesterday's date</strong> (not today)</li>
                    <li>Tasks must have status other than "Done" or "Completed" to be considered overdue</li>
                    <li>The assignee must have a manager assigned (manager_id or manager field)</li>
                    <li>Notifications are only sent once per day per task (duplicate prevention)</li>
                    <li>If a task doesn't have a manager, it will be skipped with an error logged</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

