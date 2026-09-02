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

export async function fetchRolesApi() {
  const response = await callApiGet(admin.roles);
  const data = unwrapData(response);
  return Array.isArray(data) ? data : [];
}

export async function fetchRoleApi(id) {
  const response = await callApiGet(`${admin.roles}/${id}`);
  return unwrapData(response);
}

export async function createRoleApi(payload) {
  const response = await callApiPost(admin.roles, payload);
  return unwrapData(response);
}

export async function updateRoleApi(id, payload) {
  const response = await callApiPut(`${admin.roles}/${id}`, payload);
  return unwrapData(response);
}

export async function deleteRoleApi(id) {
  return callApiDelete(`${admin.roles}/${id}`);
}
