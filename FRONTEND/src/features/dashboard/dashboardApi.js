import { dashboard } from '../../api/endpoints.js';
import { callApiGet } from '../../api/apiClient.js';

export async function fetchDashboardStatsApi() {
  const response = await callApiGet(dashboard.stats);
  return response?.data ?? response;
}
