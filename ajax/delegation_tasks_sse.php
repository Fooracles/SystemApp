<?php
// ajax/delegation_tasks_sse.php
// Stream Server-Sent Events to notify a doer when new delegation tasks are created

// Keep the script running
ignore_user_abort(true);
set_time_limit(0);

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Start session to read user, then close to avoid session locking
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // prevent buffering on some proxies

// Helper: send SSE event
function sse_send_event(string $event, $data, ?string $id = null, ?int $retryMs = null): void {
    if ($id !== null) {
        echo 'id: ' . $id . "\n";
    }
    echo 'event: ' . $event . "\n";
    if ($retryMs !== null) {
        echo 'retry: ' . $retryMs . "\n";
    }
    $payload = is_string($data) ? $data : json_encode($data);
    // Ensure data lines are prefixed with 'data: '
    foreach (preg_split("/\r?\n/", (string)$payload) as $line) {
        echo 'data: ' . $line . "\n";
    }
    echo "\n";
    @ob_flush();
    @flush();
}

try {
    if (!isLoggedIn()) {
        sse_send_event('error', ['message' => 'Not authenticated']);
        exit;
    }

    $current_doer_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    if ($current_doer_id <= 0) {
        sse_send_event('error', ['message' => 'Invalid user']);
        exit;
    }

    // We only need to read from session; close it so this long-running request does not block other requests
    session_write_close();

    // Initialize 'since' from query or Last-Event-ID header
    $since = isset($_GET['since']) ? trim($_GET['since']) : '';
    if (empty($since) && isset($_SERVER['HTTP_LAST_EVENT_ID'])) {
        $since = trim($_SERVER['HTTP_LAST_EVENT_ID']);
    }
    // If not provided, start from current time so we only get future tasks
    if ($since === '' || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since)) {
        $since = date('Y-m-d H:i:s');
    }

    // Make sure output buffers are flushed before starting
    while (ob_get_level() > 0) { ob_end_flush(); }

    // Send an initial ping so client knows the stream is open
    sse_send_event('ping', ['ok' => true], $since, 5000);

    $startTime = time();
    $maxDurationSec = 60; // keep the connection ~1 minute; client will reconnect automatically

    // Prepare query for new tasks
    $query = "SELECT 
                t.id as task_id,
                t.unique_id,
                t.created_at
              FROM tasks t
              WHERE t.doer_id = ?
                AND (t.status = 'pending' OR (t.status = 'shifted' AND (t.actual_date IS NULL OR t.actual_time IS NULL)))
                AND t.created_at > ?
              ORDER BY t.created_at DESC
              LIMIT 5";

    while (!connection_aborted()) {
        if ((time() - $startTime) > $maxDurationSec) {
            // Tell client to reconnect
            sse_send_event('ping', ['keepalive' => true], $since, 5000);
            break;
        }

        if ($stmt = mysqli_prepare($conn, $query)) {
            mysqli_stmt_bind_param($stmt, 'is', $current_doer_id, $since);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                $rows = [];
                $newLatest = $since;
                while ($row = mysqli_fetch_assoc($result)) {
                    $rows[] = $row;
                    if (!empty($row['created_at']) && $row['created_at'] > $newLatest) {
                        $newLatest = $row['created_at'];
                    }
                }
                mysqli_stmt_close($stmt);

                if (!empty($rows)) {
                    // Send event with new tasks; set event id as the latest timestamp for resumable stream
                    sse_send_event('new_tasks', ['count' => count($rows), 'items' => $rows], $newLatest, 3000);
                    $since = $newLatest; // advance since
                    // After notifying, we can break to let client reload; or continue to allow multiple batches.
                    // We'll break to keep logic simple; client will reconnect after reload.
                    break;
                } else {
                    // Periodic ping so proxies keep connection alive
                    sse_send_event('ping', ['ok' => true], $since, 5000);
                }
            } else {
                // On DB error, send error and stop
                sse_send_event('error', ['message' => mysqli_stmt_error($stmt)]);
                mysqli_stmt_close($stmt);
                break;
            }
        } else {
            sse_send_event('error', ['message' => mysqli_error($conn)]);
            break;
        }

        // Sleep a bit before checking again
        sleep(2);
    }
} catch (Throwable $e) {
    sse_send_event('error', ['message' => $e->getMessage()]);
}

exit;


