import { offlineDb } from './db.js';

export function cacheKey(endpoint) {
  return String(endpoint || '');
}

export async function writeCache(endpoint, payload) {
  await offlineDb.cache.put({
    key: cacheKey(endpoint),
    payload,
    updatedAt: Date.now(),
  });
}

export async function readCache(endpoint) {
  const row = await offlineDb.cache.get(cacheKey(endpoint));
  return row?.payload ?? null;
}

export async function writeNamedCache(name, payload) {
  await writeCache(`named:${name}`, payload);
}

export async function readNamedCache(name) {
  return readCache(`named:${name}`);
}

export function createOfflineReadError(endpoint) {
  const error = new Error('Donnée indisponible hors-ligne. Ouvrez cet écran une fois en ligne pour le précharger.');
  error.status = 404;
  error.offline = true;
  error.endpoint = endpoint;
  return error;
}
