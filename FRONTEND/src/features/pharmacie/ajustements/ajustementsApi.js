import { pharmacie } from '../../../api/endpoints.js';
import { callApiPost } from '../../../api/apiClient.js';
import { unwrapData } from '../shared/pharmacieApi.js';

export async function createAjustementApi(payload) {
  const response = await callApiPost(pharmacie.ajustements, payload);
  return unwrapData(response);
}
