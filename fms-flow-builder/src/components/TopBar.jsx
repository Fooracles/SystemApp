import React, { useState, useRef, useEffect } from 'react';
import { NODE_TYPES } from '../data/flowModel';

const styles = {
  bar: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    height: 52,
    padding: '0 20px',
    background: '#0f0f14',
    borderBottom: '1px solid #2a2a35',
    flexShrink: 0,
    position: 'relative',
    zIndex: 500,
    overflow: 'visible',
  },
  left: { display: 'flex', alignItems: 'center', gap: 16 },
  logo: { display: 'flex', alignItems: 'center', gap: 10 },
  logoIcon: {
    width: 28,
    height: 28,
    borderRadius: 8,
    background: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  logoText: { fontSize: 16, fontWeight: 600, color: '#f4f4f5' },
  tag: {
    fontSize: 13,
    padding: '4px 10px',
    background: '#2a2a35',
    color: '#f4f4f5',
    borderRadius: 6,
    cursor: 'pointer',
    border: '1px solid transparent',
    transition: 'border-color 0.15s',
  },
  tagHover: {
    borderColor: '#3f3f46',
  },
  tagInput: {
    fontSize: 13,
    padding: '4px 10px',
    background: '#27272a',
    color: '#f4f4f5',
    borderRadius: 6,
    border: '1px solid #6366f1',
    outline: 'none',
    minWidth: 120,
    maxWidth: 200,
  },
  center: { display: 'flex', alignItems: 'center', gap: 4 },
  tab: { padding: '8px 14px', fontSize: 13, color: '#71717a', background: 'transparent', border: 'none', borderBottom: '2px solid transparent', cursor: 'pointer', borderRadius: 0 },
  tabActive: { color: '#8b5cf6', borderBottom: '2px solid #8b5cf6' },
  right: { display: 'flex', alignItems: 'center', gap: 12 },
  toggleWrap: { display: 'flex', alignItems: 'center', gap: 8 },
  toggleLabel: { fontSize: 12, fontWeight: 500, minWidth: 52 },
  toggleLabelActive: { color: '#8b5cf6' },
  toggleLabelInactive: { color: '#71717a' },
  toggle: {
    width: 36,
    height: 20,
    borderRadius: 10,
    border: 'none',
    cursor: 'pointer',
    position: 'relative',
    transition: 'background 0.2s',
    padding: 0,
  },
  toggleOn: { background: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)' },
  toggleOff: { background: '#3f3f46' },
  toggleKnob: {
    position: 'absolute',
    top: 2,
    width: 16,
    height: 16,
    borderRadius: '50%',
    background: '#fff',
    transition: 'left 0.2s',
    boxShadow: '0 1px 3px rgba(0,0,0,0.3)',
  },
  toggleKnobOn: { left: 18 },
  toggleKnobOff: { left: 2 },
  btn: { padding: '6px 12px', fontSize: 13, background: 'transparent', color: '#a1a1aa', border: '1px solid #3f3f46', borderRadius: 6, cursor: 'pointer' },
  btnIcon: { width: 32, height: 32, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'transparent', color: '#a1a1aa', border: 'none', borderRadius: 6, cursor: 'pointer' },
  menu: { position: 'absolute', top: '100%', right: 0, marginTop: 4, background: '#18181b', border: '1px solid #3f3f46', borderRadius: 8, boxShadow: '0 10px 40px rgba(0,0,0,0.4)', zIndex: 6000, minWidth: 180 },
  menuItem: { display: 'block', width: '100%', padding: '10px 14px', textAlign: 'left', background: 'none', border: 'none', color: '#f4f4f5', fontSize: 13, cursor: 'pointer' },
  menuItemDisabled: { display: 'block', width: '100%', padding: '10px 14px', textAlign: 'left', background: 'none', border: 'none', color: '#52525b', fontSize: 13, cursor: 'not-allowed' },
};

export function TopBar({
  flowName,
  onFlowNameChange,
  activeTab = 'editor',
  onTabChange,
  flowActive = false,
  onActiveToggle,
  onShare,
  lastSavedAt,
  onSaveFlow,
  onAddStep,
  onAddDecision,
  onValidate,
  onLoadSample,
  validationResult,
  settings = {},
}) {
  const [addOpen, setAddOpen] = useState(false);
  const [moreOpen, setMoreOpen] = useState(false);
  const [editingName, setEditingName] = useState(false);
  const [nameValue, setNameValue] = useState(flowName || 'Untitled Flow');
  const [nameHover, setNameHover] = useState(false);
  const nameInputRef = useRef(null);
  const isValid = validationResult?.valid ?? false;
  const isSaved = lastSavedAt != null;
  const pc = settings.primaryColor || '#6366f1';
  const pcl = settings.primaryColorLight || '#8b5cf6';
  const grad = `linear-gradient(135deg, ${pc} 0%, ${pcl} 100%)`;

  // Sync nameValue when flowName prop changes
  useEffect(() => {
    if (!editingName) {
      setNameValue(flowName || 'Untitled Flow');
    }
  }, [flowName, editingName]);

  // Focus input when entering edit mode
  useEffect(() => {
    if (editingName && nameInputRef.current) {
      nameInputRef.current.focus();
      nameInputRef.current.select();
    }
  }, [editingName]);

  const handleNameSubmit = () => {
    const trimmed = nameValue.trim();
    if (trimmed && trimmed !== flowName) {
      onFlowNameChange?.(trimmed);
    }
    setEditingName(false);
  };

  const handleNameKeyDown = (e) => {
    if (e.key === 'Enter') {
      handleNameSubmit();
    } else if (e.key === 'Escape') {
      setNameValue(flowName || 'Untitled Flow');
      setEditingName(false);
    }
  };

  const tabActiveStyle = { color: pcl, borderBottom: `2px solid ${pcl}` };
  const toggleLabelActiveStyle = { color: pcl };

  return (
    <header style={styles.bar}>
      <div style={styles.left}>
        <div style={styles.logo}>
          <div style={{ ...styles.logoIcon, background: grad }}>
            <svg width="16" height="16" viewBox="0 0 640 512" fill="#fff">
              <path d="M384 320H256c-17.67 0-32 14.33-32 32v128c0 17.67 14.33 32 32 32h128c17.67 0 32-14.33 32-32V352c0-17.67-14.33-32-32-32zM192 32c0-17.67-14.33-32-32-32H32C14.33 0 0 14.33 0 32v128c0 17.67 14.33 32 32 32h95.72l73.16 128.04C211.98 300.98 232.4 288 256 288h.28L192 175.51V128h224V64H192V32zM608 0H480c-17.67 0-32 14.33-32 32v128c0 17.67 14.33 32 32 32h128c17.67 0 32-14.33 32-32V32c0-17.67-14.33-32-32-32z"/>
            </svg>
          </div>
          <span style={styles.logoText}>FMS Builder</span>
        </div>
        {editingName ? (
          <input
            ref={nameInputRef}
            type="text"
            value={nameValue}
            onChange={(e) => setNameValue(e.target.value)}
            onBlur={handleNameSubmit}
            onKeyDown={handleNameKeyDown}
            style={{ ...styles.tagInput, borderColor: pc }}
            placeholder="Flow name"
          />
        ) : (
          <span
            style={{ ...styles.tag, ...(nameHover ? styles.tagHover : {}) }}
            onClick={() => setEditingName(true)}
            onMouseEnter={() => setNameHover(true)}
            onMouseLeave={() => setNameHover(false)}
            title="Click to edit flow name"
          >
            {flowName || 'Untitled Flow'}
          </span>
        )}
      </div>

      <div style={styles.center}>
        <button
          style={{ ...styles.tab, ...(activeTab === 'editor' ? tabActiveStyle : {}) }}
          onClick={() => onTabChange?.('editor')}
        >
          Editor
        </button>
        <button
          style={{ ...styles.tab, ...(activeTab === 'executions' ? tabActiveStyle : {}) }}
          onClick={() => onTabChange?.('executions')}
        >
          Executions
        </button>
        <button
          style={{ ...styles.tab, ...(activeTab === 'tests' ? tabActiveStyle : {}) }}
          onClick={() => onTabChange?.('tests')}
        >
          Tests
        </button>
      </div>

      <div style={styles.right}>
        <div style={styles.toggleWrap}>
          <span
            style={{
              ...styles.toggleLabel,
              ...(flowActive ? toggleLabelActiveStyle : styles.toggleLabelInactive),
            }}
          >
            {flowActive ? 'Active' : 'Inactive'}
          </span>
          <button
            style={{ ...styles.toggle, ...(flowActive ? { background: grad } : styles.toggleOff) }}
            title={flowActive ? 'Click to deactivate' : 'Click to activate'}
            aria-label="Toggle flow active"
            onClick={() => onActiveToggle?.()}
          >
            <span
              style={{
                ...styles.toggleKnob,
                ...(flowActive ? styles.toggleKnobOn : styles.toggleKnobOff),
              }}
            />
          </button>
        </div>
        <button style={styles.btn} onClick={() => onShare?.()} title="Copy share link">
          Share
        </button>
        <button
          style={styles.btn}
          onClick={() => onSaveFlow?.()}
          title={isSaved ? 'Save again' : 'Save flow'}
        >
          {isSaved ? 'Saved ✓' : 'Saved'}
        </button>

        <div style={{ position: 'relative', zIndex: 6000 }}>
          <button style={styles.btnIcon} onClick={() => setMoreOpen((v) => !v)} title="More">⋯</button>
          {moreOpen && (
            <>
              <div style={{ position: 'fixed', inset: 0, zIndex: 5999 }} onClick={() => setMoreOpen(false)} />
              <div style={styles.menu}>
                <button style={styles.menuItem} onClick={() => { onValidate?.(); setMoreOpen(false); }}>Validate Flow</button>
                <button style={styles.menuItem} onClick={() => { onSaveFlow?.(); setMoreOpen(false); }}>Save Flow</button>
                <button style={styles.menuItem} onClick={() => { onLoadSample?.(); setMoreOpen(false); }}>Load Sample</button>
                {validationResult && (
                  <div style={{ padding: '8px 14px', fontSize: 12, color: isValid ? pcl : '#f87171' }}>
                    {isValid ? '✓ Valid' : '✗ Invalid'}
                  </div>
                )}
              </div>
            </>
          )}
        </div>

        <div style={{ position: 'relative', zIndex: 6000 }}>
          <button style={{ ...styles.btnIcon, background: grad, color: '#fff' }} onClick={() => setAddOpen((v) => !v)} title="Add node">+</button>
          {addOpen && (
            <>
              <div style={{ position: 'fixed', inset: 0, zIndex: 5999 }} onClick={() => setAddOpen(false)} />
              <div style={styles.menu}>
                <button style={styles.menuItem} onClick={() => { onAddStep?.(NODE_TYPES.STEP); setAddOpen(false); }}>Add Step</button>
                <button style={styles.menuItemDisabled} disabled title="Coming soon">Add Decision</button>
              </div>
            </>
          )}
        </div>
      </div>
    </header>
  );
}
