import React, { useEffect, useMemo, useState } from 'react';
import { listFlows } from '../services/flowApi';
import {
  createForm,
  getFlowSteps,
  getForm,
  listAssignableUsers,
  listForms,
  saveFormFields,
  saveFormStepMap,
  updateForm,
} from '../services/formApi';

function toHHMM(minutes) {
  const m = Math.max(0, Number(minutes) || 0);
  const h = Math.floor(m / 60);
  const r = m % 60;
  return `${String(h).padStart(2, '0')}:${String(r).padStart(2, '0')}`;
}

function parseHHMM(v) {
  if (!v) return 0;
  const m = String(v).match(/^(\d{1,3}):([0-5]\d)$/);
  if (!m) return 0;
  return Number(m[1]) * 60 + Number(m[2]);
}

const cardStyle = {
  background: '#18181b',
  border: '1px solid #2a2a35',
  borderRadius: 10,
  padding: 16,
};

const inputStyle = {
  width: '100%',
  background: '#27272a',
  border: '1px solid #3f3f46',
  color: '#f4f4f5',
  borderRadius: 6,
  padding: '8px 10px',
  fontSize: 13,
};

export function FormBuilderView({ onShowToast }) {
  const [flows, setFlows] = useState([]);
  const [users, setUsers] = useState([]);
  const [forms, setForms] = useState([]);
  const [selectedFlowId, setSelectedFlowId] = useState('');
  const [selectedFormId, setSelectedFormId] = useState('');
  const [loading, setLoading] = useState(false);

  const [meta, setMeta] = useState({ name: '', description: '', status: 'draft' });
  const [fields, setFields] = useState([]);
  const [stepMap, setStepMap] = useState([]);
  const [flowSteps, setFlowSteps] = useState([]);

  const canEdit = !!selectedFormId;
  const getValidFormId = () => {
    const parsed = Number(selectedFormId);
    return Number.isInteger(parsed) && parsed > 0 ? parsed : 0;
  };

  useEffect(() => {
    Promise.all([listFlows(), listAssignableUsers()])
      .then(([flowList, userList]) => {
        setFlows(flowList || []);
        setUsers(userList || []);
        if (flowList?.length) setSelectedFlowId(String(flowList[0].id));
      })
      .catch((e) => onShowToast?.('Init failed: ' + e.message));
  }, [onShowToast]);

  useEffect(() => {
    if (!selectedFlowId) return;
    listForms(selectedFlowId)
      .then((rows) => setForms(rows || []))
      .catch((e) => onShowToast?.('Load forms failed: ' + e.message));
  }, [selectedFlowId, loading, onShowToast]);

  useEffect(() => {
    if (!selectedFormId) {
      setMeta({ name: '', description: '', status: 'draft' });
      setFields([]);
      setStepMap([]);
      setFlowSteps([]);
      return;
    }
    setLoading(true);
    getForm(selectedFormId)
      .then((res) => {
        const form = res.form || {};
        const flowBasedMap = (res.flow_steps || []).map((s) => ({
          node_id: s.node_id,
          doer_id: s.suggested_doer_id || '',
          duration_hhmm: toHHMM(s.duration_minutes),
          sort_order: s.sort_order,
          step_name: s.step_name,
        }));
        const dbMap = (res.step_map || []).map((m) => ({
          ...m,
          duration_hhmm: m.duration_hhmm || toHHMM(m.duration_minutes),
        }));
        setMeta({
          name: form.name || '',
          description: form.description || '',
          status: form.status || 'draft',
        });
        setFields(res.fields || []);
        setStepMap(dbMap.length ? dbMap : flowBasedMap);
        setFlowSteps(res.flow_steps || []);
      })
      .catch((e) => onShowToast?.('Load form failed: ' + e.message))
      .finally(() => setLoading(false));
  }, [selectedFormId, onShowToast]);

  const userOptions = useMemo(
    () => users.map((u) => ({ id: String(u.id), label: `${u.name} (${u.user_type})` })),
    [users]
  );

  const createNewForm = async () => {
    if (!selectedFlowId) return;
    try {
      const res = await createForm({
        flow_id: Number(selectedFlowId),
        name: 'New Submission Form',
        description: '',
        status: 'draft',
      });
      setSelectedFormId(String(res.form_id));
      setLoading((v) => !v);
      onShowToast?.('Form created');
    } catch (e) {
      onShowToast?.('Create failed: ' + e.message);
    }
  };

  const saveMeta = async () => {
    const formId = getValidFormId();
    if (!formId) {
      onShowToast?.('Please select a valid form first');
      return;
    }
    try {
      await updateForm({ id: formId, ...meta });
      onShowToast?.('Form details saved');
      setLoading((v) => !v);
    } catch (e) {
      onShowToast?.('Save failed: ' + e.message);
    }
  };

  const saveFields = async () => {
    const formId = getValidFormId();
    if (!formId) {
      onShowToast?.('Please select a valid form first');
      return;
    }
    try {
      const payload = fields.map((f, idx) => ({
        field_key: f.field_key || `field_${idx + 1}`,
        field_label: f.field_label || `Field ${idx + 1}`,
        field_type: f.field_type || 'text',
        options: Array.isArray(f.options)
          ? f.options
          : String(f.options || '')
            .split(',')
            .map((s) => s.trim())
            .filter(Boolean),
        is_required: !!f.is_required,
        default_value: f.default_value || '',
        placeholder: f.placeholder || '',
        sort_order: idx + 1,
      }));
      await saveFormFields(formId, payload);
      onShowToast?.('Fields saved');
      setLoading((v) => !v);
    } catch (e) {
      onShowToast?.('Save fields failed: ' + e.message);
    }
  };

  const prefillSteps = async () => {
    if (!selectedFlowId) return;
    try {
      const steps = await getFlowSteps(Number(selectedFlowId));
      setFlowSteps(steps);
      setStepMap(
        steps.map((s) => ({
          node_id: s.node_id,
          doer_id: s.suggested_doer_id || '',
          duration_hhmm: toHHMM(s.duration_minutes),
          sort_order: s.sort_order,
          step_name: s.step_name,
        }))
      );
      onShowToast?.('Step map prefilled from flow');
    } catch (e) {
      onShowToast?.('Prefill failed: ' + e.message);
    }
  };

  const saveStepMap = async () => {
    const formId = getValidFormId();
    if (!formId) {
      onShowToast?.('Please select a valid form first');
      return;
    }
    try {
      const payload = stepMap.map((m, idx) => ({
        node_id: m.node_id,
        doer_id: Number(m.doer_id) || 0,
        duration_minutes: parseHHMM(m.duration_hhmm),
        sort_order: m.sort_order || idx + 1,
      }));
      await saveFormStepMap(formId, payload);
      onShowToast?.('Step mapping saved');
      setLoading((v) => !v);
    } catch (e) {
      onShowToast?.('Save step map failed: ' + e.message);
    }
  };

  return (
    <div style={{ padding: 20, color: '#f4f4f5', background: '#0f0f14', height: '100%', overflow: 'auto' }}>
      <h1 style={{ margin: '0 0 16px', fontSize: 22 }}>Form Builder</h1>

      <div style={{ display: 'grid', gridTemplateColumns: '280px 1fr', gap: 16 }}>
        <div style={cardStyle}>
          <div style={{ marginBottom: 10, color: '#a1a1aa', fontSize: 12 }}>Flow</div>
          <select value={selectedFlowId} onChange={(e) => { setSelectedFlowId(e.target.value); setSelectedFormId(''); }} style={inputStyle}>
            <option value="">Select flow</option>
            {flows.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
          </select>
          <button onClick={createNewForm} style={{ ...inputStyle, marginTop: 10, cursor: 'pointer', background: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)', border: 'none' }}>
            + Create Form
          </button>

          <div style={{ marginTop: 16, marginBottom: 8, color: '#a1a1aa', fontSize: 12 }}>Forms</div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8, maxHeight: 420, overflow: 'auto' }}>
            {forms.map((f) => (
              <button
                key={f.id}
                onClick={() => setSelectedFormId(String(f.id))}
                style={{
                  textAlign: 'left',
                  background: String(f.id) === selectedFormId ? 'rgba(99, 102, 241, 0.2)' : '#18181b',
                  border: '1px solid #2a2a35',
                  color: '#f4f4f5',
                  borderRadius: 8,
                  padding: 10,
                  cursor: 'pointer',
                }}
              >
                <div style={{ fontSize: 13, fontWeight: 600 }}>{f.name}</div>
                <div style={{ fontSize: 11, color: '#a1a1aa' }}>{f.status}</div>
              </button>
            ))}
          </div>
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          <div style={cardStyle}>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 10 }}>
              <h2 style={{ margin: 0, fontSize: 16 }}>Form Metadata</h2>
              <button disabled={!canEdit} onClick={saveMeta} style={{ ...inputStyle, width: 120, cursor: canEdit ? 'pointer' : 'not-allowed' }}>Save</button>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 140px', gap: 12 }}>
              <input value={meta.name} onChange={(e) => setMeta((p) => ({ ...p, name: e.target.value }))} placeholder="Form name" style={inputStyle} disabled={!canEdit} />
              <select value={meta.status} onChange={(e) => setMeta((p) => ({ ...p, status: e.target.value }))} style={inputStyle} disabled={!canEdit}>
                <option value="draft">draft</option>
                <option value="active">active</option>
                <option value="inactive">inactive</option>
              </select>
            </div>
            <textarea value={meta.description} onChange={(e) => setMeta((p) => ({ ...p, description: e.target.value }))} placeholder="Description" style={{ ...inputStyle, marginTop: 10, minHeight: 70 }} disabled={!canEdit} />
          </div>

          <div style={cardStyle}>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 10 }}>
              <h2 style={{ margin: 0, fontSize: 16 }}>Form Fields</h2>
              <div style={{ display: 'flex', gap: 8 }}>
                <button disabled={!canEdit} onClick={() => setFields((p) => [...p, { field_key: '', field_label: '', field_type: 'text', is_required: false, options: [] }])} style={{ ...inputStyle, width: 120, cursor: canEdit ? 'pointer' : 'not-allowed' }}>+ Field</button>
                <button disabled={!canEdit} onClick={saveFields} style={{ ...inputStyle, width: 120, cursor: canEdit ? 'pointer' : 'not-allowed' }}>Save Fields</button>
              </div>
            </div>
            <div style={{ display: 'grid', gap: 8 }}>
              {fields.map((f, idx) => (
                <div key={idx} style={{ display: 'grid', gridTemplateColumns: '120px 1fr 120px 80px 80px', gap: 8 }}>
                  <input value={f.field_key || ''} onChange={(e) => setFields((p) => p.map((x, i) => i === idx ? { ...x, field_key: e.target.value } : x))} placeholder="field_key" style={inputStyle} />
                  <input value={f.field_label || ''} onChange={(e) => setFields((p) => p.map((x, i) => i === idx ? { ...x, field_label: e.target.value } : x))} placeholder="Field label" style={inputStyle} />
                  <select value={f.field_type || 'text'} onChange={(e) => setFields((p) => p.map((x, i) => i === idx ? { ...x, field_type: e.target.value } : x))} style={inputStyle}>
                    {['text', 'textarea', 'number', 'date', 'select', 'multiselect', 'file', 'checkbox'].map((t) => <option key={t} value={t}>{t}</option>)}
                  </select>
                  <label style={{ color: '#a1a1aa', fontSize: 12, display: 'flex', alignItems: 'center', gap: 6 }}>
                    <input type="checkbox" checked={!!f.is_required} onChange={(e) => setFields((p) => p.map((x, i) => i === idx ? { ...x, is_required: e.target.checked } : x))} />
                    Req
                  </label>
                  <button onClick={() => setFields((p) => p.filter((_, i) => i !== idx))} style={{ ...inputStyle, cursor: 'pointer' }}>Delete</button>
                </div>
              ))}
            </div>
          </div>

          <div style={cardStyle}>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 10 }}>
              <h2 style={{ margin: 0, fontSize: 16 }}>Step Mapping</h2>
              <div style={{ display: 'flex', gap: 8 }}>
                <button disabled={!selectedFlowId || !canEdit} onClick={prefillSteps} style={{ ...inputStyle, width: 140, cursor: canEdit ? 'pointer' : 'not-allowed' }}>Prefill Steps</button>
                <button disabled={!canEdit} onClick={saveStepMap} style={{ ...inputStyle, width: 140, cursor: canEdit ? 'pointer' : 'not-allowed' }}>Save Mapping</button>
              </div>
            </div>

            <div style={{ display: 'grid', gap: 8 }}>
              {(stepMap.length ? stepMap : flowSteps).map((s, idx) => (
                <div key={s.node_id || idx} style={{ display: 'grid', gridTemplateColumns: '1.2fr 1fr 120px', gap: 8 }}>
                  <div style={{ ...inputStyle, background: '#1f1f24' }}>{s.step_name || s.node_id}</div>
                  <select
                    value={String(s.doer_id || '')}
                    onChange={(e) => setStepMap((p) => p.map((x, i) => i === idx ? { ...x, doer_id: e.target.value } : x))}
                    style={inputStyle}
                  >
                    <option value="">Assign user</option>
                    {userOptions.map((u) => <option key={u.id} value={u.id}>{u.label}</option>)}
                  </select>
                  <input
                    value={s.duration_hhmm || toHHMM(s.duration_minutes)}
                    onChange={(e) => setStepMap((p) => p.map((x, i) => i === idx ? { ...x, duration_hhmm: e.target.value } : x))}
                    placeholder="HH:MM"
                    style={inputStyle}
                  />
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

