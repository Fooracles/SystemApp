import React, { useMemo, useState } from 'react';
import { END_NODE_ID, START_NODE_ID } from '../data/flowModel';
import { validateFlow } from '../utils/validation';

function makeGraph(edges) {
  const out = new Map();
  edges.forEach((e) => {
    if (!out.has(e.source)) out.set(e.source, []);
    out.get(e.source).push(e);
  });
  return out;
}

function buildDryRunPath(flowState) {
  const nodesById = new Map(flowState.nodes.map((n) => [n.id, n]));
  const outgoing = makeGraph(flowState.edges);
  const visited = new Set();
  const steps = [];
  let current = START_NODE_ID;
  let guard = 0;
  while (current && guard < 500) {
    guard += 1;
    if (visited.has(current)) {
      steps.push({ type: 'warning', label: 'Cycle detected. Dry run stopped.' });
      break;
    }
    visited.add(current);
    const node = nodesById.get(current);
    if (!node) {
      steps.push({ type: 'warning', label: `Unknown node ${current}.` });
      break;
    }
    if (node.id !== START_NODE_ID && node.id !== END_NODE_ID) {
      steps.push({
        type: 'step',
        nodeId: node.id,
        name: node.data?.stepName || node.id,
        code: node.data?.stepCode || '-',
        ownerRole: node.data?.stepOwnerRole || '-',
        plannedDuration: node.data?.timeRules?.plannedDuration ?? 0,
        commentRequired: !!node.data?.validationRules?.commentRequired,
        attachmentRequired: !!node.data?.validationRules?.attachmentRequired,
      });
    }
    if (node.id === END_NODE_ID) break;

    const edges = outgoing.get(current) || [];
    if (edges.length === 0) {
      steps.push({ type: 'warning', label: `No outgoing edge from ${node.data?.stepName || current}.` });
      break;
    }
    // For dry run, prefer default path; if absent, use first edge.
    const next = edges.find((e) => e.condition === 'default') || edges[0];
    current = next.target;
  }
  return steps;
}

function computeChecklist(flowState, flowActive, validation) {
  const nodes = flowState.nodes || [];
  const edges = flowState.edges || [];
  const internalNodes = nodes.filter((n) => n.id !== START_NODE_ID && n.id !== END_NODE_ID);
  const outgoing = makeGraph(edges);
  const incoming = new Map();
  nodes.forEach((n) => incoming.set(n.id, []));
  edges.forEach((e) => {
    if (incoming.has(e.target)) incoming.get(e.target).push(e);
  });

  const items = [
    { label: 'Flow has a name', pass: !!flowState.flow?.name?.trim() && flowState.flow.name !== 'Untitled Flow' },
    { label: 'Flow is marked active', pass: !!flowActive },
    { label: 'Flow validation passes', pass: !!validation?.valid },
    { label: 'At least one step exists', pass: internalNodes.length > 0 },
    { label: 'All steps have names', pass: internalNodes.every((n) => !!n.data?.stepName?.trim()) },
    { label: 'All steps have owner role', pass: internalNodes.every((n) => !!n.data?.stepOwnerRole?.trim()) },
    { label: 'All steps have planned duration', pass: internalNodes.every((n) => Number(n.data?.timeRules?.plannedDuration ?? 0) > 0) },
    { label: 'No orphan internal nodes', pass: internalNodes.every((n) => (incoming.get(n.id)?.length || 0) > 0 && (outgoing.get(n.id)?.length || 0) > 0) },
    { label: 'Start node connected', pass: (outgoing.get(START_NODE_ID)?.length || 0) > 0 },
    { label: 'End node connected', pass: (incoming.get(END_NODE_ID)?.length || 0) > 0 },
  ];

  const passed = items.filter((x) => x.pass).length;
  return { items, passed, total: items.length, percent: Math.round((passed / items.length) * 100) };
}

export function TestsPanel({ flowState, flowActive }) {
  const [validation, setValidation] = useState(() => validateFlow(flowState));
  const [dryRunIndex, setDryRunIndex] = useState(0);

  const dryRunSteps = useMemo(() => buildDryRunPath(flowState), [flowState]);
  const checklist = useMemo(() => computeChecklist(flowState, flowActive, validation), [flowState, flowActive, validation]);

  return (
    <div style={{ flex: 1, minHeight: 0, overflow: 'auto', padding: 18, background: '#0f0f14', color: '#f4f4f5' }}>
      <div style={{ marginBottom: 18, border: '1px solid #2a2a35', background: '#18181b', borderRadius: 10, padding: 14 }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 10 }}>
          <strong style={{ fontSize: 14 }}>Validation</strong>
          <button
            onClick={() => setValidation(validateFlow(flowState))}
            style={{ border: '1px solid #3f3f46', background: '#0f0f14', color: '#d4d4d8', borderRadius: 6, padding: '5px 10px', cursor: 'pointer', fontSize: 12 }}
          >
            Run validation
          </button>
        </div>
        <div style={{ fontSize: 13, color: validation.valid ? '#22c55e' : '#f87171', marginBottom: 8 }}>
          {validation.valid ? 'Validation passed' : 'Validation failed'}
        </div>
        {!validation.valid && (
          <ul style={{ margin: 0, paddingLeft: 18, color: '#fca5a5', fontSize: 12 }}>
            {validation.errors.map((e, i) => <li key={`${e}-${i}`}>{e}</li>)}
          </ul>
        )}
      </div>

      <div style={{ marginBottom: 18, border: '1px solid #2a2a35', background: '#18181b', borderRadius: 10, padding: 14 }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 10 }}>
          <strong style={{ fontSize: 14 }}>Dry Run</strong>
          <div style={{ display: 'flex', gap: 8 }}>
            <button
              onClick={() => setDryRunIndex(0)}
              style={{ border: '1px solid #3f3f46', background: '#0f0f14', color: '#d4d4d8', borderRadius: 6, padding: '5px 10px', cursor: 'pointer', fontSize: 12 }}
            >
              Reset
            </button>
            <button
              onClick={() => setDryRunIndex((v) => Math.min(v + 1, dryRunSteps.length))}
              style={{ border: '1px solid #3f3f46', background: '#0f0f14', color: '#d4d4d8', borderRadius: 6, padding: '5px 10px', cursor: 'pointer', fontSize: 12 }}
            >
              Next Step
            </button>
          </div>
        </div>
        {dryRunSteps.length === 0 && <div style={{ fontSize: 12, color: '#71717a' }}>No executable path found.</div>}
        {dryRunSteps.slice(0, dryRunIndex).map((s, idx) => (
          <div key={`${s.nodeId || s.label}-${idx}`} style={{ border: '1px solid #2a2a35', borderRadius: 8, padding: 10, marginBottom: 8, background: '#0f0f14' }}>
            {s.type === 'warning' ? (
              <div style={{ fontSize: 12, color: '#f59e0b' }}>{s.label}</div>
            ) : (
              <>
                <div style={{ fontSize: 13, color: '#f4f4f5', marginBottom: 4 }}>{idx + 1}. {s.name}</div>
                <div style={{ fontSize: 12, color: '#a1a1aa' }}>
                  Code: {s.code} | Role: {s.ownerRole} | Duration: {s.plannedDuration}m
                </div>
                <div style={{ fontSize: 12, color: '#a1a1aa', marginTop: 2 }}>
                  Validation: comment {s.commentRequired ? 'required' : 'optional'}, attachment {s.attachmentRequired ? 'required' : 'optional'}
                </div>
              </>
            )}
          </div>
        ))}
      </div>

      <div style={{ border: '1px solid #2a2a35', background: '#18181b', borderRadius: 10, padding: 14 }}>
        <strong style={{ fontSize: 14, display: 'block', marginBottom: 10 }}>Pre-Deploy Checklist</strong>
        <div style={{ height: 8, borderRadius: 99, background: '#27272a', overflow: 'hidden', marginBottom: 10 }}>
          <div style={{ width: `${checklist.percent}%`, height: '100%', background: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)' }} />
        </div>
        <div style={{ fontSize: 12, color: '#a1a1aa', marginBottom: 10 }}>{checklist.passed}/{checklist.total} checks passed</div>
        {checklist.items.map((item) => (
          <div key={item.label} style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 6, fontSize: 12, color: item.pass ? '#4ade80' : '#f87171' }}>
            <span>{item.pass ? '✓' : '✗'}</span>
            <span>{item.label}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

