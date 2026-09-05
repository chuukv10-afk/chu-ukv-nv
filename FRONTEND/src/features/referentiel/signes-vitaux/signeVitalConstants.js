export const SIGNE_VITAL_STATUTS = [
  { value: 'ACTIF', label: 'Actif', color: 'success' },
  { value: 'INACTIF', label: 'Inactif', color: 'neutral' },
];

export const SIGNE_VITAL_STATUT_LABELS = SIGNE_VITAL_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const DEFAULT_SIGNE_VITAL_PAGE_SIZE = 10;
export const SIGNE_VITAL_PAGE_SIZE_OPTIONS = [10, 25, 50];

export const EMPTY_SIGNE_VITAL_FORM = {
  code: '',
  libelle: '',
  unite: '',
  demandeAuTriage: false,
  obligatoireAuTriage: false,
  ordre: 0,
  statut: 'ACTIF',
};
