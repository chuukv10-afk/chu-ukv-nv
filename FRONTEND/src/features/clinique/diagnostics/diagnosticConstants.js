export const DIAGNOSTIC_TYPE_LABELS = {
  PROVISOIRE: 'Provisoire',
  DEFINITIF: 'Définitif',
  DIFFERENTIEL: 'Différentiel',
};

export const DIAGNOSTIC_TYPE_COLORS = {
  PROVISOIRE: 'warning',
  DEFINITIF: 'danger',
  DIFFERENTIEL: 'neutral',
};

export const DIAGNOSTIC_CERTITUDE_LABELS = {
  SUSPECTE: 'Suspecté',
  PROBABLE: 'Probable',
  CONFIRMEE: 'Confirmée',
};

export const DIAGNOSTIC_CERTITUDE_COLORS = {
  SUSPECTE: 'warning',
  PROBABLE: 'primary',
  CONFIRMEE: 'success',
};

export const DEFAULT_DIAGNOSTIC_FORM = {
  type: 'PROVISOIRE',
  certitude: 'SUSPECTE',
  remarque: '',
};
