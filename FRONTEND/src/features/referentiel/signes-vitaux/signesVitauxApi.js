import { referentiel } from '../../../api/endpoints.js';
import {
  callApiDelete,
  callApiGet,
  callApiPost,
  callApiPut,
} from '../../../api/apiClient.js';

function unwrapData(response) {
  return response?.data ?? response;
}

function buildQueryString(params = {}) {
  const searchParams = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      searchParams.set(key, String(value));
    }
  });
  const query = searchParams.toString();
  return query ? `?${query}` : '';
}

export async function fetchSignesVitauxApi(params = {}) {
  const response = await callApiGet(`${referentiel.signesVitaux}${buildQueryString(params)}`);
  const data = unwrapData(response);
  return {
    items: Array.isArray(data?.items) ? data.items : [],
    pagination: data?.pagination ?? { page: 1, limit: params.limit ?? 10, total: 0, totalPages: 0 },
  };
}

export async function createSigneVitalApi(payload) {
  const response = await callApiPost(referentiel.signesVitaux, payload);
  return unwrapData(response);
}

export async function updateSigneVitalApi(id, payload) {
  const response = await callApiPut(`${referentiel.signesVitaux}/${id}`, payload);
  return unwrapData(response);
}

export async function deleteSigneVitalApi(id) {
  return callApiDelete(`${referentiel.signesVitaux}/${id}`);
}
