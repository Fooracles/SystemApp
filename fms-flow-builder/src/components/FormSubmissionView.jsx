import React, { useEffect, useMemo, useState } from 'react';
import { listFlows } from '../services/flowApi';
import { getForm, listForms } from '../services/formApi';
import { submitFormRun } from '../services/executionApi';

const inputStyle = {
  width: '100%',
  background: '#27272a',
  border: '1px solid #3f3f46',
  color: '#f4f4f5',
  borderRadius: 6,
  padding: '8px 10px',
  fontSize: 13,
};

const cardStyle = {
  background: '#18181b',
  border: '1px solid #2a2a35',
  borderRadius: 10,
  padding: 16,
};

export function FormSubmissionView({ onShowToast }) {
  const [flows, setFlows] = useState([]);
  const [forms, setForms] = useState([]);
  const [selectedFlowId, setSelectedFlowId] = useState('');
  const [selectedFormId, setSelectedFormId] = useState('');
  const [fields, setFields] = useState([]);
  const [values, setValues] = useState({});
  const [runId, setRunId] = useState(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    listFlows().then((rows) => {
      setFlows(rows || []);
      if (rows?.length) setSelectedFlowId(String(rows[0].id));
    }).catch((e) => onShowToast?.('Load flows failed: ' + e.message));
  }, [onShowToast]);

  useEffect(() => {
    if (!selectedFlowId) return;
    listForms(selectedFlowId).then((rows) => {
      const active = (rows || []).filter((x) => x.status === 'active');
      setForms(active);
      setSelectedFormId(active[0] ? String(active[0].id) : '');
    }).catch((e) => onShowToast?.('Load forms failed: ' + e.message));
  }, [selectedFlowId, onShowToast]);

  useEffect(() => {
    if (!selectedFormId) {
      setFields([]);
      setValues({});
      return;
    }
    getForm(selectedFormId).then((res) => {
      const list = res.fields || [];
      setFields(list);
      const v = {};
      list.forEach((f) => {
        v[f.field_key] = f.default_value ?? (f.field_type === 'checkbox' ? false : '');
      });
      setValues(v);
    }).catch((e) => onShowToast?.('Load form failed: ' + e.message));
  }, [selectedFormId, onShowToast]);

  const selectedForm = useMemo(() => forms.find((f) => String(f.id) === String(selectedFormId)), [forms, selectedFormId]);

  const submit = async () => {
    if (!selectedFormId) return;
    for (const f of fields) {
      if (f.is_required) {
        const val = values[f.field_key];
        const empty = val === '' || val === null || (Array.isArray(val) && val.length === 0);
        if (empty) {
          onShowToast?.(`Required: ${f.field_label}`);
          return;
        }
      }
    }
    setLoading(true);
    try {
      const titlePart = values.news_topic || values.client_name || selectedForm?.name || 'Run';
      const res = await submitFormRun(Number(selectedFormId), values, `${selectedForm?.name || 'Form'}: ${titlePart}`);
      setRunId(res.run_id);
      onShowToast?.('Run created successfully');
    } catch (e) {
      onShowToast?.('Submit failed: ' + e.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{ padding: 20, color: '#f4f4f5', background: '#0f0f14', height: '100%', overflow: 'auto' }}>
      <h1 style={{ margin: '0 0 16px', fontSize: 22 }}>Form Submission</h1>
      <div style={{ ...cardStyle, marginBottom: 16 }}>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
          <select value={selectedFlowId} onChange={(e) => setSelectedFlowId(e.target.value)} style={inputStyle}>
            <option value="">Select flow</option>
            {flows.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
          </select>
          <select value={selectedFormId} onChange={(e) => setSelectedFormId(e.target.value)} style={inputStyle}>
            <option value="">Select active form</option>
            {forms.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
          </select>
        </div>
      </div>

      <div style={cardStyle}>
        <h2 style={{ margin: '0 0 12px', fontSize: 16 }}>{selectedForm?.name || 'Fill Form'}</h2>
        <div style={{ display: 'grid', gap: 10 }}>
          {fields.map((f) => {
            const v = values[f.field_key];
            const opts = Array.isArray(f.options) ? f.options : [];
            return (
              <div key={f.field_key}>
                <div style={{ color: '#a1a1aa', fontSize: 12, marginBottom: 4 }}>
                  {f.field_label} {f.is_required ? '*' : ''}
                </div>
                {(f.field_type === 'text' || f.field_type === 'number' || f.field_type === 'date') && (
                  <input
                    type={f.field_type === 'number' ? 'number' : f.field_type === 'date' ? 'date' : 'text'}
                    value={v ?? ''}
                    onChange={(e) => setValues((p) => ({ ...p, [f.field_key]: e.target.value }))}
                    style={inputStyle}
                    placeholder={f.placeholder || ''}
                  />
                )}
                {f.field_type === 'textarea' && (
                  <textarea
                    value={v ?? ''}
                    onChange={(e) => setValues((p) => ({ ...p, [f.field_key]: e.target.value }))}
                    style={{ ...inputStyle, minHeight: 90 }}
                    placeholder={f.placeholder || ''}
                  />
                )}
                {(f.field_type === 'select' || f.field_type === 'multiselect') && (
                  <select
                    multiple={f.field_type === 'multiselect'}
                    value={f.field_type === 'multiselect' ? (Array.isArray(v) ? v : []) : (v ?? '')}
                    onChange={(e) => {
                      if (f.field_type === 'multiselect') {
                        const arr = Array.from(e.target.selectedOptions).map((o) => o.value);
                        setValues((p) => ({ ...p, [f.field_key]: arr }));
                      } else {
                        setValues((p) => ({ ...p, [f.field_key]: e.target.value }));
                      }
                    }}
                    style={inputStyle}
                  >
                    {f.field_type === 'select' && <option value="">Select</option>}
                    {opts.map((o) => <option key={o} value={o}>{o}</option>)}
                  </select>
                )}
                {f.field_type === 'checkbox' && (
                  <label style={{ display: 'flex', alignItems: 'center', gap: 8, color: '#a1a1aa' }}>
                    <input type="checkbox" checked={!!v} onChange={(e) => setValues((p) => ({ ...p, [f.field_key]: e.target.checked }))} />
                    Checked
                  </label>
                )}
              </div>
            );
          })}
        </div>

        <button onClick={submit} disabled={!selectedFormId || loading} style={{ ...inputStyle, marginTop: 16, width: 180, background: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)', border: 'none', cursor: 'pointer' }}>
          {loading ? 'Submitting...' : 'Submit Form'}
        </button>

        {runId && <div style={{ marginTop: 10, color: '#4ade80', fontSize: 13 }}>Run created: #{runId}</div>}
      </div>
    </div>
  );
}

