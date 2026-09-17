import { rh } from '../../../api/endpoints.js';
import {
  callApiDelete,
  callApiGet,
  callApiPost,
  callApiPut,
} from '../../../api/apiClient.js';
import { exportResourceApi } from '../../../utils/exportApi.js';

function unwrapData(response) {
  return response?.data ?? response;
}

export async function fetchPaiePeriodesApi() {
  const response = await callApiGet(rh.paiePeriodes);
  const data = unwrapData(response);
  return Array.isArray(data) ? data : [];
}

export async function fetchPaiePeriodeApi(id) {
  const response = await callApiGet(`${rh.paiePeriodes}/${id}`);
  return unwrapData(response);
}

export async function openPaiePeriodeApi(payload) {
  const response = await callApiPost(rh.paiePeriodes, payload);
  return unwrapData(response);
}

export async function generatePaiePeriodeApi(id) {
  const response = await callApiPost(`${rh.paiePeriodes}/${id}/generer`, {});
  return unwrapData(response);
}

export async function validatePaiePeriodeApi(id) {
  const response = await callApiPost(`${rh.paiePeriodes}/${id}/valider`, {});
  return unwrapData(response);
}

export async function updatePaieLigneApi(periodeId, ligneId, payload) {
  const response = await callApiPut(`${rh.paiePeriodes}/${periodeId}/lignes/${ligneId}`, payload);
  return unwrapData(response);
}

export async function exportPaiePeriodeApi(id, format) {
  await exportResourceApi(`${rh.paiePeriodes}/${id}`, format);
}

export async function fetchPaieBaremesApi() {
  const response = await callApiGet(rh.paieBaremes);
  const data = unwrapData(response);
  return Array.isArray(data) ? data : [];
}

export async function upsertPaieBaremeApi(payload) {
  const response = await callApiPost(rh.paieBaremes, payload);
  return unwrapData(response);
}

export async function updatePaieBaremeApi(id, payload) {
  const response = await callApiPut(`${rh.paieBaremes}/${id}`, payload);
  return unwrapData(response);
}

export async function deletePaieBaremeApi(id) {
  return callApiDelete(`${rh.paieBaremes}/${id}`);
}

export async function exportPaieBaremeApi(format) {
  await exportResourceApi(rh.paieBaremes, format);
}

export async function fetchPaieLookupsApi() {
  const response = await callApiGet(`${rh.paie}/lookups`);
  const data = unwrapData(response);
  return {
    grades: Array.isArray(data?.grades) ? data.grades : [],
    fonctions: Array.isArray(data?.fonctions) ? data.fonctions : [],
  };
}
