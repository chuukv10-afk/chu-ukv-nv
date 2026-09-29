import { API_BASE_URL } from '../../constants/apiConfig.js';
import { publicApi } from '../../api/endpoints.js';

function unwrapData(payload) {
  return payload?.data ?? payload;
}

export async function verifyBienApi(code) {
  const trimmed = String(code || '').trim();
  const query = new URLSearchParams({ code: trimmed });
  const response = await fetch(`${API_BASE_URL}${publicApi.biens}?${query.toString()}`, {
    method: 'GET',
    headers: { Accept: 'application/json' },
  });

  let payload = null;
  try {
    payload = await response.json();
  } catch {
    payload = { success: false, message: response.statusText };
  }

  if (!response.ok) {
    const error = new Error(payload?.message || 'Impossible de vérifier cet équipement.');
    error.status = response.status;
    throw error;
  }

  return unwrapData(payload);
}
