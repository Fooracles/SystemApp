<?php
/**
 * Lightweight request diagnostics for tracking intermittent HTTP 500 issues.
 * Enabled only when `debug_500=1` (query/post) or header `X-Debug-500: 1`.
 */

if (!function_exists('isDebug500Enabled')) {
    function isDebug500Enabled(): bool {
        $queryFlag = isset($_GET['debug_500']) && (string)$_GET['debug_500'] === '1';
        $postFlag = isset($_POST['debug_500']) && (string)$_POST['debug_500'] === '1';
        $headerFlag = isset($_SERVER['HTTP_X_DEBUG_500']) && (string)$_SERVER['HTTP_X_DEBUG_500'] === '1';
        return $queryFlag || $postFlag || $headerFlag;
    }
}

if (!function_exists('debug500LogPath')) {
    function debug500LogPath(): string {
        return __DIR__ . '/../logs/http_500_trace.log';
    }
}

if (!function_exists('debug500Write')) {
    function debug500Write(string $tag, string $message, array $meta = []): void {
        $logPath = debug500LogPath();
        $dir = dirname($logPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $line = [
            'time' => date('Y-m-d H:i:s'),
            'tag' => $tag,
            'message' => $message,
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'meta' => $meta,
        ];

        @file_put_contents($logPath, json_encode($line, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('debug500Bootstrap')) {
    function debug500Bootstrap(string $tag): bool {
        if (!isDebug500Enabled()) {
            return false;
        }

        $requestBody = file_get_contents('php://input');
        if (!is_string($requestBody)) {
            $requestBody = '';
        }
        if (strlen($requestBody) > 4000) {
            $requestBody = substr($requestBody, 0, 4000) . '...<truncated>';
        }

        debug500Write($tag, 'request_start', [
            'query' => $_GET ?? [],
            'post' => $_POST ?? [],
            'session_user' => $_SESSION['id'] ?? null,
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
            'body' => $requestBody,
        ]);

        register_shutdown_function(function () use ($tag) {
            $err = error_get_last();
            if (!$err) {
                debug500Write($tag, 'request_end_ok');
                return;
            }
            $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
            if (in_array($err['type'], $fatal, true)) {
                debug500Write($tag, 'fatal_error', [
                    'type' => $err['type'],
                    'message' => $err['message'] ?? '',
                    'file' => $err['file'] ?? '',
                    'line' => $err['line'] ?? 0,
                ]);
            } else {
                debug500Write($tag, 'request_end_with_last_error', [
                    'type' => $err['type'],
                    'message' => $err['message'] ?? '',
                    'file' => $err['file'] ?? '',
                    'line' => $err['line'] ?? 0,
                ]);
            }
        });

        return true;
    }
}

