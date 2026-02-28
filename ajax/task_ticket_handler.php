<?php
/**
 * Task & Ticket Handler
 * Handles CRUD operations for client_taskflow table
 */

// Suppress error display, but log errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

require_once "../includes/config.php";
require_once "../includes/functions.php";
require_once "../includes/notification_triggers.php";

// Check if user is logged in
if (!isLoggedIn()) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(401);
    jsonError('Unauthorized', 401);
}

// Check if user is Client, Manager, or Admin
if (!isClient() && !isManager() && !isAdmin()) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(403);
    jsonError('Access denied', 403);
}

// CSRF protection for POST requests
csrfProtect();

$user_id = $_SESSION['id'] ?? $_SESSION['user_id'] ?? 0;
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Download action doesn't return JSON - clear buffer and don't set JSON header
if ($action === 'download_attachment') {
    ob_clean();
    downloadAttachment();
    exit;
}

// Set JSON header for all other actions
header('Content-Type: application/json');

// Ensure table exists (wrap in try-catch to prevent fatal errors)
try {
    ensureClientTaskflowTable($conn);
} catch (Exception $e) {
    error_log("Error ensuring table exists: " . $e->getMessage());
    // Continue anyway - table might already exist
}

try {
    switch ($action) {
        case 'get_items':
            getItems($conn, $user_id);
            break;
        case 'get_item':
            getItem($conn, $user_id);
            break;
        case 'create_item':
            createItem($conn, $user_id);
            break;
        case 'update_item':
            updateItem($conn, $user_id);
            break;
        case 'delete_item':
            deleteItem($conn, $user_id);
            break;
        case 'update_status':
            updateStatus($conn, $user_id);
            break;
        case 'provide_requirement':
            provideRequirement($conn, $user_id);
            break;
            
        case 'update_provided':
            updateProvided($conn, $user_id);
            break;
        case 'get_filter_options':
            getFilterOptions($conn, $user_id);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    // Clear any output that might have been generated
    ob_clean();
    
    http_response_code(400);
    error_log("task_ticket_handler exception: " . $e->getMessage());
    handleException($e, 'task_ticket_handler');
    exit;
}

// End output buffering
ob_end_flush();

/**
 * Ensure client_taskflow table exists
 */
function ensureClientTaskflowTable($conn) {
    $table_exists = false;
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'client_taskflow'");
    if ($result && mysqli_num_rows($result) > 0) {
        $table_exists = true;
    }
    
    if (!$table_exists) {
        // First, try to create table without foreign keys (in case users table structure is different)
        $sql = "CREATE TABLE IF NOT EXISTS `client_taskflow` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `unique_id` VARCHAR(20) NOT NULL UNIQUE COMMENT 'Format: TAS001, TKT001, REQ001',
            `type` ENUM('Task', 'Ticket', 'Required') NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT,
            `status` VARCHAR(50) NOT NULL DEFAULT 'Assigned',
            `created_by` INT NOT NULL COMMENT 'User ID who created the item',
            `created_by_type` ENUM('Client', 'Manager') NOT NULL,
            `assigned_to` INT NULL COMMENT 'User ID assigned to (for Required items)',
            `attachments` JSON NULL COMMENT 'Array of attachment objects with name, size, type, path',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (`type`),
            INDEX (`status`),
            INDEX (`created_by`),
            INDEX (`created_at`),
            INDEX (`unique_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!mysqli_query($conn, $sql)) {
            $error = mysqli_error($conn);
            error_log("Failed to create client_taskflow table: " . $error);
            // Don't throw exception, just log - table might already exist or have different structure
        } else {
            // Try to add foreign keys if they don't exist (optional, won't fail if they already exist)
            $fk_sql1 = "ALTER TABLE `client_taskflow` 
                        ADD CONSTRAINT `fk_client_taskflow_created_by` 
                        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE";
            $fk_sql2 = "ALTER TABLE `client_taskflow` 
                        ADD CONSTRAINT `fk_client_taskflow_assigned_to` 
                        FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL";
            
            @mysqli_query($conn, $fk_sql1); // Suppress errors if FK already exists
            @mysqli_query($conn, $fk_sql2); // Suppress errors if FK already exists
        }
    }
    
    // Ensure edit timestamp columns exist
    $columns_to_add = [
        'title_edited_at' => "ALTER TABLE `client_taskflow` ADD COLUMN `title_edited_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Timestamp when title was last edited'",
        'description_edited_at' => "ALTER TABLE `client_taskflow` ADD COLUMN `description_edited_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Timestamp when description was last edited'",
        'attachments_edited_at' => "ALTER TABLE `client_taskflow` ADD COLUMN `attachments_edited_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Timestamp when attachments were last edited'",
        'status_updated_at' => "ALTER TABLE `client_taskflow` ADD COLUMN `status_updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Timestamp when status was last updated'",
        'provided_description' => "ALTER TABLE `client_taskflow` ADD COLUMN `provided_description` TEXT NULL COMMENT 'Description provided when requirement status changed to Provided'",
        'provided_attachments' => "ALTER TABLE `client_taskflow` ADD COLUMN `provided_attachments` JSON NULL COMMENT 'Attachments provided when requirement status changed to Provided'",
        'provided_edited_at' => "ALTER TABLE `client_taskflow` ADD COLUMN `provided_edited_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Timestamp when provided data was last updated'"
    ];
    
    foreach ($columns_to_add as $column_name => $sql) {
        $column_check = mysqli_query($conn, "SHOW COLUMNS FROM client_taskflow LIKE '$column_name'");
        if (!$column_check || mysqli_num_rows($column_check) == 0) {
            @mysqli_query($conn, $sql); // Suppress errors if column already exists
        }
    }
}

/**
 * Get all items
 */
function getItems($conn, $user_id) {
    try {
        $is_admin = isAdmin();
        $is_manager = isManager();
        $is_client = isClient();
        
        // Check if table exists first
        $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'client_taskflow'");
        if (!$table_check || mysqli_num_rows($table_check) == 0) {
            // Table doesn't exist, return empty array
            echo json_encode(['success' => true, 'items' => []]);
            return;
        }
        
        // Verify table has required columns
        $columns_check = mysqli_query($conn, "SHOW COLUMNS FROM client_taskflow LIKE 'unique_id'");
        if (!$columns_check || mysqli_num_rows($columns_check) == 0) {
            error_log("client_taskflow table exists but missing required columns");
            echo json_encode(['success' => true, 'items' => []]);
            return;
        }
        
        // Build base query with JOINs
        $sql = "SELECT ct.*, 
                u1.name as created_by_name, 
                u1.user_type as created_by_user_type,
                u1.manager_id as created_by_manager_id,
                u2.name as assigned_to_name,
                u3.name as client_account_name,
                u3.id as client_account_id
                FROM client_taskflow ct
                LEFT JOIN users u1 ON ct.created_by = u1.id
                LEFT JOIN users u2 ON ct.assigned_to = u2.id
                LEFT JOIN users u3 ON u1.manager_id = u3.id AND u3.user_type = 'client' AND (u3.password IS NULL OR u3.password = '')";
        
        // Apply role-based filtering
        $where_conditions = [];
        $params = [];
        $param_types = '';
        
        if ($is_manager && !$is_admin) {
            // Manager: Only see items related to their assigned client accounts
            // Get client accounts assigned to this manager
            $client_accounts_sql = "SELECT id FROM users WHERE user_type = 'client' AND (password IS NULL OR password = '') AND manager_id = ?";
            $client_accounts_stmt = mysqli_prepare($conn, $client_accounts_sql);
            mysqli_stmt_bind_param($client_accounts_stmt, 'i', $user_id);
            mysqli_stmt_execute($client_accounts_stmt);
            $client_accounts_result = mysqli_stmt_get_result($client_accounts_stmt);
            
            $client_account_ids = [$user_id]; // Include manager's own ID
            while ($account_row = mysqli_fetch_assoc($client_accounts_result)) {
                $client_account_ids[] = $account_row['id'];
            }
            mysqli_stmt_close($client_accounts_stmt);
            
            // Get client users under those accounts
            if (!empty($client_account_ids)) {
                $placeholders = implode(',', array_fill(0, count($client_account_ids), '?'));
                $client_users_sql = "SELECT id FROM users WHERE user_type = 'client' AND manager_id IN ($placeholders) AND password IS NOT NULL AND password != ''";
                $client_users_stmt = mysqli_prepare($conn, $client_users_sql);
                $types = str_repeat('i', count($client_account_ids));
                mysqli_stmt_bind_param($client_users_stmt, $types, ...$client_account_ids);
                mysqli_stmt_execute($client_users_stmt);
                $client_users_result = mysqli_stmt_get_result($client_users_stmt);
                
                $client_user_ids = [];
                while ($user_row = mysqli_fetch_assoc($client_users_result)) {
                    $client_user_ids[] = $user_row['id'];
                }
                mysqli_stmt_close($client_users_stmt);
                
                // Manager can see items:
                // 1. Created by themselves
                // 2. Created by client users under their assigned accounts
                // 3. Assigned to client users under their assigned accounts
                // 4. Created by client accounts assigned to them
                $allowed_user_ids = array_unique(array_merge([$user_id], $client_account_ids, $client_user_ids));
                
                if (!empty($allowed_user_ids)) {
                    // Use sanitized IDs directly in SQL for IN clause (they're already integers from database)
                    $sanitized_ids = array_map('intval', $allowed_user_ids);
                    $ids_string = implode(',', $sanitized_ids);
                    $where_conditions[] = "(ct.created_by IN ($ids_string) OR ct.assigned_to IN ($ids_string))";
                } else {
                    // No assigned accounts, return empty
                    $where_conditions[] = "1=0";
                }
            } else {
                // No assigned accounts, return empty
                $where_conditions[] = "1=0";
            }
        } elseif ($is_client) {
            // Client User: Only see items shared by their associated managers or related to their account
            // Get their manager (client account)
            $client_user_sql = "SELECT manager_id FROM users WHERE id = ?";
            $client_user_stmt = mysqli_prepare($conn, $client_user_sql);
            mysqli_stmt_bind_param($client_user_stmt, 'i', $user_id);
            mysqli_stmt_execute($client_user_stmt);
            $client_user_result = mysqli_stmt_get_result($client_user_stmt);
            $client_user_data = mysqli_fetch_assoc($client_user_result);
            mysqli_stmt_close($client_user_stmt);
            
            if ($client_user_data && !empty($client_user_data['manager_id'])) {
                $client_account_id = $client_user_data['manager_id'];
                
                // Get the manager assigned to this client account
                $account_sql = "SELECT manager_id FROM users WHERE id = ? AND user_type = 'client' AND (password IS NULL OR password = '')";
                $account_stmt = mysqli_prepare($conn, $account_sql);
                mysqli_stmt_bind_param($account_stmt, 'i', $client_account_id);
                mysqli_stmt_execute($account_stmt);
                $account_result = mysqli_stmt_get_result($account_stmt);
                $account_data = mysqli_fetch_assoc($account_result);
                mysqli_stmt_close($account_stmt);
                
                // Client user can see items only for their account (not other clients'):
                // 1. Created by themselves, their manager, or their client account
                // 2. For Required items: Only if assigned_to = current user OR created by their manager/account (not all admins)
                // 3. For Task/Ticket: Created by or assigned to any user under their client account only
                
                $allowed_user_ids = [$user_id, $client_account_id]; // Client user and their account
                if ($account_data && !empty($account_data['manager_id'])) {
                    $allowed_user_ids[] = $account_data['manager_id']; // Their manager
                }
                
                $item_conditions = [];
                $allowed_ids_string = implode(',', array_map('intval', $allowed_user_ids));
                
                // For Required items: Only show if assigned_to = current user OR created by allowed users (manager/account/client). Do NOT show Required items created by other admins for other accounts.
                $item_conditions[] = "(ct.type = 'Required' AND (ct.assigned_to = $user_id OR ct.created_by IN ($allowed_ids_string)))";
                
                // For Task/Ticket items: Show if created by or assigned to allowed users or any user under their account only
                $sibling_users_sql = "SELECT id FROM users WHERE manager_id = ? AND user_type = 'client' AND password IS NOT NULL AND password != ''";
                $sibling_users_stmt = mysqli_prepare($conn, $sibling_users_sql);
                mysqli_stmt_bind_param($sibling_users_stmt, 'i', $client_account_id);
                mysqli_stmt_execute($sibling_users_stmt);
                $sibling_users_result = mysqli_stmt_get_result($sibling_users_stmt);
                
                $sibling_user_ids = [];
                while ($sibling_row = mysqli_fetch_assoc($sibling_users_result)) {
                    $sibling_user_ids[] = $sibling_row['id'];
                }
                mysqli_stmt_close($sibling_users_stmt);
                
                if (!empty($sibling_user_ids)) {
                    $all_allowed_ids = array_unique(array_merge($allowed_user_ids, $sibling_user_ids));
                    $all_allowed_ids_string = implode(',', array_map('intval', $all_allowed_ids));
                    $item_conditions[] = "((ct.type = 'Task' OR ct.type = 'Ticket') AND (ct.created_by IN ($all_allowed_ids_string) OR ct.assigned_to IN ($all_allowed_ids_string)))";
                } else {
                    $item_conditions[] = "((ct.type = 'Task' OR ct.type = 'Ticket') AND (ct.created_by IN ($allowed_ids_string) OR ct.assigned_to IN ($allowed_ids_string)))";
                }
                
                if (!empty($item_conditions)) {
                    $where_conditions[] = "(" . implode(' OR ', $item_conditions) . ")";
                } else {
                    // No access, return empty
                    $where_conditions[] = "1=0";
                }
            } else {
                // No account association, return empty
                $where_conditions[] = "1=0";
            }
        }
        // Admin: No filtering needed - can see everything
        
        // Add WHERE clause if conditions exist
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(' AND ', $where_conditions);
        }
        
        $sql .= " ORDER BY ct.created_at DESC";
        
        // Execute query directly (IDs are sanitized)
        $result = mysqli_query($conn, $sql);
        
        // If query fails, try simpler query without JOIN
        if (!$result) {
            $error = mysqli_error($conn);
            error_log("getItems query failed: " . $error);
            
            // Try simpler query without JOIN - we'll fetch assigner info separately if needed
            $simple_sql = "SELECT * FROM client_taskflow";
            if (!empty($where_conditions)) {
                $simple_sql .= " WHERE " . implode(' AND ', $where_conditions);
            }
            $simple_sql .= " ORDER BY created_at DESC";
            
            $result = mysqli_query($conn, $simple_sql);
            
            if (!$result) {
                $error = mysqli_error($conn);
                error_log("getItems simple query also failed: " . $error);
                throw new Exception('Database error: ' . $error);
            }
        }
        
        $items = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Parse attachments JSON and deduplicate
            $attachments = [];
            if (!empty($row['attachments'])) {
                $attachments_raw = json_decode($row['attachments'], true);
                if (is_array($attachments_raw)) {
                    // Deduplicate attachments based on path or name+size
                    $seen = [];
                    foreach ($attachments_raw as $attachment) {
                        if (!is_array($attachment)) {
                            continue;
                        }
                        // Create unique key for each attachment
                        $key = isset($attachment['path']) ? $attachment['path'] : (isset($attachment['name']) ? $attachment['name'] . '_' . (isset($attachment['size']) ? $attachment['size'] : '0') : '');
                        if (!empty($key) && !in_array($key, $seen)) {
                            $attachments[] = $attachment;
                            $seen[] = $key;
                        } elseif (empty($key)) {
                            // If no key can be created, still add it (shouldn't happen, but safety check)
                            $attachments[] = $attachment;
                        }
                    }
                }
            }
            
            // Parse provided attachments JSON
            $provided_attachments = [];
            if (!empty($row['provided_attachments'])) {
                $provided_attachments_raw = json_decode($row['provided_attachments'], true);
                if (is_array($provided_attachments_raw)) {
                    $provided_attachments = $provided_attachments_raw;
                }
            }
            
            // Use status_updated_at for lastUpdated if status was updated, otherwise use updated_at
            $last_updated = $row['status_updated_at'] ?? $row['updated_at'] ?? $row['created_at'] ?? date('Y-m-d H:i:s');
            
            // Determine assigner information
            $assigner_info = null;
            $assigner_account_name = null;
            $assigner_user_name = null;
            
            // If created_by_name is not available from JOIN, fetch it separately
            if (empty($row['created_by_name']) && !empty($row['created_by'])) {
                $creator_sql = "SELECT name, manager_id FROM users WHERE id = ?";
                $creator_stmt = mysqli_prepare($conn, $creator_sql);
                if ($creator_stmt) {
                    mysqli_stmt_bind_param($creator_stmt, "i", $row['created_by']);
                    mysqli_stmt_execute($creator_stmt);
                    $creator_result = mysqli_stmt_get_result($creator_stmt);
                    if ($creator_row = mysqli_fetch_assoc($creator_result)) {
                        $row['created_by_name'] = $creator_row['name'];
                        $row['created_by_manager_id'] = $creator_row['manager_id'];
                    }
                    mysqli_stmt_close($creator_stmt);
                }
            }
            
            // For Required items: Get assigned client user info for "Assigned To" column
            // Store in separate variables to avoid confusion with assigner fields
            $assigned_to_account_name = null;
            $assigned_to_user_name = null;
            
            if ($row['type'] === 'Required' && !empty($row['assigned_to'])) {
                // Fetch the assigned client user's information
                $assigned_user_sql = "SELECT name, manager_id FROM users WHERE id = ?";
                $assigned_user_stmt = mysqli_prepare($conn, $assigned_user_sql);
                if ($assigned_user_stmt) {
                    mysqli_stmt_bind_param($assigned_user_stmt, "i", $row['assigned_to']);
                    mysqli_stmt_execute($assigned_user_stmt);
                    $assigned_user_result = mysqli_stmt_get_result($assigned_user_stmt);
                    if ($assigned_user_row = mysqli_fetch_assoc($assigned_user_result)) {
                        $assigned_to_user_name = $assigned_user_row['name'];
                        $client_account_id = $assigned_user_row['manager_id'];
                        
                        // Fetch the client account name
                        if (!empty($client_account_id)) {
                            $account_sql = "SELECT name FROM users WHERE id = ? AND user_type = 'client' AND (password IS NULL OR password = '')";
                            $account_stmt = mysqli_prepare($conn, $account_sql);
                            if ($account_stmt) {
                                mysqli_stmt_bind_param($account_stmt, "i", $client_account_id);
                                mysqli_stmt_execute($account_stmt);
                                $account_result = mysqli_stmt_get_result($account_stmt);
                                if ($account_row = mysqli_fetch_assoc($account_result)) {
                                    $assigned_to_account_name = $account_row['name'];
                                }
                                mysqli_stmt_close($account_stmt);
                            }
                        }
                    }
                    mysqli_stmt_close($assigned_user_stmt);
                }
            }
            
            // Store assigner info separately for "Assigner" column (who created it)
            $assigner_account_name_for_creator = null;
            $assigner_user_name_for_creator = null;
            
            // If created by a client user (has manager_id pointing to client account)
            // This is for the "Assigner" column
            if (!empty($row['created_by_manager_id'])) {
                // Fetch client account name if not already available
                if (empty($row['client_account_name'])) {
                    $account_sql = "SELECT name FROM users WHERE id = ? AND user_type = 'client' AND (password IS NULL OR password = '')";
                    $account_stmt = mysqli_prepare($conn, $account_sql);
                    if ($account_stmt) {
                        mysqli_stmt_bind_param($account_stmt, "i", $row['created_by_manager_id']);
                        mysqli_stmt_execute($account_stmt);
                        $account_result = mysqli_stmt_get_result($account_stmt);
                        if ($account_row = mysqli_fetch_assoc($account_result)) {
                            $row['client_account_name'] = $account_row['name'];
                        }
                        mysqli_stmt_close($account_stmt);
                    }
                }
                
                // If we have both account name and user name, use them for assigner (creator)
                if (!empty($row['client_account_name']) && !empty($row['created_by_name'])) {
                    $assigner_account_name_for_creator = $row['client_account_name'];
                    $assigner_user_name_for_creator = $row['created_by_name'];
                } else {
                    // Fallback to just the creator name
                    $assigner_info = $row['created_by_name'] ?? null;
                }
            } else {
                // This is a manager or client account - show the creator name
                $assigner_info = $row['created_by_name'] ?? null;
            }
            
            // For "Assigned To" column: Use assigned_to info for Required items
            // For "Assigner" column: Use created_by info
            // Since frontend uses assigner_account_name/assigner_user_name for "Assigned To", we'll use those
            if ($row['type'] === 'Required' && !empty($assigned_to_account_name) && !empty($assigned_to_user_name)) {
                $assigner_account_name = $assigned_to_account_name;
                $assigner_user_name = $assigned_to_user_name;
            } else if (!empty($assigner_account_name_for_creator) && !empty($assigner_user_name_for_creator)) {
                // For non-Required items or Required items created by client users: Use creator info
                $assigner_account_name = $assigner_account_name_for_creator;
                $assigner_user_name = $assigner_user_name_for_creator;
            }
            
            $items[] = [
                'id' => $row['unique_id'] ?? '',
                'db_id' => $row['id'] ?? 0,
                'type' => $row['type'] ?? '',
                'title' => $row['title'] ?? '',
                'description' => $row['description'] ?? '',
                'status' => $row['status'] ?? 'Assigned',
                'createdBy' => $row['created_by_type'] ?? '',
                'created_by' => $row['created_by'] ?? null,
                'created_by_name' => $row['created_by_name'] ?? null,
                'created_by_manager_id' => $row['created_by_manager_id'] ?? null,
                'assigned_to' => $row['assigned_to'] ?? null,
                'assigned_to_name' => $row['assigned_to_name'] ?? null,
                'assigner_info' => $assigner_info,
                'assigner_account_name' => $assigner_account_name,
                'assigner_user_name' => $assigner_user_name,
                'attachments' => $attachments,
                'lastUpdated' => $last_updated,
                'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                'status_updated_at' => $row['status_updated_at'] ?? null,
                'title_edited_at' => $row['title_edited_at'] ?? null,
                'description_edited_at' => $row['description_edited_at'] ?? null,
                'attachments_edited_at' => $row['attachments_edited_at'] ?? null,
                'provided_description' => $row['provided_description'] ?? null,
                'provided_attachments' => $provided_attachments,
                'provided_edited_at' => $row['provided_edited_at'] ?? null
            ];
        }
        
        if ($result) {
            mysqli_free_result($result);
        }
        
        // Include current user ID in response for frontend comparison
        $current_user_id = $_SESSION['id'] ?? $_SESSION['user_id'] ?? 0;
        echo json_encode(['success' => true, 'items' => $items, 'current_user_id' => $current_user_id]);
    } catch (Exception $e) {
        error_log("getItems exception: " . $e->getMessage());
        // Return empty array instead of throwing to prevent 500 error
        echo json_encode(['success' => true, 'items' => [], 'message' => $e->getMessage()]);
    }
}

/**
 * Get single item
 */
function getItem($conn, $user_id) {
    $is_admin = isAdmin();
    $is_manager = isManager();
    $is_client = isClient();
    
    $item_id = $_GET['id'] ?? $_POST['id'] ?? '';
    
    if (empty($item_id)) {
        throw new Exception('Item ID is required');
    }
    
    $sql = "SELECT ct.*, 
            u1.name as created_by_name, 
            u1.user_type as created_by_user_type,
            u1.manager_id as created_by_manager_id,
            u2.name as assigned_to_name,
            u3.name as client_account_name,
            u3.id as client_account_id
            FROM client_taskflow ct
            LEFT JOIN users u1 ON ct.created_by = u1.id
            LEFT JOIN users u2 ON ct.assigned_to = u2.id
            LEFT JOIN users u3 ON u1.manager_id = u3.id AND u3.user_type = 'client' AND (u3.password IS NULL OR u3.password = '')
            WHERE (ct.unique_id = ? OR ct.id = ?)";
    
    // Add access control based on user role
    $access_condition = "";
    if ($is_manager && !$is_admin) {
        // Manager: Only see items related to their assigned client accounts
        $client_accounts_sql = "SELECT id FROM users WHERE user_type = 'client' AND (password IS NULL OR password = '') AND manager_id = ?";
        $client_accounts_stmt = mysqli_prepare($conn, $client_accounts_sql);
        mysqli_stmt_bind_param($client_accounts_stmt, 'i', $user_id);
        mysqli_stmt_execute($client_accounts_stmt);
        $client_accounts_result = mysqli_stmt_get_result($client_accounts_stmt);
        
        $client_account_ids = [$user_id];
        while ($account_row = mysqli_fetch_assoc($client_accounts_result)) {
            $client_account_ids[] = $account_row['id'];
        }
        mysqli_stmt_close($client_accounts_stmt);
        
        // Get client users under those accounts
        if (!empty($client_account_ids)) {
            $sanitized_ids = array_map('intval', $client_account_ids);
            $placeholders = implode(',', $sanitized_ids);
            $client_users_sql = "SELECT id FROM users WHERE user_type = 'client' AND manager_id IN ($placeholders) AND password IS NOT NULL AND password != ''";
            $client_users_result = mysqli_query($conn, $client_users_sql);
            
            $client_user_ids = [];
            while ($user_row = mysqli_fetch_assoc($client_users_result)) {
                $client_user_ids[] = $user_row['id'];
            }
            
            $allowed_user_ids = array_unique(array_merge($client_account_ids, $client_user_ids));
            $sanitized_allowed = array_map('intval', $allowed_user_ids);
            $ids_string = implode(',', $sanitized_allowed);
            $access_condition = " AND (ct.created_by IN ($ids_string) OR ct.assigned_to IN ($ids_string))";
        } else {
            throw new Exception('Access denied');
        }
    } elseif ($is_client) {
        // Client User: Only see items shared by their associated managers or related to their account
        $client_user_sql = "SELECT manager_id FROM users WHERE id = ?";
        $client_user_stmt = mysqli_prepare($conn, $client_user_sql);
        mysqli_stmt_bind_param($client_user_stmt, 'i', $user_id);
        mysqli_stmt_execute($client_user_stmt);
        $client_user_result = mysqli_stmt_get_result($client_user_stmt);
        $client_user_data = mysqli_fetch_assoc($client_user_result);
        mysqli_stmt_close($client_user_stmt);
        
        if ($client_user_data && !empty($client_user_data['manager_id'])) {
            $client_account_id = $client_user_data['manager_id'];
            
            $account_sql = "SELECT manager_id FROM users WHERE id = ? AND user_type = 'client' AND (password IS NULL OR password = '')";
            $account_stmt = mysqli_prepare($conn, $account_sql);
            mysqli_stmt_bind_param($account_stmt, 'i', $client_account_id);
            mysqli_stmt_execute($account_stmt);
            $account_result = mysqli_stmt_get_result($account_stmt);
            $account_data = mysqli_fetch_assoc($account_result);
            mysqli_stmt_close($account_stmt);
            
            // For Required items: Only show if assigned_to = current user OR created by allowed users (manager/account/client). Not other admins for other accounts.
            // For other items: Show if created by or assigned to allowed users or any user under their account only
            $allowed_user_ids = [$user_id, $client_account_id];
            if ($account_data && !empty($account_data['manager_id'])) {
                $allowed_user_ids[] = $account_data['manager_id'];
            }
            
            $allowed_ids_string = implode(',', array_map('intval', $allowed_user_ids));
            
            $access_conditions = [];
            $access_conditions[] = "(ct.type = 'Required' AND (ct.assigned_to = $user_id OR ct.created_by IN ($allowed_ids_string)))";
            
            // For Task/Ticket: Include sibling users
            $sibling_users_sql = "SELECT id FROM users WHERE manager_id = ? AND user_type = 'client' AND password IS NOT NULL AND password != ''";
            $sibling_users_stmt = mysqli_prepare($conn, $sibling_users_sql);
            mysqli_stmt_bind_param($sibling_users_stmt, 'i', $client_account_id);
            mysqli_stmt_execute($sibling_users_stmt);
            $sibling_users_result = mysqli_stmt_get_result($sibling_users_stmt);
            
            $sibling_user_ids = [];
            while ($sibling_row = mysqli_fetch_assoc($sibling_users_result)) {
                $sibling_user_ids[] = $sibling_row['id'];
            }
            mysqli_stmt_close($sibling_users_stmt);
            
            if (!empty($sibling_user_ids)) {
                $all_allowed_ids = array_unique(array_merge($allowed_user_ids, $sibling_user_ids));
                $all_allowed_ids_string = implode(',', array_map('intval', $all_allowed_ids));
                $access_conditions[] = "((ct.type = 'Task' OR ct.type = 'Ticket') AND (ct.created_by IN ($all_allowed_ids_string) OR ct.assigned_to IN ($all_allowed_ids_string)))";
            } else {
                $access_conditions[] = "((ct.type = 'Task' OR ct.type = 'Ticket') AND (ct.created_by IN ($allowed_ids_string) OR ct.assigned_to IN ($allowed_ids_string)))";
            }
            
            $access_condition = " AND (" . implode(' OR ', $access_conditions) . ")";
        } else {
            throw new Exception('Access denied');
        }
    }
    // Admin: No access restriction needed
    
    $sql .= $access_condition;
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log("[DB Error] Database error: " . mysqli_error($conn)); throw new Exception('A database error occurred');
    }
    
    mysqli_stmt_bind_param($stmt, 'ss', $item_id, $item_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $row = mysqli_fetch_assoc($result);
    if (!$row) {
        throw new Exception('Item not found or access denied');
    }
    
    // Parse attachments JSON
    $attachments = [];
    if (!empty($row['attachments'])) {
        $attachments = json_decode($row['attachments'], true) ?: [];
    }
    
    // Parse provided attachments JSON
    $provided_attachments = [];
    if (!empty($row['provided_attachments'])) {
        $provided_attachments_raw = json_decode($row['provided_attachments'], true);
        if (is_array($provided_attachments_raw)) {
            $provided_attachments = $provided_attachments_raw;
        }
    }
    
    // Determine assigner information
    $assigner_info = null;
    $assigner_account_name = null;
    $assigner_user_name = null;
    
    // If created by a client user (has manager_id pointing to client account)
    // Note: For Required items created by managers/admins, assigner should be the creator (manager/admin), not the assigned user
    if (!empty($row['created_by_manager_id']) && !empty($row['client_account_name'])) {
        // This is a client user - show account name and user name
        $assigner_account_name = $row['client_account_name'] ?? null;
        $assigner_user_name = $row['created_by_name'] ?? null;
    } else {
        // This is a manager or client account - show the creator name
        $assigner_info = $row['created_by_name'] ?? null;
    }
    
    $item = [
        'id' => $row['unique_id'],
        'db_id' => $row['id'],
        'type' => $row['type'],
        'title' => $row['title'],
        'description' => $row['description'],
        'status' => $row['status'],
        'createdBy' => $row['created_by_type'],
        'created_by_name' => $row['created_by_name'],
        'created_by_manager_id' => $row['created_by_manager_id'] ?? null,
        'assigned_to' => $row['assigned_to'],
        'assigned_to_name' => $row['assigned_to_name'],
        'assigner_info' => $assigner_info,
        'assigner_account_name' => $assigner_account_name,
        'assigner_user_name' => $assigner_user_name,
        'attachments' => $attachments,
        'lastUpdated' => $row['updated_at'],
        'created_at' => $row['created_at'],
        'provided_description' => $row['provided_description'] ?? null,
        'provided_attachments' => $provided_attachments,
        'provided_edited_at' => $row['provided_edited_at'] ?? null
    ];
    
    // Include current user ID in response for frontend comparison
    $current_user_id = $_SESSION['id'] ?? $_SESSION['user_id'] ?? 0;
    echo json_encode(['success' => true, 'item' => $item, 'current_user_id' => $current_user_id]);
}

/**
 * Generate unique ID for client_taskflow items
 */
function generateClientTaskflowUniqueId($conn, $type) {
    $prefixMap = [
        'Task' => 'TAS',
        'Ticket' => 'TKT',
        'Required' => 'REQ'
    ];
    $prefix = $prefixMap[$type] ?? 'ITM';
    
    // Get the highest number for this prefix
    // Use mysqli_real_escape_string for safety instead of prepared statement
    $like_pattern = mysqli_real_escape_string($conn, $prefix . '%');
    $sql = "SELECT unique_id FROM client_taskflow WHERE unique_id LIKE '$like_pattern' ORDER BY unique_id DESC LIMIT 1";
    
    $result = mysqli_query($conn, $sql);
    
    $maxNum = 0;
    if ($result) {
        if ($row = mysqli_fetch_assoc($result)) {
            $numStr = str_replace($prefix, '', $row['unique_id']);
            $maxNum = intval($numStr) ?: 0;
        }
        mysqli_free_result($result);
    } else {
        // If query fails, log error but continue with default
        error_log("Failed to query for unique_id in generateClientTaskflowUniqueId: " . mysqli_error($conn));
    }
    
    $nextNum = $maxNum + 1;
    return $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
}

/**
 * Create new item
 */
function createItem($conn, $user_id) {
    try {
        $type = trim($_POST['type'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($type) || !in_array($type, ['Task', 'Ticket', 'Required'])) {
            throw new Exception('Invalid type');
        }
        
        if (empty($title)) {
            throw new Exception('Title is required');
        }
        
        $created_by_type = isManager() ? 'Manager' : 'Client';
        
        // Set status based on type
        // Tickets start with 'Raised', Tasks start with 'Assigned', Required start with 'Requested'
        if ($type === 'Ticket') {
            $status = 'Raised';
        } else if ($type === 'Required') {
            $status = 'Requested';
        } else {
            $status = 'Assigned';
        }
        
        // For Required items, check if client account and users are provided
        $client_account_id = null;
        $client_user_ids = [];
        if ($type === 'Required' && (isAdmin() || isManager())) {
            $client_account_id = isset($_POST['client_account_id']) ? intval($_POST['client_account_id']) : 0;
            $client_user_ids_json = $_POST['client_user_ids'] ?? '[]';
            $client_user_ids = json_decode($client_user_ids_json, true);
            
            if (!is_array($client_user_ids)) {
                $client_user_ids = [];
            }
            
            // Validate client account and users
            if ($client_account_id <= 0) {
                throw new Exception('Client account is required for requirements');
            }
            
            if (empty($client_user_ids)) {
                throw new Exception('At least one client user must be selected');
            }
            
            // Verify client account exists and user has access
            $is_admin = isAdmin();
            $is_manager = isManager();
            
            $verify_sql = "SELECT id, manager_id FROM users 
                          WHERE id = ? AND user_type = 'client' AND (password IS NULL OR password = '')";
            $verify_stmt = mysqli_prepare($conn, $verify_sql);
            mysqli_stmt_bind_param($verify_stmt, 'i', $client_account_id);
            mysqli_stmt_execute($verify_stmt);
            $verify_result = mysqli_stmt_get_result($verify_stmt);
            $account_data = mysqli_fetch_assoc($verify_result);
            mysqli_stmt_close($verify_stmt);
            
            if (!$account_data) {
                throw new Exception('Client account not found');
            }
            
            // For managers, verify they have access to this account
            if ($is_manager && !$is_admin && $account_data['manager_id'] != $user_id) {
                throw new Exception('Access denied to this client account');
            }
            
            // Verify all client users belong to the selected account
            $user_ids_placeholders = implode(',', array_fill(0, count($client_user_ids), '?'));
            $verify_users_sql = "SELECT id FROM users 
                               WHERE id IN ($user_ids_placeholders) 
                               AND user_type = 'client' 
                               AND manager_id = ? 
                               AND password IS NOT NULL 
                               AND password != ''";
            $verify_users_stmt = mysqli_prepare($conn, $verify_users_sql);
            
            $params = array_merge($client_user_ids, [$client_account_id]);
            $types = str_repeat('i', count($client_user_ids)) . 'i';
            mysqli_stmt_bind_param($verify_users_stmt, $types, ...$params);
            mysqli_stmt_execute($verify_users_stmt);
            $verify_users_result = mysqli_stmt_get_result($verify_users_stmt);
            
            $valid_user_ids = [];
            while ($row = mysqli_fetch_assoc($verify_users_result)) {
                $valid_user_ids[] = $row['id'];
            }
            mysqli_stmt_close($verify_users_stmt);
            
            if (count($valid_user_ids) !== count($client_user_ids)) {
                throw new Exception('One or more selected client users are invalid');
            }
        }
    
    // Handle attachments
    $attachments = [];
    
    // Handle file uploads
    if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
        $upload_dir = '../uploads/task_ticket/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_count = count($_FILES['attachments']['name']);
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['attachments']['name'][$i];
                $file_tmp = $_FILES['attachments']['tmp_name'][$i];
                $file_size = $_FILES['attachments']['size'][$i];
                $file_type = $_FILES['attachments']['type'][$i];
                
                // Check file size (50MB limit)
                $max_size = 50 * 1024 * 1024; // 50MB
                if ($file_size > $max_size) {
                    throw new Exception("File '{$file_name}' exceeds 50MB limit");
                }
                
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'avi', 'mov', 'mp3', 'wav'];
                
                if (in_array($file_ext, $allowed_extensions)) {
                    $new_file_name = uniqid() . '_' . time() . '_' . $i . '.' . $file_ext;
                    $file_path = $upload_dir . $new_file_name;
                    
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        $attachments[] = [
                            'name' => $file_name,
                            'size' => $file_size,
                            'type' => $file_type,
                            'path' => 'uploads/task_ticket/' . $new_file_name
                        ];
                    }
                }
            }
        }
    }
    
    // Handle base64 attachments from client
    if (!empty($_POST['attachments_json'])) {
        $client_attachments = json_decode($_POST['attachments_json'], true);
        if (is_array($client_attachments)) {
            foreach ($client_attachments as $attachment) {
                if (isset($attachment['fileData']) && !empty($attachment['fileData'])) {
                    // Save base64 file to server
                    $upload_dir = '../uploads/task_ticket/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    try {
                        $file_data = base64_decode($attachment['fileData']);
                        $file_ext = pathinfo($attachment['name'], PATHINFO_EXTENSION);
                        $new_file_name = uniqid() . '_' . time() . '.' . $file_ext;
                        $file_path = $upload_dir . $new_file_name;
                        
                        if (file_put_contents($file_path, $file_data)) {
                            $attachments[] = [
                                'name' => $attachment['name'],
                                'size' => $attachment['size'] ?? strlen($file_data),
                                'type' => $attachment['type'] ?? 'application/octet-stream',
                                'path' => 'uploads/task_ticket/' . $new_file_name
                            ];
                        }
                    } catch (Exception $e) {
                        error_log("Failed to save base64 attachment: " . $e->getMessage());
                    }
                } else if (isset($attachment['path'])) {
                    // Already uploaded file
                    $attachments[] = $attachment;
                }
            }
        }
    }
    
        $attachments_json = !empty($attachments) ? json_encode($attachments) : null;
        
        // Set status_updated_at to current time when creating item with initial status
        $current_time = date('Y-m-d H:i:s');
        $escaped_time = mysqli_real_escape_string($conn, $current_time);
        
        // For Required items with multiple users, create one requirement per user
        if ($type === 'Required' && !empty($client_user_ids)) {
            $created_items = [];
            $first_unique_id = null;
            
            foreach ($client_user_ids as $client_user_id) {
                // Generate unique ID for each requirement
                $unique_id = generateClientTaskflowUniqueId($conn, $type);
                if (empty($unique_id)) {
                    throw new Exception('Failed to generate unique ID');
                }
                
                if ($first_unique_id === null) {
                    $first_unique_id = $unique_id;
                }
                
                $sql = "INSERT INTO client_taskflow (unique_id, type, title, description, status, created_by, created_by_type, assigned_to, attachments, status_updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '$escaped_time')";
                
                $stmt = mysqli_prepare($conn, $sql);
                if (!$stmt) {
                    $error = mysqli_error($conn);
                    error_log("Failed to prepare INSERT statement: " . $error);
                    throw new Exception('Database error: ' . $error);
                }
                
                // Format string: s-s-s-s-s-i-s-i-s = 9 chars for 9 parameters
                // Parameters: unique_id(s), type(s), title(s), description(s), status(s), user_id(i), created_by_type(s), client_user_id(i), attachments_json(s)
                $format = 's' . 's' . 's' . 's' . 's' . 'i' . 's' . 'i' . 's'; // 9 chars: s-s-s-s-s-i-s-i-s
                mysqli_stmt_bind_param($stmt, $format, $unique_id, $type, $title, $description, $status, $user_id, $created_by_type, $client_user_id, $attachments_json);
                
                if (!mysqli_stmt_execute($stmt)) {
                    $error = mysqli_error($conn);
                    error_log("Failed to execute INSERT statement: " . $error);
                    mysqli_stmt_close($stmt);
                    throw new Exception('Failed to create item: ' . $error);
                }
                
                $item_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);
                
                $created_items[] = [
                    'id' => $unique_id,
                    'db_id' => $item_id,
                    'type' => $type,
                    'title' => $title
                ];
                
                // Send notification to client user
                // Get creator's name
                $creator_sql = "SELECT name FROM users WHERE id = ?";
                $creator_stmt = mysqli_prepare($conn, $creator_sql);
                $created_by_name = 'Manager/Admin';
                if ($creator_stmt) {
                    mysqli_stmt_bind_param($creator_stmt, 'i', $user_id);
                    mysqli_stmt_execute($creator_stmt);
                    $creator_result = mysqli_stmt_get_result($creator_stmt);
                    $creator = mysqli_fetch_assoc($creator_result);
                    if ($creator) {
                        $created_by_name = $creator['name'];
                    }
                    mysqli_stmt_close($creator_stmt);
                }
                
                // Trigger notification for this client user
                triggerClientRequirementNotification($conn, $unique_id, $client_user_id, $title, $created_by_name);
            }
            
            echo json_encode([
                'success' => true, 
                'message' => count($created_items) . ' requirement(s) created successfully',
                'item' => [
                    'id' => $first_unique_id,
                    'db_id' => $created_items[0]['db_id'],
                    'type' => $type,
                    'title' => $title
                ],
                'created_count' => count($created_items)
            ]);
        } else {
            // Single item creation (for Task, Ticket, or Required without user selection)
            $unique_id = generateClientTaskflowUniqueId($conn, $type);
            if (empty($unique_id)) {
                throw new Exception('Failed to generate unique ID');
            }
            
            // For Task and Ticket items created by clients, assign to their manager
            $assigned_to_manager_id = null;
            if (($type === 'Task' || $type === 'Ticket') && isClient()) {
                // Get client user's manager_id (which points to their client account)
                $client_user_sql = "SELECT manager_id FROM users WHERE id = ?";
                $client_user_stmt = mysqli_prepare($conn, $client_user_sql);
                if ($client_user_stmt) {
                    mysqli_stmt_bind_param($client_user_stmt, 'i', $user_id);
                    mysqli_stmt_execute($client_user_stmt);
                    $client_user_result = mysqli_stmt_get_result($client_user_stmt);
                    if ($client_user_row = mysqli_fetch_assoc($client_user_result)) {
                        $client_account_id = $client_user_row['manager_id'];
                        
                        // Get the manager assigned to this client account
                        if (!empty($client_account_id)) {
                            $account_sql = "SELECT manager_id FROM users WHERE id = ? AND user_type = 'client' AND (password IS NULL OR password = '')";
                            $account_stmt = mysqli_prepare($conn, $account_sql);
                            if ($account_stmt) {
                                mysqli_stmt_bind_param($account_stmt, 'i', $client_account_id);
                                mysqli_stmt_execute($account_stmt);
                                $account_result = mysqli_stmt_get_result($account_stmt);
                                if ($account_row = mysqli_fetch_assoc($account_result)) {
                                    $assigned_to_manager_id = $account_row['manager_id'];
                                }
                                mysqli_stmt_close($account_stmt);
                            }
                        }
                    }
                    mysqli_stmt_close($client_user_stmt);
                }
            }
            
            // Build SQL query with or without assigned_to
            if ($assigned_to_manager_id !== null) {
                $sql = "INSERT INTO client_taskflow (unique_id, type, title, description, status, created_by, created_by_type, assigned_to, attachments, status_updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '$escaped_time')";
            } else {
        $sql = "INSERT INTO client_taskflow (unique_id, type, title, description, status, created_by, created_by_type, attachments, status_updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, '$escaped_time')";
            }
        
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $error = mysqli_error($conn);
            error_log("Failed to prepare INSERT statement: " . $error);
            throw new Exception('Database error: ' . $error);
        }
        
            if ($assigned_to_manager_id !== null) {
                // Format: s-s-s-s-s-i-s-i-s (9 parameters: unique_id, type, title, description, status, user_id, created_by_type, assigned_to_manager_id, attachments_json)
                $format = 's' . 's' . 's' . 's' . 's' . 'i' . 's' . 'i' . 's';
                mysqli_stmt_bind_param($stmt, $format, $unique_id, $type, $title, $description, $status, $user_id, $created_by_type, $assigned_to_manager_id, $attachments_json);
            } else {
                // Format: s-s-s-s-s-i-s-s (8 parameters: unique_id, type, title, description, status, user_id, created_by_type, attachments_json)
        mysqli_stmt_bind_param($stmt, 'sssssiss', $unique_id, $type, $title, $description, $status, $user_id, $created_by_type, $attachments_json);
            }
        
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($conn);
            error_log("Failed to execute INSERT statement: " . $error);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to create item: ' . $error);
        }
        
        $item_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        // Send notifications if client user created a Task or Ticket
        if (isClient() && ($type === 'Task' || $type === 'Ticket')) {
            // Get client user's name
            $client_sql = "SELECT name FROM users WHERE id = ?";
            $client_stmt = mysqli_prepare($conn, $client_sql);
            $client_name = 'Client';
            if ($client_stmt) {
                mysqli_stmt_bind_param($client_stmt, 'i', $user_id);
                mysqli_stmt_execute($client_stmt);
                $client_result = mysqli_stmt_get_result($client_stmt);
                $client = mysqli_fetch_assoc($client_result);
                if ($client) {
                    $client_name = $client['name'];
                }
                mysqli_stmt_close($client_stmt);
            }
            
            // Trigger notifications
            if ($type === 'Ticket') {
                triggerClientTicketNotification($conn, $unique_id, $user_id, $title, $client_name);
            } else if ($type === 'Task') {
                triggerClientTaskNotification($conn, $unique_id, $user_id, $title, $client_name);
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Item created successfully',
            'item' => [
                'id' => $unique_id,
                'db_id' => $item_id,
                'type' => $type,
                'title' => $title
            ]
        ]);
        }
    } catch (Exception $e) {
        error_log("createItem error: " . $e->getMessage());
        throw $e; // Re-throw to be caught by main try-catch
    }
}

/**
 * Update item
 */
function updateItem($conn, $user_id) {
    $item_id = trim($_POST['item_id'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($item_id)) {
        throw new Exception('Item ID is required');
    }
    
    if (empty($title)) {
        throw new Exception('Title is required');
    }
    
    // Check if item exists and user has permission
    $check_sql = "SELECT * FROM client_taskflow WHERE unique_id = ? OR id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, 'ss', $item_id, $item_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    $item = mysqli_fetch_assoc($result);
    
    if (!$item) {
        throw new Exception('Item not found');
    }
    
    // Permission check: Client can edit Tasks and Tickets only, Manager can edit Required items only
    if (isClient()) {
        if ($item['type'] !== 'Task' && $item['type'] !== 'Ticket') {
            throw new Exception('Clients can only edit Tasks and Tickets');
        }
        // Client can only edit items they created
        if ($item['created_by'] != $user_id) {
            throw new Exception('You can only edit items you created');
        }
    }
    
    if (isManager()) {
        if ($item['type'] !== 'Required') {
            throw new Exception('Managers can only edit Required items');
        }
    }
    
    // Get existing attachments from database (for reference only)
    $existing_attachments = [];
    if (!empty($item['attachments'])) {
        $existing_attachments = json_decode($item['attachments'], true) ?: [];
    }
    
    // Process attachments_json from frontend (contains all attachments: existing + new)
    $all_attachments = [];
    $processed_paths = []; // Track processed paths to avoid duplicates
    
    // Handle base64 attachments and existing attachments from client
    if (!empty($_POST['attachments_json'])) {
        $client_attachments = json_decode($_POST['attachments_json'], true);
        if (is_array($client_attachments)) {
            foreach ($client_attachments as $attachment) {
                // Skip if we've already processed this attachment (by path)
                if (isset($attachment['path']) && in_array($attachment['path'], $processed_paths)) {
                    continue;
                }
                
                if (isset($attachment['fileData']) && !empty($attachment['fileData'])) {
                    // Save base64 file to server
                    $upload_dir = '../uploads/task_ticket/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    try {
                        $file_data = base64_decode($attachment['fileData']);
                        $file_ext = pathinfo($attachment['name'], PATHINFO_EXTENSION);
                        $new_file_name = uniqid() . '_' . time() . '.' . $file_ext;
                        $file_path = $upload_dir . $new_file_name;
                        
                        if (file_put_contents($file_path, $file_data)) {
                            $attachment_path = 'uploads/task_ticket/' . $new_file_name;
                            $all_attachments[] = [
                                'name' => $attachment['name'],
                                'size' => $attachment['size'] ?? strlen($file_data),
                                'type' => $attachment['type'] ?? 'application/octet-stream',
                                'path' => $attachment_path
                            ];
                            $processed_paths[] = $attachment_path;
                        }
                    } catch (Exception $e) {
                        error_log("Failed to save base64 attachment: " . $e->getMessage());
                    }
                } else if (isset($attachment['path'])) {
                    // Existing attachment - add it directly (frontend sends complete list)
                    $all_attachments[] = [
                        'name' => $attachment['name'],
                        'size' => $attachment['size'] ?? 0,
                        'type' => $attachment['type'] ?? 'application/octet-stream',
                        'path' => $attachment['path']
                    ];
                    $processed_paths[] = $attachment['path'];
                }
            }
        }
    }
    
    // Handle new file uploads (from $_FILES)
    if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
        $upload_dir = '../uploads/task_ticket/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_count = count($_FILES['attachments']['name']);
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['attachments']['name'][$i];
                $file_tmp = $_FILES['attachments']['tmp_name'][$i];
                $file_size = $_FILES['attachments']['size'][$i];
                $file_type = $_FILES['attachments']['type'][$i];
                
                // Check file size (50MB limit)
                $max_size = 50 * 1024 * 1024; // 50MB
                if ($file_size > $max_size) {
                    throw new Exception("File '{$file_name}' exceeds 50MB limit");
                }
                
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'avi', 'mov', 'mp3', 'wav'];
                
                if (in_array($file_ext, $allowed_extensions)) {
                    $new_file_name = uniqid() . '_' . time() . '_' . $i . '.' . $file_ext;
                    $file_path = $upload_dir . $new_file_name;
                    
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        $attachment_path = 'uploads/task_ticket/' . $new_file_name;
                        // Only add if not already in the list
                        if (!in_array($attachment_path, $processed_paths)) {
                            $all_attachments[] = [
                                'name' => $file_name,
                                'size' => $file_size,
                                'type' => $file_type,
                                'path' => $attachment_path
                            ];
                            $processed_paths[] = $attachment_path;
                        }
                    }
                }
            }
        }
    }
    
    // If no attachments_json was provided, use existing attachments (fallback)
    if (empty($_POST['attachments_json']) && !empty($existing_attachments)) {
        $all_attachments = $existing_attachments;
    }
    $attachments_json = !empty($all_attachments) ? json_encode($all_attachments) : null;
    
    // Detect which fields were changed
    $title_changed = (trim($item['title']) !== trim($title));
    $description_changed = (trim($item['description'] ?? '') !== trim($description ?? ''));
    $attachments_changed = false;
    
    // Check if attachments changed by comparing JSON
    $existing_attachments_json = !empty($item['attachments']) ? json_encode(json_decode($item['attachments'], true)) : '[]';
    $new_attachments_json = $attachments_json ? json_encode(json_decode($attachments_json, true)) : '[]';
    $attachments_changed = ($existing_attachments_json !== $new_attachments_json);
    
    // Build UPDATE query with conditional timestamp updates
    $current_time = date('Y-m-d H:i:s');
    
    // Use mysqli_real_escape_string for safe timestamp insertion
    $escaped_time = mysqli_real_escape_string($conn, $current_time);
    
    $sql = "UPDATE client_taskflow SET 
            title = ?, 
            description = ?, 
            attachments = ?";
    
    // Add timestamp updates for changed fields
    if ($title_changed) {
        $sql .= ", title_edited_at = '$escaped_time'";
    }
    if ($description_changed) {
        $sql .= ", description_edited_at = '$escaped_time'";
    }
    if ($attachments_changed) {
        $sql .= ", attachments_edited_at = '$escaped_time'";
    }
    
    $sql .= " WHERE unique_id = ? OR id = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        error_log("[DB Error] Database error: " . mysqli_error($conn)); throw new Exception('A database error occurred');
    }
    
    mysqli_stmt_bind_param($stmt, 'sssss', $title, $description, $attachments_json, $item_id, $item_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        error_log("[DB Error] Failed to update item: " . mysqli_error($conn)); throw new Exception('A database error occurred');
    }
    
    mysqli_stmt_close($stmt);
    
    echo json_encode(['success' => true, 'message' => 'Item updated successfully']);
}

/**
 * Delete item
 */
function deleteItem($conn, $user_id) {
    $item_id = trim($_POST['item_id'] ?? $_GET['item_id'] ?? '');
    
    if (empty($item_id)) {
        throw new Exception('Item ID is required');
    }
    
    // Check if item exists and user has permission
    $check_sql = "SELECT * FROM client_taskflow WHERE unique_id = ? OR id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, 'ss', $item_id, $item_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    $item = mysqli_fetch_assoc($result);
    
    if (!$item) {
        throw new Exception('Item not found');
    }
    
    // Permission check: Client can only delete Required items they created, Manager can delete all
    if (isClient()) {
        if ($item['type'] !== 'Required' || $item['created_by'] != $user_id) {
            throw new Exception('You can only delete Required items you created');
        }
    }
    
    // Delete attachments
    if (!empty($item['attachments'])) {
        $attachments = json_decode($item['attachments'], true);
        if (is_array($attachments)) {
            foreach ($attachments as $attachment) {
                if (isset($attachment['path'])) {
                    $file_path = '../' . $attachment['path'];
                    if (file_exists($file_path)) {
                        @unlink($file_path);
                    }
                }
            }
        }
    }
    
    $sql = "DELETE FROM client_taskflow WHERE unique_id = ? OR id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        error_log("[DB Error] Database error: " . mysqli_error($conn)); throw new Exception('A database error occurred');
    }
    
    mysqli_stmt_bind_param($stmt, 'ss', $item_id, $item_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        error_log("[DB Error] Failed to delete item: " . mysqli_error($conn)); throw new Exception('A database error occurred');
    }
    
    mysqli_stmt_close($stmt);
    
    echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
}

/**
 * Update status
 */
function updateStatus($conn, $user_id) {
    $item_id = trim($_POST['item_id'] ?? '');
    $new_status = trim($_POST['status'] ?? '');
    
    if (empty($item_id)) {
        throw new Exception('Item ID is required');
    }
    
    if (empty($new_status)) {
        throw new Exception('Status is required');
    }
    
    // Check if item exists
    $check_sql = "SELECT * FROM client_taskflow WHERE unique_id = ? OR id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, 'ss', $item_id, $item_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    $item = mysqli_fetch_assoc($result);
    
    if (!$item) {
        throw new Exception('Item not found');
    }
    
    // Allow 'Dropped' status for both Managers and Clients (for dropping items)
    if ($new_status === 'Dropped') {
        // Both Managers and Clients can drop items they have access to
        // No additional validation needed - dropping is allowed for all item types
    } else {
        // Permission check: Manager can update any status, Client can only update specific statuses
        if (isClient()) {
            // Client can only update to specific statuses based on item type
            if ($item['type'] === 'Task') {
                // Client can only set Task to 'Assigned', 'Approved', or 'Revise' on their own tasks
                if ($item['created_by'] != $user_id) {
                    throw new Exception('You can only update status of tasks you created');
                }
                if (!in_array($new_status, ['Assigned', 'Approved', 'Revise'])) {
                    throw new Exception('Clients can only set Task status to Assigned, Approved, or Revise');
                }
            } else if ($item['type'] === 'Ticket') {
                // Client cannot update Ticket status (read-only)
                throw new Exception('Clients cannot update Ticket status');
            } else if ($item['type'] === 'Required') {
                // Client can set Required to 'Requested' or 'Provided' (regardless of who created it)
                if (!in_array($new_status, ['Requested', 'Provided'])) {
                    throw new Exception('Clients can only set Required status to Requested or Provided');
                }
            }
        } else if (isManager()) {
            // Manager can update any status, but validate based on item type
            if ($item['type'] === 'Task') {
                // Manager can set: Working, Review, Revise, Approved, Completed
                $allowed = ['Working', 'Review', 'Revise', 'Approved', 'Completed'];
                if (!in_array($new_status, $allowed)) {
                    // Allow other statuses too (for flexibility), but log it
                    error_log("Manager updating Task to unexpected status: " . $new_status);
                }
            } else if ($item['type'] === 'Ticket') {
                // Manager can set: Raised, In Progress, Resolved
                $allowed = ['Raised', 'In Progress', 'Resolved'];
                if (!in_array($new_status, $allowed)) {
                    error_log("Manager updating Ticket to unexpected status: " . $new_status);
                }
            } else if ($item['type'] === 'Required') {
                // Manager can set: Requested, Provided
                $allowed = ['Requested', 'Provided'];
                if (!in_array($new_status, $allowed)) {
                    error_log("Manager updating Required to unexpected status: " . $new_status);
                }
            }
        } else if (isAdmin()) {
            // Admin can update any status for any item type
            // No restrictions
        } else {
            throw new Exception('Only admins, managers, and clients can update status');
        }
    }
    
    // Prevent updating dropped items
    if ($item['status'] === 'Dropped' && $new_status !== 'Dropped') {
        throw new Exception('Cannot update status of dropped items');
    }
    
    // Only update status_updated_at if status actually changed
    $status_changed = ($item['status'] !== $new_status);
    $current_time = date('Y-m-d H:i:s');
    $escaped_time = mysqli_real_escape_string($conn, $current_time);
    
    // Handle file attachments if provided (for Requirement -> Provided status change)
    $attachments = [];
    $update_attachments = false;
    
    // Get existing attachments
    if (!empty($item['attachments'])) {
        $existing_attachments = json_decode($item['attachments'], true);
        if (is_array($existing_attachments)) {
            $attachments = $existing_attachments;
        }
    }
    
    // Handle new attachments from client (base64 encoded)
    if (!empty($_POST['attachments_json'])) {
        $client_attachments = json_decode($_POST['attachments_json'], true);
        if (is_array($client_attachments)) {
            foreach ($client_attachments as $attachment) {
                if (isset($attachment['fileData']) && !empty($attachment['fileData'])) {
                    // Save base64 file to server
                    $upload_dir = '../uploads/task_ticket/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    try {
                        $file_data = base64_decode($attachment['fileData']);
                        $file_ext = pathinfo($attachment['name'], PATHINFO_EXTENSION);
                        $new_file_name = uniqid() . '_' . time() . '.' . $file_ext;
                        $file_path = $upload_dir . $new_file_name;
                        
                        if (file_put_contents($file_path, $file_data)) {
                            $attachments[] = [
                                'name' => $attachment['name'],
                                'size' => $attachment['size'] ?? strlen($file_data),
                                'type' => $attachment['type'] ?? 'application/octet-stream',
                                'path' => 'uploads/task_ticket/' . $new_file_name
                            ];
                            $update_attachments = true;
                        }
                    } catch (Exception $e) {
                        error_log("Failed to save base64 attachment: " . $e->getMessage());
                    }
                }
            }
        }
    }
    
    // Build SQL query
    $sql_parts = [];
    $params = [];
    $types = '';
    
    // Always update status
    $sql_parts[] = "status = ?";
    $params[] = $new_status;
    $types .= 's';
    
    // Update status_updated_at if status changed
    if ($status_changed) {
        $sql_parts[] = "status_updated_at = '$escaped_time'";
    }
    
    // Update attachments if new ones were added
    if ($update_attachments) {
        $attachments_json = json_encode($attachments);
        $attachments_escaped = mysqli_real_escape_string($conn, $attachments_json);
        $attachments_edited_time = date('Y-m-d H:i:s');
        $attachments_edited_time_escaped = mysqli_real_escape_string($conn, $attachments_edited_time);
        $sql_parts[] = "attachments = '$attachments_escaped'";
        $sql_parts[] = "attachments_edited_at = '$attachments_edited_time_escaped'";
    }
    
    $sql = "UPDATE client_taskflow SET " . implode(', ', $sql_parts) . " WHERE unique_id = ? OR id = ?";
    $params[] = $item_id;
    $params[] = $item_id;
    $types .= 'ss';
    
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        error_log("[DB Error] Database error: " . mysqli_error($conn)); throw new Exception('A database error occurred');
    }
    
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    
    if (!mysqli_stmt_execute($stmt)) {
        error_log("[DB Error] Failed to update status: " . mysqli_error($conn)); throw new Exception('A database error occurred');
    }
    
    mysqli_stmt_close($stmt);
    
    $message = 'Status updated successfully';
    if ($update_attachments) {
        $message .= ' with ' . count($client_attachments) . ' file(s) attached';
    }
    
    echo json_encode(['success' => true, 'message' => $message, 'status' => $new_status]);
}

/**
 * Provide requirement - Save provided description and attachments
 */
function provideRequirement($conn, $user_id) {
    try {
        $item_id = trim($_POST['item_id'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = trim($_POST['status'] ?? 'Provided');
        
        if (empty($item_id)) {
            throw new Exception('Item ID is required');
        }
        
        // Check if item exists
        $check_sql = "SELECT * FROM client_taskflow WHERE unique_id = ? OR id = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        if (!$check_stmt) {
            error_log("[DB Error] Database error: " . mysqli_error($conn)); throw new Exception('A database error occurred');
        }
        mysqli_stmt_bind_param($check_stmt, 'ss', $item_id, $item_id);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        $item = mysqli_fetch_assoc($result);
        mysqli_stmt_close($check_stmt);
        
        if (!$item) {
            throw new Exception('Item not found');
        }
        
        // Verify it's a Required item
        if ($item['type'] !== 'Required') {
            throw new Exception('Can only provide requirements for Required items');
        }
        
        // Handle attachments
        $provided_attachments = [];
        
        // Get existing provided attachments
        if (!empty($item['provided_attachments'])) {
            $existing = json_decode($item['provided_attachments'], true);
            if (is_array($existing)) {
                $provided_attachments = $existing;
            }
        }
        
        // Handle file uploads - prefer FormData over base64 (FormData is more efficient)
        // Only process base64 if FormData is not available
        $hasFormDataFiles = isset($_FILES['attachments']) && is_array($_FILES['attachments']['name']) && count($_FILES['attachments']['name']) > 0;
        
        // Handle FormData file uploads (preferred method)
        if ($hasFormDataFiles) {
            $upload_dir = '../uploads/task_ticket/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['attachments']['tmp_name'][$i];
                    $name = $_FILES['attachments']['name'][$i];
                    $size = $_FILES['attachments']['size'][$i];
                    $type = $_FILES['attachments']['type'][$i];
                    
                    $file_ext = pathinfo($name, PATHINFO_EXTENSION);
                    $new_file_name = uniqid() . '_provided_' . time() . '_' . $i . '_' . rand(1000, 9999) . '.' . $file_ext;
                    $file_path = $upload_dir . $new_file_name;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $provided_attachments[] = [
                            'name' => $name,
                            'size' => $size,
                            'type' => $type,
                            'path' => 'uploads/task_ticket/' . $new_file_name
                        ];
                    } else {
                        error_log("Failed to move uploaded file: " . $name);
                    }
                }
            }
        } else if (!empty($_POST['attachments_json'])) {
            // Fallback: Handle base64 attachments only if FormData files are not present
            $client_attachments = json_decode($_POST['attachments_json'], true);
            if (is_array($client_attachments)) {
                $upload_dir = '../uploads/task_ticket/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                foreach ($client_attachments as $attachment) {
                    // Only process if it has fileData (new file), not if it's just metadata
                    if (isset($attachment['fileData']) && !empty($attachment['fileData'])) {
                        try {
                            $file_data = base64_decode($attachment['fileData']);
                            if ($file_data === false) {
                                error_log("Failed to decode base64 file: " . $attachment['name']);
                                continue;
                            }
                            
                            $file_ext = pathinfo($attachment['name'], PATHINFO_EXTENSION);
                            $new_file_name = uniqid() . '_provided_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
                            $file_path = $upload_dir . $new_file_name;
                            
                            if (file_put_contents($file_path, $file_data)) {
                                $provided_attachments[] = [
                                    'name' => $attachment['name'],
                                    'size' => $attachment['size'] ?? strlen($file_data),
                                    'type' => $attachment['type'] ?? 'application/octet-stream',
                                    'path' => 'uploads/task_ticket/' . $new_file_name
                                ];
                            } else {
                                error_log("Failed to save provided attachment file: " . $attachment['name']);
                            }
                        } catch (Exception $e) {
                            error_log("Failed to save provided attachment: " . $e->getMessage());
                        }
                    }
                }
            }
        }
        
        // Update database
        $provided_attachments_json = !empty($provided_attachments) ? json_encode($provided_attachments) : null;
        $current_time = date('Y-m-d H:i:s');
        
        $update_sql = "UPDATE client_taskflow SET 
                        provided_description = ?,
                        provided_attachments = ?,
                        provided_edited_at = ?,
                        status = ?,
                        status_updated_at = ?,
                        updated_at = ?
                       WHERE (unique_id = ? OR id = ?)";
        
        $stmt = mysqli_prepare($conn, $update_sql);
        if (!$stmt) {
            error_log("[DB Error] Database error: " . mysqli_error($conn)); throw new Exception('A database error occurred');
        }
        
        mysqli_stmt_bind_param($stmt, 'ssssssss', 
            $description,
            $provided_attachments_json,
            $current_time,
            $status,
            $current_time,
            $current_time,
            $item_id,
            $item_id
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            error_log("[DB Error] Failed to update requirement: " . mysqli_error($conn)); throw new Exception('A database error occurred');
        }
        
        mysqli_stmt_close($stmt);
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Requirement provided successfully',
            'status' => $status
        ]);
        
    } catch (Exception $e) {
        ob_clean();
        http_response_code(400);
        error_log("provideRequirement error: " . $e->getMessage());
        handleException($e, 'task_ticket_handler');
    }
}

/**
 * Update provided description and/or attachments
 */
function updateProvided($conn, $user_id) {
    $item_id = $_POST['item_id'] ?? '';
    
    if (empty($item_id)) {
        jsonError('Item ID is required', 400);
    }
    
    // Get item to verify it exists and is a Required type
    $sql = "SELECT * FROM client_taskflow WHERE (unique_id = ? OR id = ?) AND type = 'Required'";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        jsonError('Database error', 500);
    }
    
    mysqli_stmt_bind_param($stmt, 'ss', $item_id, $item_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $item = mysqli_fetch_assoc($result);
    
    if (!$item) {
        jsonError('Item not found or not a Required type', 404);
    }
    
    // Update provided_description if provided
    $provided_description = $_POST['description'] ?? null;
    $provided_attachments = [];
    
    // Start with attachments from attachments_json (existing attachments to keep)
    if (!empty($_POST['attachments_json'])) {
        $existing_attachments = json_decode($_POST['attachments_json'], true);
        if (is_array($existing_attachments)) {
            $provided_attachments = $existing_attachments;
        }
    }
    
    // Add new attachments from file uploads
    if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
        $upload_dir = '../uploads/task_ticket/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
            if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['attachments']['tmp_name'][$i];
                $name = $_FILES['attachments']['name'][$i];
                $size = $_FILES['attachments']['size'][$i];
                $type = $_FILES['attachments']['type'][$i];
                
                $file_ext = pathinfo($name, PATHINFO_EXTENSION);
                $new_file_name = uniqid() . '_provided_' . time() . '_' . $i . '_' . rand(1000, 9999) . '.' . $file_ext;
                $file_path = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($tmp_name, $file_path)) {
                    $provided_attachments[] = [
                        'name' => $name,
                        'size' => $size,
                        'type' => $type,
                        'path' => 'uploads/task_ticket/' . $new_file_name
                    ];
                }
            }
        }
    }
    
    // Handle file uploads - prefer FormData over base64 (FormData is more efficient)
    $hasFormDataFiles = isset($_FILES['attachments']) && is_array($_FILES['attachments']['name']) && count($_FILES['attachments']['name']) > 0;
    
    // Handle FormData file uploads (preferred method)
    if ($hasFormDataFiles) {
        $upload_dir = '../uploads/task_ticket/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
            if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['attachments']['tmp_name'][$i];
                $name = $_FILES['attachments']['name'][$i];
                $size = $_FILES['attachments']['size'][$i];
                $type = $_FILES['attachments']['type'][$i];
                
                $file_ext = pathinfo($name, PATHINFO_EXTENSION);
                $new_file_name = uniqid() . '_provided_' . time() . '_' . $i . '_' . rand(1000, 9999) . '.' . $file_ext;
                $file_path = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($tmp_name, $file_path)) {
                    $provided_attachments[] = [
                        'name' => $name,
                        'size' => $size,
                        'type' => $type,
                        'path' => 'uploads/task_ticket/' . $new_file_name
                    ];
                }
            }
        }
    } else if (!empty($_POST['attachments_json'])) {
        // Fallback: Handle base64 attachments only if FormData files are not present
        $client_attachments = json_decode($_POST['attachments_json'], true);
        if (is_array($client_attachments)) {
            $upload_dir = '../uploads/task_ticket/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            foreach ($client_attachments as $attachment) {
                // Only process if it has fileData (new file), not if it's just metadata
                if (isset($attachment['fileData']) && !empty($attachment['fileData'])) {
                    try {
                        $file_data = base64_decode($attachment['fileData']);
                        if ($file_data === false) {
                            continue;
                        }
                        
                        $file_ext = pathinfo($attachment['name'], PATHINFO_EXTENSION);
                        $new_file_name = uniqid() . '_provided_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
                        $file_path = $upload_dir . $new_file_name;
                        
                        if (file_put_contents($file_path, $file_data)) {
                            $provided_attachments[] = [
                                'name' => $attachment['name'],
                                'size' => $attachment['size'] ?? strlen($file_data),
                                'type' => $attachment['type'] ?? 'application/octet-stream',
                                'path' => 'uploads/task_ticket/' . $new_file_name
                            ];
                        }
                    } catch (Exception $e) {
                        error_log("Failed to save provided attachment: " . $e->getMessage());
                    }
                }
            }
        }
    }
        
        $provided_attachments_json = !empty($provided_attachments) ? json_encode($provided_attachments) : null;
        $current_time = date('Y-m-d H:i:s');
        
        // Use provided description from POST if provided, otherwise keep existing
        $provided_description = !empty($_POST['description']) ? $_POST['description'] : $item['provided_description'];
        
        $update_sql = "UPDATE client_taskflow SET 
                    provided_description = ?,
                    provided_attachments = ?,
                    provided_edited_at = ?,
                    updated_at = ?
                    WHERE (unique_id = ? OR id = ?)";
    
    $stmt = mysqli_prepare($conn, $update_sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ssssss', 
            $provided_description,
            $provided_attachments_json,
            $current_time,
            $current_time,
            $item_id,
            $item_id
        );
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode([
                'success' => true,
                'message' => 'Provided information updated successfully',
                'provided_description' => $provided_description,
                'provided_attachments' => $provided_attachments,
                'provided_edited_at' => $current_time
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update provided information']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Download attachment
 */
function downloadAttachment() {
    global $conn;
    
    $attachment_id = $_GET['id'] ?? $_GET['path'] ?? '';
    
    if (empty($attachment_id)) {
        http_response_code(400);
        echo 'Invalid attachment ID';
        exit;
    }
    
    // Check if this is a file path (for server-stored files)
    if (strpos($attachment_id, 'uploads/') === 0 || strpos($attachment_id, '../uploads/') === 0) {
        $file_path = strpos($attachment_id, '../') === 0 ? $attachment_id : '../' . $attachment_id;
        
        if (!file_exists($file_path)) {
            http_response_code(404);
            echo 'File not found';
            exit;
        }
        
        $file_name = basename($file_path);
        $file_size = filesize($file_path);
        $mime_type = mime_content_type($file_path);
        
        // Fallback MIME type detection based on file extension if mime_content_type fails
        if (!$mime_type || $mime_type === 'application/octet-stream') {
            $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
            $mime_types = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'bmp' => 'image/bmp',
                'svg' => 'image/svg+xml',
                'pdf' => 'application/pdf',
                'txt' => 'text/plain',
                'html' => 'text/html',
                'css' => 'text/css',
                'js' => 'application/javascript',
                'json' => 'application/json',
                'xml' => 'application/xml',
                'zip' => 'application/zip',
                'mp4' => 'video/mp4',
                'avi' => 'video/x-msvideo',
                'mov' => 'video/quicktime',
                'wmv' => 'video/x-ms-wmv',
                'flv' => 'video/x-flv',
                'webm' => 'video/webm',
                'mkv' => 'video/x-matroska',
                '3gp' => 'video/3gpp',
                'mp3' => 'audio/mpeg',
                'wav' => 'audio/wav',
                'ogg' => 'audio/ogg',
                'm4a' => 'audio/mp4',
                'aac' => 'audio/aac',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'ppt' => 'application/vnd.ms-powerpoint',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
            ];
            $mime_type = $mime_types[$extension] ?? 'application/octet-stream';
        }
        
        // Check if preview mode is requested
        $preview = isset($_GET['preview']) && $_GET['preview'] === '1';
        
        // Clear any previous output and headers
        ob_clean();
        
        // Set proper headers for image/file serving
        header('Content-Type: ' . $mime_type);
        if ($preview) {
            header('Content-Disposition: inline; filename="' . $file_name . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $file_name . '"');
        }
        header('Content-Length: ' . $file_size);
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        // Disable output buffering for file streaming
        if (ob_get_level()) {
            ob_end_flush();
        }
        
        readfile($file_path);
        exit;
    }
    
    // If attachment_id is a numeric ID or unique_id, check client_taskflow table
    // Check if this is a provided attachment
    $is_provided = isset($_GET['provided']) && $_GET['provided'] == '1';
    $sql = $is_provided 
        ? "SELECT provided_attachments FROM client_taskflow WHERE unique_id = ? OR id = ?"
        : "SELECT attachments FROM client_taskflow WHERE unique_id = ? OR id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ss', $attachment_id, $attachment_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            // Get attachments from appropriate field
            $attachments_field = $is_provided ? 'provided_attachments' : 'attachments';
            if (!empty($row[$attachments_field])) {
                $attachments = json_decode($row[$attachments_field], true);
                if (is_array($attachments) && !empty($attachments)) {
                    // Get first attachment or specific one by index
                    $attachment_index = intval($_GET['index'] ?? 0);
                    $attachment = $attachments[$attachment_index] ?? $attachments[0];
                    
                    if (isset($attachment['path'])) {
                        $file_path = '../' . $attachment['path'];
                        $file_name = $attachment['name'] ?? basename($attachment['path']);
                        
                        if (file_exists($file_path)) {
                            $file_size = filesize($file_path);
                            $mime_type = mime_content_type($file_path);
                            
                            // Fallback MIME type detection based on file extension if mime_content_type fails
                            if (!$mime_type || $mime_type === 'application/octet-stream') {
                                $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                                $mime_types = [
                                    'jpg' => 'image/jpeg',
                                    'jpeg' => 'image/jpeg',
                                    'png' => 'image/png',
                                    'gif' => 'image/gif',
                                    'webp' => 'image/webp',
                                    'bmp' => 'image/bmp',
                                    'svg' => 'image/svg+xml',
                                    'pdf' => 'application/pdf',
                                    'txt' => 'text/plain',
                                    'html' => 'text/html',
                                    'css' => 'text/css',
                                    'js' => 'application/javascript',
                                    'json' => 'application/json',
                                    'xml' => 'application/xml',
                                    'zip' => 'application/zip',
                                    'mp4' => 'video/mp4',
                                    'avi' => 'video/x-msvideo',
                                    'mov' => 'video/quicktime',
                                    'wmv' => 'video/x-ms-wmv',
                                    'flv' => 'video/x-flv',
                                    'webm' => 'video/webm',
                                    'mkv' => 'video/x-matroska',
                                    '3gp' => 'video/3gpp',
                                    'mp3' => 'audio/mpeg',
                                    'wav' => 'audio/wav',
                                    'ogg' => 'audio/ogg',
                                    'm4a' => 'audio/mp4',
                                    'aac' => 'audio/aac',
                                    'doc' => 'application/msword',
                                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    'xls' => 'application/vnd.ms-excel',
                                    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                    'ppt' => 'application/vnd.ms-powerpoint',
                                    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
                                ];
                                $mime_type = $mime_types[$extension] ?? 'application/octet-stream';
                            }
                            
                            // Check if preview mode is requested
                            $preview = isset($_GET['preview']) && $_GET['preview'] === '1';
                            
                            // Clear any previous output and headers
                            ob_clean();
                            
                            // Set proper headers for image/file serving
                            header('Content-Type: ' . $mime_type);
                            if ($preview) {
                                header('Content-Disposition: inline; filename="' . $file_name . '"');
                            } else {
                                header('Content-Disposition: attachment; filename="' . $file_name . '"');
                            }
                            header('Content-Length: ' . $file_size);
                            header('Cache-Control: must-revalidate');
                            header('Pragma: public');
                            
                            // Disable output buffering for file streaming
                            if (ob_get_level()) {
                                ob_end_flush();
                            }
                            
                            readfile($file_path);
                            exit;
                        }
                    }
                }
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // File not found
    http_response_code(404);
    echo 'File not found';
    exit;
}

/**
 * Get filter options for Assigner and Assigned To dropdowns
 */
function getFilterOptions($conn, $user_id) {
    $is_admin = isAdmin();
    $is_manager = isManager();
    $is_client = isClient();
    
    $assigner_options = [];
    $assigned_to_options = [];
    
    if ($is_admin) {
        // Admin: All managers and client users only (no client accounts - filtering works by user)
        $managers_sql = "SELECT id, name, username FROM users WHERE user_type = 'manager' ORDER BY name";
        $managers_result = mysqli_query($conn, $managers_sql);
        while ($row = mysqli_fetch_assoc($managers_result)) {
            $name = $row['name'] ?: $row['username'];
            $assigner_options[] = ['id' => $row['id'], 'name' => $name, 'type' => 'manager'];
            $assigned_to_options[] = ['id' => $row['id'], 'name' => $name, 'type' => 'manager'];
        }
        
        $users_sql = "SELECT id, name, username FROM users 
                      WHERE user_type = 'client' 
                      AND password IS NOT NULL 
                      AND password != '' 
                      ORDER BY name";
        $users_result = mysqli_query($conn, $users_sql);
        while ($row = mysqli_fetch_assoc($users_result)) {
            $name = $row['name'] ?: $row['username'];
            $assigner_options[] = ['id' => $row['id'], 'name' => $name, 'type' => 'client_user'];
            $assigned_to_options[] = ['id' => $row['id'], 'name' => $name, 'type' => 'client_user'];
        }
    } elseif ($is_manager) {
        // Manager: Their own name + client users under their accounts only (no client account names)
        $manager_sql = "SELECT id, name, username FROM users WHERE id = ?";
        $manager_stmt = mysqli_prepare($conn, $manager_sql);
        mysqli_stmt_bind_param($manager_stmt, 'i', $user_id);
        mysqli_stmt_execute($manager_stmt);
        $manager_result = mysqli_stmt_get_result($manager_stmt);
        if ($manager_row = mysqli_fetch_assoc($manager_result)) {
            $name = $manager_row['name'] ?: $manager_row['username'];
            $assigner_options[] = ['id' => $manager_row['id'], 'name' => $name, 'type' => 'manager'];
            $assigned_to_options[] = ['id' => $manager_row['id'], 'name' => $name, 'type' => 'manager'];
        }
        mysqli_stmt_close($manager_stmt);
        
        // Get client users under this manager's accounts (no client account entries)
        $client_accounts_sql = "SELECT id FROM users 
                                WHERE user_type = 'client' 
                                AND (password IS NULL OR password = '') 
                                AND manager_id = ?";
        $client_accounts_stmt = mysqli_prepare($conn, $client_accounts_sql);
        mysqli_stmt_bind_param($client_accounts_stmt, 'i', $user_id);
        mysqli_stmt_execute($client_accounts_stmt);
        $client_accounts_result = mysqli_stmt_get_result($client_accounts_stmt);
        
        $client_account_ids = [];
        while ($account_row = mysqli_fetch_assoc($client_accounts_result)) {
            $client_account_ids[] = $account_row['id'];
        }
        mysqli_stmt_close($client_accounts_stmt);
        
        if (!empty($client_account_ids)) {
            $placeholders = implode(',', array_fill(0, count($client_account_ids), '?'));
            $users_sql = "SELECT id, name, username FROM users 
                          WHERE user_type = 'client' 
                          AND manager_id IN ($placeholders) 
                          AND password IS NOT NULL 
                          AND password != '' 
                          ORDER BY name";
            $users_stmt = mysqli_prepare($conn, $users_sql);
            $types = str_repeat('i', count($client_account_ids));
            mysqli_stmt_bind_param($users_stmt, $types, ...$client_account_ids);
            mysqli_stmt_execute($users_stmt);
            $users_result = mysqli_stmt_get_result($users_stmt);
            while ($row = mysqli_fetch_assoc($users_result)) {
                $name = $row['name'] ?: $row['username'];
                $assigner_options[] = ['id' => $row['id'], 'name' => $name, 'type' => 'client_user'];
                $assigned_to_options[] = ['id' => $row['id'], 'name' => $name, 'type' => 'client_user'];
            }
            mysqli_stmt_close($users_stmt);
        }
    } elseif ($is_client) {
        // Client: Client users under their account + their direct associated managers
        // Get client user's manager_id (client account)
        $client_user_sql = "SELECT manager_id FROM users WHERE id = ?";
        $client_user_stmt = mysqli_prepare($conn, $client_user_sql);
        mysqli_stmt_bind_param($client_user_stmt, 'i', $user_id);
        mysqli_stmt_execute($client_user_stmt);
        $client_user_result = mysqli_stmt_get_result($client_user_stmt);
        $client_user_data = mysqli_fetch_assoc($client_user_result);
        mysqli_stmt_close($client_user_stmt);
        
        if ($client_user_data && !empty($client_user_data['manager_id'])) {
            $client_account_id = $client_user_data['manager_id'];
            
            // Get client users under this account
            $users_sql = "SELECT id, name, username FROM users 
                          WHERE user_type = 'client' 
                          AND manager_id = ? 
                          AND password IS NOT NULL 
                          AND password != '' 
                          ORDER BY name";
            $users_stmt = mysqli_prepare($conn, $users_sql);
            mysqli_stmt_bind_param($users_stmt, 'i', $client_account_id);
            mysqli_stmt_execute($users_stmt);
            $users_result = mysqli_stmt_get_result($users_stmt);
            while ($row = mysqli_fetch_assoc($users_result)) {
                $name = $row['name'] ?: $row['username'];
                $assigner_options[] = ['id' => $row['id'], 'name' => $name, 'type' => 'client_user'];
                $assigned_to_options[] = ['id' => $row['id'], 'name' => $name, 'type' => 'client_user'];
            }
            mysqli_stmt_close($users_stmt);
            
            // Get the manager assigned to this client account
            $account_sql = "SELECT manager_id FROM users 
                           WHERE id = ? 
                           AND user_type = 'client' 
                           AND (password IS NULL OR password = '')";
            $account_stmt = mysqli_prepare($conn, $account_sql);
            mysqli_stmt_bind_param($account_stmt, 'i', $client_account_id);
            mysqli_stmt_execute($account_stmt);
            $account_result = mysqli_stmt_get_result($account_stmt);
            $account_data = mysqli_fetch_assoc($account_result);
            mysqli_stmt_close($account_stmt);
            
            if ($account_data && !empty($account_data['manager_id'])) {
                $manager_id = $account_data['manager_id'];
                
                // Get manager info
                $manager_sql = "SELECT id, name, username FROM users WHERE id = ? AND user_type = 'manager'";
                $manager_stmt = mysqli_prepare($conn, $manager_sql);
                mysqli_stmt_bind_param($manager_stmt, 'i', $manager_id);
                mysqli_stmt_execute($manager_stmt);
                $manager_result = mysqli_stmt_get_result($manager_stmt);
                if ($manager_row = mysqli_fetch_assoc($manager_result)) {
                    $name = $manager_row['name'] ?: $manager_row['username'];
                    $assigner_options[] = ['id' => $manager_row['id'], 'name' => $name, 'type' => 'manager'];
                    $assigned_to_options[] = ['id' => $manager_row['id'], 'name' => $name, 'type' => 'manager'];
                }
                mysqli_stmt_close($manager_stmt);
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'assigner_options' => $assigner_options,
        'assigned_to_options' => $assigned_to_options
    ]);
}

// Close database connection
if (isset($conn) && $conn instanceof mysqli) {
    mysqli_close($conn);
}
?>
