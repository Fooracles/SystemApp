import React, { useState } from 'react';
import { useReactFlow } from '@xyflow/react';

const styles = {
  container: {
    position: 'absolute',
    bottom: 16,
    right: 16,
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'flex-end',
    gap: 8,
    zIndex: 5,
  },
  toolbar: {
    display: 'flex',
    gap: 4,
    background: '#18181b',
    border: '1px solid #3f3f46',
    borderRadius: 8,
    padding: 4,
  },
  btn: {
    width: 32,
    height: 32,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    background: 'transparent',
    border: 'none',
    color: '#71717a',
    borderRadius: 6,
    cursor: 'pointer',
    fontSize: 14,
    transition: 'all 0.15s',
  },
  btnHover: {
    background: '#27272a',
    color: '#f4f4f5',
  },
  btnDisabled: {
    opacity: 0.4,
    cursor: 'not-allowed',
  },
  divider: {
    width: 1,
    height: 24,
    background: '#3f3f46',
    margin: '4px 2px',
  },
};

function ControlButton({ icon, label, onClick, disabled }) {
  const [hover, setHover] = useState(false);
  return (
    <button
      style={{
        ...styles.btn,
        ...(hover && !disabled ? styles.btnHover : {}),
        ...(disabled ? styles.btnDisabled : {}),
      }}
      title={label}
      aria-label={label}
      onClick={onClick}
      disabled={disabled}
      onMouseEnter={() => setHover(true)}
      onMouseLeave={() => setHover(false)}
    >
      {icon}
    </button>
  );
}

export function CanvasControls({ onUndo, onRedo, canUndo, canRedo, onExport }) {
  const { fitView, zoomIn, zoomOut } = useReactFlow();

  return (
    <div style={styles.container}>
      {/* Minimap is rendered separately by ReactFlow */}
      {/* This toolbar sits below it */}
      <div style={styles.toolbar}>
        <ControlButton
          icon="⛶"
          label="Fit View"
          onClick={() => fitView({ padding: 0.2 })}
        />
        <ControlButton
          icon="+"
          label="Zoom In"
          onClick={() => zoomIn()}
        />
        <ControlButton
          icon="−"
          label="Zoom Out"
          onClick={() => zoomOut()}
        />
        <div style={styles.divider} />
        <ControlButton
          icon="↶"
          label="Undo"
          onClick={onUndo}
          disabled={!canUndo}
        />
        <ControlButton
          icon="↷"
          label="Redo"
          onClick={onRedo}
          disabled={!canRedo}
        />
        <div style={styles.divider} />
        <ControlButton
          icon="↗"
          label="Export JSON"
          onClick={onExport}
        />
      </div>
    </div>
  );
}
