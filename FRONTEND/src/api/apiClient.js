import { API_BASE_URL, AUTH_TOKEN_KEY } from '../constants/apiConfig.js';

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
    const error = new Error(payload?.message || 'Une erreur est survenue.');
    error.status = response.status;
    error.payload = payload;
    throw error;
  }

  return payload;
}

export const callApiGet = (endpoint) => callApi(endpoint, 'GET');
export const callApiPost = (endpoint, body) => callApi(endpoint, 'POST', body);
export const callApiPut = (endpoint, body) => callApi(endpoint, 'PUT', body);
export const callApiDelete = (endpoint) => callApi(endpoint, 'DELETE');
