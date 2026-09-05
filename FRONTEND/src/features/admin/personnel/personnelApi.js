import { admin, organisation, referentiel } from '../../../api/endpoints.js';
import {
  callApiDelete,
  callApiGet,
  callApiPost,
  callApiPut,
  downloadFile,
  openFileInBrowser,
} from '../../../api/apiClient.js';

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

export async function fetchPersonnelsApi(params = {}) {
  const response = await callApiGet(`${admin.personnels}${buildQueryString(params)}`);
  const data = unwrapData(response);

  return {
    items: Array.isArray(data?.items) ? data.items : [],
    pagination: data?.pagination ?? {
      page: 1,
      limit: params.limit ?? 10,
      total: 0,
      totalPages: 0,
    },
  };
}

export async function fetchPersonnelApi(id) {
  const response = await callApiGet(`${admin.personnels}/${id}`);
  return unwrapData(response);
}

export async function fetchPersonnelMetaApi() {
  const response = await callApiGet(`${admin.personnels}/meta`);
  return unwrapData(response);
}

export async function createPersonnelApi(payload) {
  const response = await callApiPost(admin.personnels, payload);
  return unwrapData(response);
}

export async function updatePersonnelApi(id, payload) {
  const response = await callApiPut(`${admin.personnels}/${id}`, payload);
  return unwrapData(response);
}

export async function deletePersonnelApi(id) {
  return callApiDelete(`${admin.personnels}/${id}`);
}

export async function uploadPersonnelAvatarApi(id, file) {
  const formData = new FormData();
  formData.append('avatar', file);
  const response = await callApiPost(`${admin.personnels}/${id}/avatar`, formData);
  return unwrapData(response);
}

export async function deletePersonnelAvatarApi(id) {
  const response = await callApiDelete(`${admin.personnels}/${id}/avatar`);
  return unwrapData(response);
}

export async function exportPersonnelsApi(format, params = {}) {
  const query = buildQueryString({ ...params, format });
  const endpoint = `${admin.personnels}/export${query}`;

  if (format === 'pdf') {
    await openFileInBrowser(endpoint);
    return;
  }

  await downloadFile(endpoint);
}

export async function fetchPersonnelLookupsApi() {
  const lookupQuery = '?page=1&limit=100';
  const [gradesRes, servicesRes, departementsRes, specialitesRes, rolesRes] = await Promise.all([
    callApiGet(`${referentiel.grades}${lookupQuery}`),
    callApiGet(`${organisation.services}${lookupQuery}`),
    callApiGet(`${organisation.departements}${lookupQuery}`),
    callApiGet(`${referentiel.specialites}${lookupQuery}`),
    callApiGet(`${admin.roles}`),
  ]);

  const unwrapList = (response) => {
    const data = unwrapData(response);
    if (Array.isArray(data)) return data;
    if (Array.isArray(data?.items)) return data.items;
    return [];
  };

  return {
    grades: unwrapList(gradesRes),
    services: unwrapList(servicesRes),
    departements: unwrapList(departementsRes),
    specialites: unwrapList(specialitesRes),
    roles: unwrapList(rolesRes),
  };
}
