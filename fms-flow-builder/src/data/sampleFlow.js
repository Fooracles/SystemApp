import { NODE_TYPES, STEP_STATUS, EDGE_CONDITIONS, START_NODE_ID, END_NODE_ID, createNodeId, createEdgeId } from './flowModel';

/**
 * Sample flow JSON for "Load Sample Flow". Demonstrates step nodes with Start/End circles.
 */
export function getSampleFlow() {
  const node1 = createNodeId();
  const node2 = createNodeId();
  const node3 = createNodeId();
  const node4 = createNodeId();
  const node5 = createNodeId();

  return {
    flow: { id: 'sample-flow-1', name: 'Sample Approval Flow', status: 'draft' },
    nodes: [
      { id: START_NODE_ID, type: 'start', data: { label: 'Start' }, position: { x: 280, y: 0 } },
      {
        id: node1,
        type: NODE_TYPES.STEP,
        data: {
          stepName: 'Submit Request',
          stepCode: 'SUBMIT',
          stepType: NODE_TYPES.STEP,
          status: STEP_STATUS.COMPLETED,
          assignerRole: 'admin',
          stepOwnerRole: 'requester',
          allowSkip: false,
          allowReassignment: false,
          timeRules: { plannedDuration: 0, skipSundays: false, skipHolidays: false },
          validationRules: { commentRequired: false, attachmentRequired: false, decisionRequired: false },
        },
        position: { x: 250, y: 100 },
      },
      {
        id: node2,
        type: NODE_TYPES.STEP,
        data: {
          stepName: 'Manager Review',
          stepCode: 'MGR_REVIEW',
          stepType: NODE_TYPES.STEP,
          status: STEP_STATUS.IN_PROGRESS,
          assignerRole: 'system',
          stepOwnerRole: 'manager',
          allowSkip: false,
          allowReassignment: true,
          timeRules: { plannedDuration: 48, skipSundays: true, skipHolidays: true },
          validationRules: { commentRequired: true, attachmentRequired: false, decisionRequired: true },
        },
        position: { x: 250, y: 260 },
      },
      {
        id: node3,
        type: NODE_TYPES.STEP,
        data: {
          stepName: 'Process Approval',
          stepCode: 'PROCESS',
          stepType: NODE_TYPES.STEP,
          status: STEP_STATUS.PENDING,
          assignerRole: 'manager',
          stepOwnerRole: 'admin',
          allowSkip: false,
          allowReassignment: false,
          timeRules: { plannedDuration: 24, skipSundays: false, skipHolidays: true },
          validationRules: { commentRequired: false, attachmentRequired: false, decisionRequired: false },
        },
        position: { x: 250, y: 420 },
      },
      {
        id: node4,
        type: NODE_TYPES.STEP,
        data: {
          stepName: 'Final Verification',
          stepCode: 'VERIFY',
          stepType: NODE_TYPES.STEP,
          status: STEP_STATUS.PENDING,
          assignerRole: 'admin',
          stepOwnerRole: 'admin',
          allowSkip: false,
          allowReassignment: false,
          timeRules: { plannedDuration: 12, skipSundays: false, skipHolidays: false },
          validationRules: { commentRequired: false, attachmentRequired: false, decisionRequired: false },
        },
        position: { x: 250, y: 580 },
      },
      { id: END_NODE_ID, type: 'end', data: { label: 'End' }, position: { x: 280, y: 740 } },
    ],
    edges: [
      { id: createEdgeId(), source: START_NODE_ID, target: node1, condition: EDGE_CONDITIONS.DEFAULT },
      { id: createEdgeId(), source: node1, target: node2, condition: EDGE_CONDITIONS.DEFAULT },
      { id: createEdgeId(), source: node2, target: node3, condition: EDGE_CONDITIONS.DEFAULT },
      { id: createEdgeId(), source: node3, target: node4, condition: EDGE_CONDITIONS.DEFAULT },
      { id: createEdgeId(), source: node4, target: END_NODE_ID, condition: EDGE_CONDITIONS.DEFAULT },
    ],
  };
}
