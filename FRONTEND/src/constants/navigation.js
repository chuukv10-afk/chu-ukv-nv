import { ROUTES } from './routes.js';
import { PERMISSIONS } from './permissions.js';

export const NAV_SECTIONS = [
  {
    id: 'general',
    title: 'Général',
    items: [
      {
        label: 'Tableau de bord',
        to: ROUTES.DASHBOARD,
        icon: 'dashboard',
      },
    ],
  },
  {
    id: 'organisation',
    title: 'Organisation',
    items: [
      {
        label: 'Départements',
        to: ROUTES.ORGANISATION.DEPARTEMENTS,
        permission: PERMISSIONS.ORGANISATION.DEPARTEMENT_READ,
        icon: 'building',
      },
      {
        label: 'Services',
        to: ROUTES.ORGANISATION.SERVICES,
        permission: PERMISSIONS.ORGANISATION.SERVICE_READ,
        icon: 'network',
      },
      {
        label: 'Lits',
        to: ROUTES.ORGANISATION.LITS,
        permission: PERMISSIONS.ORGANISATION.LIT_READ,
        icon: 'bed',
      },
      {
        label: 'Chambres',
        to: ROUTES.ORGANISATION.CHAMBRES,
        permission: PERMISSIONS.ORGANISATION.CHAMBRE_READ,
        icon: 'door',
      },
      {
        label: 'Blocs',
        to: ROUTES.ORGANISATION.BLOCS,
        permission: PERMISSIONS.ORGANISATION.BLOC_READ,
        icon: 'boxes',
      },
    ],
  },
  {
    id: 'referentiel',
    title: 'Référentiel',
    items: [
      {
        label: 'Grades',
        to: ROUTES.REFERENTIEL.GRADES,
        permission: PERMISSIONS.REFERENTIEL.GRADE_READ,
        icon: 'award',
      },
      {
        label: 'Spécialités',
        to: ROUTES.REFERENTIEL.SPECIALITES,
        permission: PERMISSIONS.REFERENTIEL.SPECIALITE_READ,
        icon: 'stethoscope',
      },
      {
        label: 'Types d\'examen',
        to: ROUTES.REFERENTIEL.TYPES_EXAMEN,
        permission: PERMISSIONS.REFERENTIEL.TYPE_EXAMEN_READ,
        icon: 'flask',
      },
      {
        label: 'Types d\'antécédent',
        to: ROUTES.REFERENTIEL.TYPES_ANTECEDENT,
        permission: PERMISSIONS.REFERENTIEL.TYPE_ANTECEDENT_READ,
        icon: 'file',
      },
    ],
  },
  {
    id: 'clinique',
    title: 'Clinique',
    module: 'CLINIQUE',
    items: [
      {
        label: 'Examens',
        to: ROUTES.CLINIQUE.EXAMENS,
        permission: PERMISSIONS.CLINIQUE.EXAMEN_READ,
        icon: 'microscope',
      },
      {
        label: 'Visites',
        to: ROUTES.CLINIQUE.VISITES,
        module: 'CLINIQUE',
        icon: 'calendar',
      },
      {
        label: 'Consultations',
        to: ROUTES.CLINIQUE.CONSULTATIONS,
        module: 'CLINIQUE',
        icon: 'clipboard',
      },
    ],
  },
  {
    id: 'patient',
    title: 'Patients',
    module: 'PATIENT',
    items: [
      {
        label: 'Liste des patients',
        to: ROUTES.PATIENT.LIST,
        module: 'PATIENT',
        icon: 'users',
      },
      {
        label: 'Dossier patient (DPI)',
        to: ROUTES.PATIENT.DPI,
        module: 'PATIENT',
        icon: 'folder',
      },
    ],
  },
  {
    id: 'admin',
    title: 'Administration',
    adminOnly: true,
    items: [
      {
        label: 'Personnel',
        to: ROUTES.ADMIN.PERSONNEL,
        adminOnly: true,
        icon: 'userCog',
      },
      {
        label: 'Rôles',
        to: ROUTES.ADMIN.ROLES,
        adminOnly: true,
        icon: 'shield',
      },
      {
        label: 'Permissions',
        to: ROUTES.ADMIN.PERMISSIONS,
        adminOnly: true,
        icon: 'key',
      },
    ],
  },
];
