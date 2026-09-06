export const MEDICAMENT_STATUTS = [
  { value: 'ACTIF', label: 'Actif', color: 'success' },
  { value: 'INACTIF', label: 'Inactif', color: 'neutral' },
];

export const MEDICAMENT_STATUT_LABELS = MEDICAMENT_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const DEFAULT_MEDICAMENT_PAGE_SIZE = 10;
export const MEDICAMENT_PAGE_SIZE_OPTIONS = [10, 25, 50];

export const EMPTY_MEDICAMENT_FORM = {
  code: '',
  libelle: '',
  dci: '',
  forme: '',
  dosage: '',
  uniteId: '',
  familleId: '',
  prixVente: '',
  seuilAlerte: 0,
  statut: 'ACTIF',
};

export function formatPrixVente(value) {
  if (value === null || value === undefined || value === '') return '—';
  const amount = Number(value);
  if (Number.isNaN(amount)) return String(value);
  return `${amount.toLocaleString('fr-CD', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} FC`;
}
