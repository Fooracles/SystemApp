<?php
/**
 * Centralized Error Handler
 * 
 * SECURITY FEATURES:
 * - Environment-aware error display (dev vs production)
 * - Safe error responses that don't leak sensitive information
 * - Server-side logging for debugging
 * - Global exception and error handlers
 * 
 * Include this file early in config.php (before any output)
 */

// Prevent multiple inclusions
if (defined('ERROR_HANDLER_LOADED')) {
    return;
}
define('ERROR_HANDLER_LOADED', true);

// Determine environment - check multiple indicators
$is_production_env = !(
    (isset($_SERVER['HTTP_HOST']) && (
        $_SERVER['HTTP_HOST'] === 'localhost' || 
        $_SERVER['HTTP_HOST'] === '127.0.0.1' || 
        strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0
    )) ||
    (php_sapi_name() === 'cli' && !getenv('IS_PRODUCTION'))
);

define('IS_PRODUCTION', $is_production_env);

// Configure error display based on environment
if (IS_PRODUCTION) {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);
    ini_set('log_errors', 1);
    
    // Ensure log directory exists
    $log_dir = __DIR__ . '/../logs';
    if (!file_exists($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    ini_set('error_log', $log_dir . '/php_errors.log');
} else {
    // Development - show errors for easier debugging
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    ini_set('log_errors', 1);
}

/**
 * Safe JSON error response for AJAX endpoints
 * Never exposes sensitive information in production
 * 
 * Includes both 'success' and 'status' keys for backward compatibility:
 *   { "success": false, "status": "error", "message": "..." }
 * 
 * @param string $message User-friendly error message
 * @param int $code HTTP status code (default 500)
 * @param string|null $logMessage Detailed message for server logs (optional)
 * @param string|null $errorCode Machine-readable error code (optional)
 */
function jsonError($message = 'An error occurred', $code = 500, $logMessage = null, $errorCode = null) {
    // Log detailed error server-side
    if ($logMessage) {
        error_log("[jsonError] " . $logMessage);
    }
    
    // Set HTTP status code
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json');
    }
    
    // Build response - hide details in production
    $response = [
        'success' => false,
        'status' => 'error',
        'message' => IS_PRODUCTION ? 'An error occurred' : $message
    ];
    
    // Add error code if provided (useful for frontend handling)
    if ($errorCode) {
        $response['code'] = $errorCode;
    }
    
    echo json_encode($response);
    
    // Release DB connection immediately before exiting
    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        @mysqli_close($conn);
        $conn = null;
    }
    exit;
}

/**
 * Safe JSON success response
 * 
 * $data is merged at the top level for backward compatibility:
 *   jsonSuccess(['count' => 5], 'Done')
 *   => { "success": true, "status": "success", "message": "Done", "count": 5 }
 * 
 * @param array $data Extra response data (merged at top level)
 * @param string $message Success message
 */
function jsonSuccess($data = [], $message = 'Success') {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    $response = [
        'success' => true,
        'status' => 'success',
        'message' => $message
    ];
    
    // Merge extra data at the top level for backward compatibility
    if (is_array($data) && !empty($data)) {
        $response = array_merge($response, $data);
    }
    
    echo json_encode($response);
    
    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        @mysqli_close($conn);
        $conn = null;
    }
    exit;
}

/**
 * Safe database error handler
 * Logs details server-side, returns generic message to client
 * 
 * @param mysqli $conn Database connection
 * @param string $context Description of what operation failed
 */
function handleDbError($conn, $context = 'Database operation') {
    $error = mysqli_error($conn);
    $errno = mysqli_errno($conn);
    
    // Log detailed error server-side
    error_log("[DB Error] {$context}: [{$errno}] {$error}");
    
    // Return generic error to client
    jsonError('Database operation failed', 500, null, 'DB_ERROR');
}

/**
 * Safe exception handler
 * Logs full exception details server-side, returns generic message to client
 * 
 * @param Exception $e The exception
 * @param string $context Description of where the exception occurred
 */
function handleException($e, $context = 'Operation') {
    // Log full details server-side
    error_log("[Exception] {$context}: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("[Exception] Stack trace: " . $e->getTraceAsString());
    
    // Return generic error to client
    jsonError('An unexpected error occurred', 500, null, 'EXCEPTION');
}

/**
 * Safe database connection error handler
 * For use in config.php when connection fails
 * 
 * @param string $error The connection error message
 */
function handleConnectionError($error) {
    error_log("[DB Connection] Failed: " . $error);
    
    if (IS_PRODUCTION) {
        die("Service temporarily unavailable. Please try again later.");
    } else {
        die("Database connection failed. Check error logs for details. Error: " . htmlspecialchars($error));
    }
}

/**
 * Global exception handler
 * Catches any uncaught exceptions
 */
set_exception_handler(function($e) {
    error_log("[Uncaught Exception] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("[Uncaught Exception] Stack trace: " . $e->getTraceAsString());
    
    // Check if this is an AJAX request
    $isAjax = (
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) || (
        isset($_SERVER['HTTP_ACCEPT']) && 
        strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
    );
    
    if (!headers_sent()) {
        http_response_code(500);
    }
    
    if ($isAjax) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => IS_PRODUCTION ? 'An error occurred' : $e->getMessage()
        ]);
    } else {
        if (IS_PRODUCTION) {
            echo '<h1>An error occurred</h1><p>Please try again later.</p>';
        } else {
            echo '<h1>Error</h1><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
        }
    }
    exit(1);
});

/**
 * Global error handler
 * Converts PHP errors to exceptions or logs them
 */
set_error_handler(function($severity, $message, $file, $line) {
    // Don't handle errors that are suppressed with @
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    // Log the error
    $severityName = [
        E_ERROR => 'Error',
        E_WARNING => 'Warning',
        E_NOTICE => 'Notice',
        E_STRICT => 'Strict',
        E_DEPRECATED => 'Deprecated',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
    ][$severity] ?? 'Unknown';
    
    error_log("[PHP {$severityName}] {$message} in {$file}:{$line}");
    
    // In production, suppress display but let PHP handle it
    if (IS_PRODUCTION) {
        return true; // Suppress the error display
    }
    
    return false; // Let PHP handle it normally in dev
});

/**
 * Shutdown handler for fatal errors
 * Catches fatal errors that can't be caught by set_error_handler
 */
register_shutdown_function(function() {
    $error = error_get_last();
    
    // Only handle fatal errors
    $fatalErrors = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    
    if ($error && in_array($error['type'], $fatalErrors)) {
        error_log("[Fatal Error] {$error['message']} in {$error['file']}:{$error['line']}");
        
        if (IS_PRODUCTION && !headers_sent()) {
            http_response_code(500);
            
            // Check if this looks like an AJAX request
            $isAjax = (
                isset($_SERVER['HTTP_ACCEPT']) && 
                strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
            );
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'status' => 'error', 'message' => 'An error occurred']);
            } else {
                echo '<h1>An error occurred</h1><p>Please try again later.</p>';
            }
        }
    }
});

/**
 * Helper function to safely log messages
 * 
 * @param string $message The message to log
 * @param string $level Log level (info, warning, error)
 */
function safeLog($message, $level = 'info') {
    $prefix = strtoupper($level);
    error_log("[{$prefix}] {$message}");
}
?>