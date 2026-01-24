<?php
/**
 * Database Utility Functions
 * 
 * This file contains functions for checking and creating database tables.
 */

/**
 * Check if a table exists in the database
 * 
 * @param mysqli $conn Database connection
 * @param string $table_name Name of the table to check
 * @return bool True if the table exists, false otherwise
 */
function tableExists($conn, $table_name) {
    $result = $conn->query("SHOW TABLES LIKE '$table_name'");
    return $result->num_rows > 0;
}

/**
 * Check if an index exists in a table
 * 
 * @param mysqli $conn Database connection
 * @param string $table_name Name of the table
 * @param string $index_name Name of the index to check
 * @return bool True if the index exists, false otherwise
 */
function indexExists($conn, $table_name, $index_name) {
    $sql = "SHOW INDEX FROM `$table_name` WHERE Key_name = '$index_name'";
    $result = mysqli_query($conn, $sql);
    return $result && mysqli_num_rows($result) > 0;
}

/**
 * Check if a column exists in a table
 *
 * @param mysqli $conn Database connection
 * @param string $table_name Table name
 * @param string $column_name Column name
 * @return bool True if the column exists, false otherwise
 */
function columnExists($conn, $table_name, $column_name) {
    $sql = "SHOW COLUMNS FROM `$table_name` LIKE '" . mysqli_real_escape_string($conn, $column_name) . "'";
    $result = mysqli_query($conn, $sql);
    return $result && mysqli_num_rows($result) > 0;
}

/**
 * Ensure new columns on fms_tasks exist (non-destructive migration)
 * Adds columns only if missing to avoid breaking existing databases.
 *
 * @param mysqli $conn Database connection
 * @return void
 */
function ensureFmsTasksColumns($conn) {
    // step_code is used by sheet import; must exist
    if (!columnExists($conn, 'fms_tasks', 'step_code')) {
        $alterSql = "ALTER TABLE fms_tasks ADD COLUMN step_code VARCHAR(255) NULL AFTER sheet_label";
        if (mysqli_query($conn, $alterSql)) {
            dbLog("Column 'step_code' added to fms_tasks table");
        } else {
            dbLog("Error adding 'step_code' to fms_tasks table: " . mysqli_error($conn));
        }
    }

    // client_name is used for displaying client information
    if (!columnExists($conn, 'fms_tasks', 'client_name')) {
        $alterSql = "ALTER TABLE fms_tasks ADD COLUMN client_name VARCHAR(255) NULL AFTER step_name";
        if (mysqli_query($conn, $alterSql)) {
            dbLog("Column 'client_name' added to fms_tasks table");
        } else {
            dbLog("Error adding 'client_name' to fms_tasks table: " . mysqli_error($conn));
        }
    }

    // imported_at is used for tracking import timestamps
    if (!columnExists($conn, 'fms_tasks', 'imported_at')) {
        $alterSql = "ALTER TABLE fms_tasks ADD COLUMN imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        if (mysqli_query($conn, $alterSql)) {
            dbLog("Column 'imported_at' added to fms_tasks table");
        } else {
            dbLog("Error adding 'imported_at' to fms_tasks table: " . mysqli_error($conn));
        }
    }
}

/**
 * Ensure new/missing columns on users exist (non-destructive migration)
 * Adds columns only if missing to avoid breaking existing databases.
 *
 * @param mysqli $conn Database connection
 * @return void
 */
function ensureUsersColumns($conn) {
    if (!columnExists($conn, 'users', 'manager')) {
        $alterSql = "ALTER TABLE users ADD COLUMN manager VARCHAR(255) NULL AFTER user_type";
        if (mysqli_query($conn, $alterSql)) {
            dbLog("Column 'manager' added to users table");
        } else {
            dbLog("Error adding 'manager' to users table: " . mysqli_error($conn));
        }
    }

    if (!columnExists($conn, 'users', 'manager_id')) {
        $alterSql = "ALTER TABLE users ADD COLUMN manager_id INT NULL AFTER manager";
        if (mysqli_query($conn, $alterSql)) {
            dbLog("Column 'manager_id' added to users table");
        } else {
            dbLog("Error adding 'manager_id' to users table: " . mysqli_error($conn));
        }
    }

    if (!columnExists($conn, 'users', 'joining_date')) {
        $alterSql = "ALTER TABLE users ADD COLUMN joining_date DATE NULL AFTER manager_id";
        if (mysqli_query($conn, $alterSql)) {
            dbLog("Column 'joining_date' added to users table");
        } else {
            dbLog("Error adding 'joining_date' to users table: " . mysqli_error($conn));
        }
    }

    if (!columnExists($conn, 'users', 'date_of_birth')) {
        $alterSql = "ALTER TABLE users ADD COLUMN date_of_birth DATE NULL AFTER joining_date";
        if (mysqli_query($conn, $alterSql)) {
            dbLog("Column 'date_of_birth' added to users table");
        } else {
            dbLog("Error adding 'date_of_birth' to users table: " . mysqli_error($conn));
        }
    }

    if (!columnExists($conn, 'users', 'profile_photo')) {
        $alterSql = "ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER date_of_birth";
        if (mysqli_query($conn, $alterSql)) {
            dbLog("Column 'profile_photo' added to users table");
        } else {
            dbLog("Error adding 'profile_photo' to users table: " . mysqli_error($conn));
        }
    }
}

/**
 * Ensure password_reset_requests table has correct structure
 * Makes reset_code nullable since it's only set when admin approves
 *
 * @param mysqli $conn Database connection
 * @return void
 */
function ensurePasswordResetRequestsColumns($conn) {
    if (tableExists($conn, 'password_reset_requests')) {
        // Check if reset_code column exists and is NOT NULL, make it nullable
        $type = getColumnType($conn, 'password_reset_requests', 'reset_code');
        if ($type !== null) {
            // Check if column allows NULL by querying information_schema
            $check_null_sql = "SELECT IS_NULLABLE FROM information_schema.COLUMNS 
                              WHERE TABLE_SCHEMA = DATABASE() 
                              AND TABLE_NAME = 'password_reset_requests' 
                              AND COLUMN_NAME = 'reset_code'";
            $result = mysqli_query($conn, $check_null_sql);
            if ($result && ($row = mysqli_fetch_assoc($result))) {
                if ($row['IS_NULLABLE'] === 'NO') {
                    $alterSql = "ALTER TABLE password_reset_requests MODIFY COLUMN reset_code VARCHAR(255) NULL";
                    if (mysqli_query($conn, $alterSql)) {
                        dbLog("Column 'reset_code' on password_reset_requests made nullable");
                    } else {
                        dbLog("Error making 'reset_code' nullable on password_reset_requests: " . mysqli_error($conn));
                    }
                }
            }
        }
    }
}

/**
 * Helper to get column type from SHOW COLUMNS
 * @param mysqli $conn
 * @param string $table
 * @param string $column
 * @return string|null
 */
function getColumnType($conn, $table, $column) {
    $sql = "SHOW COLUMNS FROM `$table` LIKE '" . mysqli_real_escape_string($conn, $column) . "'";
    $res = mysqli_query($conn, $sql);
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        return isset($row['Type']) ? strtolower($row['Type']) : null;
    }
    return null;
}

/**
 * Check if a foreign key constraint exists
 * @param mysqli $conn
 * @param string $table
 * @param string $constraint
 * @return bool
 */
function foreignKeyExists($conn, $table, $constraint) {
    $dbRes = mysqli_query($conn, 'SELECT DATABASE() as db');
    $dbRow = $dbRes ? mysqli_fetch_assoc($dbRes) : null;
    $dbName = $dbRow ? $dbRow['db'] : null;
    if (!$dbName) { return false; }
    $sql = "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = '" . mysqli_real_escape_string($conn, $dbName) . "' AND TABLE_NAME = '" . mysqli_real_escape_string($conn, $table) . "' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = '" . mysqli_real_escape_string($conn, $constraint) . "'";
    $res = mysqli_query($conn, $sql);
    return $res && mysqli_num_rows($res) > 0;
}

/**
 * Ensure updates table has correct columns (non-destructive migration)
 * Adds new columns and removes old ones if needed.
 * @param mysqli $conn
 * @return void
 */
function ensureUpdatesColumns($conn) {
    // Add attachment_path column if it doesn't exist
    if (!columnExists($conn, 'updates', 'attachment_path')) {
        $alterSql = "ALTER TABLE updates ADD COLUMN attachment_path VARCHAR(500) NULL COMMENT 'Path to uploaded attachment file' AFTER content";
        if (mysqli_query($conn, $alterSql)) {
            dbLog("Column 'attachment_path' added to updates table");
        } else {
            dbLog("Error adding 'attachment_path' to updates table: " . mysqli_error($conn));
        }
    }
    
    // Add attachment_name column if it doesn't exist
    if (!columnExists($conn, 'updates', 'attachment_name')) {
        $alterSql = "ALTER TABLE updates ADD COLUMN attachment_name VARCHAR(255) NULL COMMENT 'Original name of the attachment' AFTER attachment_path";
        if (mysqli_query($conn, $alterSql)) {
            dbLog("Column 'attachment_name' added to updates table");
        } else {
            dbLog("Error adding 'attachment_name' to updates table: " . mysqli_error($conn));
        }
    }
    
    // Add voice_recording_path column if it doesn't exist
    if (!columnExists($conn, 'updates', 'voice_recording_path')) {
        $alterSql = "ALTER TABLE updates ADD COLUMN voice_recording_path VARCHAR(500) NULL COMMENT 'Path to voice recording file' AFTER attachment_name";
        if (mysqli_query($conn, $alterSql)) {
            dbLog("Column 'voice_recording_path' added to updates table");
        } else {
            dbLog("Error adding 'voice_recording_path' to updates table: " . mysqli_error($conn));
        }
    }
    
    // Add title column if it doesn't exist
    if (!columnExists($conn, 'updates', 'title')) {
        $alterSql = "ALTER TABLE updates ADD COLUMN title VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Title of the update' AFTER id";
        if (mysqli_query($conn, $alterSql)) {
            dbLog("Column 'title' added to updates table");
        } else {
            dbLog("Error adding 'title' to updates table: " . mysqli_error($conn));
        }
    }
    
    // Remove type column if it exists (old schema)
    if (columnExists($conn, 'updates', 'type')) {
        $alterSql = "ALTER TABLE updates DROP COLUMN type";
        if (mysqli_query($conn, $alterSql)) {
            dbLog("Column 'type' removed from updates table");
        } else {
            dbLog("Error removing 'type' from updates table: " . mysqli_error($conn));
        }
    }
    
    // Add target_client_id column if it doesn't exist
    if (!columnExists($conn, 'updates', 'target_client_id')) {
        $alterSql = "ALTER TABLE updates ADD COLUMN target_client_id INT NULL COMMENT 'Target client user ID (for admin/manager to send updates to specific clients)' AFTER created_by";
        if (mysqli_query($conn, $alterSql)) {
            dbLog("Column 'target_client_id' added to updates table");
            // Add foreign key constraint if it doesn't exist
            $fkCheck = "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'updates' AND COLUMN_NAME = 'target_client_id' AND CONSTRAINT_NAME LIKE 'fk_%'";
            $fkResult = mysqli_query($conn, $fkCheck);
            if (mysqli_num_rows($fkResult) == 0) {
                $fkSql = "ALTER TABLE updates ADD CONSTRAINT fk_updates_target_client FOREIGN KEY (target_client_id) REFERENCES users(id) ON DELETE SET NULL";
                if (mysqli_query($conn, $fkSql)) {
                    dbLog("Foreign key 'fk_updates_target_client' added to updates table");
                } else {
                    dbLog("Error adding foreign key 'fk_updates_target_client' to updates table: " . mysqli_error($conn));
                }
            }
            // Add index if it doesn't exist
            $indexCheck = "SHOW INDEX FROM updates WHERE Column_name = 'target_client_id'";
            $indexResult = mysqli_query($conn, $indexCheck);
            if (mysqli_num_rows($indexResult) == 0) {
                $indexSql = "ALTER TABLE updates ADD INDEX idx_target_client_id (target_client_id)";
                if (mysqli_query($conn, $indexSql)) {
                    dbLog("Index 'idx_target_client_id' added to updates table");
                } else {
                    dbLog("Error adding index 'idx_target_client_id' to updates table: " . mysqli_error($conn));
                }
            }
        } else {
            dbLog("Error adding 'target_client_id' to updates table: " . mysqli_error($conn));
        }
    }
}

/**
 * Ensure required column changes on tasks (non-destructive migration)
 * - Add duration_minutes if missing
 * - Convert assigned_by to INT (FK to users.id) if currently not INT
 * - Add FK if missing
 * @param mysqli $conn
 * @return void
 */
function ensureTasksColumns($conn) {
    if (!columnExists($conn, 'tasks', 'duration_minutes')) {
        $alterSql = "ALTER TABLE tasks ADD COLUMN duration_minutes INT NULL AFTER duration";
        if (mysqli_query($conn, $alterSql)) {
            dbLog("Column 'duration_minutes' added to tasks table");
        } else {
            dbLog("Error adding 'duration_minutes' to tasks table: " . mysqli_error($conn));
        }
    }

    // Ensure assigned_by column type is INT
    if (columnExists($conn, 'tasks', 'assigned_by')) {
        $type = getColumnType($conn, 'tasks', 'assigned_by');
        if ($type !== null && strpos($type, 'int') === false) {
            $alterSql = "ALTER TABLE tasks MODIFY COLUMN assigned_by INT NULL";
            if (mysqli_query($conn, $alterSql)) {
                dbLog("Column 'assigned_by' on tasks converted to INT");
            } else {
                dbLog("Error converting 'assigned_by' to INT on tasks: " . mysqli_error($conn));
            }
        }
    } else {
        // Column missing entirely
        $alterSql = "ALTER TABLE tasks ADD COLUMN assigned_by INT NULL AFTER doer_manager_id";
        if (mysqli_query($conn, $alterSql)) {
            dbLog("Column 'assigned_by' added to tasks table");
        } else {
            dbLog("Error adding 'assigned_by' to tasks table: " . mysqli_error($conn));
        }
    }

    // Add assigned_by_type column to track assignment type
    if (!columnExists($conn, 'tasks', 'assigned_by_type')) {
        $alterSql = "ALTER TABLE tasks ADD COLUMN assigned_by_type ENUM('self', 'manager') NULL AFTER assigned_by";
        if (mysqli_query($conn, $alterSql)) {
            dbLog("Column 'assigned_by_type' added to tasks table");
        } else {
            dbLog("Error adding 'assigned_by_type' to tasks table: " . mysqli_error($conn));
        }
    }

    // Ensure foreign key exists for assigned_by
    if (!foreignKeyExists($conn, 'tasks', 'fk_tasks_assigned_by')) {
        // Drop any unnamed or previous FK on assigned_by to avoid duplicate constraint error
        // Best-effort: try add with explicit name
        $fkSql = "ALTER TABLE tasks ADD CONSTRAINT fk_tasks_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL";
        if (mysqli_query($conn, $fkSql)) {
            dbLog("Foreign key 'fk_tasks_assigned_by' added to tasks.assigned_by");
        } else {
            dbLog("Error adding FK 'fk_tasks_assigned_by' on tasks.assigned_by: " . mysqli_error($conn));
        }
    }

    // Add priority column to tasks table or convert ENUM to TINYINT(1)
    if (!columnExists($conn, 'tasks', 'priority')) {
        $alterSql = "ALTER TABLE tasks ADD COLUMN priority TINYINT(1) DEFAULT 0 AFTER status";
        if (mysqli_query($conn, $alterSql)) {
            dbLog("Column 'priority' added to tasks table");
        } else {
            dbLog("Error adding 'priority' to tasks table: " . mysqli_error($conn));
        }
    } else {
        // Check if priority column is ENUM type and convert to TINYINT(1)
        $type = getColumnType($conn, 'tasks', 'priority');
        if ($type !== null && stripos($type, 'enum') !== false) {
            // Convert ENUM to TINYINT(1) - map ENUM values: 'low'=0, 'medium'=0, 'high'=1, 'urgent'=1
            // First, update existing data: convert 'high' and 'urgent' to 1, others to 0
            $updateSql = "UPDATE tasks SET priority = CASE 
                WHEN priority IN ('high', 'urgent') THEN 1 
                ELSE 0 
            END WHERE priority IS NOT NULL";
            mysqli_query($conn, $updateSql);
            
            // Now convert the column type
            $alterSql = "ALTER TABLE tasks MODIFY COLUMN priority TINYINT(1) DEFAULT 0";
            if (mysqli_query($conn, $alterSql)) {
                dbLog("Column 'priority' on tasks converted from ENUM to TINYINT(1)");
            } else {
                dbLog("Error converting 'priority' from ENUM to TINYINT(1) on tasks: " . mysqli_error($conn));
            }
        } elseif ($type !== null && stripos($type, 'tinyint') === false) {
            // If it's not ENUM and not TINYINT, convert it
            $alterSql = "ALTER TABLE tasks MODIFY COLUMN priority TINYINT(1) DEFAULT 0";
            if (mysqli_query($conn, $alterSql)) {
                dbLog("Column 'priority' on tasks converted to TINYINT(1)");
            } else {
                dbLog("Error converting 'priority' to TINYINT(1) on tasks: " . mysqli_error($conn));
            }
        }
    }
}

/**
 * Ensure priority column exists in checklist_subtasks table
 * @param mysqli $conn
 * @return void
 */
function ensureChecklistPriorityColumn($conn) {
    if (!columnExists($conn, 'checklist_subtasks', 'priority')) {
        $alterSql = "ALTER TABLE checklist_subtasks ADD COLUMN priority TINYINT(1) DEFAULT 0 AFTER status";
        if (mysqli_query($conn, $alterSql)) {
            dbLog("Column 'priority' added to checklist_subtasks table");
        } else {
            dbLog("Error adding 'priority' to checklist_subtasks table: " . mysqli_error($conn));
        }
    } else {
        // Check if priority column is ENUM type and convert to TINYINT(1)
        $type = getColumnType($conn, 'checklist_subtasks', 'priority');
        if ($type !== null && stripos($type, 'enum') !== false) {
            $updateSql = "UPDATE checklist_subtasks SET priority = CASE 
                WHEN priority IN ('high', 'urgent') THEN 1 
                ELSE 0 
            END WHERE priority IS NOT NULL";
            mysqli_query($conn, $updateSql);
            
            $alterSql = "ALTER TABLE checklist_subtasks MODIFY COLUMN priority TINYINT(1) DEFAULT 0";
            if (mysqli_query($conn, $alterSql)) {
                dbLog("Column 'priority' on checklist_subtasks converted from ENUM to TINYINT(1)");
            } else {
                dbLog("Error converting 'priority' from ENUM to TINYINT(1) on checklist_subtasks: " . mysqli_error($conn));
            }
        }
    }
}

/**
 * Ensure priority column exists in fms_tasks table
 * @param mysqli $conn
 * @return void
 */
function ensureFmsTasksPriorityColumn($conn) {
    if (!columnExists($conn, 'fms_tasks', 'priority')) {
        $alterSql = "ALTER TABLE fms_tasks ADD COLUMN priority TINYINT(1) DEFAULT 0 AFTER status";
        if (mysqli_query($conn, $alterSql)) {
            dbLog("Column 'priority' added to fms_tasks table");
        } else {
            dbLog("Error adding 'priority' to fms_tasks table: " . mysqli_error($conn));
        }
    } else {
        // Check if priority column is ENUM type and convert to TINYINT(1)
        $type = getColumnType($conn, 'fms_tasks', 'priority');
        if ($type !== null && stripos($type, 'enum') !== false) {
            $updateSql = "UPDATE fms_tasks SET priority = CASE 
                WHEN priority IN ('high', 'urgent') THEN 1 
                ELSE 0 
            END WHERE priority IS NOT NULL";
            mysqli_query($conn, $updateSql);
            
            $alterSql = "ALTER TABLE fms_tasks MODIFY COLUMN priority TINYINT(1) DEFAULT 0";
            if (mysqli_query($conn, $alterSql)) {
                dbLog("Column 'priority' on fms_tasks converted from ENUM to TINYINT(1)");
            } else {
                dbLog("Error converting 'priority' from ENUM to TINYINT(1) on fms_tasks: " . mysqli_error($conn));
            }
        }
    }
}

/**
 * Create performance indexes for the fms_tasks table
 * 
 * @param mysqli $conn Database connection
 * @return array Array with status of each index creation
 */
function createFmsTasksIndexes($conn) {
    $indexes = [
        'idx_doer_name' => 'doer_name',
        'idx_sheet_label' => 'sheet_label',
        'idx_planned' => 'planned',
        'idx_unique_key' => 'unique_key',
        'idx_step_name' => 'step_name'
    ];
    
    $results = [];
    
    foreach ($indexes as $indexName => $columnName) {
        if (!indexExists($conn, 'fms_tasks', $indexName)) {
            $index_sql = "CREATE INDEX $indexName ON fms_tasks($columnName)";
            if (mysqli_query($conn, $index_sql)) {
                $results[$indexName] = ['created' => true, 'error' => null];
                dbLog("Index '$indexName' created successfully on fms_tasks table");
            } else {
                $results[$indexName] = ['created' => false, 'error' => mysqli_error($conn)];
                dbLog("Error creating index '$indexName' on fms_tasks table: " . mysqli_error($conn));
            }
        } else {
            $results[$indexName] = ['created' => false, 'exists' => true, 'error' => null];
            dbLog("Index '$indexName' already exists on fms_tasks table");
        }
    }
    
    return $results;
}

/**
 * Create performance indexes for the tasks table
 * 
 * @param mysqli $conn Database connection
 * @return array Array with status of each index creation
 */
function createTasksIndexes($conn) {
    $indexes = [
        'idx_tasks_doer_id' => 'doer_id',
        'idx_tasks_status' => 'status',
        'idx_tasks_planned_date' => 'planned_date',
        'idx_tasks_assigned_by' => 'assigned_by',
        'idx_tasks_created_at' => 'created_at'
    ];
    
    $results = [];
    
    foreach ($indexes as $indexName => $columnName) {
        if (!indexExists($conn, 'tasks', $indexName)) {
            $index_sql = "CREATE INDEX $indexName ON tasks($columnName)";
            if (mysqli_query($conn, $index_sql)) {
                $results[$indexName] = ['created' => true, 'error' => null];
                dbLog("Index '$indexName' created successfully on tasks table");
            } else {
                $results[$indexName] = ['created' => false, 'error' => mysqli_error($conn)];
                dbLog("Error creating index '$indexName' on tasks table: " . mysqli_error($conn));
            }
        } else {
            $results[$indexName] = ['created' => false, 'exists' => true, 'error' => null];
            dbLog("Index '$indexName' already exists on tasks table");
        }
    }
    
    return $results;
}

/**
 * Check and create all required database tables
 * 
 * @param mysqli $conn Database connection
 * @return array Array with status of each table check/creation
 */
function checkAndCreateTables($conn) {
    global $DB_TABLES, $TABLE_CREATION_ORDER;
    
    // Include the schema definitions
    require_once __DIR__ . '/db_schema.php';
    
    $results = [];
    $success = true;
    
    // First, check which tables need to be created
    $tables_to_create = [];
    foreach ($TABLE_CREATION_ORDER as $table) {
        if (!tableExists($conn, $table)) {
            $tables_to_create[] = $table;
            $results[$table] = ['exists' => false, 'created' => false, 'error' => null];
        } else {
            $results[$table] = ['exists' => true, 'created' => false, 'error' => null];
        }
    }
    
    // Even if all tables exist, run non-destructive migrations to ensure columns/indexes
    if (tableExists($conn, 'users')) {
        ensureUsersColumns($conn);
    }
    if (tableExists($conn, 'tasks')) {
        ensureTasksColumns($conn);
        $tasks_index_results = createTasksIndexes($conn);
        $results['tasks_indexes'] = $tasks_index_results;
    }
    if (tableExists($conn, 'fms_tasks')) {
        $index_results = createFmsTasksIndexes($conn);
        $results['fms_tasks_indexes'] = $index_results;
        ensureFmsTasksColumns($conn);
    }
    if (tableExists($conn, 'password_reset_requests')) {
        ensurePasswordResetRequestsColumns($conn);
    }
    if (tableExists($conn, 'updates')) {
        ensureUpdatesColumns($conn);
    }

    // If no tables need to be created, return early
    if (empty($tables_to_create)) {
        return ['success' => true, 'message' => 'All tables already exist', 'tables' => $results];
    }
    
    // Create tables in the specified order
    foreach ($TABLE_CREATION_ORDER as $table) {
        if (in_array($table, $tables_to_create)) {
            try {
                $sql = $DB_TABLES[$table]['sql'];
                
                // Execute the CREATE TABLE statement
                if ($conn->query($sql) === TRUE) {
                    $results[$table]['created'] = true;
                    
                    // Post-create ensure routines per table
                    if ($table === 'fms_tasks') {
                        $index_results = createFmsTasksIndexes($conn);
                        $results['fms_tasks_indexes'] = $index_results;
                        // Ensure expected columns exist even on fresh create
                        ensureFmsTasksColumns($conn);
                    } else if ($table === 'users') {
                        ensureUsersColumns($conn);
                    } else if ($table === 'tasks') {
                        ensureTasksColumns($conn);
                    } else if ($table === 'password_reset_requests') {
                        ensurePasswordResetRequestsColumns($conn);
                    }
                } else {
                    $results[$table]['error'] = $conn->error;
                    $success = false;
                }
            } catch (Exception $e) {
                $results[$table]['error'] = $e->getMessage();
                $success = false;
            }
        }
    }
    
    // Create indexes for fms_tasks if it already existed
    if (!in_array('fms_tasks', $tables_to_create) && tableExists($conn, 'fms_tasks')) {
        $index_results = createFmsTasksIndexes($conn);
        $results['fms_tasks_indexes'] = $index_results;
        // Ensure expected columns exist if table pre-existed
        ensureFmsTasksColumns($conn);
    }
    
    return [
        'success' => $success,
        'message' => $success ? 'All required tables created successfully' : 'Some tables could not be created',
        'tables' => $results
    ];
}

/**
 * Log database operations to a file
 * 
 * @param string $message Message to log
 * @return void
 */
function dbLog($message) {
    $log_file = __DIR__ . '/../logs/db_operations.log';
    $dir = dirname($log_file);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message" . PHP_EOL;
    
    // Append to log file
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

/**
 * Check database tables and create any that are missing
 * 
 * @param mysqli $conn Database connection
 * @param bool $verbose Whether to output status messages
 * @return bool True if all tables exist or were created, false otherwise
 */
function ensureDatabaseTables($conn, $verbose = false) {
    // Check and create tables
    $result = checkAndCreateTables($conn);
    
    // Log the result
    if ($result['success']) {
        dbLog("Database check completed successfully: " . $result['message']);
    } else {
        dbLog("Database check failed: " . $result['message']);
        
        // Log details of failures
        foreach ($result['tables'] as $table => $status) {
            if ($table !== 'fms_tasks_indexes' && !$status['exists'] && !$status['created']) {
                dbLog("Failed to create table '$table': " . $status['error']);
            }
        }
    }
    
    // Output status if verbose mode is enabled
    if ($verbose) {
        echo "<div class='db-status'>";
        echo "<h3>Database Status</h3>";
        
        if ($result['success']) {
            echo "<p class='success'>" . $result['message'] . "</p>";
        } else {
            echo "<p class='error'>" . $result['message'] . "</p>";
        }
        
        echo "<ul>";
        foreach ($result['tables'] as $table => $status) {
            if ($table === 'fms_tasks_indexes') {
                echo "<li>FMS Tasks Indexes:";
                echo "<ul>";
                foreach ($status as $indexName => $indexStatus) {
                    if (isset($indexStatus['exists']) && $indexStatus['exists']) {
                        echo "<li>Index '$indexName': Already exists</li>";
                    } else if ($indexStatus['created']) {
                        echo "<li>Index '$indexName': Created successfully</li>";
                    } else {
                        echo "<li class='error'>Index '$indexName': Creation failed - " . $indexStatus['error'] . "</li>";
                    }
                }
                echo "</ul>";
                echo "</li>";
            } else {
                if ($status['exists']) {
                    echo "<li>Table '$table': Already exists</li>";
                } else if ($status['created']) {
                    echo "<li>Table '$table': Created successfully</li>";
                } else {
                    echo "<li class='error'>Table '$table': Creation failed - " . $status['error'] . "</li>";
                }
            }
        }
        echo "</ul>";
        echo "</div>";
    }
    
    return $result['success'];
}
?> 