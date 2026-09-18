export const aptitudeFieldSx = {
  width: '100%',
  minWidth: 0,
  '--Input-minHeight': { xs: '44px', md: '36px' },
  '--Select-minHeight': { xs: '44px', md: '36px' },
  '--Input-decoratorChildHeight': { xs: '36px', md: '32px' },
  fontSize: { xs: '16px', md: '14px' },
};

export const aptitudeFilterSx = {
  ...aptitudeFieldSx,
  width: { xs: '100%', sm: 'auto' },
  minWidth: { xs: '100%', sm: 148 },
  flex: { xs: '1 1 100%', sm: '1 1 148px' },
};

export function patientSearchLabel(patient) {
  if (!patient) return '';
  if (typeof patient === 'string') return patient;
  return patient.fullName || [patient.nom, patient.postNom, patient.prenom].filter(Boolean).join(' ');
}

export function patientSearchMeta(patient) {
  if (!patient) return '';
  return [
    patient.codeUkv ? `UKV ${patient.codeUkv}` : null,
    patient.numDossier ? `DPI ${patient.numDossier}` : null,
    patient.filiere?.libelle || patient.filiere?.code || null,
    patient.organisation?.libelle || null,
    patient.telephone || null,
  ].filter(Boolean).join(' · ');
}
