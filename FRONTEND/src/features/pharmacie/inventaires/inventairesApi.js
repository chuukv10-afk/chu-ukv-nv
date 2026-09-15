import { pharmacie } from '../../../api/endpoints.js';
import {
  callApiDelete,
  callApiGet,
  callApiPost,
} from '../../../api/apiClient.js';
import { exportResourceApi } from '../../../utils/exportApi.js';
import { buildQueryString, paginatedResult, unwrapData } from '../shared/pharmacieApi.js';

export async function fetchInventairesApi(params = {}) {
  const response = await callApiGet(`${pharmacie.inventaires}${buildQueryString(params)}`);
  return paginatedResult(unwrapData(response), params);
}

export async function fetchInventaireApi(id) {
  const response = await callApiGet(`${pharmacie.inventaires}/${id}`);
  return unwrapData(response);
}

export async function createInventaireApi(payload) {
  const response = await callApiPost(pharmacie.inventaires, payload);
  return unwrapData(response);
}

export async function compterProduitInventaireApi(inventaireId, medicamentId, payload = {}) {
  const response = await callApiPost(
    `${pharmacie.inventaires}/${inventaireId}/produits/${medicamentId}/compter`,
    payload,
  );
  return unwrapData(response);
}

export async function compterLigneInventaireApi(inventaireId, ligneId, payload = {}) {
  const response = await callApiPost(
    `${pharmacie.inventaires}/${inventaireId}/lignes/${ligneId}/compter`,
    payload,
  );
  return unwrapData(response);
}

export async function corrigerProduitInventaireApi(inventaireId, medicamentId, payload = {}) {
  const response = await callApiPost(
    `${pharmacie.inventaires}/${inventaireId}/produits/${medicamentId}/corriger`,
    payload,
  );
  return unwrapData(response);
}

export async function exportInventaireApi(id, format) {
  await exportResourceApi(`${pharmacie.inventaires}/${id}`, format);
}

export async function cloturerInventaireApi(id) {
  const response = await callApiPost(`${pharmacie.inventaires}/${id}/cloturer`, {});
  return unwrapData(response);
}

export async function deleteInventaireApi(id) {
  return callApiDelete(`${pharmacie.inventaires}/${id}`);
}
