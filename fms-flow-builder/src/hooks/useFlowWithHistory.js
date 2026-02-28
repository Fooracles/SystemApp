import { useReducer, useCallback } from 'react';
import { flowReducer, ACTIONS } from '../store/flowReducer';

const MAX_HISTORY = 50;

// Actions that should be tracked in history (meaningful changes)
const TRACKED_ACTIONS = new Set([
  ACTIONS.LOAD_FLOW,
  ACTIONS.ADD_NODE,
  ACTIONS.UPDATE_NODE,
  ACTIONS.REMOVE_NODE,
  ACTIONS.ADD_EDGE,
  ACTIONS.REMOVE_EDGE,
  ACTIONS.UPDATE_FLOW_META,
  ACTIONS.INSERT_NODE_ON_EDGE,
]);

function historyReducer(state, action) {
  if (action.type === 'UNDO') {
    if (state.index <= 0) return state;
    return { ...state, index: state.index - 1, flow: state.history[state.index - 1] };
  }
  if (action.type === 'REDO') {
    if (state.index >= state.history.length - 1) return state;
    return { ...state, index: state.index + 1, flow: state.history[state.index + 1] };
  }
  
  const nextFlow = flowReducer(state.flow, action);
  
  // Only track meaningful actions in history (skip position-only changes)
  if (!TRACKED_ACTIONS.has(action.type)) {
    return { ...state, flow: nextFlow };
  }
  
  const history = state.history.slice(0, state.index + 1);
  history.push(nextFlow);
  const trimmed = history.length > MAX_HISTORY ? history.slice(-MAX_HISTORY) : history;
  return { ...state, flow: nextFlow, history: trimmed, index: trimmed.length - 1 };
}

export function useFlowWithHistory(initialFlow) {
  const [state, dispatchHistory] = useReducer(historyReducer, {
    flow: initialFlow,
    history: [initialFlow],
    index: 0,
  });

  const dispatch = useCallback((action) => {
    dispatchHistory(action);
  }, []);

  const undo = useCallback(() => dispatchHistory({ type: 'UNDO' }), []);
  const redo = useCallback(() => dispatchHistory({ type: 'REDO' }), []);
  const canUndo = state.index > 0;
  const canRedo = state.index < state.history.length - 1;

  return {
    flowState: state.flow,
    dispatch,
    undo,
    redo,
    canUndo,
    canRedo,
  };
}
