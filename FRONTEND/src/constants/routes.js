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
    PLAINTES: '/referentiel/plaintes',
  },

  PHARMACIE: {
    UNITES: '/pharmacie/unites',
    FAMILLES: '/pharmacie/familles',
    MEDICAMENTS: '/pharmacie/medicaments',
    FOURNISSEURS: '/pharmacie/fournisseurs',
    RECEPTIONS: '/pharmacie/receptions',
    RECEPTION_NEW: '/pharmacie/receptions/nouveau',
    RECEPTION_DETAIL: '/pharmacie/receptions/:id',
    LOTS: '/pharmacie/lots',
    MOUVEMENTS: '/pharmacie/mouvements',
    VENTES: '/pharmacie/ventes',
    VENTE_NEW: '/pharmacie/ventes/nouveau',
    VENTE_DETAIL: '/pharmacie/ventes/:id',
    DEMANDES_SERVICE: '/pharmacie/demandes-service',
    DEMANDE_SERVICE_NEW: '/pharmacie/demandes-service/nouveau',
    DEMANDE_SERVICE_DETAIL: '/pharmacie/demandes-service/:id',
    CREANCES: '/pharmacie/creances',
    RECETTES: '/pharmacie/recettes',
    AJUSTEMENTS: '/pharmacie/ajustements',
    ALERTES: '/pharmacie/alertes',
    STATISTIQUES: '/pharmacie/statistiques',
  },

  INTENDANCE: {
    BIENS: '/intendance/parc',
    IDENTIFIER: '/intendance/identifier',
    FAMILLES: '/intendance/familles',
    TYPES: '/intendance/types',
    LOCAUX: '/intendance/locaux',
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
    APTITUDES: '/clinique/aptitudes',
    APTITUDE_NEW: '/clinique/aptitudes/nouveau',
    APTITUDE_DETAIL: '/clinique/aptitudes/:id',
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
    DATABASE: '/admin/base-de-donnees',
  },
};
