import React, { useState } from 'react';

const styles = {
  panel: {
    flex: 1,
    minWidth: 0,
    display: 'flex',
    flexDirection: 'column',
    background: '#18181b',
    borderTop: '1px solid #2a2a35',
    borderRight: '1px solid #2a2a35',
  },
  header: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: '10px 14px',
    borderBottom: '1px solid #2a2a35',
    fontSize: 13,
    fontWeight: 600,
    color: '#f4f4f5',
  },
  sessionId: { fontSize: 11, color: '#71717a', fontWeight: 400 },
  refresh: { background: 'none', border: 'none', color: '#71717a', cursor: 'pointer', padding: 4 },
  body: { flex: 1, overflow: 'auto', padding: 14, fontSize: 13, color: '#a1a1aa', lineHeight: 1.6 },
  inputWrap: {
    display: 'flex',
    alignItems: 'center',
    gap: 8,
    padding: 12,
    borderTop: '1px solid #2a2a35',
    background: '#0f0f14',
  },
  input: {
    flex: 1,
    padding: '10px 14px',
    background: '#27272a',
    border: '1px solid #3f3f46',
    borderRadius: 8,
    color: '#f4f4f5',
    fontSize: 13,
  },
  send: { width: 36, height: 36, borderRadius: 8, background: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)', border: 'none', color: '#fff', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' },
};

export function ChatPanel() {
  const [message, setMessage] = useState('');
  const sessionId = 'Session ' + Math.random().toString(16).slice(2, 18);

  return (
    <div style={styles.panel}>
      <div style={styles.header}>
        <span>Chat</span>
        <span style={styles.sessionId}>{sessionId}</span>
        <button style={styles.refresh} title="Refresh">↻</button>
      </div>
      <div style={styles.body}>
        <p style={{ margin: '0 0 12px' }}>Design-time only. Use this area for notes or future chat integration.</p>
        <p style={{ margin: 0, fontSize: 12, color: '#71717a' }}>Type a message below to simulate chat (UI only).</p>
      </div>
      <div style={styles.inputWrap}>
        <button type="button" style={{ ...styles.send, background: 'transparent', color: '#71717a' }} title="Previous">↑</button>
        <input
          type="text"
          placeholder="Type a message, or press 'up' arrow for previous one"
          value={message}
          onChange={(e) => setMessage(e.target.value)}
          style={styles.input}
        />
        <button type="button" style={{ ...styles.send, background: 'transparent', color: '#71717a' }} title="Next">↓</button>
        <button type="button" style={styles.send} title="Send">→</button>
      </div>
    </div>
  );
}
