export const DEMANDE_STATUTS = [
  { value: 'BROUILLON', label: 'Brouillon', color: 'neutral' },
  { value: 'ENVOYEE', label: 'Envoyée', color: 'primary' },
  { value: 'DELIVREE', label: 'Délivrée', color: 'success' },
  { value: 'REFUSEE', label: 'Refusée', color: 'danger' },
];

export const DEMANDE_STATUT_LABELS = Object.fromEntries(DEMANDE_STATUTS.map((item) => [item.value, item.label]));
export const DEMANDE_STATUT_COLORS = Object.fromEntries(DEMANDE_STATUTS.map((item) => [item.value, item.color]));

export const PAIEMENT_STATUTS = [
  { value: 'SANS_OBJET', label: 'Sans objet', color: 'neutral' },
  { value: 'IMPAYEE', label: 'Impayée', color: 'warning' },
  { value: 'PAYEE', label: 'Payée', color: 'success' },
];

export const PAIEMENT_STATUT_LABELS = Object.fromEntries(PAIEMENT_STATUTS.map((item) => [item.value, item.label]));
export const PAIEMENT_STATUT_COLORS = Object.fromEntries(PAIEMENT_STATUTS.map((item) => [item.value, item.color]));

export const DEFAULT_DEMANDE_PAGE_SIZE = 10;
export const DEMANDE_PAGE_SIZE_OPTIONS = [10, 25, 50];

export const EMPTY_DEMANDE_LIGNE = { medicamentId: '', quantite: 1 };

export function emptyDemandeForm() {
  return { serviceId: '', visiteId: '', motif: '', lignes: [{ ...EMPTY_DEMANDE_LIGNE }] };
}
