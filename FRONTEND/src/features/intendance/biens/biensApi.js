import { intendance } from '../../../api/endpoints.js';
import { callApiDelete, callApiGet, callApiPost, callApiPut, openFileInBrowser } from '../../../api/apiClient.js';
import { exportResourceApi } from '../../../utils/exportApi.js';
import { buildQueryString, paginatedResult, unwrapData } from '../shared/intendanceApi.js';

export async function fetchBiensApi(params = {}) {
  const response = await callApiGet(`${intendance.biens}${buildQueryString(params)}`);
  return paginatedResult(unwrapData(response), params);
}

export async function fetchEffectifsApi(params = {}) {
  const response = await callApiGet(`${intendance.biens}/effectifs${buildQueryString(params)}`);
  return unwrapData(response);
}

export async function fetchServicesLookupApi() {
  const response = await callApiGet(`${intendance.biens}/lookups/services`);
  const data = unwrapData(response);
  return Array.isArray(data) ? data : [];
}

export async function proposerCodeApi(params) {
  const response = await callApiGet(`${intendance.biens}/prochain-code${buildQueryString(params)}`);
  return unwrapData(response);
}

export async function createBienApi(payload) {
  const response = await callApiPost(intendance.biens, payload);
  return unwrapData(response);
}

export async function createGroupeBiensApi(payload) {
  const response = await callApiPost(`${intendance.biens}/groupe`, payload);
  return unwrapData(response);
}

export async function updateBienApi(id, payload) {
  const response = await callApiPut(`${intendance.biens}/${id}`, payload);
  return unwrapData(response);
}

export async function transfererBienApi(id, payload) {
  const response = await callApiPost(`${intendance.biens}/${id}/transferer`, payload);
  return unwrapData(response);
}

export async function reformerBienApi(id, payload) {
  const response = await callApiPost(`${intendance.biens}/${id}/reformer`, payload);
  return unwrapData(response);
}

export async function deleteBienApi(id) {
  return callApiDelete(`${intendance.biens}/${id}`);
}

export async function fetchHistoriqueBienApi(id) {
  const response = await callApiGet(`${intendance.biens}/${id}/historique`);
  const data = unwrapData(response);
  return Array.isArray(data) ? data : [];
}

export async function fetchBienByCodeApi(code) {
  const response = await callApiGet(`${intendance.biens}/par-code/${encodeURIComponent(String(code).trim())}`);
  return unwrapData(response);
}

export async function exportBiensApi(format, params = {}) {
  return exportResourceApi(intendance.biens, format, params);
}

export async function openEtiquettesApi(ids = []) {
  return openFileInBrowser(`${intendance.biens}/etiquettes${buildQueryString({ ids: ids.join(',') })}`);
}
