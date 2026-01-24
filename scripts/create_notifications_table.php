<?php
/**
 * Create or Update Notifications Table
 * Run this script to ensure the notifications table exists with all required columns
 */

require_once __DIR__ . '/../includes/config.php';

echo "Creating/Updating notifications table...\n";

// Check if table exists
$table_check = "SHOW TABLES LIKE 'notifications'";
$table_result = mysqli_query($conn, $table_check);

if (mysqli_num_rows($table_result) == 0) {
    // Table doesn't exist, create it
    echo "Table doesn't exist. Creating...\n";
    
    $create_table = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type ENUM('task_delay', 'meeting_request', 'meeting_approved', 'meeting_rescheduled', 'day_special', 'notes_reminder', 'leave_request', 'leave_approved', 'leave_rejected') NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        related_id INT NULL COMMENT 'ID of related record (task_id, meeting_id, leave_id, note_id, etc.)',
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
        echo "✓ Table created successfully!\n";
    } else {
        echo "✗ Error creating table: " . mysqli_error($conn) . "\n";
        exit(1);
    }
} else {
    // Table exists, check for missing columns
    echo "Table exists. Checking for missing columns...\n";
    
    $columns_to_check = [
        'related_id' => "INT NULL COMMENT 'ID of related record (task_id, meeting_id, leave_id, note_id, etc.)'",
        'related_type' => "VARCHAR(50) NULL COMMENT 'Type of related record (task, meeting, leave, note, etc.)'",
        'action_required' => "BOOLEAN DEFAULT 0 COMMENT 'Whether notification requires user action'",
        'action_data' => "TEXT NULL COMMENT 'JSON data for action buttons (approve, reject, reschedule, etc.)'",
        'read_at' => "DATETIME NULL"
    ];
    
    foreach ($columns_to_check as $column => $definition) {
        $check_query = "SHOW COLUMNS FROM notifications LIKE '$column'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) == 0) {
            echo "Adding column: $column...\n";
            
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
            
            $alter_query = "ALTER TABLE notifications ADD COLUMN $column $definition $position";
            
            if (mysqli_query($conn, $alter_query)) {
                echo "✓ Column $column added successfully!\n";
            } else {
                echo "✗ Error adding column $column: " . mysqli_error($conn) . "\n";
            }
        } else {
            echo "✓ Column $column already exists.\n";
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
            echo "Updating type ENUM to include all required values...\n";
            $new_enum = "ENUM('" . implode("','", $required_values) . "')";
            $alter_enum = "ALTER TABLE notifications MODIFY COLUMN type $new_enum NOT NULL";
            
            if (mysqli_query($conn, $alter_enum)) {
                echo "✓ Type ENUM updated successfully!\n";
            } else {
                echo "✗ Error updating type ENUM: " . mysqli_error($conn) . "\n";
            }
        } else {
            echo "✓ Type ENUM has all required values.\n";
        }
    }
}

echo "\n✓ Notifications table is ready!\n";

