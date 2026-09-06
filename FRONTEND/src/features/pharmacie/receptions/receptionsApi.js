import { pharmacie } from '../../../api/endpoints.js';
import {
  callApiDelete,
  callApiGet,
  callApiPost,
  callApiPut,
} from '../../../api/apiClient.js';
import { buildQueryString, paginatedResult, unwrapData } from '../shared/pharmacieApi.js';

export async function fetchReceptionsApi(params = {}) {
  const response = await callApiGet(`${pharmacie.receptions}${buildQueryString(params)}`);
  return paginatedResult(unwrapData(response), params);
}

export async function fetchReceptionApi(id) {
  const response = await callApiGet(`${pharmacie.receptions}/${id}`);
  return unwrapData(response);
}

export async function createReceptionApi(payload) {
  const response = await callApiPost(pharmacie.receptions, payload);
  return unwrapData(response);
}

export async function updateReceptionApi(id, payload) {
  const response = await callApiPut(`${pharmacie.receptions}/${id}`, payload);
  return unwrapData(response);
}

export async function validerReceptionApi(id) {
  const response = await callApiPost(`${pharmacie.receptions}/${id}/valider`, {});
  return unwrapData(response);
}

export async function deleteReceptionApi(id) {
  return callApiDelete(`${pharmacie.receptions}/${id}`);
}
