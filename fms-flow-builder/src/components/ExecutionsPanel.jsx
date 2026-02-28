import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { getRun, listRuns } from '../services/executionApi';

const panelStyles = {
  root: { flex: 1, minHeight: 0, display: 'flex', background: '#0f0f14' },
  listWrap: { width: 360, borderRight: '1px solid #2a2a35', display: 'flex', flexDirection: 'column', minHeight: 0 },
  listHeader: { padding: '14px 16px', borderBottom: '1px solid #2a2a35', display: 'flex', alignItems: 'center', justifyContent: 'space-between' },
  listBody: { padding: 10, overflow: 'auto', minHeight: 0 },
  detailsWrap: { flex: 1, minWidth: 0, minHeight: 0, display: 'flex', flexDirection: 'column' },
  detailsHeader: { padding: '14px 18px', borderBottom: '1px solid #2a2a35' },
  detailsBody: { padding: 18, overflow: 'auto', minHeight: 0 },
};

function statusColor(status) {
  switch (status) {
    case 'completed': return '#22c55e';
    case 'running': return '#8b5cf6';
    case 'paused': return '#f59e0b';
    case 'cancelled': return '#ef4444';
    case 'pending': return '#a1a1aa';
    case 'in_progress': return '#8b5cf6';
    case 'skipped': return '#f59e0b';
    default: return '#71717a';
  }
}

function formatDateTime(v) {
  if (!v) return '-';
  const d = new Date(v.replace(' ', 'T'));
  return Number.isNaN(d.getTime()) ? v : d.toLocaleString();
}

export function ExecutionsPanel({ flowId }) {
  const [runs, setRuns] = useState([]);
  const [selectedRunId, setSelectedRunId] = useState(null);
  const [selectedRun, setSelectedRun] = useState(null);
  const [loadingList, setLoadingList] = useState(false);
  const [loadingRun, setLoadingRun] = useState(false);
  const [error, setError] = useState('');

  const loadRuns = useCallback(async () => {
    if (!flowId) {
      setRuns([]);
      setSelectedRunId(null);
      setSelectedRun(null);
      return;
    }
    setLoadingList(true);
    setError('');
    try {
      const res = await listRuns({ flow_id: flowId });
      const nextRuns = Array.isArray(res.runs) ? res.runs : [];
      setRuns(nextRuns);
      if (nextRuns.length === 0) {
        setSelectedRunId(null);
        setSelectedRun(null);
        return;
      }
      const defaultId = selectedRunId && nextRuns.some((r) => r.id === selectedRunId) ? selectedRunId : nextRuns[0].id;
      setSelectedRunId(defaultId);
    } catch (e) {
      setError(e.message || 'Failed to load executions');
    } finally {
      setLoadingList(false);
    }
  }, [flowId, selectedRunId]);

  useEffect(() => {
    loadRuns();
  }, [loadRuns]);

  useEffect(() => {
    if (!selectedRunId) return;
    let mounted = true;
    (async () => {
      setLoadingRun(true);
      setError('');
      try {
        const payload = await getRun(selectedRunId);
        if (mounted) setSelectedRun(payload);
      } catch (e) {
        if (mounted) setError(e.message || 'Failed to load run details');
      } finally {
        if (mounted) setLoadingRun(false);
      }
    })();
    return () => { mounted = false; };
  }, [selectedRunId]);

  const runStats = useMemo(() => {
    if (!selectedRun?.steps?.length) return { completed: 0, total: 0, percent: 0 };
    const total = selectedRun.steps.length;
    const completed = selectedRun.steps.filter((s) => s.status === 'completed').length;
    return { total, completed, percent: Math.round((completed / total) * 100) };
  }, [selectedRun]);

  if (!flowId) {
    return (
      <div style={{ flex: 1, display: 'grid', placeItems: 'center', color: '#a1a1aa', fontSize: 14 }}>
        Save this flow first to view execution history.
      </div>
    );
  }

  return (
    <div style={panelStyles.root}>
      <div style={panelStyles.listWrap}>
        <div style={panelStyles.listHeader}>
          <strong style={{ color: '#f4f4f5', fontSize: 14 }}>Executions</strong>
          <button onClick={loadRuns} style={{ border: '1px solid #3f3f46', background: '#18181b', color: '#a1a1aa', borderRadius: 6, padding: '5px 10px', cursor: 'pointer', fontSize: 12 }}>Refresh</button>
        </div>
        <div style={panelStyles.listBody}>
          {loadingList && <div style={{ color: '#71717a', fontSize: 13 }}>Loading runs...</div>}
          {!loadingList && runs.length === 0 && <div style={{ color: '#71717a', fontSize: 13 }}>No execution runs yet.</div>}
          {!loadingList && runs.map((run) => (
            <button
              key={run.id}
              onClick={() => setSelectedRunId(run.id)}
              style={{
                width: '100%',
                textAlign: 'left',
                marginBottom: 8,
                border: selectedRunId === run.id ? '1px solid #8b5cf6' : '1px solid #2a2a35',
                background: '#18181b',
                color: '#f4f4f5',
                borderRadius: 8,
                padding: 10,
                cursor: 'pointer',
              }}
            >
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 4 }}>
                <strong style={{ fontSize: 13 }}>{run.run_title || `Run #${run.id}`}</strong>
                <span style={{ fontSize: 11, color: statusColor(run.status), textTransform: 'uppercase' }}>{run.status}</span>
              </div>
              <div style={{ fontSize: 12, color: '#a1a1aa' }}>{run.initiated_by_name || 'Unknown'} • {formatDateTime(run.started_at)}</div>
            </button>
          ))}
        </div>
      </div>

      <div style={panelStyles.detailsWrap}>
        <div style={panelStyles.detailsHeader}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
            <strong style={{ color: '#f4f4f5', fontSize: 14 }}>{selectedRun?.run?.run_title || 'Run details'}</strong>
            {selectedRun?.run?.status && <span style={{ color: statusColor(selectedRun.run.status), fontSize: 12, textTransform: 'uppercase' }}>{selectedRun.run.status}</span>}
          </div>
          {!!selectedRun && (
            <div style={{ marginTop: 8 }}>
              <div style={{ height: 6, borderRadius: 99, background: '#27272a', overflow: 'hidden' }}>
                <div style={{ width: `${runStats.percent}%`, height: '100%', background: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)' }} />
              </div>
              <div style={{ marginTop: 6, fontSize: 12, color: '#a1a1aa' }}>{runStats.completed}/{runStats.total} steps completed</div>
            </div>
          )}
        </div>

        <div style={panelStyles.detailsBody}>
          {error && <div style={{ color: '#f87171', marginBottom: 12, fontSize: 13 }}>{error}</div>}
          {loadingRun && <div style={{ color: '#71717a', fontSize: 13 }}>Loading timeline...</div>}
          {!loadingRun && !selectedRun && <div style={{ color: '#71717a', fontSize: 13 }}>Select a run to inspect timeline.</div>}
          {!loadingRun && selectedRun?.steps?.map((step) => (
            <div key={step.id} style={{ border: '1px solid #2a2a35', background: '#18181b', borderRadius: 10, padding: 12, marginBottom: 10 }}>
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10 }}>
                <strong style={{ color: '#f4f4f5', fontSize: 13 }}>{step.step_name || step.node_id}</strong>
                <span style={{ color: statusColor(step.status), fontSize: 11, textTransform: 'uppercase' }}>{step.status}</span>
              </div>
              <div style={{ marginTop: 6, color: '#a1a1aa', fontSize: 12 }}>
                <div>Doer: {step.doer_name || 'Unassigned'}</div>
                <div>Planned: {formatDateTime(step.planned_at)} | Started: {formatDateTime(step.started_at)} | Actual: {formatDateTime(step.actual_at)}</div>
                {step.comment ? <div style={{ marginTop: 4, color: '#d4d4d8' }}>Comment: {step.comment}</div> : null}
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

