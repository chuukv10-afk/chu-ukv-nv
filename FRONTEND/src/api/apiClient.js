import { API_BASE_URL, AUTH_TOKEN_KEY } from '../constants/apiConfig.js';
import { normalizeAccessDeniedMessage } from '../utils/permissionLabels.js';
import {
  createSessionExpiredError,
  handleUnauthorizedApiResponse,
} from '../features/auth/authSession.js';

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
  if (handleUnauthorizedApiResponse(response.status, endpoint)) {
    throw createSessionExpiredError();
  }

  const requiredPermissions = Array.isArray(payload?.requiredPermissions) ? payload.requiredPermissions : [];
  const rawMessage = payload?.message || response.statusText;
  const message = response.status === 403
    ? normalizeAccessDeniedMessage(rawMessage, requiredPermissions)
    : (rawMessage || 'Une erreur est survenue.');

  const error = new Error(message);
  error.status = response.status;
  error.payload = payload;
  error.requiredPermissions = requiredPermissions;
  return error;
}

export async function callApi(endpoint, method = 'GET', body = null) {
  const options = {
    method,
    headers: getAuthHeaders(body),
  };

  if (body) {
    options.body = body instanceof FormData ? body : JSON.stringify(body);
  }

  const response = await fetch(`${API_BASE_URL}${endpoint}`, options);

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

export async function downloadFile(endpoint) {
  const token = localStorage.getItem(AUTH_TOKEN_KEY);
  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  });

  if (!response.ok) {
    let payload = null;
    try {
      payload = await response.json();
    } catch {
      payload = { message: response.statusText };
    }

    throw buildApiError(response, payload, endpoint);
  }

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
  const token = localStorage.getItem(AUTH_TOKEN_KEY);
  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  });

  if (!response.ok) {
    let payload = null;
    try {
      payload = await response.json();
    } catch {
      payload = { message: response.statusText };
    }

    throw buildApiError(response, payload, endpoint);
  }

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
