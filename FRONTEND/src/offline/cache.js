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

function paginatedEnvelope(items, page = 1, limit = 10) {
  const total = items.length;
  const safePage = Math.max(1, page);
  const safeLimit = Math.max(1, limit);
  const start = (safePage - 1) * safeLimit;
  return {
    success: true,
    data: {
      items: items.slice(start, start + safeLimit),
      pagination: {
        page: safePage,
        limit: safeLimit,
        total,
        totalPages: total > 0 ? Math.ceil(total / safeLimit) : 0,
      },
    },
  };
}

const LIST_SOURCES = [
  { prefix: '/api/v1/pharmacie/medicaments', named: 'pharmacie.medicaments', searchKeys: ['libelle', 'code', 'dci'] },
  { prefix: '/api/v1/pharmacie/unites', named: 'pharmacie.unites', searchKeys: ['libelle', 'code'] },
  { prefix: '/api/v1/pharmacie/familles', named: 'pharmacie.familles', searchKeys: ['libelle', 'code'] },
  { prefix: '/api/v1/pharmacie/fournisseurs', named: 'pharmacie.fournisseurs', searchKeys: ['libelle', 'code', 'telephone'] },
  { prefix: '/api/v1/pharmacie/receptions', named: 'pharmacie.receptions', searchKeys: ['numero', 'referenceExterne'] },
  { prefix: '/api/v1/pharmacie/ventes', named: 'pharmacie.ventes', searchKeys: ['numero', 'clientNom'] },
  { prefix: '/api/v1/pharmacie/demandes-service', named: 'pharmacie.demandes', searchKeys: ['numero', 'motif'] },
  { prefix: '/api/v1/pharmacie/lots', named: 'pharmacie.lots', searchKeys: ['numeroLot'] },
  { prefix: '/api/v1/pharmacie/mouvements', named: 'pharmacie.mouvements', searchKeys: ['type', 'motif'] },
];

const SKIP_SEGMENTS = ['actifs', 'export', 'meta', 'alertes', 'vendables', 'fiches-stock'];

function namedItems(raw) {
  if (Array.isArray(raw)) return raw;
  if (Array.isArray(raw?.items)) return raw.items;
  if (Array.isArray(raw?.data)) return raw.data;
  if (Array.isArray(raw?.data?.items)) return raw.data.items;
  return [];
}

function matchesSearch(item, search, keys) {
  if (!search) return true;
  const needle = String(search).toLowerCase();
  return keys.some((key) => String(item?.[key] ?? '').toLowerCase().includes(needle));
}

function isCollectionPath(path, prefix) {
  if (path !== prefix && !path.startsWith(`${prefix}?`)) {
    return false;
  }
  return true;
}

export async function readCacheWithFallback(endpoint) {
  const exact = await readCache(endpoint);
  if (exact) {
    return exact;
  }

  const [path, queryString = ''] = String(endpoint).split('?');
  if (SKIP_SEGMENTS.some((segment) => path.includes(`/${segment}`))) {
    return null;
  }

  const params = new URLSearchParams(queryString);
  const collection = LIST_SOURCES.find((item) => isCollectionPath(path, item.prefix));
  if (collection) {
    const items = namedItems(await readNamedCache(collection.named))
      .filter((item) => matchesSearch(item, params.get('search'), collection.searchKeys))
      .filter((item) => !params.get('statut') || item.statut === params.get('statut'))
      .filter((item) => !params.get('statutPaiement') || item.statutPaiement === params.get('statutPaiement'))
      .filter((item) => !params.get('medicamentId') || String(item.medicamentId) === params.get('medicamentId') || String(item.medicament?.id) === params.get('medicamentId'));
    if (items.length === 0) {
      return paginatedEnvelope([], Number(params.get('page') || 1), Number(params.get('limit') || 10));
    }
    return paginatedEnvelope(items, Number(params.get('page') || 1), Number(params.get('limit') || 10));
  }

  for (const source of LIST_SOURCES) {
    const match = path.match(new RegExp(`^${source.prefix.replaceAll('/', '\\/')}/([^/]+)$`));
    if (!match) continue;
    const id = match[1];
    const item = namedItems(await readNamedCache(source.named))
      .find((row) => String(row.id) === String(id));
    if (item) {
      return { success: true, data: item };
    }
  }

  return null;
}

export async function upsertNamedList(name, item, { remove = false } = {}) {
  if (!item) return;
  const current = namedItems(await readNamedCache(name));
  const next = remove
    ? current.filter((row) => String(row.id) !== String(item.id))
    : [item, ...current.filter((row) => String(row.id) !== String(item.id))];
  await writeNamedCache(name, next);
}

export function createOfflineReadError(endpoint) {
  const error = new Error('Donnée indisponible hors-ligne. Synchronisez une fois en ligne pour précharger la pharmacie.');
  error.status = 404;
  error.offline = true;
  error.endpoint = endpoint;
  return error;
}
