export const PATIENT_SEXES = [
  { value: 'M', label: 'Masculin' },
  { value: 'F', label: 'Féminin' },
];

export const PATIENT_SEX_LABELS = PATIENT_SEXES.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const PATIENT_STATUSES = [
  { value: 'ACTIF', label: 'Actif', color: 'success' },
  { value: 'INACTIF', label: 'Inactif', color: 'neutral' },
  { value: 'DECEDE', label: 'Décédé', color: 'danger' },
];

export const PATIENT_STATUS_LABELS = PATIENT_STATUSES.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const PATIENT_STATUS_COLORS = PATIENT_STATUSES.reduce((acc, item) => {
  acc[item.value] = item.color;
  return acc;
}, {});

export const DPI_STATUTS = [
  { value: 'OUVERT', label: 'Ouvert', color: 'success' },
  { value: 'ARCHIVE', label: 'Archivé', color: 'warning' },
  { value: 'FERME', label: 'Fermé', color: 'neutral' },
];

export const DPI_STATUT_LABELS = DPI_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const DPI_STATUT_COLORS = DPI_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.color;
  return acc;
}, {});

export const GROUPE_SANGUIN_OPTIONS = [
  'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-',
];

export const DEFAULT_PATIENT_PAGE_SIZE = 10;
export const PATIENT_PAGE_SIZE_OPTIONS = [10, 25, 50];

export const EMPTY_PATIENT_FORM = {
  nom: '',
  postNom: '',
  prenom: '',
  telephone: '',
  adresse: '',
  lieuNaissance: '',
  dateNaissance: '',
  sexe: 'M',
  groupeSanguin: '',
  personneAprevenir: '',
  contactAPrevenir: '',
  status: 'ACTIF',
};
