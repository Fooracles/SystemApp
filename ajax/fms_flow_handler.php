<?php
/**
 * FMS Flow Builder — AJAX Handler
 * 
 * Provides CRUD API for the React-based FMS Flow Builder.
 * All table names use the fms_flow_* prefix to avoid conflicts.
 * 
 * Actions (via GET/POST ?action=xxx):
 *   list     — List all flows (GET)
 *   get      — Load a single flow with nodes + edges (GET, id=)
 *   save     — Create or update a flow (POST, JSON body)
 *   delete   — Delete a flow (POST, id=)
 *   duplicate— Clone a flow (POST, id=)
 *   versions — List saved versions for a flow (GET, id=)
 *   version  — Load a specific version snapshot (GET, id=, v=)
 */

// Buffer output so any stray notices/warnings from config.php
// don't corrupt the JSON response
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/request_debug.php';

// Discard any accidental output from includes (e.g. table creation notices)
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
debug500Bootstrap('ajax/fms_flow_handler.php');

// CORS — allow the Vite dev server (localhost:5173) to call this endpoint
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = ['http://localhost:5173', 'http://127.0.0.1:5173'];
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
}
// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Auth guard ──
if (!isLoggedIn()) {
    http_response_code(401);
    jsonError('Not authenticated', 401);
}
if (!isAdmin()) {
    http_response_code(403);
    jsonError('Admin access required', 400);
}

$userId = (int)($_SESSION['id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Helper: send JSON response ──
function respond($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function respondError($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Normalize node type to avoid legacy enum-corruption issues.
 * - Supports start/end even for old rows where DB stored invalid values.
 * - Uses node id/data hints when type is missing or wrong.
 */
function normalizeNodeType($nodeId, $nodeType, $nodeData = []) {
    $allowed = ['step', 'decision', 'target', 'start', 'end'];
    $type = strtolower(trim((string)$nodeType));

    $label = '';
    if (is_array($nodeData)) {
        $label = strtolower(trim((string)($nodeData['label'] ?? $nodeData['stepName'] ?? '')));
        $stepType = strtolower(trim((string)($nodeData['stepType'] ?? '')));
        if ($stepType !== '' && in_array($stepType, $allowed, true)) {
            $type = $stepType;
        }
    }

    if ($nodeId === '__start__' || $label === 'start' || $label === 'flow start') {
        return 'start';
    }
    if ($nodeId === '__end__' || $label === 'end' || $label === 'flow end') {
        return 'end';
    }

    if (!in_array($type, $allowed, true)) {
        // Safe fallback for unknown/corrupted types.
        return 'step';
    }
    return $type;
}

// ── Route ──
switch ($action) {

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  LIST ALL FLOWS
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    case 'list':
        $sql = "SELECT f.id, f.name, f.status, f.created_at, f.updated_at,
                       u.name AS created_by_name,
                       (SELECT COUNT(*) FROM fms_flow_nodes WHERE flow_id = f.id) AS nodes_count
                FROM fms_flows f
                LEFT JOIN users u ON f.created_by = u.id
                ORDER BY f.updated_at DESC";
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            respondError('Database error: ' . mysqli_error($conn), 500);
        }
        $flows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $flows[] = [
                'id'           => (int)$row['id'],
                'name'         => $row['name'],
                'status'       => $row['status'],
                'nodesCount'   => (int)$row['nodes_count'],
                'createdAt'    => $row['created_at'],
                'updatedAt'    => $row['updated_at'],
                'createdBy'    => $row['created_by_name'] ?? 'Unknown',
            ];
        }
        respond(['status' => 'ok', 'flows' => $flows]);
        break;

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  GET SINGLE FLOW
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    case 'get':
        $flowId = (int)($_GET['id'] ?? 0);
        if ($flowId <= 0) respondError('Invalid flow ID');

        // Flow meta
        $stmt = $conn->prepare("SELECT id, name, status FROM fms_flows WHERE id = ?");
        $stmt->bind_param("i", $flowId);
        $stmt->execute();
        $flowRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$flowRow) respondError('Flow not found', 404);

        // Nodes
        $stmt = $conn->prepare("SELECT id, type, position_x, position_y, data FROM fms_flow_nodes WHERE flow_id = ? ORDER BY sort_order");
        $stmt->bind_param("i", $flowId);
        $stmt->execute();
        $res = $stmt->get_result();
        $nodes = [];
        while ($row = $res->fetch_assoc()) {
            $nodeData = json_decode($row['data'], true);
            $normalizedType = normalizeNodeType($row['id'], $row['type'], $nodeData);
            $nodes[] = [
                'id'       => $row['id'],
                'type'     => $normalizedType,
                'position' => ['x' => (float)$row['position_x'], 'y' => (float)$row['position_y']],
                'data'     => $nodeData,
            ];
        }
        $stmt->close();

        // Edges
        $stmt = $conn->prepare("SELECT id, source_node_id, target_node_id, condition_type FROM fms_flow_edges WHERE flow_id = ?");
        $stmt->bind_param("i", $flowId);
        $stmt->execute();
        $res = $stmt->get_result();
        $edges = [];
        while ($row = $res->fetch_assoc()) {
            $edges[] = [
                'id'        => $row['id'],
                'source'    => $row['source_node_id'],
                'target'    => $row['target_node_id'],
                'condition' => $row['condition_type'],
            ];
        }
        $stmt->close();

        respond([
            'status' => 'ok',
            'flow'   => ['id' => (string)$flowRow['id'], 'name' => $flowRow['name'], 'status' => $flowRow['status']],
            'nodes'  => $nodes,
            'edges'  => $edges,
        ]);
        break;

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  SAVE FLOW (create or update)
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    case 'save':
        $rawBody = file_get_contents('php://input');
        $input = json_decode($rawBody, true);

        // Fallback: accept form-encoded payload for environments
        // where JSON body may be stripped/misrouted.
        if (!is_array($input) || !isset($input['flow'])) {
            if (isset($_POST['payload'])) {
                $decoded = json_decode((string)$_POST['payload'], true);
                if (is_array($decoded)) {
                    $input = $decoded;
                }
            } elseif (isset($_POST['flow'])) {
                $postFlow = $_POST['flow'];
                if (is_string($postFlow)) {
                    $postFlow = json_decode($postFlow, true);
                }
                $postNodes = $_POST['nodes'] ?? [];
                if (is_string($postNodes)) {
                    $postNodes = json_decode($postNodes, true);
                }
                $postEdges = $_POST['edges'] ?? [];
                if (is_string($postEdges)) {
                    $postEdges = json_decode($postEdges, true);
                }
                if (is_array($postFlow)) {
                    $input = [
                        'flow' => $postFlow,
                        'nodes' => is_array($postNodes) ? $postNodes : [],
                        'edges' => is_array($postEdges) ? $postEdges : [],
                    ];
                }
            }
        }

        if (!is_array($input) || !isset($input['flow']) || !is_array($input['flow'])) {
            respondError('Invalid request body');
        }

        $flowMeta = $input['flow'];
        $nodes    = $input['nodes'] ?? [];
        $edges    = $input['edges'] ?? [];
        $flowName = trim($flowMeta['name'] ?? 'Untitled Flow');
        $flowStatus = $flowMeta['status'] ?? 'draft';

        // Validate status
        if (!in_array($flowStatus, ['draft', 'active', 'inactive'])) {
            $flowStatus = 'draft';
        }

        $conn->begin_transaction();
        try {
            $flowId = (int)($flowMeta['id'] ?? 0);

            if ($flowId > 0) {
                // Check flow exists
                $check = $conn->prepare("SELECT id FROM fms_flows WHERE id = ?");
                $check->bind_param("i", $flowId);
                $check->execute();
                if ($check->get_result()->num_rows === 0) {
                    $check->close();
                    // ID provided but doesn't exist — create new
                    $flowId = 0;
                }
                $check->close();
            }

            if ($flowId > 0) {
                // UPDATE
                $stmt = $conn->prepare("UPDATE fms_flows SET name = ?, status = ?, updated_by = ? WHERE id = ?");
                $stmt->bind_param("ssii", $flowName, $flowStatus, $userId, $flowId);
                $stmt->execute();
                $stmt->close();
            } else {
                // INSERT
                $stmt = $conn->prepare("INSERT INTO fms_flows (name, status, created_by, updated_by) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssii", $flowName, $flowStatus, $userId, $userId);
                $stmt->execute();
                $flowId = (int)$conn->insert_id;
                $stmt->close();
            }

            // Delete existing nodes & edges for this flow (will be re-inserted)
            $conn->query("DELETE FROM fms_flow_edges WHERE flow_id = " . (int)$flowId);
            $conn->query("DELETE FROM fms_flow_nodes WHERE flow_id = " . (int)$flowId);

            // Insert nodes
            if (!empty($nodes)) {
                $stmtNode = $conn->prepare(
                    "INSERT INTO fms_flow_nodes (id, flow_id, type, position_x, position_y, data, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                foreach ($nodes as $i => $node) {
                    $nId   = (string)($node['id'] ?? '');
                    $nDataArr = is_array($node['data'] ?? null) ? $node['data'] : [];
                    $nType = normalizeNodeType($nId, $node['type'] ?? '', $nDataArr);
                    $nPosX = (float)($node['position']['x'] ?? 100);
                    $nPosY = (float)($node['position']['y'] ?? 100);
                    $nData = json_encode(!empty($nDataArr) ? $nDataArr : new \stdClass(), JSON_UNESCAPED_UNICODE);
                    $nOrder = $i;
                    $stmtNode->bind_param("sisddsi", $nId, $flowId, $nType, $nPosX, $nPosY, $nData, $nOrder);
                    $stmtNode->execute();
                }
                $stmtNode->close();
            }

            // Insert edges
            if (!empty($edges)) {
                $stmtEdge = $conn->prepare(
                    "INSERT INTO fms_flow_edges (id, flow_id, source_node_id, target_node_id, condition_type) VALUES (?, ?, ?, ?, ?)"
                );
                foreach ($edges as $edge) {
                    $eId     = $edge['id'];
                    $eSource = $edge['source'];
                    $eTarget = $edge['target'];
                    $eCond   = $edge['condition'] ?? 'default';
                    if (!in_array($eCond, ['default', 'yes', 'no'])) $eCond = 'default';
                    $stmtEdge->bind_param("sisss", $eId, $flowId, $eSource, $eTarget, $eCond);
                    $stmtEdge->execute();
                }
                $stmtEdge->close();
            }

            // Save version snapshot
            $snapshot = json_encode([
                'flow'  => ['id' => (string)$flowId, 'name' => $flowName, 'status' => $flowStatus],
                'nodes' => $nodes,
                'edges' => $edges,
            ], JSON_UNESCAPED_UNICODE);

            // Get next version number
            $verStmt = $conn->prepare("SELECT COALESCE(MAX(version), 0) + 1 AS next_ver FROM fms_flow_versions WHERE flow_id = ?");
            $verStmt->bind_param("i", $flowId);
            $verStmt->execute();
            $nextVer = (int)$verStmt->get_result()->fetch_assoc()['next_ver'];
            $verStmt->close();

            $stmtVer = $conn->prepare("INSERT INTO fms_flow_versions (flow_id, version, snapshot, saved_by) VALUES (?, ?, ?, ?)");
            $stmtVer->bind_param("iisi", $flowId, $nextVer, $snapshot, $userId);
            $stmtVer->execute();
            $stmtVer->close();

            $conn->commit();
            respond(['status' => 'ok', 'flow_id' => $flowId, 'version' => $nextVer]);

        } catch (Exception $e) {
            $conn->rollback();
            respondError('Save failed: ' . $e->getMessage(), 500);
        }
        break;

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  DELETE FLOW
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    case 'delete':
        $flowId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($flowId <= 0) respondError('Invalid flow ID');

        $stmt = $conn->prepare("DELETE FROM fms_flows WHERE id = ?");
        $stmt->bind_param("i", $flowId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) respondError('Flow not found', 404);
        respond(['status' => 'ok', 'deleted' => $flowId]);
        break;

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  DUPLICATE FLOW
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    case 'duplicate':
        $flowId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($flowId <= 0) respondError('Invalid flow ID');

        // Fetch original
        $stmt = $conn->prepare("SELECT name FROM fms_flows WHERE id = ?");
        $stmt->bind_param("i", $flowId);
        $stmt->execute();
        $orig = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$orig) respondError('Flow not found', 404);

        $conn->begin_transaction();
        try {
            // Create new flow
            $newName = $orig['name'] . ' (Copy)';
            $draftStatus = 'draft';
            $stmt = $conn->prepare("INSERT INTO fms_flows (name, status, created_by, updated_by) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssii", $newName, $draftStatus, $userId, $userId);
            $stmt->execute();
            $newFlowId = (int)$conn->insert_id;
            $stmt->close();

            // Copy nodes (generate new IDs)
            $stmt = $conn->prepare("SELECT id, type, position_x, position_y, data, sort_order FROM fms_flow_nodes WHERE flow_id = ? ORDER BY sort_order");
            $stmt->bind_param("i", $flowId);
            $stmt->execute();
            $res = $stmt->get_result();
            $idMap = []; // old_id => new_id
            $insertNode = $conn->prepare("INSERT INTO fms_flow_nodes (id, flow_id, type, position_x, position_y, data, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            while ($row = $res->fetch_assoc()) {
                $newNodeId = 'node_' . time() . '_' . substr(md5(random_bytes(8)), 0, 7);
                $idMap[$row['id']] = $newNodeId;
                $decoded = json_decode($row['data'] ?? '{}', true);
                if (!is_array($decoded)) {
                    $decoded = [];
                }
                $normType = normalizeNodeType($row['id'], $row['type'], $decoded);
                $insertNode->bind_param("sisddsi", $newNodeId, $newFlowId, $normType, $row['position_x'], $row['position_y'], $row['data'], $row['sort_order']);
                $insertNode->execute();
                usleep(1000); // tiny delay to ensure unique time-based IDs
            }
            $stmt->close();
            $insertNode->close();

            // Copy edges with remapped node IDs
            $stmt = $conn->prepare("SELECT id, source_node_id, target_node_id, condition_type FROM fms_flow_edges WHERE flow_id = ?");
            $stmt->bind_param("i", $flowId);
            $stmt->execute();
            $res = $stmt->get_result();
            $insertEdge = $conn->prepare("INSERT INTO fms_flow_edges (id, flow_id, source_node_id, target_node_id, condition_type) VALUES (?, ?, ?, ?, ?)");
            while ($row = $res->fetch_assoc()) {
                $newEdgeId = 'edge_' . time() . '_' . substr(md5(random_bytes(8)), 0, 7);
                $newSource = $idMap[$row['source_node_id']] ?? $row['source_node_id'];
                $newTarget = $idMap[$row['target_node_id']] ?? $row['target_node_id'];
                $insertEdge->bind_param("sisss", $newEdgeId, $newFlowId, $newSource, $newTarget, $row['condition_type']);
                $insertEdge->execute();
                usleep(1000);
            }
            $stmt->close();
            $insertEdge->close();

            $conn->commit();
            respond(['status' => 'ok', 'flow_id' => $newFlowId, 'name' => $newName]);

        } catch (Exception $e) {
            $conn->rollback();
            respondError('Duplicate failed: ' . $e->getMessage(), 500);
        }
        break;

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  LIST VERSIONS
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    case 'versions':
        $flowId = (int)($_GET['id'] ?? 0);
        if ($flowId <= 0) respondError('Invalid flow ID');

        $stmt = $conn->prepare("SELECT v.id, v.version, v.created_at, u.name AS saved_by_name
                                FROM fms_flow_versions v
                                LEFT JOIN users u ON v.saved_by = u.id
                                WHERE v.flow_id = ?
                                ORDER BY v.version DESC
                                LIMIT 50");
        $stmt->bind_param("i", $flowId);
        $stmt->execute();
        $res = $stmt->get_result();
        $versions = [];
        while ($row = $res->fetch_assoc()) {
            $versions[] = [
                'id'        => (int)$row['id'],
                'version'   => (int)$row['version'],
                'savedAt'   => $row['created_at'],
                'savedBy'   => $row['saved_by_name'] ?? 'Unknown',
            ];
        }
        $stmt->close();
        respond(['status' => 'ok', 'versions' => $versions]);
        break;

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  LOAD SPECIFIC VERSION
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    case 'version':
        $flowId  = (int)($_GET['id'] ?? 0);
        $version = (int)($_GET['v'] ?? 0);
        if ($flowId <= 0 || $version <= 0) respondError('Invalid flow ID or version');

        $stmt = $conn->prepare("SELECT snapshot FROM fms_flow_versions WHERE flow_id = ? AND version = ?");
        $stmt->bind_param("ii", $flowId, $version);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) respondError('Version not found', 404);

        $snapshot = json_decode($row['snapshot'], true);
        respond(['status' => 'ok', 'version' => $version, 'flow' => $snapshot['flow'] ?? null, 'nodes' => $snapshot['nodes'] ?? [], 'edges' => $snapshot['edges'] ?? []]);
        break;

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  UNKNOWN ACTION
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    default:
        respondError('Unknown action: ' . htmlspecialchars($action));
        break;
}
