export const LOT_STATUTS = [
  { value: 'DISPONIBLE', label: 'Disponible', color: 'success' },
  { value: 'EPUISE', label: 'Épuisé', color: 'neutral' },
  { value: 'PERIME', label: 'Périmé', color: 'danger' },
  { value: 'BLOQUE', label: 'Bloqué', color: 'warning' },
];

export const LOT_STATUT_LABELS = LOT_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const LOT_STATUT_COLORS = LOT_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.color;
  return acc;
}, {});

export const DEFAULT_LOT_PAGE_SIZE = 10;
export const LOT_PAGE_SIZE_OPTIONS = [10, 25, 50];
