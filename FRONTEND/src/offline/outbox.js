import { offlineDb } from './db.js';
import { setConnectivityPatch } from './connectivity.js';
import { writeCache } from './cache.js';

export function createClientId() {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) {
    return crypto.randomUUID();
  }
  return `offline-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export async function refreshOutboxCounts() {
  const [pending, conflicts] = await Promise.all([
    offlineDb.outbox.where('status').equals('pending').count(),
    offlineDb.conflicts.count(),
  ]);
  setConnectivityPatch({ pending, conflicts });
  return { pending, conflicts };
}

export async function enqueueMutation({
  action,
  module,
  endpoint,
  method,
  payload,
  optimistic,
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
  });

  if (endpoint && optimisticData) {
    await writeCache(detailEndpoint(endpoint, localId), {
      success: true,
      data: optimisticData,
    });
  }

  await refreshOutboxCounts();
  return optimisticData;
}

function detailEndpoint(endpoint, id) {
  const [path] = endpoint.split('?');
  return `${path.replace(/\/$/, '')}/${id}`;
}

export async function listPendingMutations() {
  return offlineDb.outbox.where('status').equals('pending').sortBy('createdAt');
}

export async function markOutboxStatus(clientId, status, extra = {}) {
  const row = await offlineDb.outbox.where('clientId').equals(clientId).first();
  if (!row) return;
  await offlineDb.outbox.update(row.id, { status, ...extra });
  await refreshOutboxCounts();
}

export async function addConflict(clientId, message, payload) {
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
