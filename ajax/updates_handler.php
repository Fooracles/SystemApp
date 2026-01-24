<?php
// Session is automatically started by includes/functions.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_functions.php';

// Ensure updates table has correct columns (run migration if needed)
if (tableExists($conn, 'updates')) {
    ensureUpdatesColumns($conn);
}

// Ensure users table has profile_photo column (if it doesn't exist, queries will still work with NULL)
ensureUsersColumns($conn);

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if user is Admin, Manager, or Client
if (!isAdmin() && !isManager() && !isClient()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$user_id = $_SESSION['id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// View, download, and play actions don't return JSON
if ($action !== 'download_attachment' && $action !== 'play_voice') {
    header('Content-Type: application/json');
}

try {
    switch ($action) {
        case 'get_updates':
            getUpdates($conn, $user_id);
            break;
        case 'get_update':
            getUpdate($conn, $user_id);
            break;
        case 'create_update':
            if (!isAdmin() && !isManager() && !isClient()) {
                throw new Exception('Only admins, managers, and clients can create updates');
            }
            createUpdate($conn, $user_id);
            break;
        case 'update_update':
            if (!isAdmin() && !isManager() && !isClient()) {
                throw new Exception('Only admins, managers, and clients can update updates');
            }
            updateUpdate($conn, $user_id);
            break;
        case 'delete_update':
            if (!isAdmin() && !isManager() && !isClient()) {
                throw new Exception('Only admins, managers, and clients can delete updates');
            }
            deleteUpdate($conn, $user_id);
            break;
        case 'download_attachment':
            downloadAttachment($conn, $user_id);
            break;
        case 'play_voice':
            playVoiceRecording($conn, $user_id);
            break;
        case 'get_client_accounts':
            if (!isAdmin() && !isManager()) {
                throw new Exception('Only admins and managers can access this');
            }
            getClientAccounts($conn, $user_id);
            break;
        case 'get_client_users':
            if (!isAdmin() && !isManager()) {
                throw new Exception('Only admins and managers can access this');
            }
            getClientUsers($conn, $user_id);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if ($action !== 'download_attachment' && $action !== 'play_voice') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        http_response_code(404);
        echo 'File not found';
    }
}

function getUpdates($conn, $user_id) {
    // Get optional filter parameters (for Manager/Admin dropdown filtering)
    $client_account_id = isset($_GET['client_account_id']) ? intval($_GET['client_account_id']) : 0;
    $client_user_id = isset($_GET['client_user_id']) ? intval($_GET['client_user_id']) : 0;
    
    // Get current user info
    $user_sql = "SELECT id, user_type, manager_id FROM users WHERE id = ?";
    $user_stmt = mysqli_prepare($conn, $user_sql);
    mysqli_stmt_bind_param($user_stmt, 'i', $user_id);
    mysqli_stmt_execute($user_stmt);
    $user_result = mysqli_stmt_get_result($user_stmt);
    $current_user = mysqli_fetch_assoc($user_result);
    mysqli_stmt_close($user_stmt);
    
    if (!$current_user) {
        throw new Exception('User not found');
    }
    
    $is_admin = isAdmin();
    $is_manager = isManager();
    $is_client = isClient();
    
    // Build visibility filter based on user role
    $where_conditions = [];
    $params = [];
    $param_types = '';
    
    // Handle filtering for Manager/Admin when dropdown selections are made
    if (($is_admin || $is_manager) && $client_user_id > 0) {
        // Filter by specific client user: show updates between that user and their manager(s)
        $client_user_sql = "SELECT manager_id FROM users WHERE id = ? AND user_type = 'client'";
        $client_user_stmt = mysqli_prepare($conn, $client_user_sql);
        mysqli_stmt_bind_param($client_user_stmt, 'i', $client_user_id);
        mysqli_stmt_execute($client_user_stmt);
        $client_user_result = mysqli_stmt_get_result($client_user_stmt);
        $client_user_data = mysqli_fetch_assoc($client_user_result);
        mysqli_stmt_close($client_user_stmt);
        
        if ($client_user_data && $client_user_data['manager_id']) {
            // Show updates from the client user and their manager
            $sql = "SELECT u.*, usr.name as created_by_name, usr.user_type as created_by_user_type, usr.profile_photo as created_by_photo
                    FROM updates u 
                    LEFT JOIN users usr ON u.created_by = usr.id 
                    WHERE u.created_by IN (?, ?)
                    ORDER BY u.created_at DESC";
            $params = array($client_user_id, $client_user_data['manager_id']);
            $param_types = 'ii';
        } else {
            // Only show updates from the client user
            $sql = "SELECT u.*, usr.name as created_by_name, usr.user_type as created_by_user_type, usr.profile_photo as created_by_photo
                    FROM updates u 
                    LEFT JOIN users usr ON u.created_by = usr.id 
                    WHERE u.created_by = ?
                    ORDER BY u.created_at DESC";
            $params = array($client_user_id);
            $param_types = 'i';
        }
    } elseif (($is_admin || $is_manager) && $client_account_id > 0) {
        // Filter by client account: show all updates from account, all its users, and associated managers
        // Get the manager_id for this client account
        $account_sql = "SELECT manager_id FROM users WHERE id = ? AND user_type = 'client' AND (password IS NULL OR password = '')";
        $account_stmt = mysqli_prepare($conn, $account_sql);
        mysqli_stmt_bind_param($account_stmt, 'i', $client_account_id);
        mysqli_stmt_execute($account_stmt);
        $account_result = mysqli_stmt_get_result($account_stmt);
        $account_data = mysqli_fetch_assoc($account_result);
        mysqli_stmt_close($account_stmt);
        
        // Get all client users under this account
        $client_users_sql = "SELECT id FROM users WHERE manager_id = ? AND user_type = 'client' AND password IS NOT NULL AND password != ''";
        $client_users_stmt = mysqli_prepare($conn, $client_users_sql);
        mysqli_stmt_bind_param($client_users_stmt, 'i', $client_account_id);
        mysqli_stmt_execute($client_users_stmt);
        $client_users_result = mysqli_stmt_get_result($client_users_stmt);
        
        $user_ids = array();
        if ($account_data && $account_data['manager_id']) {
            $user_ids[] = $account_data['manager_id']; // Add manager
        }
        $user_ids[] = $client_account_id; // Add the account itself
        while ($row = mysqli_fetch_assoc($client_users_result)) {
            $user_ids[] = $row['id']; // Add all client users
        }
        mysqli_stmt_close($client_users_stmt);
        
        if (count($user_ids) > 0) {
            $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
            $sql = "SELECT u.*, usr.name as created_by_name, usr.user_type as created_by_user_type, usr.profile_photo as created_by_photo
                    FROM updates u 
                    LEFT JOIN users usr ON u.created_by = usr.id 
                    WHERE u.created_by IN ($placeholders)
                    ORDER BY u.created_at DESC";
            $params = $user_ids;
            $param_types = str_repeat('i', count($user_ids));
        } else {
            // No users found, return empty
            $sql = "SELECT u.*, usr.name as created_by_name, usr.user_type as created_by_user_type, usr.profile_photo as created_by_photo
                    FROM updates u 
                    LEFT JOIN users usr ON u.created_by = usr.id 
                    WHERE 1=0
                    ORDER BY u.created_at DESC";
            $params = array();
            $param_types = '';
        }
    } elseif ($is_admin) {
        // Admin can see all updates (when no filter is applied)
        $sql = "SELECT u.*, usr.name as created_by_name, usr.user_type as created_by_user_type, usr.profile_photo as created_by_photo
                FROM updates u 
                LEFT JOIN users usr ON u.created_by = usr.id 
                ORDER BY u.created_at DESC";
    } elseif ($is_manager) {
        // Manager can only see updates from their associated Client Users
        // Get Client Accounts assigned to this manager, then get Client Users under those accounts
        $client_accounts_sql = "SELECT id FROM users WHERE user_type = 'client' AND manager_id = ?";
        $client_accounts_stmt = mysqli_prepare($conn, $client_accounts_sql);
        mysqli_stmt_bind_param($client_accounts_stmt, 'i', $user_id);
        mysqli_stmt_execute($client_accounts_stmt);
        $client_accounts_result = mysqli_stmt_get_result($client_accounts_stmt);
        
        $client_account_ids = array();
        while ($client_account_row = mysqli_fetch_assoc($client_accounts_result)) {
            $client_account_ids[] = $client_account_row['id'];
        }
        mysqli_stmt_close($client_accounts_stmt);
        
        // Get Client Users under those Client Accounts (Client Users have user_type = 'client' and manager_id pointing to Client Account)
        $client_user_ids = array($user_id); // Include manager's own updates
        if (!empty($client_account_ids)) {
            $placeholders = implode(',', array_fill(0, count($client_account_ids), '?'));
            $client_users_sql = "SELECT id FROM users WHERE user_type = 'client' AND manager_id IN ($placeholders)";
            $client_users_stmt = mysqli_prepare($conn, $client_users_sql);
            if ($client_users_stmt) {
                $refs = array();
                foreach ($client_account_ids as $key => $value) {
                    $refs[$key] = &$client_account_ids[$key];
                }
                $bind_params = array_merge(array(str_repeat('i', count($client_account_ids))), $refs);
                call_user_func_array(array($client_users_stmt, 'bind_param'), $bind_params);
                mysqli_stmt_execute($client_users_stmt);
                $client_users_result = mysqli_stmt_get_result($client_users_stmt);
                while ($client_user_row = mysqli_fetch_assoc($client_users_result)) {
                    $client_user_ids[] = $client_user_row['id'];
                }
                mysqli_stmt_close($client_users_stmt);
            }
        }
        
        $client_ids = $client_user_ids;
        
        if (count($client_ids) == 1) {
            // Only own updates (no clients assigned)
            $sql = "SELECT u.*, usr.name as created_by_name, usr.user_type as created_by_user_type, usr.profile_photo as created_by_photo
                    FROM updates u 
                    LEFT JOIN users usr ON u.created_by = usr.id 
                    WHERE u.created_by = ?
                    ORDER BY u.created_at DESC";
            $params = [$user_id];
            $param_types = 'i';
        } else {
            // Multiple IDs - use IN clause
            $placeholders = implode(',', array_fill(0, count($client_ids), '?'));
            $sql = "SELECT u.*, usr.name as created_by_name, usr.user_type as created_by_user_type, usr.profile_photo as created_by_photo
                    FROM updates u 
                    LEFT JOIN users usr ON u.created_by = usr.id 
                    WHERE u.created_by IN ($placeholders)
                    ORDER BY u.created_at DESC";
            $params = $client_ids;
            $param_types = str_repeat('i', count($client_ids));
        }
    } elseif ($is_client) {
        // Client can see updates from their assigned manager, own updates, and updates targeted to them
        // Get user info including password to determine if client user or client account
        $user_info_sql = "SELECT manager_id, password FROM users WHERE id = ?";
        $user_info_stmt = mysqli_prepare($conn, $user_info_sql);
        mysqli_stmt_bind_param($user_info_stmt, 'i', $user_id);
        mysqli_stmt_execute($user_info_stmt);
        $user_info_result = mysqli_stmt_get_result($user_info_stmt);
        $user_info = mysqli_fetch_assoc($user_info_result);
        mysqli_stmt_close($user_info_stmt);
        
        $client_manager_id = $user_info['manager_id'] ?? null;
        $has_password = !empty($user_info['password']);
        $actual_manager_id = null;
        $client_account_id = null;
        
        // Determine if this is a client user (has password) or client account (no password)
        if ($has_password && $client_manager_id) {
            // This is a client user - manager_id points to client account
            $client_account_id = $client_manager_id;
            
            // Get the actual manager/admin from the client account
            // Client accounts have user_type='client' and (password IS NULL OR password = '')
            $account_sql = "SELECT manager_id, user_type FROM users WHERE id = ?";
            $account_stmt = mysqli_prepare($conn, $account_sql);
            mysqli_stmt_bind_param($account_stmt, 'i', $client_account_id);
            mysqli_stmt_execute($account_stmt);
            $account_result = mysqli_stmt_get_result($account_stmt);
            $account_data = mysqli_fetch_assoc($account_result);
            mysqli_stmt_close($account_stmt);
            
            // Verify this is actually a client account (not a client user)
            if ($account_data && $account_data['user_type'] == 'client') {
                if ($account_data['manager_id']) {
                    $actual_manager_id = $account_data['manager_id'];
                }
            }
        } else if ($client_manager_id) {
            // This is a client account - manager_id points directly to manager/admin
            $actual_manager_id = $client_manager_id;
        }
        
        // Build list of user IDs to show updates from
        $user_ids_to_show = array($user_id); // Always show own updates
        
        if ($actual_manager_id) {
            $user_ids_to_show[] = $actual_manager_id; // Show updates from manager/admin
        }
        
        if ($client_account_id && $client_account_id != $user_id) {
            $user_ids_to_show[] = $client_account_id; // Show updates from client account
        }
        
        // Remove duplicates and re-index
        $user_ids_to_show = array_values(array_unique($user_ids_to_show));
        
        // Build WHERE clause conditions
        // Key: Show ALL updates from manager/admin (regardless of target_client_id)
        //      + updates targeted to this client or client account
        //      + own updates
        $where_conditions = array();
        $params = array();
        $param_types = '';
        
        // Condition 1: Show updates created by client themselves, their manager/admin, or their client account
        // This includes ALL updates from manager/admin, even if target_client_id is NULL
        if (count($user_ids_to_show) > 0) {
            $placeholders = implode(',', array_fill(0, count($user_ids_to_show), '?'));
            $where_conditions[] = "u.created_by IN ($placeholders)";
            $params = array_merge($params, $user_ids_to_show);
            $param_types .= str_repeat('i', count($user_ids_to_show));
        }
        
        // Condition 2: Show updates explicitly targeted to this client user
        $where_conditions[] = "u.target_client_id = ?";
        $params[] = $user_id;
        $param_types .= 'i';
        
        // Condition 3: Show updates explicitly targeted to client account (if client user)
        if ($client_account_id && $client_account_id != $user_id) {
            $where_conditions[] = "u.target_client_id = ?";
            $params[] = $client_account_id;
            $param_types .= 'i';
        }
        
        // Fallback: at least show own updates
        if (empty($where_conditions)) {
            $where_conditions[] = "u.created_by = ?";
            $params = array($user_id);
            $param_types = 'i';
        }
        
        $where_clause = '(' . implode(' OR ', $where_conditions) . ')';
        
        $sql = "SELECT u.*, usr.name as created_by_name, usr.user_type as created_by_user_type, usr.profile_photo as created_by_photo
                FROM updates u 
                LEFT JOIN users usr ON u.created_by = usr.id 
                WHERE $where_clause
                ORDER BY u.created_at DESC";
        
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            throw new Exception('Database error: ' . mysqli_error($conn) . ' - SQL: ' . $sql);
        }
        
        if (!empty($params)) {
            // Bind parameters - handle variable number of parameters correctly
            $refs = array();
            foreach ($params as $key => $value) {
                $refs[$key] = &$params[$key];
            }
            $bind_params = array_merge(array($param_types), $refs);
            call_user_func_array(array($stmt, 'bind_param'), $bind_params);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (!$result) {
            mysqli_stmt_close($stmt);
            throw new Exception('Database error: ' . mysqli_error($conn));
        }
        
        $updates = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $updates[] = $row;
        }
        
        mysqli_stmt_close($stmt);
        
        echo json_encode(['success' => true, 'updates' => $updates]);
        return; // Exit early for client case
    } else {
        // Fallback: only own updates
        $sql = "SELECT u.*, usr.name as created_by_name, usr.user_type as created_by_user_type, usr.profile_photo as created_by_photo
                FROM updates u 
                LEFT JOIN users usr ON u.created_by = usr.id 
                WHERE u.created_by = ?
                ORDER BY u.created_at DESC";
        $params = array($user_id);
        $param_types = 'i';
    }
    
    // Execute query for non-client cases
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    
    if (!empty($params)) {
        // Bind parameters - handle variable number of parameters correctly
        $refs = array();
        foreach ($params as $key => $value) {
            $refs[$key] = &$params[$key];
        }
        $bind_params = array_merge(array($param_types), $refs);
        call_user_func_array(array($stmt, 'bind_param'), $bind_params);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        mysqli_stmt_close($stmt);
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    
    $updates = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $updates[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    
    echo json_encode(['success' => true, 'updates' => $updates]);
}

function getUpdate($conn, $user_id) {
    $update_id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    
    if ($update_id <= 0) {
        throw new Exception('Invalid update ID');
    }
    
    // Get current user info
    $user_sql = "SELECT id, user_type, manager_id FROM users WHERE id = ?";
    $user_stmt = mysqli_prepare($conn, $user_sql);
    mysqli_stmt_bind_param($user_stmt, 'i', $user_id);
    mysqli_stmt_execute($user_stmt);
    $user_result = mysqli_stmt_get_result($user_stmt);
    $current_user = mysqli_fetch_assoc($user_result);
    mysqli_stmt_close($user_stmt);
    
    if (!$current_user) {
        throw new Exception('User not found');
    }
    
    $sql = "SELECT u.*, usr.name as created_by_name, usr.user_type as created_by_user_type, usr.profile_photo as created_by_photo
            FROM updates u 
            LEFT JOIN users usr ON u.created_by = usr.id 
            WHERE u.id = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $update_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $row = mysqli_fetch_assoc($result);
    if (!$row) {
        throw new Exception('Update not found');
    }
    
    // Check visibility permissions
    $is_admin = isAdmin();
    $is_manager = isManager();
    $is_client = isClient();
    $update_creator_id = $row['created_by'];
    
    $has_access = false;
    
    if ($is_admin) {
        // Admin can see all updates
        $has_access = true;
    } elseif ($is_manager) {
        // Manager can see updates from their clients or their own
        if ($update_creator_id == $user_id) {
            $has_access = true;
        } else {
            // Check if creator is a client assigned to this manager
            $check_client_sql = "SELECT id FROM users WHERE id = ? AND user_type = 'client' AND manager_id = ?";
            $check_client_stmt = mysqli_prepare($conn, $check_client_sql);
            mysqli_stmt_bind_param($check_client_stmt, 'ii', $update_creator_id, $user_id);
            mysqli_stmt_execute($check_client_stmt);
            $check_client_result = mysqli_stmt_get_result($check_client_stmt);
            if (mysqli_num_rows($check_client_result) > 0) {
                $has_access = true;
            }
            mysqli_stmt_close($check_client_stmt);
        }
    } elseif ($is_client) {
        // Client can see updates from their manager or their own
        if ($update_creator_id == $user_id) {
            $has_access = true;
        } else {
            $manager_id = $current_user['manager_id'] ?? null;
            if ($manager_id && $update_creator_id == $manager_id) {
                $has_access = true;
            }
        }
    }
    
    if (!$has_access) {
        throw new Exception('You do not have permission to view this update');
    }
    
    echo json_encode(['success' => true, 'update' => $row]);
}

function createUpdate($conn, $user_id) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    
    if (empty($title)) {
        throw new Exception('Title is required');
    }
    
    if (empty($content)) {
        throw new Exception('Content is required');
    }
    
    $attachment_path = null;
    $attachment_name = null;
    $voice_recording_path = null;
    
    // Handle attachment upload
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $allowed_types = [
            'application/pdf',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg',
            'image/png',
            'image/gif',
            'video/mp4',
            'video/avi',
            'video/quicktime'
        ];
        
        $file_type = $file['type'];
        $file_size = $file['size'];
        $max_size = 50 * 1024 * 1024; // 50MB
        
        if ($file_size > $max_size) {
            throw new Exception('File size exceeds 50MB limit');
        }
        
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['pdf', 'ppt', 'pptx', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'zip', 'rar', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'mp4', 'avi', 'mov', 'wmv', 'flv', 'mp3', 'wav', 'm4a', 'aac'];
        
        if (!in_array($file_ext, $allowed_extensions)) {
            throw new Exception('Invalid file type. Allowed: PDF, PPT, DOC, XLS, TXT, CSV, ZIP, Images, Video, Audio');
        }
        
        $upload_dir = '../uploads/updates/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_name = uniqid() . '_' . time() . '.' . $file_ext;
        $file_path = $upload_dir . $file_name;
        
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception('Failed to save attachment');
        }
        
        $attachment_path = 'uploads/updates/' . $file_name;
        $attachment_name = $file['name'];
    }
    
    // Handle voice recording (base64 data)
    if (!empty($_POST['voice_recording'])) {
        $voice_data = $_POST['voice_recording'];
        
        // Decode base64 data
        if (preg_match('/^data:audio\/(\w+);base64,/', $voice_data, $matches)) {
            $audio_data = substr($voice_data, strpos($voice_data, ',') + 1);
            $audio_data = base64_decode($audio_data);
            
            $upload_dir = '../uploads/updates/voice/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $voice_file_name = uniqid() . '_' . time() . '.webm';
            $voice_file_path = $upload_dir . $voice_file_name;
            
            if (file_put_contents($voice_file_path, $audio_data) === false) {
                throw new Exception('Failed to save voice recording');
            }
            
            $voice_recording_path = 'uploads/updates/voice/' . $voice_file_name;
        }
    }
    
    // Get target_client_id for admin/manager (optional)
    $target_client_id = null;
    $is_admin = isAdmin();
    $is_manager = isManager();
    
    if (($is_admin || $is_manager) && isset($_POST['target_client_id']) && !empty($_POST['target_client_id'])) {
        $target_client_id = intval($_POST['target_client_id']);
        // Validate that the target is a client user
        $validate_sql = "SELECT id FROM users WHERE id = ? AND user_type = 'client'";
        $validate_stmt = mysqli_prepare($conn, $validate_sql);
        mysqli_stmt_bind_param($validate_stmt, 'i', $target_client_id);
        mysqli_stmt_execute($validate_stmt);
        $validate_result = mysqli_stmt_get_result($validate_stmt);
        if (mysqli_num_rows($validate_result) == 0) {
            mysqli_stmt_close($validate_stmt);
            throw new Exception('Invalid target client user');
        }
        mysqli_stmt_close($validate_stmt);
    }
    
    $sql = "INSERT INTO updates (title, content, attachment_path, attachment_name, voice_recording_path, created_by, target_client_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        // Clean up uploaded files if database insert fails
        if ($attachment_path) @unlink('../' . $attachment_path);
        if ($voice_recording_path) @unlink('../' . $voice_recording_path);
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, 'sssssii', $title, $content, $attachment_path, $attachment_name, $voice_recording_path, $user_id, $target_client_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        // Clean up uploaded files if database insert fails
        if ($attachment_path) @unlink('../' . $attachment_path);
        if ($voice_recording_path) @unlink('../' . $voice_recording_path);
        throw new Exception('Failed to create update: ' . mysqli_error($conn));
    }
    
    echo json_encode(['success' => true, 'message' => 'Update created successfully']);
}

function updateUpdate($conn, $user_id) {
    $update_id = intval($_POST['update_id'] ?? 0);
    
    if ($update_id <= 0) {
        throw new Exception('Invalid update ID');
    }
    
    // Check if update exists and was created by this user (or user is admin)
    $check_sql = "SELECT * FROM updates WHERE id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, 'i', $update_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    
    $update = mysqli_fetch_assoc($result);
    if (!$update) {
        throw new Exception('Update not found');
    }
    
    // Strict ownership: Only the creator can edit their own updates (applies to all roles)
    if ($update['created_by'] != $user_id) {
        throw new Exception('You can only edit your own updates');
    }
    
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    
    if (empty($title)) {
        throw new Exception('Title is required');
    }
    
    if (empty($content)) {
        throw new Exception('Content is required');
    }
    
    $attachment_path = $update['attachment_path'];
    $attachment_name = $update['attachment_name'];
    $voice_recording_path = $update['voice_recording_path'];
    $target_client_id = $update['target_client_id']; // Keep existing target or update it
    
    // Handle target_client_id update for admin/manager
    $is_admin = isAdmin();
    $is_manager = isManager();
    
    if (($is_admin || $is_manager)) {
        // Allow updating target_client_id (can be set to NULL to make it general)
        if (isset($_POST['target_client_id'])) {
            if (empty($_POST['target_client_id']) || $_POST['target_client_id'] === '') {
                $target_client_id = null; // Clear target (make it general)
            } else {
                $new_target_client_id = intval($_POST['target_client_id']);
                // Validate that the target is a client user
                $validate_sql = "SELECT id FROM users WHERE id = ? AND user_type = 'client'";
                $validate_stmt = mysqli_prepare($conn, $validate_sql);
                mysqli_stmt_bind_param($validate_stmt, 'i', $new_target_client_id);
                mysqli_stmt_execute($validate_stmt);
                $validate_result = mysqli_stmt_get_result($validate_stmt);
                if (mysqli_num_rows($validate_result) == 0) {
                    mysqli_stmt_close($validate_stmt);
                    throw new Exception('Invalid target client user');
                }
                mysqli_stmt_close($validate_stmt);
                $target_client_id = $new_target_client_id;
            }
        }
    }
    
    // Handle attachment upload (if new file is provided)
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        // Delete old attachment if exists
        if ($attachment_path && file_exists('../' . $attachment_path)) {
            @unlink('../' . $attachment_path);
        }
        
        $file = $_FILES['attachment'];
        $allowed_types = [
            'application/pdf',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg',
            'image/png',
            'image/gif',
            'video/mp4',
            'video/avi',
            'video/quicktime'
        ];
        
        $file_type = $file['type'];
        $file_size = $file['size'];
        $max_size = 50 * 1024 * 1024;
        
        if ($file_size > $max_size) {
            throw new Exception('File size exceeds 50MB limit');
        }
        
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['pdf', 'ppt', 'pptx', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'zip', 'rar', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'mp4', 'avi', 'mov', 'wmv', 'flv', 'mp3', 'wav', 'm4a', 'aac'];
        
        if (!in_array($file_ext, $allowed_extensions)) {
            throw new Exception('Invalid file type. Allowed: PDF, PPT, DOC, XLS, TXT, CSV, ZIP, Images, Video, Audio');
        }
        
        $upload_dir = '../uploads/updates/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_name = uniqid() . '_' . time() . '.' . $file_ext;
        $file_path = $upload_dir . $file_name;
        
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception('Failed to save attachment');
        }
        
        $attachment_path = 'uploads/updates/' . $file_name;
        $attachment_name = $file['name'];
    }
    
    // Handle voice recording (if new recording is provided)
    if (!empty($_POST['voice_recording'])) {
        // Delete old voice recording if exists
        if ($voice_recording_path && file_exists('../' . $voice_recording_path)) {
            @unlink('../' . $voice_recording_path);
        }
        
        $voice_data = $_POST['voice_recording'];
        
        if (preg_match('/^data:audio\/(\w+);base64,/', $voice_data, $matches)) {
            $audio_data = substr($voice_data, strpos($voice_data, ',') + 1);
            $audio_data = base64_decode($audio_data);
            
            $upload_dir = '../uploads/updates/voice/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $voice_file_name = uniqid() . '_' . time() . '.webm';
            $voice_file_path = $upload_dir . $voice_file_name;
            
            if (file_put_contents($voice_file_path, $audio_data) === false) {
                throw new Exception('Failed to save voice recording');
            }
            
            $voice_recording_path = 'uploads/updates/voice/' . $voice_file_name;
        }
    }
    
    // Handle NULL target_client_id properly
    if ($target_client_id === null) {
        $sql = "UPDATE updates SET title = ?, content = ?, attachment_path = ?, attachment_name = ?, voice_recording_path = ?, target_client_id = NULL WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        
        if (!$stmt) {
            throw new Exception('Database error: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, 'sssssi', $title, $content, $attachment_path, $attachment_name, $voice_recording_path, $update_id);
    } else {
        $sql = "UPDATE updates SET title = ?, content = ?, attachment_path = ?, attachment_name = ?, voice_recording_path = ?, target_client_id = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        
        if (!$stmt) {
            throw new Exception('Database error: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, 'sssssii', $title, $content, $attachment_path, $attachment_name, $voice_recording_path, $target_client_id, $update_id);
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to update update: ' . mysqli_error($conn));
    }
    
    echo json_encode(['success' => true, 'message' => 'Update updated successfully']);
}

function deleteUpdate($conn, $user_id) {
    $update_id = intval($_POST['update_id'] ?? 0);
    
    if ($update_id <= 0) {
        throw new Exception('Invalid update ID');
    }
    
    // Check if update exists and was created by this user (or user is admin)
    $check_sql = "SELECT * FROM updates WHERE id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, 'i', $update_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    
    $update = mysqli_fetch_assoc($result);
    if (!$update) {
        throw new Exception('Update not found');
    }
    
    // Strict ownership: Only the creator can delete their own updates (applies to all roles)
    if ($update['created_by'] != $user_id) {
        throw new Exception('You can only delete your own updates');
    }
    
    $sql = "DELETE FROM updates WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $update_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to delete update: ' . mysqli_error($conn));
    }
    
    // Delete associated files
    if ($update['attachment_path'] && file_exists('../' . $update['attachment_path'])) {
        @unlink('../' . $update['attachment_path']);
    }
    if ($update['voice_recording_path'] && file_exists('../' . $update['voice_recording_path'])) {
        @unlink('../' . $update['voice_recording_path']);
    }
    
    echo json_encode(['success' => true, 'message' => 'Update deleted successfully']);
}

function downloadAttachment($conn, $user_id) {
    $update_id = intval($_GET['id'] ?? 0);
    
    if ($update_id <= 0) {
        throw new Exception('Invalid update ID');
    }
    
    // Get current user info for visibility check
    $user_sql = "SELECT id, user_type, manager_id FROM users WHERE id = ?";
    $user_stmt = mysqli_prepare($conn, $user_sql);
    mysqli_stmt_bind_param($user_stmt, 'i', $user_id);
    mysqli_stmt_execute($user_stmt);
    $user_result = mysqli_stmt_get_result($user_stmt);
    $current_user = mysqli_fetch_assoc($user_result);
    mysqli_stmt_close($user_stmt);
    
    $sql = "SELECT u.*, usr.user_type as created_by_user_type 
            FROM updates u 
            LEFT JOIN users usr ON u.created_by = usr.id 
            WHERE u.id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $update_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$row = mysqli_fetch_assoc($result)) {
        throw new Exception('Update not found');
    }
    
    // Check visibility permissions (same as getUpdate)
    $is_admin = isAdmin();
    $is_manager = isManager();
    $is_client = isClient();
    $update_creator_id = $row['created_by'];
    
    $has_access = false;
    
    if ($is_admin) {
        $has_access = true;
    } elseif ($is_manager) {
        if ($update_creator_id == $user_id) {
            $has_access = true;
        } else {
            $check_client_sql = "SELECT id FROM users WHERE id = ? AND user_type = 'client' AND manager_id = ?";
            $check_client_stmt = mysqli_prepare($conn, $check_client_sql);
            mysqli_stmt_bind_param($check_client_stmt, 'ii', $update_creator_id, $user_id);
            mysqli_stmt_execute($check_client_stmt);
            $check_client_result = mysqli_stmt_get_result($check_client_stmt);
            if (mysqli_num_rows($check_client_result) > 0) {
                $has_access = true;
            }
            mysqli_stmt_close($check_client_stmt);
        }
    } elseif ($is_client) {
        if ($update_creator_id == $user_id) {
            $has_access = true;
        } else {
            $manager_id = $current_user['manager_id'] ?? null;
            if ($manager_id && $update_creator_id == $manager_id) {
                $has_access = true;
            }
        }
    }
    
    if (!$has_access) {
        throw new Exception('You do not have permission to access this attachment');
    }
    
    if (!$row['attachment_path'] || empty(trim($row['attachment_path']))) {
        throw new Exception('No attachment found');
    }
    
    $file_path = '../' . $row['attachment_path'];
    
    if (!file_exists($file_path)) {
        throw new Exception('File not found');
    }
    
    $file_name = $row['attachment_name'] ?: basename($file_path);
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Content-Length: ' . filesize($file_path));
    
    readfile($file_path);
    exit;
}

function getClientAccounts($conn, $user_id) {
    $is_admin = isAdmin();
    $is_manager = isManager();
    
    if (!$is_admin && !$is_manager) {
        throw new Exception('Access denied');
    }
    
    // Get client accounts (user_type = 'client', password is NULL or empty)
    // For managers, only show accounts assigned to them
    if ($is_manager && !$is_admin) {
        $sql = "SELECT id, name, username FROM users 
                WHERE user_type = 'client' 
                AND (password IS NULL OR password = '') 
                AND manager_id = ?
                ORDER BY name";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
    } else {
        // Admin can see all client accounts
        $sql = "SELECT id, name, username FROM users 
                WHERE user_type = 'client' 
                AND (password IS NULL OR password = '') 
                ORDER BY name";
        $stmt = mysqli_prepare($conn, $sql);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $client_accounts = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $client_accounts[] = [
            'id' => $row['id'],
            'name' => $row['name'] ?: $row['username']
        ];
    }
    
    mysqli_stmt_close($stmt);
    
    echo json_encode(['success' => true, 'client_accounts' => $client_accounts]);
}

function getClientUsers($conn, $user_id) {
    $is_admin = isAdmin();
    $is_manager = isManager();
    
    if (!$is_admin && !$is_manager) {
        throw new Exception('Access denied');
    }
    
    $client_account_id = isset($_GET['client_account_id']) ? intval($_GET['client_account_id']) : 0;
    
    if ($client_account_id <= 0) {
        throw new Exception('Invalid client account ID');
    }
    
    // Verify the client account exists and manager has access
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
    
    // Get client users under this account (user_type = 'client', manager_id = client_account_id, password is NOT NULL and NOT empty)
    $sql = "SELECT id, name, username FROM users 
            WHERE user_type = 'client' 
            AND manager_id = ? 
            AND password IS NOT NULL 
            AND password != ''
            ORDER BY name";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $client_account_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $client_users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $client_users[] = [
            'id' => $row['id'],
            'name' => $row['name'] ?: $row['username']
        ];
    }
    
    mysqli_stmt_close($stmt);
    
    echo json_encode(['success' => true, 'client_users' => $client_users]);
}

function playVoiceRecording($conn, $user_id) {
    $update_id = intval($_GET['id'] ?? 0);
    
    if ($update_id <= 0) {
        throw new Exception('Invalid update ID');
    }
    
    // Get current user info for visibility check
    $user_sql = "SELECT id, user_type, manager_id FROM users WHERE id = ?";
    $user_stmt = mysqli_prepare($conn, $user_sql);
    mysqli_stmt_bind_param($user_stmt, 'i', $user_id);
    mysqli_stmt_execute($user_stmt);
    $user_result = mysqli_stmt_get_result($user_stmt);
    $current_user = mysqli_fetch_assoc($user_result);
    mysqli_stmt_close($user_stmt);
    
    $sql = "SELECT u.*, usr.user_type as created_by_user_type 
            FROM updates u 
            LEFT JOIN users usr ON u.created_by = usr.id 
            WHERE u.id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $update_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$row = mysqli_fetch_assoc($result)) {
        throw new Exception('Update not found');
    }
    
    // Check visibility permissions (same as getUpdate)
    $is_admin = isAdmin();
    $is_manager = isManager();
    $is_client = isClient();
    $update_creator_id = $row['created_by'];
    
    $has_access = false;
    
    if ($is_admin) {
        $has_access = true;
    } elseif ($is_manager) {
        if ($update_creator_id == $user_id) {
            $has_access = true;
        } else {
            $check_client_sql = "SELECT id FROM users WHERE id = ? AND user_type = 'client' AND manager_id = ?";
            $check_client_stmt = mysqli_prepare($conn, $check_client_sql);
            mysqli_stmt_bind_param($check_client_stmt, 'ii', $update_creator_id, $user_id);
            mysqli_stmt_execute($check_client_stmt);
            $check_client_result = mysqli_stmt_get_result($check_client_stmt);
            if (mysqli_num_rows($check_client_result) > 0) {
                $has_access = true;
            }
            mysqli_stmt_close($check_client_stmt);
        }
    } elseif ($is_client) {
        if ($update_creator_id == $user_id) {
            $has_access = true;
        } else {
            $manager_id = $current_user['manager_id'] ?? null;
            if ($manager_id && $update_creator_id == $manager_id) {
                $has_access = true;
            }
        }
    }
    
    if (!$has_access) {
        throw new Exception('You do not have permission to access this voice recording');
    }
    
    if (!$row['voice_recording_path'] || empty(trim($row['voice_recording_path']))) {
        throw new Exception('No voice recording found');
    }
    
    $file_path = '../' . $row['voice_recording_path'];
    
    if (!file_exists($file_path)) {
        throw new Exception('File not found');
    }
    
    header('Content-Type: audio/webm');
    header('Content-Length: ' . filesize($file_path));
    header('Accept-Ranges: bytes');
    
    readfile($file_path);
    exit;
}

