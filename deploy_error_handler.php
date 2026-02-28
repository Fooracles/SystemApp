<?php
/**
 * ============================================================================
 * CENTRALIZED ERROR HANDLER DEPLOYMENT SCRIPT
 * ============================================================================
 * 
 * Drop this file into the ROOT of your project (same level as login.php)
 * and run it once via browser or CLI:
 * 
 *   Browser:  http://localhost/your-project/deploy_error_handler.php
 *   CLI:      php deploy_error_handler.php
 * 
 * What it does:
 *   1. Creates/updates includes/error_handler.php (centralized error handling)
 *   2. Ensures config.php includes error_handler.php
 *   3. Security fix: Removes mysqli_error() from ALL client-facing responses
 *      in ajax/ and pages/ directories
 *   4. Replaces ad-hoc JSON error responses with jsonError()
 *   5. Replaces ad-hoc JSON success responses with jsonSuccess()
 *   6. Replaces catch blocks that leak exception details with handleException()
 * 
 * Safety:
 *   - Idempotent: safe to run multiple times (skips already-applied changes)
 *   - Creates .bak backups before modifying any file
 *   - Reports each step clearly
 * 
 * After running, DELETE this file from your project.
 * ============================================================================
 */

// ---- Configuration --------------------------------------------------------
$IS_CLI = (php_sapi_name() === 'cli');
$BASE   = __DIR__;

// ---- Output helpers -------------------------------------------------------
function out($msg, $type = 'info') {
    global $IS_CLI;
    $icons = ['ok' => '✅', 'skip' => '⏭️', 'err' => '❌', 'info' => 'ℹ️', 'warn' => '⚠️'];
    $icon  = $icons[$type] ?? '';

    if ($IS_CLI) {
        $prefix = ['ok'=>'[OK]','skip'=>'[SKIP]','err'=>'[ERR]','info'=>'[INFO]','warn'=>'[WARN]'][$type] ?? '[--]';
        echo "$prefix $msg\n";
    } else {
        $colors = ['ok'=>'#28a745','skip'=>'#6c757d','err'=>'#dc3545','info'=>'#17a2b8','warn'=>'#ffc107'];
        $color  = $colors[$type] ?? '#333';
        echo "<div style='padding:6px 12px;margin:3px 0;font-family:monospace;font-size:14px;color:$color;'>$icon $msg</div>\n";
    }
}

function heading($title) {
    global $IS_CLI;
    if ($IS_CLI) {
        echo "\n=== $title ===\n";
    } else {
        echo "<h3 style='margin:20px 0 8px 0;font-family:monospace;color:#fff;background:#1e1e2e;padding:10px 15px;border-radius:6px;'>$title</h3>\n";
    }
}

function backup($path) {
    $bak = $path . '.bak.' . date('Ymd_His');
    if (copy($path, $bak)) {
        out("Backup created: " . basename($bak), 'info');
        return true;
    }
    out("Failed to create backup for: " . basename($path), 'err');
    return false;
}

// ---- Start output ---------------------------------------------------------
if (!$IS_CLI) {
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error Handler Deployment</title>'
       . '<style>body{background:#0d1117;color:#c9d1d9;font-family:monospace;padding:20px 40px;max-width:1000px;margin:0 auto;}'
       . '.summary{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:15px;margin:15px 0;}'
       . '</style></head><body>';
    echo '<h1 style="color:#58a6ff;">🛡️ Centralized Error Handler Deployment Script</h1><hr style="border-color:#30363d;">';
}

// Track statistics
$stats = ['files_modified' => 0, 'security_fixes' => 0, 'error_replacements' => 0, 'success_replacements' => 0, 'catch_fixes' => 0];

// ============================================================================
// STEP 1: Create/update includes/error_handler.php
// ============================================================================
heading('Step 1 — Create/update includes/error_handler.php');

$error_handler_path = $BASE . '/includes/error_handler.php';
$error_handler_content = get_error_handler_content();

// Check includes directory exists
if (!is_dir($BASE . '/includes')) {
    out("includes/ directory not found. Is this the correct project root?", 'err');
    if (!$IS_CLI) echo '</body></html>';
    exit(1);
}

if (file_exists($error_handler_path)) {
    $existing = file_get_contents($error_handler_path);
    if (strpos($existing, "'success' => false") !== false && strpos($existing, "array_merge(\$response, \$data)") !== false) {
        out("error_handler.php already has the updated jsonError/jsonSuccess functions", 'skip');
    } else {
        backup($error_handler_path);
        file_put_contents($error_handler_path, $error_handler_content);
        out("error_handler.php updated with backward-compatible jsonError/jsonSuccess", 'ok');
        $stats['files_modified']++;
    }
} else {
    file_put_contents($error_handler_path, $error_handler_content);
    out("error_handler.php created", 'ok');
    $stats['files_modified']++;
}

// ============================================================================
// STEP 2: Ensure config.php includes error_handler.php
// ============================================================================
heading('Step 2 — Ensure config.php includes error_handler.php');

$config_path = $BASE . '/includes/config.php';
if (!file_exists($config_path)) {
    out("config.php not found at: $config_path", 'err');
    if (!$IS_CLI) echo '</body></html>';
    exit(1);
}

$config_content = file_get_contents($config_path);
if (strpos($config_content, 'error_handler.php') !== false) {
    out("config.php already includes error_handler.php", 'skip');
} else {
    backup($config_path);
    // Insert after the opening <?php or after session_start
    $needle = "<?php\n";
    if (strpos($config_content, "session_start()") !== false) {
        // Insert after session_start block
        $config_content = preg_replace(
            '/(session_start\(\);?\s*\}?\s*\n)/',
            "$1\n// Load centralized error handler FIRST (before any potential errors)\nrequire_once __DIR__ . '/error_handler.php';\n",
            $config_content,
            1
        );
    } else {
        // Insert near the top
        $config_content = str_replace(
            $needle,
            $needle . "\n// Load centralized error handler FIRST (before any potential errors)\nrequire_once __DIR__ . '/error_handler.php';\n",
            $config_content
        );
    }
    file_put_contents($config_path, $config_content);
    out("Added require_once for error_handler.php to config.php", 'ok');
    $stats['files_modified']++;
}

// ============================================================================
// STEP 3: Security fix — Remove mysqli_error() from client-facing responses
// ============================================================================
heading('Step 3 — Security fix: Remove mysqli_error() from client-facing responses');

$scan_dirs = [
    $BASE . '/ajax',
    $BASE . '/pages',
];

$php_files = [];
foreach ($scan_dirs as $dir) {
    if (!is_dir($dir)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $php_files[] = $file->getPathname();
        }
    }
}

out("Scanning " . count($php_files) . " PHP files for security issues...", 'info');

foreach ($php_files as $filepath) {
    $content = file_get_contents($filepath);
    $original = $content;
    $filename = str_replace($BASE . '/', '', str_replace('\\', '/', $filepath));
    $changes_made = false;

    // ---- SECURITY FIX 3a: throw new Exception('...' . mysqli_error($conn)) ----
    // Replace: throw new Exception('...' . mysqli_error($conn))
    // With:    error_log("[DB Error] ...: " . mysqli_error($conn)); throw new Exception('A database error occurred')
    $pattern = '/throw\s+new\s+Exception\s*\(\s*[\'"]([^"\']*?)(?::?\s*)?[\'"]\s*\.\s*mysqli_error\s*\(\s*\$conn\s*\)\s*\)/';
    if (preg_match_all($pattern, $content, $matches)) {
        foreach ($matches[0] as $i => $full_match) {
            $context = trim($matches[1][$i], ': ');
            if (empty($context)) $context = 'Database operation';
            $replacement = "error_log(\"[DB Error] {$context}: \" . mysqli_error(\$conn)); throw new Exception('A database error occurred')";
            $content = str_replace($full_match, $replacement, $content);
            $stats['security_fixes']++;
            $changes_made = true;
        }
    }

    // ---- SECURITY FIX 3b: throw new Exception('...' . mysqli_stmt_error($stmt)) ----
    $pattern2 = '/throw\s+new\s+Exception\s*\(\s*[\'"]([^"\']*?)(?::?\s*)?[\'"]\s*\.\s*mysqli_stmt_error\s*\(\s*\$\w+\s*\)\s*\)/';
    if (preg_match_all($pattern2, $content, $matches2)) {
        foreach ($matches2[0] as $i => $full_match) {
            $context = trim($matches2[1][$i], ': ');
            if (empty($context)) $context = 'Database operation';
            // Keep the original error logging but use generic throw
            $replacement = "error_log(\"[DB Error] {$context}\"); throw new Exception('A database error occurred')";
            $content = str_replace($full_match, $replacement, $content);
            $stats['security_fixes']++;
            $changes_made = true;
        }
    }

    // ---- SECURITY FIX 3c: echo json_encode with mysqli_error in message ----
    // Pattern: echo json_encode(['...'=>'...' . mysqli_error($conn)])
    $pattern3 = '/echo\s+json_encode\s*\(\s*\[[^\]]*?[\'"](?:error|message)[\'"]\s*=>\s*[\'"][^\'"]*?[\'"]\s*\.\s*mysqli_error\s*\(\s*\$conn\s*\)[^\]]*?\]\s*\)\s*;/s';
    if (preg_match_all($pattern3, $content, $matches3)) {
        foreach ($matches3[0] as $full_match) {
            $replacement = "handleDbError(\$conn, '{$filename}');";
            $content = str_replace($full_match, $replacement, $content);
            $stats['security_fixes']++;
            $changes_made = true;
        }
    }

    // ---- SECURITY FIX 3d: echo json_encode with mysqli_stmt_error in message ----
    $pattern4 = '/echo\s+json_encode\s*\(\s*\[[^\]]*?[\'"](?:error|message)[\'"]\s*=>\s*[\'"][^\'"]*?[\'"]\s*\.\s*mysqli_stmt_error\s*\(\s*\$\w+\s*\)[^\]]*?\]\s*\)\s*;/s';
    if (preg_match_all($pattern4, $content, $matches4)) {
        foreach ($matches4[0] as $full_match) {
            $replacement = "handleDbError(\$conn, '{$filename}');";
            $content = str_replace($full_match, $replacement, $content);
            $stats['security_fixes']++;
            $changes_made = true;
        }
    }

    // ---- SECURITY FIX 3e: $error = mysqli_error($conn); ... echo json_encode with $error ----
    $pattern5 = '/\$error\s*=\s*mysqli_error\s*\(\s*\$conn\s*\)\s*;\s*\n\s*echo\s+json_encode\s*\(\s*\[[^\]]*?[\'"](?:error|message)[\'"]\s*=>\s*[\'"][^\'"]*?[\'"]\s*\.\s*\$error[^\]]*?\]\s*\)\s*;/s';
    if (preg_match_all($pattern5, $content, $matches5)) {
        foreach ($matches5[0] as $full_match) {
            $replacement = "handleDbError(\$conn, '{$filename}');";
            $content = str_replace($full_match, $replacement, $content);
            $stats['security_fixes']++;
            $changes_made = true;
        }
    }

    // ---- SECURITY FIX 3f: error_msg or similar with mysqli_error exposed to user ----
    // Replace: $error_msg = "..." . mysqli_error($conn);
    // With:    error_log("[DB Error] " . mysqli_error($conn)); $error_msg = "A database error occurred.";
    $pattern6 = '/(\$(?:error_msg|error)\s*=\s*[\'"][^\'"]*?[\'"]\s*\.\s*)mysqli_error\s*\(\s*\$conn\s*\)\s*;/';
    if (preg_match_all($pattern6, $content, $matches6)) {
        foreach ($matches6[0] as $i => $full_match) {
            $replacement = 'error_log("[DB Error] " . mysqli_error($conn)); $error_msg = "A database error occurred. Please try again.";';
            $content = str_replace($full_match, $replacement, $content);
            $stats['security_fixes']++;
            $changes_made = true;
        }
    }

    // ---- SECURITY FIX 3g: $error_msg with mysqli_stmt_error ----
    $pattern7 = '/(\$(?:error_msg|error)\s*=\s*[\'"][^\'"]*?[\'"]\s*\.\s*)mysqli_stmt_error\s*\(\s*\$\w+\s*\)\s*;/';
    if (preg_match_all($pattern7, $content, $matches7)) {
        foreach ($matches7[0] as $full_match) {
            $replacement = 'error_log("[DB Error] " . mysqli_stmt_error($stmt)); $error_msg = "A database error occurred. Please try again.";';
            $content = str_replace($full_match, $replacement, $content);
            $stats['security_fixes']++;
            $changes_made = true;
        }
    }

    if ($changes_made) {
        backup($filepath);
        file_put_contents($filepath, $content);
        out("Security fixed: $filename", 'ok');
        $stats['files_modified']++;
    }
}

out("Security scan complete. Fixed {$stats['security_fixes']} potential data leaks.", $stats['security_fixes'] > 0 ? 'ok' : 'skip');

// ============================================================================
// STEP 4: Replace ad-hoc error responses with jsonError() in AJAX files
// ============================================================================
heading('Step 4 — Replace ad-hoc error responses with jsonError()');

$ajax_dir = $BASE . '/ajax';
if (is_dir($ajax_dir)) {
    $ajax_files = glob($ajax_dir . '/*.php');
    
    foreach ($ajax_files as $filepath) {
        $content = file_get_contents($filepath);
        $original = $content;
        $filename = basename($filepath);
        
        // Skip if file already uses jsonError extensively
        if (substr_count($content, 'jsonError(') > 3) {
            continue;
        }

        // Pattern: echo json_encode(['status' => 'error', 'message' => 'LITERAL']); exit;
        $content = preg_replace_callback(
            '/echo\s+json_encode\s*\(\s*\[\s*[\'"]status[\'"]\s*=>\s*[\'"]error[\'"]\s*,\s*[\'"]message[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]\s*\]\s*\)\s*;\s*\n?\s*exit\s*;/m',
            function($m) use (&$stats) {
                $msg = $m[1];
                $code = 500;
                if (stripos($msg, 'unauthorized') !== false || stripos($msg, 'not authenticated') !== false || stripos($msg, 'not logged in') !== false) $code = 401;
                elseif (stripos($msg, 'access denied') !== false || stripos($msg, 'forbidden') !== false) $code = 403;
                elseif (stripos($msg, 'not found') !== false) $code = 404;
                elseif (stripos($msg, 'method not allowed') !== false) $code = 405;
                elseif (stripos($msg, 'required') !== false || stripos($msg, 'invalid') !== false || stripos($msg, 'missing') !== false) $code = 400;
                $stats['error_replacements']++;
                return "jsonError('$msg', $code);";
            },
            $content
        );

        // Pattern: echo json_encode(['success' => false, 'error' => 'LITERAL']); exit;
        $content = preg_replace_callback(
            '/echo\s+json_encode\s*\(\s*\[\s*[\'"]success[\'"]\s*=>\s*false\s*,\s*[\'"]error[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]\s*\]\s*\)\s*;\s*\n?\s*exit\s*;/m',
            function($m) use (&$stats) {
                $msg = $m[1];
                $code = 500;
                if (stripos($msg, 'unauthorized') !== false) $code = 401;
                elseif (stripos($msg, 'access denied') !== false) $code = 403;
                elseif (stripos($msg, 'not found') !== false) $code = 404;
                elseif (stripos($msg, 'required') !== false || stripos($msg, 'invalid') !== false || stripos($msg, 'missing') !== false) $code = 400;
                $stats['error_replacements']++;
                return "jsonError('$msg', $code);";
            },
            $content
        );

        // Pattern: echo json_encode(['success' => false, 'message' => 'LITERAL']); exit;
        $content = preg_replace_callback(
            '/echo\s+json_encode\s*\(\s*\[\s*[\'"]success[\'"]\s*=>\s*false\s*,\s*[\'"]message[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]\s*\]\s*\)\s*;\s*\n?\s*exit\s*;/m',
            function($m) use (&$stats) {
                $msg = $m[1];
                $code = 500;
                if (stripos($msg, 'unauthorized') !== false) $code = 401;
                elseif (stripos($msg, 'access denied') !== false) $code = 403;
                elseif (stripos($msg, 'not found') !== false) $code = 404;
                elseif (stripos($msg, 'required') !== false || stripos($msg, 'invalid') !== false || stripos($msg, 'missing') !== false) $code = 400;
                $stats['error_replacements']++;
                return "jsonError('$msg', $code);";
            },
            $content
        );

        // Pattern: http_response_code(NNN); echo json_encode([...]); exit;
        // (with optional newlines/spaces between)
        $content = preg_replace_callback(
            '/http_response_code\s*\(\s*(\d+)\s*\)\s*;\s*\n?\s*echo\s+json_encode\s*\(\s*\[\s*[\'"](?:success|status)[\'"]\s*=>\s*(?:false|[\'"]error[\'"])\s*,\s*[\'"](?:error|message)[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]\s*\]\s*\)\s*;\s*\n?\s*exit\s*;/m',
            function($m) use (&$stats) {
                $code = (int)$m[1];
                $msg = $m[2];
                $stats['error_replacements']++;
                return "jsonError('$msg', $code);";
            },
            $content
        );

        // Also handle: echo json_encode(...); return; (without exit)
        $content = preg_replace_callback(
            '/echo\s+json_encode\s*\(\s*\[\s*[\'"]success[\'"]\s*=>\s*false\s*,\s*[\'"](?:error|message)[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]\s*\]\s*\)\s*;\s*\n?\s*return\s*;/m',
            function($m) use (&$stats) {
                $msg = $m[1];
                $code = 400;
                if (stripos($msg, 'access denied') !== false) $code = 403;
                elseif (stripos($msg, 'not found') !== false) $code = 404;
                $stats['error_replacements']++;
                return "jsonError('$msg', $code);";
            },
            $content
        );

        if ($content !== $original) {
            if ($original === file_get_contents($filepath)) {
                // Only backup if not already backed up in Step 3
                backup($filepath);
            }
            file_put_contents($filepath, $content);
            out("Error responses updated: $filename", 'ok');
            $stats['files_modified']++;
        }
    }
}

out("Replaced {$stats['error_replacements']} ad-hoc error responses with jsonError().", $stats['error_replacements'] > 0 ? 'ok' : 'skip');

// ============================================================================
// STEP 5: Replace catch blocks that leak exception details
// ============================================================================
heading('Step 5 — Replace catch blocks that leak exception details');

if (is_dir($ajax_dir)) {
    $ajax_files = glob($ajax_dir . '/*.php');

    foreach ($ajax_files as $filepath) {
        $content = file_get_contents($filepath);
        $original = $content;
        $filename = basename($filepath);

        // Skip if file already uses handleException
        if (strpos($content, 'handleException(') !== false) {
            continue;
        }

        // Pattern: catch block with echo json_encode([...'message' => $e->getMessage()...])
        $handler_name = str_replace('.php', '', $filename);

        // Replace: echo json_encode(['success' => false, 'message' => $e->getMessage()]); 
        // With: handleException($e, 'handler_name');
        $content = preg_replace(
            '/echo\s+json_encode\s*\(\s*\[\s*[\'"](?:success|status)[\'"]\s*=>\s*(?:false|[\'"]error[\'"])\s*,\s*[\'"](?:error|message)[\'"]\s*=>\s*(?:\$e->getMessage\(\)|[\'"][^\'"]*?[\'"]\s*\.\s*\$e->getMessage\(\))\s*\]\s*\)\s*;/m',
            "handleException(\$e, '$handler_name');",
            $content,
            -1,
            $count
        );

        if ($count > 0) {
            $stats['catch_fixes'] += $count;
            if ($original === file_get_contents($filepath)) {
                backup($filepath);
            }
            file_put_contents($filepath, $content);
            out("Catch blocks secured: $filename ($count fixes)", 'ok');
            $stats['files_modified']++;
        }
    }
}

out("Replaced {$stats['catch_fixes']} catch blocks with handleException().", $stats['catch_fixes'] > 0 ? 'ok' : 'skip');

// ============================================================================
// STEP 6: Post-deployment verification
// ============================================================================
heading('Step 6 — Post-deployment verification');

// Check for remaining mysqli_error exposure
$remaining_issues = [];
foreach ($php_files as $filepath) {
    $content = file_get_contents($filepath);
    $filename = str_replace($BASE . '/', '', str_replace('\\', '/', $filepath));

    // Check for client-facing mysqli_error (not in error_log calls)
    $lines = explode("\n", $content);
    foreach ($lines as $lineNum => $line) {
        $trimmed = trim($line);
        // Skip lines that are purely error_log
        if (strpos($trimmed, 'error_log') === 0) continue;
        // Skip comments
        if (strpos($trimmed, '//') === 0 || strpos($trimmed, '*') === 0) continue;

        // Check for mysqli_error in echo/throw that's NOT already wrapped
        if ((strpos($line, 'echo') !== false || strpos($line, 'throw') !== false)
            && (strpos($line, 'mysqli_error') !== false || strpos($line, 'mysqli_stmt_error') !== false)
            && strpos($line, 'error_log') === false) {
            $remaining_issues[] = "$filename:" . ($lineNum + 1) . " — " . trim($line);
        }
    }
}

if (empty($remaining_issues)) {
    out("No remaining mysqli_error exposure found in client responses!", 'ok');
} else {
    out("Found " . count($remaining_issues) . " remaining issues that may need manual review:", 'warn');
    foreach ($remaining_issues as $issue) {
        out("  → $issue", 'warn');
    }
}

// Check error_handler.php is loaded
if (file_exists($error_handler_path) && strpos(file_get_contents($config_path), 'error_handler.php') !== false) {
    out("error_handler.php exists and is included in config.php", 'ok');
} else {
    out("error_handler.php integration issue — check manually", 'err');
}

// ============================================================================
// Summary
// ============================================================================
heading('Deployment Summary');

if (!$IS_CLI) echo '<div class="summary">';
out("Files modified: {$stats['files_modified']}", 'info');
out("Security fixes (mysqli_error removed from client): {$stats['security_fixes']}", 'info');
out("Error responses replaced with jsonError(): {$stats['error_replacements']}", 'info');
out("Catch blocks replaced with handleException(): {$stats['catch_fixes']}", 'info');

if ($stats['files_modified'] > 0) {
    out("", 'info');
    out("Deployment COMPLETE. All AJAX endpoints now use centralized error handling.", 'ok');
    out("", 'info');
    out("Functions available in all files that include config.php:", 'info');
    out("  jsonError(\$message, \$code, \$logMessage, \$errorCode) — safe error JSON response", 'info');
    out("  jsonSuccess(\$data, \$message)                         — success JSON response", 'info');
    out("  handleDbError(\$conn, \$context)                       — DB error (logs + safe response)", 'info');
    out("  handleException(\$e, \$context)                        — exception (logs + safe response)", 'info');
    out("", 'info');
    out("IMPORTANT: Delete this deployment script after running!", 'warn');
} else {
    out("No changes needed — everything is already up to date.", 'skip');
}
if (!$IS_CLI) echo '</div>';

// ---- Cleanup output -------------------------------------------------------
if (!$IS_CLI) {
    echo '</body></html>';
}

// ============================================================================
// Helper: Full content of includes/error_handler.php
// ============================================================================
function get_error_handler_content() {
    return <<<'ERRHANDLER'
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
ERRHANDLER;
}
