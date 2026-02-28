<?php
// ajax/delegation_tasks_sse.php
// Stream Server-Sent Events to notify a doer when new delegation tasks are created

// Stop script when client disconnects to free DB connections immediately
ignore_user_abort(false);
set_time_limit(120);

require_once '../includes/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

function sse_send_event(string $event, $data, ?string $id = null, ?int $retryMs = null): void {
    if ($id !== null) {
        echo 'id: ' . $id . "\n";
    }
    echo 'event: ' . $event . "\n";
    if ($retryMs !== null) {
        echo 'retry: ' . $retryMs . "\n";
    }
    $payload = is_string($data) ? $data : json_encode($data);
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

    session_write_close();

    // Close the DB connection opened by config.php -- we'll open short-lived connections per poll
    if (isset($conn) && $conn instanceof mysqli) {
        mysqli_close($conn);
        $conn = null;
    }

    $since = isset($_GET['since']) ? trim($_GET['since']) : '';
    if (empty($since) && isset($_SERVER['HTTP_LAST_EVENT_ID'])) {
        $since = trim($_SERVER['HTTP_LAST_EVENT_ID']);
    }
    if ($since === '' || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since)) {
        $since = date('Y-m-d H:i:s');
    }

    while (ob_get_level() > 0) { ob_end_flush(); }

    sse_send_event('ping', ['ok' => true], $since, 10000);

    $startTime = time();
    $maxDurationSec = 30;

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
            sse_send_event('ping', ['keepalive' => true], $since, 10000);
            break;
        }

        // Open a short-lived DB connection just for this poll cycle
        $poll_conn = @mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
        if (!$poll_conn) {
            sse_send_event('ping', ['ok' => true], $since, 10000);
            sleep(5);
            continue;
        }
        mysqli_set_charset($poll_conn, "utf8mb4");

        if ($stmt = mysqli_prepare($poll_conn, $query)) {
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
                mysqli_close($poll_conn);

                if (!empty($rows)) {
                    sse_send_event('new_tasks', ['count' => count($rows), 'items' => $rows], $newLatest, 3000);
                    $since = $newLatest;
                    break;
                } else {
                    sse_send_event('ping', ['ok' => true], $since, 10000);
                }
            } else {
                sse_send_event('error', ['message' => mysqli_stmt_error($stmt)]);
                mysqli_stmt_close($stmt);
                mysqli_close($poll_conn);
                break;
            }
        } else {
            sse_send_event('error', ['message' => mysqli_error($poll_conn)]);
            mysqli_close($poll_conn);
            break;
        }

        sleep(5);
    }
} catch (Throwable $e) {
    sse_send_event('error', ['message' => $e->getMessage()]);
}

exit;
