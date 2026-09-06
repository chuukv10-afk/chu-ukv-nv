import { pharmacie } from '../../../api/endpoints.js';
import { callApiGet } from '../../../api/apiClient.js';
import { buildQueryString, unwrapData } from '../shared/pharmacieApi.js';

export async function fetchStatistiquesApi(params = {}) {
  const response = await callApiGet(`${pharmacie.statistiques}${buildQueryString(params)}`);
  return unwrapData(response);
}
