import React from 'react';
import { Handle, Position } from '@xyflow/react';
import { NODE_TYPES, STEP_STATUS } from '../../data/flowModel';

const statusLabels = {
  [STEP_STATUS.PENDING]: 'Pending',
  [STEP_STATUS.IN_PROGRESS]: 'In Progress',
  [STEP_STATUS.COMPLETED]: 'Completed',
};

const statusClass = {
  [STEP_STATUS.PENDING]: 'status-pending',
  [STEP_STATUS.IN_PROGRESS]: 'status-progress',
  [STEP_STATUS.COMPLETED]: 'status-completed',
};

export function DecisionNode({ data, selected }) {
  const status = data?.status || STEP_STATUS.PENDING;
  return (
    <div className={`fms-node fms-node-decision ${selected ? 'selected' : ''}`}>
      <Handle type="target" position={Position.Left} className="node-handle" />
      <div className="fms-node-badge fms-badge-decision">Decision</div>
      <div className="fms-node-title">{data?.stepName || 'Decision'}</div>
      <div className="fms-node-code">{data?.stepCode || '—'}</div>
      <div className={`fms-node-status ${statusClass[status]}`}>
        {statusLabels[status]}
      </div>
      <div className="fms-decision-side-handles">
        <span className="handle-label handle-yes fms-decision-label-yes">Yes</span>
        <Handle type="source" position={Position.Right} id="yes" className="node-handle" style={{ top: '36%' }} />
        <span className="handle-label handle-no fms-decision-label-no">No</span>
        <Handle type="source" position={Position.Right} id="no" className="node-handle" style={{ top: '68%' }} />
      </div>
    </div>
  );
}
