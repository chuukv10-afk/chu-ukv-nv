import { pharmacie } from '../../../api/endpoints.js';
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

export async function fetchFamillesApi(params = {}) {
  const response = await callApiGet(`${pharmacie.familles}${buildQueryString(params)}`);
  const data = unwrapData(response);
  return {
    items: Array.isArray(data?.items) ? data.items : [],
    pagination: data?.pagination ?? { page: 1, limit: params.limit ?? 10, total: 0, totalPages: 0 },
  };
}

export async function fetchFamillesActivesApi() {
  const response = await callApiGet(`${pharmacie.familles}/actifs`);
  const data = unwrapData(response);
  return Array.isArray(data) ? data : [];
}

export async function createFamilleApi(payload) {
  const response = await callApiPost(pharmacie.familles, payload);
  return unwrapData(response);
}

export async function updateFamilleApi(id, payload) {
  const response = await callApiPut(`${pharmacie.familles}/${id}`, payload);
  return unwrapData(response);
}

export async function deleteFamilleApi(id) {
  return callApiDelete(`${pharmacie.familles}/${id}`);
}
