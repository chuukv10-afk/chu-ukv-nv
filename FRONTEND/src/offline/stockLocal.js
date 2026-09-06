import { offlineDb } from './db.js';

export async function replaceStockSnapshot(stock = []) {
  await offlineDb.stockLocal.clear();
  if (!Array.isArray(stock) || stock.length === 0) {
    return;
  }
  await offlineDb.stockLocal.bulkPut(stock.map((item) => ({
    medicamentId: Number(item.medicamentId),
    stockDisponible: Number(item.stockDisponible || 0),
    lots: Array.isArray(item.lots) ? item.lots : [],
  })));
}

export async function getLocalStock(medicamentId) {
  return offlineDb.stockLocal.get(Number(medicamentId));
}

export async function decrementLocalStock(lignes = []) {
  for (const ligne of lignes) {
    const medicamentId = Number(ligne.medicamentId);
    const quantite = Number(ligne.quantite || 0);
    if (!medicamentId || quantite <= 0) {
      continue;
    }
    const current = await getLocalStock(medicamentId);
    if (!current) {
      const error = new Error('Stock local inconnu. Synchronisez avant de vendre hors-ligne.');
      error.offline = true;
      throw error;
    }
    if (Number(current.stockDisponible || 0) < quantite) {
      const error = new Error('Stock local insuffisant pour ce médicament.');
      error.offline = true;
      throw error;
    }

    const lots = [...(current.lots || [])];
    let remaining = quantite;
    const requestedLotId = ligne.lotId ? Number(ligne.lotId) : null;

    if (requestedLotId) {
      const lot = lots.find((item) => Number(item.id) === requestedLotId);
      if (!lot || Number(lot.quantiteRestante || 0) < remaining) {
        const error = new Error('Lot local insuffisant.');
        error.offline = true;
        throw error;
      }
      lot.quantiteRestante = Number(lot.quantiteRestante) - remaining;
      remaining = 0;
    } else {
      for (const lot of lots) {
        if (remaining <= 0) break;
        const available = Number(lot.quantiteRestante || 0);
        if (available <= 0) continue;
        const take = Math.min(available, remaining);
        lot.quantiteRestante = available - take;
        remaining -= take;
      }
    }

    if (remaining > 0) {
      const error = new Error('Stock local insuffisant (FEFO).');
      error.offline = true;
      throw error;
    }

    await offlineDb.stockLocal.put({
      ...current,
      stockDisponible: Number(current.stockDisponible) - quantite,
      lots,
    });
  }
}

export async function restoreLocalStock(lignes = []) {
  for (const ligne of lignes) {
    const medicamentId = Number(ligne.medicamentId);
    const quantite = Number(ligne.quantite || 0);
    if (!medicamentId || quantite <= 0) continue;
    const current = await getLocalStock(medicamentId);
    if (!current) continue;
    await offlineDb.stockLocal.put({
      ...current,
      stockDisponible: Number(current.stockDisponible || 0) + quantite,
    });
  }
}
