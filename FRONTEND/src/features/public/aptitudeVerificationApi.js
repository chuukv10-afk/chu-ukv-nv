import { API_BASE_URL } from '../../constants/apiConfig.js';
import { publicApi } from '../../api/endpoints.js';

function unwrapData(payload) {
  return payload?.data ?? payload;
}

export async function verifyAptitudeCertificateApi(numero) {
  const trimmed = String(numero || '').trim();
  const query = new URLSearchParams({ numero: trimmed });
  const response = await fetch(`${API_BASE_URL}${publicApi.aptitudeCertificates}?${query.toString()}`, {
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
    const error = new Error(payload?.message || 'Impossible de vérifier ce certificat.');
    error.status = response.status;
    throw error;
  }

  return unwrapData(payload);
}
