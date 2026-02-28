import { EDGE_CONDITIONS } from '../data/flowModel';

/**
 * Convert our document nodes/edges to React Flow format.
 * - Our edges have condition (default | yes | no); React Flow edges get sourceHandle for yes/no.
 */
export function toReactFlowNodes(nodes) {
  return nodes.map((n) => {
    const isCircle = n.type === 'start' || n.type === 'end';
    return {
      id: n.id,
      type: n.type,
      data: n.data,
      position: n.position,
      draggable: true,
      // Explicit dimensions for MiniMap rendering
      width: isCircle ? 64 : 180,
      height: isCircle ? 64 : (n.type === 'decision' ? 100 : 90),
    };
  });
}

export function toReactFlowEdges(edges) {
  return edges.map((e) => {
    const isYes = e.condition === EDGE_CONDITIONS.YES;
    const isNo = e.condition === EDGE_CONDITIONS.NO;
    return {
      id: e.id,
      source: e.source,
      target: e.target,
      sourceHandle: isYes ? 'yes' : isNo ? 'no' : null,
      targetHandle: null,
      data: { condition: e.condition },
    };
  });
}

/**
 * Convert React Flow nodes back to our document format (positions updated).
 */
export function fromReactFlowNodes(rfNodes) {
  return rfNodes.map((n) => ({
    id: n.id,
    type: n.type,
    data: n.data,
    position: n.position,
  }));
}

/**
 * Convert React Flow edges back to our document format (condition from sourceHandle or data).
 */
export function fromReactFlowEdges(rfEdges) {
  return rfEdges.map((e) => {
    let condition = e.data?.condition;
    if (!condition && e.sourceHandle) condition = e.sourceHandle;
    if (!condition) condition = EDGE_CONDITIONS.DEFAULT;
    return {
      id: e.id,
      source: e.source,
      target: e.target,
      condition,
    };
  });
}
