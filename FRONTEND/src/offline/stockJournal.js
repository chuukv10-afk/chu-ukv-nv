function medicamentRef(ligne, medicamentsById) {
  if (ligne?.medicament?.libelle || ligne?.medicament?.code) {
    return {
      id: ligne.medicament.id ?? ligne.medicamentId,
      code: ligne.medicament.code || '',
      libelle: ligne.medicament.libelle || '',
    };
  }
  const med = medicamentsById.get(String(ligne?.medicamentId ?? ''));
  if (med) {
    return { id: med.id, code: med.code || '', libelle: med.libelle || '' };
  }
  if (ligne?.medicamentId == null) return null;
  return { id: ligne.medicamentId, code: '', libelle: '' };
}

function documentLignes(row) {
  const payload = row.payload || {};
  const optimistic = row.optimistic || {};
  if (Array.isArray(payload.lignes) && payload.lignes.length > 0) return payload.lignes;
  if (Array.isArray(optimistic.lignes) && optimistic.lignes.length > 0) return optimistic.lignes;
  return [];
}

function movementLine(row, index, type, sens, ligne, extra, medicamentsById) {
  return {
    id: `off-mvt-${row.clientId}-${index}`,
    type,
    sens,
    quantite: Number(ligne.quantite ?? extra.quantite ?? 0),
    documentType: extra.documentType,
    documentId: row.optimistic?.id ?? row.payload?.id,
    motif: extra.motif || '',
    createdAt: row.optimistic?.createdAt || row.createdAt,
    pendingSync: true,
    medicamentId: ligne.medicamentId ?? extra.medicamentId,
    medicament: medicamentRef({ ...ligne, medicamentId: ligne.medicamentId ?? extra.medicamentId }, medicamentsById),
    lot: extra.lot || (ligne.numeroLot || ligne.lotId
      ? { id: ligne.lotId, numeroLot: ligne.numeroLot || '—' }
      : null),
  };
}

export function movementsFromOutboxRow(row, medicamentsById = new Map()) {
  const action = row.action || '';
  const payload = row.payload || {};
  const optimistic = row.optimistic || {};
  const lignes = documentLignes(row);

  if (action === 'pharmacie.reception.valider') {
    return lignes.map((ligne, index) => movementLine(row, index, 'ENTREE_RECEPTION', 'ENTREE', ligne, {
      documentType: 'RECEPTION',
      motif: optimistic.referenceExterne || payload.referenceExterne || '',
      lot: { id: ligne.lotId, numeroLot: ligne.numeroLot || 'OFF' },
    }, medicamentsById));
  }

  if (action === 'pharmacie.vente.complete' || action === 'pharmacie.vente.create_and_valider' || action === 'pharmacie.vente.valider') {
    return lignes.map((ligne, index) => movementLine(row, index, 'SORTIE_VENTE', 'SORTIE', ligne, {
      documentType: 'VENTE',
    }, medicamentsById));
  }

  if (action === 'pharmacie.vente.annuler') {
    return lignes.map((ligne, index) => movementLine(row, index, 'ENTREE_ANNULATION_VENTE', 'ENTREE', ligne, {
      documentType: 'VENTE',
      motif: payload.motif || '',
    }, medicamentsById));
  }

  if (action === 'pharmacie.demande_service.delivrer') {
    return lignes.map((ligne, index) => movementLine(row, index, 'SORTIE_SERVICE', 'SORTIE', ligne, {
      documentType: 'DEMANDE_SERVICE',
    }, medicamentsById));
  }

  if (action === 'pharmacie.ajustement.create') {
    const type = payload.type || optimistic.type;
    return [movementLine(row, 0, type, type === 'AJUSTEMENT_PLUS' ? 'ENTREE' : 'SORTIE', {
      medicamentId: payload.medicamentId || optimistic.medicamentId,
      quantite: payload.quantite || optimistic.quantite,
      lotId: payload.lotId || optimistic.lotId,
    }, {
      documentType: 'AJUSTEMENT',
      motif: payload.motif || optimistic.motif || '',
      lot: { id: payload.lotId || optimistic.lotId, numeroLot: optimistic.lot?.numeroLot },
    }, medicamentsById)];
  }

  return [];
}

export function lotsFromOutboxRow(row, medicamentsById = new Map()) {
  if (row.action !== 'pharmacie.reception.valider') return [];
  return documentLignes(row).map((ligne, index) => ({
    id: ligne.lotId || `off-lot-${row.clientId}-${index}`,
    numeroLot: ligne.numeroLot || 'OFF',
    datePeremption: ligne.datePeremption || null,
    quantiteInitiale: Number(ligne.quantite || 0),
    quantiteRestante: Number(ligne.quantite || 0),
    medicamentId: ligne.medicamentId,
    medicament: medicamentRef(ligne, medicamentsById),
    statut: 'DISPONIBLE',
    pendingSync: true,
  }));
}
