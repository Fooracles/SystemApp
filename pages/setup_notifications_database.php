<?php
/**
 * Setup Notifications Database
 * This script creates the notifications table and all required columns
 * Access this page via browser to set up the database
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

// Allow any logged-in user to run setup (not just admin)
// This helps with troubleshooting

$messages = [];
$errors = [];
$warnings = [];

// Check if table exists
$table_check = "SHOW TABLES LIKE 'notifications'";
$table_result = mysqli_query($conn, $table_check);
$table_exists = mysqli_num_rows($table_result) > 0;

if (!$table_exists) {
    // Create the table from scratch
    $messages[] = "Creating notifications table...";
    
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
        INDEX idx_user_id (user_id),
        INDEX idx_type (type),
        INDEX idx_is_read (is_read),
        INDEX idx_created_at (created_at),
        INDEX idx_action_required (action_required),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if (mysqli_query($conn, $create_table)) {
        $messages[] = "✓ Notifications table created successfully!";
    } else {
        $errors[] = "✗ Error creating table: " . mysqli_error($conn);
    }
} else {
    $messages[] = "✓ Notifications table already exists. Checking for missing columns...";
    
    // Get existing columns
    $cols_query = "SHOW COLUMNS FROM notifications";
    $cols_result = mysqli_query($conn, $cols_query);
    $existing_columns = [];
    while ($col = mysqli_fetch_assoc($cols_result)) {
        $existing_columns[$col['Field']] = $col;
    }
    
    // Define required columns with their specifications
    $required_columns = [
        'id' => [
            'type' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'position' => 'FIRST'
        ],
        'user_id' => [
            'type' => 'INT NOT NULL',
            'position' => 'AFTER id'
        ],
        'type' => [
            'type' => "ENUM('task_delay', 'meeting_request', 'meeting_approved', 'meeting_rescheduled', 'day_special', 'notes_reminder', 'leave_request', 'leave_approved', 'leave_rejected') NOT NULL",
            'position' => 'AFTER user_id'
        ],
        'title' => [
            'type' => 'VARCHAR(255) NOT NULL',
            'position' => 'AFTER type'
        ],
        'message' => [
            'type' => 'TEXT NOT NULL',
            'position' => 'AFTER title'
        ],
        'related_id' => [
            'type' => "VARCHAR(255) NULL COMMENT 'ID of related record (task_id, meeting_id, leave_id, note_id, etc.) - Can be INT or VARCHAR'",
            'position' => 'AFTER message'
        ],
        'related_type' => [
            'type' => "VARCHAR(50) NULL COMMENT 'Type of related record (task, meeting, leave, note, etc.)'",
            'position' => 'AFTER related_id'
        ],
        'is_read' => [
            'type' => 'BOOLEAN DEFAULT 0',
            'position' => 'AFTER related_type'
        ],
        'action_required' => [
            'type' => "BOOLEAN DEFAULT 0 COMMENT 'Whether notification requires user action'",
            'position' => 'AFTER is_read'
        ],
        'action_data' => [
            'type' => "TEXT NULL COMMENT 'JSON data for action buttons (approve, reject, reschedule, etc.)'",
            'position' => 'AFTER action_required'
        ],
        'created_at' => [
            'type' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'position' => 'AFTER action_data'
        ],
        'read_at' => [
            'type' => 'DATETIME NULL',
            'position' => 'AFTER created_at'
        ]
    ];
    
    // Check and add missing columns
    foreach ($required_columns as $column_name => $column_spec) {
        if (!isset($existing_columns[$column_name])) {
            $messages[] = "Adding missing column: $column_name...";
            $alter_query = "ALTER TABLE notifications ADD COLUMN $column_name {$column_spec['type']} {$column_spec['position']}";
            
            if (mysqli_query($conn, $alter_query)) {
                $messages[] = "✓ Column $column_name added successfully!";
            } else {
                $errors[] = "✗ Error adding column $column_name: " . mysqli_error($conn);
            }
        } else {
            // Check if column type needs to be updated
            $current_type = $existing_columns[$column_name]['Type'];
            $required_type = $column_spec['type'];
            
            // Special handling for related_id - should be VARCHAR(255) not INT
            if ($column_name === 'related_id' && strpos($current_type, 'int') !== false && strpos($required_type, 'VARCHAR') !== false) {
                $messages[] = "Updating column type for $column_name from $current_type to VARCHAR(255)...";
                $alter_query = "ALTER TABLE notifications MODIFY COLUMN $column_name VARCHAR(255) NULL COMMENT 'ID of related record (task_id, meeting_id, leave_id, note_id, etc.) - Can be INT or VARCHAR'";
                
                if (mysqli_query($conn, $alter_query)) {
                    $messages[] = "✓ Column $column_name type updated successfully!";
                } else {
                    $warnings[] = "⚠ Could not update column $column_name type: " . mysqli_error($conn);
                }
            }
            
            // Check if type ENUM needs updating
            if ($column_name === 'type' && strpos($current_type, 'ENUM') !== false) {
                $required_values = ['task_delay', 'meeting_request', 'meeting_approved', 'meeting_rescheduled', 'day_special', 'notes_reminder', 'leave_request', 'leave_approved', 'leave_rejected'];
                $all_present = true;
                foreach ($required_values as $value) {
                    if (strpos($current_type, $value) === false) {
                        $all_present = false;
                        break;
                    }
                }
                
                if (!$all_present) {
                    $messages[] = "Updating type ENUM to include all required values...";
                    $new_enum = "ENUM('" . implode("','", $required_values) . "')";
                    $alter_query = "ALTER TABLE notifications MODIFY COLUMN type $new_enum NOT NULL";
                    
                    if (mysqli_query($conn, $alter_query)) {
                        $messages[] = "✓ Type ENUM updated successfully!";
                    } else {
                        $warnings[] = "⚠ Could not update type ENUM: " . mysqli_error($conn);
                    }
                }
            }
        }
    }
    
    // Check and create indexes
    $indexes_to_check = [
        'idx_user_id' => 'user_id',
        'idx_type' => 'type',
        'idx_is_read' => 'is_read',
        'idx_created_at' => 'created_at',
        'idx_action_required' => 'action_required'
    ];
    
    $indexes_query = "SHOW INDEXES FROM notifications";
    $indexes_result = mysqli_query($conn, $indexes_query);
    $existing_indexes = [];
    while ($idx = mysqli_fetch_assoc($indexes_result)) {
        $existing_indexes[] = $idx['Key_name'];
    }
    
    foreach ($indexes_to_check as $index_name => $column_name) {
        if (!in_array($index_name, $existing_indexes)) {
            $messages[] = "Creating index: $index_name on column $column_name...";
            $create_index = "CREATE INDEX $index_name ON notifications ($column_name)";
            
            if (mysqli_query($conn, $create_index)) {
                $messages[] = "✓ Index $index_name created successfully!";
            } else {
                $warnings[] = "⚠ Could not create index $index_name: " . mysqli_error($conn);
            }
        }
    }
    
    // Check foreign key
    $fk_query = "SELECT CONSTRAINT_NAME 
                 FROM information_schema.TABLE_CONSTRAINTS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = 'notifications' 
                 AND CONSTRAINT_TYPE = 'FOREIGN KEY' 
                 AND CONSTRAINT_NAME LIKE '%user_id%'";
    $fk_result = mysqli_query($conn, $fk_query);
    
    if (mysqli_num_rows($fk_result) == 0) {
        $messages[] = "Creating foreign key constraint on user_id...";
        $create_fk = "ALTER TABLE notifications 
                      ADD CONSTRAINT fk_notifications_user_id 
                      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE";
        
        if (mysqli_query($conn, $create_fk)) {
            $messages[] = "✓ Foreign key constraint created successfully!";
        } else {
            $warnings[] = "⚠ Could not create foreign key constraint: " . mysqli_error($conn);
        }
    } else {
        $messages[] = "✓ Foreign key constraint already exists.";
    }
}

// Verify the setup
$verify_query = "SHOW COLUMNS FROM notifications";
$verify_result = mysqli_query($conn, $verify_query);
$final_columns = [];
while ($col = mysqli_fetch_assoc($verify_result)) {
    $final_columns[] = $col['Field'];
}

$required_cols = ['id', 'user_id', 'type', 'title', 'message', 'related_id', 'related_type', 'is_read', 'action_required', 'action_data', 'created_at', 'read_at'];
$missing_cols = array_diff($required_cols, $final_columns);

if (empty($missing_cols)) {
    $messages[] = "✓ All required columns are present!";
} else {
    $errors[] = "✗ Missing columns: " . implode(', ', $missing_cols);
}

$page_title = 'Setup Notifications Database';
require_once '../includes/header.php';
?>

<style>
.setup-container {
    max-width: 900px;
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
    font-size: 0.9rem;
    line-height: 1.6;
}

.message.success {
    color: #22c55e;
}

.message.error {
    color: #ef4444;
}

.message.warning {
    color: #f59e0b;
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

.summary {
    background: var(--dark-bg);
    padding: 1rem;
    border-radius: 6px;
    margin-top: 1rem;
    border: 1px solid var(--glass-border);
}

.summary h4 {
    margin: 0 0 0.5rem 0;
    color: var(--brand-primary);
}
</style>

<div class="container-fluid">
    <div class="setup-container">
        <h1 class="mb-4">Setup Notifications Database</h1>
        
        <div class="message-box">
            <?php if (!empty($messages)): ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message success"><?php echo htmlspecialchars($msg); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($warnings)): ?>
                <?php foreach ($warnings as $warn): ?>
                    <div class="message warning"><?php echo htmlspecialchars($warn); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $err): ?>
                    <div class="message error"><?php echo htmlspecialchars($err); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (empty($errors) && !empty($messages)): ?>
                <div class="summary">
                    <h4>✓ Database Setup Complete!</h4>
                    <p style="color: var(--dark-text-secondary); margin: 0;">
                        The notifications table has been created/updated with all required columns, indexes, and constraints.
                        You can now use the notifications system.
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($final_columns)): ?>
        <div class="message-box">
            <h4 style="color: var(--brand-primary); margin-bottom: 1rem;">Final Table Structure</h4>
            <ul style="color: var(--dark-text-secondary); font-family: monospace; font-size: 0.85rem; line-height: 2;">
                <?php foreach ($final_columns as $col): ?>
                    <li><?php echo htmlspecialchars($col); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <a href="test_notifications.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Go to Test Notifications
        </a>
        
        <a href="setup_notifications_database.php" class="btn-back" style="background: #6366f1; margin-left: 0.5rem;">
            <i class="fas fa-sync"></i> Re-run Setup
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

