import React, { useState, useCallback, useEffect, useRef } from 'react';
import { ACTIONS } from './store/flowReducer';
import { createEmptyFlow } from './data/flowModel';
import { getSampleFlow } from './data/sampleFlow';
import { validateFlow } from './utils/validation';
import { useFlowWithHistory } from './hooks/useFlowWithHistory';
import { FlowCanvas } from './components/FlowCanvas';
import { TopBar } from './components/TopBar';
import { LogsPanel } from './components/LogsPanel';
import { StepEditModal } from './components/StepEditModal';
import { NavigationSidebar } from './components/NavigationSidebar';
import { HomeView } from './components/HomeView';
import { SettingsView } from './components/SettingsView';
import { FormBuilderView } from './components/FormBuilderView';
import { FormSubmissionView } from './components/FormSubmissionView';
import { MyTasksView } from './components/MyTasksView';
import { ExecutionsPanel } from './components/ExecutionsPanel';
import { TestsPanel } from './components/TestsPanel';
import { loadSettings, saveSettings } from './store/settingsStore';
import { saveFlow as apiSaveFlow, getFlow as apiGetFlow } from './services/flowApi';

const initialFlow = createEmptyFlow();

const RIGHT_PANEL_MIN = 200;
const RIGHT_PANEL_MAX = 500;
const RIGHT_PANEL_DEFAULT = 280;
const VALID_VIEWS = new Set(['home', 'editor', 'settings', 'formBuilder', 'formSubmit', 'myTasks']);
const VALID_TABS = new Set(['editor', 'executions', 'tests']);

function resolveTabFromQuery() {
  const fromQuery = new URLSearchParams(window.location.search).get('tab');
  if (fromQuery && VALID_TABS.has(fromQuery)) return fromQuery;
  return 'editor';
}

function resolveInitialView() {
  const fromWindow = window.__FMS_INITIAL_VIEW;
  if (fromWindow && VALID_VIEWS.has(fromWindow)) return fromWindow;
  const fromQuery = new URLSearchParams(window.location.search).get('view');
  if (fromQuery && VALID_VIEWS.has(fromQuery)) return fromQuery;
  return 'editor';
}

export default function App() {
  const { flowState, dispatch, undo, redo, canUndo, canRedo } = useFlowWithHistory(initialFlow);
  const [editingNode, setEditingNode] = useState(null);
  const [validationResult, setValidationResult] = useState(null);
  const [activeTab, setActiveTab] = useState(resolveTabFromQuery);
  const [flowActive, setFlowActive] = useState(false);
  const [lastSavedAt, setLastSavedAt] = useState(null);
  const [toast, setToast] = useState(null);
  const [rightPanelWidth, setRightPanelWidth] = useState(RIGHT_PANEL_DEFAULT);
  const [isResizingRight, setIsResizingRight] = useState(false);
  const [rightHandleHover, setRightHandleHover] = useState(false);
  const [currentView, setCurrentView] = useState(resolveInitialView);
  const [settings, setSettings] = useState(() => loadSettings());
  const autoSaveRef = useRef(null);

  // Keep URL query in sync with current React route/section.
  useEffect(() => {
    const next = new URL(window.location.href);
    if (VALID_VIEWS.has(currentView)) {
      next.searchParams.set('view', currentView);
    } else {
      next.searchParams.delete('view');
    }
    if (currentView === 'editor' && VALID_TABS.has(activeTab)) {
      next.searchParams.set('tab', activeTab);
    } else {
      next.searchParams.delete('tab');
    }
    const current = window.location.pathname + window.location.search + window.location.hash;
    const target = next.pathname + next.search + next.hash;
    if (current !== target) {
      window.history.replaceState(window.history.state, '', target);
    }
  }, [currentView, activeTab]);

  // Handle browser back/forward for query-driven view restoration.
  useEffect(() => {
    const onPopState = () => {
      const viewFromUrl = new URLSearchParams(window.location.search).get('view');
      const tabFromUrl = new URLSearchParams(window.location.search).get('tab');
      setCurrentView(viewFromUrl && VALID_VIEWS.has(viewFromUrl) ? viewFromUrl : 'editor');
      setActiveTab(tabFromUrl && VALID_TABS.has(tabFromUrl) ? tabFromUrl : 'editor');
    };
    window.addEventListener('popstate', onPopState);
    return () => window.removeEventListener('popstate', onPopState);
  }, []);

  // Right panel resize handling
  useEffect(() => {
    if (!isResizingRight) return;

    const handleMouseMove = (e) => {
      const newWidth = window.innerWidth - e.clientX;
      setRightPanelWidth(Math.min(RIGHT_PANEL_MAX, Math.max(RIGHT_PANEL_MIN, newWidth)));
    };

    const handleMouseUp = () => {
      setIsResizingRight(false);
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
  }, [isResizingRight]);

  // Auto-save effect — calls real API when a flow id exists
  useEffect(() => {
    if (autoSaveRef.current) clearInterval(autoSaveRef.current);
    if (settings.autoSaveInterval > 0) {
      autoSaveRef.current = setInterval(() => {
        const payload = {
          flow: { ...flowState.flow, status: flowActive ? 'active' : 'draft' },
          nodes: flowState.nodes,
          edges: flowState.edges,
        };
        apiSaveFlow(payload)
          .then((res) => {
            // After first save, persist the id returned by the backend
            if (res.flow_id && String(res.flow_id) !== String(flowState.flow.id)) {
              dispatch({ type: ACTIONS.UPDATE_FLOW_META, payload: { id: String(res.flow_id) } });
            }
            setLastSavedAt(Date.now());
          })
          .catch((err) => console.warn('Auto-save failed (backend may be offline):', err.message));
      }, settings.autoSaveInterval);
    }
    return () => { if (autoSaveRef.current) clearInterval(autoSaveRef.current); };
  }, [settings.autoSaveInterval, flowState, flowActive, dispatch]);

  // Apply CSS variables when theme settings change
  useEffect(() => {
    const root = document.documentElement;
    root.style.setProperty('--primary-color', settings.primaryColor);
    root.style.setProperty('--primary-color-light', settings.primaryColorLight);
    root.style.setProperty('--gradient-primary', `linear-gradient(135deg, ${settings.primaryColor} 0%, ${settings.primaryColorLight} 100%)`);
    root.style.setProperty('--primary-glow', `rgba(${parseInt(settings.primaryColor.slice(1,3),16)}, ${parseInt(settings.primaryColor.slice(3,5),16)}, ${parseInt(settings.primaryColor.slice(5,7),16)}, 0.3)`);
    // Node border radius
    const borderMap = { default: '12px', rounded: '20px', sharp: '4px' };
    root.style.setProperty('--node-border-radius', borderMap[settings.nodeBorderStyle] || '12px');
  }, [settings.primaryColor, settings.primaryColorLight, settings.nodeBorderStyle]);

  const handleSettingsChange = useCallback((newSettings) => {
    setSettings(newSettings);
    saveSettings(newSettings);
  }, []);

  const showToast = useCallback((msg) => {
    setToast(msg);
    setTimeout(() => setToast(null), 2500);
  }, []);

  const handleAddNode = useCallback(
    (stepType, position) => {
      const pos = position ?? {
        x: 100 + (flowState.nodes.length % 4) * 200,
        y: 100 + Math.floor(flowState.nodes.length / 4) * 120,
      };
      dispatch({
        type: ACTIONS.ADD_NODE,
        payload: {
          stepType,
          position: pos,
          defaults: { plannedDuration: settings.defaultPlannedDuration },
        },
      });
    },
    [flowState.nodes.length, dispatch, settings.defaultPlannedDuration]
  );

  const handleNodeEditSave = useCallback((data) => {
    if (!editingNode) return;
    dispatch({
      type: ACTIONS.UPDATE_NODE,
      payload: { nodeId: editingNode.id, data },
    });
    setEditingNode(null);
  }, [editingNode, dispatch]);

  const handleValidate = useCallback(() => {
    setValidationResult(validateFlow(flowState));
    showToast('Validation run');
  }, [flowState, showToast]);

  const handleSaveFlow = useCallback(async () => {
    if (settings.showValidationOnSave) {
      const result = validateFlow(flowState);
      if (!result.valid) {
        setValidationResult(result);
        showToast('Fix errors before saving');
        return;
      }
      setValidationResult(result);
    }
    const payload = {
      flow: { ...flowState.flow, status: flowActive ? 'active' : 'draft' },
      nodes: flowState.nodes,
      edges: flowState.edges,
    };
    try {
      const res = await apiSaveFlow(payload);
      if (res.flow_id && String(res.flow_id) !== String(flowState.flow.id)) {
        dispatch({ type: ACTIONS.UPDATE_FLOW_META, payload: { id: String(res.flow_id) } });
      }
      setLastSavedAt(Date.now());
      showToast('Flow saved (v' + (res.version || '?') + ')');
    } catch (err) {
      console.error('Save failed:', err);
      showToast('Save failed — ' + err.message);
    }
  }, [flowState, flowActive, showToast, settings.showValidationOnSave, dispatch]);

  const handleLoadSample = useCallback(() => {
    if (settings.confirmLoadSample) {
      if (!window.confirm('This will overwrite the current flow. Continue?')) return;
    }
    dispatch({ type: ACTIONS.LOAD_FLOW, payload: getSampleFlow() });
    setValidationResult(null);
    showToast('Sample flow loaded');
  }, [dispatch, showToast, settings.confirmLoadSample]);

  const handleShare = useCallback(() => {
    const url = window.location.origin + window.location.pathname + '?flow=' + (flowState.flow?.id || 'new');
    navigator.clipboard.writeText(url).then(() => showToast('Link copied to clipboard')).catch(() => showToast('Could not copy'));
  }, [flowState.flow?.id, showToast]);

  const handleFlowNameChange = useCallback((newName) => {
    dispatch({ type: ACTIONS.UPDATE_FLOW_META, payload: { name: newName } });
    showToast('Flow renamed');
  }, [dispatch, showToast]);

  const handleExport = useCallback(() => {
    const payload = { flow: flowState.flow, nodes: flowState.nodes, edges: flowState.edges };
    const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = (flowState.flow?.name || 'flow').replace(/\s+/g, '_') + '.json';
    a.click();
    URL.revokeObjectURL(a.href);
    showToast('Exported as JSON');
  }, [flowState, showToast]);

  const handleOpenWorkflow = useCallback(async (workflow) => {
    showToast(`Opening "${workflow.name}"...`);
    try {
      const data = await apiGetFlow(workflow.id);
      dispatch({ type: ACTIONS.LOAD_FLOW, payload: data });
      setFlowActive(data.flow?.status === 'active');
      setCurrentView('editor');
      setValidationResult(null);
    } catch (err) {
      console.error('Load flow failed:', err);
      showToast('Failed to open — ' + err.message);
    }
  }, [showToast, dispatch]);

  const handleCreateNewWorkflow = useCallback(() => {
    dispatch({ type: ACTIONS.LOAD_FLOW, payload: createEmptyFlow() });
    setCurrentView('editor');
    showToast('New workflow created');
  }, [dispatch, showToast]);

  return (
    <div style={{ display: 'flex', height: '100%', background: '#0f0f14' }}>
      {/* Navigation Sidebar - always visible */}
      <NavigationSidebar
        currentView={currentView}
        onViewChange={setCurrentView}
        onShowToast={showToast}
      />

      {/* Main content area */}
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
        {toast && (
          <div style={{ position: 'fixed', bottom: 24, left: '50%', transform: 'translateX(-50%)', padding: '10px 20px', background: '#18181b', border: `1px solid ${settings.primaryColor}`, borderRadius: 8, color: '#f4f4f5', fontSize: 13, zIndex: 1000, boxShadow: '0 4px 12px rgba(0,0,0,0.4)' }}>
            {toast}
          </div>
        )}

        {/* HOME VIEW */}
        {currentView === 'home' && (
          <HomeView
            onOpenWorkflow={handleOpenWorkflow}
            onCreateNew={handleCreateNewWorkflow}
          />
        )}

        {/* SETTINGS VIEW */}
        {currentView === 'settings' && (
          <SettingsView
            settings={settings}
            onSettingsChange={handleSettingsChange}
            onShowToast={showToast}
          />
        )}

        {/* EDITOR VIEW */}
        {currentView === 'editor' && (
          <>
            <TopBar
              flowName={flowState.flow?.name}
              onFlowNameChange={handleFlowNameChange}
              activeTab={activeTab}
              onTabChange={setActiveTab}
              flowActive={flowActive}
              onActiveToggle={() => setFlowActive((v) => !v)}
              onShare={handleShare}
              lastSavedAt={lastSavedAt}
              onSaveFlow={handleSaveFlow}
              onAddStep={() => handleAddNode('step')}
              onAddDecision={() => handleAddNode('decision')}
              onValidate={handleValidate}
              onLoadSample={handleLoadSample}
              validationResult={validationResult}
              settings={settings}
            />
            <div style={{ flex: 1, minHeight: 0, display: 'flex', position: 'relative', zIndex: 1 }}>
              {activeTab === 'editor' && (
                <>
                  <div style={{ flex: 1, minHeight: 0 }}>
                    <FlowCanvas
                      flowState={flowState}
                      dispatch={dispatch}
                      onNodeEdit={setEditingNode}
                      onUndo={undo}
                      onRedo={redo}
                      canUndo={canUndo}
                      canRedo={canRedo}
                      onExport={handleExport}
                      settings={settings}
                    />
                  </div>
                  <div style={{ position: 'relative', width: rightPanelWidth, flexShrink: 0, display: 'flex' }}>
                    {/* Resize handle */}
                    <div
                      style={{
                        position: 'absolute',
                        top: 0,
                        left: 0,
                        width: 6,
                        height: '100%',
                        cursor: 'ew-resize',
                        background: rightHandleHover || isResizingRight ? `linear-gradient(135deg, ${settings.primaryColor} 0%, ${settings.primaryColorLight} 100%)` : 'transparent',
                        zIndex: 10,
                        transition: 'background 0.15s',
                      }}
                      onMouseDown={(e) => { e.preventDefault(); setIsResizingRight(true); }}
                      onMouseEnter={() => setRightHandleHover(true)}
                      onMouseLeave={() => !isResizingRight && setRightHandleHover(false)}
                    />
                    <div style={{ flex: 1, borderLeft: '1px solid #2a2a35', display: 'flex', flexDirection: 'column', background: '#18181b', overflow: 'hidden' }}>
                      <LogsPanel nodes={flowState.nodes} />
                    </div>
                  </div>
                </>
              )}
              {activeTab === 'executions' && (
                <ExecutionsPanel flowId={flowState.flow?.id} />
              )}
              {activeTab === 'tests' && (
                <TestsPanel flowState={flowState} flowActive={flowActive} />
              )}
            </div>
          </>
        )}

        {currentView === 'formBuilder' && (
          <FormBuilderView onShowToast={showToast} />
        )}

        {currentView === 'formSubmit' && (
          <FormSubmissionView onShowToast={showToast} />
        )}

        {currentView === 'myTasks' && (
          <MyTasksView onShowToast={showToast} />
        )}

        {editingNode && (
          <StepEditModal
            node={editingNode}
            onSave={handleNodeEditSave}
            onClose={() => setEditingNode(null)}
          />
        )}
      </div>
    </div>
  );
}
