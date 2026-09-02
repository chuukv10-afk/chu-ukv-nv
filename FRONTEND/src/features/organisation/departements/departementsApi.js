import { organisation } from '../../../api/endpoints.js';
import { callApiGet } from '../../../api/apiClient.js';

export async function fetchDepartementsApi() {
  return callApiGet(organisation.departements);
}
