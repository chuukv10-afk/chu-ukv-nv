export const FICHES_IMPRIMABLES_MAX = 40;

export function clampFichePercent(value) {
  const parsed = Math.round(Number(value));
  if (!Number.isFinite(parsed)) {
    return 50;
  }
  return Math.min(100, Math.max(1, parsed));
}

export function splitFichesByPercent(items, percent) {
  const rate = clampFichePercent(percent);
  if (!items.length) {
    return { rate, pageSize: 0, pages: [] };
  }

  const pageSize = Math.max(1, Math.ceil((items.length * rate) / 100));
  const pages = [];
  for (let index = 0; index < items.length; index += pageSize) {
    pages.push(items.slice(index, index + pageSize));
  }

  return { rate, pageSize, pages };
}
