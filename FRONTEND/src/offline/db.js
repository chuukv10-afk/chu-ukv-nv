import Dexie from 'dexie';

export const offlineDb = new Dexie('chu_ukv_offline');

offlineDb.version(1).stores({
  session: 'id',
  cache: 'key, updatedAt',
  outbox: '++id, clientId, status, createdAt',
  syncMeta: 'id',
  conflicts: '++id, clientId, createdAt',
  stockLocal: 'medicamentId',
});

export async function clearOfflineData() {
  await Promise.all([
    offlineDb.session.clear(),
    offlineDb.cache.clear(),
    offlineDb.outbox.clear(),
    offlineDb.syncMeta.clear(),
    offlineDb.conflicts.clear(),
    offlineDb.stockLocal.clear(),
  ]);
}
