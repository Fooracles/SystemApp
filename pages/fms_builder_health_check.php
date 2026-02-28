<?php
$page_title = "FMS Builder Health Check";
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

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$checks = [];

function addCheck(&$checks, $name, $ok, $detail) {
    $checks[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
}

function tableExistsSafe($conn, $table) {
    $res = mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $table) . "'");
    return $res && mysqli_num_rows($res) > 0;
}

function columnExistsSafe($conn, $table, $column) {
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '" . mysqli_real_escape_string($conn, $column) . "'");
    return $res && mysqli_num_rows($res) > 0;
}

$requiredTables = [
    'fms_flow_forms',
    'fms_flow_form_fields',
    'fms_flow_form_step_map',
    'fms_flow_runs',
    'fms_flow_run_steps',
];
foreach ($requiredTables as $t) {
    $ok = tableExistsSafe($conn, $t);
    addCheck($checks, "Table exists: $t", $ok, $ok ? 'OK' : 'Missing');
}

$requiredStepMapCols = ['id', 'form_id', 'node_id', 'doer_id', 'duration_minutes', 'sort_order', 'created_at', 'updated_at'];
if (tableExistsSafe($conn, 'fms_flow_form_step_map')) {
    foreach ($requiredStepMapCols as $c) {
        $ok = columnExistsSafe($conn, 'fms_flow_form_step_map', $c);
        addCheck($checks, "Column exists: fms_flow_form_step_map.$c", $ok, $ok ? 'OK' : 'Missing');
    }
}

$sampleFormId = 0;
$sampleFlowId = 0;
$sampleNodeId = '';
$sampleDoerId = 0;

$r = mysqli_query($conn, "SELECT id, flow_id FROM fms_flow_forms ORDER BY id ASC LIMIT 1");
if ($r && ($row = mysqli_fetch_assoc($r))) {
    $sampleFormId = (int)$row['id'];
    $sampleFlowId = (int)$row['flow_id'];
}
addCheck($checks, 'Sample form available', $sampleFormId > 0, $sampleFormId > 0 ? "form_id=$sampleFormId" : 'No form found');

if ($sampleFlowId > 0) {
    $nodeSql = "SELECT id FROM fms_flow_nodes WHERE flow_id = $sampleFlowId AND id NOT IN ('__start__','__end__') ORDER BY sort_order ASC LIMIT 1";
    $rn = mysqli_query($conn, $nodeSql);
    if ($rn && ($n = mysqli_fetch_assoc($rn))) {
        $sampleNodeId = $n['id'];
    }
}
addCheck($checks, 'Sample executable node available', $sampleNodeId !== '', $sampleNodeId !== '' ? $sampleNodeId : 'No step node found');

$ru = mysqli_query($conn, "SELECT id FROM users WHERE user_type IN ('admin','manager','doer') ORDER BY id ASC LIMIT 1");
if ($ru && ($u = mysqli_fetch_assoc($ru))) {
    $sampleDoerId = (int)$u['id'];
}
addCheck($checks, 'Sample assignable user available', $sampleDoerId > 0, $sampleDoerId > 0 ? "user_id=$sampleDoerId" : 'No admin/manager/doer found');

// Insert-path test inside transaction (rollback always).
if ($sampleFormId > 0 && $sampleNodeId !== '' && $sampleDoerId > 0 && tableExistsSafe($conn, 'fms_flow_form_step_map')) {
    mysqli_begin_transaction($conn);
    $okInsert = true;
    $err = '';
    try {
        $stmt = mysqli_prepare($conn, "INSERT INTO fms_flow_form_step_map (form_id, node_id, doer_id, duration_minutes, sort_order) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . mysqli_error($conn));
        }
        $duration = 60;
        $sortOrder = 9999;
        mysqli_stmt_bind_param($stmt, "isiii", $sampleFormId, $sampleNodeId, $sampleDoerId, $duration, $sortOrder);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Execute failed: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } catch (Throwable $e) {
        $okInsert = false;
        $err = $e->getMessage();
    }
    mysqli_rollback($conn);
    addCheck($checks, 'Step-map insert path test (rollback)', $okInsert, $okInsert ? 'OK' : $err);
}

$allOk = true;
foreach ($checks as $c) {
    if (!$c['ok']) {
        $allOk = false;
        break;
    }
}

require_once __DIR__ . "/../includes/header.php";
?>
<style>
.fms-health { padding: 20px; color: #f4f4f5; background: #0f0f14; min-height: calc(100vh - 70px); }
.fms-health h1 { margin: 0 0 12px; font-size: 22px; }
.fms-health .summary { margin-bottom: 14px; font-size: 14px; color: <?php echo $allOk ? '#4ade80' : '#f87171'; ?>; }
.fms-health table { width: 100%; border-collapse: collapse; background: #18181b; border: 1px solid #2a2a35; border-radius: 8px; overflow: hidden; }
.fms-health th, .fms-health td { padding: 10px 12px; border-bottom: 1px solid #2a2a35; font-size: 13px; text-align: left; }
.fms-health th { color: #a1a1aa; background: #16161d; }
.ok { color: #4ade80; }
.bad { color: #f87171; }
.muted { color: #a1a1aa; font-size: 12px; margin-top: 10px; }
</style>
<div class="fms-health">
  <h1>FMS Builder Health Check</h1>
  <div class="summary"><?php echo $allOk ? 'All checks passed.' : 'Some checks failed. See details below.'; ?></div>
  <table>
    <thead>
      <tr><th>Check</th><th>Status</th><th>Detail</th></tr>
    </thead>
    <tbody>
      <?php foreach ($checks as $c): ?>
        <tr>
          <td><?php echo htmlspecialchars($c['name']); ?></td>
          <td class="<?php echo $c['ok'] ? 'ok' : 'bad'; ?>"><?php echo $c['ok'] ? 'PASS' : 'FAIL'; ?></td>
          <td><?php echo htmlspecialchars($c['detail']); ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="muted">Open this page after deployments/migrations to validate Form Builder mapping readiness.</div>
</div>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>

