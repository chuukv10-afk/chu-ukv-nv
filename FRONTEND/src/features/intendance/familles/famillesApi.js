import { intendance } from '../../../api/endpoints.js';
import { callApiDelete, callApiGet, callApiPost, callApiPut } from '../../../api/apiClient.js';
import { buildQueryString, paginatedResult, unwrapData } from '../shared/intendanceApi.js';

export async function fetchFamillesApi(params = {}) {
  const response = await callApiGet(`${intendance.familles}${buildQueryString(params)}`);
  return paginatedResult(unwrapData(response), params);
}

export async function fetchFamillesActivesApi() {
  const response = await callApiGet(`${intendance.familles}/actifs`);
  const data = unwrapData(response);
  return Array.isArray(data) ? data : [];
}

export async function createFamilleApi(payload) {
  const response = await callApiPost(intendance.familles, payload);
  return unwrapData(response);
}

export async function updateFamilleApi(id, payload) {
  const response = await callApiPut(`${intendance.familles}/${id}`, payload);
  return unwrapData(response);
}

export async function deleteFamilleApi(id) {
  return callApiDelete(`${intendance.familles}/${id}`);
}
