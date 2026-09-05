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

export async function fetchExamensApi(params = {}) {
  const response = await callApiGet(`${clinique.examens}${buildQueryString(params)}`);
  const data = unwrapData(response);
  return {
    items: Array.isArray(data?.items) ? data.items : [],
    pagination: data?.pagination ?? { page: 1, limit: params.limit ?? 10, total: 0, totalPages: 0 },
  };
}

export async function createExamenApi(payload) {
  const response = await callApiPost(clinique.examens, payload);
  return unwrapData(response);
}

export async function updateExamenApi(id, payload) {
  const response = await callApiPut(`${clinique.examens}/${id}`, payload);
  return unwrapData(response);
}

export async function deleteExamenApi(id) {
  return callApiDelete(`${clinique.examens}/${id}`);
}
