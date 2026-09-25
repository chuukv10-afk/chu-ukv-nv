import { ROUTES } from '../../../constants/routes.js';

export const IMAGERIE_STATUTS = [
  { value: 'EN_ATTENTE', label: 'En attente', color: 'neutral' },
  { value: 'IMAGES', label: 'Images chargées', color: 'primary' },
  { value: 'INTERPRETE', label: 'Interprété', color: 'warning' },
  { value: 'VALIDE', label: 'Validé', color: 'success' },
  { value: 'ANNULEE', label: 'Annulée', color: 'danger' },
];

export const IMAGERIE_STATUT_LABELS = IMAGERIE_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const IMAGERIE_STATUT_COLORS = IMAGERIE_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.color;
  return acc;
}, {});

export const DEFAULT_IMAGERIE_PAGE_SIZE = 10;
export const IMAGERIE_PAGE_SIZE_OPTIONS = [10, 25, 50];
export const IMAGERIE_MAX_SIZE_BYTES = 50 * 1024 * 1024;
export const IMAGERIE_ACCEPT = 'image/jpeg,image/png,image/webp,application/pdf,.pdf';

export function medecinLabel(personnel) {
  if (!personnel) return '—';
  const nom = String(personnel.nom || personnel.fullName || '').trim();
  if (!nom) return '—';
  return nom.toLowerCase().startsWith('dr') ? nom : `Dr. ${nom}`;
}

export function isImageriePdf(image) {
  return String(image?.mimeType || '').toLowerCase().includes('pdf')
    || String(image?.originalName || '').toLowerCase().endsWith('.pdf');
}
export const IMAGERIE_INTERPRET_STATUTS = 'IMAGES,INTERPRETE,VALIDE';

export function imagerieDetailPath(id, tab = 'images') {
  const path = ROUTES.CLINIQUE.IMAGERIE_DETAIL.replace(':id', String(id));
  return tab && tab !== 'images' ? `${path}?tab=${tab}` : path;
}
