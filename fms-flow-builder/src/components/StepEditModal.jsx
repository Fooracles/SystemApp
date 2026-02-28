import React, { useState, useEffect } from 'react';
import { NODE_TYPES } from '../data/flowModel';

function hoursToHhmm(hoursValue) {
  const totalMinutes = Math.max(0, Math.round((Number(hoursValue) || 0) * 60));
  const hh = String(Math.floor(totalMinutes / 60)).padStart(2, '0');
  const mm = String(totalMinutes % 60).padStart(2, '0');
  return `${hh}:${mm}`;
}

function hhmmToHours(hhmmValue) {
  const value = String(hhmmValue || '').trim();
  const match = value.match(/^(\d{1,3}):([0-5]\d)$/);
  if (!match) return 0;
  const hours = Number(match[1]) || 0;
  const minutes = Number(match[2]) || 0;
  return (hours * 60 + minutes) / 60;
}

export function StepEditModal({ node, onSave, onClose }) {
  const [form, setForm] = useState({
    stepName: '',
    stepCode: '',
    stepType: NODE_TYPES.STEP,
    allowSkip: false,
    allowReassignment: false,
    plannedDurationHhmm: '00:00',
    skipSundays: false,
    skipHolidays: false,
    targetValue: 0,
  });

  useEffect(() => {
    if (!node?.data) return;
    const d = node.data;
    const tr = d.timeRules || {};
    const tc = d.targetConfig || {};
    setForm({
      stepName: d.stepName ?? '',
      stepCode: d.stepCode ?? '',
      stepType: d.stepType ?? NODE_TYPES.STEP,
      allowSkip: d.allowSkip ?? false,
      allowReassignment: d.allowReassignment ?? false,
      plannedDurationHhmm: hoursToHhmm(tr.plannedDuration ?? 0),
      skipSundays: tr.skipSundays ?? false,
      skipHolidays: tr.skipHolidays ?? false,
      targetValue: tc.targetValue ?? 0,
    });
  }, [node?.id]);

  const handleChange = (field, value) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    onSave({
      stepName: form.stepName,
      stepCode: form.stepCode,
      stepType: form.stepType,
      allowSkip: form.allowSkip,
      allowReassignment: form.allowReassignment,
      timeRules: {
        plannedDuration: hhmmToHours(form.plannedDurationHhmm),
        skipSundays: form.skipSundays,
        skipHolidays: form.skipHolidays,
      },
      ...(form.stepType === NODE_TYPES.TARGET && {
        targetConfig: { targetValue: Number(form.targetValue) || 0 },
      }),
    });
    onClose();
  };

  if (!node) return null;

  const isTarget = node.type === NODE_TYPES.TARGET;

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" onClick={(e) => e.stopPropagation()}>
        <div style={{ padding: '16px 20px' }}>
          <h2 style={{ margin: '0 0 12px', fontSize: 16 }}>Edit Step</h2>
          <form onSubmit={handleSubmit}>
            {/* Row 1: Name and Code */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
              <div>
                <label>Step name</label>
                <input
                  type="text"
                  value={form.stepName}
                  onChange={(e) => handleChange('stepName', e.target.value)}
                  placeholder="Step name"
                />
              </div>
              <div>
                <label>Step code</label>
                <input
                  type="text"
                  value={form.stepCode}
                  readOnly
                  placeholder="STEP_CODE"
                />
              </div>
            </div>

            {/* Options row */}
            <div style={{ display: 'flex', gap: 16, marginTop: 8 }}>
              <div className="field-row" style={{ margin: 0 }}>
                <input
                  type="checkbox"
                  id="allowSkip"
                  checked={form.allowSkip}
                  onChange={(e) => handleChange('allowSkip', e.target.checked)}
                />
                <label htmlFor="allowSkip">Allow skip</label>
              </div>
              <div className="field-row" style={{ margin: 0 }}>
                <input
                  type="checkbox"
                  id="allowReassignment"
                  checked={form.allowReassignment}
                  onChange={(e) => handleChange('allowReassignment', e.target.checked)}
                />
                <label htmlFor="allowReassignment">Allow reassignment</label>
              </div>
            </div>

            {/* Time rules */}
            <div style={{ marginTop: 12 }}>
              <div>
                <h3 style={{ margin: '0 0 8px', fontSize: 13, color: '#a1a1aa' }}>Time rules</h3>
                <label>Planned duration (HH:MM)</label>
                <input
                  type="text"
                  value={form.plannedDurationHhmm}
                  onChange={(e) => handleChange('plannedDurationHhmm', e.target.value)}
                  placeholder="00:00"
                  style={{ marginBottom: 8 }}
                />
                <div className="field-row" style={{ margin: '4px 0' }}>
                  <input
                    type="checkbox"
                    id="skipSundays"
                    checked={form.skipSundays}
                    onChange={(e) => handleChange('skipSundays', e.target.checked)}
                  />
                  <label htmlFor="skipSundays">Skip Sundays</label>
                </div>
                <div className="field-row" style={{ margin: '4px 0' }}>
                  <input
                    type="checkbox"
                    id="skipHolidays"
                    checked={form.skipHolidays}
                    onChange={(e) => handleChange('skipHolidays', e.target.checked)}
                  />
                  <label htmlFor="skipHolidays">Skip holidays</label>
                </div>
              </div>
            </div>

            {isTarget && (
              <div style={{ marginTop: 12 }}>
                <h3 style={{ margin: '0 0 8px', fontSize: 13, color: '#a1a1aa' }}>Target config</h3>
                <div style={{ maxWidth: 200 }}>
                  <label>Target value</label>
                  <input
                    type="number"
                    value={form.targetValue}
                    onChange={(e) => handleChange('targetValue', e.target.value)}
                  />
                </div>
              </div>
            )}

            <div style={{ display: 'flex', gap: 8, marginTop: 16 }}>
              <button type="submit" style={{ padding: '8px 16px', background: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)', border: 'none', borderRadius: 6, color: '#fff', cursor: 'pointer' }}>
                Save
              </button>
              <button type="button" onClick={onClose} style={{ padding: '8px 16px', background: '#3f3f46', border: 'none', borderRadius: 6, color: '#e8e8e8', cursor: 'pointer' }}>
                Cancel
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}
