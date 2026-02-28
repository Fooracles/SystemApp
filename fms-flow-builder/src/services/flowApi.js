import { apiRequest, apiRequestWithPayloadFallback } from './apiBase';

// ── Public API ────────────────────────────────────────────

/** List all flows */
export async function listFlows() {
  const json = await apiRequest('fms_flow_handler.php', 'list');
  return json.flows; // Array
}

/** Get a single flow with nodes + edges */
export async function getFlow(flowId) {
  const json = await apiRequest('fms_flow_handler.php', 'get', { params: { id: flowId } });
  return { flow: json.flow, nodes: json.nodes, edges: json.edges };
}

/** Save (create or update) a flow. Returns { flow_id, version }. */
export async function saveFlow({ flow, nodes, edges }) {
  return apiRequestWithPayloadFallback('fms_flow_handler.php', 'save', { flow, nodes, edges });
}

/** Delete a flow */
export async function deleteFlow(flowId) {
  return apiRequest('fms_flow_handler.php', 'delete', { body: {}, params: { id: flowId } });
}

/** Duplicate a flow. Returns { flow_id, name }. */
export async function duplicateFlow(flowId) {
  return apiRequest('fms_flow_handler.php', 'duplicate', { body: {}, params: { id: flowId } });
}

/** List saved versions for a flow */
export async function listVersions(flowId) {
  const json = await apiRequest('fms_flow_handler.php', 'versions', { params: { id: flowId } });
  return json.versions;
}

/** Load a specific version snapshot */
export async function getVersion(flowId, version) {
  const json = await apiRequest('fms_flow_handler.php', 'version', { params: { id: flowId, v: version } });
  return { flow: json.flow, nodes: json.nodes, edges: json.edges };
}
