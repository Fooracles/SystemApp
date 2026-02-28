import React, { useState, useEffect, useCallback } from 'react';
import { listFlows, deleteFlow, duplicateFlow } from '../services/flowApi';

const styles = {
  container: {
    padding: 24,
    height: '100%',
    overflow: 'auto',
    background: '#0f0f14',
  },
  header: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 24,
  },
  title: {
    color: '#f4f4f5',
    fontSize: 22,
    fontWeight: 600,
    margin: 0,
  },
  subtitle: {
    color: '#71717a',
    fontSize: 13,
    marginTop: 4,
  },
  statsRow: {
    display: 'flex',
    gap: 16,
    marginBottom: 24,
  },
  statCard: {
    background: '#18181b',
    border: '1px solid #2a2a35',
    borderRadius: 10,
    padding: '16px 20px',
    flex: 1,
    minWidth: 140,
  },
  statValue: {
    fontSize: 28,
    fontWeight: 700,
    color: '#f4f4f5',
  },
  statLabel: {
    fontSize: 12,
    color: '#71717a',
    marginTop: 4,
  },
  grid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))',
    gap: 16,
  },
  card: {
    background: '#18181b',
    border: '1px solid #2a2a35',
    borderRadius: 12,
    padding: 16,
    cursor: 'pointer',
    transition: 'border-color 0.15s, box-shadow 0.15s',
  },
  cardHover: {
    borderColor: '#6366f1',
    boxShadow: '0 0 0 1px rgba(99, 102, 241, 0.3)',
  },
  cardHeader: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  cardName: {
    fontSize: 15,
    fontWeight: 600,
    color: '#f4f4f5',
    margin: 0,
  },
  badge: {
    fontSize: 10,
    fontWeight: 600,
    textTransform: 'uppercase',
    padding: '3px 8px',
    borderRadius: 4,
    letterSpacing: '0.03em',
  },
  badgeActive: {
    background: 'rgba(99, 102, 241, 0.15)',
    color: '#6366f1',
  },
  badgeDraft: {
    background: 'rgba(113, 113, 122, 0.15)',
    color: '#71717a',
  },
  badgeInactive: {
    background: 'rgba(248, 113, 113, 0.15)',
    color: '#f87171',
  },
  cardMeta: {
    fontSize: 12,
    color: '#71717a',
    marginBottom: 8,
  },
  cardFooter: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: 12,
    borderTop: '1px solid #2a2a35',
    marginTop: 12,
  },
  cardStat: {
    fontSize: 12,
    color: '#a1a1aa',
  },
  openBtn: {
    padding: '6px 12px',
    fontSize: 12,
    fontWeight: 500,
    background: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)',
    color: '#fff',
    border: 'none',
    borderRadius: 6,
    cursor: 'pointer',
  },
  emptyState: {
    textAlign: 'center',
    padding: 48,
    color: '#71717a',
  },
};

function formatDate(dateStr) {
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function WorkflowCard({ workflow, onOpen, onDelete, onDuplicate }) {
  const [hover, setHover] = useState(false);
  const badgeStyle =
    workflow.status === 'active'
      ? { ...styles.badge, ...styles.badgeActive }
      : workflow.status === 'draft'
        ? { ...styles.badge, ...styles.badgeDraft }
        : { ...styles.badge, ...styles.badgeInactive };

  const actionBtn = {
    background: 'none',
    border: 'none',
    color: '#71717a',
    cursor: 'pointer',
    fontSize: 13,
    padding: '4px 8px',
    borderRadius: 4,
    transition: 'color 0.15s, background 0.15s',
  };

  return (
    <div
      style={{ ...styles.card, ...(hover ? styles.cardHover : {}) }}
      onMouseEnter={() => setHover(true)}
      onMouseLeave={() => setHover(false)}
      onClick={() => onOpen(workflow)}
    >
      <div style={styles.cardHeader}>
        <h3 style={styles.cardName}>{workflow.name}</h3>
        <span style={badgeStyle}>{workflow.status}</span>
      </div>
      <div style={styles.cardMeta}>
        Created by {workflow.createdBy} • {formatDate(workflow.createdAt)}
      </div>
      <div style={styles.cardFooter}>
        <span style={styles.cardStat}>{workflow.nodesCount} steps</span>
        <span style={{ display: 'flex', gap: 4 }}>
          <button
            style={actionBtn}
            title="Duplicate"
            onMouseEnter={(e) => { e.currentTarget.style.color = '#6366f1'; e.currentTarget.style.background = 'rgba(99,102,241,0.1)'; }}
            onMouseLeave={(e) => { e.currentTarget.style.color = '#71717a'; e.currentTarget.style.background = 'none'; }}
            onClick={(e) => onDuplicate(e, workflow)}
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
          </button>
          <button
            style={actionBtn}
            title="Delete"
            onMouseEnter={(e) => { e.currentTarget.style.color = '#f87171'; e.currentTarget.style.background = 'rgba(248,113,113,0.1)'; }}
            onMouseLeave={(e) => { e.currentTarget.style.color = '#71717a'; e.currentTarget.style.background = 'none'; }}
            onClick={(e) => onDelete(e, workflow)}
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          </button>
        </span>
      </div>
    </div>
  );
}

export function HomeView({ onOpenWorkflow, onCreateNew }) {
  const [workflows, setWorkflows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const fetchWorkflows = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const flows = await listFlows();
      setWorkflows(flows);
    } catch (err) {
      console.warn('Could not load workflows from API:', err.message);
      setError(err.message);
      setWorkflows([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchWorkflows(); }, [fetchWorkflows]);

  const handleDelete = useCallback(async (e, workflow) => {
    e.stopPropagation();
    if (!window.confirm(`Delete "${workflow.name}"? This cannot be undone.`)) return;
    try {
      await deleteFlow(workflow.id);
      setWorkflows((prev) => prev.filter((w) => w.id !== workflow.id));
    } catch (err) {
      alert('Delete failed: ' + err.message);
    }
  }, []);

  const handleDuplicate = useCallback(async (e, workflow) => {
    e.stopPropagation();
    try {
      await duplicateFlow(workflow.id);
      fetchWorkflows(); // Refresh list
    } catch (err) {
      alert('Duplicate failed: ' + err.message);
    }
  }, [fetchWorkflows]);

  const totalFlows = workflows.length;
  const activeFlows = workflows.filter((w) => w.status === 'active').length;
  const draftFlows = workflows.filter((w) => w.status === 'draft').length;

  return (
    <div style={styles.container}>
      <div style={styles.header}>
        <div>
          <h1 style={styles.title}>Workflows</h1>
          <p style={styles.subtitle}>Manage and monitor all your FMS workflows</p>
        </div>
        <button style={styles.openBtn} onClick={onCreateNew}>
          + Create New Workflow
        </button>
      </div>

      <div style={styles.statsRow}>
        <div style={styles.statCard}>
          <div style={styles.statValue}>{totalFlows}</div>
          <div style={styles.statLabel}>Total Workflows</div>
        </div>
        <div style={styles.statCard}>
          <div style={{ ...styles.statValue, color: '#6366f1' }}>{activeFlows}</div>
          <div style={styles.statLabel}>Active</div>
        </div>
        <div style={styles.statCard}>
          <div style={{ ...styles.statValue, color: '#71717a' }}>{draftFlows}</div>
          <div style={styles.statLabel}>Drafts</div>
        </div>
      </div>

      {loading ? (
        <div style={styles.emptyState}>
          <p>Loading workflows…</p>
        </div>
      ) : error ? (
        <div style={styles.emptyState}>
          <p style={{ color: '#f87171' }}>Could not load workflows: {error}</p>
          <button style={{ ...styles.openBtn, marginTop: 12 }} onClick={fetchWorkflows}>Retry</button>
        </div>
      ) : workflows.length > 0 ? (
        <div style={styles.grid}>
          {workflows.map((workflow) => (
            <WorkflowCard
              key={workflow.id}
              workflow={workflow}
              onOpen={onOpenWorkflow}
              onDelete={handleDelete}
              onDuplicate={handleDuplicate}
            />
          ))}
        </div>
      ) : (
        <div style={styles.emptyState}>
          <p>No workflows yet. Create your first workflow to get started.</p>
        </div>
      )}
    </div>
  );
}
