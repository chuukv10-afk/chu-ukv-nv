import { pharmacie } from '../../../api/endpoints.js';
import {
  callApiDelete,
  callApiGet,
  callApiPost,
  callApiPut,
} from '../../../api/apiClient.js';
import { buildQueryString, paginatedResult, unwrapData } from '../shared/pharmacieApi.js';

export async function fetchVentesApi(params = {}) {
  const response = await callApiGet(`${pharmacie.ventes}${buildQueryString(params)}`);
  return paginatedResult(unwrapData(response), params);
}

export async function fetchVenteApi(id) {
  const response = await callApiGet(`${pharmacie.ventes}/${id}`);
  return unwrapData(response);
}

export async function createVenteApi(payload) {
  const response = await callApiPost(pharmacie.ventes, payload);
  return unwrapData(response);
}

export async function updateVenteApi(id, payload) {
  const response = await callApiPut(`${pharmacie.ventes}/${id}`, payload);
  return unwrapData(response);
}

export async function validerVenteApi(id) {
  const response = await callApiPost(`${pharmacie.ventes}/${id}/valider`, {});
  return unwrapData(response);
}

export async function annulerVenteApi(id, motif = '') {
  const response = await callApiPost(`${pharmacie.ventes}/${id}/annuler`, { motif });
  return unwrapData(response);
}

export async function deleteVenteApi(id) {
  return callApiDelete(`${pharmacie.ventes}/${id}`);
}

export async function fetchVisitesHospitaliseesApi(search = '', serviceId) {
  const response = await callApiGet(`${pharmacie.visitesHospitalisees}${buildQueryString({ search, serviceId })}`);
  const data = unwrapData(response);
  return Array.isArray(data) ? data : [];
}
