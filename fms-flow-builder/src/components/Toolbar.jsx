import React from 'react';
import { NODE_TYPES } from '../data/flowModel';

const toolbarStyle = {
  display: 'flex',
  alignItems: 'center',
  gap: 8,
  padding: '10px 16px',
  background: '#16213e',
  borderBottom: '1px solid #333',
  flexWrap: 'wrap',
};

const btnStyle = {
  padding: '6px 12px',
  background: '#2a2a4e',
  border: '1px solid #333',
  borderRadius: 4,
  color: '#e8e8e8',
  cursor: 'pointer',
  fontSize: 13,
};

const btnPrimary = { ...btnStyle, background: '#6c9bc7', borderColor: '#6c9bc7' };

export function Toolbar({
  onAddStep,
  onAddDecision,
  onAddTarget,
  onValidate,
  onSaveFlow,
  onLoadSample,
  validationResult,
}) {
  const isValid = validationResult?.valid ?? false;

  return (
    <div style={toolbarStyle}>
      <button type="button" style={btnStyle} onClick={() => onAddStep(NODE_TYPES.STEP)}>
        Add Step
      </button>
      <button type="button" style={btnStyle} onClick={() => onAddDecision(NODE_TYPES.DECISION)}>
        Add Decision
      </button>
      <button type="button" style={btnStyle} onClick={() => onAddTarget(NODE_TYPES.TARGET)}>
        Add Target Step
      </button>
      <span style={{ width: 1, height: 20, background: '#333' }} />
      <button type="button" style={btnStyle} onClick={onValidate}>
        Validate Flow
      </button>
      <button
        type="button"
        style={{ ...btnPrimary, opacity: isValid ? 1 : 0.6 }}
        onClick={onSaveFlow}
        title={isValid ? 'Save flow (see console)' : 'Fix validation errors first'}
      >
        Save Flow
      </button>
      <button type="button" style={btnStyle} onClick={onLoadSample}>
        Load Sample Flow
      </button>
      {validationResult && (
        <span
          style={{
            fontSize: 12,
            color: validationResult.valid ? '#4ade80' : '#f87171',
            marginLeft: 8,
          }}
        >
          {validationResult.valid ? '✓ Valid' : '✗ Invalid'}
        </span>
      )}
    </div>
  );
}
