<?php
// Session is automatically started by includes/functions.php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if user is Manager or Client
if (!isAdmin() && !isManager() && !isClient()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

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
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getReports($conn, $user_id) {
    $sql = "SELECT r.*, u.name as uploaded_by_name 
            FROM reports r 
            LEFT JOIN users u ON r.uploaded_by = u.id 
            ORDER BY r.uploaded_at DESC";
    
    $result = mysqli_query($conn, $sql);
    
    if (!$result) {
        throw new Exception('Database error: ' . mysqli_error($conn));
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
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $report_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => true, 'report' => $row]);
    } else {
        throw new Exception('Report not found');
    }
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
    
    // For admins/managers with multiple users, create one report per user
    if (($is_admin || $is_manager) && !empty($client_user_ids)) {
        $created_count = 0;
        $first_report_id = null;
        
        foreach ($client_user_ids as $client_user_id) {
            // Copy file for each user (or use same file path - we'll use same path)
            if ($created_count === 0) {
                // First user - move the uploaded file
                if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                    throw new Exception('Failed to save file');
                }
            } else {
                // For subsequent users, copy the file
                $new_file_name = uniqid() . '_' . time() . '_' . $created_count . '.' . $file_ext;
                $new_file_path = $upload_dir . $new_file_name;
                if (!copy($file_path, $new_file_path)) {
                    // If copy fails, continue with same file path
                    $new_file_name = $file_name;
                } else {
                    $file_name = $new_file_name;
                    $file_path = $new_file_path;
                }
            }
            
            $relative_path = 'uploads/reports/' . $file_name;
            
            if ($has_assigned_to && $has_client_account_id) {
                $sql = "INSERT INTO reports (title, project_name, file_path, file_name, file_type, file_size, uploaded_by, assigned_to, client_account_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                if (!$stmt) {
                    if ($created_count === 0) @unlink($file_path);
                    throw new Exception('Database error: ' . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($stmt, 'sssssiiii', 
                    $title, 
                    $project_name, 
                    $relative_path,
                    $file['name'],
                    $file_type,
                    $file_size,
                    $user_id,
                    $client_user_id,
                    $client_account_id
                );
            } else {
                // Fallback: try to add columns if they don't exist
                if (!$has_assigned_to) {
                    @mysqli_query($conn, "ALTER TABLE reports ADD COLUMN assigned_to INT NULL COMMENT 'Client user ID this report is assigned to'");
                }
                if (!$has_client_account_id) {
                    @mysqli_query($conn, "ALTER TABLE reports ADD COLUMN client_account_id INT NULL COMMENT 'Client account ID this report belongs to'");
                }
                
                // Try again with the new columns
                $sql = "INSERT INTO reports (title, project_name, file_path, file_name, file_type, file_size, uploaded_by, assigned_to, client_account_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                if (!$stmt) {
                    // If still fails, insert without these columns
                    $sql = "INSERT INTO reports (title, project_name, file_path, file_name, file_type, file_size, uploaded_by) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $sql);
                    if (!$stmt) {
                        if ($created_count === 0) @unlink($file_path);
                        throw new Exception('Database error: ' . mysqli_error($conn));
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
                } else {
                    mysqli_stmt_bind_param($stmt, 'sssssiiii', 
                        $title, 
                        $project_name, 
                        $relative_path,
                        $file['name'],
                        $file_type,
                        $file_size,
                        $user_id,
                        $client_user_id,
                        $client_account_id
                    );
                }
            }
            
            if (!mysqli_stmt_execute($stmt)) {
                if ($created_count === 0) @unlink($file_path);
                throw new Exception('Failed to save report: ' . mysqli_error($conn));
            }
            
            if ($first_report_id === null) {
                $first_report_id = mysqli_insert_id($conn);
            }
            
            mysqli_stmt_close($stmt);
            $created_count++;
        }
        
        echo json_encode([
            'success' => true, 
            'message' => $created_count . ' report(s) uploaded successfully',
            'created_count' => $created_count
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
                throw new Exception('Database error: ' . mysqli_error($conn));
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
                throw new Exception('Database error: ' . mysqli_error($conn));
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
            throw new Exception('Failed to save report: ' . mysqli_error($conn));
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
        throw new Exception('Failed to update report: ' . mysqli_error($conn));
    }
    
    echo json_encode(['success' => true, 'message' => 'Report updated successfully']);
}

function viewReport($conn, $user_id) {
    $report_id = intval($_GET['id'] ?? 0);
    
    if ($report_id <= 0) {
        throw new Exception('Invalid report ID');
    }
    
    $sql = "SELECT * FROM reports WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $report_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$row = mysqli_fetch_assoc($result)) {
        throw new Exception('Report not found');
    }
    
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
    
    $sql = "SELECT * FROM reports WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $report_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$row = mysqli_fetch_assoc($result)) {
        throw new Exception('Report not found');
    }
    
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

