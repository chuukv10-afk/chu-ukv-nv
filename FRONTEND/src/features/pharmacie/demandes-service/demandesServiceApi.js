import { pharmacie } from '../../../api/endpoints.js';
import {
  callApiDelete,
  callApiGet,
  callApiPost,
  callApiPut,
} from '../../../api/apiClient.js';
import { buildQueryString, paginatedResult, unwrapData } from '../shared/pharmacieApi.js';

export async function fetchDemandesServiceApi(params = {}) {
  const response = await callApiGet(`${pharmacie.demandesService}${buildQueryString(params)}`);
  return paginatedResult(unwrapData(response), params);
}

export async function fetchDemandeServiceApi(id) {
  const response = await callApiGet(`${pharmacie.demandesService}/${id}`);
  return unwrapData(response);
}

export async function createDemandeServiceApi(payload) {
  const response = await callApiPost(pharmacie.demandesService, payload);
  return unwrapData(response);
}

export async function updateDemandeServiceApi(id, payload) {
  const response = await callApiPut(`${pharmacie.demandesService}/${id}`, payload);
  return unwrapData(response);
}

export async function envoyerDemandeServiceApi(id) {
  const response = await callApiPost(`${pharmacie.demandesService}/${id}/envoyer`, {});
  return unwrapData(response);
}

export async function delivrerDemandeServiceApi(id) {
  const response = await callApiPost(`${pharmacie.demandesService}/${id}/delivrer`, {});
  return unwrapData(response);
}

export async function refuserDemandeServiceApi(id, motif) {
  const response = await callApiPost(`${pharmacie.demandesService}/${id}/refuser`, { motif });
  return unwrapData(response);
}

export async function reglerDemandeServiceApi(id, modePaiement) {
  const response = await callApiPost(`${pharmacie.demandesService}/${id}/regler`, { modePaiement });
  return unwrapData(response);
}

export async function deleteDemandeServiceApi(id) {
  return callApiDelete(`${pharmacie.demandesService}/${id}`);
}

export async function fetchServicesActifsPharmacieApi() {
  const response = await callApiGet(pharmacie.servicesActifs);
  const data = unwrapData(response);
  return Array.isArray(data) ? data : [];
}
