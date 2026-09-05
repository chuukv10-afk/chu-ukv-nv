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

function unwrapList(response) {
  const data = unwrapData(response);
  if (Array.isArray(data)) return data;
  if (Array.isArray(data?.items)) return data.items;
  return [];
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

export async function fetchDepartementsApi(params = {}) {
  const response = await callApiGet(`${organisation.departements}${buildQueryString(params)}`);
  const data = unwrapData(response);
  return {
    items: Array.isArray(data?.items) ? data.items : unwrapList(response),
    pagination: data?.pagination ?? {
      page: 1,
      limit: params.limit ?? 10,
      total: unwrapList(response).length,
      totalPages: 1,
    },
  };
}

export async function fetchDepartementsLookupApi() {
  const response = await fetchDepartementsApi({ page: 1, limit: 100 });
  return response.items;
}

export async function fetchDepartementApi(id) {
  const response = await callApiGet(`${organisation.departements}/${id}`);
  return unwrapData(response);
}

export async function createDepartementApi(payload) {
  const response = await callApiPost(organisation.departements, payload);
  return unwrapData(response);
}

export async function updateDepartementApi(id, payload) {
  const response = await callApiPut(`${organisation.departements}/${id}`, payload);
  return unwrapData(response);
}

export async function deleteDepartementApi(id) {
  return callApiDelete(`${organisation.departements}/${id}`);
}
