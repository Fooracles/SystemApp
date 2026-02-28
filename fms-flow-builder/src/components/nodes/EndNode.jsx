import React from 'react';
import { Handle, Position } from '@xyflow/react';

export function EndNode({ selected }) {
  return (
    <div
      style={{
        width: 64,
        height: 64,
        borderRadius: '50%',
        background: '#27272a',
        border: '3px solid #71717a',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        color: '#a1a1aa',
        fontSize: 10,
        fontWeight: 700,
        letterSpacing: '0.04em',
        textAlign: 'center',
        lineHeight: 1.2,
        boxShadow: selected
          ? '0 0 0 2px #71717a, 0 4px 12px rgba(113,113,122,0.3)'
          : '0 2px 8px rgba(0,0,0,0.3)',
        cursor: 'grab',
        userSelect: 'none',
      }}
    >
      Flow<br/>End
      <Handle
        type="target"
        position={Position.Left}
        style={{
          width: 8,
          height: 8,
          background: '#a1a1aa',
          border: '2px solid #71717a',
          left: -4,
        }}
      />
    </div>
  );
}
