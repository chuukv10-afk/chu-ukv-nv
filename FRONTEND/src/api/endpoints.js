export const auth = {
  login: '/api/v1/login',
  me: '/api/v1/me',
};

export const organisation = {
  departements: '/api/v1/organisation/departements',
  services: '/api/v1/organisation/services',
  lits: '/api/v1/organisation/lits',
  chambres: '/api/v1/organisation/chambres',
  blocs: '/api/v1/organisation/blocs',
};

export const referentiel = {
  grades: '/api/v1/referentiel/grades',
  specialites: '/api/v1/referentiel/specialites',
  typesExamen: '/api/v1/referentiel/types-examen',
  typesAntecedent: '/api/v1/referentiel/types-antecedent',
};

export const clinique = {
  examens: '/api/v1/clinique/examens',
  visites: '/api/v1/clinique/visites',
  consultations: '/api/v1/clinique/consultations',
};

export const patient = {
  list: '/api/v1/patients',
  dpi: '/api/v1/patients/dpi',
};

export const admin = {
  personnel: '/api/v1/admin/personnel',
  roles: '/api/v1/admin/roles',
  permissions: '/api/v1/admin/permissions',
};
