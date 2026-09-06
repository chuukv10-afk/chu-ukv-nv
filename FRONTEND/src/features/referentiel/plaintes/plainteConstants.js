export const PLAINTE_STATUTS = [
  { value: 'ACTIF', label: 'Actif', color: 'success' },
  { value: 'INACTIF', label: 'Inactif', color: 'neutral' },
];

export const PLAINTE_STATUT_LABELS = PLAINTE_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const DEFAULT_PLAINTE_PAGE_SIZE = 10;
export const PLAINTE_PAGE_SIZE_OPTIONS = [10, 25, 50];

export const EMPTY_PLAINTE_FORM = {
  code: '',
  libelle: '',
  ordre: 0,
  statut: 'ACTIF',
};

export const EMPTY_SYMPTOMS = {
  mode: 'NONE',
  selectedComplaints: [],
  freeText: '',
};
