<?php
/**
 * FMS Builder — Admin-only: embed Flow Builder directly (no iframe for built version).
 * Auto-uses dev server iframe (http://localhost:5173/) when running for real-time HMR.
 * When using built app, embeds JS/CSS directly into the page.
 */
$page_title = "FMS Builder";
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/functions.php";

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}
if (!isAdmin()) {
    header("Location: admin_dashboard.php");
    exit;
}

// Prevent caching so we always get latest assets
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$builder_dir = __DIR__ . "/../fms-flow-builder";
$dist_dir = $builder_dir . "/dist";
$dist_js = $dist_dir . "/assets/fms-builder.js";
$dist_css = $dist_dir . "/assets/fms-builder.css";
$has_build = file_exists($dist_js);
$dev_server_url = "http://localhost:5173/";
$requested_view = $_GET['view'] ?? 'editor';
$allowed_views = ['editor', 'formBuilder', 'formSubmit', 'myTasks', 'home', 'settings'];
if (!in_array($requested_view, $allowed_views, true)) {
    $requested_view = 'editor';
}

// Cache-bust version
$build_version = $has_build ? '?v=' . filemtime($dist_js) : '';

// Base path for assets
$base_path = str_replace("\\", "/", dirname(dirname($_SERVER['PHP_SELF'])));
if ($base_path === "/" || $base_path === "\\" || $base_path === "") {
    $assets_base = "/fms-flow-builder/dist/assets";
} else {
    $assets_base = rtrim($base_path, "/") . "/fms-flow-builder/dist/assets";
}
$app_root_for_dev = rtrim($base_path, "/");
if ($app_root_for_dev === '') {
    $app_root_for_dev = '/';
}

// Check if dev server is running (use iframe for HMR); ?source=built forces built version
$force_built = isset($_GET['source']) && $_GET['source'] === 'built';
$use_dev_server = false;
if (!$force_built) {
    $ctx = stream_context_create(['http' => ['timeout' => 1.0, 'ignore_errors' => true]]);
    $use_dev_server = @file_get_contents($dev_server_url, false, $ctx) !== false;
}
$full_view = isset($_GET['fullview']) && $_GET['fullview'] === '1';

if ($full_view) {
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FMS Builder</title>
    <style>
        html, body { margin: 0; width: 100%; height: 100%; background: #0f0f14; overflow: hidden; }
        .fms-builder-standalone, .fms-builder-standalone #root, .fms-builder-standalone iframe { width: 100%; height: 100%; border: none; }
        .fms-builder-standalone { position: fixed; inset: 0; background: #0f0f14; }
        .fms-builder-fallback {
            box-sizing: border-box;
            padding: 2rem;
            max-width: 560px;
            color: #f4f4f5;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        }
        .fms-builder-fallback p, .fms-builder-fallback ol { color: #a1a1aa; line-height: 1.6; }
        .fms-builder-fallback code { background: #27272a; color: #22c55e; padding: 2px 8px; border-radius: 6px; border: 1px solid #3f3f46; }
    </style>
</head>
<body>
<?php if ($use_dev_server): ?>
    <div class="fms-builder-standalone">
        <iframe src="<?php echo htmlspecialchars($dev_server_url . '?view=' . urlencode($requested_view) . '&appRoot=' . urlencode($app_root_for_dev)); ?>" title="FMS Builder"></iframe>
    </div>
<?php elseif ($has_build): ?>
    <div class="fms-builder-standalone">
        <div id="root"></div>
    </div>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assets_base . '/fms-builder.css' . $build_version); ?>">
    <script>
    window.__FMS_PHP_BASE = '<?php echo rtrim($base_path, "/"); ?>/ajax/fms_flow_handler.php';
    window.__FMS_APP_ROOT = '<?php echo rtrim($base_path, "/"); ?>';
    window.__FMS_INITIAL_VIEW = '<?php echo htmlspecialchars($requested_view, ENT_QUOTES); ?>';
    </script>
    <script type="module" src="<?php echo htmlspecialchars($assets_base . '/fms-builder.js' . $build_version); ?>"></script>
<?php else: ?>
    <div class="fms-builder-fallback">
        <h1>FMS Builder</h1>
        <p>The Flow Builder app is not built yet. Build it to run in full view.</p>
        <ol>
            <li>Open terminal in project folder.</li>
            <li>Run: <code>cd fms-flow-builder</code></li>
            <li>Run: <code>npm install</code></li>
            <li>Run: <code>npm run build</code></li>
            <li>Refresh this page.</li>
        </ol>
    </div>
<?php endif; ?>
</body>
</html>
<?php
    exit;
}

require_once __DIR__ . "/../includes/header.php";
?>
<style>
/* FMS Builder page — dark theme, full-bleed */
.content-area:has(.fms-builder-container),
.content-area:has(.fms-builder-iframe-wrap),
.content-area:has(.fms-builder-fallback) {
    padding: 0 !important;
    min-height: calc(100vh - 70px);
    position: relative;
    background: #0f0f14;
    overflow: hidden;
}
.main-content:has(.fms-builder-container),
.main-content:has(.fms-builder-iframe-wrap) {
    overflow: hidden;
}

/* Direct embed container */
.fms-builder-container {
    position: absolute;
    left: 0; top: 0; right: 0; bottom: 0;
    background: #0f0f14;
}
.fms-builder-container #root {
    width: 100%;
    height: 100%;
}

/* Iframe for dev server */
.fms-builder-iframe-wrap {
    position: absolute;
    left: 0; top: 0; right: 0; bottom: 0;
    background: #0f0f14;
}
.fms-builder-iframe-wrap iframe {
    display: block;
    width: 100%;
    height: 100%;
    border: none;
}

/* Fallback */
.fms-builder-fallback {
    padding: 2rem;
    max-width: 560px;
    background: #0f0f14;
    min-height: calc(100vh - 70px);
    color: #f4f4f5;
}
.fms-builder-fallback h1 { margin: 0 0 1rem; font-size: 1.5rem; font-weight: 600; color: #f4f4f5; }
.fms-builder-fallback p { color: #a1a1aa; line-height: 1.6; margin: 0 0 0.75rem; }
.fms-builder-fallback ol { color: #a1a1aa; line-height: 1.8; margin: 0 0 1rem; padding-left: 1.25rem; }
.fms-builder-fallback code { background: #27272a; color: #22c55e; padding: 2px 8px; border-radius: 6px; font-size: 0.9em; border: 1px solid #3f3f46; }
.fms-builder-fallback a { color: #22c55e; text-decoration: none; }
.fms-builder-fallback a:hover { text-decoration: underline; }

/* Status bar */
.fms-builder-status {
    position: fixed;
    bottom: 12px;
    right: 16px;
    padding: 6px 10px;
    background: #18181b;
    border: 1px solid #2a2a35;
    border-radius: 8px;
    font-size: 11px;
    color: #71717a;
    z-index: 120;
}
.fms-builder-status a { color: #22c55e; }
.fms-live-dot {
    display: inline-block;
    width: 6px; height: 6px;
    background: #22c55e;
    border-radius: 50%;
    margin-right: 6px;
    animation: fms-pulse 1.5s ease-in-out infinite;
    vertical-align: middle;
}
@keyframes fms-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
</style>

<?php if ($use_dev_server): ?>
<!-- Dev server: use iframe for HMR -->
<div class="fms-builder-iframe-wrap">
    <iframe src="<?php echo htmlspecialchars($dev_server_url . '?view=' . urlencode($requested_view) . '&appRoot=' . urlencode($app_root_for_dev)); ?>" title="FMS Builder"></iframe>
</div>
<p class="fms-builder-status">
    <span class="fms-live-dot"></span> Live — dev server at <strong><?php echo htmlspecialchars($dev_server_url); ?></strong>
    <span style="margin-left: 12px;">Click here for full view</span>
    <a href="fms_builder.php?fullview=1&view=<?php echo urlencode($requested_view); ?>" target="_blank" title="Open full view" style="margin-left: 6px; text-decoration: none;">&#8599;</a>
</p>

<?php elseif ($has_build): ?>
<!-- Built version: embed directly -->
<div class="fms-builder-container">
    <div id="root"></div>
</div>
<link rel="stylesheet" href="<?php echo htmlspecialchars($assets_base . '/fms-builder.css' . $build_version); ?>">
<script>
// Tell the React app where the PHP API lives (used by flowApi.js)
window.__FMS_PHP_BASE = '<?php echo rtrim($base_path, "/"); ?>/ajax/fms_flow_handler.php';
window.__FMS_APP_ROOT = '<?php echo rtrim($base_path, "/"); ?>';
window.__FMS_INITIAL_VIEW = '<?php echo htmlspecialchars($requested_view, ENT_QUOTES); ?>';
</script>
<script type="module" src="<?php echo htmlspecialchars($assets_base . '/fms-builder.js' . $build_version); ?>"></script>
<p class="fms-builder-status">
    Built version loaded.
    <span style="margin-left: 12px;">Click here for full view</span>
    <a href="fms_builder.php?fullview=1&view=<?php echo urlencode($requested_view); ?>" target="_blank" title="Open full view" style="margin-left: 6px; text-decoration: none;">&#8599;</a>
</p>

<?php else: ?>
<!-- No build available -->
<div class="fms-builder-fallback">
    <h1>FMS Builder</h1>
    <p>The Flow Builder app is not built yet. Build it to embed directly in this page.</p>
    <ol>
        <li>Open a terminal in the project folder.</li>
        <li>Run: <code>cd fms-flow-builder</code></li>
        <li>Run: <code>npm install</code></li>
        <li>Run: <code>npm run build</code></li>
        <li>Refresh this page.</li>
    </ol>
    <p><strong>Or use the dev server:</strong></p>
    <p>Run <code>npm run dev</code> in <code>fms-flow-builder</code>, then refresh this page.</p>
    <p>Direct link: <a href="<?php echo htmlspecialchars($dev_server_url); ?>" target="_blank"><?php echo htmlspecialchars($dev_server_url); ?></a></p>
</div>
<?php endif; ?>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
