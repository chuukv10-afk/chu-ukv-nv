export const PERSONNEL_TYPES = {
  MEDICAL: 'MEDICAL',
  PARAMEDICAL: 'PARAMEDICAL',
  ADMINISTRATIF: 'ADMINISTRATIF',
  TECHNIQUE: 'TECHNIQUE',
};

export function isMedicalProfile(type) {
  return type === PERSONNEL_TYPES.MEDICAL || type === PERSONNEL_TYPES.PARAMEDICAL;
}

export function canAccessModule(type, module) {
  if (module === 'CLINIQUE' || module === 'PATIENT') {
    return isMedicalProfile(type);
  }

  return true;
}
