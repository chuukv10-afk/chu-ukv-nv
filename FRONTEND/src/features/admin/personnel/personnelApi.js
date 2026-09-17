import { admin, organisation, referentiel, rh } from '../../../api/endpoints.js';
import {
  callApiDelete,
  callApiGet,
  callApiPost,
  callApiPut,
  downloadFile,
  openFileInBrowser,
} from '../../../api/apiClient.js';
import { uploadViaPreparedUrl } from '../../../utils/storageUpload.js';

function unwrapData(response) {
  return response?.data ?? response;
}

function personnelBase(base) {
  return base || admin.personnels;
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

export async function fetchPersonnelsApi(params = {}, { base } = {}) {
  const response = await callApiGet(`${personnelBase(base)}${buildQueryString(params)}`);
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

export async function fetchPersonnelApi(id, { base } = {}) {
  const response = await callApiGet(`${personnelBase(base)}/${id}`);
  return unwrapData(response);
}

export async function fetchPersonnelMetaApi({ base } = {}) {
  const response = await callApiGet(`${personnelBase(base)}/meta`);
  return unwrapData(response);
}

export async function createPersonnelApi(payload, { base } = {}) {
  const response = await callApiPost(personnelBase(base), payload);
  return unwrapData(response);
}

export async function updatePersonnelApi(id, payload, { base } = {}) {
  const response = await callApiPut(`${personnelBase(base)}/${id}`, payload);
  return unwrapData(response);
}

export async function deletePersonnelApi(id, { base } = {}) {
  return callApiDelete(`${personnelBase(base)}/${id}`);
}

export async function uploadPersonnelAvatarApi(id, file, { base } = {}) {
  const root = personnelBase(base);
  return uploadViaPreparedUrl({
    file,
    prepare: async (body) => unwrapData(await callApiPost(`${root}/${id}/avatar/prepare`, body)),
    confirm: async (body) => unwrapData(await callApiPost(`${root}/${id}/avatar/confirm`, body)),
    localUpload: async (localFile) => {
      const formData = new FormData();
      formData.append('avatar', localFile);
      return unwrapData(await callApiPost(`${root}/${id}/avatar`, formData));
    },
  });
}

export async function deletePersonnelAvatarApi(id, { base } = {}) {
  const response = await callApiDelete(`${personnelBase(base)}/${id}/avatar`);
  return unwrapData(response);
}

export async function uploadPersonnelSignatureApi(id, file, { base } = {}) {
  const root = personnelBase(base);
  return uploadViaPreparedUrl({
    file,
    prepare: async (body) => unwrapData(await callApiPost(`${root}/${id}/signature/prepare`, body)),
    confirm: async (body) => unwrapData(await callApiPost(`${root}/${id}/signature/confirm`, body)),
    localUpload: async (localFile) => {
      const formData = new FormData();
      formData.append('signature', localFile);
      return unwrapData(await callApiPost(`${root}/${id}/signature`, formData));
    },
  });
}

export async function deletePersonnelSignatureApi(id, { base } = {}) {
  const response = await callApiDelete(`${personnelBase(base)}/${id}/signature`);
  return unwrapData(response);
}

export async function exportPersonnelsApi(format, params = {}, { base } = {}) {
  const query = buildQueryString({ ...params, format });
  const endpoint = `${personnelBase(base)}/export${query}`;

  if (format === 'pdf') {
    await openFileInBrowser(endpoint);
    return;
  }

  await downloadFile(endpoint);
}

export async function fetchRhPersonnelLookupsApi() {
  const response = await callApiGet(`${personnelBase(rh.personnels)}/lookups`);
  const data = unwrapData(response);

  return {
    grades: Array.isArray(data?.grades) ? data.grades : [],
    fonctions: Array.isArray(data?.fonctions) ? data.fonctions : [],
    services: Array.isArray(data?.services) ? data.services : [],
    departements: Array.isArray(data?.departements) ? data.departements : [],
  };
}

export async function fetchPersonnelLookupsApi({ includeRoles = true, limit = 100 } = {}) {
  const lookupQuery = `?page=1&limit=${limit}`;

  const unwrapList = (response) => {
    const data = unwrapData(response);
    if (Array.isArray(data)) return data;
    if (Array.isArray(data?.items)) return data.items;
    return [];
  };

  const safeList = async (promise) => {
    try {
      return unwrapList(await promise);
    } catch {
      return [];
    }
  };

  const [grades, fonctions, services, departements, specialites, roles] = await Promise.all([
    safeList(callApiGet(`${referentiel.grades}${lookupQuery}`)),
    safeList(callApiGet(`${referentiel.fonctions}${lookupQuery}`)),
    safeList(callApiGet(`${organisation.services}${lookupQuery}`)),
    safeList(callApiGet(`${organisation.departements}${lookupQuery}`)),
    safeList(callApiGet(`${referentiel.specialites}${lookupQuery}`)),
    includeRoles ? safeList(callApiGet(`${admin.roles}`)) : Promise.resolve([]),
  ]);

  return {
    grades,
    fonctions,
    services,
    departements,
    specialites,
    roles,
  };
}
