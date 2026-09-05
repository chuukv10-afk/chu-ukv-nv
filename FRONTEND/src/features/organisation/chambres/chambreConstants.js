export const DEFAULT_CHAMBRE_PAGE_SIZE = 10;
export const CHAMBRE_PAGE_SIZE_OPTIONS = [10, 25, 50];

export const CHAMBRE_TYPES = [
  { value: 'SIMPLE', label: 'Simple' },
  { value: 'DOUBLE', label: 'Double' },
  { value: 'VIP', label: 'VIP' },
  { value: 'ISOLE', label: 'Isolé' },
];

export const CHAMBRE_TYPE_LABELS = CHAMBRE_TYPES.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const EMPTY_CHAMBRE_FORM = {
  code: '',
  libelle: '',
  type: 'SIMPLE',
  blocId: '',
};
