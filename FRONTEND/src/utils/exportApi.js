import { downloadFile, openFileInBrowser } from '../api/apiClient.js';

export function buildExportQueryString(params = {}, format) {
  const searchParams = new URLSearchParams();

  Object.entries({ ...params, format }).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      searchParams.set(key, String(value));
    }
  });

  const query = searchParams.toString();
  return query ? `?${query}` : '';
}

export async function exportResourceApi(endpoint, format, params = {}) {
  const url = `${endpoint}/export${buildExportQueryString(params, format)}`;

  if (format === 'pdf') {
    await openFileInBrowser(url);
    return;
  }

  await downloadFile(url);
}
