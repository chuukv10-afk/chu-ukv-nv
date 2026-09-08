import { offlineDb } from './db.js';
import { namedListForAction } from './policies.js';
import { lotsFromOutboxRow, movementsFromOutboxRow } from './stockJournal.js';
import { getLocalStock } from './stockLocal.js';

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
  { prefix: '/api/v1/pharmacie/mouvements', named: 'pharmacie.mouvements', searchKeys: ['type', 'motif', 'documentType'] },
  { prefix: '/api/v1/patients', named: 'patients', searchKeys: ['nom', 'postNom', 'prenom', 'fullName', 'telephone', 'numDossier'], extraEquals: ['status', 'sexe'] },
  { prefix: '/api/v1/clinique/visites', named: 'clinique.visites', searchKeys: ['patientName', 'motif', 'numDossier'], extraEquals: ['statut', 'serviceId'] },
  { prefix: '/api/v1/clinique/consultations', named: 'clinique.consultations', searchKeys: ['motif', 'patientName'], extraEquals: ['statut', 'visiteId', 'typeConsultation'] },
];

const NAMED_PREFIX = Object.fromEntries(LIST_SOURCES.map((item) => [item.named, item.prefix]));

const SKIP_SEGMENTS = ['export', 'meta', 'alertes', 'vendables', 'fiches-stock'];

function namedItems(raw) {
  if (Array.isArray(raw)) return raw;
  if (Array.isArray(raw?.items)) return raw.items;
  if (Array.isArray(raw?.data)) return raw.data;
  if (Array.isArray(raw?.data?.items)) return raw.data.items;
  return [];
}

function mergeById(rows) {
  const map = new Map();
  for (const row of rows) {
    if (row?.id == null) continue;
    const key = String(row.id);
    if (!map.has(key) || row.pendingSync) {
      map.set(key, { ...map.get(key), ...row });
    }
  }
  return [...map.values()];
}

function matchesSearch(item, search, keys) {
  if (!search) return true;
  const needle = String(search).toLowerCase();
  if (keys.some((key) => String(item?.[key] ?? '').toLowerCase().includes(needle))) return true;
  return [item?.medicament?.libelle, item?.medicament?.code, item?.lot?.numeroLot]
    .some((value) => String(value ?? '').toLowerCase().includes(needle));
}

function calendarDay(raw) {
  const date = new Date(raw);
  if (!Number.isNaN(date.getTime())) {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${date.getFullYear()}-${month}-${day}`;
  }
  return String(raw).slice(0, 10);
}

function inDateRange(item, from, to) {
  if (!from && !to) return true;
  const raw = item.dateVente || item.dateReception || item.enterAt || item.createdAt;
  if (!raw) return true;
  const day = calendarDay(raw);
  if (from && day < from) return false;
  if (to && day > to) return false;
  return true;
}

function isCollectionPath(path, prefix) {
  return path === prefix;
}

function matchesCollectionFilters(item, params, collection) {
  if (!matchesSearch(item, params.get('search'), collection.searchKeys)) return false;
  if (params.get('statut') && item.statut !== params.get('statut')) return false;
  if (params.get('statutPaiement') && item.statutPaiement !== params.get('statutPaiement')) return false;
  if (params.get('medicamentId') && String(item.medicamentId) !== params.get('medicamentId') && String(item.medicament?.id) !== params.get('medicamentId')) {
    return false;
  }
  if (!inDateRange(item, params.get('dateFrom'), params.get('dateTo'))) return false;
  if (params.get('pendingHospitalization') === 'true' && !item.pendingHospitalization) return false;
  for (const key of collection.extraEquals || []) {
    const expected = params.get(key);
    if (!expected) continue;
    if (String(item[key] ?? '') !== String(expected)) return false;
  }
  return true;
}

async function medicamentsById() {
  const items = namedItems(await readNamedCache('pharmacie.medicaments'));
  return new Map(items.map((item) => [String(item.id), item]));
}

async function applyLocalStockLevels(items) {
  const next = [];
  for (const item of items) {
    const local = await getLocalStock(item.id);
    next.push(local ? { ...item, stockDisponible: local.stockDisponible } : item);
  }
  return next;
}

async function outboxItemsFor(named) {
  const rows = await offlineDb.outbox
    .where('status')
    .anyOf(['pending', 'conflict', 'rejected'])
    .toArray();
  const extras = [];
  const removed = new Set();
  const lookup = (named === 'pharmacie.mouvements' || named === 'pharmacie.lots')
    ? await medicamentsById()
    : new Map();

  for (const row of rows) {
    if (named === 'pharmacie.mouvements') {
      extras.push(...movementsFromOutboxRow(row, lookup));
      continue;
    }
    if (named === 'pharmacie.lots') {
      extras.push(...lotsFromOutboxRow(row, lookup));
      continue;
    }
    if (namedListForAction(row.action) !== named) continue;
    const localId = row.optimistic?.id ?? row.payload?.id;
    if (row.action.endsWith('.delete') && localId != null) {
      removed.add(String(localId));
      continue;
    }
    if (row.optimistic) {
      extras.push({ ...row.optimistic, pendingSync: true });
    }
  }
  return { extras, removed };
}

async function visibleList(named, seed = []) {
  const { extras, removed } = await outboxItemsFor(named);
  const items = mergeById([...extras, ...seed])
    .filter((item) => !removed.has(String(item.id)));
  if (named === 'pharmacie.medicaments') {
    return applyLocalStockLevels(items);
  }
  return items;
}

function mergeVendableLots(localLots, cachedLots) {
  const map = new Map();
  for (const lot of [...cachedLots, ...localLots]) {
    if (!lot) continue;
    const key = String(lot.id ?? lot.numeroLot ?? '');
    if (!key) continue;
    map.set(key, { ...map.get(key), ...lot });
  }
  return [...map.values()].filter((lot) => Number(lot.quantiteRestante || 0) > 0);
}

async function overlayVendableLots(endpoint, payload) {
  const [path] = String(endpoint).split('?');
  const match = path.match(/\/lots\/vendables\/([^/]+)$/);
  if (!match) return payload;
  const local = await getLocalStock(match[1]);
  const localLots = (local?.lots || []).map((lot) => ({
    ...lot,
    medicamentId: local.medicamentId,
    statut: lot.statut || 'DISPONIBLE',
  }));
  const merged = mergeVendableLots(localLots, namedItems(payload));
  if (Array.isArray(payload)) return merged;
  if (Array.isArray(payload?.data)) return { ...payload, data: merged };
  return { success: true, data: merged };
}

export async function invalidateListCaches(prefix) {
  if (!prefix) return;
  const rows = await offlineDb.cache.toArray();
  await Promise.all(rows
    .filter((row) => {
      const key = String(row.key || '');
      if (key.startsWith('named:')) return false;
      const [path] = key.split('?');
      return path === prefix;
    })
    .map((row) => offlineDb.cache.delete(row.key)));
}

export async function readCacheWithFallback(endpoint) {
  const [path, queryString = ''] = String(endpoint).split('?');
  const params = new URLSearchParams(queryString);

  if (path.endsWith('/actifs')) {
    const source = LIST_SOURCES.find((item) => path === `${item.prefix}/actifs`);
    if (source) {
      const exact = await readCache(endpoint);
      const named = namedItems(await readNamedCache(source.named))
        .filter((item) => !item.statut || item.statut === 'ACTIF');
      const merged = await visibleList(source.named, [...named, ...namedItems(exact)]);
      return { success: true, data: merged.filter((item) => !item.statut || item.statut === 'ACTIF') };
    }
  }

  if (path.includes('/lots/vendables/')) {
    return overlayVendableLots(endpoint, await readCache(endpoint));
  }

  if (SKIP_SEGMENTS.some((segment) => path.includes(`/${segment}`))) {
    return readCache(endpoint);
  }

  const collection = LIST_SOURCES.find((item) => isCollectionPath(path, item.prefix));
  if (collection) {
    const exact = await readCache(endpoint);
    const named = namedItems(await readNamedCache(collection.named));
    const items = (await visibleList(collection.named, [...named, ...namedItems(exact)]))
      .filter((item) => matchesCollectionFilters(item, params, collection));
    return paginatedEnvelope(items, Number(params.get('page') || 1), Number(params.get('limit') || 10));
  }

  const exact = await readCache(endpoint);
  if (exact) {
    return exact;
  }

  for (const source of LIST_SOURCES) {
    const match = path.match(new RegExp(`^${source.prefix.replaceAll('/', '\\/')}/([^/]+)$`));
    if (!match) continue;
    const id = match[1];
    const items = await visibleList(source.named, namedItems(await readNamedCache(source.named)));
    const item = items.find((row) => String(row.id) === String(id));
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
  await invalidateListCaches(NAMED_PREFIX[name]);
}

export async function overlayPendingOnGet(endpoint, payload) {
  if (!payload) return payload;
  const [path, queryString = ''] = String(endpoint).split('?');
  const params = new URLSearchParams(queryString);

  if (path.includes('/lots/vendables/')) {
    return overlayVendableLots(endpoint, payload);
  }

  if (path.endsWith('/actifs')) {
    const source = LIST_SOURCES.find((item) => path === `${item.prefix}/actifs`);
    if (!source) return payload;
    const merged = (await visibleList(source.named, namedItems(payload)))
      .filter((item) => !item.statut || item.statut === 'ACTIF');
    if (Array.isArray(payload)) return merged;
    if (Array.isArray(payload?.data)) return { ...payload, data: merged };
    return { ...payload, success: true, data: merged };
  }

  const collection = LIST_SOURCES.find((item) => isCollectionPath(path, item.prefix));
  if (!collection) return payload;

  const { extras, removed } = await outboxItemsFor(collection.named);
  if (extras.length === 0 && removed.size === 0) {
    if (collection.named === 'pharmacie.medicaments') {
      const items = await applyLocalStockLevels(namedItems(payload));
      if (payload?.data?.items) {
        return { ...payload, data: { ...payload.data, items } };
      }
      return { ...payload, success: true, data: items };
    }
    return payload;
  }

  const matches = (item) => matchesCollectionFilters(item, params, collection);

  const extrasVisible = extras.filter(matches);
  const existing = namedItems(payload).filter((item) => !removed.has(String(item.id)));
  let items = mergeById([...extrasVisible, ...existing]);
  if (collection.named === 'pharmacie.medicaments') {
    items = await applyLocalStockLevels(items);
  }
  const extraOnlyCount = extrasVisible.filter((item) => !existing.some((row) => String(row.id) === String(item.id))).length;
  const pagination = payload?.data?.pagination;
  const baseTotal = Number(pagination?.total ?? existing.length);

  return {
    ...payload,
    success: payload.success !== false,
    data: {
      items,
      pagination: {
        page: Number(pagination?.page || params.get('page') || 1),
        limit: Number(pagination?.limit || params.get('limit') || 10),
        total: baseTotal + extraOnlyCount,
        totalPages: Math.max(
          1,
          Math.ceil((baseTotal + extraOnlyCount) / Number(pagination?.limit || params.get('limit') || 10)),
        ),
      },
    },
  };
}

export function createOfflineReadError(endpoint) {
  const error = new Error('Donnée indisponible hors-ligne. Synchronisez une fois en ligne pour précharger la pharmacie.');
  error.status = 404;
  error.offline = true;
  error.endpoint = endpoint;
  return error;
}
