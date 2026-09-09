import Dexie from 'dexie';
import { isDesktopApp } from './desktop.js';
import { createSqliteOfflineDb } from './sqliteAdapter.js';

function createDexieDb() {
  const db = new Dexie('chu_ukv_offline');

  db.version(1).stores({
    session: 'id',
    cache: 'key, updatedAt',
    outbox: '++id, clientId, status, createdAt',
    syncMeta: 'id',
    conflicts: '++id, clientId, createdAt',
    stockLocal: 'medicamentId',
  });

  db.version(2).stores({
    session: 'id',
    cache: 'key, updatedAt',
    outbox: '++id, clientId, status, createdAt',
    syncMeta: 'id',
    conflicts: '++id, clientId, createdAt',
    stockLocal: 'medicamentId',
    localUsers: 'telephone',
    idMap: 'localId, entityType, serverId',
  });

  return db;
}

export const offlineDb = isDesktopApp() ? createSqliteOfflineDb() : createDexieDb();

export async function clearOfflineData() {
  await Promise.all([
    offlineDb.session.clear(),
    offlineDb.cache.clear(),
    offlineDb.outbox.clear(),
    offlineDb.syncMeta.clear(),
    offlineDb.conflicts.clear(),
    offlineDb.stockLocal.clear(),
    offlineDb.idMap.clear(),
  ]);
}
