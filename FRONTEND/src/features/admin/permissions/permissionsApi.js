import { admin } from '../../../api/endpoints.js';
import {
  callApiDelete,
  callApiGet,
  callApiPost,
  callApiPut,
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

export async function fetchPermissionsApi(params = {}) {
  const response = await callApiGet(`${admin.permissions}${buildQueryString(params)}`);
  const data = unwrapData(response);

  if (Array.isArray(data)) {
    return {
      items: data,
      pagination: {
        page: 1,
        limit: data.length,
        total: data.length,
        totalPages: 1,
      },
    };
  }

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

export async function fetchPermissionApi(id) {
  const response = await callApiGet(`${admin.permissions}/${id}`);
  return unwrapData(response);
}

export async function createPermissionApi(payload) {
  const response = await callApiPost(admin.permissions, payload);
  return unwrapData(response);
}

export async function updatePermissionApi(id, payload) {
  const response = await callApiPut(`${admin.permissions}/${id}`, payload);
  return unwrapData(response);
}

export async function deletePermissionApi(id) {
  return callApiDelete(`${admin.permissions}/${id}`);
}
