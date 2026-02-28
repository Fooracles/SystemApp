/**
 * Frontend data model for FMS Flow (design-time only).
 * All business rules live in this JSON shape; no hardcoded logic.
 *
 * Backend API plug-in points (later):
 * - POST /api/flows → save flow (send this JSON)
 * - GET /api/flows/:id → load flow (receive this JSON)
 * - GET /api/roles, /api/holidays → for dropdowns in modal
 */

export const NODE_TYPES = {
  START: 'start',
  STEP: 'step',
  DECISION: 'decision',
  TARGET: 'target',
  END: 'end',
};

// Fixed IDs for the singleton start/end nodes
export const START_NODE_ID = '__start__';
export const END_NODE_ID = '__end__';

export const STEP_STATUS = {
  PENDING: 'pending',
  IN_PROGRESS: 'in_progress',
  COMPLETED: 'completed',
};

/** Edge condition: default | yes | no (yes/no for decision nodes) */
export const EDGE_CONDITIONS = {
  DEFAULT: 'default',
  YES: 'yes',
  NO: 'no',
};

/**
 * Flow document (design-time representation).
 * @typedef {Object} FlowMeta
 * @property {string} id
 * @property {string} name
 * @property {string} status
 *
 * @typedef {Object} NodeData
 * @property {string} stepName
 * @property {string} stepCode
 * @property {string} stepType - NODE_TYPES
 * @property {string} status - STEP_STATUS (display only)
 * @property {string} [assignerRole]
 * @property {string} [stepOwnerRole]
 * @property {boolean} allowSkip
 * @property {boolean} allowReassignment
 * @property {Object} [timeRules]
 * @property {number} [timeRules.plannedDuration]
 * @property {boolean} [timeRules.skipSundays]
 * @property {boolean} [timeRules.skipHolidays]
 * @property {Object} [validationRules]
 * @property {boolean} [validationRules.commentRequired]
 * @property {boolean} [validationRules.attachmentRequired]
 * @property {boolean} [validationRules.decisionRequired]
 * @property {Object} [targetConfig] - only for target nodes
 * @property {number} [targetConfig.targetValue]
 *
 * @typedef {{ id: string, type: string, data: NodeData, position: { x: number, y: number } }} FlowNode
 * @typedef {{ id: string, source: string, target: string, condition?: string }} FlowEdge
 *
 * @typedef {Object} FlowDocument
 * @property {FlowMeta} flow
 * @property {FlowNode[]} nodes
 * @property {FlowEdge[]} edges
 */

export function createEmptyFlow() {
  return {
    flow: { id: '', name: 'Untitled Flow', status: 'draft' },
    nodes: [
      { id: START_NODE_ID, type: 'start', data: { label: 'Start' }, position: { x: 250, y: 40 } },
      { id: END_NODE_ID, type: 'end', data: { label: 'End' }, position: { x: 250, y: 200 } },
    ],
    edges: [
      { id: 'edge_start_end', source: START_NODE_ID, target: END_NODE_ID, condition: 'default' },
    ],
  };
}

export function createDefaultNodeData(stepType) {
  const base = {
    stepName: 'New Step',
    stepCode: `STEP_${Date.now()}`,
    stepType,
    status: STEP_STATUS.PENDING,
    assignerRole: '',
    stepOwnerRole: '',
    allowSkip: false,
    allowReassignment: false,
    timeRules: { plannedDuration: 0, skipSundays: false, skipHolidays: false },
    validationRules: { commentRequired: false, attachmentRequired: false, decisionRequired: false },
  };
  if (stepType === NODE_TYPES.TARGET) {
    base.targetConfig = { targetValue: 0 };
  }
  return base;
}

export function createNodeId() {
  return `node_${Date.now()}_${Math.random().toString(36).slice(2, 9)}`;
}

export function createEdgeId() {
  return `edge_${Date.now()}_${Math.random().toString(36).slice(2, 9)}`;
}
