export const FAMILLE_STATUTS = [
  { value: 'ACTIF', label: 'Actif', color: 'success' },
  { value: 'INACTIF', label: 'Inactif', color: 'neutral' },
];

export const FAMILLE_STATUT_LABELS = FAMILLE_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const DEFAULT_FAMILLE_PAGE_SIZE = 10;
export const FAMILLE_PAGE_SIZE_OPTIONS = [10, 25, 50];

export const EMPTY_FAMILLE_FORM = {
  code: '',
  libelle: '',
  ordre: 0,
  statut: 'ACTIF',
};
