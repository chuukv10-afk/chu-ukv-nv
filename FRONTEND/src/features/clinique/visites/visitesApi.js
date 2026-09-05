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

export async function fetchVisitesApi(params = {}) {
  const response = await callApiGet(`${clinique.visites}${buildQueryString(params)}`);
  const data = unwrapData(response);
  return {
    items: Array.isArray(data?.items) ? data.items : [],
    pagination: data?.pagination ?? { page: 1, limit: params.limit ?? 10, total: 0, totalPages: 0 },
  };
}

export async function fetchVisiteMetaApi() {
  const response = await callApiGet(`${clinique.visites}/meta`);
  return unwrapData(response) ?? {};
}

export async function fetchVisiteHospitalisationMetaApi(visiteId) {
  const query = visiteId ? `?visiteId=${encodeURIComponent(String(visiteId))}` : '';
  const response = await callApiGet(`${clinique.visites}/meta/hospitalisation${query}`);
  return unwrapData(response) ?? { blocs: [] };
}

export async function fetchVisiteCreateMetaApi() {
  const response = await callApiGet(`${clinique.visites}/meta/create`);
  return unwrapData(response) ?? {};
}

export async function fetchVisiteApi(id) {
  const response = await callApiGet(`${clinique.visites}/${id}`);
  return unwrapData(response);
}

export async function createVisiteApi(payload) {
  const response = await callApiPost(clinique.visites, payload);
  return unwrapData(response);
}

export async function updateVisiteApi(id, payload) {
  const response = await callApiPut(`${clinique.visites}/${id}`, payload);
  return unwrapData(response);
}

export async function deleteVisiteApi(id) {
  return callApiDelete(`${clinique.visites}/${id}`);
}
