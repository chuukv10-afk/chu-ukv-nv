import { offlineDb } from './db.js';
import { setConnectivityPatch } from './connectivity.js';
import { namedListForAction } from './policies.js';
import { upsertNamedList, writeCache } from './cache.js';
import { restoreLocalStock } from './stockLocal.js';

export function createClientId() {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) {
    return crypto.randomUUID();
  }
  return `offline-${Date.now()}-${Math.random().toString(16).slice(2)}`;
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
  const clientId = createClientId();
  const createdAt = new Date().toISOString();
  const localId = optimistic?.id ?? `offline-${clientId}`;
  const optimisticData = {
    ...optimistic,
    id: localId,
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
    payload,
    optimistic: optimisticData,
    status: 'pending',
    createdAt,
    createdByTelephone,
  });

  if (endpoint && optimisticData) {
    await writeCache(detailEndpoint(endpoint, localId), {
      success: true,
      data: optimisticData,
    });
  }

  const listName = namedListForAction(action);
  if (listName) {
    await upsertNamedList(listName, optimisticData, { remove: action.endsWith('.delete') });
  }

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
  return offlineDb.outbox
    .where('status')
    .anyOf(['pending', 'conflict', 'rejected'])
    .sortBy('createdAt');
}

export async function findPendingByLocalId(localId) {
  if (localId == null || localId === '') return null;
  const pending = await listPendingMutations();
  return pending.find((row) => String(row.optimistic?.id) === String(localId)) ?? null;
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
  const existing = await offlineDb.conflicts.where('clientId').equals(clientId).toArray();
  await Promise.all(existing.map((row) => offlineDb.conflicts.delete(row.id)));
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
