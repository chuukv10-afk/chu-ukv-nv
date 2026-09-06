import { pharmacie } from '../../../api/endpoints.js';
import { callApiGet } from '../../../api/apiClient.js';
import { buildQueryString, unwrapData } from '../shared/pharmacieApi.js';

const EMPTY_TOTAUX = {
  encaisseVentes: '0',
  encaisseServices: '0',
  encaisseTotal: '0',
  creancesOuvertes: '0',
  creancesCount: 0,
};

export async function fetchRecettesApi(params = {}) {
  const response = await callApiGet(`${pharmacie.recettes}${buildQueryString(params)}`);
  const data = unwrapData(response);
  return {
    items: Array.isArray(data?.items) ? data.items : [],
    pagination: data?.pagination ?? { page: 1, limit: params.limit ?? 15, total: 0, totalPages: 0 },
    totaux: { ...EMPTY_TOTAUX, ...(data?.totaux ?? {}) },
  };
}
