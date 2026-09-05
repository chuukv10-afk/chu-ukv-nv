import { patient } from '../../../api/endpoints.js';
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

export async function fetchPatientsApi(params = {}) {
  const response = await callApiGet(`${patient.list}${buildQueryString(params)}`);
  const data = unwrapData(response);
  return {
    items: Array.isArray(data?.items) ? data.items : [],
    pagination: data?.pagination ?? { page: 1, limit: params.limit ?? 10, total: 0, totalPages: 0 },
  };
}

export async function fetchPatientMetaApi() {
  const response = await callApiGet(`${patient.list}/meta`);
  return unwrapData(response) ?? {};
}

export async function fetchPatientApi(id) {
  const response = await callApiGet(`${patient.list}/${id}`);
  return unwrapData(response);
}

export async function fetchPatientDpiApi(id) {
  const response = await callApiGet(`${patient.list}/${id}/dpi`);
  return unwrapData(response);
}

export async function createPatientApi(payload) {
  const response = await callApiPost(patient.list, payload);
  return unwrapData(response);
}

export async function updatePatientApi(id, payload) {
  const response = await callApiPut(`${patient.list}/${id}`, payload);
  return unwrapData(response);
}

export async function updatePatientDpiApi(id, payload) {
  const response = await callApiPut(`${patient.list}/${id}/dpi`, payload);
  return unwrapData(response);
}

export async function deletePatientApi(id) {
  return callApiDelete(`${patient.list}/${id}`);
}
