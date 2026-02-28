import React from 'react';
import { getSmoothStepPath, EdgeLabelRenderer, BaseEdge } from '@xyflow/react';
import { EDGE_CONDITIONS } from '../../data/flowModel';

export function ConditionEdge({
  id,
  sourceX,
  sourceY,
  targetX,
  targetY,
  sourcePosition,
  targetPosition,
}) {
  const condition = EDGE_CONDITIONS.DEFAULT;

  const [path, labelX, labelY] = getSmoothStepPath({
    sourceX,
    sourceY,
    targetX,
    targetY,
    sourcePosition,
    targetPosition,
  });

  const isYes = condition === EDGE_CONDITIONS.YES;
  const isNo = condition === EDGE_CONDITIONS.NO;
  const label = isYes ? 'Yes' : isNo ? 'No' : null;
  const edgeClass = isYes ? 'edge-yes' : isNo ? 'edge-no' : '';

  return (
    <>
      <BaseEdge id={id} path={path} className={edgeClass} />

      <EdgeLabelRenderer>
        {/* Condition label (Yes / No) */}
        {label && (
          <div
            className="nopan nodrag"
            style={{
              position: 'absolute',
              transform: `translate(-50%, -50%) translate(${labelX}px,${labelY}px)`,
              pointerEvents: 'all',
              fontSize: 10,
              fontWeight: 600,
              color: isYes ? '#6366f1' : '#f87171',
              background: '#18181b',
              padding: '2px 6px',
              borderRadius: 4,
              border: `1px solid ${isYes ? '#6366f1' : '#f87171'}`,
            }}
          >
            {label}
          </div>
        )}
      </EdgeLabelRenderer>
    </>
  );
}
