<?php
$page_title = "500 Debug Probe";
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}
if (!isAdmin()) {
    header("Location: admin_dashboard.php");
    exit;
}

$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $endpoint = trim((string)($_POST['endpoint'] ?? ''));
    $method = strtoupper(trim((string)($_POST['method'] ?? 'GET')));
    $payloadRaw = trim((string)($_POST['payload'] ?? ''));
    $debugOn = isset($_POST['debug_500']) ? '1' : '0';

    if ($endpoint === '') {
        $error = 'Endpoint is required.';
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

        // Accept absolute URLs or project-relative paths like ../ajax/foo.php?action=bar
        $url = preg_match('#^https?://#i', $endpoint)
            ? $endpoint
            : $scheme . '://' . $host . '/' . ltrim(str_replace('../', '', $endpoint), '/');

        $headers = [
            'Accept: application/json',
            'X-Requested-With: XMLHttpRequest',
            'X-Debug-500: ' . $debugOn,
        ];

        $body = null;
        if ($payloadRaw !== '') {
            $body = $payloadRaw;
            $headers[] = 'Content-Type: application/json';
        }

        $start = microtime(true);
        $status = 0;
        $responseBody = '';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 25);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
            if ($body !== null && $method !== 'GET') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $responseBody = (string)curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);
            if ($curlErr !== '') {
                $error = 'cURL error: ' . $curlErr;
            }
        } else {
            $opts = [
                'http' => [
                    'method' => $method,
                    'header' => implode("\r\n", $headers),
                    'ignore_errors' => true,
                    'timeout' => 25,
                ]
            ];
            if ($body !== null && $method !== 'GET') {
                $opts['http']['content'] = $body;
            }
            $ctx = stream_context_create($opts);
            $responseBody = (string)@file_get_contents($url, false, $ctx);
            if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
                $status = (int)$m[1];
            }
        }

        $elapsedMs = (int)round((microtime(true) - $start) * 1000);
        $logPath = __DIR__ . '/../logs/http_500_probe.log';
        $entry = [
            'time' => date('Y-m-d H:i:s'),
            'url' => $url,
            'method' => $method,
            'status' => $status,
            'elapsed_ms' => $elapsedMs,
            'debug_500' => $debugOn,
            'payload' => $payloadRaw,
            'response' => mb_substr($responseBody, 0, 8000),
        ];
        @file_put_contents($logPath, json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);

        $result = $entry;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.probe-wrap { padding: 20px; color: #f4f4f5; background: #0f0f14; min-height: calc(100vh - 70px); }
.probe-card { background: #18181b; border: 1px solid #2a2a35; border-radius: 10px; padding: 14px; margin-bottom: 14px; }
.probe-input, .probe-textarea, .probe-select { width: 100%; background: #101016; border: 1px solid #2a2a35; color: #e4e4e7; border-radius: 8px; padding: 10px; }
.probe-textarea { min-height: 140px; font-family: Consolas, monospace; }
.probe-row { margin-bottom: 10px; }
.probe-btn { background: #2563eb; color: #fff; border: 0; border-radius: 8px; padding: 10px 14px; cursor: pointer; }
.ok { color: #4ade80; }
.bad { color: #f87171; }
</style>
<div class="probe-wrap">
    <h2>500 Debug Probe</h2>
    <div class="probe-card">
        <form method="post">
            <div class="probe-row">
                <label>Endpoint URL or path</label>
                <input class="probe-input" type="text" name="endpoint" value="<?php echo htmlspecialchars($_POST['endpoint'] ?? 'ajax/fms_execution_handler.php?action=submit'); ?>" />
            </div>
            <div class="probe-row">
                <label>Method</label>
                <select class="probe-select" name="method">
                    <?php $currentMethod = $_POST['method'] ?? 'POST'; ?>
                    <option value="GET" <?php echo $currentMethod === 'GET' ? 'selected' : ''; ?>>GET</option>
                    <option value="POST" <?php echo $currentMethod === 'POST' ? 'selected' : ''; ?>>POST</option>
                </select>
            </div>
            <div class="probe-row">
                <label>JSON payload (optional)</label>
                <textarea class="probe-textarea" name="payload"><?php echo htmlspecialchars($_POST['payload'] ?? '{"form_id":1,"form_data":{},"run_title":"Debug Run"}'); ?></textarea>
            </div>
            <div class="probe-row">
                <label><input type="checkbox" name="debug_500" value="1" <?php echo isset($_POST['debug_500']) ? 'checked' : ''; ?> /> Enable deep server trace (`debug_500`)</label>
            </div>
            <button class="probe-btn" type="submit">Run Probe</button>
        </form>
    </div>

    <?php if ($error): ?>
        <div class="probe-card bad"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($result): ?>
        <div class="probe-card">
            <div>Status: <strong class="<?php echo ($result['status'] >= 200 && $result['status'] < 400) ? 'ok' : 'bad'; ?>"><?php echo (int)$result['status']; ?></strong></div>
            <div>Elapsed: <?php echo (int)$result['elapsed_ms']; ?> ms</div>
            <div>Log files:
                <code>logs/http_500_probe.log</code>,
                <code>logs/http_500_trace.log</code> (when enabled)
            </div>
            <pre style="white-space: pre-wrap; background:#101016; border:1px solid #2a2a35; border-radius:8px; padding:10px; margin-top:10px;"><?php echo htmlspecialchars($result['response'] ?? ''); ?></pre>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

