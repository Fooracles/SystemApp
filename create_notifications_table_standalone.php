<?php
/**
 * Standalone Script to Create Notifications Table
 * 
 * This script can be used in any project to create the notifications table
 * with all required columns and indexes.
 * 
 * Usage:
 * 1. Configure database connection settings below
 * 2. Run via command line: php create_notifications_table_standalone.php
 * 3. Or access via browser (if placed in web-accessible directory)
 * 
 * Note: Make sure the 'users' table exists before running this script
 *       as there's a foreign key constraint on user_id.
 */

// ============================================
// DATABASE CONFIGURATION
// ============================================
// Update these values according to your database settings

$db_host = 'localhost';
$db_username = 'root';
$db_password = '';
$db_name = 'your_database_name';

// ============================================
// SCRIPT EXECUTION
// ============================================

// Connect to database
$conn = @mysqli_connect($db_host, $db_username, $db_password, $db_name);

if (!$conn) {
    die("✗ Database connection failed: " . mysqli_connect_error() . "\n");
}

echo "✓ Connected to database successfully!\n\n";

// Check if users table exists (required for foreign key)
$users_table_check = "SHOW TABLES LIKE 'users'";
$users_table_result = mysqli_query($conn, $users_table_check);

if (mysqli_num_rows($users_table_result) == 0) {
    echo "⚠ WARNING: 'users' table does not exist.\n";
    echo "The notifications table requires a foreign key to the users table.\n";
    echo "Do you want to continue anyway? (Foreign key will be skipped)\n";
    echo "Press Enter to continue or Ctrl+C to cancel...\n";
    // For CLI, you might want to handle this differently
    $skip_foreign_key = true;
} else {
    $skip_foreign_key = false;
}

echo "Creating/Updating notifications table...\n\n";

// Check if table exists
$table_check = "SHOW TABLES LIKE 'notifications'";
$table_result = mysqli_query($conn, $table_check);

if (mysqli_num_rows($table_result) == 0) {
    // Table doesn't exist, create it
    echo "Table doesn't exist. Creating...\n";
    
    $foreign_key_sql = $skip_foreign_key ? '' : ", FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE";
    
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
        INDEX idx_action_required (action_required)
        $foreign_key_sql
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if (mysqli_query($conn, $create_table)) {
        echo "✓ Table created successfully!\n";
    } else {
        echo "✗ Error creating table: " . mysqli_error($conn) . "\n";
        mysqli_close($conn);
        exit(1);
    }
} else {
    // Table exists, check for missing columns
    echo "Table exists. Checking for missing columns...\n\n";
    
    $columns_to_check = [
        'related_id' => [
            'definition' => "VARCHAR(255) NULL COMMENT 'ID of related record (task_id, meeting_id, leave_id, note_id, etc.) - Can be INT or VARCHAR'",
            'position' => 'AFTER message'
        ],
        'related_type' => [
            'definition' => "VARCHAR(50) NULL COMMENT 'Type of related record (task, meeting, leave, note, etc.)'",
            'position' => 'AFTER related_id'
        ],
        'action_required' => [
            'definition' => "BOOLEAN DEFAULT 0 COMMENT 'Whether notification requires user action'",
            'position' => 'AFTER is_read'
        ],
        'action_data' => [
            'definition' => "TEXT NULL COMMENT 'JSON data for action buttons (approve, reject, reschedule, etc.)'",
            'position' => 'AFTER action_required'
        ],
        'read_at' => [
            'definition' => "DATETIME NULL",
            'position' => 'AFTER created_at'
        ]
    ];
    
    foreach ($columns_to_check as $column => $config) {
        $check_query = "SHOW COLUMNS FROM notifications LIKE '$column'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) == 0) {
            echo "Adding column: $column...\n";
            
            $alter_query = "ALTER TABLE notifications ADD COLUMN $column {$config['definition']} {$config['position']}";
            
            if (mysqli_query($conn, $alter_query)) {
                echo "✓ Column $column added successfully!\n";
            } else {
                echo "✗ Error adding column $column: " . mysqli_error($conn) . "\n";
            }
        } else {
            // Check if column type needs to be updated (e.g., related_id from INT to VARCHAR)
            $col_info = mysqli_fetch_assoc($check_result);
            if ($column === 'related_id' && strpos($col_info['Type'], 'varchar') === false && strpos($col_info['Type'], 'VARCHAR') === false) {
                echo "Updating column type for $column (INT to VARCHAR)...\n";
                $alter_query = "ALTER TABLE notifications MODIFY COLUMN $column {$config['definition']}";
                if (mysqli_query($conn, $alter_query)) {
                    echo "✓ Column $column updated successfully!\n";
                } else {
                    echo "✗ Error updating column $column: " . mysqli_error($conn) . "\n";
                }
            } else {
                echo "✓ Column $column already exists.\n";
            }
        }
    }
    
    // Check and add missing indexes
    echo "\nChecking indexes...\n";
    $indexes_to_check = [
        'idx_user_id' => 'user_id',
        'idx_type' => 'type',
        'idx_is_read' => 'is_read',
        'idx_created_at' => 'created_at',
        'idx_action_required' => 'action_required'
    ];
    
    $existing_indexes = [];
    $index_query = "SHOW INDEXES FROM notifications";
    $index_result = mysqli_query($conn, $index_query);
    while ($row = mysqli_fetch_assoc($index_result)) {
        $existing_indexes[] = $row['Key_name'];
    }
    
    foreach ($indexes_to_check as $index_name => $column_name) {
        if (!in_array($index_name, $existing_indexes)) {
            // Check if column exists before creating index
            $col_check = "SHOW COLUMNS FROM notifications LIKE '$column_name'";
            $col_result = mysqli_query($conn, $col_check);
            if (mysqli_num_rows($col_result) > 0) {
                echo "Adding index: $index_name on $column_name...\n";
                $add_index = "ALTER TABLE notifications ADD INDEX $index_name ($column_name)";
                if (mysqli_query($conn, $add_index)) {
                    echo "✓ Index $index_name created successfully!\n";
                } else {
                    echo "✗ Error creating index $index_name: " . mysqli_error($conn) . "\n";
                }
            }
        } else {
            echo "✓ Index $index_name already exists.\n";
        }
    }
    
    // Check if foreign key exists
    if (!$skip_foreign_key) {
        echo "\nChecking foreign key constraint...\n";
        $fk_check = "SELECT CONSTRAINT_NAME 
                     FROM information_schema.KEY_COLUMN_USAGE 
                     WHERE TABLE_SCHEMA = '$db_name' 
                     AND TABLE_NAME = 'notifications' 
                     AND COLUMN_NAME = 'user_id' 
                     AND REFERENCED_TABLE_NAME = 'users'";
        $fk_result = mysqli_query($conn, $fk_check);
        
        if (mysqli_num_rows($fk_result) == 0) {
            echo "Adding foreign key constraint...\n";
            $add_fk = "ALTER TABLE notifications ADD CONSTRAINT fk_notifications_user_id 
                       FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE";
            if (mysqli_query($conn, $add_fk)) {
                echo "✓ Foreign key constraint added successfully!\n";
            } else {
                echo "⚠ Warning: Could not add foreign key: " . mysqli_error($conn) . "\n";
            }
        } else {
            echo "✓ Foreign key constraint already exists.\n";
        }
    }
    
    // Check if type ENUM has all required values
    echo "\nChecking type ENUM values...\n";
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

echo "\n" . str_repeat("=", 50) . "\n";
echo "✓ Notifications table setup completed successfully!\n";
echo str_repeat("=", 50) . "\n\n";

// Display table structure
echo "Table Structure:\n";
echo str_repeat("-", 50) . "\n";
$desc_query = "DESCRIBE notifications";
$desc_result = mysqli_query($conn, $desc_query);
while ($row = mysqli_fetch_assoc($desc_result)) {
    printf("%-20s %-30s %s\n", $row['Field'], $row['Type'], $row['Null'] === 'YES' ? 'NULL' : 'NOT NULL');
}
echo str_repeat("-", 50) . "\n";

mysqli_close($conn);
?>

