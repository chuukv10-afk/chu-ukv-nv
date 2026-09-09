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

export function createLocalEntityId(kind = 'entity') {
  const uuid = (typeof crypto !== 'undefined' && crypto.randomUUID)
    ? crypto.randomUUID()
    : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
  return `offline-${kind}-${uuid}`;
}

export function toSyncId(value) {
  if (value == null || value === '') {
    return null;
  }
  const raw = String(value).trim();
  if (!raw || raw === 'NaN') {
    return null;
  }
  if (isLocalId(raw)) {
    return raw;
  }
  const numeric = Number(raw);
  return Number.isFinite(numeric) && numeric > 0 ? numeric : raw;
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
  if (!hasPositiveId(next.medicamentId) && next.medicament != null) {
    next.medicamentId = coerceId('medicamentId', await resolveServerId(flattenEntityId(next.medicament)));
  }
  if (!hasPositiveId(next.lotId) && next.lot != null) {
    next.lotId = coerceId('lotId', await resolveServerId(flattenEntityId(next.lot)));
  }
  if (next.lotId != null && (isLocalId(next.lotId) || Number(next.lotId) <= 0 || Number.isNaN(Number(next.lotId)))) {
    next.lotId = null;
  }
  return next;
}

export function flattenEntityId(value) {
  if (value == null || value === '') return value;
  if (typeof value === 'object' && !Array.isArray(value) && value.id != null) {
    return value.id;
  }
  return value;
}

function hasPositiveId(value) {
  if (value == null || value === '') return false;
  const numeric = Number(value);
  if (Number.isFinite(numeric)) {
    return numeric > 0;
  }
  return String(value).trim() !== '';
}
