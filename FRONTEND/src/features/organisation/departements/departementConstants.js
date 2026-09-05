export const DEPARTEMENT_TYPES = [
  { value: 'CLINIQUE', label: 'Clinique' },
  { value: 'ADMINISTRATIF', label: 'Administratif' },
  { value: 'TECHNIQUE', label: 'Technique' },
  { value: 'APPUI', label: 'Appui' },
];

export const DEPARTEMENT_TYPE_LABELS = DEPARTEMENT_TYPES.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const DEFAULT_DEPARTEMENT_PAGE_SIZE = 10;
export const DEPARTEMENT_PAGE_SIZE_OPTIONS = [10, 25, 50];

export const EMPTY_DEPARTEMENT_FORM = {
  code: '',
  libelle: '',
  type: 'CLINIQUE',
};
