export const PAIE_STATUTS = [
  { value: 'BROUILLON', label: 'En cours', color: 'warning' },
  { value: 'VALIDE', label: 'Clôturé', color: 'success' },
];

export const PAIE_STATUT_LABELS = Object.fromEntries(PAIE_STATUTS.map((item) => [item.value, item.label]));
export const PAIE_STATUT_COLORS = Object.fromEntries(PAIE_STATUTS.map((item) => [item.value, item.color]));

export const PAIE_ETAT_LABELS = {
  ok: { label: 'Tarif', color: 'success' },
  ajuste: { label: 'Ajusté', color: 'warning' },
  sans_fonction: { label: 'Fonction manquante', color: 'danger' },
  sans_grade: { label: 'Grade manquant', color: 'danger' },
  sans_bareme: { label: 'Montant à saisir', color: 'warning' },
  inactif: { label: 'Non actif', color: 'neutral' },
  exclu: { label: 'Non payé', color: 'neutral' },
};

export function ligneEtat(ligne) {
  return PAIE_ETAT_LABELS[ligne?.etat] ?? { label: ligne?.motifCode || '—', color: 'neutral' };
}

export const PAIE_MOIS = [
  { value: 1, label: 'Janvier' },
  { value: 2, label: 'Février' },
  { value: 3, label: 'Mars' },
  { value: 4, label: 'Avril' },
  { value: 5, label: 'Mai' },
  { value: 6, label: 'Juin' },
  { value: 7, label: 'Juillet' },
  { value: 8, label: 'Août' },
  { value: 9, label: 'Septembre' },
  { value: 10, label: 'Octobre' },
  { value: 11, label: 'Novembre' },
  { value: 12, label: 'Décembre' },
];

export function paieDetailPath(id) {
  return `/rh/paie/${id}`;
}

export function kinshasaMonth() {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Africa/Kinshasa',
    year: 'numeric',
    month: '2-digit',
  }).formatToParts(new Date());
  const year = Number(parts.find((part) => part.type === 'year')?.value);
  const month = Number(parts.find((part) => part.type === 'month')?.value);
  return {
    year: Number.isFinite(year) ? year : new Date().getFullYear(),
    month: Number.isFinite(month) ? month : new Date().getMonth() + 1,
  };
}

export function formatPaieMontant(value) {
  if (value === null || value === undefined || value === '') return '—';
  const amount = Number(value);
  if (Number.isNaN(amount)) return String(value);
  return amount.toLocaleString('fr-CD', { maximumFractionDigits: 0 });
}

export function formatPaieDateTime(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleString('fr-CD', {
    timeZone: 'Africa/Kinshasa',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export function isPaieProposal(ligne, montant) {
  if (ligne?.montantPropose == null || ligne.montantPropose === '') return false;
  return Number(montant) === Number(ligne.montantPropose);
}
