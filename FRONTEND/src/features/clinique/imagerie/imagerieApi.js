import { clinique } from '../../../api/endpoints.js';
import {
  callApiDelete,
  callApiGet,
  callApiPost,
  openFileInBrowser,
} from '../../../api/apiClient.js';
import { uploadViaPreparedUrl } from '../../../utils/storageUpload.js';

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

export async function fetchEtudesImagerieApi(params = {}) {
  const response = await callApiGet(`${clinique.imagerie}${buildQueryString(params)}`);
  const data = unwrapData(response);
  return {
    items: Array.isArray(data?.items) ? data.items : [],
    pagination: data?.pagination ?? { page: 1, limit: params.limit ?? 10, total: 0, totalPages: 0 },
  };
}

export async function fetchEtudeImagerieApi(id) {
  const response = await callApiGet(`${clinique.imagerie}/${id}`);
  return unwrapData(response);
}

export async function createEtudeImagerieApi(payload) {
  const response = await callApiPost(clinique.imagerie, payload);
  return unwrapData(response);
}

export async function interpretEtudeImagerieApi(id, payload) {
  const response = await callApiPost(`${clinique.imagerie}/${id}/interpreter`, payload);
  return unwrapData(response);
}

export async function validerEtudeImagerieApi(id) {
  const response = await callApiPost(`${clinique.imagerie}/${id}/valider`, {});
  return unwrapData(response);
}

export async function annulerEtudeImagerieApi(id) {
  const response = await callApiPost(`${clinique.imagerie}/${id}/annuler`, {});
  return unwrapData(response);
}

export async function deleteEtudeImagerieApi(id) {
  return callApiDelete(`${clinique.imagerie}/${id}`);
}

export async function fetchMedecinsImagerieApi() {
  const response = await callApiGet(`${clinique.imagerie}/medecins`);
  const data = unwrapData(response);
  return Array.isArray(data) ? data : [];
}

export async function openEtudeImageriePdfApi(id) {
  await openFileInBrowser(`${clinique.imagerie}/${id}/pdf`);
}

export async function openEtudeImagerieBonPdfApi(id) {
  await openFileInBrowser(`${clinique.imagerie}/${id}/bon-pdf`);
}

function resolveImageMime(file) {
  if (file.type) return file.type;
  const name = String(file.name || '').toLowerCase();
  if (name.endsWith('.pdf')) return 'application/pdf';
  if (name.endsWith('.png')) return 'image/png';
  if (name.endsWith('.webp')) return 'image/webp';
  if (name.endsWith('.jpg') || name.endsWith('.jpeg')) return 'image/jpeg';
  return file.type;
}

export async function uploadEtudeImageApi(id, file) {
  const mimeType = resolveImageMime(file);
  return uploadViaPreparedUrl({
    file,
    mimeType,
    prepare: async (body) => unwrapData(await callApiPost(`${clinique.imagerie}/${id}/images/prepare`, body)),
    confirm: async (body) => unwrapData(await callApiPost(`${clinique.imagerie}/${id}/images/confirm`, {
      ...body,
      originalName: file.name,
      mimeType,
      size: file.size,
    })),
    localUpload: async (localFile) => {
      const formData = new FormData();
      formData.append('image', localFile);
      return unwrapData(await callApiPost(`${clinique.imagerie}/${id}/images`, formData));
    },
  });
}

export async function deleteEtudeImageApi(etudeId, imageId) {
  const response = await callApiDelete(`${clinique.imagerie}/${etudeId}/images/${imageId}`);
  return unwrapData(response);
}
