import React, { useState } from 'react';
import { DEFAULT_SETTINGS, getGradient } from '../store/settingsStore';

const AUTO_SAVE_OPTIONS = [
  { value: 0, label: 'Off' },
  { value: 30000, label: '30 seconds' },
  { value: 60000, label: '1 minute' },
  { value: 300000, label: '5 minutes' },
];

const BG_STYLE_OPTIONS = [
  { value: 'dots', label: 'Dots' },
  { value: 'lines', label: 'Lines' },
  { value: 'cross', label: 'Cross' },
  { value: 'none', label: 'None' },
];

const NODE_BORDER_OPTIONS = [
  { value: 'default', label: 'Default (12px)' },
  { value: 'rounded', label: 'Rounded (20px)' },
  { value: 'sharp', label: 'Sharp (4px)' },
];

const COLOR_PRESETS = [
  { label: 'Indigo', primary: '#6366f1', light: '#8b5cf6' },
  { label: 'Emerald', primary: '#10b981', light: '#34d399' },
  { label: 'Blue', primary: '#3b82f6', light: '#60a5fa' },
  { label: 'Rose', primary: '#f43f5e', light: '#fb7185' },
  { label: 'Amber', primary: '#f59e0b', light: '#fbbf24' },
  { label: 'Cyan', primary: '#06b6d4', light: '#22d3ee' },
];

const CANVAS_BG_PRESETS = [
  { label: 'Dark', value: '#0f0f14' },
  { label: 'Charcoal', value: '#1a1a2e' },
  { label: 'Navy', value: '#0d1117' },
  { label: 'Black', value: '#000000' },
];

const s = {
  container: {
    padding: 24,
    height: '100%',
    overflow: 'auto',
    background: '#0f0f14',
  },
  header: {
    marginBottom: 28,
  },
  title: {
    color: '#f4f4f5',
    fontSize: 22,
    fontWeight: 600,
    margin: 0,
  },
  subtitle: {
    color: '#71717a',
    fontSize: 13,
    marginTop: 4,
  },
  section: {
    marginBottom: 28,
  },
  sectionTitle: {
    fontSize: 14,
    fontWeight: 600,
    color: '#f4f4f5',
    marginBottom: 16,
    paddingBottom: 8,
    borderBottom: '1px solid #2a2a35',
  },
  row: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: '10px 0',
  },
  label: {
    fontSize: 13,
    color: '#a1a1aa',
  },
  description: {
    fontSize: 11,
    color: '#52525b',
    marginTop: 2,
  },
  select: {
    padding: '6px 10px',
    background: '#27272a',
    border: '1px solid #3f3f46',
    borderRadius: 6,
    color: '#f4f4f5',
    fontSize: 13,
    minWidth: 140,
    cursor: 'pointer',
  },
  input: {
    padding: '6px 10px',
    background: '#27272a',
    border: '1px solid #3f3f46',
    borderRadius: 6,
    color: '#f4f4f5',
    fontSize: 13,
    width: 80,
    textAlign: 'center',
  },
  toggle: {
    width: 40,
    height: 22,
    borderRadius: 11,
    border: 'none',
    cursor: 'pointer',
    position: 'relative',
    transition: 'background 0.2s',
    padding: 0,
    flexShrink: 0,
  },
  toggleKnob: {
    position: 'absolute',
    top: 3,
    width: 16,
    height: 16,
    borderRadius: '50%',
    background: '#fff',
    transition: 'left 0.2s',
    boxShadow: '0 1px 3px rgba(0,0,0,0.3)',
  },
  colorSwatches: {
    display: 'flex',
    gap: 8,
    flexWrap: 'wrap',
  },
  colorSwatch: {
    width: 32,
    height: 32,
    borderRadius: 8,
    cursor: 'pointer',
    border: '2px solid transparent',
    transition: 'border-color 0.15s, transform 0.1s',
  },
  colorSwatchActive: {
    border: '2px solid #fff',
    transform: 'scale(1.1)',
  },
  footer: {
    display: 'flex',
    gap: 8,
    paddingTop: 16,
    borderTop: '1px solid #2a2a35',
    marginTop: 8,
  },
  btnPrimary: {
    padding: '8px 20px',
    fontSize: 13,
    fontWeight: 500,
    border: 'none',
    borderRadius: 6,
    color: '#fff',
    cursor: 'pointer',
  },
  btnSecondary: {
    padding: '8px 20px',
    fontSize: 13,
    fontWeight: 500,
    background: '#3f3f46',
    border: 'none',
    borderRadius: 6,
    color: '#e8e8e8',
    cursor: 'pointer',
  },
};

function Toggle({ value, onChange, gradient }) {
  return (
    <button
      type="button"
      style={{
        ...s.toggle,
        background: value ? (gradient || '#6366f1') : '#3f3f46',
      }}
      onClick={() => onChange(!value)}
    >
      <span style={{ ...s.toggleKnob, left: value ? 21 : 3 }} />
    </button>
  );
}

function SettingRow({ label, description, children }) {
  return (
    <div style={s.row}>
      <div style={{ flex: 1 }}>
        <div style={s.label}>{label}</div>
        {description && <div style={s.description}>{description}</div>}
      </div>
      <div style={{ flexShrink: 0, marginLeft: 16 }}>
        {children}
      </div>
    </div>
  );
}

export function SettingsView({ settings, onSettingsChange, onShowToast }) {
  const [local, setLocal] = useState({ ...settings });

  const update = (key, value) => {
    setLocal((prev) => ({ ...prev, [key]: value }));
  };

  const handleSave = () => {
    onSettingsChange(local);
    onShowToast?.('Settings saved');
  };

  const handleReset = () => {
    setLocal({ ...DEFAULT_SETTINGS });
    onSettingsChange({ ...DEFAULT_SETTINGS });
    onShowToast?.('Settings reset to defaults');
  };

  const gradient = getGradient(local);

  return (
    <div style={s.container}>
      <div style={s.header}>
        <h1 style={s.title}>Settings</h1>
        <p style={s.subtitle}>Configure the FMS Builder to your preferences</p>
      </div>

      {/* ── 1. General ─── */}
      <div style={s.section}>
        <div style={s.sectionTitle}>General</div>
        <SettingRow label="Auto-save interval" description="Automatically save flow at regular intervals">
          <select
            style={s.select}
            value={local.autoSaveInterval}
            onChange={(e) => update('autoSaveInterval', Number(e.target.value))}
          >
            {AUTO_SAVE_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </select>
        </SettingRow>
      </div>

      {/* ── 2. Canvas ─── */}
      <div style={s.section}>
        <div style={s.sectionTitle}>Canvas</div>

        <SettingRow label="Grid snap" description="Snap nodes to a grid when dragging">
          <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
            <Toggle value={local.gridSnap} onChange={(v) => update('gridSnap', v)} gradient={gradient} />
            {local.gridSnap && (
              <input
                type="number"
                style={s.input}
                min={4}
                max={64}
                value={local.gridSnapSize}
                onChange={(e) => update('gridSnapSize', Math.max(4, Number(e.target.value) || 16))}
                title="Snap grid size (px)"
              />
            )}
          </div>
        </SettingRow>

        <SettingRow label="Background style" description="Visual pattern shown on the canvas">
          <select
            style={s.select}
            value={local.backgroundStyle}
            onChange={(e) => update('backgroundStyle', e.target.value)}
          >
            {BG_STYLE_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </select>
        </SettingRow>

        <SettingRow label="Default zoom level" description="Initial zoom when opening a flow">
          <input
            type="number"
            style={s.input}
            min={0.1}
            max={3}
            step={0.1}
            value={local.defaultZoom}
            onChange={(e) => update('defaultZoom', Math.max(0.1, Math.min(3, Number(e.target.value) || 1)))}
          />
        </SettingRow>

        <SettingRow label="Show minimap" description="Display a minimap in the bottom-right corner">
          <Toggle value={local.showMinimap} onChange={(v) => update('showMinimap', v)} gradient={gradient} />
        </SettingRow>

        <SettingRow label="Animated edges" description="Animate the flow direction on edges">
          <Toggle value={local.edgeAnimation} onChange={(v) => update('edgeAnimation', v)} gradient={gradient} />
        </SettingRow>
      </div>

      {/* ── 3. Node Defaults ─── */}
      <div style={s.section}>
        <div style={s.sectionTitle}>Node Defaults</div>

        <SettingRow label="Default planned duration" description="Default hours for new step nodes">
          <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
            <input
              type="number"
              style={s.input}
              min={0}
              max={999}
              value={local.defaultPlannedDuration}
              onChange={(e) => update('defaultPlannedDuration', Math.max(0, Number(e.target.value) || 0))}
            />
            <span style={{ fontSize: 12, color: '#71717a' }}>hours</span>
          </div>
        </SettingRow>
      </div>

      {/* ── 4. Theme / Appearance ─── */}
      <div style={s.section}>
        <div style={s.sectionTitle}>Theme / Appearance</div>

        <SettingRow label="Primary color" description="Accent color used throughout the builder">
          <div style={s.colorSwatches}>
            {COLOR_PRESETS.map((c) => (
              <div
                key={c.label}
                title={c.label}
                style={{
                  ...s.colorSwatch,
                  background: `linear-gradient(135deg, ${c.primary} 0%, ${c.light} 100%)`,
                  ...(local.primaryColor === c.primary ? s.colorSwatchActive : {}),
                }}
                onClick={() => {
                  update('primaryColor', c.primary);
                  update('primaryColorLight', c.light);
                }}
              />
            ))}
          </div>
        </SettingRow>

        <SettingRow label="Canvas background" description="Background color of the flow canvas">
          <div style={s.colorSwatches}>
            {CANVAS_BG_PRESETS.map((c) => (
              <div
                key={c.value}
                title={c.label}
                style={{
                  ...s.colorSwatch,
                  background: c.value,
                  border: local.canvasBackground === c.value
                    ? '2px solid #fff'
                    : '2px solid #3f3f46',
                  ...(local.canvasBackground === c.value ? { transform: 'scale(1.1)' } : {}),
                }}
                onClick={() => update('canvasBackground', c.value)}
              />
            ))}
          </div>
        </SettingRow>

        <SettingRow label="Node border style" description="Border radius for flow nodes">
          <select
            style={s.select}
            value={local.nodeBorderStyle}
            onChange={(e) => update('nodeBorderStyle', e.target.value)}
          >
            {NODE_BORDER_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </select>
        </SettingRow>
      </div>

      {/* ── 5. Notifications ─── */}
      <div style={s.section}>
        <div style={s.sectionTitle}>Notifications / Alerts</div>

        <SettingRow label="Validation warnings on save" description="Run validation before saving and show warnings">
          <Toggle value={local.showValidationOnSave} onChange={(v) => update('showValidationOnSave', v)} gradient={gradient} />
        </SettingRow>

        <SettingRow label="Warn before deleting nodes" description="Show a confirmation dialog before node deletion">
          <Toggle value={local.warnBeforeDelete} onChange={(v) => update('warnBeforeDelete', v)} gradient={gradient} />
        </SettingRow>

        <SettingRow label="Confirm loading sample" description="Ask for confirmation before overwriting current flow">
          <Toggle value={local.confirmLoadSample} onChange={(v) => update('confirmLoadSample', v)} gradient={gradient} />
        </SettingRow>
      </div>

      {/* ── Footer ─── */}
      <div style={s.footer}>
        <button style={{ ...s.btnPrimary, background: gradient }} onClick={handleSave}>
          Save Settings
        </button>
        <button style={s.btnSecondary} onClick={handleReset}>
          Reset to Defaults
        </button>
      </div>
    </div>
  );
}
