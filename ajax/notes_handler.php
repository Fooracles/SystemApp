<?php
// Session is automatically started by includes/functions.php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    jsonError('Unauthorized', 401);
}

// CSRF protection for POST requests
csrfProtect();

$user_id = $_SESSION['id'];
$action = $_POST['action'] ?? '';

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'create_note':
            createNote($conn, $user_id);
            break;
        case 'update_note':
            updateNote($conn, $user_id);
            break;
        case 'delete_note':
            deleteNote($conn, $user_id);
            break;
        case 'get_notes':
            getNotes($conn, $user_id);
            break;
        case 'get_note':
            getNote($conn, $user_id);
            break;
        case 'share_note':
            shareNote($conn, $user_id);
            break;
        case 'unshare_note':
            unshareNote($conn, $user_id);
            break;
        case 'add_comment':
            addComment($conn, $user_id);
            break;
        case 'get_comments':
            getComments($conn, $user_id);
            break;
        case 'toggle_important':
            toggleImportant($conn, $user_id);
            break;
        case 'toggle_completed':
            toggleCompleted($conn, $user_id);
            break;
        case 'set_reminder':
            setReminder($conn, $user_id);
            break;
        case 'check_reminders':
            checkReminders($conn, $user_id);
            break;
        case 'update_order':
            updateNoteOrder($conn, $user_id);
            break;
        case 'get_sharing_list':
            getSharingList($conn, $user_id);
            break;
        case 'remove_sharing':
            removeSharing($conn, $user_id);
            break;
        case 'get_all_users':
            getAllUsers($conn, $user_id);
            break;
        case 'get_shared_notes':
            getSharedNotes($conn, $user_id);
            break;
        case 'update_shared_note':
            updateSharedNote($conn, $user_id);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    handleException($e, 'notes_handler');
}

function createNote($conn, $user_id) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $is_important = isset($_POST['is_important']) ? 1 : 0;
    $reminder_date = $_POST['reminder_date'] ?? null;
    
    if (empty($title)) {
        throw new Exception('Title is required');
    }
    
    $sql = "INSERT INTO user_notes (user_id, title, content, is_important, reminder_date) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        error_log("[DB Error] Database error: " . mysqli_error($conn)); throw new Exception('A database error occurred');
    }
    
    $reminder_datetime = $reminder_date ? date('Y-m-d H:i:s', strtotime($reminder_date)) : null;
    mysqli_stmt_bind_param($stmt, "issis", $user_id, $title, $content, $is_important, $reminder_datetime);
    
    if (mysqli_stmt_execute($stmt)) {
        $note_id = mysqli_insert_id($conn);
        echo json_encode(['success' => true, 'note_id' => $note_id, 'message' => 'Note created successfully']);
    } else {
        throw new Exception('Failed to create note');
    }
    
    mysqli_stmt_close($stmt);
}

function updateNote($conn, $user_id) {
    $note_id = (int)($_POST['note_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $is_important = isset($_POST['is_important']) ? 1 : 0;
    $reminder_date = $_POST['reminder_date'] ?? null;
    
    if (empty($title)) {
        throw new Exception('Title is required');
    }
    
    // Check if user owns the note or has edit permission
    if (!hasNotePermission($conn, $note_id, $user_id, 'edit')) {
        throw new Exception('You do not have permission to edit this note');
    }
    
    $sql = "UPDATE user_notes SET title = ?, content = ?, is_important = ?, reminder_date = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        error_log("[DB Error] Database error: " . mysqli_error($conn)); throw new Exception('A database error occurred');
    }
    
    $reminder_datetime = $reminder_date ? date('Y-m-d H:i:s', strtotime($reminder_date)) : null;
    mysqli_stmt_bind_param($stmt, "ssisi", $title, $content, $is_important, $reminder_datetime, $note_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Note updated successfully']);
    } else {
        throw new Exception('Failed to update note');
    }
    
    mysqli_stmt_close($stmt);
}

function deleteNote($conn, $user_id) {
    $note_id = (int)($_POST['note_id'] ?? 0);
    
    // Check if user owns the note
    $sql = "SELECT user_id FROM user_notes WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $note_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $note = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$note || $note['user_id'] != $user_id) {
        throw new Exception('You do not have permission to delete this note');
    }
    
    $sql = "DELETE FROM user_notes WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $note_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Note deleted successfully']);
    } else {
        throw new Exception('Failed to delete note');
    }
    
    mysqli_stmt_close($stmt);
}

function getNotes($conn, $user_id) {
    $search = $_POST['search'] ?? '';
    $filter = $_POST['filter'] ?? 'all'; // all, important, completed, pending
    
    $sql = "SELECT n.*, u.name as owner_name,
            (SELECT COUNT(*) FROM note_sharing ns WHERE ns.note_id = n.id) as shared_count
            FROM user_notes n
            LEFT JOIN users u ON n.user_id = u.id
            WHERE (n.user_id = ? OR n.id IN (
                SELECT ns.note_id FROM note_sharing ns WHERE ns.shared_with_user_id = ?
            ))";
    
    $params = [$user_id, $user_id];
    $types = "ii";
    
    if (!empty($search)) {
        $sql .= " AND (n.title LIKE ? OR n.content LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ss";
    }
    
    if ($filter === 'important') {
        $sql .= " AND n.is_important = 1";
    } elseif ($filter === 'completed') {
        $sql .= " AND n.is_completed = 1";
    } elseif ($filter === 'pending') {
        $sql .= " AND n.is_completed = 0";
    }
    
    $sql .= " ORDER BY n.is_important DESC, n.created_at DESC";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $notes = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $notes[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true, 'notes' => $notes]);
}

function getNote($conn, $user_id) {
    $note_id = (int)($_POST['note_id'] ?? 0);
    
    if (!hasNotePermission($conn, $note_id, $user_id, 'view')) {
        throw new Exception('You do not have permission to view this note');
    }
    
    $sql = "SELECT n.*, u.name as owner_name FROM user_notes n
            LEFT JOIN users u ON n.user_id = u.id
            WHERE n.id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $note_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $note = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$note) {
        throw new Exception('Note not found');
    }
    
    echo json_encode(['success' => true, 'note' => $note]);
}

function shareNote($conn, $user_id) {
    $note_id = (int)($_POST['note_id'] ?? 0);
    $shared_with_user_id = (int)($_POST['shared_with_user_id'] ?? 0);
    $permission = $_POST['permission'] ?? 'view';
    
    // Check if user owns the note
    $sql = "SELECT user_id FROM user_notes WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $note_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $note = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$note || $note['user_id'] != $user_id) {
        throw new Exception('You can only share your own notes');
    }
    
    if ($shared_with_user_id == $user_id) {
        throw new Exception('You cannot share a note with yourself');
    }
    
    $sql = "INSERT INTO note_sharing (note_id, shared_with_user_id, permission) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE permission = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iiss", $note_id, $shared_with_user_id, $permission, $permission);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Note shared successfully']);
    } else {
        throw new Exception('Failed to share note');
    }
    
    mysqli_stmt_close($stmt);
}

function unshareNote($conn, $user_id) {
    $note_id = (int)($_POST['note_id'] ?? 0);
    $shared_with_user_id = (int)($_POST['shared_with_user_id'] ?? 0);
    
    // Check if user owns the note
    $sql = "SELECT user_id FROM user_notes WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $note_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $note = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$note || $note['user_id'] != $user_id) {
        throw new Exception('You can only unshare your own notes');
    }
    
    $sql = "DELETE FROM note_sharing WHERE note_id = ? AND shared_with_user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $note_id, $shared_with_user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Note unshared successfully']);
    } else {
        throw new Exception('Failed to unshare note');
    }
    
    mysqli_stmt_close($stmt);
}

function addComment($conn, $user_id) {
    $note_id = (int)($_POST['note_id'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    
    if (empty($comment)) {
        throw new Exception('Comment cannot be empty');
    }
    
    if (!hasNotePermission($conn, $note_id, $user_id, 'comment')) {
        throw new Exception('You do not have permission to comment on this note');
    }
    
    $sql = "INSERT INTO note_comments (note_id, user_id, comment) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iis", $note_id, $user_id, $comment);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Comment added successfully']);
    } else {
        throw new Exception('Failed to add comment');
    }
    
    mysqli_stmt_close($stmt);
}

function getComments($conn, $user_id) {
    $note_id = (int)($_POST['note_id'] ?? 0);
    
    if (!hasNotePermission($conn, $note_id, $user_id, 'view')) {
        throw new Exception('You do not have permission to view comments for this note');
    }
    
    $sql = "SELECT c.*, u.name as user_name FROM note_comments c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.note_id = ? ORDER BY c.created_at ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $note_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $comments = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $comments[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true, 'comments' => $comments]);
}

function toggleImportant($conn, $user_id) {
    $note_id = (int)($_POST['note_id'] ?? 0);
    
    if (!hasNotePermission($conn, $note_id, $user_id, 'edit')) {
        throw new Exception('You do not have permission to modify this note');
    }
    
    $sql = "UPDATE user_notes SET is_important = NOT is_important WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $note_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Note importance toggled']);
    } else {
        throw new Exception('Failed to toggle importance');
    }
    
    mysqli_stmt_close($stmt);
}

function toggleCompleted($conn, $user_id) {
    $note_id = (int)($_POST['note_id'] ?? 0);
    
    if (!hasNotePermission($conn, $note_id, $user_id, 'edit')) {
        throw new Exception('You do not have permission to modify this note');
    }
    
    $sql = "UPDATE user_notes SET is_completed = NOT is_completed WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $note_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Note completion status toggled']);
    } else {
        throw new Exception('Failed to toggle completion status');
    }
    
    mysqli_stmt_close($stmt);
}

function setReminder($conn, $user_id) {
    $note_id = (int)($_POST['note_id'] ?? 0);
    $reminder_date = $_POST['reminder_date'] ?? null;
    
    if (!hasNotePermission($conn, $note_id, $user_id, 'edit')) {
        throw new Exception('You do not have permission to modify this note');
    }
    
    $reminder_datetime = $reminder_date ? date('Y-m-d H:i:s', strtotime($reminder_date)) : null;
    
    $sql = "UPDATE user_notes SET reminder_date = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $reminder_datetime, $note_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Reminder set successfully']);
    } else {
        throw new Exception('Failed to set reminder');
    }
    
    mysqli_stmt_close($stmt);
}

function hasNotePermission($conn, $note_id, $user_id, $permission) {
    // Check if user owns the note
    $sql = "SELECT user_id FROM user_notes WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $note_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $note = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($note && $note['user_id'] == $user_id) {
        return true; // Owner has all permissions
    }
    
    // Check sharing permissions
    $sql = "SELECT permission FROM note_sharing WHERE note_id = ? AND shared_with_user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $note_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $sharing = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$sharing) {
        return false;
    }
    
    $user_permission = $sharing['permission'];
    
    if ($permission === 'view') {
        return in_array($user_permission, ['view', 'comment', 'edit']);
    } elseif ($permission === 'comment') {
        return in_array($user_permission, ['comment', 'edit']);
    } elseif ($permission === 'edit') {
        return $user_permission === 'edit';
    }
    
    return false;
}

function updateNoteOrder($conn, $user_id) {
    $note_ids = $_POST['note_ids'] ?? [];
    
    if (empty($note_ids) || !is_array($note_ids)) {
        throw new Exception('Invalid note IDs');
    }
    
    // Update sort order for each note
    foreach ($note_ids as $index => $note_id) {
        $sql = "UPDATE user_notes SET sort_order = ? WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iii", $index, $note_id, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    
    echo json_encode(['success' => true, 'message' => 'Note order updated successfully']);
}

function checkReminders($conn, $user_id) {
    // Check for upcoming reminders and create notifications
    require_once __DIR__ . '/../includes/notification_triggers.php';
    
    // Get notes with reminders that are due (at the scheduled time or within the last 5 minutes)
    $now = date('Y-m-d H:i:s');
    $five_minutes_ago = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    
    // Check for reminders that are:
    // 1. Due now or within the last 5 minutes (to catch reminders that just passed)
    // This ensures reminders trigger at the scheduled time
    $sql = "SELECT id, title, reminder_date 
            FROM user_notes 
            WHERE user_id = ? 
            AND reminder_date IS NOT NULL 
            AND reminder_date <= ?
            AND reminder_date >= ?
            AND (reminder_sent = 0 OR reminder_sent IS NULL)
            AND is_completed = 0";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log("[DB Error] Database error: " . mysqli_error($conn)); throw new Exception('A database error occurred');
    }
    
    mysqli_stmt_bind_param($stmt, "iss", $user_id, $now, $five_minutes_ago);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $reminders = [];
    $notifications_created = 0;
    
    while ($row = mysqli_fetch_assoc($result)) {
        $reminders[] = $row;
        
        // Check if notification already exists for this reminder today
        // createNotification will also check for duplicates, but we check here too
        $notif_check = "SELECT id FROM notifications 
                       WHERE type = 'notes_reminder' 
                       AND related_id = ? 
                       AND user_id = ? 
                       AND DATE(created_at) = CURDATE()";
        $notif_stmt = mysqli_prepare($conn, $notif_check);
        if ($notif_stmt) {
            mysqli_stmt_bind_param($notif_stmt, 'ii', $row['id'], $user_id);
            mysqli_stmt_execute($notif_stmt);
            $notif_result = mysqli_stmt_get_result($notif_stmt);
            
            if (mysqli_num_rows($notif_result) == 0) {
                // Create notification (createNotification will also check for duplicates)
                $notification_id = triggerNotesReminderNotification($conn, $row['id'], $user_id, $row['title']);
                
                if ($notification_id) {
                    $notifications_created++;
                    
                    // Mark reminder as sent
                    $update_query = "UPDATE user_notes SET reminder_sent = 1 WHERE id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_query);
                    if ($update_stmt) {
                        mysqli_stmt_bind_param($update_stmt, 'i', $row['id']);
                        mysqli_stmt_execute($update_stmt);
                        mysqli_stmt_close($update_stmt);
                    }
                }
            }
            mysqli_stmt_close($notif_stmt);
        }
    }
    
    mysqli_stmt_close($stmt);
    
    echo json_encode([
        'success' => true, 
        'reminders' => $reminders,
        'notifications_created' => $notifications_created
    ]);
}

function getSharingList($conn, $user_id) {
    $note_id = $_POST['note_id'] ?? null;
    
    if (!$note_id) {
        throw new Exception('Note ID is required');
    }
    
    // Check if user owns the note
    $sql = "SELECT id FROM user_notes WHERE id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $note_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        mysqli_stmt_close($stmt);
        throw new Exception('Note not found or access denied');
    }
    mysqli_stmt_close($stmt);
    
    // Get sharing list
    $sql = "SELECT ns.id, ns.permission, u.name as user_name, u.email as user_email 
            FROM note_sharing ns 
            JOIN users u ON ns.shared_with_user_id = u.id 
            WHERE ns.note_id = ? 
            ORDER BY ns.created_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $note_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $shared_users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $shared_users[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true, 'shared_users' => $shared_users]);
}

function removeSharing($conn, $user_id) {
    $sharing_id = $_POST['sharing_id'] ?? null;
    
    if (!$sharing_id) {
        throw new Exception('Sharing ID is required');
    }
    
    // Check if user owns the note
    $sql = "SELECT ns.note_id FROM note_sharing ns 
            JOIN user_notes un ON ns.note_id = un.id 
            WHERE ns.id = ? AND un.user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $sharing_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        mysqli_stmt_close($stmt);
        throw new Exception('Sharing record not found or access denied');
    }
    mysqli_stmt_close($stmt);
    
    // Remove sharing
    $sql = "DELETE FROM note_sharing WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $sharing_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    echo json_encode(['success' => true, 'message' => 'Sharing removed successfully']);
}

function getAllUsers($conn, $user_id) {
    // Get all users except the current user
    $sql = "SELECT id, name, email, user_type, department_id 
            FROM users 
            WHERE id != ? AND Status = 'Active' AND user_type IN ('admin', 'manager', 'doer') 
            ORDER BY user_type, name";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true, 'users' => $users]);
}

function getSharedNotes($conn, $user_id) {
    // Get notes shared with the current user
    $sql = "SELECT un.id, un.title, un.content, un.is_important, un.is_completed, 
                   un.reminder_date, un.created_at, un.updated_at,
                   u.name as owner_name, u.email as owner_email,
                   ns.permission, ns.created_at as shared_at
            FROM note_sharing ns
            JOIN user_notes un ON ns.note_id = un.id
            JOIN users u ON un.user_id = u.id
            WHERE ns.shared_with_user_id = ?
            ORDER BY ns.created_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $shared_notes = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $shared_notes[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true, 'shared_notes' => $shared_notes]);
}

function updateSharedNote($conn, $user_id) {
    $note_id = $_POST['note_id'] ?? null;
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $is_important = isset($_POST['is_important']) ? 1 : 0;
    $is_completed = isset($_POST['is_completed']) ? 1 : 0;
    $reminder_date = $_POST['reminder_date'] ?? null;
    
    if (!$note_id) {
        throw new Exception('Note ID is required');
    }
    
    // Check if user has edit permission on this shared note
    $sql = "SELECT ns.permission FROM note_sharing ns 
            WHERE ns.note_id = ? AND ns.shared_with_user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $note_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        mysqli_stmt_close($stmt);
        throw new Exception('Note not found or access denied');
    }
    
    $sharing = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($sharing['permission'] !== 'edit') {
        throw new Exception('You do not have permission to edit this note');
    }
    
    // Update the note
    $sql = "UPDATE user_notes SET 
            title = ?, 
            content = ?, 
            is_important = ?, 
            is_completed = ?, 
            reminder_date = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssiisi", $title, $content, $is_important, $is_completed, $reminder_date, $note_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    echo json_encode(['success' => true, 'message' => 'Note updated successfully']);
}

// Close database connection
if (isset($conn) && $conn instanceof mysqli) {
    mysqli_close($conn);
}
?>
