export const VENTE_STATUTS = [
  { value: 'BROUILLON', label: 'Brouillon', color: 'neutral' },
  { value: 'VALIDEE', label: 'Validée', color: 'success' },
  { value: 'ANNULEE', label: 'Annulée', color: 'danger' },
];

export const VENTE_STATUT_LABELS = VENTE_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const VENTE_STATUT_COLORS = VENTE_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.color;
  return acc;
}, {});

export const VENTE_CLIENT_TYPES = [
  { value: 'PASSANT', label: 'Passant' },
  { value: 'PATIENT', label: 'Patient' },
  { value: 'HOSPITALISE', label: 'Hospitalisé' },
];

export const VENTE_MODES_PAIEMENT = [
  { value: 'ESPECES', label: 'Espèces' },
  { value: 'MOBILE', label: 'Mobile money' },
];

export const DEFAULT_VENTE_PAGE_SIZE = 10;
export const VENTE_PAGE_SIZE_OPTIONS = [10, 25, 50];

export const EMPTY_VENTE_LIGNE = {
  medicamentId: '',
  lotId: '',
  quantite: 1,
};

export function emptyVenteForm() {
  return {
    clientType: 'PASSANT',
    patientId: '',
    clientNom: '',
    visiteId: '',
    modePaiement: 'ESPECES',
    lignes: [{ ...EMPTY_VENTE_LIGNE }],
  };
}
