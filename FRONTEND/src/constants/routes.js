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
    SIGNES_VITAUX: '/referentiel/signes-vitaux',
  },

  CLINIQUE: {
    EXAMENS: '/clinique/examens',
    DEMANDES_EXAMEN: '/clinique/demandes-examen',
    MALADIES: '/clinique/maladies',
    VISITES: '/clinique/visites',
    CONSULTATIONS: '/clinique/consultations',
    CONSULTATIONS_DETAIL: '/clinique/consultations/:id',
    TOUR_DE_SALLE: '/clinique/consultations/:id/tour',
    TOUR_DE_SALLE_FICHE: '/clinique/consultations/:id/tour/:fiche',
  },

  PATIENT: {
    LIST: '/patients',
    DPI: '/patients/:patientId/dpi',
  },

  ADMIN: {
    PERSONNEL: '/admin/personnel',
    ROLES: '/admin/roles',
    PERMISSIONS: '/admin/permissions',
    ROLE_PERMISSIONS: '/admin/role-permissions',
  },
};
