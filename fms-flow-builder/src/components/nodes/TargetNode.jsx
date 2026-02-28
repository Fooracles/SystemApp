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

export function TargetNode({ id, data, selected }) {
  const status = data?.status || STEP_STATUS.PENDING;
  const targetValue = data?.targetConfig?.targetValue ?? 0;

  const handleEdit = (e) => {
    e.stopPropagation();
    data?.onEdit?.(id);
  };

  const handleDelete = (e) => {
    e.stopPropagation();
    data?.onDelete?.(id);
  };

  return (
    <div className={`fms-node fms-node-target ${selected ? 'selected' : ''}`}>
      <Handle type="target" position={Position.Left} className="node-handle" />
      <div className="fms-node-actions-top">
        <button onClick={handleEdit} title="Edit" className="fms-node-btn-sm">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
          </svg>
        </button>
        <button onClick={handleDelete} title="Delete" className="fms-node-btn-sm fms-node-btn-delete">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M3 6h18"/>
            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>
            <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
          </svg>
        </button>
      </div>
      <div className="fms-node-badge fms-badge-target">Target</div>
      <div className="fms-node-title">{data?.stepName || 'Target'}</div>
      <div className="fms-node-code">{data?.stepCode || '—'}</div>
      <div className="fms-node-target-value">Target: {targetValue}</div>
      <div className={`fms-node-status ${statusClass[status]}`}>
        {statusLabels[status]}
      </div>
      <Handle type="source" position={Position.Right} className="node-handle" id="default" />
    </div>
  );
}
