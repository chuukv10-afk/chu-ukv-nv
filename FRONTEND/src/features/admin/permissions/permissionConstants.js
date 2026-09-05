export const PERMISSION_MODULES = [
  { value: 'PATIENT', label: 'Patient' },
  { value: 'CLINIQUE', label: 'Clinique' },
  { value: 'FACTURATION', label: 'Facturation' },
  { value: 'ORGANISATION', label: 'Organisation' },
  { value: 'REFERENTIEL', label: 'Référentiel' },
  { value: 'ADMIN', label: 'Administration' },
];

export const PERMISSION_MODULE_LABELS = PERMISSION_MODULES.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const DEFAULT_PERMISSION_PAGE_SIZE = 10;
export const PERMISSION_PAGE_SIZE_OPTIONS = [10, 25, 50];

export const EMPTY_PERMISSION_FORM = {
  code: '',
  libelle: '',
  description: '',
  module: 'ADMIN',
};
