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
  prixUnitaire: '',
};

export const DATE_STOCK_OUVERTURE = '2026-08-28';

export function toDateOnly(value) {
  if (value == null) return null;
  const raw = String(value).trim();
  if (!raw) return null;
  const match = raw.match(/^(\d{4}-\d{2}-\d{2})/);
  return match ? match[1] : null;
}

export function toDateVenteIso(value, fallback = new Date().toISOString()) {
  const day = toDateOnly(value) || toDateOnly(fallback);
  if (day) return `${day}T12:00:00`;
  return fallback;
}

export function isHistoriqueDate(value, today = new Date()) {
  if (!value) return false;
  const day = String(value).slice(0, 10);
  const month = String(today.getMonth() + 1).padStart(2, '0');
  const dayNum = String(today.getDate()).padStart(2, '0');
  return day < `${today.getFullYear()}-${month}-${dayNum}`;
}

export function emptyVenteForm() {
  return {
    clientType: 'PASSANT',
    patientId: '',
    clientNom: '',
    visiteId: '',
    modePaiement: 'ESPECES',
    dateVente: '',
    lignes: [{ ...EMPTY_VENTE_LIGNE }],
  };
}
