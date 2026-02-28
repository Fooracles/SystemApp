import React, { useState } from 'react';

const styles = {
  panel: {
    width: '100%',
    minWidth: 0,
    flex: 1,
    display: 'flex',
    flexDirection: 'column',
    background: 'transparent',
    overflow: 'hidden',
  },
  header: { padding: '10px 14px', borderBottom: '1px solid #2a2a35', fontSize: 13, fontWeight: 600, color: '#f4f4f5' },
  tree: { flex: 1, overflow: 'auto', padding: '8px 0', fontSize: 12 },
  treeItem: { padding: '6px 14px', cursor: 'pointer', color: '#a1a1aa', display: 'flex', alignItems: 'center', gap: 6 },
  treeItemActive: { background: '#27272a', color: '#f4f4f5' },
  treeExpand: { width: 16, textAlign: 'center' },
  detail: { padding: 14, borderTop: '1px solid #2a2a35', fontSize: 12, color: '#71717a' },
  detailTitle: { fontSize: 11, textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: 6, color: '#71717a' },
  json: { background: '#0f0f14', padding: 10, borderRadius: 6, fontFamily: 'monospace', fontSize: 11, overflow: 'auto', color: '#a1a1aa' },
};

export function LogsPanel({ nodes = [] }) {
  // Filter out Start/End circle nodes — only show actual steps
  const stepNodes = nodes.filter((n) => n.type !== 'start' && n.type !== 'end');
  const [expanded, setExpanded] = useState(true);
  const [selectedId, setSelectedId] = useState(stepNodes[0]?.id ?? null);
  const selected = stepNodes.find((n) => n.id === selectedId);

  return (
    <div style={styles.panel}>
      <div style={styles.header}>Latest Logs from flow</div>
      <div style={styles.tree}>
        {stepNodes.length === 0 ? (
          <div style={{ padding: 14, color: '#71717a', fontSize: 12 }}>No steps yet. Add steps to see log tree.</div>
        ) : (
          <>
            <div
              style={styles.treeItem}
              onClick={() => setExpanded((e) => !e)}
            >
              <span style={styles.treeExpand}>{expanded ? '▼' : '▶'}</span>
              <span>Flow steps</span>
            </div>
            {expanded && stepNodes.map((n) => (
              <div
                key={n.id}
                style={{
                  ...styles.treeItem,
                  paddingLeft: 28,
                  ...(selectedId === n.id ? styles.treeItemActive : {}),
                }}
                onClick={() => setSelectedId(n.id)}
              >
                <span style={styles.treeExpand}>•</span>
                <span>{n.data?.stepName || n.id}</span>
              </div>
            ))}
          </>
        )}
      </div>
      {selected && (
        <div style={styles.detail}>
          <div style={styles.detailTitle}>Selected node</div>
          <div style={{ marginBottom: 8 }}>{selected.data?.stepName || selected.id}</div>
          <div style={styles.detailTitle}>Input (design-time)</div>
          <pre style={styles.json}>
            {JSON.stringify({ stepCode: selected.data?.stepCode, stepType: selected.data?.stepType }, null, 2)}
          </pre>
        </div>
      )}
    </div>
  );
}
