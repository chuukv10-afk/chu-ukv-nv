import { organisation } from '../../../api/endpoints.js';
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

export async function fetchChambresApi(params = {}) {
  const response = await callApiGet(`${organisation.chambres}${buildQueryString(params)}`);
  const data = unwrapData(response);
  return {
    items: Array.isArray(data?.items) ? data.items : [],
    pagination: data?.pagination ?? { page: 1, limit: params.limit ?? 10, total: 0, totalPages: 0 },
  };
}

export async function fetchChambresLookupApi() {
  const response = await fetchChambresApi({ page: 1, limit: 100 });
  return response.items;
}

export async function createChambreApi(payload) {
  const response = await callApiPost(organisation.chambres, payload);
  return unwrapData(response);
}

export async function updateChambreApi(id, payload) {
  const response = await callApiPut(`${organisation.chambres}/${id}`, payload);
  return unwrapData(response);
}

export async function deleteChambreApi(id) {
  return callApiDelete(`${organisation.chambres}/${id}`);
}
