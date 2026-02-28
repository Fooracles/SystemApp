import React, { useState, useCallback, useEffect } from 'react';

const MIN_WIDTH = 46;
const MAX_WIDTH = 130;
const DEFAULT_WIDTH = 46;
const EXPANDED_THRESHOLD = 100;

const getStyles = (isExpanded) => ({
  wrapper: {
    position: 'relative',
    display: 'flex',
    flexShrink: 0,
  },
  sidebar: {
    background: '#0a0a0f',
    borderRight: '1px solid #2a2a35',
    display: 'flex',
    flexDirection: 'column',
    alignItems: isExpanded ? 'stretch' : 'center',
    padding: '12px 8px',
    flexShrink: 0,
    overflow: 'hidden',
  },
  nav: {
    display: 'flex',
    flexDirection: 'column',
    gap: 4,
    flex: 1,
  },
  iconBtn: {
    minWidth: 40,
    height: 40,
    display: 'flex',
    alignItems: 'center',
    justifyContent: isExpanded ? 'flex-start' : 'center',
    gap: 10,
    padding: isExpanded ? '0 10px' : '0',
    background: 'transparent',
    border: 'none',
    color: '#71717a',
    borderRadius: 8,
    cursor: 'pointer',
    whiteSpace: 'nowrap',
    overflow: 'hidden',
    transition: 'background 0.15s',
  },
  iconBtnHover: {
    background: '#27272a',
  },
  iconBtnActive: {
    background: 'rgba(99, 102, 241, 0.15)',
    color: '#8b5cf6',
  },
  iconSpan: {
    fontSize: 18,
    width: 20,
    textAlign: 'center',
    flexShrink: 0,
  },
  labelSpan: {
    fontSize: 12,
    fontWeight: 500,
    color: '#71717a',
    overflow: 'hidden',
    textOverflow: 'ellipsis',
  },
  bottom: {
    marginTop: 'auto',
    display: 'flex',
    flexDirection: 'column',
    gap: 4,
    paddingTop: 12,
    borderTop: '1px solid #2a2a35',
  },
  resizeHandle: {
    position: 'absolute',
    top: 0,
    right: 0,
    width: 6,
    height: '100%',
    cursor: 'ew-resize',
    background: 'transparent',
    zIndex: 10,
    transition: 'background 0.15s',
  },
  resizeHandleHover: {
    background: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)',
  },
});

function NavButton({ icon, label, onClick, active, isExpanded, styles }) {
  const [hover, setHover] = useState(false);
  return (
    <button
      style={{
        ...styles.iconBtn,
        ...(active ? styles.iconBtnActive : {}),
        ...(hover && !active ? styles.iconBtnHover : {}),
      }}
      title={label}
      aria-label={label}
      onClick={onClick}
      onMouseEnter={() => setHover(true)}
      onMouseLeave={() => setHover(false)}
    >
      <span style={{ ...styles.iconSpan, ...(active ? { color: '#8b5cf6' } : {}) }}>{icon}</span>
      {isExpanded && <span style={{ ...styles.labelSpan, ...(active ? { color: '#8b5cf6' } : {}) }}>{label}</span>}
    </button>
  );
}

export function NavigationSidebar({ currentView, onViewChange, onShowToast }) {
  const [width, setWidth] = useState(DEFAULT_WIDTH);
  const [isResizing, setIsResizing] = useState(false);
  const [isHovering, setIsHovering] = useState(false);

  const isExpanded = width >= EXPANDED_THRESHOLD;
  const styles = getStyles(isExpanded);

  const handleMouseDown = useCallback((e) => {
    e.preventDefault();
    setIsResizing(true);
  }, []);

  useEffect(() => {
    if (!isResizing) return;

    const handleMouseMove = (e) => {
      const newWidth = e.clientX;
      setWidth(Math.min(MAX_WIDTH, Math.max(MIN_WIDTH, newWidth)));
    };

    const handleMouseUp = () => {
      setIsResizing(false);
    };

    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseup', handleMouseUp);
    document.body.style.cursor = 'ew-resize';
    document.body.style.userSelect = 'none';

    return () => {
      document.removeEventListener('mousemove', handleMouseMove);
      document.removeEventListener('mouseup', handleMouseUp);
      document.body.style.cursor = '';
      document.body.style.userSelect = '';
    };
  }, [isResizing]);

  return (
    <div style={{ ...styles.wrapper, width }}>
      <aside style={{ ...styles.sidebar, width }}>
        <nav style={styles.nav}>
          <NavButton
            icon="⌂"
            label="Home"
            onClick={() => onViewChange('home')}
            active={currentView === 'home'}
            isExpanded={isExpanded}
            styles={styles}
          />
          <NavButton
            icon="✎"
            label="Editor"
            onClick={() => onViewChange('editor')}
            active={currentView === 'editor'}
            isExpanded={isExpanded}
            styles={styles}
          />
          <NavButton
            icon="🧩"
            label="Form Builder"
            onClick={() => onViewChange('formBuilder')}
            active={currentView === 'formBuilder'}
            isExpanded={isExpanded}
            styles={styles}
          />
          <NavButton
            icon="📝"
            label="Submit Form"
            onClick={() => onViewChange('formSubmit')}
            active={currentView === 'formSubmit'}
            isExpanded={isExpanded}
            styles={styles}
          />
          <NavButton
            icon="✓"
            label="My Tasks"
            onClick={() => onViewChange('myTasks')}
            active={currentView === 'myTasks'}
            isExpanded={isExpanded}
            styles={styles}
          />
        </nav>
        <div style={styles.bottom}>
          <NavButton
            icon="⚙"
            label="Settings"
            onClick={() => onViewChange('settings')}
            active={currentView === 'settings'}
            isExpanded={isExpanded}
            styles={styles}
          />
        </div>
      </aside>
      <div
        style={{
          ...styles.resizeHandle,
          ...(isHovering || isResizing ? styles.resizeHandleHover : {}),
        }}
        onMouseDown={handleMouseDown}
        onMouseEnter={() => setIsHovering(true)}
        onMouseLeave={() => !isResizing && setIsHovering(false)}
      />
    </div>
  );
}
