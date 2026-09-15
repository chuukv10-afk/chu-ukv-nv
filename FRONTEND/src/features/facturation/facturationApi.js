import { facturation } from '../../api/endpoints.js';
import {
  callApiDelete,
  callApiGet,
  callApiPost,
  callApiPut,
} from '../../api/apiClient.js';

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

function paginatedResult(data, params = {}) {
  return {
    items: Array.isArray(data?.items) ? data.items : [],
    pagination: data?.pagination ?? {
      page: 1,
      limit: params.limit ?? 10,
      total: 0,
      totalPages: 0,
    },
  };
}

export async function fetchStructuresApi(params = {}) {
  const response = await callApiGet(`${facturation.structures}${buildQueryString(params)}`);
  return paginatedResult(unwrapData(response), params);
}

export async function fetchStructuresActivesApi(type) {
  const response = await callApiGet(`${facturation.structures}/actifs${buildQueryString({ type })}`);
  const data = unwrapData(response);
  return Array.isArray(data) ? data : [];
}

export async function createStructureApi(payload) {
  const response = await callApiPost(facturation.structures, payload);
  return unwrapData(response);
}

export async function updateStructureApi(id, payload) {
  const response = await callApiPut(`${facturation.structures}/${id}`, payload);
  return unwrapData(response);
}

export async function deleteStructureApi(id) {
  return callApiDelete(`${facturation.structures}/${id}`);
}

export async function fetchActesFinanciersApi(params = {}) {
  const response = await callApiGet(`${facturation.actes}${buildQueryString(params)}`);
  return paginatedResult(unwrapData(response), params);
}

export async function fetchActesFinanciersMetaApi() {
  const response = await callApiGet(`${facturation.actes}/meta`);
  return unwrapData(response) ?? {};
}

export async function importGrilleTarifaireApi(file) {
  const body = new FormData();
  body.append('file', file);
  const response = await callApiPost(`${facturation.actes}/import`, body);
  return unwrapData(response);
}

export async function createActeFinancierApi(payload) {
  const response = await callApiPost(facturation.actes, payload);
  return unwrapData(response);
}

export async function updateActeFinancierApi(id, payload) {
  const response = await callApiPut(`${facturation.actes}/${id}`, payload);
  return unwrapData(response);
}

export async function deleteActeFinancierApi(id) {
  return callApiDelete(`${facturation.actes}/${id}`);
}
