export const FOURNISSEUR_STATUTS = [
  { value: 'ACTIF', label: 'Actif', color: 'success' },
  { value: 'INACTIF', label: 'Inactif', color: 'neutral' },
];

export const FOURNISSEUR_STATUT_LABELS = FOURNISSEUR_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const DEFAULT_FOURNISSEUR_PAGE_SIZE = 10;
export const FOURNISSEUR_PAGE_SIZE_OPTIONS = [10, 25, 50];

export const EMPTY_FOURNISSEUR_FORM = {
  code: '',
  libelle: '',
  telephone: '',
  adresse: '',
  statut: 'ACTIF',
};
