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

export async function incrementLocalStock(lignes = []) {
  for (const ligne of lignes) {
    const medicamentId = Number(ligne.medicamentId);
    const quantite = Number(ligne.quantite || 0);
    if (!medicamentId || quantite <= 0) continue;
    const current = await getLocalStock(medicamentId) || {
      medicamentId,
      stockDisponible: 0,
      lots: [],
    };
    const lots = [...(current.lots || [])];
    const numeroLot = String(ligne.numeroLot || '').trim();
    const existing = numeroLot
      ? lots.find((lot) => String(lot.numeroLot).toUpperCase() === numeroLot.toUpperCase())
      : null;
    if (existing) {
      existing.quantiteRestante = Number(existing.quantiteRestante || 0) + quantite;
    } else {
      lots.push({
        id: ligne.lotId || `offline-lot-${medicamentId}-${numeroLot || Date.now()}`,
        numeroLot: numeroLot || 'OFF',
        quantiteRestante: quantite,
        datePeremption: ligne.datePeremption || null,
        statut: 'DISPONIBLE',
      });
    }
    await offlineDb.stockLocal.put({
      ...current,
      stockDisponible: Number(current.stockDisponible || 0) + quantite,
      lots,
    });
  }
}

export async function adjustLocalLot(lotId, type, quantite) {
  const qty = Number(quantite || 0);
  if (!lotId || qty <= 0) {
    const error = new Error('Ajustement local invalide.');
    error.offline = true;
    throw error;
  }
  const plus = type === 'AJUSTEMENT_PLUS';
  const rows = await offlineDb.stockLocal.toArray();
  for (const row of rows) {
    const lot = (row.lots || []).find((item) => String(item.id) === String(lotId));
    if (!lot) continue;
    const nextLotQty = Number(lot.quantiteRestante || 0) + (plus ? qty : -qty);
    if (nextLotQty < 0) {
      const error = new Error('Stock local insuffisant pour cet ajustement.');
      error.offline = true;
      throw error;
    }
    lot.quantiteRestante = nextLotQty;
    await offlineDb.stockLocal.put({
      ...row,
      stockDisponible: Number(row.stockDisponible || 0) + (plus ? qty : -qty),
      lots: row.lots,
    });
    return row.medicamentId;
  }
  const error = new Error('Lot inconnu hors-ligne. Synchronisez avant d’ajuster le stock.');
  error.offline = true;
  throw error;
}
