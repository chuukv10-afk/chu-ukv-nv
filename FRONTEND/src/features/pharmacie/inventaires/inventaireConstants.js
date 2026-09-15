export const INVENTAIRE_STATUTS = [
  { value: 'EN_COURS', label: 'En cours', color: 'warning' },
  { value: 'CLOTURE', label: 'Clôturée', color: 'success' },
];

export const INVENTAIRE_STATUT_LABELS = INVENTAIRE_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const INVENTAIRE_STATUT_COLORS = INVENTAIRE_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.color;
  return acc;
}, {});

export const DEFAULT_INVENTAIRE_PAGE_SIZE = 10;
export const INVENTAIRE_PAGE_SIZE_OPTIONS = [10, 25, 50];

export function inventaireDetailPath(id) {
  return `/pharmacie/inventaires/${id}`;
}

export function defaultInventaireLibelle(now = new Date()) {
  const day = String(now.getDate()).padStart(2, '0');
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const year = now.getFullYear();
  return `Inventaire du ${day}/${month}/${year}`;
}

export function formatPersonnelName(personne) {
  if (!personne) return '—';
  const parts = [personne.prenom, personne.nom].filter(Boolean);
  return parts.length ? parts.join(' ') : '—';
}
