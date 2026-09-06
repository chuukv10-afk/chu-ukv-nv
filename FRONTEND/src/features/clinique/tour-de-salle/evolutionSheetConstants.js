export const EVOLUTION_SHEET_VERSION = 1;

export const CLINICAL_EVALUATION_OPTIONS = [
  { value: 'GOOD', label: 'Bonne évolution clinique' },
  { value: 'STABLE', label: 'Statique-clinique' },
  { value: 'WORSENING', label: 'Aggravation' },
];

export const DIAGNOSIS_EVOLUTION_MODES = [
  { value: 'RETAINED', label: 'Diagnostic retenu (pas de changement)' },
  { value: 'EVOLVED', label: 'Évolution des diagnostics' },
];

export const EMPTY_EVOLUTION_SHEET = {
  version: EVOLUTION_SHEET_VERSION,
  symptoms: {
    mode: 'NONE',
    selectedComplaints: [],
    freeText: '',
  },
  clinicalEvaluation: {
    type: '',
    worseningDetails: '',
  },
  diagnosisEvolutionMode: 'RETAINED',
  continueCurrentTreatment: true,
};
