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

function pad2(value) {
  return String(value).padStart(2, '0');
}

function localYmd(date) {
  return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;
}

export function toDateOnly(value) {
  if (value == null || value === '') return null;
  if (value instanceof Date && !Number.isNaN(value.getTime())) {
    return localYmd(value);
  }
  const raw = String(value).trim();
  if (!raw) return null;
  if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
    return raw;
  }
  if (/(Z|[+-]\d{2}:?\d{2})$/i.test(raw)) {
    const parsed = new Date(raw);
    if (!Number.isNaN(parsed.getTime())) {
      return localYmd(parsed);
    }
  }
  const match = raw.match(/^(\d{4}-\d{2}-\d{2})/);
  return match ? match[1] : null;
}

export function toDateVenteIso(value, fallback = new Date().toISOString()) {
  const day = toDateOnly(value) || toDateOnly(fallback);
  if (day) return `${day}T12:00:00`;
  return fallback;
}

export function isHistoriqueDate(value, today = new Date()) {
  const day = toDateOnly(value);
  if (!day) return false;
  return day < localYmd(today);
}

/** Jour à envoyer au sync : uniquement une date déjà passée, sinon null (vente du jour). */
export function dateVenteForSync(value, today = new Date()) {
  const day = toDateOnly(value);
  if (!day || day >= localYmd(today)) return null;
  return day;
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
