# Fms-flow-builder audit
_Exported on 2/23/2026 at 18:21:02 GMT+5:30 from Cursor (2.4.21)_

---

**User**

analyze the whole fms-flow-builder, give an brutal honest audit..

---

**Cursor**

I'll do a thorough audit of the FMS Flow Builder. Let me start by finding all related files and understanding the full scope.

Good, I have the full layout. Now let me read every source file in depth.

Now let me read all the components.

Now let me read the remaining node components and the backend files.

Now let me read the backend PHP files.

Let me check the database schema for the flow tables.

I've now read every single file in the FMS Flow Builder -- all 30+ source files across frontend, backend, and database schema. Here's the brutally honest audit.

---

# FMS Flow Builder -- Brutal Honest Audit

## Overview

A React + Vite visual workflow designer (inspired by n8n) embedded into a PHP/MySQL admin panel via iframe or direct JS embed. Uses `@xyflow/react` for the canvas. ~30 source files, ~2,800 lines of custom code.

---

## ARCHITECTURE: 6/10

**What works:**
- Clean separation: React SPA for the builder, PHP for the API, clear data contract between them.
- The "dual mode" embed strategy (`fms_builder.php` auto-detects dev server vs built assets) is clever and practical for XAMPP-based development.
- `useReducer` + history wrapper is a solid pattern for undo/redo.
- The adapter layer (`reactFlowAdapter.js`) that translates between your document model and React Flow's model is good -- keeps your domain model independent.

**What doesn't:**
- **God component: `App.jsx` is doing too much.** It manages flow state, settings, routing (view switching), auto-save, validation, toasts, resize logic, API calls, and rendering. This is a classic "fat controller" anti-pattern. Should be at least 3-4 separate concerns.
- **No router.** View switching (`currentView`) is a manual `useState` string. As views grow (and they will), this becomes a maintenance trap.
- **No context/provider pattern.** Settings, dispatch, and toast are drilled through 3-4 levels of props. This will only get worse.
- **The `Toolbar.jsx` component is dead code.** It's defined but never imported or rendered in `App.jsx`. The TopBar replaced it, but nobody cleaned up.
- **`ChatPanel.jsx` and `TeamView.jsx` are pure mock/placeholder components** with hardcoded data that do nothing functional. They're dead weight shipping to production.
- **`useResizable.js` hook is broken and unused.** The `handleMouseMove` body for horizontal direction is empty (lines 24-28 are a comment). The actual resize logic in `NavigationSidebar` and `App.jsx` is reimplemented inline, duplicated.

---

## STATE MANAGEMENT: 7/10

**What works:**
- `flowReducer.js` is well-structured with clear action types.
- The history hook (`useFlowWithHistory`) correctly separates "tracked" (meaningful) vs "untracked" (position drag) changes. Smart.
- Node removal auto-bridges edges (predecessor to successor reconnection). That's thoughtful.
- `INSERT_NODE_ON_EDGE` action is clean.

**What doesn't:**
- **Auto-save fires on the entire `flowState` dependency.** Every node drag (position change) resets the auto-save interval because `flowState` is a new object reference on every dispatch. This means auto-save is effectively re-triggered on every mouse movement during a drag. The `setInterval` is cleared and recreated constantly.

```65:86:fms-flow-builder/src/App.jsx
  useEffect(() => {
    if (autoSaveRef.current) clearInterval(autoSaveRef.current);
    if (settings.autoSaveInterval > 0) {
      autoSaveRef.current = setInterval(() => {
        // ...saves...
      }, settings.autoSaveInterval);
    }
    return () => { if (autoSaveRef.current) clearInterval(autoSaveRef.current); };
  }, [settings.autoSaveInterval, flowState, flowActive, dispatch]);
```

The `flowState` dependency here is a bug. Every reducer dispatch creates a new state object, which restarts the interval. The auto-save will never actually fire at its configured interval unless you stop touching the canvas entirely for that duration.

- **`NODES_CHANGE` and `EDGES_CHANGE` actions bypass the document model.** They accept raw React Flow nodes/edges after `applyNodeChanges`, which means the reducer gets "React Flow format" data passed through `fromReactFlowNodes`. This round-trip works but is fragile -- a format mismatch would be silent.
- **No debouncing on node position updates.** Every pixel of a drag dispatches through the reducer and creates a new state object.

---

## SECURITY: 5/10

**What works:**
- Auth guard (isLoggedIn + isAdmin) on the PHP handler.
- Prepared statements for all database queries (SQL injection protection).
- CORS limited to Vite dev server origins.
- `htmlspecialchars` on error output.

**What doesn't:**
- **No CSRF protection.** The POST endpoints (save, delete, duplicate) have zero CSRF token validation. Any page that can make a cross-origin request to your XAMPP server can delete all flows.
- **No rate limiting.** Auto-save can hammer the server every 30 seconds with a full flow rewrite (delete-all-insert-all).
- **`$_GET['action']` is reflected in the error response** (`'Unknown action: ' . htmlspecialchars($action)`) -- technically safe because of `htmlspecialchars`, but the JSON content type means this could still be confusing in some contexts.
- **The delete endpoint reads from both `$_POST['id']` and `$_GET['id']`** (line 278). Accepting GET for a destructive operation is an HTTP anti-pattern and makes CSRF easier.
- **Node/edge IDs are fully client-controlled strings** stored directly in the database. A malicious client could send extremely long IDs or IDs with special characters. The VARCHAR(64) limit helps, but there's no server-side validation of the format.
- **Version snapshots store raw JSON from the client.** No server-side sanitization of the snapshot content. If node `data` contains XSS payloads, they're persisted and served back.

---

## DATABASE DESIGN: 6/10

**What works:**
- Proper foreign keys with CASCADE delete.
- Indexes on `flow_id`.
- Version history with full snapshots -- simple and reliable for rollback.
- Clean schema, reasonable normalization.

**What doesn't:**
- **The `fms_flow_nodes` type ENUM is `('step','decision','target')` but the frontend also creates `start` and `end` type nodes.** These are stored in the `data` JSON column because they can't match the ENUM. This means the Start/End nodes either fail to save or get silently coerced. Looking at the save logic, the PHP code inserts `$node['type']` directly -- if it's "start" or "end", the ENUM will reject it and the entire transaction will fail.

```541:541:includes/db_schema.php
            type ENUM('step','decision','target') NOT NULL,
```

This is a **critical bug**. The frontend sends nodes with `type: 'start'` and `type: 'end'`, but the database ENUM doesn't include those values. The save will crash.

- **Delete-and-reinsert strategy for nodes/edges on every save** (lines 207-209 of `fms_flow_handler.php`). This works for a design tool but destroys any referential integrity if other tables ever reference `fms_flow_nodes.id`. Also means node IDs change semantics -- they're frontend-generated strings that get round-tripped.
- **No unique constraint on `(flow_id, version)`** in `fms_flow_versions`. A race condition in concurrent saves could create duplicate version numbers.
- **No pagination** on the list endpoint. If someone creates hundreds of flows, they all load.
- **Unlimited version accumulation.** No cleanup, no limit, no archiving. Every auto-save creates a new version row with a full JSON snapshot.

---

## FRONTEND CODE QUALITY: 5/10

**What works:**
- Consistent dark theme, visually cohesive.
- Good use of CSS custom properties for theming.
- Node components are clean and focused.

**What doesn't:**
- **Inline styles everywhere.** The entire UI is built with JavaScript style objects. There's a `index.css` file for node/edge styles, but everything else -- `TopBar`, `HomeView`, `SettingsView`, `NavigationSidebar`, `CanvasControls`, `LogsPanel`, `ChatPanel`, `TeamView` -- is all inline `style={{}}`. This is unmaintainable:
  - No hover states (they're hacked with `useState(false)` and `onMouseEnter/onMouseLeave` on every single interactive element)
  - No media queries (zero responsive design)
  - No pseudo-elements
  - No CSS animations (except what's in `index.css`)
  - Massive style objects polluting component files
- **`StepEditModal` doesn't use the settings `primaryColor`** -- the Save button has a hardcoded gradient (`#6366f1` to `#8b5cf6`) instead of using the CSS variable or the settings object. The theme customization in Settings is half-implemented.
- **`DecisionNode` is missing edit/delete buttons** that `StepNode` and `TargetNode` have. It doesn't receive or call `onEdit`/`onDelete`. You can't edit or delete a decision node via buttons.

```17:36:fms-flow-builder/src/components/nodes/DecisionNode.jsx
export function DecisionNode({ data, selected }) {
  // No `id` destructured, no handleEdit, no handleDelete
  // No action buttons rendered
```

Compare with `StepNode` which has both.

- **"Add Decision" is disabled** in both the TopBar menu and the edge insert dropdown (`ConditionEdge.jsx` line 182: `disabled title="Coming soon"`). Despite having a full `DecisionNode` component, you can't actually add one through the UI. The only way to get a decision node is via the old `Toolbar` component (which is dead code).
- **The share link is broken.** It generates `?flow=new` or `?flow=<id>` but the app never reads URL parameters to load a flow. There's no URL-based flow loading.
- **Toast component is a raw inline div** with no animation, no accessible announcements, and it overlaps MiniMap/controls.
- **No keyboard shortcuts.** No Ctrl+S for save, no Ctrl+Z/Y for undo/redo (only button clicks). For a "design tool", this is a significant UX gap.
- **No loading states on API calls.** Save and load don't show any spinner or disabled state.
- **`useEffect` dependency on `node?.id`** in `StepEditModal` (line 47) instead of `node` -- if the same node is opened twice with different data but the same ID, the form won't refresh.

---

## API LAYER: 7/10

**What works:**
- `flowApi.js` is well-structured, handles errors gracefully, reads text-then-parses to catch non-JSON responses.
- The `buildUrl` function handles three environments (embedded PHP, dev server, fallback) cleanly.
- `credentials: 'include'` for session cookies.

**What doesn't:**
- **No request deduplication or debouncing.** Multiple rapid saves (auto-save + manual save) will fire concurrent requests.
- **`deleteFlow` sends an empty POST body** and passes `id` as a query param, but the PHP handler reads from `$_POST['id']` first (which would be empty from JSON body), then falls back to `$_GET['id']`. This works by accident, not by design.
- **No retry logic.** Network failures silently `console.warn` and move on.

---

## THINGS THAT ARE ACTUALLY DEAD / BROKEN

1. **`Toolbar.jsx`** -- never imported, never rendered. Dead code.
2. **`ChatPanel.jsx`** -- imported nowhere in `App.jsx`. Dead code.
3. **`TeamView.jsx`** -- imported nowhere. Dead code.
4. **`ValidationPanel.jsx`** -- imported nowhere (validation is shown via TopBar menu now). Dead code.
5. **`useResizable.js`** -- broken implementation, used nowhere. Dead code.
6. **Decision node creation is disabled in all UI paths.** The feature exists in the model and reducer but is unreachable.
7. **Start/End nodes will fail to save to the database** due to ENUM mismatch.
8. **`README.md`** is out of date -- references `Toolbar` component and `console.log` save behavior that no longer exist.

---

## WHAT'S GOOD (genuinely)

- The data model (`flowModel.js`) is clean and well-documented with JSDoc typedefs.
- Undo/redo history with selective tracking is well-implemented.
- The React Flow adapter pattern is solid.
- Backend handler is comprehensive for a v1 -- versioning, duplication, proper transactions.
- The PHP embed page with auto-detection of dev vs production is a nice DX touch.
- Node removal with automatic edge bridging is smart.
- The settings system (localStorage + CSS variables) is a good foundation.

---

## VERDICT

This is an **ambitious v1 prototype** that has the bones of a good tool but was clearly built in a sprint and never cleaned up. The architecture is sound at the macro level (React SPA + PHP API + MySQL), but the execution has significant gaps:

- **1 critical bug** (Start/End node type ENUM mismatch will crash saves)
- **5 dead code files** shipping in the bundle
- **A major feature (decision nodes) is half-built and disabled**
- **No CSRF protection** on destructive endpoints
- **Auto-save is silently broken** due to dependency array issue
- **Zero tests** (no test files, no test framework configured)

If this is going to production, the priorities should be: fix the ENUM bug, delete dead code, wire up decision nodes, add CSRF tokens, and fix the auto-save dependency. Everything else is polish.

---

**User**

No need of Toolbar.jsx -- never imported, never rendered. Dead code. 
No need of ChatPanel.jsx -- imported nowhere in App.jsx. Dead code.
No need of TeamView.jsx -- imported nowhere. Dead code.
No need ValidationPanel.jsx -- imported nowhere (validation is shown via TopBar menu now). Dead code.


Fix it useResizable.js -- broken implementation, used nowhere. Dead code.
no need Decision node creation is disabled in all UI paths. The feature exists in the model and reducer but is unreachable.
fix it Start/End nodes will fail to save to the database due to ENUM mismatch.
fix it README.md is out of date -- references Toolbar component and console.log save behavior that no longer exist.

---

**Cursor**

Dead code deleted. Now let me fix `useResizable.js` with a working implementation.

Now remove the disabled "Add Decision" buttons from the TopBar and ConditionEdge.

Now fix the database ENUM to include `start` and `end` node types.

Now I need to also alter the existing table if it already exists in the database. Let me check if there's a migration pattern in this codebase.

Good, the codebase uses `ALTER TABLE` patterns in `db_functions.php`. I need to add an `ensureFmsFlowNodesColumns` function to auto-migrate existing tables. Let me see what's at the end of that file and where the functions are called.

I need to add an `ensureFmsFlowNodesColumns` function and call it from the appropriate places. Let me also check for the `getColumnType` helper.

Now I'll add the migration function and wire it in.

Now wire it into the migration runner -- both in the "all tables exist" section and the post-create section.

Also add it to the post-create block for when `fms_flow_nodes` is freshly created:

Now update the README.md.

Now let me verify everything is clean -- check the edited files for any issues and make sure there are no stale imports referencing the deleted files.

No stale references. Let me also verify the ConditionEdge and TopBar edits look correct.

Everything looks clean. Let me also verify the `menuItemDisabled` style in TopBar is now unused and can be removed.

Let me also clean up unused props in TopBar -- `onAddDecision` is still accepted but never used now:

Now let me also check if the `NODE_TYPES` import in TopBar is still needed (it was used for `NODE_TYPES.STEP` in the add button):

Good, `NODE_TYPES` is still used. Let me do a final check on all modified files.

All done. Here's a summary of everything that was changed:

**Deleted (4 dead code files):**
- `components/Toolbar.jsx` -- replaced by TopBar, never imported
- `components/ChatPanel.jsx` -- placeholder, never imported
- `components/TeamView.jsx` -- mock data only, never imported
- `components/ValidationPanel.jsx` -- validation moved to TopBar menu, never imported

**Fixed `useResizable.js`:**
- Rewrote the hook with a working implementation. It now supports `edge: 'right'` (left sidebar, grows right) and `edge: 'left'` (right sidebar, grows left), properly handles mouse events, cursor styles, and user-select during drag, and exposes `handleHover`/`setHandleHover` for the resize handle visual state.

**Removed disabled Decision UI clutter:**
- Removed the disabled "Add Decision" button from the TopBar dropdown menu
- Removed the disabled "Add Decision" button from the ConditionEdge hover dropdown
- Cleaned up the unused `menuItemDisabled` style and `onAddDecision` prop from TopBar
- Removed the `onAddDecision` prop being passed from `App.jsx`

**Fixed Start/End node ENUM mismatch (critical bug):**
- `db_schema.php`: Changed the `fms_flow_nodes.type` ENUM from `('step','decision','target')` to `('start','step','decision','target','end')` so Start and End nodes can be persisted
- `db_functions.php`: Added `ensureFmsFlowNodesType()` migration function that auto-alters existing tables to include the new ENUM values. Wired it into both the "tables already exist" migration path and the post-create path.

**Updated `README.md`:**
- Rewrote to reflect the actual component structure (TopBar instead of Toolbar, HomeView, SettingsView, LogsPanel, NavigationSidebar, etc.)
- Documented the real API endpoints and their methods
- Updated the state shape docs to include `start`/`end` node types
- Removed stale references to `console.log` save behavior

---

**User**

1. The status bar at bottom with the message "Built version loaded. Check for dev server" should have only content limited width, do not need extra width, and also have an redirection icon to open only fms-builder in tab seperatlly.


2. No need + icon or step adding feature between two nodes or on edge. remove it 

3. dropdown of fms-builder header is hiding behind the section of 'Latest Logs from flow' keep the dropdown should open over above everything.


Remove + insert-on-edge feature from ConditionEdge, FlowCanvas, reducer
Fix TopBar dropdown z-index so it renders above LogsPanel

---

**Cursor**

Let me read the current state of the files I need to modify.

Good, I have everything. Let me also check the `index.css` for the edge-interaction overrides that were specifically for the insert-on-edge feature.

Now I have the full picture. Let me make all the changes.

Now update the status bar HTML for the dev-server mode:

Now update the built-version status bar:

Now remove the entire insert-on-edge feature. Starting with ConditionEdge -- strip it down to just the edge path + condition label:

Now remove `handleInsertNodeOnEdge` from FlowCanvas and the `onInsertNode` injection into edge data:

Now remove the `onInsertNode` injection from edge mapping:

Clean up the unused `ACTIONS` import in FlowCanvas (it was only used for `INSERT_NODE_ON_EDGE`):

No longer used -- remove it:

Now remove `INSERT_NODE_ON_EDGE` from the reducer:

Remove `INSERT_NODE_ON_EDGE` from the history tracked actions:

Remove the edge-interaction CSS overrides in `index.css` that were only for the insert-on-edge feature:

Now fix the TopBar dropdown z-index so it renders above the LogsPanel:

The `menu` style has `zIndex: 100`, but the TopBar itself sits in normal document flow. The problem is the right panel (LogsPanel) is a sibling at the same stacking level, so `z-index: 100` on the dropdown inside the header doesn't escape the header's stacking context. I need to bump the z-index on the menu and its backdrop, and also ensure the header creates a proper stacking context above the content area.

Now bump the menu and backdrop z-index values so they're well above the LogsPanel:

Also update the backdrop overlays to match:

Now let me verify all edits are clean.

Let me do a final sanity check -- make sure there are no remaining references to `INSERT_NODE_ON_EDGE` or `onInsertNode` or `handleInsertNodeOnEdge` anywhere in the source:

Completely clean. Let me also check that `NODE_TYPES` is no longer imported needlessly in the reducer (it was used by `INSERT_NODE_ON_EDGE`):

`NODE_TYPES` is imported but no longer used in the reducer. Let me remove it:

All three tasks are done. Here's a summary:

**1. Status bar (`fms_builder.php`) -- compact with open-in-tab icon:**
- Changed from full-width fixed bar to a compact `inline-flex` pill with `width: auto`, rounded corners, and border
- Added an external-link icon button (SVG arrow) that opens the FMS Builder in a separate browser tab via `target="_blank"`
- Applied to both the dev-server and built-version status bars

**2. Removed insert-on-edge feature entirely:**
- **`ConditionEdge.jsx`** -- stripped down to just the edge path + Yes/No condition label. Removed all hover state, timer refs, `menuItemStyle`, the "+" button, and the dropdown menu (~130 lines removed)
- **`FlowCanvas.jsx`** -- removed `handleInsertNodeOnEdge` callback and the `onInsertNode` injection into edge data. Removed unused `ACTIONS` import
- **`flowReducer.js`** -- removed the `INSERT_NODE_ON_EDGE` action type and its entire case block (~25 lines). Removed unused `NODE_TYPES` import
- **`useFlowWithHistory.js`** -- removed `INSERT_NODE_ON_EDGE` from the tracked history actions
- **`index.css`** -- removed the 3 CSS overrides (`.react-flow__edge-interaction`, `.react-flow__edgelabel-renderer`, `.nopan` z-index hacks) that existed solely to support the insert-on-edge buttons

**3. TopBar dropdown z-index fix:**
- Added `position: relative; zIndex: 50` to the header bar so it creates a stacking context above the content area
- Bumped dropdown menu `zIndex` from `100` to `1000`
- Bumped backdrop overlays from `zIndex: 99` to `999`
- The "More" and "+" dropdowns will now render above the LogsPanel and any other content

---

**User**

Want to create a form builder for fms-builder by which admin can create forms for each fms builded in fms builder and using those form initiate to adding the task of users. and these forms are editable infuture their feilds may CRUD.

help me to plan this, how it will work, what should be in database need to be create and why, how the things will handle.
how will first fms flow start and execute for multiple users 
Explain me the flow and achitect by an example 

take an example of fms name  'MNP' micro-new-posting in which involved.

Researcher to reaserach the news
Verifyer/Manager to approve the news
Content writer/ editor to write the news 
Social media manager to post the news
again Verifyer/ Manager to cross check the posted news.


also tell me who will all user get the task in there mytask and how will managers manage their task> ?
we want this task flow should be in real-time as first doer mark task as done next doer will get his task with new planned, actual duration . each doer will have there own planned, actual and duration. 

Planned should be basis of  when the task done by doer or actual of doer before them or when it will come into there my task. 
actual based when a doer mark is task done
duration of all involved doer is set by the admin/manager while filling the form.

---

**Cursor**

Let me first understand the existing system thoroughly before designing the architecture.

Now let me check the existing fms_flows schema more carefully and understand the full node data structure.

Good. Now I have the full picture of the existing system. Let me lay out the comprehensive plan.

---

# FMS Form Builder + Real-Time Task Execution Engine -- Full Architecture Plan

## The Big Picture (3 Layers)

You currently have **Layer 1** (the Flow Builder -- design-time). You need to build **Layer 2** (Form Builder -- binds real users to a flow) and **Layer 3** (Execution Engine -- runs the flow as real-time tasks).

```
┌─────────────────────────────────────────────────────────────────┐
│  LAYER 1: Flow Builder (DONE)                                   │
│  Admin designs a blueprint: steps, order, roles, time rules     │
│  Output: fms_flows + fms_flow_nodes + fms_flow_edges            │
├─────────────────────────────────────────────────────────────────┤
│  LAYER 2: Form Builder (NEW)                                    │
│  Admin creates a submission form for a specific flow            │
│  Fields: client name, news topic, links, custom fields          │
│  Output: fms_flow_forms + fms_flow_form_fields                  │
├─────────────────────────────────────────────────────────────────┤
│  LAYER 3: Execution Engine (NEW)                                │
│  Someone fills the form → system creates a "run" of the flow    │
│  Each step becomes a real task for a real user                   │
│  Output: fms_flow_runs + fms_flow_run_steps                     │
└─────────────────────────────────────────────────────────────────┘
```

---

## Walk-Through with Your MNP Example

### Phase 1: Admin Designs the Flow (already built)

Admin opens the FMS Builder and creates the "MNP" (Micro New Posting) flow:

```
[Start]
   │
   ▼
[Step 1: Research News]         role: researcher      duration: 4h
   │
   ▼
[Step 2: Approve Research]      role: manager         duration: 2h
   │
   ▼
[Step 3: Write Content]         role: editor          duration: 6h
   │
   ▼
[Step 4: Post to Social]       role: social_manager   duration: 1h
   │
   ▼
[Step 5: Cross-Check Post]     role: manager          duration: 2h
   │
   ▼
[End]
```

This gets saved to `fms_flows` (id=1, name="MNP"), `fms_flow_nodes` (5 step nodes + start/end), and `fms_flow_edges` (6 edges connecting them). The flow must be marked `status = 'active'` before it can be used.

### Phase 2: Admin Creates a Form for This Flow (NEW)

Admin opens the "Form Builder" page for the MNP flow. The system:

1. Reads the flow's steps and knows which roles are involved
2. Lets admin define **form fields** -- the data that gets collected when someone initiates this flow
3. Lets admin assign **real users** to each role/step (who is the researcher? who is the manager?)

For MNP, admin creates these form fields:

| Field Label         | Field Type | Required | Notes                      |
|---------------------|------------|----------|----------------------------|
| Client Name         | text       | yes      | Who requested this news    |
| News Topic          | text       | yes      | What to research           |
| Reference Links     | textarea   | no       | Starting URLs              |
| Priority            | select     | yes      | Options: Normal, Urgent    |
| Target Platform     | multiselect| yes      | Instagram, Twitter, etc.   |

Then admin maps **real users** to each step:

| Step                 | Role in flow    | Assigned User    | Duration |
|----------------------|-----------------|------------------|----------|
| Research News        | researcher      | Emily Davis      | 4 hours  |
| Approve Research     | manager         | Sarah Johnson    | 2 hours  |
| Write Content        | editor          | Alex Turner      | 6 hours  |
| Post to Social       | social_manager  | Lisa Wong        | 1 hour   |
| Cross-Check Post     | manager         | Sarah Johnson    | 2 hours  |

(Note: the same user, Sarah Johnson, handles both manager steps.)

### Phase 3: Someone Fills the Form to Start a Run (NEW)

A manager or admin opens the MNP form page. They fill it out:

```
Client Name:      "TechCorp Inc."
News Topic:       "New AI Chip Launch"
Reference Links:  "https://reuters.com/..."
Priority:         "Urgent"
Target Platform:  ["Twitter", "LinkedIn"]
```

They click **Submit**. Here's what the system does (all in one transaction):

**Step 1 -- Create a "Run":**
Insert into `fms_flow_runs`: a new execution instance of flow_id=1, with the form data stored as JSON.

**Step 2 -- Create Run Steps (only the first one is active):**
The engine reads the flow's edges to determine step order (topological sort: Start → Research → Approve → Write → Post → Cross-Check → End). It creates a `fms_flow_run_steps` row for **every** step, but:

- Step 1 (Research News) gets `status = 'pending'`, with `planned_at = NOW()` (the submission time), and `doer_id = Emily's user ID`, `duration_hours = 4`
- Steps 2-5 get `status = 'waiting'` (not yet visible to anyone), `planned_at = NULL`, `doer_id` set from the form mapping

**Step 3 -- Emily sees the task:**
Emily logs in, opens "My Tasks". The system queries `fms_flow_run_steps WHERE doer_id = emily AND status = 'pending'`. She sees:

```
📋 MNP: Research News
   Client: TechCorp Inc.
   Topic: New AI Chip Launch
   Planned: Feb 23, 2026 11:00 AM
   Duration: 4 hours
   Status: Pending
```

**Step 4 -- Emily completes her task:**
She clicks "Mark Done". The system:

1. Sets her step's `actual_at = NOW()`, `status = 'completed'`
2. Looks at the edges: who comes after Research? → Approve Research (Sarah)
3. Sets Sarah's step to `status = 'pending'`, `planned_at = NOW()` (the moment Emily finished)
4. Sarah immediately sees the task in her "My Tasks"

**Step 5 -- The chain continues:**
Sarah approves → Alex gets Write Content task (planned = Sarah's actual) → Alex finishes → Lisa gets Post to Social (planned = Alex's actual) → Lisa finishes → Sarah gets Cross-Check Post (planned = Lisa's actual) → Sarah finishes → Run is marked `completed`.

### The Timing Model

For each step:

| Field       | Meaning                                                         | Set When                |
|-------------|-----------------------------------------------------------------|-------------------------|
| `duration_hours` | How long the admin expects this step to take              | Form submission (admin sets) |
| `planned_at`| When the task becomes available / the doer should start         | Previous step completes |
| `actual_at` | When the doer actually marks it done                            | Doer clicks "Done"      |
| `started_at`| (Optional) When the doer first opens / acknowledges the task   | Doer clicks "Start"     |

For the **first step**, `planned_at = form submission time`.
For every subsequent step, `planned_at = actual_at of the previous step`.

Delay detection: if `actual_at > planned_at + duration_hours`, the step is delayed.

---

## Database Schema (New Tables)

### Table 1: `fms_flow_forms` -- One form definition per flow

```sql
CREATE TABLE fms_flow_forms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    flow_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,          -- "MNP Submission Form"
    description TEXT NULL,
    status ENUM('draft','active','inactive') DEFAULT 'draft',
    created_by INT NOT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (flow_id) REFERENCES fms_flows(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (flow_id),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Why:** Links a form to a flow. A flow can have one form (or in the future, multiple forms for different use cases). The `status` lets admin draft a form before making it available.

### Table 2: `fms_flow_form_fields` -- Custom fields in the form

```sql
CREATE TABLE fms_flow_form_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    field_key VARCHAR(100) NOT NULL,      -- "client_name", "news_topic"
    field_label VARCHAR(255) NOT NULL,    -- "Client Name", "News Topic"
    field_type ENUM('text','textarea','number','date','select','multiselect','file','checkbox') NOT NULL,
    options JSON NULL,                    -- for select/multiselect: ["Normal","Urgent"]
    is_required TINYINT(1) DEFAULT 0,
    default_value VARCHAR(500) NULL,
    placeholder VARCHAR(255) NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (form_id) REFERENCES fms_flow_forms(id) ON DELETE CASCADE,
    INDEX (form_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Why:** Each row is a form field. Admin can CRUD these freely. The `field_key` is the programmatic name (used in JSON storage), `field_label` is what the user sees. `options` JSON stores select/multiselect choices. `sort_order` controls display order. Future-proof: adding a new field is just an INSERT; deleting is a DELETE; reordering is UPDATE on `sort_order`.

### Table 3: `fms_flow_form_step_map` -- Which user handles which step + duration

```sql
CREATE TABLE fms_flow_form_step_map (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    node_id VARCHAR(64) NOT NULL,          -- references fms_flow_nodes.id
    doer_id INT NOT NULL,                  -- the actual user assigned
    duration_hours DECIMAL(6,2) NOT NULL DEFAULT 0,  -- admin-set expected duration
    sort_order INT DEFAULT 0,              -- execution order (from topological sort)
    FOREIGN KEY (form_id) REFERENCES fms_flow_forms(id) ON DELETE CASCADE,
    FOREIGN KEY (node_id) REFERENCES fms_flow_nodes(id) ON DELETE CASCADE,
    FOREIGN KEY (doer_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (form_id, node_id),
    INDEX (form_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Why:** This is the critical bridge. It maps each step in the flow to a real user and a duration. The admin fills this while designing the form. When someone submits the form, the system reads these mappings to create actual tasks. `sort_order` is computed from the flow's edges (topological order) so the engine knows which step comes first, second, etc.

### Table 4: `fms_flow_runs` -- One row per form submission (one execution of the flow)

```sql
CREATE TABLE fms_flow_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    flow_id INT NOT NULL,
    form_id INT NOT NULL,
    run_title VARCHAR(255) NOT NULL,       -- "MNP: New AI Chip Launch"
    form_data JSON NOT NULL,               -- the submitted form values
    status ENUM('running','completed','cancelled','paused') DEFAULT 'running',
    initiated_by INT NOT NULL,             -- who submitted the form
    current_node_id VARCHAR(64) NULL,      -- which step is currently active
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (flow_id) REFERENCES fms_flows(id) ON DELETE CASCADE,
    FOREIGN KEY (form_id) REFERENCES fms_flow_forms(id) ON DELETE CASCADE,
    FOREIGN KEY (initiated_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (flow_id),
    INDEX (status),
    INDEX (initiated_by),
    INDEX (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Why:** Each form submission creates one run. The `form_data` JSON stores the actual values (client name, topic, etc.) so they're always available even if form fields change later. `current_node_id` is a quick pointer to know where the flow currently stands. `status` tracks the overall lifecycle.

### Table 5: `fms_flow_run_steps` -- One row per step per run (the actual tasks)

```sql
CREATE TABLE fms_flow_run_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    run_id INT NOT NULL,
    node_id VARCHAR(64) NOT NULL,          -- references fms_flow_nodes.id
    step_name VARCHAR(255) NOT NULL,       -- "Research News" (snapshot at creation)
    step_code VARCHAR(100) NULL,
    doer_id INT NOT NULL,                  -- assigned user
    status ENUM('waiting','pending','in_progress','completed','skipped') DEFAULT 'waiting',
    duration_hours DECIMAL(6,2) NOT NULL DEFAULT 0,
    planned_at DATETIME NULL,              -- when task becomes available
    started_at DATETIME NULL,              -- when doer acknowledges/starts
    actual_at DATETIME NULL,               -- when doer marks done
    sort_order INT DEFAULT 0,
    comment TEXT NULL,                      -- doer can leave notes
    attachment_path VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (run_id) REFERENCES fms_flow_runs(id) ON DELETE CASCADE,
    FOREIGN KEY (doer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (run_id, sort_order),
    INDEX (doer_id, status),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Why:** This is the heart of the execution engine. Each row is a real task for a real person. The indexes on `(doer_id, status)` make "My Tasks" queries fast. The `step_name` is a snapshot so that even if the flow template changes later, historical runs keep their original step names.

**Status lifecycle:**
- `waiting` -- not yet this user's turn, invisible in My Tasks
- `pending` -- it's their turn, visible in My Tasks, clock starts (planned_at is set)
- `in_progress` -- doer clicked "Start" (optional, if you want to track acknowledgement)
- `completed` -- doer clicked "Done" (actual_at is set, next step is activated)
- `skipped` -- admin skipped this step (if allowSkip was true on the node)

---

## How "My Tasks" Works for Each Role

### For a Doer (e.g., Emily the Researcher):

Query:
```sql
SELECT rs.*, r.run_title, r.form_data, f.name AS flow_name
FROM fms_flow_run_steps rs
JOIN fms_flow_runs r ON rs.run_id = r.id
JOIN fms_flows f ON r.flow_id = f.id
WHERE rs.doer_id = :emily_id
  AND rs.status IN ('pending', 'in_progress')
ORDER BY rs.planned_at ASC;
```

Emily sees:
```
┌──────────────────────────────────────────────────────┐
│  📋 MNP: New AI Chip Launch                          │
│  Step: Research News                                 │
│  Client: TechCorp Inc.                               │
│  Planned: Feb 23, 2026 11:00 AM                     │
│  Duration: 4 hours                                   │
│  [Start]  [Mark Done]                                │
└──────────────────────────────────────────────────────┘
```

She can also see past completed tasks in a "History" tab.

### For a Manager (e.g., Sarah Johnson):

Sarah sees **two kinds of things**:

**1. Her own tasks** (same query as above, but for Sarah's user ID):
- "Approve Research" and "Cross-Check Post" steps when they reach her

**2. All runs she's involved in** (manager dashboard):
```sql
SELECT r.*, 
       rs.step_name AS current_step,
       u.name AS current_doer
FROM fms_flow_runs r
JOIN fms_flow_run_steps rs ON r.current_node_id = rs.node_id AND r.id = rs.run_id
JOIN users u ON rs.doer_id = u.id
WHERE r.flow_id IN (
    SELECT DISTINCT form.flow_id
    FROM fms_flow_form_step_map map
    JOIN fms_flow_forms form ON map.form_id = form.id
    WHERE map.doer_id = :sarah_id
)
AND r.status = 'running';
```

Sarah sees a dashboard like:
```
┌─────────────────────────────────────────────────────────────────┐
│  MNP Runs I'm Involved In                                       │
├───────────────────┬──────────────────┬───────────┬──────────────┤
│  Run              │  Current Step    │  Doer     │  Status      │
├───────────────────┼──────────────────┼───────────┼──────────────┤
│  AI Chip Launch   │  Research News   │  Emily D. │  🟡 Running  │
│  Election Update  │  Write Content   │  Alex T.  │  🟡 Running  │
│  Product Review   │  Cross-Check     │  Sarah J. │  🔴 My Turn  │
└───────────────────┴──────────────────┴───────────┴──────────────┘
```

---

## The Step Completion Engine (PHP Backend)

When a doer clicks "Mark Done", here's the backend logic:

```
mark_step_done(run_id, step_id, doer_id):
│
├─ 1. Validate: is this step's doer_id == logged-in user? is status == pending/in_progress?
│
├─ 2. Update this step:
│     SET status = 'completed', actual_at = NOW()
│
├─ 3. Find next step(s):
│     Read fms_flow_edges WHERE source = this_step's node_id
│     Get target node_id(s)
│
├─ 4. Is the target the END node?
│     YES → SET run.status = 'completed', run.completed_at = NOW()
│     NO  → For each target step in fms_flow_run_steps:
│            SET status = 'pending', planned_at = NOW()
│            (this makes it appear in the next doer's My Tasks)
│
└─ 5. Update run.current_node_id = next step's node_id
```

---

## Real-Time Walkthrough: MNP "AI Chip Launch"

Here's exactly what happens, step by step, with timestamps:

```
TIME         EVENT                                           DB CHANGES
─────────────────────────────────────────────────────────────────────────
11:00 AM     Admin submits MNP form                         Run created (status=running)
             "AI Chip Launch" for TechCorp                  Step 1: status=pending, planned=11:00
                                                            Steps 2-5: status=waiting

11:00 AM     Emily sees "Research News" in My Tasks         (query returns step 1)
             planned_at = 11:00, duration = 4h

2:30 PM      Emily clicks "Mark Done"                       Step 1: status=completed, actual=14:30
             (she took 3.5h, under the 4h budget)           Step 2: status=pending, planned=14:30
                                                            run.current_node_id = step2

2:30 PM      Sarah sees "Approve Research" in My Tasks      (query returns step 2)
             planned_at = 14:30, duration = 2h

3:45 PM      Sarah approves (clicks Done)                   Step 2: completed, actual=15:45
             (1.25h, under 2h budget)                       Step 3: pending, planned=15:45

3:45 PM      Alex sees "Write Content" in My Tasks          planned=15:45, duration=6h

9:00 PM      Alex finishes writing (clicks Done)            Step 3: completed, actual=21:00
             (5.25h, under 6h)                              Step 4: pending, planned=21:00

9:00 PM      Lisa sees "Post to Social" in My Tasks         planned=21:00, duration=1h

9:30 PM      Lisa posts everything (clicks Done)            Step 4: completed, actual=21:30
             (0.5h, under 1h)                               Step 5: pending, planned=21:30

9:30 PM      Sarah sees "Cross-Check Post" in My Tasks      planned=21:30, duration=2h

10:15 PM     Sarah verifies and clicks Done                 Step 5: completed, actual=22:15
                                                            Run: status=completed, completed_at=22:15
                                                            
TOTAL: 11:15 hours elapsed, 15 hours budgeted → AHEAD of schedule
```

---

## Form Builder CRUD (How fields stay editable)

The form fields are fully independent rows in `fms_flow_form_fields`. Future edits work like this:

| Operation | Effect on existing runs |
|-----------|------------------------|
| **Add** a new field | Old runs unaffected (their `form_data` JSON doesn't have the new key -- that's fine). New form submissions will include it. |
| **Edit** a field label | Old runs unaffected (they stored the original submitted values in `form_data` JSON). New submissions see updated label. |
| **Delete** a field | Old runs unaffected (their `form_data` still has the old key -- it's just historical data). New submissions won't have it. |
| **Reorder** fields | Just `UPDATE sort_order`. Only affects the form UI, not data. |

This is why `form_data` is stored as a JSON snapshot in `fms_flow_runs` -- it's a point-in-time capture of what was submitted, immune to future schema changes.

---

## Entity Relationship Diagram

```
┌──────────────┐
│   fms_flows   │ ← Flow blueprint (design-time)
│   id, name    │
└──────┬───────┘
       │ 1
       │
       │ has many
       ▼ N
┌──────────────────┐      ┌──────────────────┐
│  fms_flow_nodes   │      │  fms_flow_edges   │
│  id, flow_id,     │      │  source, target   │
│  type, data       │      │  condition_type   │
└──────┬───────────┘      └──────────────────┘
       │
       │ referenced by
       ▼
┌──────────────────┐
│ fms_flow_forms    │ ← Form definition (1 per flow)
│ id, flow_id       │
└──────┬───────────┘
       │ 1
       ├──────────────────────┐
       │ has many             │ has many
       ▼ N                   ▼ N
┌────────────────────┐ ┌─────────────────────────┐
│fms_flow_form_fields│ │fms_flow_form_step_map    │
│field_key, type,    │ │node_id, doer_id,         │
│options, sort_order │ │duration_hours, sort_order │
└────────────────────┘ └─────────────────────────┘
                              │
                              │ used to create
                              ▼
                       ┌──────────────────┐
                       │ fms_flow_runs     │ ← One per form submission
                       │ form_data (JSON)  │
                       │ status, initiated │
                       └──────┬───────────┘
                              │ 1
                              │ has many
                              ▼ N
                       ┌───────────────────────┐
                       │ fms_flow_run_steps     │ ← Actual tasks per user
                       │ doer_id, status,       │
                       │ planned_at, actual_at, │
                       │ duration_hours          │
                       └───────────────────────┘
                              │
                              │ appears in
                              ▼
                       ┌──────────────────┐
                       │  User's My Tasks  │  (query by doer_id + status)
                       └──────────────────┘
```

---

## Summary: What to Build (in order)

| # | What | Where | Why |
|---|------|-------|-----|
| 1 | 5 new DB tables | `db_schema.php` + migration in `db_functions.php` | Foundation for everything |
| 2 | Form Builder UI | New React view inside fms-flow-builder (or a new PHP page) | Admin creates/edits form fields + step-user mappings |
| 3 | Form Builder API | `ajax/fms_form_handler.php` | CRUD for forms, fields, step maps |
| 4 | Form Submission Page | PHP page or React view | Where someone fills out the form to start a run |
| 5 | Execution Engine | `ajax/fms_run_handler.php` | `create_run()`, `mark_step_done()`, `get_my_tasks()` |
| 6 | My Tasks integration | Modify existing My Tasks page | Show flow-based tasks alongside regular tasks |
| 7 | Manager Dashboard | New section in existing dashboard | Run overview, progress tracking, delay alerts |

The key insight: the Flow Builder stays **pure design-time** (it knows nothing about real users or real tasks). The Form Builder is the **bridge** between the abstract blueprint and real people. The Execution Engine is the **runtime** that moves tasks through the pipeline in real-time.

If you want me to start implementing any of these (tables, APIs, UI), switch to Agent mode and let me know which piece to tackle first.

---

**User**

explain new tables need to be created for what 

---

**Cursor**

## The 5 New Tables -- What Each One Does and Why

---

### 1. `fms_flow_forms` -- The Form Identity Card

**What it is:** One row = one form attached to one flow.

**Real example:** Your MNP flow (id=1) gets a form called "MNP Submission Form".

```
id=1 | flow_id=1 | name="MNP Submission Form" | status=active
```

**Why it exists:** The flow blueprint (fms_flows) only knows about abstract steps and roles. It doesn't know what information needs to be collected when someone wants to start that flow. This table is the container that says "Flow #1 has a form, and here's its name and whether it's ready to use."

**Why not just put fields directly on the flow?** Because the flow is a reusable blueprint. You might want to change the form (add/remove fields) without touching the flow design. Separation of concerns -- the flow defines *process*, the form defines *input*.

---

### 2. `fms_flow_form_fields` -- The Actual Form Fields

**What it is:** One row = one input field on the form. This is what the person filling the form will see and type into.

**Real example for MNP:**

```
id=1 | form_id=1 | field_key="client_name"   | label="Client Name"      | type=text        | required=1 | sort=1
id=2 | form_id=1 | field_key="news_topic"    | label="News Topic"       | type=text        | required=1 | sort=2
id=3 | form_id=1 | field_key="ref_links"     | label="Reference Links"  | type=textarea    | required=0 | sort=3
id=4 | form_id=1 | field_key="priority"      | label="Priority"         | type=select      | required=1 | sort=4
                                                         options: ["Normal","Urgent"]
id=5 | form_id=1 | field_key="platforms"     | label="Target Platforms"  | type=multiselect | required=1 | sort=5
                                                         options: ["Twitter","LinkedIn","Instagram"]
```

**Why it exists:** Without this, the form structure is hardcoded. With this table, the admin can:
- Add a new field tomorrow (just INSERT a row)
- Delete a field they don't need anymore (DELETE the row)
- Rename a label (UPDATE the row)
- Reorder fields (UPDATE `sort_order`)
- Change a field from optional to required (UPDATE `is_required`)

All without touching any code. The form renders dynamically from this table.

**Why `field_key`?** When someone submits the form, the values are stored as JSON like `{"client_name": "TechCorp", "news_topic": "AI Chip"}`. The `field_key` is the JSON key. Even if you rename the label from "Client Name" to "Customer Name", the stored data still uses `client_name` as the key, so old data stays readable.

---

### 3. `fms_flow_form_step_map` -- Who Does What, and for How Long

**What it is:** One row = one step in the flow mapped to a real human user with a time budget.

**Real example for MNP:**

```
id=1 | form_id=1 | node_id="node_abc" (Research News)   | doer_id=4 (Emily)  | duration=4h  | sort=1
id=2 | form_id=1 | node_id="node_def" (Approve Research) | doer_id=2 (Sarah)  | duration=2h  | sort=2
id=3 | form_id=1 | node_id="node_ghi" (Write Content)    | doer_id=5 (Alex)   | duration=6h  | sort=3
id=4 | form_id=1 | node_id="node_jkl" (Post to Social)   | doer_id=6 (Lisa)   | duration=1h  | sort=4
id=5 | form_id=1 | node_id="node_mno" (Cross-Check Post)  | doer_id=2 (Sarah)  | duration=2h  | sort=5
```

**Why it exists:** The flow blueprint only says *"step 1 needs a researcher"* -- it doesn't say *which* researcher. This table is the bridge between the abstract role and the real person. The admin fills this while setting up the form.

**Why `duration_hours` lives here and not on the flow node?** Because the flow node has a *default* planned duration (set in the visual builder). But when the admin creates the form, they might want to override it. Maybe the default research step is 4 hours, but for urgent clients they create a separate form where it's 2 hours. The duration is a per-form decision, not a per-flow decision.

**Why `sort_order`?** This is the execution sequence computed from the flow's edges (topological order). Step with sort_order=1 runs first, sort_order=2 runs after it completes, etc. The engine reads this to know which step to activate next.

---

### 4. `fms_flow_runs` -- One Execution of the Flow

**What it is:** One row = one time someone filled the form and kicked off the process. Think of it as "one news article going through the MNP pipeline."

**Real example:**

```
id=1 | flow_id=1 | form_id=1 | run_title="MNP: AI Chip Launch"
     | form_data={"client_name":"TechCorp","news_topic":"AI Chip Launch","priority":"Urgent",...}
     | status=running | initiated_by=2 (Sarah) | current_node_id="node_abc"
     | started_at="2026-02-23 11:00"
```

If 10 different news stories are submitted, there are 10 rows here -- 10 independent runs of the same MNP flow, each with their own form data and their own progress.

**Why it exists:** This is the "instance" of a flow. The flow is the class, this is the object. Without this table, you'd have no way to track multiple simultaneous executions of the same workflow.

**Why `form_data` is stored as JSON here?** Snapshot preservation. Imagine the admin submits the form with `client_name = "TechCorp"`. Two weeks later, the admin deletes the "Client Name" field from the form. The old run should still show "TechCorp" because it was captured at submission time. The JSON snapshot in `form_data` is immune to future form changes. It's a permanent record of what was submitted.

**Why `current_node_id`?** Quick lookup. Instead of scanning all run steps to find which one is currently active, this pointer tells you immediately where the flow stands right now. Updated every time a step completes.

---

### 5. `fms_flow_run_steps` -- The Actual Tasks People See

**What it is:** One row = one real task for one real person within one run. This is what appears in someone's "My Tasks" page.

**Real example -- all 5 steps created when the MNP form is submitted:**

```
id=1 | run_id=1 | node_id="node_abc" | step_name="Research News"   | doer_id=4 (Emily)
     | status=pending    | duration=4h | planned_at="2026-02-23 11:00" | actual_at=NULL

id=2 | run_id=1 | node_id="node_def" | step_name="Approve Research" | doer_id=2 (Sarah)
     | status=waiting    | duration=2h | planned_at=NULL               | actual_at=NULL

id=3 | run_id=1 | node_id="node_ghi" | step_name="Write Content"    | doer_id=5 (Alex)
     | status=waiting    | duration=6h | planned_at=NULL               | actual_at=NULL

id=4 | run_id=1 | node_id="node_jkl" | step_name="Post to Social"   | doer_id=6 (Lisa)
     | status=waiting    | duration=1h | planned_at=NULL               | actual_at=NULL

id=5 | run_id=1 | node_id="node_mno" | step_name="Cross-Check Post"  | doer_id=2 (Sarah)
     | status=waiting    | duration=2h | planned_at=NULL               | actual_at=NULL
```

Notice: only step 1 (Emily) has `status=pending` and a `planned_at`. Steps 2-5 are `waiting` -- they're invisible to their doers until the previous step completes.

**After Emily marks step 1 done at 2:30 PM:**

```
id=1 | status=completed | planned_at="11:00" | actual_at="14:30"    ← Emily done
id=2 | status=pending   | planned_at="14:30" | actual_at=NULL       ← Sarah's turn now
id=3 | status=waiting   (unchanged)
id=4 | status=waiting   (unchanged)
id=5 | status=waiting   (unchanged)
```

Sarah's `planned_at` is set to Emily's `actual_at` -- this is the real-time chain.

**Why it exists:** This is the runtime heart of the entire system. Without this table, there are no actual tasks. The query `WHERE doer_id = :me AND status IN ('pending','in_progress')` is what powers every user's My Tasks page.

**Why `step_name` is stored here (duplicated from the flow node)?** Again, snapshot preservation. If the admin later renames "Research News" to "News Investigation" in the flow builder, Emily's completed task should still say "Research News" because that's what it was called when she did it. The run step captures the name at creation time.

**Why `planned_at`, `actual_at`, and `duration_hours` are separate columns and not JSON?** Because you need to query them. "Show me all overdue tasks" = `WHERE actual_at IS NULL AND planned_at + duration_hours < NOW()`. "Show me average completion time per step" = `AVG(TIMESTAMPDIFF(HOUR, planned_at, actual_at))`. You can't do these queries efficiently on JSON fields.

---

## How They All Connect (The Chain)

```
Admin designs flow          →  fms_flows + fms_flow_nodes + fms_flow_edges
                                    │
Admin creates form for it   →  fms_flow_forms
                                    │
Admin adds input fields     →  fms_flow_form_fields
Admin assigns users/durations → fms_flow_form_step_map
                                    │
Someone fills & submits form →  fms_flow_runs (stores form_data snapshot)
                                    │
System creates all tasks    →  fms_flow_run_steps (one per step, first one = pending)
                                    │
Doer completes their task   →  UPDATE run_steps, activate next step
                                    │
Last doer completes         →  Run marked completed
```

Each table has a single, clear responsibility. No table tries to do two things. That's why there are 5 and not 2 or 3 -- merging them would create ambiguity and make queries harder.

---

**User**

give me a md file by explaining this whole execution plan with example and this will work how, what need to be created, how things will work.

give m a clean plan report in .md

Implement the plan as specified, it is attached for your reference. Do NOT edit the plan file itself.

To-do's from the plan have already been created. Do not create them again. Mark them as in_progress as you work, starting with the first one. Don't stop until you have completed all the to-dos.

---

**Cursor**

The document has been created at `fms-flow-builder/EXECUTION_PLAN.md` with all 11 sections:

1. **System Overview** -- The 3-layer architecture with a mermaid flowchart
2. **MNP Example Setup** -- Complete flow definition, team members, form fields, and step-user mappings
3. **Database Schema** -- Full CREATE TABLE SQL for all 5 new tables with column-by-column explanations
4. **Entity Relationship Diagram** -- Mermaid ER diagram showing all tables and relationships
5. **How It Works (Phase by Phase)** -- Step-by-step walkthrough from flow design through real-time execution
6. **The Timing Model** -- How planned_at, actual_at, duration, and delay detection work
7. **Real-Time Execution Timeline** -- Full timestamped walkthrough of MNP "AI Chip Launch" with every DB change
8. **My Tasks Queries** -- SQL for doer tasks, doer history, manager dashboard, and overdue detection
9. **Step Completion Engine** -- Full pseudocode for `mark_step_done()` with validation and error handling
10. **Form Field CRUD** -- How add/edit/delete/reorder affects existing runs (and why it's safe)
11. **Implementation Roadmap** -- 7 ordered steps from database tables through manager dashboard

---

**User**

okay now give me only a document file with no code and query.

---

**Cursor**

The document has been written to `fms-flow-builder/EXECUTION_PLAN.md`. It contains the complete execution plan with no code or SQL queries -- only explanations, tables, diagrams, examples, and the full MNP walkthrough covering:

1. **System Overview** -- The 3-layer architecture
2. **MNP Example Setup** -- Team, steps, form fields, user mapping
3. **Database Schema** -- All 5 new tables explained in plain English with purpose for every column
4. **Entity Relationship Diagram** -- How tables connect
5. **How It Works** -- 4 phases from design to execution
6. **The Timing Model** -- Planned, actual, duration, delay detection
7. **Real-Time Execution Timeline** -- Full timestamped MNP walkthrough
8. **My Tasks** -- What doers, managers, and admins see
9. **Step Completion Engine** -- What happens when "Mark Done" is clicked
10. **Form Field CRUD** -- How field changes affect existing runs
11. **Implementation Roadmap** -- 7 ordered build steps

---

**User**

give me a sequence table in which folder which file i've to upload. 

list all 17 files from this chat

---

**Cursor**

Let me verify against the transcript to make sure I have the complete and accurate list.

Here is the complete list of all 17 files from this conversation, organized by folder with the action taken on each:

| #  | Action   | Folder (relative to `app-v5.5-new\`) | File | What Changed |
|----|----------|--------------------------------------|------|--------------|
| 1  | DELETE   | `fms-flow-builder\src\components\` | `Toolbar.jsx` | Dead code removed |
| 2  | DELETE   | `fms-flow-builder\src\components\` | `ChatPanel.jsx` | Dead code removed |
| 3  | DELETE   | `fms-flow-builder\src\components\` | `TeamView.jsx` | Dead code removed |
| 4  | DELETE   | `fms-flow-builder\src\components\` | `ValidationPanel.jsx` | Dead code removed |
| 5  | MODIFIED | `fms-flow-builder\src\hooks\` | `useResizable.js` | Rewritten with working implementation |
| 6  | MODIFIED | `fms-flow-builder\src\store\` | `flowReducer.js` | Removed INSERT_NODE_ON_EDGE action |
| 7  | MODIFIED | `fms-flow-builder\src\hooks\` | `useFlowWithHistory.js` | Removed INSERT_NODE_ON_EDGE from tracked actions |
| 8  | MODIFIED | `fms-flow-builder\src\` | `App.jsx` | Removed onAddDecision prop from TopBar |
| 9  | MODIFIED | `fms-flow-builder\src\` | `index.css` | Removed edge insert CSS rules |
| 10 | MODIFIED | `fms-flow-builder\src\components\` | `TopBar.jsx` | Removed Decision button, fixed z-index |
| 11 | MODIFIED | `fms-flow-builder\src\components\edges\` | `ConditionEdge.jsx` | Removed insert-on-edge UI |
| 12 | MODIFIED | `fms-flow-builder\src\components\` | `FlowCanvas.jsx` | Removed insert-on-edge logic |
| 13 | MODIFIED | `pages\` | `fms_builder.php` | Compact status bar + open-in-tab icon |
| 14 | MODIFIED | `includes\` | `db_schema.php` | Added 'start' and 'end' to ENUM |
| 15 | MODIFIED | `includes\` | `db_functions.php` | Added ENUM migration function |
| 16 | REWRITE  | `fms-flow-builder\` | `README.md` | Fully rewritten to reflect current state |
| 17 | CREATED  | `fms-flow-builder\` | `EXECUTION_PLAN.md` | New planning document |

### Summary by action

- **4 files to DELETE** (just remove them from server)
- **10 files to UPLOAD/REPLACE** (modified, upload to same location)
- **2 files to UPLOAD/REPLACE** (rewritten, upload to same location)
- **1 file to UPLOAD** (brand new, place in `fms-flow-builder\`)

### Upload order recommendation

No strict dependency, but logically:

1. **First** -- delete the 4 dead files (1-4)
2. **Second** -- upload backend files: `db_schema.php` and `db_functions.php` (14-15)
3. **Third** -- upload the PHP page: `fms_builder.php` (13)
4. **Fourth** -- upload all React source files (5-12) together since they depend on each other
5. **Last** -- upload docs: `README.md` and `EXECUTION_PLAN.md` (16-17)

