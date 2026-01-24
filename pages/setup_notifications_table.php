<?php
/**
 * Setup Notifications Table
 * Access this page via browser to create/update the notifications table
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    die('Access denied. Admin login required.');
}

$messages = [];
$errors = [];

// Check if table exists
$table_check = "SHOW TABLES LIKE 'notifications'";
$table_result = mysqli_query($conn, $table_check);

if (mysqli_num_rows($table_result) == 0) {
    // Table doesn't exist, create it
    $create_table = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type ENUM('task_delay', 'meeting_request', 'meeting_approved', 'meeting_rescheduled', 'day_special', 'notes_reminder', 'leave_request', 'leave_approved', 'leave_rejected') NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        related_id VARCHAR(255) NULL COMMENT 'ID of related record (task_id, meeting_id, leave_id, note_id, etc.) - Can be INT or VARCHAR',
        related_type VARCHAR(50) NULL COMMENT 'Type of related record (task, meeting, leave, note, etc.)',
        is_read BOOLEAN DEFAULT 0,
        action_required BOOLEAN DEFAULT 0 COMMENT 'Whether notification requires user action',
        action_data TEXT NULL COMMENT 'JSON data for action buttons (approve, reject, reschedule, etc.)',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        read_at DATETIME NULL,
        INDEX (user_id),
        INDEX (type),
        INDEX (is_read),
        INDEX (created_at),
        INDEX (action_required),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if (mysqli_query($conn, $create_table)) {
        $messages[] = "✓ Table created successfully!";
    } else {
        $errors[] = "✗ Error creating table: " . mysqli_error($conn);
    }
} else {
    // Table exists, check for missing columns
    $messages[] = "Table exists. Checking for missing columns...";
    
    $columns_to_check = [
        'related_id' => "VARCHAR(255) NULL COMMENT 'ID of related record (task_id, meeting_id, leave_id, note_id, etc.) - Can be INT or VARCHAR'",
        'related_type' => "VARCHAR(50) NULL COMMENT 'Type of related record (task, meeting, leave, note, etc.)'",
        'action_required' => "BOOLEAN DEFAULT 0 COMMENT 'Whether notification requires user action'",
        'action_data' => "TEXT NULL COMMENT 'JSON data for action buttons (approve, reject, reschedule, etc.)'",
        'read_at' => "DATETIME NULL"
    ];
    
    foreach ($columns_to_check as $column => $definition) {
        $check_query = "SHOW COLUMNS FROM notifications LIKE '$column'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) == 0) {
            // Determine position based on column
            $position = '';
            if ($column === 'related_id') {
                $position = 'AFTER message';
            } elseif ($column === 'related_type') {
                $position = 'AFTER related_id';
            } elseif ($column === 'action_required') {
                $position = 'AFTER related_type';
            } elseif ($column === 'action_data') {
                $position = 'AFTER action_required';
            } elseif ($column === 'read_at') {
                $position = 'AFTER created_at';
            }
            
            // If column exists but type is wrong, modify it instead of adding
            $check_type = "SHOW COLUMNS FROM notifications WHERE Field = '$column'";
            $type_result = mysqli_query($conn, $check_type);
            if (mysqli_num_rows($type_result) > 0) {
                // Column exists, modify it
                $alter_query = "ALTER TABLE notifications MODIFY COLUMN $column $definition";
            } else {
                // Column doesn't exist, add it
                $alter_query = "ALTER TABLE notifications ADD COLUMN $column $definition $position";
            }
            
            if (mysqli_query($conn, $alter_query)) {
                $messages[] = "✓ Column $column added successfully!";
            } else {
                $errors[] = "✗ Error adding column $column: " . mysqli_error($conn);
            }
        } else {
            $messages[] = "✓ Column $column already exists.";
        }
    }
    
    // Check if type ENUM has all required values
    $enum_check = "SHOW COLUMNS FROM notifications WHERE Field = 'type'";
    $enum_result = mysqli_query($conn, $enum_check);
    $enum_row = mysqli_fetch_assoc($enum_result);
    
    if ($enum_row) {
        $current_enum = $enum_row['Type'];
        $required_values = ['task_delay', 'meeting_request', 'meeting_approved', 'meeting_rescheduled', 'day_special', 'notes_reminder', 'leave_request', 'leave_approved', 'leave_rejected'];
        
        // Check if all required values are in the ENUM
        $all_present = true;
        foreach ($required_values as $value) {
            if (strpos($current_enum, $value) === false) {
                $all_present = false;
                break;
            }
        }
        
        if (!$all_present) {
            $new_enum = "ENUM('" . implode("','", $required_values) . "')";
            $alter_enum = "ALTER TABLE notifications MODIFY COLUMN type $new_enum NOT NULL";
            
            if (mysqli_query($conn, $alter_enum)) {
                $messages[] = "✓ Type ENUM updated successfully!";
            } else {
                $errors[] = "✗ Error updating type ENUM: " . mysqli_error($conn);
            }
        } else {
            $messages[] = "✓ Type ENUM has all required values.";
        }
    }
}

$page_title = 'Setup Notifications Table';
require_once '../includes/header.php';
?>

<style>
.setup-container {
    max-width: 800px;
    margin: 2rem auto;
    padding: 2rem;
}

.message-box {
    background: var(--dark-bg-card);
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    border: 1px solid var(--glass-border);
}

.message {
    padding: 0.5rem 0;
    color: var(--dark-text-primary);
    font-family: monospace;
}

.message.success {
    color: #22c55e;
}

.message.error {
    color: #ef4444;
}

.btn-back {
    background: var(--brand-primary);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    text-decoration: none;
    display: inline-block;
    margin-top: 1rem;
    transition: all 0.3s ease;
}

.btn-back:hover {
    background: var(--brand-primary-hover);
    transform: translateY(-2px);
}
</style>

<div class="container-fluid">
    <div class="setup-container">
        <h1 class="mb-4">Setup Notifications Table</h1>
        
        <div class="message-box">
            <?php if (!empty($messages)): ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message success"><?php echo htmlspecialchars($msg); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $err): ?>
                    <div class="message error"><?php echo htmlspecialchars($err); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (empty($errors)): ?>
                <div class="message success" style="margin-top: 1rem; font-weight: 600;">
                    ✓ Notifications table is ready!
                </div>
            <?php endif; ?>
        </div>
        
        <a href="test_notifications.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Go to Test Notifications
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

