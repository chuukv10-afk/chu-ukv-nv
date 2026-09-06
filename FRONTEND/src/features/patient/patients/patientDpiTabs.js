import { ROUTES } from '../../../constants/routes.js';

export const PATIENT_DPI_TAB_KEYS = {
  identite: 0,
  dossier: 1,
  antecedents: 2,
  diagnostics: 3,
  examens: 4,
  visites: 5,
  consultations: 6,
  hospitalisation: 7,
};

const TAB_KEY_BY_INDEX = Object.fromEntries(
  Object.entries(PATIENT_DPI_TAB_KEYS).map(([key, index]) => [index, key]),
);

export function resolvePatientDpiTabIndex(tabParam) {
  if (!tabParam) return PATIENT_DPI_TAB_KEYS.identite;
  return PATIENT_DPI_TAB_KEYS[tabParam] ?? PATIENT_DPI_TAB_KEYS.identite;
}

export function patientDpiTabKeyFromIndex(index) {
  return TAB_KEY_BY_INDEX[index] ?? 'identite';
}

export function buildPatientDpiPath(patientId, tabKey = 'identite') {
  const base = `/patients/${patientId}/dpi`;
  if (!tabKey || tabKey === 'identite') {
    return base;
  }
  return `${base}?tab=${tabKey}`;
}

export function getConsultationBackPath(consultation) {
  if (consultation?.patientId) {
    return buildPatientDpiPath(consultation.patientId, 'consultations');
  }
  return ROUTES.PATIENT.LIST;
}
