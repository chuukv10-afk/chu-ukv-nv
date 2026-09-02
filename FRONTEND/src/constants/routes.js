export const ROUTES = {
  HOME: '/',
  LOGIN: '/login',
  DASHBOARD: '/dashboard',
  ACCESS_DENIED: '/acces-refuse',
  NOT_FOUND: '*',

  ORGANISATION: {
    DEPARTEMENTS: '/organisation/departements',
    SERVICES: '/organisation/services',
    LITS: '/organisation/lits',
    CHAMBRES: '/organisation/chambres',
    BLOCS: '/organisation/blocs',
  },

  REFERENTIEL: {
    GRADES: '/referentiel/grades',
    SPECIALITES: '/referentiel/specialites',
    TYPES_EXAMEN: '/referentiel/types-examen',
    TYPES_ANTECEDENT: '/referentiel/types-antecedent',
  },

  CLINIQUE: {
    EXAMENS: '/clinique/examens',
    VISITES: '/clinique/visites',
    CONSULTATIONS: '/clinique/consultations',
  },

  PATIENT: {
    LIST: '/patients',
    DPI: '/patients/dpi',
  },

  ADMIN: {
    PERSONNEL: '/admin/personnel',
    ROLES: '/admin/roles',
    PERMISSIONS: '/admin/permissions',
  },
};
