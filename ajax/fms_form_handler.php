<?php
/**
 * FMS Form Builder + Step Mapping API
 *
 * Admin-only handler for:
 * - form CRUD
 * - dynamic field CRUD (bulk save)
 * - form step mappings
 */

ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/request_debug.php';

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
debug500Bootstrap('ajax/fms_form_handler.php');

// CORS for Vite dev server
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ['http://localhost:5173', 'http://127.0.0.1:5173'];
if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Admin access required']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$userId = (int)($_SESSION['id'] ?? 0);

function ok($data = []) {
    echo json_encode(array_merge(['status' => 'ok'], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function fail($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function parseBody() {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    // Fallback for form-encoded payloads.
    if (isset($_POST['payload'])) {
        $decoded = json_decode((string)$_POST['payload'], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    if (!empty($_POST) && is_array($_POST)) {
        return $_POST;
    }

    return [];
}

function hhmmToMinutes($value) {
    if (is_int($value)) {
        return max(0, $value);
    }
    if (is_numeric($value)) {
        return max(0, (int)$value);
    }
    $str = trim((string)$value);
    if ($str === '') {
        return 0;
    }
    if (preg_match('/^(\d{1,3}):([0-5]\d)$/', $str, $m)) {
        return ((int)$m[1] * 60) + (int)$m[2];
    }
    return 0;
}

/**
 * Start/End are terminal markers, not executable steps.
 * This helper defensively excludes them even if legacy data has bad type values.
 */
function isExecutableFlowNode($node) {
    $id = (string)($node['id'] ?? '');
    $type = strtolower(trim((string)($node['type'] ?? '')));
    $data = is_array($node['data'] ?? null) ? $node['data'] : [];
    $label = strtolower(trim((string)($data['label'] ?? $data['stepName'] ?? '')));
    $stepType = strtolower(trim((string)($data['stepType'] ?? '')));

    if ($id === '__start__' || $id === '__end__') {
        return false;
    }
    if (in_array($type, ['start', 'end'], true)) {
        return false;
    }
    if (in_array($stepType, ['start', 'end'], true)) {
        return false;
    }
    if (in_array($label, ['start', 'end', 'flow start', 'flow end'], true)) {
        return false;
    }
    return true;
}

/**
 * Build a deterministic step order using start-node traversal first,
 * then append any remaining executable nodes.
 */
function buildExecutableSteps($conn, $flowId) {
    $flowId = (int)$flowId;
    $nodesRes = mysqli_query(
        $conn,
        "SELECT id, type, data FROM fms_flow_nodes WHERE flow_id = {$flowId} ORDER BY sort_order ASC"
    );
    if (!$nodesRes) {
        return [];
    }

    $nodes = [];
    while ($row = mysqli_fetch_assoc($nodesRes)) {
        $data = json_decode($row['data'] ?? '{}', true);
        if (!is_array($data)) {
            $data = [];
        }
        $nodes[$row['id']] = [
            'id' => $row['id'],
            'type' => $row['type'],
            'data' => $data
        ];
    }

    $edgesRes = mysqli_query(
        $conn,
        "SELECT source_node_id, target_node_id FROM fms_flow_edges WHERE flow_id = {$flowId}"
    );
    $out = [];
    if ($edgesRes) {
        while ($row = mysqli_fetch_assoc($edgesRes)) {
            $src = $row['source_node_id'];
            $tgt = $row['target_node_id'];
            if (!isset($out[$src])) {
                $out[$src] = [];
            }
            $out[$src][] = $tgt;
        }
    }

    $startId = '__start__';
    $queue = [$startId];
    $seen = [];
    $ordered = [];

    while (!empty($queue)) {
        $curr = array_shift($queue);
        if (isset($seen[$curr])) {
            continue;
        }
        $seen[$curr] = true;

        if (isset($nodes[$curr])) {
            $node = $nodes[$curr];
            if (isExecutableFlowNode($node)) {
                $ordered[] = $node;
            }
        }

        foreach ($out[$curr] ?? [] as $nextId) {
            if (!isset($seen[$nextId])) {
                $queue[] = $nextId;
            }
        }
    }

    foreach ($nodes as $id => $node) {
        if (!isExecutableFlowNode($node)) {
            continue;
        }
        $exists = false;
        foreach ($ordered as $o) {
            if ($o['id'] === $id) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $ordered[] = $node;
        }
    }

    $steps = [];
    foreach ($ordered as $idx => $node) {
        $d = $node['data'];
        $durationHours = (float)($d['timeRules']['plannedDuration'] ?? 0);
        $durationMin = (int)round(max(0, $durationHours) * 60);
        $steps[] = [
            'node_id' => $node['id'],
            'step_name' => $d['stepName'] ?? $node['id'],
            'step_code' => $d['stepCode'] ?? null,
            'suggested_doer_id' => (int)($d['defaultDoerId'] ?? $d['doerId'] ?? $d['ownerId'] ?? 0),
            'duration_minutes' => $durationMin,
            'sort_order' => $idx + 1
        ];
    }
    return $steps;
}

switch ($action) {
    case 'list_users': {
        $sql = "SELECT id, name, user_type FROM users WHERE user_type IN ('admin','manager','doer') ORDER BY name ASC";
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            fail('Failed to list users', 500);
        }
        $users = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $users[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'user_type' => $row['user_type'],
            ];
        }
        ok(['users' => $users]);
        break;
    }

    case 'list_forms': {
        $flowId = (int)($_GET['flow_id'] ?? 0);
        $where = $flowId > 0 ? "WHERE ff.flow_id = {$flowId}" : '';
        $sql = "SELECT ff.*, f.name AS flow_name, u.name AS created_by_name
                FROM fms_flow_forms ff
                LEFT JOIN fms_flows f ON f.id = ff.flow_id
                LEFT JOIN users u ON u.id = ff.created_by
                {$where}
                ORDER BY ff.updated_at DESC";
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            fail('Failed to list forms', 500);
        }
        $forms = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $forms[] = [
                'id' => (int)$row['id'],
                'flow_id' => (int)$row['flow_id'],
                'flow_name' => $row['flow_name'] ?? '',
                'name' => $row['name'],
                'description' => $row['description'],
                'status' => $row['status'],
                'created_by' => (int)$row['created_by'],
                'created_by_name' => $row['created_by_name'] ?? '',
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }
        ok(['forms' => $forms]);
        break;
    }

    case 'get_form': {
        $formId = (int)($_GET['id'] ?? 0);
        if ($formId <= 0) {
            fail('Invalid form ID');
        }

        $stmt = $conn->prepare("SELECT * FROM fms_flow_forms WHERE id = ?");
        $stmt->bind_param("i", $formId);
        $stmt->execute();
        $form = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$form) {
            fail('Form not found', 404);
        }

        $fields = [];
        $stmt = $conn->prepare("SELECT * FROM fms_flow_form_fields WHERE form_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->bind_param("i", $formId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $fields[] = [
                'id' => (int)$row['id'],
                'form_id' => (int)$row['form_id'],
                'field_key' => $row['field_key'],
                'field_label' => $row['field_label'],
                'field_type' => $row['field_type'],
                'options' => $row['options'] ? (json_decode($row['options'], true) ?: []) : [],
                'is_required' => (bool)$row['is_required'],
                'default_value' => $row['default_value'],
                'placeholder' => $row['placeholder'],
                'sort_order' => (int)$row['sort_order'],
            ];
        }
        $stmt->close();

        $mappings = [];
        $stmt = $conn->prepare("SELECT m.*, u.name AS doer_name FROM fms_flow_form_step_map m LEFT JOIN users u ON u.id = m.doer_id WHERE m.form_id = ? ORDER BY m.sort_order ASC, m.id ASC");
        $stmt->bind_param("i", $formId);
        $stmt->execute();
        $res = $stmt->get_result();
        $steps = buildExecutableSteps($conn, (int)$form['flow_id']);
        $allowedNodeIds = [];
        foreach ($steps as $s) {
            $allowedNodeIds[$s['node_id']] = true;
        }
        while ($row = $res->fetch_assoc()) {
            if (!isset($allowedNodeIds[$row['node_id']])) {
                continue;
            }
            $mappings[] = [
                'id' => (int)$row['id'],
                'form_id' => (int)$row['form_id'],
                'node_id' => $row['node_id'],
                'doer_id' => (int)$row['doer_id'],
                'doer_name' => $row['doer_name'] ?? '',
                'duration_minutes' => (int)$row['duration_minutes'],
                'duration_hhmm' => sprintf('%02d:%02d', floor(((int)$row['duration_minutes']) / 60), ((int)$row['duration_minutes']) % 60),
                'sort_order' => (int)$row['sort_order'],
            ];
        }
        $stmt->close();

        ok([
            'form' => [
                'id' => (int)$form['id'],
                'flow_id' => (int)$form['flow_id'],
                'name' => $form['name'],
                'description' => $form['description'],
                'status' => $form['status'],
                'created_by' => (int)$form['created_by'],
                'updated_by' => (int)($form['updated_by'] ?? 0),
                'created_at' => $form['created_at'],
                'updated_at' => $form['updated_at'],
            ],
            'fields' => $fields,
            'step_map' => $mappings,
            'flow_steps' => $steps,
        ]);
        break;
    }

    case 'create_form': {
        $body = parseBody();
        $flowId = (int)($body['flow_id'] ?? 0);
        $name = trim((string)($body['name'] ?? 'Untitled Form'));
        $description = trim((string)($body['description'] ?? ''));
        $status = $body['status'] ?? 'draft';
        if (!in_array($status, ['draft', 'active', 'inactive'], true)) {
            $status = 'draft';
        }
        if ($flowId <= 0) {
            fail('Invalid flow ID');
        }

        $stmt = $conn->prepare("INSERT INTO fms_flow_forms (flow_id, name, description, status, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssii", $flowId, $name, $description, $status, $userId, $userId);
        if (!$stmt->execute()) {
            $stmt->close();
            fail('Failed to create form', 500);
        }
        $formId = (int)$conn->insert_id;
        $stmt->close();
        ok(['form_id' => $formId]);
        break;
    }

    case 'update_form': {
        $body = parseBody();
        $formId = (int)($body['id'] ?? $body['form_id'] ?? $body['formId'] ?? 0);
        if ($formId <= 0) {
            fail('Invalid form ID');
        }
        $name = trim((string)($body['name'] ?? 'Untitled Form'));
        $description = trim((string)($body['description'] ?? ''));
        $status = $body['status'] ?? 'draft';
        if (!in_array($status, ['draft', 'active', 'inactive'], true)) {
            $status = 'draft';
        }
        $stmt = $conn->prepare("UPDATE fms_flow_forms SET name = ?, description = ?, status = ?, updated_by = ? WHERE id = ?");
        $stmt->bind_param("sssii", $name, $description, $status, $userId, $formId);
        if (!$stmt->execute()) {
            $stmt->close();
            fail('Failed to update form', 500);
        }
        $stmt->close();
        ok(['updated' => true]);
        break;
    }

    case 'delete_form': {
        $formId = (int)($_GET['id'] ?? 0);
        if ($formId <= 0) {
            $body = parseBody();
            $formId = (int)($body['id'] ?? 0);
        }
        if ($formId <= 0) {
            fail('Invalid form ID');
        }
        $stmt = $conn->prepare("DELETE FROM fms_flow_forms WHERE id = ?");
        $stmt->bind_param("i", $formId);
        if (!$stmt->execute()) {
            $stmt->close();
            fail('Failed to delete form', 500);
        }
        $stmt->close();
        ok(['deleted' => $formId]);
        break;
    }

    case 'save_fields': {
        $body = parseBody();
        $formId = (int)($body['form_id'] ?? $body['formId'] ?? $body['id'] ?? 0);
        $fields = $body['fields'] ?? ($body['form_fields'] ?? []);
        if (is_string($fields)) {
            $decodedFields = json_decode($fields, true);
            if (is_array($decodedFields)) {
                $fields = $decodedFields;
            }
        }
        if ($formId <= 0 || !is_array($fields)) {
            fail('Invalid payload');
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("DELETE FROM fms_flow_form_fields WHERE form_id = ?");
            $stmt->bind_param("i", $formId);
            $stmt->execute();
            $stmt->close();

            $insert = $conn->prepare(
                "INSERT INTO fms_flow_form_fields (form_id, field_key, field_label, field_type, options, is_required, default_value, placeholder, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            foreach ($fields as $idx => $f) {
                $fieldKey = trim((string)($f['field_key'] ?? 'field_' . ($idx + 1)));
                $fieldLabel = trim((string)($f['field_label'] ?? $fieldKey));
                $fieldType = (string)($f['field_type'] ?? 'text');
                if (!in_array($fieldType, ['text', 'textarea', 'number', 'date', 'select', 'multiselect', 'file', 'checkbox'], true)) {
                    $fieldType = 'text';
                }
                $options = $f['options'] ?? null;
                $optionsJson = is_array($options) ? json_encode(array_values($options), JSON_UNESCAPED_UNICODE) : null;
                $isRequired = !empty($f['is_required']) ? 1 : 0;
                $defaultValue = isset($f['default_value']) ? (string)$f['default_value'] : null;
                $placeholder = isset($f['placeholder']) ? (string)$f['placeholder'] : null;
                $sortOrder = (int)($f['sort_order'] ?? ($idx + 1));
                $insert->bind_param("issssissi", $formId, $fieldKey, $fieldLabel, $fieldType, $optionsJson, $isRequired, $defaultValue, $placeholder, $sortOrder);
                $insert->execute();
            }
            $insert->close();

            $upd = $conn->prepare("UPDATE fms_flow_forms SET updated_by = ? WHERE id = ?");
            $upd->bind_param("ii", $userId, $formId);
            $upd->execute();
            $upd->close();

            $conn->commit();
            ok(['saved' => true]);
        } catch (Throwable $e) {
            $conn->rollback();
            fail('Failed to save fields: ' . $e->getMessage(), 500);
        }
        break;
    }

    case 'get_flow_steps': {
        $flowId = (int)($_GET['flow_id'] ?? 0);
        if ($flowId <= 0) {
            fail('Invalid flow ID');
        }
        ok(['steps' => buildExecutableSteps($conn, $flowId)]);
        break;
    }

    case 'save_step_map': {
        if (function_exists('ensureFmsFlowExecutionSchema')) {
            ensureFmsFlowExecutionSchema($conn);
        }
        if (
            !columnExists($conn, 'fms_flow_form_step_map', 'form_id') ||
            !columnExists($conn, 'fms_flow_form_step_map', 'node_id') ||
            !columnExists($conn, 'fms_flow_form_step_map', 'doer_id') ||
            !columnExists($conn, 'fms_flow_form_step_map', 'duration_minutes') ||
            !columnExists($conn, 'fms_flow_form_step_map', 'sort_order')
        ) {
            fail('Flow step map table is outdated. Please reload once to run DB migration.', 500);
        }
        $body = parseBody();
        $formId = (int)($body['form_id'] ?? $body['formId'] ?? $body['id'] ?? $_POST['form_id'] ?? $_POST['formId'] ?? $_POST['id'] ?? 0);
        $mappings = $body['mappings'] ?? ($body['step_map'] ?? ($body['stepMap'] ?? ($_POST['mappings'] ?? ($_POST['step_map'] ?? $_POST['stepMap'] ?? []))));
        if (is_string($mappings)) {
            $decodedMappings = json_decode($mappings, true);
            if (is_array($decodedMappings)) {
                $mappings = $decodedMappings;
            }
        }
        // If an associative object is received, normalize to indexed array.
        if (is_array($mappings) && array_keys($mappings) !== range(0, count($mappings) - 1)) {
            $mappings = array_values($mappings);
        }
        if ($formId <= 0 || !is_array($mappings)) {
            if (function_exists('dbLog')) {
                dbLog("save_step_map invalid payload: formId={$formId}; bodyKeys=" . implode(',', array_keys(is_array($body) ? $body : [])));
            }
            fail('Invalid payload');
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("DELETE FROM fms_flow_form_step_map WHERE form_id = ?");
            $stmt->bind_param("i", $formId);
            $stmt->execute();
            $stmt->close();

            $insert = $conn->prepare(
                "INSERT INTO fms_flow_form_step_map (form_id, node_id, doer_id, duration_minutes, sort_order)
                 VALUES (?, ?, ?, ?, ?)"
            );
            foreach ($mappings as $idx => $map) {
                $nodeId = trim((string)($map['node_id'] ?? ''));
                $doerId = (int)($map['doer_id'] ?? 0);
                $duration = hhmmToMinutes($map['duration_hhmm'] ?? $map['duration_minutes'] ?? 0);
                $sortOrder = (int)($map['sort_order'] ?? ($idx + 1));
                if ($nodeId === '' || $doerId <= 0) {
                    continue;
                }
                $insert->bind_param("isiii", $formId, $nodeId, $doerId, $duration, $sortOrder);
                $insert->execute();
            }
            $insert->close();

            $upd = $conn->prepare("UPDATE fms_flow_forms SET updated_by = ? WHERE id = ?");
            $upd->bind_param("ii", $userId, $formId);
            $upd->execute();
            $upd->close();

            $conn->commit();
            ok(['saved' => true]);
        } catch (Throwable $e) {
            $conn->rollback();
            fail('Failed to save step map: ' . $e->getMessage(), 500);
        }
        break;
    }

    default:
        fail('Unknown action: ' . htmlspecialchars($action));
}

