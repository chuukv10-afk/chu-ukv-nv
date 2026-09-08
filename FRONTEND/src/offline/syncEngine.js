import { API_BASE_URL } from '../constants/apiConfig.js';
import { AUTH_TOKEN_KEY } from '../constants/apiConfig.js';
import { auth } from '../api/endpoints.js';
import { isServerReachable, pingServer, setConnectivityPatch } from './connectivity.js';
import { writeCache, writeNamedCache } from './cache.js';
import { rememberIdMapping, resolvePayloadIds } from './idMap.js';
import { addConflict, clearConflict, listPendingMutations, markOutboxStatus, refreshOutboxCounts } from './outbox.js';
import { decrementLocalStock, incrementLocalStock, replaceStockSnapshot, restoreLocalStock } from './stockLocal.js';

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

function paginatedEnvelope(items, page = 1, limit = 10) {
  const total = items.length;
  return {
    success: true,
    data: {
      items: items.slice(0, limit),
      pagination: {
        page,
        limit,
        total,
        totalPages: total > 0 ? Math.ceil(total / limit) : 0,
      },
    },
  };
}

async function writeListSnapshot(path, named, items, actifsPath) {
  const list = Array.isArray(items) ? items : [];
  await writeNamedCache(named, list);
  await writeCache(path, paginatedEnvelope(list));
  if (actifsPath) {
    await writeCache(actifsPath, {
      success: true,
      data: list.filter((item) => !item.statut || item.statut === 'ACTIF'),
    });
  }
  for (const item of list) {
    if (item?.id != null) {
      await writeCache(`${path}/${item.id}`, { success: true, data: item });
    }
  }
}

export async function pullSnapshots(modules = ['pharmacie', 'organisation', 'referentiel', 'clinique']) {
  const response = await authorizedJson(auth.syncPull, 'POST', { modules });
  const data = response?.data ?? response;
  await writeNamedCache('sync.pull', data);

  const pharmacie = data?.modules?.pharmacie;
  if (pharmacie) {
    await writeListSnapshot('/api/v1/pharmacie/medicaments', 'pharmacie.medicaments', pharmacie.medicaments, '/api/v1/pharmacie/medicaments/actifs');
    await writeListSnapshot('/api/v1/pharmacie/unites', 'pharmacie.unites', pharmacie.unites, '/api/v1/pharmacie/unites/actifs');
    await writeListSnapshot('/api/v1/pharmacie/familles', 'pharmacie.familles', pharmacie.familles, '/api/v1/pharmacie/familles/actifs');
    await writeListSnapshot('/api/v1/pharmacie/fournisseurs', 'pharmacie.fournisseurs', pharmacie.fournisseurs, '/api/v1/pharmacie/fournisseurs/actifs');
    await writeListSnapshot('/api/v1/pharmacie/receptions', 'pharmacie.receptions', pharmacie.receptions);
    await writeListSnapshot('/api/v1/pharmacie/ventes', 'pharmacie.ventes', pharmacie.ventes);
    await writeListSnapshot('/api/v1/pharmacie/demandes-service', 'pharmacie.demandes', pharmacie.demandes);
    await writeListSnapshot('/api/v1/pharmacie/lots', 'pharmacie.lots', pharmacie.lots);
    await writeListSnapshot('/api/v1/pharmacie/mouvements', 'pharmacie.mouvements', pharmacie.mouvements);
    await writeCache('/api/v1/pharmacie/lots/alertes', { success: true, data: pharmacie.alertes || [] });
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
    await writeCache('/api/v1/organisation/services', paginatedEnvelope(organisation.services));
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

  const results = [];
  for (const item of pending) {
    const payload = await resolvePayloadIds(item.payload || {});
    const response = await authorizedJson(auth.syncPush, 'POST', {
      mutations: [{
        clientId: item.clientId,
        module: item.module,
        action: item.action,
        payload,
        createdAt: item.createdAt,
      }],
    });
    const result = (response?.data?.results ?? [])[0];
    if (!result) {
      continue;
    }
    results.push(result);

    if (result.status === 'ACCEPTED') {
      if (item.optimistic?.id && result.entityId) {
        await rememberIdMapping(result.entityType, item.optimistic.id, result.entityId);
      }
      await clearConflict(result.clientId);
      await markOutboxStatus(result.clientId, 'synced', { serverId: result.entityId, result });
    } else if (result.status === 'CONFLICT') {
      if (item.status === 'pending' && (item.action?.includes('vente') || item.action === 'pharmacie.demande_service.delivrer')) {
        await restoreLocalStock(item.payload?.lignes || []);
      }
      if (item.status === 'pending' && item.action === 'pharmacie.reception.valider') {
        await decrementLocalStock(item.payload?.lignes || []);
      }
      if (item.status === 'pending' && item.action === 'pharmacie.ajustement.create') {
        const ligne = [{
          medicamentId: item.payload.medicamentId,
          quantite: item.payload.quantite,
          lotId: item.payload.lotId,
        }];
        if (item.payload?.type === 'AJUSTEMENT_PLUS') {
          await decrementLocalStock(ligne);
        } else {
          await incrementLocalStock(ligne);
        }
      }
      await markOutboxStatus(result.clientId, 'conflict', { result });
      await addConflict(result.clientId, result.message || 'Conflit de synchronisation.', item.payload);
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
