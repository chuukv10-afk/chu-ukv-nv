import { pharmacie } from '../../../api/endpoints.js';
import {
  callApiDelete,
  callApiGet,
  callApiPost,
  callApiPut,
} from '../../../api/apiClient.js';
import { buildQueryString, paginatedResult, unwrapData } from '../shared/pharmacieApi.js';

export async function fetchFournisseursApi(params = {}) {
  const response = await callApiGet(`${pharmacie.fournisseurs}${buildQueryString(params)}`);
  return paginatedResult(unwrapData(response), params);
}

export async function fetchFournisseursActifsApi() {
  const response = await callApiGet(`${pharmacie.fournisseurs}/actifs`);
  const data = unwrapData(response);
  return Array.isArray(data) ? data : [];
}

export async function createFournisseurApi(payload) {
  const response = await callApiPost(pharmacie.fournisseurs, payload);
  return unwrapData(response);
}

export async function updateFournisseurApi(id, payload) {
  const response = await callApiPut(`${pharmacie.fournisseurs}/${id}`, payload);
  return unwrapData(response);
}

export async function deleteFournisseurApi(id) {
  return callApiDelete(`${pharmacie.fournisseurs}/${id}`);
}
