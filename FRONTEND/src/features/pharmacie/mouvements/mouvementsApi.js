import { pharmacie } from '../../../api/endpoints.js';
import { callApiGet, downloadFile } from '../../../api/apiClient.js';
import { exportResourceApi } from '../../../utils/exportApi.js';
import { buildQueryString, paginatedResult, unwrapData } from '../shared/pharmacieApi.js';

export async function fetchMouvementsApi(params = {}) {
  const response = await callApiGet(`${pharmacie.mouvements}${buildQueryString(params)}`);
  return paginatedResult(unwrapData(response), params);
}

export async function exportFichesStockApi(format, params = {}) {
  return exportResourceApi(`${pharmacie.mouvements}/fiches-stock`, format, params);
}

export async function downloadFichesImprimablesApi(params = {}) {
  await downloadFile(
    `${pharmacie.mouvements}/fiches-stock/imprimables${buildQueryString(params)}`,
    'GET',
    null,
    'fiches-stock-actives.zip',
  );
}
