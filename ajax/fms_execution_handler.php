<?php
/**
 * FMS Execution Engine API
 *
 * Handles:
 * - submit form -> create run + run steps
 * - run/task reads
 * - task start/complete
 * - active/history task feeds
 */

ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/request_debug.php';

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
debug500Bootstrap('ajax/fms_execution_handler.php');

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

if (function_exists('ensureFmsFlowExecutionSchema')) {
    ensureFmsFlowExecutionSchema($conn);
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
    if (!$raw) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function addMinutesSqlExpr($minutes) {
    $mins = max(0, (int)$minutes);
    return "DATE_ADD(NOW(), INTERVAL {$mins} MINUTE)";
}

function canManageRun() {
    return isAdmin() || isManager();
}

function loadRunWithSteps($conn, $runId) {
    $runId = (int)$runId;
    $stmt = $conn->prepare("SELECT r.*, f.name AS form_name, fl.name AS flow_name, u.name AS initiated_by_name
                            FROM fms_flow_runs r
                            LEFT JOIN fms_flow_forms f ON f.id = r.form_id
                            LEFT JOIN fms_flows fl ON fl.id = r.flow_id
                            LEFT JOIN users u ON u.id = r.initiated_by
                            WHERE r.id = ?");
    $stmt->bind_param("i", $runId);
    $stmt->execute();
    $run = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$run) {
        return null;
    }

    $steps = [];
    $stmt = $conn->prepare("SELECT s.*, u.name AS doer_name
                            FROM fms_flow_run_steps s
                            LEFT JOIN users u ON u.id = s.doer_id
                            WHERE s.run_id = ?
                            ORDER BY s.sort_order ASC, s.id ASC");
    $stmt->bind_param("i", $runId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $steps[] = [
            'id' => (int)$row['id'],
            'run_id' => (int)$row['run_id'],
            'node_id' => $row['node_id'],
            'step_name' => $row['step_name'],
            'step_code' => $row['step_code'],
            'doer_id' => (int)$row['doer_id'],
            'doer_name' => $row['doer_name'] ?? '',
            'status' => $row['status'],
            'duration_minutes' => (int)$row['duration_minutes'],
            'planned_at' => $row['planned_at'],
            'started_at' => $row['started_at'],
            'actual_at' => $row['actual_at'],
            'sort_order' => (int)$row['sort_order'],
            'comment' => $row['comment'],
            'attachment_path' => $row['attachment_path']
        ];
    }
    $stmt->close();

    return [
        'run' => [
            'id' => (int)$run['id'],
            'flow_id' => (int)$run['flow_id'],
            'form_id' => (int)$run['form_id'],
            'flow_name' => $run['flow_name'] ?? '',
            'form_name' => $run['form_name'] ?? '',
            'run_title' => $run['run_title'],
            'form_data' => json_decode($run['form_data'] ?? '{}', true) ?: [],
            'status' => $run['status'],
            'initiated_by' => (int)$run['initiated_by'],
            'initiated_by_name' => $run['initiated_by_name'] ?? '',
            'current_node_id' => $run['current_node_id'],
            'started_at' => $run['started_at'],
            'completed_at' => $run['completed_at']
        ],
        'steps' => $steps
    ];
}

switch ($action) {
    case 'submit': {
        if (!columnExists($conn, 'fms_flow_runs', 'form_id')) {
            fail('Execution table is outdated (missing fms_flow_runs.form_id). Reload once to run migration.', 500);
        }
        if (!columnExists($conn, 'fms_flow_run_steps', 'duration_minutes')) {
            fail('Execution table is outdated (missing fms_flow_run_steps.duration_minutes). Reload once to run migration.', 500);
        }
        $body = parseBody();
        $formId = (int)($body['form_id'] ?? 0);
        $formData = $body['form_data'] ?? [];
        $runTitle = trim((string)($body['run_title'] ?? ''));

        if ($formId <= 0 || !is_array($formData)) {
            fail('Invalid submission payload');
        }

        $stmt = $conn->prepare("SELECT id, flow_id, name, status FROM fms_flow_forms WHERE id = ?");
        $stmt->bind_param("i", $formId);
        $stmt->execute();
        $form = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$form) {
            fail('Form not found', 404);
        }
        if ($form['status'] !== 'active') {
            fail('Form is not active', 400);
        }

        // Required-field validation
        $requiredStmt = $conn->prepare("SELECT field_key, field_label, field_type, is_required FROM fms_flow_form_fields WHERE form_id = ?");
        $requiredStmt->bind_param("i", $formId);
        $requiredStmt->execute();
        $requiredRes = $requiredStmt->get_result();
        while ($row = $requiredRes->fetch_assoc()) {
            $key = $row['field_key'];
            if ((int)$row['is_required'] !== 1) {
                continue;
            }
            $v = $formData[$key] ?? null;
            $empty = ($v === null || $v === '' || (is_array($v) && count($v) === 0));
            if ($empty) {
                $requiredStmt->close();
                fail('Required field missing: ' . ($row['field_label'] ?: $key), 400);
            }
        }
        $requiredStmt->close();

        $mapStmt = $conn->prepare("SELECT * FROM fms_flow_form_step_map WHERE form_id = ? ORDER BY sort_order ASC, id ASC");
        $mapStmt->bind_param("i", $formId);
        $mapStmt->execute();
        $mapRes = $mapStmt->get_result();
        $mappings = [];
        while ($row = $mapRes->fetch_assoc()) {
            $mappings[] = $row;
        }
        $mapStmt->close();
        if (empty($mappings)) {
            fail('No step mapping found for this form', 400);
        }

        $flowId = (int)$form['flow_id'];
        if ($runTitle === '') {
            $runTitle = $form['name'] . ' #' . date('Ymd-His');
        }

        $conn->begin_transaction();
        try {
            $firstNodeId = $mappings[0]['node_id'];
            $formDataJson = json_encode($formData, JSON_UNESCAPED_UNICODE);
            $runStmt = $conn->prepare(
                "INSERT INTO fms_flow_runs (flow_id, form_id, run_title, form_data, status, initiated_by, current_node_id)
                 VALUES (?, ?, ?, ?, 'running', ?, ?)"
            );
            $runStmt->bind_param("iissis", $flowId, $formId, $runTitle, $formDataJson, $userId, $firstNodeId);
            $runStmt->execute();
            $runId = (int)$conn->insert_id;
            $runStmt->close();

            $nodeStmt = $conn->prepare("SELECT type, data FROM fms_flow_nodes WHERE flow_id = ? AND id = ?");
            $stepInsert = $conn->prepare(
                "INSERT INTO fms_flow_run_steps (run_id, node_id, step_name, step_code, doer_id, status, duration_minutes, planned_at, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            foreach ($mappings as $idx => $map) {
                $nodeId = $map['node_id'];
                $doerId = (int)$map['doer_id'];
                $duration = max(0, (int)$map['duration_minutes']);
                $sortOrder = (int)$map['sort_order'];

                $nodeStmt->bind_param("is", $flowId, $nodeId);
                $nodeStmt->execute();
                $node = $nodeStmt->get_result()->fetch_assoc();
                $data = json_decode($node['data'] ?? '{}', true);
                if (!is_array($data)) {
                    $data = [];
                }
                $stepName = (string)($data['stepName'] ?? $nodeId);
                $stepCode = isset($data['stepCode']) ? (string)$data['stepCode'] : null;

                $status = $idx === 0 ? 'pending' : 'waiting';
                $plannedAt = null;
                if ($idx === 0) {
                    $plannedAt = date('Y-m-d H:i:s', time() + ($duration * 60));
                }
                $stepInsert->bind_param(
                    "isssisssi",
                    $runId,
                    $nodeId,
                    $stepName,
                    $stepCode,
                    $doerId,
                    $status,
                    $duration,
                    $plannedAt,
                    $sortOrder
                );
                $stepInsert->execute();
            }
            $nodeStmt->close();
            $stepInsert->close();

            $conn->commit();
            ok(['run_id' => $runId]);
        } catch (Throwable $e) {
            $conn->rollback();
            fail('Failed to submit form: ' . $e->getMessage(), 500);
        }
        break;
    }

    case 'get_run': {
        $runId = (int)($_GET['id'] ?? 0);
        if ($runId <= 0) {
            fail('Invalid run ID');
        }
        $payload = loadRunWithSteps($conn, $runId);
        if (!$payload) {
            fail('Run not found', 404);
        }
        ok($payload);
        break;
    }

    case 'list_runs': {
        $flowId = (int)($_GET['flow_id'] ?? 0);
        $status = trim((string)($_GET['status'] ?? ''));
        $where = ["1=1"];
        if ($flowId > 0) {
            $where[] = "r.flow_id = {$flowId}";
        }
        if ($status !== '' && in_array($status, ['running', 'completed', 'cancelled', 'paused'], true)) {
            $where[] = "r.status = '" . mysqli_real_escape_string($conn, $status) . "'";
        }

        $sql = "SELECT r.*, f.name AS form_name, fl.name AS flow_name, u.name AS initiated_by_name
                FROM fms_flow_runs r
                LEFT JOIN fms_flow_forms f ON f.id = r.form_id
                LEFT JOIN fms_flows fl ON fl.id = r.flow_id
                LEFT JOIN users u ON u.id = r.initiated_by
                WHERE " . implode(' AND ', $where) . "
                ORDER BY r.started_at DESC
                LIMIT 300";
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            fail('Failed to list runs', 500);
        }
        $runs = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $runs[] = [
                'id' => (int)$row['id'],
                'flow_id' => (int)$row['flow_id'],
                'flow_name' => $row['flow_name'] ?? '',
                'form_id' => (int)$row['form_id'],
                'form_name' => $row['form_name'] ?? '',
                'run_title' => $row['run_title'],
                'status' => $row['status'],
                'initiated_by' => (int)$row['initiated_by'],
                'initiated_by_name' => $row['initiated_by_name'] ?? '',
                'current_node_id' => $row['current_node_id'],
                'started_at' => $row['started_at'],
                'completed_at' => $row['completed_at'],
            ];
        }
        ok(['runs' => $runs]);
        break;
    }

    case 'start_step': {
        $body = parseBody();
        $stepId = (int)($body['step_id'] ?? 0);
        if ($stepId <= 0) {
            fail('Invalid step ID');
        }

        $stmt = $conn->prepare("UPDATE fms_flow_run_steps
                                SET status = 'in_progress', started_at = COALESCE(started_at, NOW()), updated_at = NOW()
                                WHERE id = ? AND doer_id = ? AND status IN ('pending', 'in_progress')");
        $stmt->bind_param("ii", $stepId, $userId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        if ($affected <= 0) {
            fail('Step is not active or not assigned to you', 400);
        }
        ok(['started' => true]);
        break;
    }

    case 'complete_step': {
        $body = parseBody();
        $stepId = (int)($body['step_id'] ?? 0);
        $comment = trim((string)($body['comment'] ?? ''));
        $attachmentPath = trim((string)($body['attachment_path'] ?? ''));
        $decision = strtolower(trim((string)($body['decision'] ?? ''))); // yes/no/default
        if ($stepId <= 0) {
            fail('Invalid step ID');
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "SELECT s.*, r.flow_id, r.status AS run_status
                 FROM fms_flow_run_steps s
                 INNER JOIN fms_flow_runs r ON r.id = s.run_id
                 WHERE s.id = ? FOR UPDATE"
            );
            $stmt->bind_param("i", $stepId);
            $stmt->execute();
            $curr = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$curr) {
                throw new Exception('Step not found');
            }
            if ((int)$curr['doer_id'] !== $userId) {
                throw new Exception('You are not assigned to this step');
            }
            if (!in_array($curr['status'], ['pending', 'in_progress'], true)) {
                throw new Exception('Step is not active');
            }
            if ($curr['run_status'] !== 'running') {
                throw new Exception('Run is not in running state');
            }

            $nodeStmt = $conn->prepare("SELECT type, data FROM fms_flow_nodes WHERE flow_id = ? AND id = ?");
            $flowId = (int)$curr['flow_id'];
            $nodeId = $curr['node_id'];
            $nodeStmt->bind_param("is", $flowId, $nodeId);
            $nodeStmt->execute();
            $node = $nodeStmt->get_result()->fetch_assoc();
            $nodeStmt->close();
            $nodeData = json_decode($node['data'] ?? '{}', true) ?: [];
            $validationRules = $nodeData['validationRules'] ?? [];
            if (!empty($validationRules['commentRequired']) && $comment === '') {
                throw new Exception('Comment is required for this step');
            }
            if (!empty($validationRules['attachmentRequired']) && $attachmentPath === '') {
                throw new Exception('Attachment is required');
            }

            $updateCurrent = $conn->prepare(
                "UPDATE fms_flow_run_steps
                 SET status = 'completed',
                     started_at = COALESCE(started_at, NOW()),
                     actual_at = NOW(),
                     comment = ?,
                     attachment_path = ?,
                     updated_at = NOW()
                 WHERE id = ? AND status IN ('pending', 'in_progress')"
            );
            $updateCurrent->bind_param("ssi", $comment, $attachmentPath, $stepId);
            $updateCurrent->execute();
            if ($updateCurrent->affected_rows <= 0) {
                $updateCurrent->close();
                $conn->rollback();
                ok(['idempotent' => true, 'message' => 'Already completed']);
            }
            $updateCurrent->close();

            $edgesStmt = $conn->prepare("SELECT target_node_id, condition_type FROM fms_flow_edges WHERE flow_id = ? AND source_node_id = ?");
            $edgesStmt->bind_param("is", $flowId, $nodeId);
            $edgesStmt->execute();
            $edgesRes = $edgesStmt->get_result();
            $edges = [];
            while ($er = $edgesRes->fetch_assoc()) {
                $edges[] = $er;
            }
            $edgesStmt->close();

            $nextNodeId = null;
            if (count($edges) === 1) {
                $nextNodeId = $edges[0]['target_node_id'];
            } else if (count($edges) > 1) {
                $matches = [];
                foreach ($edges as $e) {
                    $cond = strtolower((string)$e['condition_type']);
                    if ($decision !== '' && $decision === $cond) {
                        $matches[] = $e;
                    }
                }
                if (count($matches) === 0 && $decision === '') {
                    foreach ($edges as $e) {
                        if (($e['condition_type'] ?? 'default') === 'default') {
                            $matches[] = $e;
                        }
                    }
                }
                if (count($matches) === 1) {
                    $nextNodeId = $matches[0]['target_node_id'];
                } else if (count($matches) === 0) {
                    $pause = $conn->prepare("UPDATE fms_flow_runs SET status = 'paused', updated_at = NOW() WHERE id = ?");
                    $pause->bind_param("i", $curr['run_id']);
                    $pause->execute();
                    $pause->close();
                    $conn->commit();
                    fail('No valid next step', 409);
                } else {
                    $pause = $conn->prepare("UPDATE fms_flow_runs SET status = 'paused', updated_at = NOW() WHERE id = ?");
                    $pause->bind_param("i", $curr['run_id']);
                    $pause->execute();
                    $pause->close();
                    $conn->commit();
                    fail('Ambiguous branch result', 409);
                }
            } else {
                $pause = $conn->prepare("UPDATE fms_flow_runs SET status = 'paused', updated_at = NOW() WHERE id = ?");
                $pause->bind_param("i", $curr['run_id']);
                $pause->execute();
                $pause->close();
                $conn->commit();
                fail('No valid next step', 409);
            }

            if (!$nextNodeId) {
                throw new Exception('Unable to resolve next step');
            }

            $nextNodeTypeStmt = $conn->prepare("SELECT type FROM fms_flow_nodes WHERE flow_id = ? AND id = ?");
            $nextNodeTypeStmt->bind_param("is", $flowId, $nextNodeId);
            $nextNodeTypeStmt->execute();
            $nextNode = $nextNodeTypeStmt->get_result()->fetch_assoc();
            $nextNodeTypeStmt->close();
            if ($nextNode && $nextNode['type'] === 'end') {
                $runDone = $conn->prepare("UPDATE fms_flow_runs SET status = 'completed', completed_at = NOW(), current_node_id = ?, updated_at = NOW() WHERE id = ?");
                $runDone->bind_param("si", $nextNodeId, $curr['run_id']);
                $runDone->execute();
                $runDone->close();
                $conn->commit();
                ok(['completed' => true, 'run_completed' => true]);
            }

            $nextStepStmt = $conn->prepare("SELECT id, duration_minutes FROM fms_flow_run_steps WHERE run_id = ? AND node_id = ? LIMIT 1");
            $nextStepStmt->bind_param("is", $curr['run_id'], $nextNodeId);
            $nextStepStmt->execute();
            $nextStep = $nextStepStmt->get_result()->fetch_assoc();
            $nextStepStmt->close();
            if (!$nextStep) {
                $pause = $conn->prepare("UPDATE fms_flow_runs SET status = 'paused', updated_at = NOW() WHERE id = ?");
                $pause->bind_param("i", $curr['run_id']);
                $pause->execute();
                $pause->close();
                $conn->commit();
                fail('Next step task not found', 409);
            }

            $activate = $conn->prepare(
                "UPDATE fms_flow_run_steps
                 SET status = 'pending',
                     planned_at = DATE_ADD(NOW(), INTERVAL duration_minutes MINUTE),
                     updated_at = NOW()
                 WHERE id = ? AND status = 'waiting'"
            );
            $activate->bind_param("i", $nextStep['id']);
            $activate->execute();
            $activate->close();

            $moveRun = $conn->prepare("UPDATE fms_flow_runs SET current_node_id = ?, updated_at = NOW() WHERE id = ?");
            $moveRun->bind_param("si", $nextNodeId, $curr['run_id']);
            $moveRun->execute();
            $moveRun->close();

            $conn->commit();
            ok(['completed' => true, 'run_completed' => false, 'next_node_id' => $nextNodeId]);
        } catch (Throwable $e) {
            $conn->rollback();
            fail('Failed to complete step: ' . $e->getMessage(), 500);
        }
        break;
    }

    case 'cancel_run': {
        if (!canManageRun()) {
            fail('Permission denied', 403);
        }
        $body = parseBody();
        $runId = (int)($body['run_id'] ?? 0);
        if ($runId <= 0) {
            fail('Invalid run ID');
        }
        $stmt = $conn->prepare("UPDATE fms_flow_runs SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND status IN ('running','paused')");
        $stmt->bind_param("i", $runId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        if ($affected <= 0) {
            fail('Run not found or not cancellable', 400);
        }
        ok(['cancelled' => true]);
        break;
    }

    case 'my_tasks': {
        $stmt = $conn->prepare(
            "SELECT s.*, r.run_title, r.status AS run_status, r.form_data
             FROM fms_flow_run_steps s
             INNER JOIN fms_flow_runs r ON r.id = s.run_id
             WHERE s.doer_id = ?
               AND s.status IN ('pending', 'in_progress')
             ORDER BY s.planned_at ASC, s.id ASC"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $tasks = [];
        while ($row = $res->fetch_assoc()) {
            $formData = json_decode($row['form_data'] ?? '{}', true);
            if (!is_array($formData)) {
                $formData = [];
            }
            $tasks[] = [
                'id' => (int)$row['id'],
                'run_id' => (int)$row['run_id'],
                'run_title' => $row['run_title'],
                'run_status' => $row['run_status'],
                'node_id' => $row['node_id'],
                'step_name' => $row['step_name'],
                'step_code' => $row['step_code'],
                'status' => $row['status'],
                'duration_minutes' => (int)$row['duration_minutes'],
                'planned_at' => $row['planned_at'],
                'started_at' => $row['started_at'],
                'actual_at' => $row['actual_at'],
                'sort_order' => (int)$row['sort_order'],
                'form_data' => $formData
            ];
        }
        $stmt->close();
        ok(['tasks' => $tasks]);
        break;
    }

    case 'my_history': {
        $stmt = $conn->prepare(
            "SELECT s.*, r.run_title, r.status AS run_status, r.form_data
             FROM fms_flow_run_steps s
             INNER JOIN fms_flow_runs r ON r.id = s.run_id
             WHERE s.doer_id = ?
               AND s.status IN ('completed', 'skipped')
             ORDER BY COALESCE(s.actual_at, s.updated_at) DESC, s.id DESC
             LIMIT 200"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $tasks = [];
        while ($row = $res->fetch_assoc()) {
            $formData = json_decode($row['form_data'] ?? '{}', true);
            if (!is_array($formData)) {
                $formData = [];
            }
            $tasks[] = [
                'id' => (int)$row['id'],
                'run_id' => (int)$row['run_id'],
                'run_title' => $row['run_title'],
                'run_status' => $row['run_status'],
                'node_id' => $row['node_id'],
                'step_name' => $row['step_name'],
                'step_code' => $row['step_code'],
                'status' => $row['status'],
                'duration_minutes' => (int)$row['duration_minutes'],
                'planned_at' => $row['planned_at'],
                'started_at' => $row['started_at'],
                'actual_at' => $row['actual_at'],
                'sort_order' => (int)$row['sort_order'],
                'comment' => $row['comment'],
                'form_data' => $formData
            ];
        }
        $stmt->close();
        ok(['tasks' => $tasks]);
        break;
    }

    default:
        fail('Unknown action: ' . htmlspecialchars($action));
}

