<?php
// Suppress all output except JSON
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once '../includes/config.php';
require_once '../includes/functions.php';

session_start();

// Clear any output buffer
ob_clean();

// Check if user is logged in
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user_id = $_SESSION['id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'User ID not found']);
    exit;
}

switch ($action) {
    case 'get_notifications':
        getNotifications($conn, $user_id);
        break;
    
    case 'mark_read':
        markNotificationRead($conn, $user_id);
        break;
    
    case 'mark_all_read':
        markAllNotificationsRead($conn, $user_id);
        break;
    
    case 'delete':
        deleteNotification($conn, $user_id);
        break;
    
    case 'get_unread_count':
        getUnreadCount($conn, $user_id);
        break;
    
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

function getNotifications($conn, $user_id) {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
    
    $query = "SELECT * FROM notifications WHERE user_id = ?";
    if ($unread_only) {
        $query .= " AND is_read = 0";
    }
    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . mysqli_error($conn)]);
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, 'iii', $user_id, $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    // Track seen notifications to filter duplicates
    $seen_notifications = [];
    $notifications = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        // Create a unique key for duplicate detection
        // For most types: user_id + type + related_id + date
        // For day_special: user_id + type + related_id + date (same person, same day)
        $duplicate_key = $row['user_id'] . '|' . $row['type'] . '|' . ($row['related_id'] ?? '') . '|' . date('Y-m-d', strtotime($row['created_at']));
        
        // For leave_request, also check if unread (don't show duplicate unread requests)
        if ($row['type'] === 'leave_request' && $row['is_read'] == 0) {
            $duplicate_key .= '|unread';
        }
        
        // Skip if we've already seen this notification
        if (isset($seen_notifications[$duplicate_key])) {
            // Keep the one with the latest created_at or the unread one
            $existing = $seen_notifications[$duplicate_key];
            if (strtotime($row['created_at']) > strtotime($existing['created_at']) || 
                ($row['is_read'] == 0 && $existing['is_read'] == 1)) {
                // Replace with newer or unread version
                $notifications = array_filter($notifications, function($n) use ($existing) {
                    return $n['id'] != $existing['id'];
                });
                $row['action_data'] = $row['action_data'] ? json_decode($row['action_data'], true) : null;
                $notifications[] = $row;
                $seen_notifications[$duplicate_key] = $row;
            }
            continue;
        }
        
        $row['action_data'] = $row['action_data'] ? json_decode($row['action_data'], true) : null;
        $notifications[] = $row;
        $seen_notifications[$duplicate_key] = $row;
    }
    
    // Re-sort by created_at DESC after filtering
    usort($notifications, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Get unread count (after deduplication)
    $unread_count = 0;
    foreach ($notifications as $notif) {
        if ($notif['is_read'] == 0) {
            $unread_count++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => array_values($notifications), // Re-index array
        'unread_count' => $unread_count,
        'total' => count($notifications)
    ]);
}

function markNotificationRead($conn, $user_id) {
    $notification_id = $_POST['notification_id'] ?? null;
    
    if (!$notification_id) {
        echo json_encode(['success' => false, 'error' => 'Notification ID required']);
        exit;
    }
    
    $query = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, 'ii', $notification_id, $user_id);
    mysqli_stmt_execute($stmt);
    
    echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
}

function markAllNotificationsRead($conn, $user_id) {
    $query = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    
    echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
}

function deleteNotification($conn, $user_id) {
    $notification_id = $_POST['notification_id'] ?? null;
    
    if (!$notification_id) {
        echo json_encode(['success' => false, 'error' => 'Notification ID required']);
        exit;
    }
    
    $query = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, 'ii', $notification_id, $user_id);
    mysqli_stmt_execute($stmt);
    
    echo json_encode(['success' => true, 'message' => 'Notification deleted']);
}

function getUnreadCount($conn, $user_id) {
    $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    echo json_encode([
        'success' => true,
        'unread_count' => (int)($row['count'] ?? 0)
    ]);
}

