import { NODE_TYPES, EDGE_CONDITIONS, START_NODE_ID, END_NODE_ID } from '../data/flowModel';

/**
 * UI-only flow validation (design-time). Returns list of error messages.
 * No backend calls.
 *
 * Rules:
 * - Must have Start and End nodes
 * - Start must have at least one outgoing connection
 * - End must have at least one incoming connection
 * - No orphan step nodes
 * - Decision nodes must have both Yes and No outgoing paths
 * - No circular paths
 *
 * @param {{ nodes: Array<{ id: string, type: string }>, edges: Array<{ source: string, target: string, condition?: string }> }} flow
 * @returns {{ valid: boolean, errors: string[] }}
 */
export function validateFlow(flow) {
  const { nodes, edges } = flow;
  const errors = [];
  const nodeIds = new Set(nodes.map((n) => n.id));

  // Filter out start/end for step-count checks
  const stepNodes = nodes.filter((n) => n.type !== 'start' && n.type !== 'end');

  if (stepNodes.length === 0) {
    errors.push('Flow has no steps. Add at least one step between Start and End.');
    return { valid: false, errors };
  }

  const hasStart = nodes.some((n) => n.id === START_NODE_ID);
  const hasEnd = nodes.some((n) => n.id === END_NODE_ID);
  if (!hasStart) errors.push('Missing Start node.');
  if (!hasEnd) errors.push('Missing End node.');

  const incoming = new Map();
  const outgoing = new Map();
  nodeIds.forEach((id) => {
    incoming.set(id, []);
    outgoing.set(id, []);
  });
  edges.forEach((e) => {
    if (nodeIds.has(e.source) && nodeIds.has(e.target)) {
      outgoing.get(e.source).push(e);
      incoming.get(e.target).push(e);
    }
  });

  // Start must connect to something
  if (hasStart && (outgoing.get(START_NODE_ID) || []).length === 0) {
    errors.push('Start node has no outgoing connections.');
  }
  // End must be connected from something
  if (hasEnd && (incoming.get(END_NODE_ID) || []).length === 0) {
    errors.push('End node has no incoming connections.');
  }

  // Orphan step nodes (exclude start/end)
  stepNodes.forEach((n) => {
    const inCount = (incoming.get(n.id) || []).length;
    const outCount = (outgoing.get(n.id) || []).length;
    if (inCount === 0 && outCount === 0) {
      errors.push(`Orphan node: "${(n.data?.stepName) || n.id}" has no connections.`);
    }
  });

  // Decision nodes: must have both Yes and No outgoing edges
  nodes
    .filter((n) => n.type === NODE_TYPES.DECISION)
    .forEach((n) => {
      const out = outgoing.get(n.id);
      const hasYes = out.some((e) => e.condition === EDGE_CONDITIONS.YES);
      const hasNo = out.some((e) => e.condition === EDGE_CONDITIONS.NO);
      if (!hasYes || !hasNo) {
        errors.push(
          `Decision node "${(n.data?.stepName) || n.id}" must have both a "Yes" and a "No" outgoing connection.`
        );
      }
    });

  // Basic cycle detection from Start node
  if (hasStart) {
    const visiting = new Set();
    const visited = new Set();
    const path = [];
    const cycleNode = findCycle(START_NODE_ID, outgoing, visiting, visited, path);
    if (cycleNode !== null) {
      errors.push('Flow contains a circular path (cycle detected).');
    }
  }

  return {
    valid: errors.length === 0,
    errors,
  };
}

function findCycle(nodeId, outgoing, visiting, visited, path) {
  if (visiting.has(nodeId)) return nodeId;
  if (visited.has(nodeId)) return null;
  visiting.add(nodeId);
  path.push(nodeId);
  for (const e of outgoing.get(nodeId) || []) {
    const found = findCycle(e.target, outgoing, visiting, visited, path);
    if (found !== null) return found;
  }
  visiting.delete(nodeId);
  visited.add(nodeId);
  path.pop();
  return null;
}
