/**
 * Settings store — persists to localStorage.
 */

const STORAGE_KEY = 'fms-builder-settings';

export const DEFAULT_SETTINGS = {
  // General
  autoSaveInterval: 0, // 0 = off, 30000, 60000, 300000

  // Canvas
  gridSnap: true,
  gridSnapSize: 16,
  backgroundStyle: 'dots', // 'dots' | 'lines' | 'cross' | 'none'
  defaultZoom: 1,
  showMinimap: true,
  edgeAnimation: false,

  // Node Defaults
  defaultPlannedDuration: 0,

  // Theme
  primaryColor: '#6366f1',
  primaryColorLight: '#8b5cf6',
  canvasBackground: '#0f0f14',
  nodeBorderStyle: 'default', // 'default' | 'rounded' | 'sharp'

  // Notifications
  showValidationOnSave: true,
  warnBeforeDelete: true,
  confirmLoadSample: true,
};

export function loadSettings() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return { ...DEFAULT_SETTINGS };
    const saved = JSON.parse(raw);
    return { ...DEFAULT_SETTINGS, ...saved };
  } catch {
    return { ...DEFAULT_SETTINGS };
  }
}

export function saveSettings(settings) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
  } catch {
    // ignore quota errors
  }
}

/** Build CSS gradient from primary colors */
export function getGradient(settings) {
  return `linear-gradient(135deg, ${settings.primaryColor} 0%, ${settings.primaryColorLight} 100%)`;
}
