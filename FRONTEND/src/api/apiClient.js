import { API_BASE_URL, AUTH_TOKEN_KEY } from '../constants/apiConfig.js';
import { auth } from './endpoints.js';
import { normalizeAccessDeniedMessage } from '../utils/permissionLabels.js';
import {
  createSessionExpiredError,
  handleUnauthorizedApiResponse,
} from '../features/auth/authSession.js';
import { createOfflineReadError, overlayPendingOnGet, readCacheWithFallback, writeCache } from '../offline/cache.js';
import { isServerReachable, setConnectivityPatch } from '../offline/connectivity.js';
import { isDesktopApp } from '../offline/desktop.js';
import { cancelLocalMutation, enqueueMutation, findPendingByLocalId } from '../offline/outbox.js';
import { isLocalId } from '../offline/idMap.js';
import { isAuthBypassEndpoint, matchWritePolicy, shouldBypassCache } from '../offline/policies.js';
import { getStoredRefreshToken, setStoredRefreshToken, readOfflineSession } from '../offline/session.js';
import { assertPharmacyWrite } from '../offline/pharmacyRules.js';
import { adjustLocalLot, decrementLocalStock, incrementLocalStock, restoreLocalStock, updateLocalLotMeta } from '../offline/stockLocal.js';
import { priceVenteLignes } from '../offline/ventePricing.js';
import { runSyncCycle } from '../offline/syncEngine.js';

let refreshPromise = null;

function isOversizedClientHeader(response) {
  if (response.status !== 400) {
    return false;
  }
  const contentType = (response.headers.get('content-type') || '').toLowerCase();
  return contentType.includes('text/html') || contentType.includes('text/plain') || contentType === '';
}

function getAuthHeaders(body, endpoint = '') {
  const headers = {};

  if (!(body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
  }

  const token = localStorage.getItem(AUTH_TOKEN_KEY);
  if (token && !isAuthBypassEndpoint(endpoint)) {
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
    headers: getAuthHeaders(body, endpoint),
  };

  if (body) {
    options.body = body instanceof FormData ? body : JSON.stringify(body);
  }

  const response = await fetch(`${API_BASE_URL}${endpoint}`, options);
  setConnectivityPatch({ online: true, serverReachable: true });

  if (response.status === 400 && isOversizedClientHeader(response)) {
    localStorage.removeItem(AUTH_TOKEN_KEY);
    if (handleUnauthorizedApiResponse(401, endpoint)) {
      throw createSessionExpiredError();
    }
  }

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
  if (action === 'pharmacie.lot.update') {
    await updateLocalLotMeta(payload.id, payload.numeroLot, payload.datePeremption);
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

  const checked = await assertPharmacyWrite(policy.action, payload);
  Object.assign(payload, checked);
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
      optimistic: await buildOptimistic(policy.action, payload),
      createdByTelephone: session?.profile?.telephone || '',
    }),
  };
}

async function buildOptimistic(action, payload) {
  if (action.startsWith('pharmacie.vente')) {
    const validated = action.includes('valider') || action.includes('complete');
    const now = new Date().toISOString();
    const priced = await priceVenteLignes(payload.lignes || []);
    return {
      id: payload.id,
      numero: 'OFF-VENTE',
      statut: action.includes('annuler') ? 'ANNULEE' : (validated ? 'VALIDEE' : 'BROUILLON'),
      clientType: payload.clientType,
      clientNom: payload.clientNom,
      patientId: payload.patientId,
      modePaiement: payload.modePaiement,
      lignes: priced.lignes,
      montantTotal: priced.montantTotal,
      dateVente: now,
      createdAt: now,
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
      createdAt: new Date().toISOString(),
    };
  }
  if (action.startsWith('pharmacie.reception')) {
    const now = new Date().toISOString();
    return {
      id: payload.id,
      numero: 'OFF-REC',
      statut: action.endsWith('.valider') ? 'VALIDEE' : 'BROUILLON',
      createdAt: now,
      dateReception: payload.dateReception || now,
      fournisseurId: payload.fournisseurId,
      referenceExterne: payload.referenceExterne,
      lignes: payload.lignes || [],
      lignesCount: (payload.lignes || []).length,
    };
  }
  if (action === 'pharmacie.lot.update') {
    return {
      id: payload.id,
      numeroLot: String(payload.numeroLot || '').trim().toUpperCase(),
      datePeremption: payload.datePeremption,
    };
  }
  if (action.startsWith('pharmacie.medicament') || action.startsWith('pharmacie.unite') || action.startsWith('pharmacie.famille') || action.startsWith('pharmacie.fournisseur')) {
    return { ...payload, id: payload.id, statut: payload.statut || 'ACTIF' };
  }
  if (action === 'pharmacie.ajustement.create') {
    const now = new Date().toISOString();
    return {
      type: payload.type,
      quantite: payload.quantite,
      motif: payload.motif,
      lotId: payload.lotId,
      medicamentId: payload.medicamentId,
      sens: payload.type === 'AJUSTEMENT_PLUS' ? 'ENTREE' : 'SORTIE',
      documentType: 'AJUSTEMENT',
      createdAt: now,
    };
  }
  if (action === 'patient.create') {
    return {
      nom: payload.nom,
      postNom: payload.postNom,
      prenom: payload.prenom,
      fullName: [payload.nom, payload.postNom, payload.prenom].filter(Boolean).join(' '),
      telephone: payload.telephone,
      sexe: payload.sexe,
      dateNaissance: payload.dateNaissance,
      status: payload.status || 'ACTIF',
      numDossier: 'OFF-PAT',
      createdAt: new Date().toISOString(),
    };
  }
  if (action === 'clinique.visite.create') {
    const now = new Date().toISOString();
    return {
      statut: payload.statut || 'TRIAGE',
      motif: payload.motif,
      serviceId: payload.serviceId,
      dpiId: payload.dpiId,
      patientId: payload.patientId,
      patientName: payload.patientName || 'Patient (hors-ligne)',
      typeEntree: payload.typeEntree,
      enterAt: now,
      createdAt: now,
      consultationCount: 0,
    };
  }
  if (action.startsWith('clinique.consultation')) {
    return {
      statut: payload.statut || 'EN_COURS',
      motif: payload.motif,
      visiteId: payload.visiteId || payload.id,
      typeConsultation: payload.typeConsultation,
      createdAt: new Date().toISOString(),
    };
  }
  return payload;
}

function isDesktopLocalEndpoint(endpoint) {
  if (isAuthBypassEndpoint(endpoint) || shouldBypassCache(endpoint)) {
    return false;
  }
  const path = String(endpoint || '').split('?')[0];
  return path !== '/api/v1/me' && path !== '/api/v1/sync/pull' && path !== '/api/v1/sync/push';
}

function isComputedLocalEndpoint(endpoint) {
  const path = String(endpoint || '').split('?')[0];
  return path === '/api/v1/pharmacie/recettes' || path === '/api/v1/pharmacie/statistiques';
}

function isEmptyLocalList(payload) {
  const items = payload?.data?.items;
  if (!Array.isArray(items)) {
    return !payload;
  }
  return items.length === 0 && Number(payload?.data?.pagination?.total || 0) === 0;
}

export async function callApi(endpoint, method = 'GET', body = null) {
  const reachable = isServerReachable();
  const desktop = isDesktopApp();

  if (desktop && method === 'GET' && isDesktopLocalEndpoint(endpoint)) {
    const local = await readCacheWithFallback(endpoint);
    const useLocal = local && (
      isComputedLocalEndpoint(endpoint)
      || !isEmptyLocalList(local)
      || !reachable
    );
    if (useLocal) {
      if (reachable && !isComputedLocalEndpoint(endpoint)) {
        networkFetch(endpoint, method, body)
          .then(async (payload) => {
            await writeCache(endpoint, payload);
          })
          .catch(() => {});
      }
      return local;
    }
    if (!reachable) {
      throw createOfflineReadError(endpoint);
    }
  }

  if (desktop && method !== 'GET' && matchWritePolicy(method, endpoint)) {
    const result = await enqueueWrite(method, endpoint, body);
    if (reachable) {
      runSyncCycle().catch(() => {});
    }
    return result;
  }

  if (method === 'GET') {
    if (reachable) {
      try {
        const payload = await networkFetch(endpoint, method, body);
        if (!shouldBypassCache(endpoint)) {
          await writeCache(endpoint, payload);
        }
        return overlayPendingOnGet(endpoint, payload);
      } catch (error) {
        if (isNetworkFailure(error) || error.status >= 500) {
          setConnectivityPatch({ serverReachable: false });
          const cached = await readCacheWithFallback(endpoint);
          if (cached) return cached;
        }
        if (error.status === 404) {
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

async function fetchFile(endpoint, method = 'GET', body = null) {
  if (!isServerReachable()) {
    throw new Error('L’export nécessite une connexion au serveur.');
  }

  const token = localStorage.getItem(AUTH_TOKEN_KEY);
  const headers = token ? { Authorization: `Bearer ${token}` } : {};
  const options = { method, headers };
  if (body && !(body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(body);
  }

  const response = await fetch(`${API_BASE_URL}${endpoint}`, options);

  if (response.status === 401) {
    const refreshed = await tryRefreshToken();
    if (refreshed) {
      return fetchFile(endpoint, method, body);
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

function filenameFromDownloadResponse(response, fallbackFilename = 'export') {
  const headerName = response.headers.get('X-Export-Filename');
  if (headerName) {
    return headerName.trim();
  }

  const disposition = response.headers.get('Content-Disposition') ?? '';
  const utf8Match = disposition.match(/filename\*=(?:UTF-8''|)([^;]+)/i);
  if (utf8Match?.[1]) {
    try {
      return decodeURIComponent(utf8Match[1].replace(/['"]/g, '').trim());
    } catch {
      // ignore malformed RFC 5987 value
    }
  }

  const quotedMatch = disposition.match(/filename="([^"]+)"/i);
  if (quotedMatch?.[1]) {
    return quotedMatch[1];
  }

  const unquotedMatch = disposition.match(/filename=([^;]+)/i);
  if (unquotedMatch?.[1]) {
    return unquotedMatch[1].trim().replace(/^["']|["']$/g, '');
  }

  return fallbackFilename;
}

export async function downloadFile(endpoint, method = 'GET', body = null, fallbackFilename = 'export') {
  const response = await fetchFile(endpoint, method, body);
  const blob = await response.blob();
  let filename = filenameFromDownloadResponse(response, fallbackFilename);

  if (!/\.[A-Za-z0-9]+$/.test(filename) && /\.[A-Za-z0-9]+$/.test(fallbackFilename)) {
    filename = `${filename}${fallbackFilename.slice(fallbackFilename.lastIndexOf('.'))}`;
  }

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
