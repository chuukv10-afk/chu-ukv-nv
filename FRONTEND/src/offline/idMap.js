import { offlineDb } from './db.js';

const ID_KEYS = [
  'id',
  'medicamentId',
  'lotId',
  'uniteId',
  'familleId',
  'fournisseurId',
  'serviceId',
  'visiteId',
  'receptionId',
];

export function isLocalId(value) {
  if (value == null || value === '') {
    return false;
  }
  const raw = String(value);
  if (raw.startsWith('offline-')) {
    return true;
  }
  return Number.isNaN(Number(raw));
}

export async function rememberIdMapping(entityType, localId, serverId) {
  if (!localId || !serverId) {
    return;
  }
  await offlineDb.idMap.put({
    localId: String(localId),
    entityType: entityType || '',
    serverId: String(serverId),
  });
}

export async function resolveServerId(value) {
  if (value == null || value === '') {
    return value;
  }
  if (!isLocalId(value)) {
    return value;
  }
  const row = await offlineDb.idMap.get(String(value));
  return row?.serverId ?? value;
}

function coerceId(key, value) {
  if (value == null || value === '') {
    return value;
  }
  if (key === 'patientId' || String(value).startsWith('offline-')) {
    return value;
  }
  const numeric = Number(value);
  return Number.isFinite(numeric) && numeric > 0 ? numeric : value;
}

export async function resolvePayloadIds(payload) {
  if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
    return payload;
  }

  const next = { ...payload };
  for (const key of ID_KEYS) {
    if (next[key] != null && next[key] !== '') {
      next[key] = coerceId(key, await resolveServerId(next[key]));
    }
  }
  if (next.patientId != null) {
    next.patientId = await resolveServerId(next.patientId);
  }
  if (Array.isArray(next.lignes)) {
    next.lignes = await Promise.all(next.lignes.map((ligne) => resolvePayloadIds(ligne)));
  }
  if (next.lotId != null && (isLocalId(next.lotId) || Number(next.lotId) <= 0 || Number.isNaN(Number(next.lotId)))) {
    next.lotId = null;
  }
  return next;
}
