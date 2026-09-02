/**
 * Palette Lotru — dashboard preview (MUI Store / tamplate.png).
 * Sidebar sombre + accent indigo + contenu clair.
 */

/** Accent indigo du dashboard (item actif, graphiques) */
export const LOTRU_PRIMARY = {
  50: '#eef2ff',
  100: '#e0e7ff',
  200: '#c7d2fe',
  300: '#a5b4fc',
  400: '#818cf8',
  500: '#6366f1',
  600: '#4f46e5',
  700: '#4338ca',
  800: '#3730a3',
  900: '#312e81',
};

export const LOTRU_NEUTRAL = {
  50: '#F9FAFB',
  100: '#F3F4F6',
  200: '#E5E7EB',
  300: '#D1D5DB',
  400: '#9CA3AF',
  500: '#6B7280',
  600: '#4B5563',
  700: '#374151',
  800: '#1F2937',
  900: '#111827',
  950: '#0e0f11',
};

/** Layout dashboard Lotru */
export const LOTRU_LAYOUT = {
  sidebarBg: '#111927',
  sidebarSurface: '#1C2536',
  sidebarBorder: 'rgba(255, 255, 255, 0.08)',
  sidebarText: '#E5E7EB',
  sidebarTextMuted: '#9CA3AF',
  sidebarSection: '#6B7280',
  mainBg: '#F9FAFB',
  cardBg: '#FFFFFF',
  chartLavender: '#C3C6FF',
  trendUp: '#10B981',
};

export const LOTRU_DANGER = {
  50: '#fef2f2',
  100: '#ffe1e1',
  200: '#ffc9c9',
  300: '#fea3a3',
  400: '#fb6e6e',
  500: '#f23a3a',
  600: '#e02222',
  700: '#bc1919',
  800: '#9c1818',
  900: '#811b1b',
};

export const LOTRU_SUCCESS = {
  50: '#ecfdf5',
  100: '#d1fae5',
  200: '#a7f3d0',
  300: '#6ee7b7',
  400: '#34d399',
  500: '#10B981',
  600: '#059669',
  700: '#047857',
  800: '#065f46',
  900: '#064e3b',
};

export const LOTRU_WARNING = {
  50: '#fffbeb',
  100: '#fef3c7',
  200: '#fde68a',
  300: '#fcd34d',
  400: '#fbbf24',
  500: '#f59e0b',
  600: '#d97706',
  700: '#b45309',
  800: '#92400e',
  900: '#78350f',
};

export const LOTRU_PRIMARY_VARIANTS_LIGHT = {
  plainColor: LOTRU_PRIMARY[500],
  plainHoverBg: LOTRU_PRIMARY[100],
  plainActiveBg: LOTRU_PRIMARY[200],
  plainDisabledColor: LOTRU_NEUTRAL[400],
  outlinedColor: LOTRU_PRIMARY[500],
  outlinedBorder: LOTRU_PRIMARY[300],
  outlinedHoverBg: LOTRU_PRIMARY[100],
  outlinedActiveBg: LOTRU_PRIMARY[200],
  outlinedDisabledColor: LOTRU_NEUTRAL[400],
  outlinedDisabledBorder: LOTRU_NEUTRAL[200],
  softColor: LOTRU_PRIMARY[700],
  softBg: LOTRU_PRIMARY[100],
  softHoverBg: LOTRU_PRIMARY[200],
  softActiveColor: LOTRU_PRIMARY[800],
  softActiveBg: LOTRU_PRIMARY[300],
  softDisabledColor: LOTRU_NEUTRAL[400],
  softDisabledBg: LOTRU_NEUTRAL[50],
  solidColor: '#FFFFFF',
  solidBg: LOTRU_PRIMARY[500],
  solidHoverBg: LOTRU_PRIMARY[600],
  solidActiveBg: LOTRU_PRIMARY[700],
  solidDisabledColor: LOTRU_NEUTRAL[400],
  solidDisabledBg: LOTRU_NEUTRAL[100],
};

export const LOTRU_PRIMARY_VARIANTS_DARK = {
  plainColor: LOTRU_PRIMARY[300],
  plainHoverBg: LOTRU_LAYOUT.sidebarSurface,
  plainActiveBg: LOTRU_PRIMARY[800],
  plainDisabledColor: LOTRU_NEUTRAL[500],
  outlinedColor: LOTRU_PRIMARY[200],
  outlinedBorder: LOTRU_PRIMARY[700],
  outlinedHoverBg: LOTRU_LAYOUT.sidebarSurface,
  outlinedActiveBg: LOTRU_PRIMARY[800],
  outlinedDisabledColor: LOTRU_NEUTRAL[500],
  outlinedDisabledBorder: LOTRU_LAYOUT.sidebarSurface,
  softColor: LOTRU_PRIMARY[200],
  softBg: LOTRU_PRIMARY[800],
  softHoverBg: LOTRU_PRIMARY[700],
  softActiveColor: LOTRU_PRIMARY[100],
  softActiveBg: LOTRU_PRIMARY[600],
  softDisabledColor: LOTRU_NEUTRAL[500],
  softDisabledBg: LOTRU_LAYOUT.sidebarSurface,
  solidColor: '#FFFFFF',
  solidBg: LOTRU_PRIMARY[500],
  solidHoverBg: LOTRU_PRIMARY[600],
  solidActiveBg: LOTRU_PRIMARY[700],
  solidDisabledColor: LOTRU_NEUTRAL[500],
  solidDisabledBg: LOTRU_LAYOUT.sidebarSurface,
};

export const LOTRU_THEME_COLOR = '#111927';
