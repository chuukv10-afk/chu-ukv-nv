import { API_BASE_URL, AUTH_TOKEN_KEY } from '../constants/apiConfig.js';
import { auth } from './endpoints.js';
import { normalizeAccessDeniedMessage } from '../utils/permissionLabels.js';
import {
  createSessionExpiredError,
  handleUnauthorizedApiResponse,
} from '../features/auth/authSession.js';
import { createOfflineReadError, readCacheWithFallback, writeCache } from '../offline/cache.js';
import { isServerReachable, setConnectivityPatch } from '../offline/connectivity.js';
import { cancelLocalMutation, enqueueMutation, findPendingByLocalId } from '../offline/outbox.js';
import { isLocalId } from '../offline/idMap.js';
import { isAuthBypassEndpoint, matchWritePolicy, shouldBypassCache } from '../offline/policies.js';
import { getStoredRefreshToken, setStoredRefreshToken, readOfflineSession } from '../offline/session.js';
import { adjustLocalLot, decrementLocalStock, incrementLocalStock, restoreLocalStock } from '../offline/stockLocal.js';

let refreshPromise = null;

function getAuthHeaders(body) {
  const headers = {};

  if (!(body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
  }

  const token = localStorage.getItem(AUTH_TOKEN_KEY);
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  return headers;
}

function buildApiError(response, payload, endpoint) {
  const requiredPermissions = Array.isArray(payload?.requiredPermissions) ? payload.requiredPermissions : [];
  const rawMessage = payload?.message || response.statusText;
  const message = response.status === 403
    ? normalizeAccessDeniedMessage(rawMessage, requiredPermissions)
    : (rawMessage || 'Une erreur est survenue.');

  const error = new Error(message);
  error.status = response.status;
  error.payload = payload;
  error.requiredPermissions = requiredPermissions;
  error.endpoint = endpoint;
  return error;
}

function isNetworkFailure(error) {
  return error instanceof TypeError || error?.offline === true || error?.message === 'Failed to fetch';
}

function parseBody(body) {
  if (!body || body instanceof FormData) return {};
  if (typeof body === 'string') {
    try {
      return JSON.parse(body);
    } catch {
      return {};
    }
  }
  return body;
}

async function tryRefreshToken() {
  const refreshToken = getStoredRefreshToken();
  if (!refreshToken) {
    return false;
  }
  if (refreshPromise) {
    return refreshPromise;
  }

  refreshPromise = (async () => {
    try {
      const response = await fetch(`${API_BASE_URL}${auth.refresh}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refreshToken }),
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload?.token) {
        return false;
      }
      localStorage.setItem(AUTH_TOKEN_KEY, payload.token);
      setStoredRefreshToken(payload.refreshToken || '');
      return true;
    } catch {
      return false;
    } finally {
      refreshPromise = null;
    }
  })();

  return refreshPromise;
}

async function networkFetch(endpoint, method, body, allowRefresh = true) {
  const options = {
    method,
    headers: getAuthHeaders(body),
  };

  if (body) {
    options.body = body instanceof FormData ? body : JSON.stringify(body);
  }

  const response = await fetch(`${API_BASE_URL}${endpoint}`, options);
  setConnectivityPatch({ online: true, serverReachable: true });

  if (response.status === 401 && allowRefresh && !isAuthBypassEndpoint(endpoint)) {
    const refreshed = await tryRefreshToken();
    if (refreshed) {
      return networkFetch(endpoint, method, body, false);
    }
    if (!isServerReachable()) {
      const error = new Error('Serveur injoignable.');
      error.offline = true;
      throw error;
    }
    if (handleUnauthorizedApiResponse(response.status, endpoint)) {
      throw createSessionExpiredError();
    }
  }

  let payload = null;
  try {
    payload = await response.json();
  } catch {
    payload = { success: response.ok, message: response.statusText };
  }

  if (!response.ok) {
    throw buildApiError(response, payload, endpoint);
  }

  return payload;
}

async function loadCachedEntity(basePath, id) {
  const cached = await readCacheWithFallback(`${basePath}/${id}`);
  return cached?.data ?? cached ?? null;
}

async function lignesForDocument(kind, id, fallback = []) {
  if (Array.isArray(fallback) && fallback.length > 0) {
    return fallback;
  }
  const bases = {
    vente: '/api/v1/pharmacie/ventes',
    demande: '/api/v1/pharmacie/demandes-service',
    reception: '/api/v1/pharmacie/receptions',
  };
  const entity = await loadCachedEntity(bases[kind], id);
  return Array.isArray(entity?.lignes) ? entity.lignes : [];
}

async function applyLocalStock(action, payload) {
  if (action === 'pharmacie.vente.complete' || action === 'pharmacie.vente.create_and_valider' || action === 'pharmacie.vente.valider') {
    const lignes = await lignesForDocument('vente', payload.id, payload.lignes);
    payload.lignes = lignes;
    await decrementLocalStock(lignes);
  }
  if (action === 'pharmacie.vente.annuler') {
    const lignes = await lignesForDocument('vente', payload.id, payload.lignes);
    payload.lignes = lignes;
    await restoreLocalStock(lignes);
  }
  if (action === 'pharmacie.demande_service.delivrer') {
    const lignes = await lignesForDocument('demande', payload.id, payload.lignes);
    payload.lignes = lignes;
    await decrementLocalStock(lignes);
  }
  if (action === 'pharmacie.reception.valider') {
    const lignes = await lignesForDocument('reception', payload.id, payload.lignes);
    payload.lignes = lignes;
    await incrementLocalStock(lignes);
  }
  if (action === 'pharmacie.ajustement.create') {
    payload.medicamentId = await adjustLocalLot(payload.lotId, payload.type, payload.quantite);
  }
}

async function enqueueWrite(method, endpoint, body) {
  const policy = matchWritePolicy(method, endpoint);
  if (!policy) {
    const error = new Error('Cette action nécessite une connexion au serveur.');
    error.offline = true;
    throw error;
  }

  const payload = parseBody(body);
  if (policy.idFromPath) {
    payload.id = policy.idFromPath(endpoint);
  }

  if (payload.id != null && isLocalId(payload.id) && (
    policy.action.endsWith('.annuler')
    || policy.action.endsWith('.delete')
  )) {
    const pending = await findPendingByLocalId(payload.id);
    if (pending) {
      const lignes = pending.payload?.lignes || [];
      const cancelled = await cancelLocalMutation(payload.id, {
        restoreLignes: policy.action.includes('vente') || policy.action.includes('delivrer') ? lignes : [],
      });
      if (cancelled) {
        return {
          success: true,
          offline: true,
          message: 'Opération locale annulée (pas encore synchronisée).',
          data: { id: payload.id, cancelled: true },
        };
      }
    }
  }

  await applyLocalStock(policy.action, payload);
  const session = await readOfflineSession();

  return {
    success: true,
    offline: true,
    message: 'Enregistré hors-ligne. La synchronisation se fera au retour du serveur.',
    data: await enqueueMutation({
      action: policy.action,
      module: policy.module,
      endpoint,
      method,
      payload,
      optimistic: buildOptimistic(policy.action, payload),
      createdByTelephone: session?.profile?.telephone || '',
    }),
  };
}

function buildOptimistic(action, payload) {
  if (action.startsWith('pharmacie.vente')) {
    const validated = action.includes('valider') || action.includes('complete');
    return {
      id: payload.id,
      numero: 'OFF-VENTE',
      statut: action.includes('annuler') ? 'ANNULEE' : (validated ? 'VALIDEE' : 'BROUILLON'),
      clientType: payload.clientType,
      clientNom: payload.clientNom,
      patientId: payload.patientId,
      modePaiement: payload.modePaiement,
      lignes: payload.lignes || [],
      montantTotal: '0',
    };
  }
  if (action.startsWith('pharmacie.demande_service')) {
    let statut = 'BROUILLON';
    let statutPaiement = 'SANS_OBJET';
    if (action.endsWith('.envoyer')) statut = 'ENVOYEE';
    if (action.endsWith('.delivrer')) {
      statut = 'DELIVREE';
      statutPaiement = 'IMPAYEE';
    }
    if (action.endsWith('.refuser')) statut = 'REFUSEE';
    if (action.endsWith('.regler')) {
      statut = 'DELIVREE';
      statutPaiement = 'PAYEE';
    }
    return {
      id: payload.id,
      numero: 'OFF-DEM',
      statut,
      statutPaiement,
      serviceId: payload.serviceId,
      motif: payload.motif,
      lignes: payload.lignes || [],
      modePaiement: payload.modePaiement,
    };
  }
  if (action.startsWith('pharmacie.reception')) {
    return {
      id: payload.id,
      numero: 'OFF-REC',
      statut: action.endsWith('.valider') ? 'VALIDEE' : 'BROUILLON',
      fournisseurId: payload.fournisseurId,
      dateReception: payload.dateReception,
      referenceExterne: payload.referenceExterne,
      lignes: payload.lignes || [],
    };
  }
  if (action.startsWith('pharmacie.medicament') || action.startsWith('pharmacie.unite') || action.startsWith('pharmacie.famille') || action.startsWith('pharmacie.fournisseur')) {
    return { ...payload, id: payload.id, statut: payload.statut || 'ACTIF' };
  }
  if (action === 'pharmacie.ajustement.create') {
    return {
      type: payload.type,
      quantite: payload.quantite,
      motif: payload.motif,
      lotId: payload.lotId,
    };
  }
  if (action === 'patient.create') {
    return {
      nom: payload.nom,
      postNom: payload.postNom,
      prenom: payload.prenom,
      fullName: [payload.nom, payload.postNom, payload.prenom].filter(Boolean).join(' '),
      status: payload.status || 'ACTIF',
    };
  }
  if (action === 'clinique.visite.create') {
    return {
      statut: 'TRIAGE',
      motif: payload.motif,
      serviceId: payload.serviceId,
      dpiId: payload.dpiId,
    };
  }
  if (action.startsWith('clinique.consultation')) {
    return {
      statut: payload.statut || 'EN_COURS',
      motif: payload.motif,
      visiteId: payload.visiteId || payload.id,
      typeConsultation: payload.typeConsultation,
    };
  }
  return payload;
}

export async function callApi(endpoint, method = 'GET', body = null) {
  const reachable = isServerReachable();

  if (method === 'GET') {
    if (reachable) {
      try {
        const payload = await networkFetch(endpoint, method, body);
        if (!shouldBypassCache(endpoint)) {
          await writeCache(endpoint, payload);
        }
        return payload;
      } catch (error) {
        if (isNetworkFailure(error) || error.status >= 500) {
          setConnectivityPatch({ serverReachable: false });
          const cached = await readCacheWithFallback(endpoint);
          if (cached) return cached;
        }
        throw error;
      }
    }

    const cached = await readCacheWithFallback(endpoint);
    if (cached) return cached;
    throw createOfflineReadError(endpoint);
  }

  if (!reachable) {
    return enqueueWrite(method, endpoint, body);
  }

  try {
    return await networkFetch(endpoint, method, body);
  } catch (error) {
    if (isNetworkFailure(error)) {
      setConnectivityPatch({ serverReachable: false });
      return enqueueWrite(method, endpoint, body);
    }
    throw error;
  }
}

async function fetchFile(endpoint) {
  if (!isServerReachable()) {
    throw new Error('L’export nécessite une connexion au serveur.');
  }

  const token = localStorage.getItem(AUTH_TOKEN_KEY);
  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  });

  if (response.status === 401) {
    const refreshed = await tryRefreshToken();
    if (refreshed) {
      return fetchFile(endpoint);
    }
    if (handleUnauthorizedApiResponse(response.status, endpoint)) {
      throw createSessionExpiredError();
    }
  }

  if (!response.ok) {
    let payload = null;
    try {
      payload = await response.json();
    } catch {
      payload = { message: response.statusText };
    }
    throw buildApiError(response, payload, endpoint);
  }

  return response;
}

export async function downloadFile(endpoint) {
  const response = await fetchFile(endpoint);
  const blob = await response.blob();
  const disposition = response.headers.get('Content-Disposition') ?? '';
  const filenameMatch = disposition.match(/filename="([^"]+)"/i);
  const filename = filenameMatch?.[1] ?? 'export';

  const objectUrl = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = objectUrl;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(objectUrl);
}

export async function openFileInBrowser(endpoint) {
  const response = await fetchFile(endpoint);
  const blob = await response.blob();
  const objectUrl = URL.createObjectURL(blob);
  const opened = window.open(objectUrl, '_blank', 'noopener,noreferrer');

  if (!opened) {
    URL.revokeObjectURL(objectUrl);
    throw new Error('Impossible d\'ouvrir le document. Autorisez les pop-ups ou téléchargez le fichier.');
  }

  window.setTimeout(() => URL.revokeObjectURL(objectUrl), 120000);
}

export const callApiGet = (endpoint) => callApi(endpoint, 'GET');
export const callApiPost = (endpoint, body) => callApi(endpoint, 'POST', body);
export const callApiPut = (endpoint, body) => callApi(endpoint, 'PUT', body);
export const callApiDelete = (endpoint) => callApi(endpoint, 'DELETE');
