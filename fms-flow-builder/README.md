# FMS Flow Builder (Admin)

React-based visual workflow designer inspired by n8n. **Design-time only** — no execution logic.

## Run

```bash
cd fms-flow-builder
npm install
npm run dev
```

Open the URL shown (e.g. http://localhost:5173).

## Component structure

- **App.jsx** — Root: `useReducer(flowReducer)`, flow state, modal open/close, validation state. Renders Toolbar, FlowCanvas, ValidationPanel, StepEditModal.
- **Toolbar** — Top bar: Add Step / Add Decision / Add Target Step, Validate Flow, Save Flow, Load Sample Flow. Reads `validationResult` for valid/invalid indicator.
- **FlowCanvas** — Wraps `@xyflow/react`: grid background, pan/zoom, controls, minimap. Converts document `nodes`/`edges` to React Flow format; on connect/drag/delete dispatches to reducer. Double-click node opens edit modal.
- **nodes/StepNode.jsx, DecisionNode.jsx, TargetNode.jsx** — Custom node UIs: step name, code, type badge, status (Pending / In Progress / Completed). Decision has two source handles (Yes / No).
- **edges/ConditionEdge.jsx** — Custom edge with optional Yes/No label; green for Yes, red for No.
- **StepEditModal** — Reusable modal: step name, code, type, assigner/owner roles, allow skip/reassignment, time rules, validation rules, target config (for target nodes). Save writes back via `onSave(data)` → `UPDATE_NODE`.
- **ValidationPanel** — Side panel: “Validate Flow” button and list of validation errors (or success).

## State shape

Single source of truth in `flowState` (reducer state):

```js
{
  flow: { id, name, status },
  nodes: [ { id, type, data, position } ],
  edges: [ { id, source, target, condition } ]
}
```

- `data` on nodes holds step name, code, status, roles, time/validation rules, targetConfig (for target nodes).
- `condition` on edges: `default` | `yes` | `no` (yes/no for decision node outputs).

All business rules are expressed in this JSON; no hardcoded logic outside validation rules.

## Where backend APIs will plug in

- **Save Flow** — Replace `console.log` in `App.jsx` with e.g. `POST /api/flows` sending `{ flow, nodes, edges }`.
- **Load Flow** — Add “Load flow” by id; `GET /api/flows/:id` returning same JSON; dispatch `LOAD_FLOW` with response.
- **Roles / holidays** — In `StepEditModal`, replace `MOCK_ROLES` with data from `GET /api/roles`; add holiday list from `GET /api/holidays` if needed for time rules.

No execution or runtime engine in this UI.
