<?php
/**
 * Cleanup Duplicate Notifications Script
 * Removes duplicate notifications from the database
 * Run this script once to clean up any existing duplicates
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

echo "Starting duplicate notification cleanup...\n";

// Get all notifications ordered by user, type, related_id, and date
$query = "SELECT id, user_id, type, related_id, related_type, created_at, is_read
          FROM notifications
          ORDER BY user_id, type, related_id, DATE(created_at), created_at DESC, is_read ASC";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Error: " . mysqli_error($conn) . "\n");
}

$duplicates_to_delete = [];
$kept_notifications = [];

while ($row = mysqli_fetch_assoc($result)) {
    // Create unique key for duplicate detection
    $date_key = date('Y-m-d', strtotime($row['created_at']));
    $key = $row['user_id'] . '|' . $row['type'] . '|' . ($row['related_id'] ?? '') . '|' . $date_key;
    
    // Special handling for leave_request - keep unread ones
    if ($row['type'] === 'leave_request' && $row['is_read'] == 0) {
        $key .= '|unread';
    }
    
    // If we've already seen this key, mark as duplicate
    if (isset($kept_notifications[$key])) {
        $duplicates_to_delete[] = $row['id'];
    } else {
        // Keep the first one (which is the most recent or unread)
        $kept_notifications[$key] = $row['id'];
    }
}

// Delete duplicates
if (!empty($duplicates_to_delete)) {
    $ids = implode(',', array_map('intval', $duplicates_to_delete));
    $delete_query = "DELETE FROM notifications WHERE id IN ($ids)";
    
    if (mysqli_query($conn, $delete_query)) {
        $deleted_count = mysqli_affected_rows($conn);
        echo "✓ Deleted {$deleted_count} duplicate notifications\n";
    } else {
        echo "✗ Error deleting duplicates: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "✓ No duplicates found\n";
}

echo "Cleanup completed!\n";

