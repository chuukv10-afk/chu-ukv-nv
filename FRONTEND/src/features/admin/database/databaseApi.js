import { admin } from '../../../api/endpoints.js';
import { callApiGet, callApiPost, downloadFile } from '../../../api/apiClient.js';

function unwrapData(response) {
  return response?.data ?? response;
}

export async function fetchDatabaseOverviewApi() {
  const response = await callApiGet(admin.database);
  return unwrapData(response);
}

export async function truncateDatabaseTablesApi(tables) {
  const response = await callApiPost(`${admin.database}/truncate`, { tables });
  return unwrapData(response);
}

export async function exportDatabaseTablesApi(tables, format) {
  await downloadFile(`${admin.database}/export`, 'POST', { tables, format });
}

export async function importDatabaseSqlApi(file) {
  const body = new FormData();
  body.append('file', file);
  const response = await callApiPost(`${admin.database}/import`, body);
  return unwrapData(response);
}
