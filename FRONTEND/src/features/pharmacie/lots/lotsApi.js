import { pharmacie } from '../../../api/endpoints.js';
import { callApiGet } from '../../../api/apiClient.js';
import { buildQueryString, paginatedResult, unwrapData } from '../shared/pharmacieApi.js';

export async function fetchLotsApi(params = {}) {
  const response = await callApiGet(`${pharmacie.lots}${buildQueryString(params)}`);
  return paginatedResult(unwrapData(response), params);
}

export async function fetchLotAlertesApi() {
  const response = await callApiGet(`${pharmacie.lots}/alertes`);
  const data = unwrapData(response);
  return {
    perimes: Array.isArray(data?.perimes) ? data.perimes : [],
    peremptionProche: Array.isArray(data?.peremptionProche) ? data.peremptionProche : [],
    stockBas: Array.isArray(data?.stockBas) ? data.stockBas : [],
  };
}

export async function fetchLotsVendablesApi(medicamentId) {
  const response = await callApiGet(`${pharmacie.lots}/vendables/${medicamentId}`);
  const data = unwrapData(response);
  return Array.isArray(data) ? data : [];
}
