import { extendTheme } from '@mui/joy/styles';
import {
  LOTRU_DANGER,
  LOTRU_LAYOUT,
  LOTRU_NEUTRAL,
  LOTRU_PRIMARY,
  LOTRU_PRIMARY_VARIANTS_DARK,
  LOTRU_PRIMARY_VARIANTS_LIGHT,
  LOTRU_SUCCESS,
  LOTRU_WARNING,
} from './lotruPalette.js';

export const joyTheme = extendTheme({
  fontFamily: {
    body: "'Be Vietnam Pro', var(--joy-fontFamily-fallback)",
    display: "'Inter', var(--joy-fontFamily-fallback)",
  },
  fontWeight: {
    sm: 300,
    md: 500,
    lg: 600,
    xl: 700,
  },
  radius: {
    xs: '2px',
    sm: '6px',
    md: '8px',
    lg: '12px',
    xl: '16px',
  },
  colorSchemes: {
    light: {
      palette: {
        primary: {
          ...LOTRU_PRIMARY,
          ...LOTRU_PRIMARY_VARIANTS_LIGHT,
        },
        neutral: LOTRU_NEUTRAL,
        danger: LOTRU_DANGER,
        success: LOTRU_SUCCESS,
        warning: LOTRU_WARNING,
        background: {
          body: LOTRU_LAYOUT.mainBg,
          surface: LOTRU_LAYOUT.cardBg,
          popup: LOTRU_LAYOUT.cardBg,
          level1: LOTRU_NEUTRAL[50],
          level2: LOTRU_NEUTRAL[100],
          level3: LOTRU_NEUTRAL[200],
          backdrop: 'rgba(17, 25, 39, 0.75)',
        },
        text: {
          primary: LOTRU_NEUTRAL[900],
          secondary: LOTRU_NEUTRAL[600],
          tertiary: LOTRU_NEUTRAL[500],
          icon: LOTRU_NEUTRAL[500],
        },
        divider: LOTRU_NEUTRAL[200],
        focusVisible: LOTRU_PRIMARY[500],
      },
    },
    dark: {
      palette: {
        primary: {
          ...LOTRU_PRIMARY,
          ...LOTRU_PRIMARY_VARIANTS_DARK,
        },
        neutral: LOTRU_NEUTRAL,
        danger: LOTRU_DANGER,
        success: LOTRU_SUCCESS,
        warning: LOTRU_WARNING,
        background: {
          body: LOTRU_LAYOUT.sidebarBg,
          surface: LOTRU_LAYOUT.sidebarBg,
          popup: LOTRU_LAYOUT.sidebarSurface,
          level1: LOTRU_LAYOUT.sidebarSurface,
          level2: LOTRU_NEUTRAL[800],
          level3: LOTRU_NEUTRAL[700],
          backdrop: 'rgba(9, 10, 11, 0.9)',
        },
        text: {
          primary: '#FFFFFF',
          secondary: LOTRU_NEUTRAL[300],
          tertiary: LOTRU_NEUTRAL[400],
          icon: LOTRU_NEUTRAL[400],
        },
        divider: LOTRU_LAYOUT.sidebarBorder,
        focusVisible: LOTRU_PRIMARY[400],
      },
    },
  },
  components: {
    JoyCard: {
      defaultProps: {
        variant: 'outlined',
      },
      styleOverrides: {
        root: {
          borderRadius: 'var(--joy-radius-lg)',
          boxShadow: 'none',
          bgcolor: LOTRU_LAYOUT.cardBg,
          borderColor: LOTRU_NEUTRAL[200],
        },
      },
    },
    JoyListItemButton: {
      styleOverrides: {
        root: ({ ownerState }) => ({
          fontWeight: 500,
          borderRadius: 'var(--joy-radius-md)',
          ...(ownerState.selected && {
            fontWeight: 600,
          }),
        }),
      },
    },
    JoyInput: {
      defaultProps: {
        variant: 'outlined',
      },
      styleOverrides: {
        root: {
          borderRadius: 'var(--joy-radius-md)',
        },
      },
    },
    JoyIconButton: {
      styleOverrides: {
        root: {
          borderRadius: 'var(--joy-radius-md)',
        },
      },
    },
    JoyButton: {
      styleOverrides: {
        root: {
          borderRadius: 'var(--joy-radius-md)',
        },
      },
    },
  },
});
