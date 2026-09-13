import { dateVenteForSync, toDateOnly } from '../features/pharmacie/ventes/venteConstants.js';

export function sanitizeSyncDates(payload = {}) {
  const next = { ...payload };
  const dateVente = dateVenteForSync(next.dateVente);
  const dateLivraison = dateVenteForSync(next.dateLivraison);
  const dateReception = toDateOnly(next.dateReception);
  if (dateVente) {
    next.dateVente = dateVente;
  } else {
    delete next.dateVente;
  }
  if (dateLivraison) {
    next.dateLivraison = dateLivraison;
  } else {
    delete next.dateLivraison;
  }
  if (dateReception) next.dateReception = dateReception;
  if (Array.isArray(next.lignes)) {
    next.lignes = next.lignes.map((ligne) => {
      if (!ligne || typeof ligne !== 'object') return ligne;
      const datePeremption = toDateOnly(ligne.datePeremption);
      return datePeremption ? { ...ligne, datePeremption } : ligne;
    });
  }
  return next;
}
