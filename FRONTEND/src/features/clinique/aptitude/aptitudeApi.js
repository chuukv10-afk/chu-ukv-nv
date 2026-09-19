import { clinique } from '../../../api/endpoints.js';
import {
  callApiDelete,
  callApiGet,
  callApiPost,
  callApiPut,
  downloadFile,
  openFileInBrowser,
} from '../../../api/apiClient.js';
import { buildExportQueryString, exportResourceApi } from '../../../utils/exportApi.js';

function unwrapData(response) {
  return response?.data ?? response;
}

function buildQueryString(params = {}) {
  const searchParams = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      searchParams.set(key, String(value));
    }
  });
  const query = searchParams.toString();
  return query ? `?${query}` : '';
}

export async function fetchAptitudesApi(params = {}) {
  const response = await callApiGet(`${clinique.aptitudes}${buildQueryString(params)}`);
  const data = unwrapData(response);
  return {
    items: Array.isArray(data?.items) ? data.items : [],
    pagination: data?.pagination ?? { page: 1, limit: params.limit ?? 10, total: 0, totalPages: 0 },
  };
}

export async function fetchAptitudeApi(id) {
  const response = await callApiGet(`${clinique.aptitudes}/${id}`);
  return unwrapData(response);
}

export async function fetchAptitudeServicesApi() {
  const response = await callApiGet(`${clinique.aptitudes}/lookups/services`);
  const data = unwrapData(response);
  return Array.isArray(data) ? data : [];
}

export async function fetchAptitudeFilieresApi(organisationId) {
  const response = await callApiGet(`${clinique.aptitudes}/lookups/filieres${buildQueryString({
    organisationId: organisationId || undefined,
  })}`);
  const data = unwrapData(response);
  return Array.isArray(data) ? data : [];
}

export async function fetchAptitudeOrganisationsApi() {
  const response = await callApiGet(`${clinique.aptitudes}/lookups/organisations`);
  const data = unwrapData(response);
  return Array.isArray(data) ? data : [];
}

export async function fetchAptitudeStatsApi(params = {}) {
  const response = await callApiGet(`${clinique.aptitudes}/stats${buildQueryString(params)}`);
  const data = unwrapData(response);
  return {
    filters: data?.filters ?? {},
    totals: {
      total: data?.totals?.total ?? 0,
      apte: data?.totals?.apte ?? 0,
      inapte: data?.totals?.inapte ?? 0,
      brouillon: data?.totals?.brouillon ?? 0,
      signe: data?.totals?.signe ?? 0,
      annule: data?.totals?.annule ?? 0,
    },
    byFiliere: Array.isArray(data?.byFiliere) ? data.byFiliere : [],
  };
}

export async function exportAptitudeStatsApi(format, params = {}) {
  const url = `${clinique.aptitudes}/stats/export${buildExportQueryString(params, format)}`;
  if (format === 'pdf') {
    await openFileInBrowser(url);
    return;
  }
  await downloadFile(url);
}

export async function createAptitudeApi(payload) {
  const response = await callApiPost(clinique.aptitudes, payload);
  return unwrapData(response);
}

export async function updateAptitudeApi(id, payload) {
  const response = await callApiPut(`${clinique.aptitudes}/${id}`, payload);
  return unwrapData(response);
}

export async function deleteAptitudeApi(id) {
  return callApiDelete(`${clinique.aptitudes}/${id}`);
}

export async function bulkDeleteAptitudesApi(ids = []) {
  const response = await callApiPost(`${clinique.aptitudes}/supprimer`, { ids });
  return unwrapData(response);
}

export async function signerAptitudeApi(id) {
  const response = await callApiPost(`${clinique.aptitudes}/${id}/signer`);
  return unwrapData(response);
}

export async function annulerAptitudeApi(id) {
  const response = await callApiPost(`${clinique.aptitudes}/${id}/annuler`);
  return unwrapData(response);
}

export async function openAptitudePdfApi(id) {
  await openFileInBrowser(`${clinique.aptitudes}/${id}/pdf`);
}

export async function openAptitudeBatchPdfApi(ids = []) {
  const query = buildQueryString({ ids: ids.join(',') });
  await openFileInBrowser(`${clinique.aptitudes}/pdf-lot${query}`);
}

export async function openAptitudeJetonsPdfApi(annee) {
  await openFileInBrowser(`${clinique.aptitudes}/jetons${buildQueryString({ annee })}`);
}

export async function markAptitudesPrintedApi(ids = []) {
  const response = await callApiPost(`${clinique.aptitudes}/marquer-imprime`, { ids });
  return unwrapData(response);
}

export async function exportAptitudesApi(format, params = {}) {
  return exportResourceApi(clinique.aptitudes, format, params);
}

export async function downloadAptitudeImportTemplateApi() {
  await downloadFile(`${clinique.aptitudes}/import-modele`, 'GET', null, 'modele-import-etudiants-ukv.xlsx');
}

export async function previewAptitudeImportApi(file) {
  const body = new FormData();
  body.append('file', file);
  const response = await callApiPost(`${clinique.aptitudes}/import-preview`, body);
  return unwrapData(response);
}

export async function importAptitudeEtudiantsApi({
  file, organisationId, filiereId, annee, categorieTarifaire,
}) {
  const body = new FormData();
  body.append('file', file);
  body.append('organisationId', String(organisationId));
  if (filiereId) body.append('filiereId', String(filiereId));
  body.append('annee', String(annee));
  body.append('categorieTarifaire', categorieTarifaire || 'A');
  const response = await callApiPost(`${clinique.aptitudes}/import`, body);
  return unwrapData(response);
}
