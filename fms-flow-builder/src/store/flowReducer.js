import {
  NODE_TYPES,
  createEmptyFlow,
  createDefaultNodeData,
  createNodeId,
  createEdgeId,
  START_NODE_ID,
  END_NODE_ID,
} from '../data/flowModel';
import { EDGE_CONDITIONS } from '../data/flowModel';

export const ACTIONS = {
  LOAD_FLOW: 'LOAD_FLOW',
  ADD_NODE: 'ADD_NODE',
  UPDATE_NODE: 'UPDATE_NODE',
  REMOVE_NODE: 'REMOVE_NODE',
  NODES_CHANGE: 'NODES_CHANGE',
  ADD_EDGE: 'ADD_EDGE',
  REMOVE_EDGE: 'REMOVE_EDGE',
  EDGES_CHANGE: 'EDGES_CHANGE',
  UPDATE_FLOW_META: 'UPDATE_FLOW_META',
  INSERT_NODE_ON_EDGE: 'INSERT_NODE_ON_EDGE',
};

/**
 * State shape:
 * {
 *   flow: { id, name, status },
 *   nodes: Array<{ id, type, data, position }>,
 *   edges: Array<{ id, source, target, condition?, sourceHandle?, targetHandle? }>
 * }
 * nodes/edges are in our document format; conversion to React Flow format happens in FlowCanvas.
 */
export function flowReducer(state, action) {
  switch (action.type) {
    case ACTIONS.LOAD_FLOW: {
      return { ...action.payload };
    }

    case ACTIONS.ADD_NODE: {
      const { stepType, position, defaults } = action.payload;
      const id = createNodeId();
      const data = createDefaultNodeData(stepType);
      // Apply user defaults from settings
      if (defaults?.plannedDuration != null) {
        data.timeRules.plannedDuration = defaults.plannedDuration;
      }

      // Find the edge that currently points to __end__
      const edgeToEnd = state.edges.find((e) => e.target === END_NODE_ID);
      if (edgeToEnd) {
        // Insert the new node between the source of that edge and End
        const sourceNode = state.nodes.find((n) => n.id === edgeToEnd.source);
        const endNode = state.nodes.find((n) => n.id === END_NODE_ID);

        // Position: midway between the source and End, shifted down
        const srcPos = sourceNode?.position || { x: 250, y: 100 };
        const endPos = endNode?.position || { x: 250, y: 400 };
        const newPos = position || {
          x: srcPos.x,
          y: srcPos.y + 140,
        };

        // Push the End node down to make room
        const updatedEndPos = { x: endPos.x, y: Math.max(endPos.y, newPos.y + 160) };

        const newNode = { id, type: stepType, data, position: newPos };

        // Remove the old edge, add source→new and new→End
        const newEdges = state.edges
          .filter((e) => e.id !== edgeToEnd.id)
          .concat([
            { id: createEdgeId(), source: edgeToEnd.source, target: id, condition: EDGE_CONDITIONS.DEFAULT },
            { id: createEdgeId(), source: id, target: END_NODE_ID, condition: EDGE_CONDITIONS.DEFAULT },
          ]);

        return {
          ...state,
          nodes: [
            ...state.nodes.map((n) =>
              n.id === END_NODE_ID ? { ...n, position: updatedEndPos } : n
            ),
            newNode,
          ],
          edges: newEdges,
        };
      }

      // Fallback: no End node edge found — just add the node
      const newNode = {
        id,
        type: stepType,
        data,
        position: position || { x: 100, y: 100 },
      };
      return {
        ...state,
        nodes: [...state.nodes, newNode],
      };
    }

    case ACTIONS.UPDATE_NODE: {
      const { nodeId, data } = action.payload;
      return {
        ...state,
        nodes: state.nodes.map((n) =>
          n.id === nodeId ? { ...n, data: { ...n.data, ...data } } : n
        ),
      };
    }

    case ACTIONS.REMOVE_NODE: {
      const { nodeId } = action.payload;
      // Prevent removing Start/End
      if (nodeId === START_NODE_ID || nodeId === END_NODE_ID) return state;

      // Find predecessors and successors to reconnect them
      const incomingEdges = state.edges.filter((e) => e.target === nodeId);
      const outgoingEdges = state.edges.filter((e) => e.source === nodeId);

      // Create bridge edges: each predecessor → each successor
      const bridgeEdges = [];
      for (const inEdge of incomingEdges) {
        for (const outEdge of outgoingEdges) {
          bridgeEdges.push({
            id: createEdgeId(),
            source: inEdge.source,
            target: outEdge.target,
            condition: inEdge.condition || EDGE_CONDITIONS.DEFAULT,
          });
        }
      }

      return {
        ...state,
        nodes: state.nodes.filter((n) => n.id !== nodeId),
        edges: [
          ...state.edges.filter((e) => e.source !== nodeId && e.target !== nodeId),
          ...bridgeEdges,
        ],
      };
    }

    case ACTIONS.NODES_CHANGE: {
      const nodes = action.payload;
      return { ...state, nodes };
    }

    case ACTIONS.ADD_EDGE: {
      const { source, target, condition } = action.payload;
      const id = createEdgeId();
      return {
        ...state,
        edges: [...state.edges, { id, source, target, condition: condition || EDGE_CONDITIONS.DEFAULT }],
      };
    }

    case ACTIONS.REMOVE_EDGE: {
      const { edgeId } = action.payload;
      return {
        ...state,
        edges: state.edges.filter((e) => e.id !== edgeId),
      };
    }

    case ACTIONS.EDGES_CHANGE: {
      const edges = action.payload;
      return { ...state, edges };
    }

    case ACTIONS.UPDATE_FLOW_META: {
      return {
        ...state,
        flow: { ...state.flow, ...action.payload },
      };
    }

    case ACTIONS.INSERT_NODE_ON_EDGE: {
      const { edgeId, position, stepType, defaults } = action.payload;
      const edge = state.edges.find((e) => e.id === edgeId);
      if (!edge) return state;

      // Create the new node to insert between source and target
      const newId = createNodeId();
      const type = stepType || NODE_TYPES.STEP;
      const data = createDefaultNodeData(type);
      if (defaults?.plannedDuration != null) {
        data.timeRules.plannedDuration = defaults.plannedDuration;
      }
      const newNode = { id: newId, type, data, position };

      // Remove the original edge and add two new edges
      const newEdges = state.edges
        .filter((e) => e.id !== edgeId)
        .concat([
          { id: createEdgeId(), source: edge.source, target: newId, condition: edge.condition || EDGE_CONDITIONS.DEFAULT },
          { id: createEdgeId(), source: newId, target: edge.target, condition: EDGE_CONDITIONS.DEFAULT },
        ]);

      return { ...state, nodes: [...state.nodes, newNode], edges: newEdges };
    }

    default:
      return state;
  }
}
