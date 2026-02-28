import React from 'react';
import { validateFlow } from '../utils/validation';

export function ValidationPanel({ flowState, validationResult, onValidate }) {
  const result = validationResult ?? (flowState?.nodes?.length ? validateFlow(flowState) : null);

  return (
    <div className="validation-panel-wrapper" style={{ marginTop: 8 }}>
      <button
        type="button"
        onClick={onValidate}
        style={{
          padding: '6px 12px',
          background: '#2a2a4e',
          border: '1px solid #333',
          borderRadius: 4,
          color: '#e8e8e8',
          cursor: 'pointer',
          fontSize: 13,
        }}
      >
        Validate Flow
      </button>
      {result && (
        <div className={`validation-panel ${result.valid ? 'success' : 'error'}`}>
          {result.valid ? (
            <p className="success-msg">Flow is valid. You can save.</p>
          ) : (
            <>
              <p style={{ margin: '0 0 8px', fontWeight: 600, color: '#f87171' }}>Validation errors:</p>
              <ul>
                {result.errors.map((err, i) => (
                  <li key={i} className="error-msg">{err}</li>
                ))}
              </ul>
            </>
          )}
        </div>
      )}
    </div>
  );
}
