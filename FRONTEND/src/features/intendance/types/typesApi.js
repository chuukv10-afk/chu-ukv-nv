import { intendance } from '../../../api/endpoints.js';
import { callApiDelete, callApiGet, callApiPost, callApiPut } from '../../../api/apiClient.js';
import { buildQueryString, paginatedResult, unwrapData } from '../shared/intendanceApi.js';

export async function fetchTypesApi(params = {}) {
  const response = await callApiGet(`${intendance.types}${buildQueryString(params)}`);
  return paginatedResult(unwrapData(response), params);
}

export async function fetchTypesActifsApi(familleId) {
  const response = await callApiGet(`${intendance.types}/actifs${buildQueryString({ familleId })}`);
  const data = unwrapData(response);
  return Array.isArray(data) ? data : [];
}

export async function createTypeApi(payload) {
  const response = await callApiPost(intendance.types, payload);
  return unwrapData(response);
}

export async function updateTypeApi(id, payload) {
  const response = await callApiPut(`${intendance.types}/${id}`, payload);
  return unwrapData(response);
}

export async function deleteTypeApi(id) {
  return callApiDelete(`${intendance.types}/${id}`);
}
