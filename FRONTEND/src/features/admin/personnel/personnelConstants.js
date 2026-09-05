export const PERSONNEL_SEXES = [
  { value: 'M', label: 'Masculin' },
  { value: 'F', label: 'Féminin' },
];

export const PERSONNEL_SEX_LABELS = PERSONNEL_SEXES.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const PERSONNEL_TYPES = [
  { value: 'MEDICAL', label: 'Médical' },
  { value: 'PARAMEDICAL', label: 'Paramédical' },
  { value: 'ADMINISTRATIF', label: 'Administratif' },
  { value: 'TECHNIQUE', label: 'Technique' },
];

export const PERSONNEL_TYPE_LABELS = PERSONNEL_TYPES.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const MEDICAL_PERSONNEL_TYPES = ['MEDICAL', 'PARAMEDICAL'];

export function isMedicalPersonnelType(type) {
  return MEDICAL_PERSONNEL_TYPES.includes(type);
}

export const PERSONNEL_STATUSES = [
  { value: 'ACTIF', label: 'Actif', color: 'success' },
  { value: 'INACTIF', label: 'Inactif', color: 'neutral' },
  { value: 'SUSPENDU', label: 'Suspendu', color: 'warning' },
  { value: 'RETRAITE', label: 'Retraité', color: 'neutral' },
  { value: 'DEMISSION', label: 'Démission', color: 'neutral' },
  { value: 'MORTE', label: 'Décédé', color: 'neutral' },
];

export const PERSONNEL_STATUS_LABELS = PERSONNEL_STATUSES.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const PERSONNEL_STATUS_COLORS = PERSONNEL_STATUSES.reduce((acc, item) => {
  acc[item.value] = item.color;
  return acc;
}, {});

export const DEFAULT_PERSONNEL_PAGE_SIZE = 10;
export const PERSONNEL_PAGE_SIZE_OPTIONS = [10, 25, 50];

export const EMPTY_PERSONNEL_FORM = {
  nom: '',
  postNom: '',
  prenom: '',
  telephone: '',
  matricule: '',
  sexe: 'M',
  type: 'MEDICAL',
  status: 'ACTIF',
  password: '',
  adresse: '',
  lieuNaissance: '',
  cnome: '',
  gradeId: null,
  serviceId: null,
  specialiteIds: [],
  roleAssignments: [],
};

export const EMPTY_ROLE_ASSIGNMENT = {
  roleId: '',
  serviceId: null,
  departementId: null,
};
