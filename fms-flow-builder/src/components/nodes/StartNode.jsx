import React from 'react';
import { Handle, Position } from '@xyflow/react';

export function StartNode({ selected }) {
  return (
    <div
      style={{
        width: 64,
        height: 64,
        borderRadius: '50%',
        background: 'linear-gradient(135deg, var(--primary-color, #6366f1) 0%, var(--primary-color-light, #8b5cf6) 100%)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        color: '#fff',
        fontSize: 10,
        fontWeight: 700,
        letterSpacing: '0.04em',
        textAlign: 'center',
        lineHeight: 1.2,
        boxShadow: selected
          ? '0 0 0 2px var(--primary-color, #6366f1), 0 4px 12px rgba(99,102,241,0.4)'
          : '0 2px 8px rgba(0,0,0,0.3)',
        cursor: 'grab',
        userSelect: 'none',
      }}
    >
      Flow<br/>Start
      <Handle
        type="source"
        position={Position.Right}
        style={{
          width: 8,
          height: 8,
          background: '#fff',
          border: '2px solid var(--primary-color, #6366f1)',
          right: -4,
        }}
      />
    </div>
  );
}
