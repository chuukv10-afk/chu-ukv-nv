import { clinique } from '../../../api/endpoints.js';
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

export async function fetchConsultationsApi(params = {}) {
  const response = await callApiGet(`${clinique.consultations}${buildQueryString(params)}`);
  const data = unwrapData(response);
  return {
    items: Array.isArray(data?.items) ? data.items : [],
    pagination: data?.pagination ?? { page: 1, limit: params.limit ?? 10, total: 0, totalPages: 0 },
  };
}

export async function fetchConsultationMetaApi() {
  const response = await callApiGet(`${clinique.consultations}/meta`);
  return unwrapData(response) ?? {};
}

export async function fetchConsultationApi(id) {
  const response = await callApiGet(`${clinique.consultations}/${id}`);
  return unwrapData(response);
}

export async function createConsultationApi(payload) {
  const response = await callApiPost(clinique.consultations, payload);
  return unwrapData(response);
}

export async function updateConsultationApi(id, payload) {
  const response = await callApiPut(`${clinique.consultations}/${id}`, payload);
  return unwrapData(response);
}

export async function deleteConsultationApi(id) {
  await callApiDelete(`${clinique.consultations}/${id}`);
}

export async function closeConsultationApi(id, payload) {
  const response = await callApiPost(`${clinique.consultations}/${id}/close`, payload);
  return unwrapData(response);
}

export async function fetchConsultationVitalsApi(id) {
  const response = await callApiGet(`${clinique.consultations}/${id}/vitals`);
  return unwrapData(response);
}

export async function addConsultationVitalApi(id, payload) {
  const response = await callApiPost(`${clinique.consultations}/${id}/vitals`, payload);
  return unwrapData(response);
}

export async function addConsultationVitalsPriseApi(id, payload) {
  const response = await callApiPost(`${clinique.consultations}/${id}/vitals/prise`, payload);
  return unwrapData(response);
}
