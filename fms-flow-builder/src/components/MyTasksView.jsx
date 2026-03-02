import React, { useEffect, useState } from 'react';
import { completeTask, getMyActiveTasks, getMyTaskHistory, startTask } from '../services/executionApi';

const cardStyle = {
  background: '#18181b',
  border: '1px solid #2a2a35',
  borderRadius: 10,
  padding: 14,
};

const btnStyle = {
  background: '#27272a',
  border: '1px solid #3f3f46',
  color: '#f4f4f5',
  borderRadius: 6,
  padding: '6px 10px',
  fontSize: 12,
  cursor: 'pointer',
};

export function MyTasksView({ onShowToast }) {
  const [active, setActive] = useState([]);
  const [history, setHistory] = useState([]);
  const [tab, setTab] = useState('active');
  const [busyId, setBusyId] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = () => {
    setLoading(true);
    setError('');
    Promise.all([getMyActiveTasks(), getMyTaskHistory()])
      .then(([a, h]) => {
        setActive(a.tasks || []);
        setHistory(h.tasks || []);
      })
      .catch((e) => {
        const msg = e?.message || 'Unknown error';
        setError(msg);
        onShowToast?.('Load tasks failed: ' + msg);
      })
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    load();
  }, []);

  const onStart = async (id) => {
    try {
      setBusyId(id);
      await startTask(id);
      onShowToast?.('Task started');
      load();
    } catch (e) {
      onShowToast?.('Start failed: ' + e.message);
    } finally {
      setBusyId(0);
    }
  };

  const onDone = async (id) => {
    try {
      setBusyId(id);
      await completeTask(id, {});
      onShowToast?.('Task completed');
      load();
    } catch (e) {
      onShowToast?.('Complete failed: ' + e.message);
    } finally {
      setBusyId(0);
    }
  };

  const rows = tab === 'active' ? active : history;

  return (
    <div style={{ padding: 20, color: '#f4f4f5', background: '#0f0f14', height: '100%', overflow: 'auto' }}>
      <h1 style={{ margin: '0 0 16px', fontSize: 22 }}>My Tasks</h1>
      <div style={{ display: 'flex', gap: 8, marginBottom: 12 }}>
        <button onClick={() => setTab('active')} style={{ ...btnStyle, background: tab === 'active' ? 'rgba(99, 102, 241, 0.25)' : '#27272a' }}>
          Active ({active.length})
        </button>
        <button onClick={() => setTab('history')} style={{ ...btnStyle, background: tab === 'history' ? 'rgba(99, 102, 241, 0.25)' : '#27272a' }}>
          History ({history.length})
        </button>
      </div>

      <div style={{ display: 'grid', gap: 10 }}>
        {loading ? (
          <div style={{ ...cardStyle, color: '#a1a1aa' }}>Loading tasks...</div>
        ) : error ? (
          <div style={{ ...cardStyle }}>
            <div style={{ color: '#f87171', marginBottom: 10 }}>Could not load tasks: {error}</div>
            <button onClick={load} style={btnStyle}>Retry</button>
          </div>
        ) : rows.length === 0 ? (
          <div style={{ ...cardStyle, color: '#a1a1aa' }}>
            <div style={{ marginBottom: 8 }}>
              {tab === 'active' ? 'No active tasks assigned to you.' : 'No task history yet.'}
            </div>
            {tab === 'active' && (
              <div style={{ fontSize: 12, color: '#71717a', marginBottom: 10 }}>
                Tasks appear here when a submitted form step is assigned to your user.
              </div>
            )}
            <button onClick={load} style={btnStyle}>Refresh</button>
          </div>
        ) : (
          rows.map((t) => (
            <div key={t.id} style={cardStyle}>
              <div style={{ display: 'flex', justifyContent: 'space-between', gap: 12 }}>
                <div>
                  <div style={{ fontSize: 14, fontWeight: 600 }}>{t.run_title}</div>
                  <div style={{ fontSize: 12, color: '#a1a1aa' }}>{t.step_name} • {t.status}</div>
                  {!!t.doer_name && (
                    <div style={{ fontSize: 12, color: '#a1a1aa', marginTop: 4 }}>Assigned to: {t.doer_name}</div>
                  )}
                  <div style={{ fontSize: 12, color: '#71717a', marginTop: 6 }}>
                    Planned: {t.planned_at || '—'} | Duration: {Math.floor((t.duration_minutes || 0) / 60)}h {(t.duration_minutes || 0) % 60}m
                  </div>
                </div>
                {tab === 'active' && (
                  <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                    <button disabled={busyId === t.id} onClick={() => onStart(t.id)} style={btnStyle}>Start</button>
                    <button disabled={busyId === t.id} onClick={() => onDone(t.id)} style={{ ...btnStyle, background: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)', border: 'none' }}>Mark Done</button>
                  </div>
                )}
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
}

