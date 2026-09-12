import { offlineDb } from './db.js';
import { setConnectivityPatch } from './connectivity.js';
import { namedListForAction } from './policies.js';
import { upsertNamedList, writeCache, cacheKey } from './cache.js';
import { reverseLocalMutationStock, restoreLocalStock } from './stockLocal.js';
import { createLocalEntityId, isLocalId } from './idMap.js';

export function createClientId() {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) {
    return crypto.randomUUID();
  }
  return `offline-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function kindFromAction(action = '') {
  if (action.includes('reception')) return 'reception';
  if (action.includes('vente')) return 'vente';
  if (action.includes('demande')) return 'demande';
  if (action.includes('medicament')) return 'medicament';
  if (action.includes('fournisseur')) return 'fournisseur';
  if (action.includes('famille')) return 'famille';
  if (action.includes('unite')) return 'unite';
  if (action.includes('patient')) return 'patient';
  if (action.includes('visite')) return 'visite';
  if (action.includes('consultation')) return 'consultation';
  if (action.includes('lot')) return 'lot';
  return 'entity';
}

function isCreateAction(action = '') {
  return action.endsWith('.create')
    || action.endsWith('.complete')
    || action.includes('create_and_valider');
}

function isFollowUpAction(action = '') {
  return /\.(update|valider|envoyer|delivrer|refuser|regler|annuler|delete)$/.test(action);
}

async function listByStatus(statuses) {
  const wanted = new Set(statuses);
  try {
    const rows = await offlineDb.outbox.where('status').anyOf(statuses).sortBy('createdAt');
    if (Array.isArray(rows)) {
      return rows.filter(Boolean);
    }
  } catch {
    // SQLite / Dexie : repli sur la liste complète.
  }
  try {
    const all = await offlineDb.outbox.toArray();
    return (Array.isArray(all) ? all : [])
      .filter((row) => row && wanted.has(row.status))
      .sort((left, right) => String(left.createdAt || '').localeCompare(String(right.createdAt || '')));
  } catch {
    return [];
  }
}

function sameLocalEntity(row, localId) {
  if (localId == null || localId === '') return false;
  const needle = String(localId);
  return String(row.optimistic?.id ?? '') === needle || String(row.payload?.id ?? '') === needle;
}

async function findPendingCreate(localId) {
  const pending = await listByStatus(['pending']);
  return pending.find((row) => isCreateAction(row.action) && sameLocalEntity(row, localId)) ?? null;
}

async function findPendingFollowUp(action, localId) {
  const pending = await listByStatus(['pending']);
  return pending.find((row) => row.action === action && sameLocalEntity(row, localId)) ?? null;
}

async function persistOptimistic(endpoint, action, optimisticData, { remove = false } = {}) {
  if (endpoint && optimisticData) {
    await writeCache(detailEndpoint(endpoint, optimisticData.id), {
      success: true,
      data: optimisticData,
    });
  }
  const listName = namedListForAction(action);
  if (listName && optimisticData) {
    await upsertNamedList(listName, optimisticData, { remove });
  }
}

async function patchOutboxRow(row, changes) {
  await offlineDb.outbox.update(row.id, changes);
  await refreshOutboxCounts();
}

export async function refreshOutboxCounts() {
  const [pending, conflictRows] = await Promise.all([
    offlineDb.outbox.where('status').equals('pending').count(),
    offlineDb.conflicts.orderBy('createdAt').reverse().toArray(),
  ]);
  setConnectivityPatch({
    pending,
    conflicts: conflictRows.length,
    lastConflict: conflictRows[0]?.message || '',
  });
  return { pending, conflicts: conflictRows.length };
}

export async function enqueueMutation({
  action,
  module,
  endpoint,
  method,
  payload,
  optimistic,
  createdByTelephone = '',
}) {
  const nextPayload = { ...(payload || {}) };
  if (nextPayload.historique || nextPayload.saisieAnterieure) {
    if (nextPayload.dateVente) nextPayload.dateVente = String(nextPayload.dateVente).slice(0, 10);
  } else {
    delete nextPayload.dateVente;
  }
  if (nextPayload.dateReception) nextPayload.dateReception = String(nextPayload.dateReception).slice(0, 10);
  if (isCreateAction(action) && (nextPayload.id == null || nextPayload.id === '')) {
    nextPayload.id = optimistic?.id || createLocalEntityId(kindFromAction(action));
  }

  const localId = nextPayload.id ?? optimistic?.id;
  if (localId != null && isLocalId(localId) && isFollowUpAction(action)) {
    const pendingCreate = await findPendingCreate(localId);
    if (pendingCreate && action.endsWith('.update')) {
      const mergedPayload = { ...pendingCreate.payload, ...nextPayload, id: localId };
      const mergedOptimistic = {
        ...pendingCreate.optimistic,
        ...optimistic,
        id: localId,
        pendingSync: true,
      };
      await patchOutboxRow(pendingCreate, { payload: mergedPayload, optimistic: mergedOptimistic });
      await persistOptimistic(pendingCreate.endpoint || endpoint, pendingCreate.action, mergedOptimistic);
      return mergedOptimistic;
    }
    if (pendingCreate && action === 'pharmacie.vente.valider') {
      const mergedPayload = { ...pendingCreate.payload, ...nextPayload, id: localId };
      const mergedOptimistic = {
        ...pendingCreate.optimistic,
        ...optimistic,
        id: localId,
        statut: 'VALIDEE',
        pendingSync: true,
      };
      await patchOutboxRow(pendingCreate, {
        action: 'pharmacie.vente.create_and_valider',
        payload: mergedPayload,
        optimistic: mergedOptimistic,
      });
      await persistOptimistic(pendingCreate.endpoint || endpoint, pendingCreate.action, mergedOptimistic);
      return mergedOptimistic;
    }
    const duplicate = await findPendingFollowUp(action, localId);
    if (duplicate) {
      const mergedPayload = { ...duplicate.payload, ...nextPayload, id: localId };
      const mergedOptimistic = {
        ...duplicate.optimistic,
        ...optimistic,
        id: localId,
        pendingSync: true,
      };
      await patchOutboxRow(duplicate, { payload: mergedPayload, optimistic: mergedOptimistic });
      await persistOptimistic(duplicate.endpoint || endpoint, action, mergedOptimistic);
      return mergedOptimistic;
    }
  }

  const clientId = createClientId();
  const createdAt = new Date().toISOString();
  const entityId = localId || createLocalEntityId(kindFromAction(action));
  const optimisticData = {
    ...optimistic,
    id: entityId,
    clientId,
    pendingSync: true,
    createdAt,
  };

  await offlineDb.outbox.add({
    clientId,
    action,
    module,
    endpoint,
    method,
    payload: { ...nextPayload, id: nextPayload.id ?? entityId },
    optimistic: optimisticData,
    status: 'pending',
    createdAt,
    createdByTelephone,
  });

  await persistOptimistic(endpoint, action, optimisticData, { remove: action.endsWith('.delete') });
  await refreshOutboxCounts();
  return optimisticData;
}

function detailEndpoint(endpoint, id) {
  const [path] = endpoint.split('?');
  if (/\/\d+$/.test(path) || /\/offline-[^/]+$/.test(path)) {
    return path;
  }
  return `${path.replace(/\/$/, '')}/${id}`;
}

export async function listPendingMutations() {
  return listByStatus(['pending']);
}

export async function listRetryableMutations() {
  return listByStatus(['pending', 'conflict', 'rejected']);
}

export async function requeueUnblockedMutations(resolvePayload) {
  const blocked = await listByStatus(['conflict', 'rejected']);
  for (const row of blocked) {
    const payload = typeof resolvePayload === 'function'
      ? await resolvePayload(row.payload || {})
      : row.payload || {};
    const resolvedId = payload.id;
    const followUpNeedsServerId = isFollowUpAction(row.action);
    if (followUpNeedsServerId && (resolvedId == null || isLocalId(resolvedId))) {
      continue;
    }
    await clearConflict(row.clientId);
    await offlineDb.outbox.update(row.id, { status: 'pending', payload: { ...row.payload, ...payload } });
  }
}

export async function retryUnsynced(clientId) {
  if (!clientId) return false;
  const row = await offlineDb.outbox.where('clientId').equals(clientId).first();
  if (!row) return false;
  await clearConflict(clientId);
  await offlineDb.outbox.update(row.id, { status: 'pending' });
  await refreshOutboxCounts();
  return true;
}

export async function retryAllUnsynced() {
  const rows = await listByStatus(['conflict', 'rejected']);
  for (const row of rows) {
    await clearConflict(row.clientId);
    await offlineDb.outbox.update(row.id, { status: 'pending' });
  }
  await refreshOutboxCounts();
  return rows.length;
}

export async function findPendingByLocalId(localId) {
  if (localId == null || localId === '') return null;
  const pending = await listRetryableMutations();
  return pending.find((row) => sameLocalEntity(row, localId)) ?? null;
}

export async function cancelLocalMutation(localId, { restoreLignes = [] } = {}) {
  const row = await findPendingByLocalId(localId);
  if (!row) {
    return false;
  }
  if (restoreLignes.length > 0) {
    await restoreLocalStock(restoreLignes);
  }
  await offlineDb.outbox.update(row.id, { status: 'cancelled' });
  const listName = namedListForAction(row.action);
  if (listName && row.optimistic) {
    await upsertNamedList(listName, row.optimistic, { remove: true });
  }
  await refreshOutboxCounts();
  return true;
}

export async function markOutboxStatus(clientId, status, extra = {}) {
  const row = await offlineDb.outbox.where('clientId').equals(clientId).first();
  if (!row) return;
  await offlineDb.outbox.update(row.id, { status, ...extra });
  await refreshOutboxCounts();
}

export async function clearConflict(clientId) {
  if (clientId) {
    const existing = await offlineDb.conflicts.where('clientId').equals(clientId).toArray();
    await Promise.all(existing.map((row) => offlineDb.conflicts.delete(row.id)));
  }
  if (clientId && String(clientId).startsWith('orphan-')) {
    const numericId = Number(String(clientId).slice('orphan-'.length));
    if (Number.isFinite(numericId) && numericId > 0) {
      try {
        await offlineDb.conflicts.delete(numericId);
      } catch {
        // déjà supprimé
      }
    }
  }
}

export async function addConflict(clientId, message, payload) {
  await clearConflict(clientId);
  await offlineDb.conflicts.add({
    clientId,
    message,
    payload,
    createdAt: new Date().toISOString(),
  });
  await refreshOutboxCounts();
}

export async function listConflicts() {
  return offlineDb.conflicts.orderBy('createdAt').reverse().toArray();
}

async function removeOptimisticTrace(row) {
  const listName = namedListForAction(row.action);
  if (listName && row.optimistic) {
    await upsertNamedList(listName, row.optimistic, { remove: true });
  }
  const entityId = row.optimistic?.id ?? row.payload?.id;
  if (row.endpoint && entityId != null) {
    try {
      await offlineDb.cache.delete(cacheKey(detailEndpoint(row.endpoint, entityId)));
    } catch {
      // Dexie / SQLite : la clé de cache peut déjà être absente.
    }
  }
}

export async function listUnsyncedMutations() {
  const [rows, conflicts] = await Promise.all([
    listRetryableMutations(),
    listConflicts(),
  ]);
  const byClient = Object.fromEntries(
    conflicts
      .filter((item) => item?.clientId)
      .map((item) => [item.clientId, item]),
  );
  const listed = new Set();
  const items = rows.filter(Boolean).map((row) => {
    listed.add(row.clientId);
    const conflict = byClient[row.clientId];
    return {
      id: row.id,
      clientId: row.clientId,
      createdAt: row.createdAt || conflict?.createdAt,
      action: row.action,
      status: row.status,
      payload: row.payload || conflict?.payload,
      optimistic: row.optimistic,
      conflictRecordId: conflict?.id ?? null,
      message: conflict?.message
        || (row.status === 'pending'
          ? 'En attente de synchronisation.'
          : row.result?.message || 'Écriture refusée.'),
    };
  });

  for (const conflict of conflicts) {
    if (!conflict || listed.has(conflict.clientId)) continue;
    items.push({
      id: conflict.id != null ? `conflict-${conflict.id}` : `conflict-${conflict.clientId || conflict.createdAt}`,
      clientId: conflict.clientId || `orphan-${conflict.id || conflict.createdAt}`,
      createdAt: conflict.createdAt,
      action: conflict.payload?.action || conflict.payload?.optimistic?.action || '',
      status: 'conflict',
      payload: conflict.payload,
      optimistic: conflict.payload?.optimistic || conflict.payload,
      conflictRecordId: conflict.id ?? null,
      message: conflict.message || 'Écriture refusée.',
    });
  }

  return items;
}

export async function discardUnsynced(clientId, conflictRecordId = null) {
  const row = clientId
    ? await offlineDb.outbox.where('clientId').equals(clientId).first()
    : null;
  await clearConflict(clientId);
  if (conflictRecordId != null) {
    try {
      await offlineDb.conflicts.delete(conflictRecordId);
    } catch {
      // déjà supprimé
    }
  }
  if (!row) {
    await refreshOutboxCounts();
    return;
  }
  if (row.status === 'pending') {
    try {
      await reverseLocalMutationStock(row.action, row.payload || {});
    } catch {
      // Stock déjà rétabli, lot absent : on abandonne quand même l'écriture locale.
    }
  }
  await offlineDb.outbox.update(row.id, { status: 'discarded' });
  await removeOptimisticTrace(row);
  await refreshOutboxCounts();
}

export async function discardAllUnsynced() {
  const rows = await listUnsyncedMutations();
  for (const row of rows) {
    await discardUnsynced(row.clientId, row.conflictRecordId);
  }
}

export async function listConflictsWithMutations() {
  return listUnsyncedMutations();
}

export async function discardConflict(clientId) {
  await discardUnsynced(clientId);
}

export async function discardAllConflicts() {
  await discardAllUnsynced();
}
