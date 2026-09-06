export const UNITE_STATUTS = [
  { value: 'ACTIF', label: 'Actif', color: 'success' },
  { value: 'INACTIF', label: 'Inactif', color: 'neutral' },
];

export const UNITE_STATUT_LABELS = UNITE_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const DEFAULT_UNITE_PAGE_SIZE = 10;
export const UNITE_PAGE_SIZE_OPTIONS = [10, 25, 50];

export const EMPTY_UNITE_FORM = {
  code: '',
  libelle: '',
  ordre: 0,
  statut: 'ACTIF',
};
