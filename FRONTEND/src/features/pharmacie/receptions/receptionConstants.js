export const RECEPTION_STATUTS = [
  { value: 'BROUILLON', label: 'Brouillon', color: 'neutral' },
  { value: 'VALIDEE', label: 'Validée', color: 'success' },
];

export const RECEPTION_STATUT_LABELS = RECEPTION_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const RECEPTION_STATUT_COLORS = RECEPTION_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.color;
  return acc;
}, {});

export const DEFAULT_RECEPTION_PAGE_SIZE = 10;
export const RECEPTION_PAGE_SIZE_OPTIONS = [10, 25, 50];

export const EMPTY_RECEPTION_LIGNE = {
  medicamentId: '',
  numeroLot: '',
  datePeremption: '',
  quantite: '',
  prixAchatUnitaire: '',
  prixVente: '',
};

export function emptyReceptionForm(dateReception) {
  return {
    fournisseurId: '',
    dateReception,
    referenceExterne: '',
    lignes: [{ ...EMPTY_RECEPTION_LIGNE }],
  };
}
