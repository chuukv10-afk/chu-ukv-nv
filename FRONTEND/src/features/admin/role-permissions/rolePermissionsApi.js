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

export async function fetchRolePermissionsListApi(params = {}) {
  const response = await callApiGet(`${admin.rolePermissions}${buildQueryString(params)}`);
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

export async function fetchRolePermissionAssignmentApi(roleId) {
  const response = await callApiGet(`${admin.rolePermissions}/${roleId}`);
  return unwrapData(response);
}

export async function createRolePermissionAssignmentApi(payload) {
  const response = await callApiPost(admin.rolePermissions, payload);
  return unwrapData(response);
}

export async function updateRolePermissionAssignmentApi(roleId, permissionIds) {
  const response = await callApiPut(`${admin.rolePermissions}/${roleId}`, { permissionIds });
  return unwrapData(response);
}

export async function clearRolePermissionAssignmentApi(roleId) {
  return callApiDelete(`${admin.rolePermissions}/${roleId}`);
}

export async function removeRolePermissionApi(roleId, permissionId) {
  return callApiDelete(`${admin.rolePermissions}/${roleId}/permissions/${permissionId}`);
}
