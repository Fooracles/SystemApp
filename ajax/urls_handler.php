<?php
// urls_handler.php
// AJAX handler for useful URLs functionality

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once "../includes/config.php";
require_once "../includes/functions.php";

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get the action from POST data
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_all_urls':
            get_all_urls();
            break;
        case 'get_personal_urls':
            get_personal_urls();
            break;
        case 'get_admin_urls':
            get_admin_urls();
            break;
        case 'get_personal_url':
            get_personal_url();
            break;
        case 'get_admin_url':
            get_admin_url();
            break;
        case 'create_personal_url':
            create_personal_url();
            break;
        case 'create_admin_url':
            create_admin_url();
            break;
        case 'update_personal_url':
            update_personal_url();
            break;
        case 'update_admin_url':
            update_admin_url();
            break;
        case 'delete_personal_url':
            delete_personal_url();
            break;
        case 'delete_admin_url':
            delete_admin_url();
            break;
        case 'update_order':
            update_order();
            break;
        case 'share_url':
            share_url();
            break;
        case 'get_sharing_list':
            get_sharing_list();
            break;
        case 'remove_sharing':
            remove_sharing();
            break;
        case 'get_all_users':
            get_all_users();
            break;
        case 'get_shared_urls':
            get_shared_urls();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function get_all_urls() {
    global $conn;
    
    $search = $_POST['search'] ?? '';
    $category = $_POST['category'] ?? '';
    
    // Get personal URLs
    $personal_urls = get_urls_by_type('personal', $search, $category);
    
    // Get admin URLs (visible to all users)
    $admin_urls = get_urls_by_type('admin', $search, $category);
    
    echo json_encode([
        'success' => true,
        'personal_urls' => $personal_urls,
        'admin_urls' => $admin_urls
    ]);
}

function get_personal_urls() {
    global $conn;
    
    $search = $_POST['search'] ?? '';
    $category = $_POST['category'] ?? '';
    
    $urls = get_urls_by_type('personal', $search, $category);
    
    echo json_encode([
        'success' => true,
        'urls' => $urls
    ]);
}

function get_admin_urls() {
    global $conn;
    
    // All users can view admin URLs (visibility is handled by get_urls_by_type)
    // Only admins can create/edit/delete admin URLs
    
    $search = $_POST['search'] ?? '';
    $category = $_POST['category'] ?? '';
    
    $urls = get_urls_by_type('admin', $search, $category);
    
    echo json_encode([
        'success' => true,
        'urls' => $urls
    ]);
}

function get_personal_url() {
    global $conn;
    
    $url_id = $_POST['url_id'] ?? 0;
    
    $sql = "SELECT * FROM personal_urls WHERE id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $url_id, $_SESSION['id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => true, 'url' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'URL not found']);
    }
}

function get_admin_url() {
    global $conn;
    
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    $url_id = $_POST['url_id'] ?? 0;
    
    $sql = "SELECT * FROM admin_urls WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $url_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => true, 'url' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'URL not found']);
    }
}

// Helper function to normalize URL (add http:// if no protocol)
function normalizeUrl($url) {
    $url = trim($url);
    // If URL doesn't have a protocol, add http:// prefix
    if (!preg_match('/^https?:\/\//i', $url)) {
        $url = 'http://' . $url;
    }
    return $url;
}

function create_personal_url() {
    global $conn;
    
    $title = trim($_POST['title'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    
    if (empty($title) || empty($url)) {
        echo json_encode(['success' => false, 'message' => 'Title and URL are required']);
        return;
    }
    
    // Remove https validation - allow URLs with or without protocol
    // If URL doesn't have a protocol, add http:// prefix before saving
    if (!preg_match('/^https?:\/\//i', $url)) {
        $url = 'http://' . $url;
    }
    
    // Validate the URL (now with protocol)
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid URL format']);
        return;
    }
    
    $sql = "INSERT INTO personal_urls (user_id, title, url, description, category, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "issss", $_SESSION['id'], $title, $url, $description, $category);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Personal URL created successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create URL']);
    }
}

function create_admin_url() {
    global $conn;
    
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    $title = trim($_POST['title'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $visible_for = trim($_POST['visible_for'] ?? 'all');
    
    if (empty($title) || empty($url)) {
        echo json_encode(['success' => false, 'message' => 'Title and URL are required']);
        return;
    }
    
    // Normalize URL (add http:// if no protocol)
    $url = normalizeUrl($url);
    
    // Validate the URL (now with protocol)
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid URL format']);
        return;
    }
    
    $sql = "INSERT INTO admin_urls (created_by, title, url, description, category, visible_for, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "isssss", $_SESSION['id'], $title, $url, $description, $category, $visible_for);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Admin URL created successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create URL']);
    }
}

function update_personal_url() {
    global $conn;
    
    $url_id = $_POST['url_id'] ?? 0;
    $title = trim($_POST['title'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    
    if (empty($title) || empty($url)) {
        echo json_encode(['success' => false, 'message' => 'Title and URL are required']);
        return;
    }
    
    // Normalize URL (add http:// if no protocol)
    $url = normalizeUrl($url);
    
    // Validate the URL (now with protocol)
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid URL format']);
        return;
    }
    
    $sql = "UPDATE personal_urls SET title = ?, url = ?, description = ?, category = ? WHERE id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssii", $title, $url, $description, $category, $url_id, $_SESSION['id']);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Personal URL updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update URL']);
    }
}

function update_admin_url() {
    global $conn;
    
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    $url_id = $_POST['url_id'] ?? 0;
    $title = trim($_POST['title'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $visible_for = trim($_POST['visible_for'] ?? 'all');
    
    if (empty($title) || empty($url)) {
        echo json_encode(['success' => false, 'message' => 'Title and URL are required']);
        return;
    }
    
    // Normalize URL (add http:// if no protocol)
    $url = normalizeUrl($url);
    
    // Validate the URL (now with protocol)
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid URL format']);
        return;
    }
    
    $sql = "UPDATE admin_urls SET title = ?, url = ?, description = ?, category = ?, visible_for = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssssi", $title, $url, $description, $category, $visible_for, $url_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Admin URL updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update URL']);
    }
}

function delete_personal_url() {
    global $conn;
    
    $url_id = $_POST['url_id'] ?? 0;
    
    $sql = "DELETE FROM personal_urls WHERE id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $url_id, $_SESSION['id']);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Personal URL deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete URL']);
    }
}

function delete_admin_url() {
    global $conn;
    
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    $url_id = $_POST['url_id'] ?? 0;
    
    $sql = "DELETE FROM admin_urls WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $url_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Admin URL deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete URL']);
    }
}

function update_order() {
    global $conn;
    
    $url_type = $_POST['url_type'] ?? '';
    $url_ids = $_POST['url_ids'] ?? [];
    
    if (empty($url_ids) || !is_array($url_ids)) {
        echo json_encode(['success' => false, 'message' => 'Invalid URL IDs']);
        return;
    }
    
    // Update order for each URL
    foreach ($url_ids as $index => $url_id) {
        $order = $index + 1;
        if ($url_type === 'personal') {
            $sql = "UPDATE personal_urls SET sort_order = ? WHERE id = ?";
        } else {
            $sql = "UPDATE admin_urls SET sort_order = ? WHERE id = ?";
        }
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $order, $url_id);
        mysqli_stmt_execute($stmt);
    }
    
    echo json_encode(['success' => true, 'message' => 'URL order updated successfully']);
}

function get_urls_by_type($type, $search = '', $category = '') {
    global $conn;
    
    $user_id = $_SESSION['id'];
    $user_type = $_SESSION['user_type'] ?? '';
    
    if ($type === 'personal') {
        $sql = "SELECT * FROM personal_urls WHERE user_id = ?";
        $params = [$user_id];
        $param_types = "i";
    } else {
        $sql = "SELECT * FROM admin_urls WHERE (visible_for = 'all'";
        $params = [];
        $param_types = "";
        
        // Add visibility restrictions for admin URLs based on user level
        if ($user_type === 'admin') {
            $sql .= " OR visible_for = 'admin'";
        }
        if (in_array($user_type, ['admin', 'manager'])) {
            $sql .= " OR visible_for = 'manager'";
        }
        if (in_array($user_type, ['admin', 'manager', 'doer'])) {
            $sql .= " OR visible_for = 'doer'";
        }
        $sql .= ")";
    }
    
    // Add search filter
    if (!empty($search)) {
        $sql .= " AND (title LIKE ? OR url LIKE ? OR description LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $param_types .= "sss";
    }
    
    // Add category filter
    if (!empty($category)) {
        $sql .= " AND category = ?";
        $params[] = $category;
        $param_types .= "s";
    }
    
    $sql .= " ORDER BY sort_order ASC, created_at DESC";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $urls = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $urls[] = $row;
    }
    
    return $urls;
}

function share_url() {
    global $conn;
    
    $url_id = (int)($_POST['url_id'] ?? 0);
    $url_type = $_POST['url_type'] ?? '';
    $shared_with_user_id = (int)($_POST['shared_with_user_id'] ?? 0);
    $permission = $_POST['permission'] ?? 'view';
    
    if (!$url_id || !$shared_with_user_id || !in_array($url_type, ['personal', 'admin'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        return;
    }
    
    // Check if sharing table exists, if not try to create it
    $table_name = $url_type === 'personal' ? 'personal_url_sharing' : 'admin_url_sharing';
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE '$table_name'");
    if (mysqli_num_rows($check_table) == 0) {
        // Try to create the table automatically
        if ($url_type === 'personal') {
            $create_sql = "CREATE TABLE IF NOT EXISTS personal_url_sharing (
                id INT AUTO_INCREMENT PRIMARY KEY,
                url_id INT NOT NULL,
                shared_with_user_id INT NOT NULL,
                permission ENUM('view','comment','edit') DEFAULT 'view',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (url_id) REFERENCES personal_urls(id) ON DELETE CASCADE,
                FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_share (url_id, shared_with_user_id),
                INDEX (url_id),
                INDEX (shared_with_user_id),
                INDEX (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        } else {
            $create_sql = "CREATE TABLE IF NOT EXISTS admin_url_sharing (
                id INT AUTO_INCREMENT PRIMARY KEY,
                url_id INT NOT NULL,
                shared_with_user_id INT NOT NULL,
                permission ENUM('view','comment','edit') DEFAULT 'view',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (url_id) REFERENCES admin_urls(id) ON DELETE CASCADE,
                FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_share (url_id, shared_with_user_id),
                INDEX (url_id),
                INDEX (shared_with_user_id),
                INDEX (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        }
        
        if (!mysqli_query($conn, $create_sql)) {
            echo json_encode(['success' => false, 'message' => "Failed to create sharing table '$table_name': " . mysqli_error($conn)]);
            return;
        }
    }
    
    $user_id = $_SESSION['id'];
    
    // Check if user owns the URL
    if ($url_type === 'personal') {
        $sql = "SELECT user_id FROM personal_urls WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $url_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $url = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$url || $url['user_id'] != $user_id) {
            echo json_encode(['success' => false, 'message' => 'You can only share your own URLs']);
            return;
        }
    } else {
        // For admin URLs, only admins can share
        if (!isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
    }
    
    if ($shared_with_user_id == $user_id) {
        echo json_encode(['success' => false, 'message' => 'You cannot share a URL with yourself']);
        return;
    }
    
    // Check if sharing already exists
    $table_name = $url_type === 'personal' ? 'personal_url_sharing' : 'admin_url_sharing';
    $sql = "SELECT id FROM $table_name WHERE url_id = ? AND shared_with_user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $url_id, $shared_with_user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $existing = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($existing) {
        // Update existing sharing
        $sql = "UPDATE $table_name SET permission = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
            return;
        }
        mysqli_stmt_bind_param($stmt, "si", $permission, $existing['id']);
    } else {
        // Create new sharing
        $sql = "INSERT INTO $table_name (url_id, shared_with_user_id, permission, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
            return;
        }
        mysqli_stmt_bind_param($stmt, "iis", $url_id, $shared_with_user_id, $permission);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'URL shared successfully']);
    } else {
        $error = mysqli_error($conn);
        echo json_encode(['success' => false, 'message' => 'Failed to share URL: ' . $error]);
    }
    mysqli_stmt_close($stmt);
}

function get_sharing_list() {
    global $conn;
    
    $url_id = (int)($_POST['url_id'] ?? 0);
    $url_type = $_POST['url_type'] ?? '';
    
    if (!$url_id || !in_array($url_type, ['personal', 'admin'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        return;
    }
    
    $user_id = $_SESSION['id'];
    
    // Check if user owns the URL
    if ($url_type === 'personal') {
        $sql = "SELECT user_id FROM personal_urls WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $url_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $url = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$url || $url['user_id'] != $user_id) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
    } else {
        // For admin URLs, only admins can view sharing list
        if (!isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
    }
    
    $table_name = $url_type === 'personal' ? 'personal_url_sharing' : 'admin_url_sharing';
    $sql = "SELECT s.*, u.name as user_name 
            FROM $table_name s
            JOIN users u ON s.shared_with_user_id = u.id
            WHERE s.url_id = ?
            ORDER BY s.created_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $url_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $shared_users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $shared_users[] = [
            'id' => $row['id'],
            'user_name' => $row['user_name'],
            'permission' => $row['permission']
        ];
    }
    mysqli_stmt_close($stmt);
    
    echo json_encode(['success' => true, 'shared_users' => $shared_users]);
}

function remove_sharing() {
    global $conn;
    
    $sharing_id = (int)($_POST['sharing_id'] ?? 0);
    
    if (!$sharing_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid sharing ID']);
        return;
    }
    
    $user_id = $_SESSION['id'];
    
    // Check if sharing exists and user has permission to remove it
    // Try personal_url_sharing first
    $sql = "SELECT s.*, p.user_id as url_owner_id 
            FROM personal_url_sharing s
            JOIN personal_urls p ON s.url_id = p.id
            WHERE s.id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $sharing_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $sharing = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    $table_name = 'personal_url_sharing';
    
    if (!$sharing) {
        // Try admin_url_sharing
        $sql = "SELECT s.*, a.created_by as url_owner_id 
                FROM admin_url_sharing s
                JOIN admin_urls a ON s.url_id = a.id
                WHERE s.id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $sharing_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $sharing = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        $table_name = 'admin_url_sharing';
    }
    
    if (!$sharing) {
        echo json_encode(['success' => false, 'message' => 'Sharing not found']);
        return;
    }
    
    // Check if user owns the URL or is admin
    if ($sharing['url_owner_id'] != $user_id && !isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    $sql = "DELETE FROM $table_name WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $sharing_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Sharing removed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove sharing']);
    }
    mysqli_stmt_close($stmt);
}

function get_all_users() {
    global $conn;
    
    $user_id = $_SESSION['id'];
    
    // Get all users except the current user
    $sql = "SELECT id, name, user_type FROM users WHERE id != ? ORDER BY name ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'user_type' => $row['user_type']
        ];
    }
    mysqli_stmt_close($stmt);
    
    echo json_encode(['success' => true, 'users' => $users]);
}

function get_shared_urls() {
    global $conn;
    
    $user_id = $_SESSION['id'];
    
    // Get personal URLs shared with user
    $sql = "SELECT p.*, s.permission, u.name as owner_name, s.created_at as shared_at
            FROM personal_url_sharing s
            JOIN personal_urls p ON s.url_id = p.id
            JOIN users u ON p.user_id = u.id
            WHERE s.shared_with_user_id = ?
            ORDER BY s.created_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $shared_urls = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $shared_urls[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'url' => $row['url'],
            'description' => $row['description'],
            'category' => $row['category'],
            'permission' => $row['permission'],
            'owner_name' => $row['owner_name'],
            'created_at' => $row['created_at']
        ];
    }
    mysqli_stmt_close($stmt);
    
    // Get admin URLs shared with user (if any)
    $sql = "SELECT a.*, s.permission, u.name as owner_name, s.created_at as shared_at
            FROM admin_url_sharing s
            JOIN admin_urls a ON s.url_id = a.id
            JOIN users u ON a.created_by = u.id
            WHERE s.shared_with_user_id = ?
            ORDER BY s.created_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $shared_urls[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'url' => $row['url'],
            'description' => $row['description'],
            'category' => $row['category'],
            'permission' => $row['permission'],
            'owner_name' => $row['owner_name'],
            'created_at' => $row['created_at']
        ];
    }
    mysqli_stmt_close($stmt);
    
    echo json_encode(['success' => true, 'shared_urls' => $shared_urls]);
}
?>