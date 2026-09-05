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

export async function fetchServicesApi(params = {}) {
  const response = await callApiGet(`${organisation.services}${buildQueryString(params)}`);
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

export async function fetchServiceApi(id) {
  const response = await callApiGet(`${organisation.services}/${id}`);
  return unwrapData(response);
}

export async function createServiceApi(payload) {
  const response = await callApiPost(organisation.services, payload);
  return unwrapData(response);
}

export async function updateServiceApi(id, payload) {
  const response = await callApiPut(`${organisation.services}/${id}`, payload);
  return unwrapData(response);
}

export async function deleteServiceApi(id) {
  return callApiDelete(`${organisation.services}/${id}`);
}
