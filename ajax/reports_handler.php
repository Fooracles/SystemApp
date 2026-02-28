<?php
// Session is automatically started by includes/functions.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/notification_triggers.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    jsonError('Unauthorized', 401);
}

// Check if user is Manager or Client
if (!isAdmin() && !isManager() && !isClient()) {
    http_response_code(403);
    jsonError('Access denied', 403);
}

// CSRF protection for POST requests
csrfProtect();

$user_id = $_SESSION['id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// View and download actions don't return JSON
if ($action !== 'view' && $action !== 'download') {
    header('Content-Type: application/json');
}

try {
    switch ($action) {
        case 'get_reports':
            getReports($conn, $user_id);
            break;
        case 'get_report':
            getReport($conn, $user_id);
            break;
        case 'upload_report':
            if (!isAdmin() && !isManager()) {
                throw new Exception('Only admins and managers can upload reports');
            }
            uploadReport($conn, $user_id);
            break;
        case 'update_report':
            if (!isAdmin() && !isManager()) {
                throw new Exception('Only admins and managers can update reports');
            }
            updateReport($conn, $user_id);
            break;
        case 'delete_report':
            if (!isAdmin() && !isManager()) {
                throw new Exception('Only admins and managers can delete reports');
            }
            deleteReport($conn, $user_id);
            break;
        case 'view':
            viewReport($conn, $user_id);
            break;
        case 'download':
            downloadReport($conn, $user_id);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    handleException($e, 'reports_handler');
}

/**
 * Ensure report_recipients table exists (one report can be shared with multiple client users)
 */
function ensureReportRecipientsTable($conn) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'report_recipients'");
    if ($result && mysqli_num_rows($result) > 0) {
        return;
    }
    $sql = "CREATE TABLE IF NOT EXISTS report_recipients (
        report_id INT NOT NULL,
        user_id INT NOT NULL,
        PRIMARY KEY (report_id, user_id),
        FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    @mysqli_query($conn, $sql);
}

function getReports($conn, $user_id) {
    $is_admin = isAdmin();
    $is_manager = isManager();
    $is_client = isClient();
    
    // Check if columns exist
    $check_assigned = mysqli_query($conn, "SHOW COLUMNS FROM reports LIKE 'assigned_to'");
    $check_account = mysqli_query($conn, "SHOW COLUMNS FROM reports LIKE 'client_account_id'");
    $has_assigned_to = $check_assigned && mysqli_num_rows($check_assigned) > 0;
    $has_client_account_id = $check_account && mysqli_num_rows($check_account) > 0;
    
    ensureReportRecipientsTable($conn);
    
    // Build base query
    $sql = "SELECT r.*, u.name as uploaded_by_name 
            FROM reports r 
            LEFT JOIN users u ON r.uploaded_by = u.id";
    
    // Add WHERE clause based on user role
    $where_conditions = [];
    
    if ($is_client) {
        // Client users: See reports assigned to them OR where they are in report_recipients
        if ($has_assigned_to) {
            $where_conditions[] = "(r.assigned_to = $user_id OR r.id IN (SELECT report_id FROM report_recipients WHERE user_id = $user_id))";
        } else {
            $where_conditions[] = "r.id IN (SELECT report_id FROM report_recipients WHERE user_id = $user_id)";
        }
    } elseif ($is_manager && !$is_admin) {
        // Manager: Only see reports assigned to their client accounts/users
        // Get client accounts assigned to this manager
        $client_accounts_sql = "SELECT id FROM users WHERE user_type = 'client' AND (password IS NULL OR password = '') AND manager_id = ?";
        $client_accounts_stmt = mysqli_prepare($conn, $client_accounts_sql);
        mysqli_stmt_bind_param($client_accounts_stmt, 'i', $user_id);
        mysqli_stmt_execute($client_accounts_stmt);
        $client_accounts_result = mysqli_stmt_get_result($client_accounts_stmt);
        
        $client_account_ids = [];
        while ($account_row = mysqli_fetch_assoc($client_accounts_result)) {
            $client_account_ids[] = $account_row['id'];
        }
        mysqli_stmt_close($client_accounts_stmt);
        
        // Get client users under those accounts
        if (!empty($client_account_ids)) {
            $sanitized_account_ids = array_map('intval', $client_account_ids);
            $account_ids_string = implode(',', $sanitized_account_ids);
            
            $client_users_sql = "SELECT id FROM users WHERE user_type = 'client' AND manager_id IN ($account_ids_string) AND password IS NOT NULL AND password != ''";
            $client_users_result = mysqli_query($conn, $client_users_sql);
            
            $client_user_ids = [];
            while ($user_row = mysqli_fetch_assoc($client_users_result)) {
                $client_user_ids[] = $user_row['id'];
            }
            
            $all_allowed_ids = array_unique(array_merge($client_account_ids, $client_user_ids));
            $sanitized_allowed = array_map('intval', $all_allowed_ids);
            $ids_string = implode(',', $sanitized_allowed);
            
            // Build condition: report is assigned to allowed users OR belongs to allowed accounts
            $manager_conditions = [];
            if ($has_assigned_to) {
                $manager_conditions[] = "r.assigned_to IN ($ids_string)";
            }
            if ($has_client_account_id && !empty($client_account_ids)) {
                $manager_conditions[] = "r.client_account_id IN ($account_ids_string)";
            }
            if (!empty($manager_conditions)) {
                $where_conditions[] = "(" . implode(' OR ', $manager_conditions) . ")";
            }
        } else {
            // No assigned accounts, return empty
            $where_conditions[] = "1=0";
        }
    }
    // Admin: No WHERE clause needed - see all reports
    
    // Add WHERE clause if conditions exist
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(' OR ', $where_conditions);
    }
    
    $sql .= " ORDER BY r.uploaded_at DESC";
    
    $result = mysqli_query($conn, $sql);
    
    if (!$result) {
        error_log("[DB Error] Database error: " . mysqli_error($conn)); throw new Exception('A database error occurred');
    }
    
    $reports = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $reports[] = $row;
    }
    
    echo json_encode(['success' => true, 'reports' => $reports]);
}

function getReport($conn, $user_id) {
    $report_id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    
    if ($report_id <= 0) {
        throw new Exception('Invalid report ID');
    }
    
    $is_admin = isAdmin();
    $is_manager = isManager();
    $is_client = isClient();
    
    // Check if columns exist
    $check_assigned = mysqli_query($conn, "SHOW COLUMNS FROM reports LIKE 'assigned_to'");
    $check_account = mysqli_query($conn, "SHOW COLUMNS FROM reports LIKE 'client_account_id'");
    $has_assigned_to = $check_assigned && mysqli_num_rows($check_assigned) > 0;
    $has_client_account_id = $check_account && mysqli_num_rows($check_account) > 0;
    
    $sql = "SELECT r.*, u.name as uploaded_by_name";
    if ($has_assigned_to) {
        $sql .= ", r.assigned_to";
    }
    if ($has_client_account_id) {
        $sql .= ", r.client_account_id";
    }
    $sql .= " FROM reports r 
            LEFT JOIN users u ON r.uploaded_by = u.id 
            WHERE r.id = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log("[DB Error] Database error: " . mysqli_error($conn)); throw new Exception('A database error occurred');
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $report_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$row = mysqli_fetch_assoc($result)) {
        throw new Exception('Report not found');
    }
    
    // Access control check
    if ($is_client) {
        $allowed = false;
        if ($has_assigned_to && isset($row['assigned_to']) && (int)$row['assigned_to'] === (int)$user_id) {
            $allowed = true;
        }
        if (!$allowed) {
            $rid = (int) $row['id'];
            $rr_check = mysqli_query($conn, "SELECT 1 FROM report_recipients WHERE report_id = $rid AND user_id = " . (int)$user_id . " LIMIT 1");
            if ($rr_check && mysqli_num_rows($rr_check) > 0) {
                $allowed = true;
            }
        }
        if (!$allowed) {
            throw new Exception('Access denied');
        }
    } elseif ($is_manager && !$is_admin) {
        // Manager: Only access reports assigned to their client accounts/users
        if ($has_assigned_to || $has_client_account_id) {
            // Get client accounts assigned to this manager
            $client_accounts_sql = "SELECT id FROM users WHERE user_type = 'client' AND (password IS NULL OR password = '') AND manager_id = ?";
            $client_accounts_stmt = mysqli_prepare($conn, $client_accounts_sql);
            mysqli_stmt_bind_param($client_accounts_stmt, 'i', $user_id);
            mysqli_stmt_execute($client_accounts_stmt);
            $client_accounts_result = mysqli_stmt_get_result($client_accounts_stmt);
            
            $client_account_ids = [];
            while ($account_row = mysqli_fetch_assoc($client_accounts_result)) {
                $client_account_ids[] = $account_row['id'];
            }
            mysqli_stmt_close($client_accounts_stmt);
            
            // Get client users under those accounts
            if (!empty($client_account_ids)) {
                $sanitized_account_ids = array_map('intval', $client_account_ids);
                $account_ids_string = implode(',', $sanitized_account_ids);
                
                $client_users_sql = "SELECT id FROM users WHERE user_type = 'client' AND manager_id IN ($account_ids_string) AND password IS NOT NULL AND password != ''";
                $client_users_result = mysqli_query($conn, $client_users_sql);
                
                $client_user_ids = [];
                while ($user_row = mysqli_fetch_assoc($client_users_result)) {
                    $client_user_ids[] = $user_row['id'];
                }
                
                $all_allowed_ids = array_unique(array_merge($client_account_ids, $client_user_ids));
                
                // Check if report is assigned to allowed user or belongs to allowed account
                $has_access = false;
                if ($has_assigned_to && isset($row['assigned_to']) && in_array($row['assigned_to'], $all_allowed_ids)) {
                    $has_access = true;
                }
                if ($has_client_account_id && isset($row['client_account_id']) && in_array($row['client_account_id'], $client_account_ids)) {
                    $has_access = true;
                }
                
                if (!$has_access) {
                    throw new Exception('Access denied');
                }
            } else {
                throw new Exception('Access denied');
            }
        }
    }
    // Admin: No access control needed - can access all reports
    
    echo json_encode(['success' => true, 'report' => $row]);
}

function uploadReport($conn, $user_id) {
    $title = trim($_POST['title'] ?? '');
    $project_name = trim($_POST['project_name'] ?? '');
    $client_account_id = isset($_POST['client_account_id']) ? intval($_POST['client_account_id']) : 0;
    $client_user_ids_json = $_POST['client_user_ids'] ?? '[]';
    $client_user_ids = json_decode($client_user_ids_json, true);
    
    if (!is_array($client_user_ids)) {
        $client_user_ids = [];
    }
    
    // Remove duplicates and filter out invalid values
    $client_user_ids = array_unique(array_filter(array_map('intval', $client_user_ids), function($id) {
        return $id > 0;
    }));
    
    if (empty($title)) {
        throw new Exception('Report title is required');
    }
    
    if (empty($project_name)) {
        throw new Exception('Project name is required');
    }
    
    // Validate client account and users for admins/managers
    $is_admin = isAdmin();
    $is_manager = isManager();
    
    if (($is_admin || $is_manager)) {
        if ($client_account_id <= 0) {
            throw new Exception('Client account is required');
        }
        
        if (empty($client_user_ids)) {
            throw new Exception('At least one client user must be selected');
        }
        
        // Verify client account exists and user has access
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
        if (!empty($client_user_ids)) {
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
    }
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed');
    }
    
    $file = $_FILES['file'];
    $allowed_types = [
        'application/pdf',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain'
    ];
    
    $file_type = $file['type'];
    $file_size = $file['size'];
    $max_size = 50 * 1024 * 1024; // 50MB
    
    if ($file_size > $max_size) {
        throw new Exception('File size exceeds 50MB limit');
    }
    
    // Check file extension as well
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['pdf', 'ppt', 'pptx', 'doc', 'docx', 'txt'];
    
    if (!in_array($file_ext, $allowed_extensions)) {
        throw new Exception('Invalid file type. Allowed: PDF, PPT, PPTX, DOC, DOCX, TXT');
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = '../uploads/reports/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $file_name = uniqid() . '_' . time() . '.' . $file_ext;
    $file_path = $upload_dir . $file_name;
    
    // Check if assigned_to column exists, if not we'll handle it differently
    $check_column = mysqli_query($conn, "SHOW COLUMNS FROM reports LIKE 'assigned_to'");
    $has_assigned_to = $check_column && mysqli_num_rows($check_column) > 0;
    
    $check_column_account = mysqli_query($conn, "SHOW COLUMNS FROM reports LIKE 'client_account_id'");
    $has_client_account_id = $check_column_account && mysqli_num_rows($check_column_account) > 0;
    
    // For admins/managers with multiple users: upload ONCE, one report row, share with all selected users via report_recipients
    if (($is_admin || $is_manager) && !empty($client_user_ids)) {
        ensureReportRecipientsTable($conn);
        
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception('Failed to save file');
        }
        $relative_path = 'uploads/reports/' . $file_name;
        $first_user_id = $client_user_ids[0];
        
        if ($has_assigned_to && $has_client_account_id) {
            $sql = "INSERT INTO reports (title, project_name, file_path, file_name, file_type, file_size, uploaded_by, assigned_to, client_account_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                @unlink($file_path);
                error_log("[DB Error] Database error: " . mysqli_error($conn)); throw new Exception('A database error occurred');
            }
            mysqli_stmt_bind_param($stmt, 'sssssiiii', 
                $title, 
                $project_name, 
                $relative_path,
                $file['name'],
                $file_type,
                $file_size,
                $user_id,
                $first_user_id,
                $client_account_id
            );
        } else {
            if (!$has_assigned_to) {
                @mysqli_query($conn, "ALTER TABLE reports ADD COLUMN assigned_to INT NULL COMMENT 'Client user ID this report is assigned to'");
            }
            if (!$has_client_account_id) {
                @mysqli_query($conn, "ALTER TABLE reports ADD COLUMN client_account_id INT NULL COMMENT 'Client account ID this report belongs to'");
            }
            $sql = "INSERT INTO reports (title, project_name, file_path, file_name, file_type, file_size, uploaded_by, assigned_to, client_account_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                @unlink($file_path);
                error_log("[DB Error] Database error: " . mysqli_error($conn)); throw new Exception('A database error occurred');
            }
            mysqli_stmt_bind_param($stmt, 'sssssiiii', 
                $title, 
                $project_name, 
                $relative_path,
                $file['name'],
                $file_type,
                $file_size,
                $user_id,
                $first_user_id,
                $client_account_id
            );
        }
        
        if (!mysqli_stmt_execute($stmt)) {
            @unlink($file_path);
            error_log("[DB Error] Failed to save report: " . mysqli_error($conn)); throw new Exception('A database error occurred');
        }
        $report_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        $recipient_stmt = mysqli_prepare($conn, "INSERT IGNORE INTO report_recipients (report_id, user_id) VALUES (?, ?)");
        if ($recipient_stmt) {
            foreach ($client_user_ids as $client_user_id) {
                $uid = (int) $client_user_id;
                if ($uid > 0) {
                    mysqli_stmt_bind_param($recipient_stmt, 'ii', $report_id, $uid);
                    mysqli_stmt_execute($recipient_stmt);
                }
            }
            mysqli_stmt_close($recipient_stmt);
        }
        
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
        foreach ($client_user_ids as $client_user_id) {
            triggerClientReportNotification($conn, $report_id, (int) $client_user_id, $title, $created_by_name);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Report uploaded successfully',
            'created_count' => 1
        ]);
    } else {
        // Single report upload (for clients or if no users selected)
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception('Failed to save file');
        }
        
        $relative_path = 'uploads/reports/' . $file_name;
        
        if ($has_assigned_to && $has_client_account_id && $client_account_id > 0 && !empty($client_user_ids)) {
            // Use first user if multiple selected
            $assigned_user_id = $client_user_ids[0];
            $sql = "INSERT INTO reports (title, project_name, file_path, file_name, file_type, file_size, uploaded_by, assigned_to, client_account_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                @unlink($file_path);
                error_log("[DB Error] Database error: " . mysqli_error($conn)); throw new Exception('A database error occurred');
            }
            mysqli_stmt_bind_param($stmt, 'sssssiiii', 
                $title, 
                $project_name, 
                $relative_path,
                $file['name'],
                $file_type,
                $file_size,
                $user_id,
                $assigned_user_id,
                $client_account_id
            );
        } else {
            $sql = "INSERT INTO reports (title, project_name, file_path, file_name, file_type, file_size, uploaded_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                @unlink($file_path);
                error_log("[DB Error] Database error: " . mysqli_error($conn)); throw new Exception('A database error occurred');
            }
            mysqli_stmt_bind_param($stmt, 'sssssii', 
                $title, 
                $project_name, 
                $relative_path,
                $file['name'],
                $file_type,
                $file_size,
                $user_id
            );
        }
        
        if (!mysqli_stmt_execute($stmt)) {
            @unlink($file_path);
            error_log("[DB Error] Failed to save report: " . mysqli_error($conn)); throw new Exception('A database error occurred');
        }
        
        echo json_encode(['success' => true, 'message' => 'Report uploaded successfully']);
    }
}

function updateReport($conn, $user_id) {
    $report_id = intval($_POST['report_id'] ?? 0);
    
    if ($report_id <= 0) {
        throw new Exception('Invalid report ID');
    }
    
    // Check if report exists and was uploaded by this user
    $check_sql = "SELECT * FROM reports WHERE id = ? AND uploaded_by = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, 'ii', $report_id, $user_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    
    if (!mysqli_fetch_assoc($result)) {
        throw new Exception('Report not found or access denied');
    }
    
    $title = trim($_POST['title'] ?? '');
    $project_name = trim($_POST['project_name'] ?? '');
    
    if (empty($title)) {
        throw new Exception('Report title is required');
    }
    
    if (empty($project_name)) {
        throw new Exception('Project name is required');
    }
    
    $update_file = isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK;
    
    if ($update_file) {
        $file = $_FILES['file'];
        $file_type = $file['type'];
        $file_size = $file['size'];
        $max_size = 50 * 1024 * 1024;
        
        if ($file_size > $max_size) {
            throw new Exception('File size exceeds 50MB limit');
        }
        
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['pdf', 'ppt', 'pptx', 'doc', 'docx', 'txt'];
        
        if (!in_array($file_ext, $allowed_extensions)) {
            throw new Exception('Invalid file type. Allowed: PDF, PPT, PPTX, DOC, DOCX, TXT');
        }
        
        // Get old file path
        $old_sql = "SELECT file_path FROM reports WHERE id = ?";
        $old_stmt = mysqli_prepare($conn, $old_sql);
        mysqli_stmt_bind_param($old_stmt, 'i', $report_id);
        mysqli_stmt_execute($old_stmt);
        $old_result = mysqli_stmt_get_result($old_stmt);
        $old_row = mysqli_fetch_assoc($old_result);
        $old_file_path = '../' . $old_row['file_path'];
        
        // Upload new file
        $upload_dir = '../uploads/reports/';
        $file_name = uniqid() . '_' . time() . '.' . $file_ext;
        $file_path = $upload_dir . $file_name;
        
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception('Failed to save file');
        }
        
        // Delete old file
        if (file_exists($old_file_path)) {
            @unlink($old_file_path);
        }
        
        $relative_path = 'uploads/reports/' . $file_name;
        $sql = "UPDATE reports SET title = ?, project_name = ?, file_path = ?, file_name = ?, file_type = ?, file_size = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sssssii', 
            $title, 
            $project_name, 
            $relative_path,
            $file['name'],
            $file_type,
            $file_size,
            $report_id
        );
    } else {
        $sql = "UPDATE reports SET title = ?, project_name = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ssi', $title, $project_name, $report_id);
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        error_log("[DB Error] Failed to update report: " . mysqli_error($conn)); throw new Exception('A database error occurred');
    }
    
    echo json_encode(['success' => true, 'message' => 'Report updated successfully']);
}

function deleteReport($conn, $user_id) {
    $report_id = intval($_POST['report_id'] ?? 0);
    
    if ($report_id <= 0) {
        throw new Exception('Invalid report ID');
    }
    
    $is_admin = isAdmin();
    $is_manager = isManager();
    
    // Check if columns exist
    $check_assigned = mysqli_query($conn, "SHOW COLUMNS FROM reports LIKE 'assigned_to'");
    $check_account = mysqli_query($conn, "SHOW COLUMNS FROM reports LIKE 'client_account_id'");
    $has_assigned_to = $check_assigned && mysqli_num_rows($check_assigned) > 0;
    $has_client_account_id = $check_account && mysqli_num_rows($check_account) > 0;
    
    // Get report details
    $sql = "SELECT * FROM reports WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log("[DB Error] Database error: " . mysqli_error($conn)); throw new Exception('A database error occurred');
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $report_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$row = mysqli_fetch_assoc($result)) {
        throw new Exception('Report not found');
    }
    
    mysqli_stmt_close($stmt);
    
    // Access control check
    if ($is_manager && !$is_admin) {
        // Manager: Only delete reports assigned to their client accounts/users
        if ($has_assigned_to || $has_client_account_id) {
            // Get client accounts assigned to this manager
            $client_accounts_sql = "SELECT id FROM users WHERE user_type = 'client' AND (password IS NULL OR password = '') AND manager_id = ?";
            $client_accounts_stmt = mysqli_prepare($conn, $client_accounts_sql);
            mysqli_stmt_bind_param($client_accounts_stmt, 'i', $user_id);
            mysqli_stmt_execute($client_accounts_stmt);
            $client_accounts_result = mysqli_stmt_get_result($client_accounts_stmt);
            
            $client_account_ids = [];
            while ($account_row = mysqli_fetch_assoc($client_accounts_result)) {
                $client_account_ids[] = $account_row['id'];
            }
            mysqli_stmt_close($client_accounts_stmt);
            
            // Get client users under those accounts
            if (!empty($client_account_ids)) {
                $sanitized_account_ids = array_map('intval', $client_account_ids);
                $account_ids_string = implode(',', $sanitized_account_ids);
                
                $client_users_sql = "SELECT id FROM users WHERE user_type = 'client' AND manager_id IN ($account_ids_string) AND password IS NOT NULL AND password != ''";
                $client_users_result = mysqli_query($conn, $client_users_sql);
                
                $client_user_ids = [];
                while ($user_row = mysqli_fetch_assoc($client_users_result)) {
                    $client_user_ids[] = $user_row['id'];
                }
                
                $all_allowed_ids = array_unique(array_merge($client_account_ids, $client_user_ids));
                
                // Check if report is assigned to allowed user or belongs to allowed account
                $has_access = false;
                if ($has_assigned_to && isset($row['assigned_to']) && in_array($row['assigned_to'], $all_allowed_ids)) {
                    $has_access = true;
                }
                if ($has_client_account_id && isset($row['client_account_id']) && in_array($row['client_account_id'], $client_account_ids)) {
                    $has_access = true;
                }
                
                // Also allow if manager uploaded the report
                if ($row['uploaded_by'] == $user_id) {
                    $has_access = true;
                }
                
                if (!$has_access) {
                    throw new Exception('Access denied');
                }
            } else {
                // Manager with no assigned accounts can only delete their own uploads
                if ($row['uploaded_by'] != $user_id) {
                    throw new Exception('Access denied');
                }
            }
        } else {
            // If columns don't exist, manager can only delete their own uploads
            if ($row['uploaded_by'] != $user_id) {
                throw new Exception('Access denied');
            }
        }
    }
    // Admin: No access control needed - can delete all reports
    
    // Delete the file
    $file_path = '../' . $row['file_path'];
    if (file_exists($file_path)) {
        @unlink($file_path);
    }
    
    // Delete the report record
    $delete_sql = "DELETE FROM reports WHERE id = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_sql);
    if (!$delete_stmt) {
        error_log("[DB Error] Database error: " . mysqli_error($conn)); throw new Exception('A database error occurred');
    }
    
    mysqli_stmt_bind_param($delete_stmt, 'i', $report_id);
    
    if (!mysqli_stmt_execute($delete_stmt)) {
        error_log("[DB Error] Failed to delete report: " . mysqli_error($conn)); throw new Exception('A database error occurred');
    }
    
    mysqli_stmt_close($delete_stmt);
    
    echo json_encode(['success' => true, 'message' => 'Report deleted successfully']);
}

function viewReport($conn, $user_id) {
    $report_id = intval($_GET['id'] ?? 0);
    
    if ($report_id <= 0) {
        throw new Exception('Invalid report ID');
    }
    
    $is_admin = isAdmin();
    $is_manager = isManager();
    $is_client = isClient();
    
    // Check if columns exist
    $check_assigned = mysqli_query($conn, "SHOW COLUMNS FROM reports LIKE 'assigned_to'");
    $check_account = mysqli_query($conn, "SHOW COLUMNS FROM reports LIKE 'client_account_id'");
    $has_assigned_to = $check_assigned && mysqli_num_rows($check_assigned) > 0;
    $has_client_account_id = $check_account && mysqli_num_rows($check_account) > 0;
    
    $sql = "SELECT * FROM reports WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $report_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$row = mysqli_fetch_assoc($result)) {
        throw new Exception('Report not found');
    }
    
    // Access control check
    if ($is_client) {
        $allowed = false;
        if ($has_assigned_to && isset($row['assigned_to']) && (int)$row['assigned_to'] === (int)$user_id) {
            $allowed = true;
        }
        if (!$allowed) {
            $rid = (int) $row['id'];
            $rr_check = mysqli_query($conn, "SELECT 1 FROM report_recipients WHERE report_id = $rid AND user_id = " . (int)$user_id . " LIMIT 1");
            if ($rr_check && mysqli_num_rows($rr_check) > 0) {
                $allowed = true;
            }
        }
        if (!$allowed) {
            throw new Exception('Access denied');
        }
    } elseif ($is_manager && !$is_admin) {
        // Manager: Only access reports assigned to their client accounts/users
        if ($has_assigned_to || $has_client_account_id) {
            // Get client accounts assigned to this manager
            $client_accounts_sql = "SELECT id FROM users WHERE user_type = 'client' AND (password IS NULL OR password = '') AND manager_id = ?";
            $client_accounts_stmt = mysqli_prepare($conn, $client_accounts_sql);
            mysqli_stmt_bind_param($client_accounts_stmt, 'i', $user_id);
            mysqli_stmt_execute($client_accounts_stmt);
            $client_accounts_result = mysqli_stmt_get_result($client_accounts_stmt);
            
            $client_account_ids = [];
            while ($account_row = mysqli_fetch_assoc($client_accounts_result)) {
                $client_account_ids[] = $account_row['id'];
            }
            mysqli_stmt_close($client_accounts_stmt);
            
            // Get client users under those accounts
            if (!empty($client_account_ids)) {
                $sanitized_account_ids = array_map('intval', $client_account_ids);
                $account_ids_string = implode(',', $sanitized_account_ids);
                
                $client_users_sql = "SELECT id FROM users WHERE user_type = 'client' AND manager_id IN ($account_ids_string) AND password IS NOT NULL AND password != ''";
                $client_users_result = mysqli_query($conn, $client_users_sql);
                
                $client_user_ids = [];
                while ($user_row = mysqli_fetch_assoc($client_users_result)) {
                    $client_user_ids[] = $user_row['id'];
                }
                
                $all_allowed_ids = array_unique(array_merge($client_account_ids, $client_user_ids));
                
                // Check if report is assigned to allowed user or belongs to allowed account
                $has_access = false;
                if ($has_assigned_to && isset($row['assigned_to']) && in_array($row['assigned_to'], $all_allowed_ids)) {
                    $has_access = true;
                }
                if ($has_client_account_id && isset($row['client_account_id']) && in_array($row['client_account_id'], $client_account_ids)) {
                    $has_access = true;
                }
                
                if (!$has_access) {
                    throw new Exception('Access denied');
                }
            } else {
                throw new Exception('Access denied');
            }
        }
    }
    // Admin: No access control needed - can access all reports
    
    $file_path = '../' . $row['file_path'];
    
    if (!file_exists($file_path)) {
        throw new Exception('File not found');
    }
    
    $file_type = $row['file_type'];
    $file_name = $row['file_name'];
    
    // Set appropriate headers
    header('Content-Type: ' . $file_type);
    header('Content-Disposition: inline; filename="' . $file_name . '"');
    header('Content-Length: ' . filesize($file_path));
    
    readfile($file_path);
    exit;
}

function downloadReport($conn, $user_id) {
    $report_id = intval($_GET['id'] ?? 0);
    
    if ($report_id <= 0) {
        throw new Exception('Invalid report ID');
    }
    
    $is_admin = isAdmin();
    $is_manager = isManager();
    $is_client = isClient();
    
    // Check if columns exist
    $check_assigned = mysqli_query($conn, "SHOW COLUMNS FROM reports LIKE 'assigned_to'");
    $check_account = mysqli_query($conn, "SHOW COLUMNS FROM reports LIKE 'client_account_id'");
    $has_assigned_to = $check_assigned && mysqli_num_rows($check_assigned) > 0;
    $has_client_account_id = $check_account && mysqli_num_rows($check_account) > 0;
    
    $sql = "SELECT * FROM reports WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $report_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$row = mysqli_fetch_assoc($result)) {
        throw new Exception('Report not found');
    }
    
    // Access control check
    if ($is_client) {
        $allowed = false;
        if ($has_assigned_to && isset($row['assigned_to']) && (int)$row['assigned_to'] === (int)$user_id) {
            $allowed = true;
        }
        if (!$allowed) {
            $rid = (int) $row['id'];
            $rr_check = mysqli_query($conn, "SELECT 1 FROM report_recipients WHERE report_id = $rid AND user_id = " . (int)$user_id . " LIMIT 1");
            if ($rr_check && mysqli_num_rows($rr_check) > 0) {
                $allowed = true;
            }
        }
        if (!$allowed) {
            throw new Exception('Access denied');
        }
    } elseif ($is_manager && !$is_admin) {
        // Manager: Only access reports assigned to their client accounts/users
        if ($has_assigned_to || $has_client_account_id) {
            // Get client accounts assigned to this manager
            $client_accounts_sql = "SELECT id FROM users WHERE user_type = 'client' AND (password IS NULL OR password = '') AND manager_id = ?";
            $client_accounts_stmt = mysqli_prepare($conn, $client_accounts_sql);
            mysqli_stmt_bind_param($client_accounts_stmt, 'i', $user_id);
            mysqli_stmt_execute($client_accounts_stmt);
            $client_accounts_result = mysqli_stmt_get_result($client_accounts_stmt);
            
            $client_account_ids = [];
            while ($account_row = mysqli_fetch_assoc($client_accounts_result)) {
                $client_account_ids[] = $account_row['id'];
            }
            mysqli_stmt_close($client_accounts_stmt);
            
            // Get client users under those accounts
            if (!empty($client_account_ids)) {
                $sanitized_account_ids = array_map('intval', $client_account_ids);
                $account_ids_string = implode(',', $sanitized_account_ids);
                
                $client_users_sql = "SELECT id FROM users WHERE user_type = 'client' AND manager_id IN ($account_ids_string) AND password IS NOT NULL AND password != ''";
                $client_users_result = mysqli_query($conn, $client_users_sql);
                
                $client_user_ids = [];
                while ($user_row = mysqli_fetch_assoc($client_users_result)) {
                    $client_user_ids[] = $user_row['id'];
                }
                
                $all_allowed_ids = array_unique(array_merge($client_account_ids, $client_user_ids));
                
                // Check if report is assigned to allowed user or belongs to allowed account
                $has_access = false;
                if ($has_assigned_to && isset($row['assigned_to']) && in_array($row['assigned_to'], $all_allowed_ids)) {
                    $has_access = true;
                }
                if ($has_client_account_id && isset($row['client_account_id']) && in_array($row['client_account_id'], $client_account_ids)) {
                    $has_access = true;
                }
                
                if (!$has_access) {
                    throw new Exception('Access denied');
                }
            } else {
                throw new Exception('Access denied');
            }
        }
    }
    // Admin: No access control needed - can access all reports
    
    $file_path = '../' . $row['file_path'];
    
    if (!file_exists($file_path)) {
        throw new Exception('File not found');
    }
    
    $file_name = $row['file_name'];
    
    // Set appropriate headers for download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Content-Length: ' . filesize($file_path));
    
    readfile($file_path);
    exit;
}

// Close database connection
if (isset($conn) && $conn instanceof mysqli) {
    mysqli_close($conn);
}
