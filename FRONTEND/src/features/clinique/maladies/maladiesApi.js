import { clinique } from '../../../api/endpoints.js';
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

export async function fetchMaladiesApi(params = {}) {
  const response = await callApiGet(`${clinique.maladies}${buildQueryString(params)}`);
  const data = unwrapData(response);
  return {
    items: Array.isArray(data?.items) ? data.items : [],
    pagination: data?.pagination ?? { page: 1, limit: params.limit ?? 10, total: 0, totalPages: 0 },
  };
}

export async function fetchMaladieChapitresApi() {
  const response = await callApiGet(`${clinique.maladies}/chapitres`);
  const data = unwrapData(response);
  return Array.isArray(data) ? data : [];
}

export async function createMaladieApi(payload) {
  const response = await callApiPost(clinique.maladies, payload);
  return unwrapData(response);
}

export async function updateMaladieApi(id, payload) {
  const response = await callApiPut(`${clinique.maladies}/${id}`, payload);
  return unwrapData(response);
}

export async function deleteMaladieApi(id) {
  return callApiDelete(`${clinique.maladies}/${id}`);
}

export async function bulkDeleteMaladiesApi(ids) {
  const response = await callApiPost(`${clinique.maladies}/bulk-delete`, { ids });
  return unwrapData(response);
}
