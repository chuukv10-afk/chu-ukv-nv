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

export async function fetchUnitesApi(params = {}) {
  const response = await callApiGet(`${pharmacie.unites}${buildQueryString(params)}`);
  const data = unwrapData(response);
  return {
    items: Array.isArray(data?.items) ? data.items : [],
    pagination: data?.pagination ?? { page: 1, limit: params.limit ?? 10, total: 0, totalPages: 0 },
  };
}

export async function fetchUnitesActivesApi() {
  const response = await callApiGet(`${pharmacie.unites}/actifs`);
  const data = unwrapData(response);
  return Array.isArray(data) ? data : [];
}

export async function createUniteApi(payload) {
  const response = await callApiPost(pharmacie.unites, payload);
  return unwrapData(response);
}

export async function updateUniteApi(id, payload) {
  const response = await callApiPut(`${pharmacie.unites}/${id}`, payload);
  return unwrapData(response);
}

export async function deleteUniteApi(id) {
  return callApiDelete(`${pharmacie.unites}/${id}`);
}
