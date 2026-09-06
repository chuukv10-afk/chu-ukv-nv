import { API_BASE_URL } from '../constants/apiConfig.js';
import { AUTH_TOKEN_KEY } from '../constants/apiConfig.js';
import { auth } from '../api/endpoints.js';
import { isServerReachable, pingServer, setConnectivityPatch } from './connectivity.js';
import { writeCache, writeNamedCache } from './cache.js';
import { addConflict, listPendingMutations, markOutboxStatus, refreshOutboxCounts } from './outbox.js';
import { replaceStockSnapshot, restoreLocalStock } from './stockLocal.js';

async function authorizedJson(endpoint, method, body) {
  const token = localStorage.getItem(AUTH_TOKEN_KEY);
  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    method,
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    const error = new Error(payload?.message || 'Synchronisation impossible.');
    error.status = response.status;
    throw error;
  }
  return payload;
}

export async function pullSnapshots(modules = ['pharmacie', 'organisation', 'referentiel', 'clinique']) {
  const response = await authorizedJson(auth.syncPull, 'POST', { modules });
  const data = response?.data ?? response;
  await writeNamedCache('sync.pull', data);

  const pharmacie = data?.modules?.pharmacie;
  if (pharmacie) {
    await writeCache('/api/v1/pharmacie/medicaments/actifs', { success: true, data: pharmacie.medicaments });
    await writeCache('/api/v1/pharmacie/unites/actifs', { success: true, data: pharmacie.unites });
    await writeCache('/api/v1/pharmacie/familles/actifs', { success: true, data: pharmacie.familles });
    await writeCache('/api/v1/pharmacie/lots/alertes', { success: true, data: pharmacie.alertes });
    await replaceStockSnapshot(pharmacie.stock || []);
    for (const item of pharmacie.stock || []) {
      await writeCache(`/api/v1/pharmacie/lots/vendables/${item.medicamentId}`, {
        success: true,
        data: item.lots || [],
      });
    }
  }

  const organisation = data?.modules?.organisation;
  if (organisation?.services) {
    await writeCache('/api/v1/pharmacie/services-actifs', { success: true, data: organisation.services });
  }

  const clinique = data?.modules?.clinique;
  if (clinique?.visitesHospitalisees) {
    await writeCache('/api/v1/pharmacie/visites-hospitalisees', {
      success: true,
      data: clinique.visitesHospitalisees,
    });
  }

  return data;
}

export async function pushOutbox() {
  const pending = await listPendingMutations();
  if (pending.length === 0) {
    await refreshOutboxCounts();
    return [];
  }

  const response = await authorizedJson(auth.syncPush, 'POST', {
    mutations: pending.map((item) => ({
      clientId: item.clientId,
      module: item.module,
      action: item.action,
      payload: item.payload,
      createdAt: item.createdAt,
    })),
  });
  const results = response?.data?.results ?? [];

  for (const result of results) {
    if (result.status === 'ACCEPTED') {
      await markOutboxStatus(result.clientId, 'synced', { serverId: result.entityId, result });
    } else if (result.status === 'CONFLICT') {
      const row = pending.find((item) => item.clientId === result.clientId);
      if (row?.action?.includes('vente') && Array.isArray(row?.payload?.lignes)) {
        await restoreLocalStock(row.payload.lignes);
      }
      await markOutboxStatus(result.clientId, 'conflict', { result });
      await addConflict(result.clientId, result.message || 'Conflit de synchronisation.', row?.payload);
    } else {
      await markOutboxStatus(result.clientId, 'rejected', { result });
      await addConflict(result.clientId, result.message || 'Mutation rejetée.', result);
    }
  }

  return results;
}

export async function runSyncCycle() {
  const reachable = await pingServer();
  if (!reachable || !isServerReachable()) {
    return { pulled: false, pushed: [] };
  }

  setConnectivityPatch({ syncing: true });
  try {
    await pushOutbox();
    await pullSnapshots();
    return { pulled: true };
  } finally {
    setConnectivityPatch({ syncing: false });
    await refreshOutboxCounts();
  }
}
