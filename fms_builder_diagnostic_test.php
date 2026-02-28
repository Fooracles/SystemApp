<?php
/**
 * FMS Builder diagnostic test script.
 *
 * Usage (CLI):
 *   php fms_builder_diagnostic_test.php
 *
 * Usage (Browser):
 *   /app-v8.3/fms_builder_diagnostic_test.php
 */

declare(strict_types=1);

$projectRoot = __DIR__;
$results = [];
$startedAt = microtime(true);

function add_result(array &$results, string $name, string $status, string $details, string $severity = 'info', array $meta = []): void
{
    $results[] = [
        'name' => $name,
        'status' => $status, // pass | fail | warn
        'severity' => $severity, // info | low | medium | high
        'details' => $details,
        'meta' => $meta,
    ];
}

function project_path(string $root, string $relative): string
{
    return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function resolve_php_cli_binary(string $projectRoot): ?string
{
    $candidates = [];

    if (defined('PHP_BINARY') && PHP_BINARY !== '') {
        $candidates[] = PHP_BINARY;
    }

    $candidates[] = 'C:\\xampp\\php\\php.exe';
    $candidates[] = project_path($projectRoot, 'php\\php.exe');

    if (defined('PHP_BINDIR') && PHP_BINDIR !== '') {
        $candidates[] = rtrim(PHP_BINDIR, '\\/') . DIRECTORY_SEPARATOR . 'php.exe';
    }

    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || $candidate === '') {
            continue;
        }
        $normalized = str_replace('/', DIRECTORY_SEPARATOR, $candidate);
        if (!is_file($normalized)) {
            continue;
        }
        $lower = strtolower($normalized);
        if (strpos($lower, 'httpd.exe') !== false || strpos($lower, 'apache') !== false) {
            continue;
        }
        return $normalized;
    }

    return null;
}

function lint_php_file(string $filePath, ?string $phpCliBinary): array
{
    if (!is_file($filePath)) {
        return ['ok' => false, 'code' => 1, 'output' => "Missing file: {$filePath}"];
    }

    if (!$phpCliBinary) {
        return ['ok' => null, 'code' => null, 'output' => 'PHP CLI binary not found. Skipping lint.'];
    }

    $cmd = escapeshellarg($phpCliBinary) . ' -l ' . escapeshellarg($filePath) . ' 2>&1';
    $out = [];
    $code = 1;
    @exec($cmd, $out, $code);
    return [
        'ok' => ($code === 0),
        'code' => $code,
        'output' => implode(PHP_EOL, $out),
    ];
}

function assert_contains(array &$results, string $name, string $filePath, array $needles, string $severity = 'medium'): void
{
    if (!is_file($filePath)) {
        add_result($results, $name, 'fail', "File not found: {$filePath}", 'high');
        return;
    }
    $content = (string)file_get_contents($filePath);
    $missing = [];
    foreach ($needles as $needle) {
        if (strpos($content, $needle) === false) {
            $missing[] = $needle;
        }
    }
    if (empty($missing)) {
        add_result($results, $name, 'pass', 'All expected markers found.');
    } else {
        add_result($results, $name, 'fail', 'Missing markers: ' . implode(' | ', $missing), $severity);
    }
}

// 1) Critical file existence checks.
$requiredFiles = [
    'pages/fms_builder.php',
    'includes/sidebar.php',
    'ajax/fms_flow_handler.php',
    'ajax/fms_execution_handler.php',
    'ajax/fms_form_handler.php',
    'fms-flow-builder/src/App.jsx',
    'fms-flow-builder/src/components/TopBar.jsx',
    'fms-flow-builder/src/components/ExecutionsPanel.jsx',
    'fms-flow-builder/src/components/TestsPanel.jsx',
    'fms-flow-builder/src/services/executionApi.js',
    'fms-flow-builder/src/services/formApi.js',
];

foreach ($requiredFiles as $rel) {
    $full = project_path($projectRoot, $rel);
    if (is_file($full)) {
        add_result($results, "File exists: {$rel}", 'pass', 'Found');
    } else {
        add_result($results, "File exists: {$rel}", 'fail', 'Missing', 'high');
    }
}

// 2) PHP lint checks for backend files.
$phpCliBinary = resolve_php_cli_binary($projectRoot);
$phpSapi = PHP_SAPI;
if ($phpCliBinary === null) {
    add_result(
        $results,
        'PHP lint runner',
        'warn',
        "No valid php.exe found for lint checks (current SAPI: {$phpSapi}).",
        'medium'
    );
} else {
    add_result(
        $results,
        'PHP lint runner',
        'pass',
        "Using PHP CLI binary: {$phpCliBinary}",
        'info'
    );
}

$phpLintTargets = [
    'pages/fms_builder.php',
    'includes/sidebar.php',
    'ajax/fms_flow_handler.php',
    'ajax/fms_execution_handler.php',
    'ajax/fms_form_handler.php',
];
foreach ($phpLintTargets as $rel) {
    $full = project_path($projectRoot, $rel);
    $lint = lint_php_file($full, $phpCliBinary);
    $status = 'warn';
    $severity = 'medium';
    if ($lint['ok'] === true) {
        $status = 'pass';
        $severity = 'info';
    } elseif ($lint['ok'] === false) {
        $status = 'fail';
        $severity = 'high';
    }
    add_result(
        $results,
        "PHP lint: {$rel}",
        $status,
        $lint['output'] !== '' ? $lint['output'] : 'No output',
        $severity,
        ['exit_code' => $lint['code']]
    );
}

// 3) Feature marker checks from recent FMS changes.
assert_contains(
    $results,
    'Full-view support in fms_builder.php',
    project_path($projectRoot, 'pages/fms_builder.php'),
    ['$full_view', 'fullview=1', 'Click here for full view'],
    'high'
);

assert_contains(
    $results,
    'TopBar dropdown z-index hardening',
    project_path($projectRoot, 'fms-flow-builder/src/components/TopBar.jsx'),
    ['zIndex: 6000', 'overflow: \'visible\'', 'zIndex: 5999'],
    'high'
);

assert_contains(
    $results,
    'App.jsx wired with Executions/Tests',
    project_path($projectRoot, 'fms-flow-builder/src/App.jsx'),
    ['ExecutionsPanel', 'TestsPanel', "activeTab === 'executions'", "activeTab === 'tests'"],
    'high'
);

assert_contains(
    $results,
    'Sidebar keeps single FMS Builder navigation',
    project_path($projectRoot, 'includes/sidebar.php'),
    ['href="fms_builder.php" title="FMS Builder"'],
    'medium'
);

// Confirm that old FMS submenu section is removed.
$sidebarContent = @file_get_contents(project_path($projectRoot, 'includes/sidebar.php'));
if (is_string($sidebarContent) && strpos($sidebarContent, 'FMS Builder Suite') !== false) {
    add_result($results, 'Old FMS submenu removed', 'fail', 'Found legacy "FMS Builder Suite" block.', 'medium');
} else {
    add_result($results, 'Old FMS submenu removed', 'pass', 'Legacy FMS suite submenu not found.');
}

// 4) Build artifact checks.
$buildFiles = [
    'fms-flow-builder/dist/assets/fms-builder.js',
    'fms-flow-builder/dist/assets/fms-builder.css',
];
foreach ($buildFiles as $rel) {
    $full = project_path($projectRoot, $rel);
    if (is_file($full) && filesize($full) > 0) {
        add_result($results, "Build asset: {$rel}", 'pass', 'Present', 'info', ['bytes' => filesize($full)]);
    } else {
        add_result($results, "Build asset: {$rel}", 'warn', 'Missing or empty. Run npm build in fms-flow-builder.', 'medium');
    }
}

// 5) Database checks (if config can initialize mysqli connection).
$dbConn = null;
$configFile = project_path($projectRoot, 'includes/config.php');
if (!is_file($configFile)) {
    add_result($results, 'DB config load', 'fail', 'includes/config.php not found', 'high');
} else {
    ob_start();
    $configLoaded = @include $configFile;
    $buffer = trim((string)ob_get_clean());

    if ($buffer !== '') {
        add_result($results, 'DB config output noise', 'warn', 'Config produced output during include', 'low', ['output' => $buffer]);
    }

    if ($configLoaded === false) {
        add_result($results, 'DB config include', 'fail', 'Could not include includes/config.php', 'high');
    }

    if (isset($conn) && $conn instanceof mysqli) {
        $dbConn = $conn;
    }
}

if ($dbConn instanceof mysqli) {
    if (@$dbConn->ping()) {
        add_result($results, 'Database connection', 'pass', 'Connected');
        $requiredTables = [
            'fms_flows',
            'fms_flow_nodes',
            'fms_flow_edges',
            'fms_flow_versions',
            'fms_flow_forms',
            'fms_flow_form_fields',
            'fms_flow_form_step_map',
            'fms_flow_runs',
            'fms_flow_run_steps',
        ];
        foreach ($requiredTables as $table) {
            $escaped = $dbConn->real_escape_string($table);
            $sql = "SHOW TABLES LIKE '{$escaped}'";
            $res = @$dbConn->query($sql);
            if ($res && $res->num_rows > 0) {
                add_result($results, "DB table exists: {$table}", 'pass', 'Present');
            } else {
                add_result($results, "DB table exists: {$table}", 'fail', 'Missing', 'high');
            }
            if ($res instanceof mysqli_result) {
                $res->free();
            }
        }

        // Optional metrics for quick situational awareness.
        $metrics = [
            'fms_flows' => 'SELECT COUNT(*) AS c FROM fms_flows',
            'fms_flow_forms' => 'SELECT COUNT(*) AS c FROM fms_flow_forms',
            'fms_flow_runs' => 'SELECT COUNT(*) AS c FROM fms_flow_runs',
            'fms_flow_run_steps' => 'SELECT COUNT(*) AS c FROM fms_flow_run_steps',
        ];
        foreach ($metrics as $name => $sql) {
            $res = @$dbConn->query($sql);
            if ($res && ($row = $res->fetch_assoc())) {
                add_result($results, "DB metric: {$name}", 'pass', 'Row count collected', 'info', ['count' => (int)$row['c']]);
            } else {
                add_result($results, "DB metric: {$name}", 'warn', 'Could not fetch row count', 'low');
            }
            if ($res instanceof mysqli_result) {
                $res->free();
            }
        }
    } else {
        add_result($results, 'Database connection', 'fail', 'Connection ping failed', 'high');
    }
} else {
    add_result($results, 'Database connection', 'warn', 'No mysqli connection available from includes/config.php', 'medium');
}

// 6) Summary + JSON report.
$total = count($results);
$passed = count(array_filter($results, static fn($r) => $r['status'] === 'pass'));
$failed = count(array_filter($results, static fn($r) => $r['status'] === 'fail'));
$warnings = count(array_filter($results, static fn($r) => $r['status'] === 'warn'));
$elapsedMs = (int)round((microtime(true) - $startedAt) * 1000);

$report = [
    'generated_at' => date('c'),
    'project_root' => $projectRoot,
    'summary' => [
        'total' => $total,
        'passed' => $passed,
        'failed' => $failed,
        'warnings' => $warnings,
        'elapsed_ms' => $elapsedMs,
    ],
    'results' => $results,
];

$jsonReportPath = project_path($projectRoot, 'fms_builder_diagnostic_report.json');
@file_put_contents($jsonReportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "FMS Builder Diagnostic Test\n";
echo "Generated: " . $report['generated_at'] . "\n";
echo "Project: {$projectRoot}\n";
echo "Elapsed: {$elapsedMs} ms\n";
echo str_repeat('=', 72) . "\n";
echo "Summary: total={$total}, passed={$passed}, failed={$failed}, warnings={$warnings}\n";
echo str_repeat('-', 72) . "\n";

foreach ($results as $idx => $row) {
    $n = $idx + 1;
    $status = strtoupper($row['status']);
    $sev = strtoupper($row['severity']);
    echo "[{$n}] {$status} ({$sev}) {$row['name']}\n";
    echo "    {$row['details']}\n";
    if (!empty($row['meta'])) {
        echo "    meta: " . json_encode($row['meta'], JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo str_repeat('-', 72) . "\n";
echo "JSON report: {$jsonReportPath}\n";
echo ($failed > 0) ? "FINAL STATUS: FAIL\n" : (($warnings > 0) ? "FINAL STATUS: PASS_WITH_WARNINGS\n" : "FINAL STATUS: PASS\n");

exit($failed > 0 ? 1 : 0);

