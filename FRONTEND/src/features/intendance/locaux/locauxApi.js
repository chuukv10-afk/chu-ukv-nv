import { intendance } from '../../../api/endpoints.js';
import { callApiDelete, callApiGet, callApiPost, callApiPut } from '../../../api/apiClient.js';
import { buildQueryString, paginatedResult, unwrapData } from '../shared/intendanceApi.js';

export async function fetchLocauxApi(params = {}) {
  const response = await callApiGet(`${intendance.locaux}${buildQueryString(params)}`);
  return paginatedResult(unwrapData(response), params);
}

export async function fetchLocauxActifsApi(serviceId) {
  const response = await callApiGet(`${intendance.locaux}/actifs${buildQueryString({ serviceId })}`);
  const data = unwrapData(response);
  return Array.isArray(data) ? data : [];
}

export async function createLocalApi(payload) {
  const response = await callApiPost(intendance.locaux, payload);
  return unwrapData(response);
}

export async function updateLocalApi(id, payload) {
  const response = await callApiPut(`${intendance.locaux}/${id}`, payload);
  return unwrapData(response);
}

export async function deleteLocalApi(id) {
  return callApiDelete(`${intendance.locaux}/${id}`);
}
