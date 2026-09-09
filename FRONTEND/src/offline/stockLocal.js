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
  if (medicamentId == null || medicamentId === '') return undefined;
  const numeric = Number(medicamentId);
  return (await offlineDb.stockLocal.get(String(medicamentId)))
    || (Number.isFinite(numeric) ? await offlineDb.stockLocal.get(numeric) : undefined)
    || undefined;
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
    const requestedLotId = ligne.lotId != null && ligne.lotId !== '' ? String(ligne.lotId) : '';
    const consumedLots = [];

    if (requestedLotId && requestedLotId !== 'NaN') {
      const lot = lots.find((item) => String(item.id) === requestedLotId);
      if (!lot || Number(lot.quantiteRestante || 0) < remaining) {
        const error = new Error('Lot local insuffisant.');
        error.offline = true;
        throw error;
      }
      lot.quantiteRestante = Number(lot.quantiteRestante) - remaining;
      consumedLots.push({ id: lot.id, quantite: remaining });
      remaining = 0;
    } else {
      for (const lot of lots) {
        if (remaining <= 0) break;
        const available = Number(lot.quantiteRestante || 0);
        if (available <= 0) continue;
        const take = Math.min(available, remaining);
        lot.quantiteRestante = available - take;
        consumedLots.push({ id: lot.id, quantite: take });
        remaining -= take;
      }
    }
    ligne.consumedLots = consumedLots;

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
    const quantite = Number(ligne.quantite || 0);
    if (quantite <= 0) continue;
    const current = await getLocalStock(ligne.medicamentId);
    if (!current) continue;
    const lots = [...(current.lots || [])];
    const consumed = Array.isArray(ligne.consumedLots) ? ligne.consumedLots : [];
    if (consumed.length > 0) {
      for (const taken of consumed) {
        const lot = lots.find((item) => String(item.id) === String(taken.id));
        if (lot) {
          lot.quantiteRestante = Number(lot.quantiteRestante || 0) + Number(taken.quantite || 0);
        }
      }
    } else if (ligne.lotId != null && ligne.lotId !== '') {
      const lot = lots.find((item) => String(item.id) === String(ligne.lotId));
      if (lot) {
        lot.quantiteRestante = Number(lot.quantiteRestante || 0) + quantite;
      }
    }
    await offlineDb.stockLocal.put({
      ...current,
      stockDisponible: Number(current.stockDisponible || 0) + quantite,
      lots,
    });
  }
}

export function makeLocalLotId(medicamentId, numeroLot) {
  return `offline-lot-${medicamentId}-${String(numeroLot || '').trim().toUpperCase() || Date.now()}`;
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
        id: ligne.lotId || makeLocalLotId(medicamentId, numeroLot),
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

export async function reverseReceptionStock(lignes = []) {
  for (const ligne of lignes) {
    const quantite = Number(ligne.quantite || 0);
    if (quantite <= 0) continue;
    const current = await getLocalStock(ligne.medicamentId);
    if (!current) continue;
    const lots = [...(current.lots || [])];
    const numero = String(ligne.numeroLot || '').trim().toUpperCase();
    const lot = (ligne.lotId != null && ligne.lotId !== ''
      ? lots.find((item) => String(item.id) === String(ligne.lotId))
      : null)
      || (numero ? lots.find((item) => String(item.numeroLot || '').toUpperCase() === numero) : null);
    if (lot) {
      lot.quantiteRestante = Math.max(0, Number(lot.quantiteRestante || 0) - quantite);
    }
    await offlineDb.stockLocal.put({
      ...current,
      stockDisponible: Math.max(0, Number(current.stockDisponible || 0) - quantite),
      lots,
    });
  }
}

export async function reverseLocalMutationStock(action, payload = {}) {
  const lignes = payload.lignes || [];
  if (
    action === 'pharmacie.vente.complete'
    || action === 'pharmacie.vente.create_and_valider'
    || action === 'pharmacie.vente.valider'
    || action === 'pharmacie.demande_service.delivrer'
  ) {
    await restoreLocalStock(lignes);
    return;
  }
  if (action === 'pharmacie.vente.annuler') {
    await decrementLocalStock(lignes);
    return;
  }
  if (action === 'pharmacie.reception.valider') {
    await reverseReceptionStock(lignes);
    return;
  }
  if (action === 'pharmacie.ajustement.create') {
    const reverseType = payload.type === 'AJUSTEMENT_PLUS' ? 'AJUSTEMENT_MOINS' : 'AJUSTEMENT_PLUS';
    await adjustLocalLot(payload.lotId, reverseType, payload.quantite);
  }
}

export async function updateLocalLotMeta(lotId, numeroLot, datePeremption) {
  if (lotId == null || lotId === '') return;
  const rows = await offlineDb.stockLocal.toArray();
  for (const row of rows) {
    const lot = (row.lots || []).find((item) => String(item.id) === String(lotId));
    if (!lot) continue;
    if (numeroLot) lot.numeroLot = String(numeroLot).trim().toUpperCase();
    if (datePeremption) lot.datePeremption = datePeremption;
    await offlineDb.stockLocal.put(row);
    return;
  }
}
