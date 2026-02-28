<?php
/**
 * Diagnostic / Test File for Reports Page
 * Tests: dependencies, DB schema, file system, auth, AJAX endpoint, upload config
 * Access: Admin only
 */

$page_title = "Reports Diagnostics";
require_once "../includes/header.php";

if (!isLoggedIn() || !isAdmin()) {
    echo '<div style="padding:2rem;color:#f87171;">Access denied. Admin only.</div>';
    require_once "../includes/footer.php";
    exit;
}
?>
<style>
    .diag-container { max-width: 1000px; margin: 0 auto; padding: 1.5rem; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    .diag-title { color: #e2e8f0; font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem; }
    .diag-subtitle { color: #94a3b8; font-size: 0.95rem; margin-bottom: 2rem; }
    .diag-section { background: rgba(17, 24, 39, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 0.75rem; margin-bottom: 1.25rem; overflow: hidden; }
    .diag-section-header { padding: 0.875rem 1.25rem; background: rgba(30, 41, 59, 0.5); border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; gap: 0.625rem; }
    .diag-section-header h3 { color: #e2e8f0; font-size: 1rem; font-weight: 600; margin: 0; }
    .diag-section-header .icon { font-size: 1.1rem; }
    .diag-row { display: flex; justify-content: space-between; align-items: center; padding: 0.625rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.04); font-size: 0.875rem; }
    .diag-row:last-child { border-bottom: none; }
    .diag-label { color: #cbd5e1; flex: 1; }
    .diag-value { color: #94a3b8; flex: 1; text-align: right; word-break: break-all; }
    .badge { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.2rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
    .badge-pass { background: rgba(34,197,94,0.15); color: #4ade80; border: 1px solid rgba(34,197,94,0.25); }
    .badge-fail { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.25); }
    .badge-warn { background: rgba(251,191,36,0.15); color: #fbbf24; border: 1px solid rgba(251,191,36,0.25); }
    .badge-info { background: rgba(96,165,250,0.15); color: #60a5fa; border: 1px solid rgba(96,165,250,0.25); }
    .diag-summary { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .summary-card { flex: 1; min-width: 140px; padding: 1rem; border-radius: 0.75rem; text-align: center; }
    .summary-card .num { font-size: 2rem; font-weight: 700; }
    .summary-card .lbl { font-size: 0.8rem; margin-top: 0.25rem; }
    .card-pass { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); }
    .card-pass .num { color: #4ade80; }
    .card-pass .lbl { color: #86efac; }
    .card-fail { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); }
    .card-fail .num { color: #f87171; }
    .card-fail .lbl { color: #fca5a5; }
    .card-warn { background: rgba(251,191,36,0.1); border: 1px solid rgba(251,191,36,0.2); }
    .card-warn .num { color: #fbbf24; }
    .card-warn .lbl { color: #fde68a; }
    .card-total { background: rgba(96,165,250,0.1); border: 1px solid rgba(96,165,250,0.2); }
    .card-total .num { color: #60a5fa; }
    .card-total .lbl { color: #93c5fd; }
    .detail-text { font-size: 0.75rem; color: #64748b; margin-top: 0.125rem; }
</style>

<?php
$pass = 0; $fail = 0; $warn = 0; $total = 0;
$results = [];

function addResult(&$results, &$total, &$pass, &$fail, &$warn, $section, $test, $status, $value, $detail = '') {
    $total++;
    if ($status === 'pass') $pass++;
    elseif ($status === 'fail') $fail++;
    elseif ($status === 'warn') $warn++;
    $results[] = ['section' => $section, 'test' => $test, 'status' => $status, 'value' => $value, 'detail' => $detail];
}

// ─── 1. ENVIRONMENT ───
$section = 'Environment';

$phpVer = phpversion();
addResult($results, $total, $pass, $fail, $warn, $section, 'PHP Version', version_compare($phpVer, '7.4', '>=') ? 'pass' : 'fail', $phpVer, 'Requires >= 7.4');

$requiredExts = ['mysqli', 'json', 'mbstring', 'fileinfo', 'session'];
foreach ($requiredExts as $ext) {
    $loaded = extension_loaded($ext);
    addResult($results, $total, $pass, $fail, $warn, $section, "Extension: $ext", $loaded ? 'pass' : 'fail', $loaded ? 'Loaded' : 'Missing');
}

addResult($results, $total, $pass, $fail, $warn, $section, 'Display Errors', ini_get('display_errors') ? 'warn' : 'pass', ini_get('display_errors') ? 'ON (disable in production)' : 'OFF');

$tz = date_default_timezone_get();
addResult($results, $total, $pass, $fail, $warn, $section, 'Timezone', 'info', $tz);

// ─── 2. FILE DEPENDENCIES ───
$section = 'File Dependencies';

$depFiles = [
    'includes/config.php'                => '../includes/config.php',
    'includes/functions.php'             => '../includes/functions.php',
    'includes/header.php'                => '../includes/header.php',
    'includes/footer.php'                => '../includes/footer.php',
    'includes/notification_triggers.php' => '../includes/notification_triggers.php',
    'includes/error_handler.php'         => '../includes/error_handler.php',
    'includes/file_uploader.php'         => '../includes/file_uploader.php',
    'includes/db_schema.php'             => '../includes/db_schema.php',
    'includes/db_functions.php'          => '../includes/db_functions.php',
    'includes/sidebar.php'              => '../includes/sidebar.php',
    'pages/report.php'                   => 'report.php',
    'ajax/reports_handler.php'           => '../ajax/reports_handler.php',
    'ajax/updates_handler.php'           => '../ajax/updates_handler.php',
];

foreach ($depFiles as $label => $path) {
    $exists = file_exists($path);
    addResult($results, $total, $pass, $fail, $warn, $section, $label, $exists ? 'pass' : 'fail', $exists ? 'Found' : 'MISSING', realpath($path) ?: $path);
}

$securityFile = '../includes/security.php';
addResult($results, $total, $pass, $fail, $warn, $section, 'includes/security.php (referenced by reports_handler)', file_exists($securityFile) ? 'pass' : 'warn', file_exists($securityFile) ? 'Found' : 'NOT FOUND — may cause 500 error if called');

// ─── 3. DATABASE CONNECTION ───
$section = 'Database';

$dbOk = isset($conn) && $conn instanceof mysqli && !$conn->connect_error;
addResult($results, $total, $pass, $fail, $warn, $section, 'MySQL Connection', $dbOk ? 'pass' : 'fail', $dbOk ? 'Connected' : 'FAILED', $dbOk ? $conn->server_info : ($conn->connect_error ?? 'No $conn'));

if ($dbOk) {
    // Reports table
    $tblCheck = mysqli_query($conn, "SHOW TABLES LIKE 'reports'");
    $tblExists = $tblCheck && mysqli_num_rows($tblCheck) > 0;
    addResult($results, $total, $pass, $fail, $warn, $section, 'Table: reports', $tblExists ? 'pass' : 'fail', $tblExists ? 'Exists' : 'MISSING');

    if ($tblExists) {
        $requiredCols = ['id', 'title', 'project_name', 'file_path', 'file_name', 'file_type', 'file_size', 'uploaded_by', 'uploaded_at'];
        $optionalCols = ['assigned_to', 'client_account_id'];
        
        $colResult = mysqli_query($conn, "SHOW COLUMNS FROM reports");
        $existingCols = [];
        while ($colRow = mysqli_fetch_assoc($colResult)) {
            $existingCols[] = $colRow['Field'];
        }

        foreach ($requiredCols as $col) {
            $has = in_array($col, $existingCols);
            addResult($results, $total, $pass, $fail, $warn, $section, "Column: reports.$col", $has ? 'pass' : 'fail', $has ? 'Present' : 'MISSING');
        }

        foreach ($optionalCols as $col) {
            $has = in_array($col, $existingCols);
            addResult($results, $total, $pass, $fail, $warn, $section, "Column: reports.$col (optional)", $has ? 'pass' : 'warn', $has ? 'Present' : 'Missing — will be auto-created on first upload');
        }

        // Row count
        $countResult = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM reports");
        $count = $countResult ? mysqli_fetch_assoc($countResult)['cnt'] : 0;
        addResult($results, $total, $pass, $fail, $warn, $section, 'Reports row count', 'info', number_format($count));

        // Foreign key check
        $fkCheck = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM reports r LEFT JOIN users u ON r.uploaded_by = u.id WHERE u.id IS NULL");
        $orphaned = $fkCheck ? mysqli_fetch_assoc($fkCheck)['cnt'] : 0;
        addResult($results, $total, $pass, $fail, $warn, $section, 'Orphaned reports (uploaded_by → deleted user)', $orphaned == 0 ? 'pass' : 'warn', $orphaned == 0 ? 'None' : "$orphaned orphaned row(s)");

        // Index check
        $indexResult = mysqli_query($conn, "SHOW INDEX FROM reports");
        $indexes = [];
        while ($idxRow = mysqli_fetch_assoc($indexResult)) {
            $indexes[] = $idxRow['Key_name'] . '.' . $idxRow['Column_name'];
        }
        $hasUploadedByIdx = false;
        $hasUploadedAtIdx = false;
        foreach ($indexes as $idx) {
            if (strpos($idx, 'uploaded_by') !== false) $hasUploadedByIdx = true;
            if (strpos($idx, 'uploaded_at') !== false) $hasUploadedAtIdx = true;
        }
        addResult($results, $total, $pass, $fail, $warn, $section, 'Index on uploaded_by', $hasUploadedByIdx ? 'pass' : 'warn', $hasUploadedByIdx ? 'Present' : 'Missing — queries may be slow');
        addResult($results, $total, $pass, $fail, $warn, $section, 'Index on uploaded_at', $hasUploadedAtIdx ? 'pass' : 'warn', $hasUploadedAtIdx ? 'Present' : 'Missing — ORDER BY may be slow');
    }

    // report_recipients table
    $rrCheck = mysqli_query($conn, "SHOW TABLES LIKE 'report_recipients'");
    $rrExists = $rrCheck && mysqli_num_rows($rrCheck) > 0;
    addResult($results, $total, $pass, $fail, $warn, $section, 'Table: report_recipients', $rrExists ? 'pass' : 'warn', $rrExists ? 'Exists' : 'Missing — will be auto-created on first multi-user upload');

    // Users table
    $usrCheck = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
    $usrExists = $usrCheck && mysqli_num_rows($usrCheck) > 0;
    addResult($results, $total, $pass, $fail, $warn, $section, 'Table: users', $usrExists ? 'pass' : 'fail', $usrExists ? 'Exists' : 'MISSING — critical');

    if ($usrExists) {
        $adminCount = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users WHERE user_type = 'admin'");
        $admins = $adminCount ? mysqli_fetch_assoc($adminCount)['cnt'] : 0;
        addResult($results, $total, $pass, $fail, $warn, $section, 'Admin users', $admins > 0 ? 'pass' : 'warn', $admins);

        $mgrCount = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users WHERE user_type = 'manager'");
        $mgrs = $mgrCount ? mysqli_fetch_assoc($mgrCount)['cnt'] : 0;
        addResult($results, $total, $pass, $fail, $warn, $section, 'Manager users', 'info', $mgrs);

        $clientAccounts = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users WHERE user_type = 'client' AND (password IS NULL OR password = '')");
        $ca = $clientAccounts ? mysqli_fetch_assoc($clientAccounts)['cnt'] : 0;
        addResult($results, $total, $pass, $fail, $warn, $section, 'Client accounts (password-less)', 'info', $ca);

        $clientUsers = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users WHERE user_type = 'client' AND password IS NOT NULL AND password != ''");
        $cu = $clientUsers ? mysqli_fetch_assoc($clientUsers)['cnt'] : 0;
        addResult($results, $total, $pass, $fail, $warn, $section, 'Client users (with password)', 'info', $cu);
    }
}

// ─── 4. FILE SYSTEM ───
$section = 'File System & Uploads';

$uploadDir = '../uploads/reports/';
$uploadDirExists = is_dir($uploadDir);
addResult($results, $total, $pass, $fail, $warn, $section, 'Upload directory exists', $uploadDirExists ? 'pass' : 'warn', $uploadDirExists ? realpath($uploadDir) : 'Missing — will be created on first upload');

if ($uploadDirExists) {
    $writable = is_writable($uploadDir);
    addResult($results, $total, $pass, $fail, $warn, $section, 'Upload directory writable', $writable ? 'pass' : 'fail', $writable ? 'Yes' : 'NOT WRITABLE — uploads will fail');

    $fileCount = count(glob($uploadDir . '*'));
    addResult($results, $total, $pass, $fail, $warn, $section, 'Files in upload directory', 'info', number_format($fileCount));

    if ($dbOk && $tblExists) {
        $dbFileCount = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM reports");
        $dbFiles = $dbFileCount ? mysqli_fetch_assoc($dbFileCount)['cnt'] : 0;
        $mismatch = abs($fileCount - $dbFiles);
        addResult($results, $total, $pass, $fail, $warn, $section, 'DB records vs disk files', $mismatch <= 2 ? 'pass' : 'warn', "DB: $dbFiles, Disk: $fileCount", $mismatch > 2 ? 'Possible orphaned files or missing files' : 'In sync');
    }
} else {
    addResult($results, $total, $pass, $fail, $warn, $section, 'Upload directory writable', 'warn', 'N/A — directory does not exist yet');
}

// PHP upload limits
$uploadMax = ini_get('upload_max_filesize');
$postMax = ini_get('post_max_size');
$memLimit = ini_get('memory_limit');

$uploadMaxBytes = (int) $uploadMax * (stripos($uploadMax, 'M') !== false ? 1048576 : (stripos($uploadMax, 'G') !== false ? 1073741824 : 1));
$appMax = 50 * 1024 * 1024; // 50MB app limit

addResult($results, $total, $pass, $fail, $warn, $section, 'PHP upload_max_filesize', $uploadMaxBytes >= $appMax ? 'pass' : 'fail', $uploadMax, "App requires 50MB max. Current: $uploadMax");

$postMaxBytes = (int) $postMax * (stripos($postMax, 'M') !== false ? 1048576 : (stripos($postMax, 'G') !== false ? 1073741824 : 1));
addResult($results, $total, $pass, $fail, $warn, $section, 'PHP post_max_size', $postMaxBytes >= $appMax ? 'pass' : 'fail', $postMax, 'Must be >= upload_max_filesize');

addResult($results, $total, $pass, $fail, $warn, $section, 'PHP memory_limit', 'info', $memLimit);
addResult($results, $total, $pass, $fail, $warn, $section, 'PHP max_execution_time', 'info', ini_get('max_execution_time') . 's');
addResult($results, $total, $pass, $fail, $warn, $section, 'PHP file_uploads enabled', ini_get('file_uploads') ? 'pass' : 'fail', ini_get('file_uploads') ? 'Yes' : 'DISABLED');

// ─── 5. SESSION & AUTH ───
$section = 'Session & Auth';

addResult($results, $total, $pass, $fail, $warn, $section, 'Session active', session_status() === PHP_SESSION_ACTIVE ? 'pass' : 'fail', session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive');
addResult($results, $total, $pass, $fail, $warn, $section, 'User logged in', isLoggedIn() ? 'pass' : 'fail', isLoggedIn() ? 'Yes' : 'No');
addResult($results, $total, $pass, $fail, $warn, $section, 'User ID', 'info', $_SESSION['id'] ?? 'N/A');
addResult($results, $total, $pass, $fail, $warn, $section, 'Username', 'info', htmlspecialchars($_SESSION['username'] ?? 'N/A'));
addResult($results, $total, $pass, $fail, $warn, $section, 'User type', 'info', $_SESSION['user_type'] ?? 'N/A');
addResult($results, $total, $pass, $fail, $warn, $section, 'isAdmin()', 'info', isAdmin() ? 'true' : 'false');
addResult($results, $total, $pass, $fail, $warn, $section, 'isManager()', 'info', isManager() ? 'true' : 'false');
addResult($results, $total, $pass, $fail, $warn, $section, 'isClient()', 'info', isClient() ? 'true' : 'false');

$csrfOk = function_exists('generateCsrfToken');
addResult($results, $total, $pass, $fail, $warn, $section, 'CSRF functions available', $csrfOk ? 'pass' : 'fail', $csrfOk ? 'Yes' : 'generateCsrfToken not found');

if ($csrfOk) {
    $token = generateCsrfToken();
    addResult($results, $total, $pass, $fail, $warn, $section, 'CSRF token generation', !empty($token) ? 'pass' : 'fail', !empty($token) ? 'Token generated (' . strlen($token) . ' chars)' : 'FAILED');
}

// ─── 6. REPORTS HANDLER ENDPOINT ───
$section = 'AJAX Endpoint';

$handlerPath = '../ajax/reports_handler.php';
addResult($results, $total, $pass, $fail, $warn, $section, 'reports_handler.php readable', is_readable($handlerPath) ? 'pass' : 'fail', is_readable($handlerPath) ? 'Yes' : 'NOT READABLE');

$handlerContent = file_get_contents($handlerPath);

$expectedActions = ['get_reports', 'get_report', 'upload_report', 'update_report', 'delete_report', 'view', 'download'];
foreach ($expectedActions as $action) {
    $found = strpos($handlerContent, "'$action'") !== false;
    addResult($results, $total, $pass, $fail, $warn, $section, "Action: $action", $found ? 'pass' : 'fail', $found ? 'Implemented' : 'NOT FOUND in handler');
}

$hasAuthCheck = strpos($handlerContent, 'isLoggedIn') !== false;
addResult($results, $total, $pass, $fail, $warn, $section, 'Auth check in handler', $hasAuthCheck ? 'pass' : 'fail', $hasAuthCheck ? 'Present' : 'MISSING — security risk');

$hasCsrf = strpos($handlerContent, 'csrfProtect') !== false;
addResult($results, $total, $pass, $fail, $warn, $section, 'CSRF protection in handler', $hasCsrf ? 'pass' : 'fail', $hasCsrf ? 'Present' : 'MISSING — security risk');

$hasRoleCheck = strpos($handlerContent, 'isAdmin') !== false && strpos($handlerContent, 'isManager') !== false;
addResult($results, $total, $pass, $fail, $warn, $section, 'Role-based access control', $hasRoleCheck ? 'pass' : 'fail', $hasRoleCheck ? 'Present' : 'MISSING');

// ─── 7. DATA INTEGRITY ───
$section = 'Data Integrity';

if ($dbOk && $tblExists) {
    // Check for reports with missing files
    $missingFiles = 0;
    $fileCheck = mysqli_query($conn, "SELECT id, file_path FROM reports LIMIT 100");
    $checked = 0;
    if ($fileCheck) {
        while ($row = mysqli_fetch_assoc($fileCheck)) {
            $checked++;
            $fp = '../' . $row['file_path'];
            if (!file_exists($fp)) {
                $missingFiles++;
            }
        }
    }
    addResult($results, $total, $pass, $fail, $warn, $section, 'Reports with missing files', $missingFiles === 0 ? 'pass' : 'warn', $missingFiles === 0 ? "All $checked checked files exist" : "$missingFiles of $checked reports have missing files");

    // Check for zero-size files
    $zeroSize = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM reports WHERE file_size = 0");
    $zeros = $zeroSize ? mysqli_fetch_assoc($zeroSize)['cnt'] : 0;
    addResult($results, $total, $pass, $fail, $warn, $section, 'Zero-size report records', $zeros === 0 ? 'pass' : 'warn', $zeros === 0 ? 'None' : "$zeros record(s)");

    // Check for empty titles
    $emptyTitle = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM reports WHERE title IS NULL OR title = ''");
    $empties = $emptyTitle ? mysqli_fetch_assoc($emptyTitle)['cnt'] : 0;
    addResult($results, $total, $pass, $fail, $warn, $section, 'Reports with empty titles', $empties === 0 ? 'pass' : 'warn', $empties === 0 ? 'None' : "$empties record(s)");

    // Latest report
    $latest = mysqli_query($conn, "SELECT title, uploaded_at, file_type FROM reports ORDER BY uploaded_at DESC LIMIT 1");
    if ($latest && $latestRow = mysqli_fetch_assoc($latest)) {
        addResult($results, $total, $pass, $fail, $warn, $section, 'Latest report', 'info', htmlspecialchars($latestRow['title']) . ' (' . $latestRow['uploaded_at'] . ')');
    } else {
        addResult($results, $total, $pass, $fail, $warn, $section, 'Latest report', 'info', 'No reports uploaded yet');
    }

    // File type distribution
    $types = mysqli_query($conn, "SELECT SUBSTRING_INDEX(file_name, '.', -1) as ext, COUNT(*) as cnt FROM reports GROUP BY ext ORDER BY cnt DESC");
    $typeList = [];
    if ($types) {
        while ($tr = mysqli_fetch_assoc($types)) {
            $typeList[] = strtoupper($tr['ext']) . ': ' . $tr['cnt'];
        }
    }
    addResult($results, $total, $pass, $fail, $warn, $section, 'File type distribution', 'info', !empty($typeList) ? implode(', ', $typeList) : 'No data');
} else {
    addResult($results, $total, $pass, $fail, $warn, $section, 'Skipped', 'warn', 'DB not connected or reports table missing');
}

// ─── 8. NOTIFICATIONS ───
$section = 'Notifications';

$ntFunc = function_exists('triggerClientReportNotification');
addResult($results, $total, $pass, $fail, $warn, $section, 'triggerClientReportNotification()', $ntFunc ? 'pass' : 'fail', $ntFunc ? 'Available' : 'MISSING — report upload notifications will fail');

$ntTblCheck = $dbOk ? mysqli_query($conn, "SHOW TABLES LIKE 'notifications'") : false;
$ntTblExists = $ntTblCheck && mysqli_num_rows($ntTblCheck) > 0;
addResult($results, $total, $pass, $fail, $warn, $section, 'Table: notifications', $ntTblExists ? 'pass' : 'warn', $ntTblExists ? 'Exists' : 'Missing — notifications wont persist');

// ─── RENDER ───
?>

<div class="diag-container">
    <h1 class="diag-title"><i class="fas fa-stethoscope"></i> Reports Page Diagnostics</h1>
    <p class="diag-subtitle">Testing <code>pages/report.php</code> and <code>ajax/reports_handler.php</code> — <?php echo date('Y-m-d H:i:s'); ?></p>

    <div class="diag-summary">
        <div class="summary-card card-total">
            <div class="num"><?php echo $total; ?></div>
            <div class="lbl">Total Tests</div>
        </div>
        <div class="summary-card card-pass">
            <div class="num"><?php echo $pass; ?></div>
            <div class="lbl">Passed</div>
        </div>
        <div class="summary-card card-fail">
            <div class="num"><?php echo $fail; ?></div>
            <div class="lbl">Failed</div>
        </div>
        <div class="summary-card card-warn">
            <div class="num"><?php echo $warn; ?></div>
            <div class="lbl">Warnings</div>
        </div>
    </div>

    <?php
    $currentSection = '';
    foreach ($results as $r) {
        if ($r['section'] !== $currentSection) {
            if ($currentSection !== '') echo '</div>';
            $currentSection = $r['section'];
            $icons = [
                'Environment' => 'fas fa-server',
                'File Dependencies' => 'fas fa-sitemap',
                'Database' => 'fas fa-database',
                'File System & Uploads' => 'fas fa-folder-open',
                'Session & Auth' => 'fas fa-shield-alt',
                'AJAX Endpoint' => 'fas fa-plug',
                'Data Integrity' => 'fas fa-check-double',
                'Notifications' => 'fas fa-bell',
            ];
            $icon = $icons[$currentSection] ?? 'fas fa-cog';
            echo '<div class="diag-section">';
            echo '<div class="diag-section-header"><i class="icon ' . $icon . '" style="color:#8b5cf6;"></i><h3>' . $currentSection . '</h3></div>';
        }

        $badgeClass = 'badge-' . $r['status'];
        $statusLabel = strtoupper($r['status']);
        if ($r['status'] === 'info') $statusLabel = 'INFO';

        echo '<div class="diag-row">';
        echo '<span class="diag-label">' . $r['test'] . '</span>';
        echo '<span class="diag-value">';
        echo '<span class="badge ' . $badgeClass . '">' . $statusLabel . '</span> ';
        echo htmlspecialchars($r['value']);
        if (!empty($r['detail'])) echo '<div class="detail-text">' . htmlspecialchars($r['detail']) . '</div>';
        echo '</span>';
        echo '</div>';
    }
    if ($currentSection !== '') echo '</div>';
    ?>

    <div style="text-align:center; margin-top:2rem; padding:1rem; color:#64748b; font-size:0.8rem;">
        Diagnostic completed in <?php echo round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000); ?>ms
        &middot; PHP <?php echo phpversion(); ?>
        &middot; MySQL <?php echo $dbOk ? $conn->server_info : 'N/A'; ?>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
