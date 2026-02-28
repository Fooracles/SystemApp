import React, { useCallback } from 'react';
import {
  ReactFlow,
  Background,
  MiniMap,
  applyNodeChanges,
  applyEdgeChanges,
  ReactFlowProvider,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import { toReactFlowNodes, toReactFlowEdges, fromReactFlowNodes, fromReactFlowEdges } from '../utils/reactFlowAdapter';
import { StepNode } from './nodes/StepNode';
import { DecisionNode } from './nodes/DecisionNode';
import { TargetNode } from './nodes/TargetNode';
import { StartNode } from './nodes/StartNode';
import { EndNode } from './nodes/EndNode';
import { ConditionEdge } from './edges/ConditionEdge';
import { CanvasControls } from './CanvasControls';
import { EDGE_CONDITIONS, START_NODE_ID, END_NODE_ID } from '../data/flowModel';

const nodeTypes = { step: StepNode, decision: DecisionNode, target: TargetNode, start: StartNode, end: EndNode };
const edgeTypes = { condition: ConditionEdge };

// MiniMap node color based on type
const getNodeColor = (node) => {
  switch (node.type) {
    case 'start': return '#6366f1';
    case 'step': return '#6366f1';
    case 'decision': return '#eab308';
    case 'target': return '#8b5cf6';
    case 'end': return '#71717a';
    default: return '#6366f1';
  }
};

function FlowCanvasInner({
  flowState,
  dispatch,
  onNodeEdit,
  onUndo,
  onRedo,
  canUndo,
  canRedo,
  onExport,
  settings = {},
}) {
  const docNodes = flowState.nodes;
  const docEdges = flowState.edges;

  // Callback for edit button on nodes
  const handleNodeEditById = useCallback(
    (nodeId) => {
      const docNode = docNodes.find((n) => n.id === nodeId);
      if (docNode) onNodeEdit(docNode);
    },
    [docNodes, onNodeEdit]
  );

  // Callback for delete button on nodes (with optional confirmation)
  const handleNodeDelete = useCallback(
    (nodeId) => {
      // Prevent deleting start/end nodes
      if (nodeId === START_NODE_ID || nodeId === END_NODE_ID) return;
      if (settings.warnBeforeDelete) {
        if (!window.confirm('Delete this node? This cannot be undone.')) return;
      }
      dispatch({ type: 'REMOVE_NODE', payload: { nodeId } });
    },
    [dispatch, settings.warnBeforeDelete]
  );

  // Inject onEdit and onDelete callbacks into node data (skip for start/end)
  const nodes = React.useMemo(
    () => toReactFlowNodes(docNodes).map((node) => {
      const isTerminal = node.type === 'start' || node.type === 'end';
      return {
        ...node,
        // Start/End nodes cannot be deleted via keyboard
        deletable: !isTerminal,
        data: {
          ...node.data,
          ...(isTerminal ? {} : { onEdit: handleNodeEditById, onDelete: handleNodeDelete }),
        },
      };
    }),
    [docNodes, handleNodeEditById, handleNodeDelete]
  );
  const edges = React.useMemo(
    () => toReactFlowEdges(docEdges).map((e) => ({
      ...e,
      type: 'condition',
      animated: !!settings.edgeAnimation,
      data: { ...e.data },
    })),
    [docEdges, settings.edgeAnimation]
  );

  const onConnect = useCallback(
    (connection) => {
      const condition =
        connection.sourceHandle === 'yes'
          ? EDGE_CONDITIONS.YES
          : connection.sourceHandle === 'no'
            ? EDGE_CONDITIONS.NO
            : EDGE_CONDITIONS.DEFAULT;
      dispatch({
        type: 'ADD_EDGE',
        payload: {
          source: connection.source,
          target: connection.target,
          condition,
        },
      });
    },
    [dispatch]
  );

  const onNodesChange = useCallback(
    (changes) => {
      // Filter out removal of Start/End nodes
      const removedIds = changes
        .filter((c) => c.type === 'remove' && c.id !== START_NODE_ID && c.id !== END_NODE_ID)
        .map((c) => c.id);
      removedIds.forEach((id) => dispatch({ type: 'REMOVE_NODE', payload: { nodeId: id } }));
      const restChanges = changes.filter((c) => c.type !== 'remove');
      if (restChanges.length === 0) return;
      const nextNodes = applyNodeChanges(restChanges, nodes);
      dispatch({ type: 'NODES_CHANGE', payload: fromReactFlowNodes(nextNodes) });
    },
    [nodes, dispatch]
  );

  const onEdgesChange = useCallback(
    (changes) => {
      const removedIds = changes.filter((c) => c.type === 'remove').map((c) => c.id);
      removedIds.forEach((id) => dispatch({ type: 'REMOVE_EDGE', payload: { edgeId: id } }));
      const restChanges = changes.filter((c) => c.type !== 'remove');
      if (restChanges.length === 0) return;
      const nextEdges = applyEdgeChanges(restChanges, edges);
      dispatch({ type: 'EDGES_CHANGE', payload: fromReactFlowEdges(nextEdges) });
    },
    [edges, dispatch]
  );

  const onNodeDoubleClick = useCallback(
    (_, node) => {
      // No edit modal for Start/End circle nodes
      if (node.type === 'start' || node.type === 'end') return;
      const docNode = docNodes.find((n) => n.id === node.id);
      if (docNode) onNodeEdit(docNode);
    },
    [docNodes, onNodeEdit]
  );

  const gridSize = settings.gridSnapSize || 16;
  const canvasBg = settings.canvasBackground || '#0f0f14';
  const bgStyle = settings.backgroundStyle || 'dots';

  return (
    <div style={{ width: '100%', height: '100%', minHeight: 0, position: 'relative' }}>
      <ReactFlow
        nodes={nodes}
        edges={edges}
        onNodesChange={onNodesChange}
        onEdgesChange={onEdgesChange}
        onConnect={onConnect}
        onNodeDoubleClick={onNodeDoubleClick}
        nodeTypes={nodeTypes}
        edgeTypes={edgeTypes}
        proOptions={{ hideAttribution: true }}
        fitView
        snapToGrid={settings.gridSnap !== false}
        snapGrid={[gridSize, gridSize]}
        defaultViewport={{ x: 0, y: 0, zoom: settings.defaultZoom || 1 }}
        panOnScroll
        panOnDrag
        zoomOnScroll
        edgesFocusable={false}
        deleteKeyCode={['Backspace', 'Delete']}
        style={{ background: canvasBg }}
      >
        {bgStyle !== 'none' && (
          <Background
            variant={bgStyle}
            gap={bgStyle === 'cross' ? 24 : 20}
            size={bgStyle === 'dots' ? 1 : 6}
            color="#2a2a35"
          />
        )}
        {settings.showMinimap !== false && (
          <MiniMap
            nodeColor={getNodeColor}
            nodeStrokeColor="#3f3f46"
            nodeStrokeWidth={2}
            nodeBorderRadius={4}
            maskColor="rgba(15, 15, 20, 0.7)"
            style={{ 
              height: 120, 
              width: 180, 
              background: '#18181b',
              borderRadius: 8, 
              border: '1px solid #3f3f46',
              bottom: 60,
              right: 16,
            }}
            zoomable
            pannable
          />
        )}
        <CanvasControls
          onUndo={onUndo}
          onRedo={onRedo}
          canUndo={canUndo}
          canRedo={canRedo}
          onExport={onExport}
        />
      </ReactFlow>

    </div>
  );
}

export function FlowCanvas(props) {
  return (
    <ReactFlowProvider>
      <FlowCanvasInner {...props} />
    </ReactFlowProvider>
  );
}
