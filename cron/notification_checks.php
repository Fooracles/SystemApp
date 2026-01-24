<?php
/**
 * Notification Checks Cron Job
 * Run this file periodically (every 1-5 minutes) to check for:
 * - Task delay warnings (5 minutes before delay)
 * - Day special events (birthdays, work anniversaries)
 * - Notes reminders
 * 
 * Setup cron job (runs every minute):
 * Add to crontab: * * * * * php /path/to/cron/notification_checks.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notification_triggers.php';

// Set execution time limit for long-running script
set_time_limit(300); // 5 minutes

// Log start
error_log("[" . date('Y-m-d H:i:s') . "] Notification checks cron job started");

try {
    // 1. Check task delay warnings (5 minutes before delay)
    checkTaskDelayWarnings($conn);
    error_log("[" . date('Y-m-d H:i:s') . "] Task delay warnings checked");
    
    // 2. Check day special events (birthdays, work anniversaries)
    // Only check once per day (at midnight or first run of the day)
    $last_check_file = __DIR__ . '/.last_day_special_check';
    $today = date('Y-m-d');
    $last_check_date = file_exists($last_check_file) ? trim(file_get_contents($last_check_file)) : '';
    
    if ($last_check_date !== $today) {
        triggerDaySpecialNotifications($conn);
        file_put_contents($last_check_file, $today);
        error_log("[" . date('Y-m-d H:i:s') . "] Day special notifications checked");
    } else {
        error_log("[" . date('Y-m-d H:i:s') . "] Day special notifications already checked today");
    }
    
    // 3. Check notes reminders
    checkNotesReminders($conn);
    error_log("[" . date('Y-m-d H:i:s') . "] Notes reminders checked");
    
    // 4. Check overdue checklist tasks (once per day)
    $last_checklist_check_file = __DIR__ . '/.last_checklist_overdue_check';
    $today = date('Y-m-d');
    $last_checklist_check_date = file_exists($last_checklist_check_file) ? trim(file_get_contents($last_checklist_check_file)) : '';
    
    if ($last_checklist_check_date !== $today) {
        checkOverdueChecklistTasks($conn);
        file_put_contents($last_checklist_check_file, $today);
        error_log("[" . date('Y-m-d H:i:s') . "] Overdue checklist tasks checked");
    } else {
        error_log("[" . date('Y-m-d H:i:s') . "] Overdue checklist tasks already checked today");
    }
    
    error_log("[" . date('Y-m-d H:i:s') . "] Notification checks cron job completed successfully");
    
} catch (Exception $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] Notification checks cron job error: " . $e->getMessage());
}

/**
 * Check and create notifications for notes reminders
 */
function checkNotesReminders($conn) {
    // Get notes with reminders that are due (at the scheduled time or within the last 5 minutes)
    $now = date('Y-m-d H:i:s');
    $five_minutes_ago = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    
    // Check for reminders that are:
    // 1. Due now or within the last 5 minutes (to catch reminders that just passed)
    // This ensures reminders trigger at the scheduled time
    $query = "SELECT id, user_id, title, reminder_date 
              FROM user_notes 
              WHERE reminder_date IS NOT NULL 
              AND reminder_date <= ?
              AND reminder_date >= ?
              AND (reminder_sent = 0 OR reminder_sent IS NULL)
              AND is_completed = 0";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        error_log("Failed to prepare notes reminder query: " . mysqli_error($conn));
        return;
    }
    
    mysqli_stmt_bind_param($stmt, 'ss', $now, $five_minutes_ago);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $notifications_created = 0;
    while ($note = mysqli_fetch_assoc($result)) {
        // Check if notification already exists for this reminder today
        // createNotification will also check for duplicates, but we check here too
        $notif_check = "SELECT id FROM notifications 
                       WHERE type = 'notes_reminder' 
                       AND related_id = ? 
                       AND user_id = ? 
                       AND DATE(created_at) = CURDATE()";
        $notif_stmt = mysqli_prepare($conn, $notif_check);
        if ($notif_stmt) {
            mysqli_stmt_bind_param($notif_stmt, 'ii', $note['id'], $note['user_id']);
            mysqli_stmt_execute($notif_stmt);
            $notif_result = mysqli_stmt_get_result($notif_stmt);
            
            if (mysqli_num_rows($notif_result) == 0) {
                // Create notification (createNotification will also check for duplicates)
                $notification_id = triggerNotesReminderNotification($conn, $note['id'], $note['user_id'], $note['title']);
                
                if ($notification_id) {
                    $notifications_created++;
                    
                    // Mark reminder as sent
                    $update_query = "UPDATE user_notes SET reminder_sent = 1 WHERE id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_query);
                    if ($update_stmt) {
                        mysqli_stmt_bind_param($update_stmt, 'i', $note['id']);
                        mysqli_stmt_execute($update_stmt);
                        mysqli_stmt_close($update_stmt);
                    }
                }
            }
            mysqli_stmt_close($notif_stmt);
        }
    }
    
    mysqli_stmt_close($stmt);
    
    if ($notifications_created > 0) {
        error_log("Created {$notifications_created} notes reminder notifications");
    }
}

