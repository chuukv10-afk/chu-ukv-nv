import { clinique, patient } from '../../../api/endpoints.js';
import {
  callApiDelete,
  callApiGet,
  callApiPost,
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

function unwrapPaginated(response, params = {}) {
  const data = unwrapData(response);
  return {
    items: Array.isArray(data?.items) ? data.items : [],
    pagination: data?.pagination ?? {
      page: params.page ?? 1,
      limit: params.limit ?? 50,
      total: 0,
      totalPages: 0,
    },
  };
}

export async function fetchPatientAntecedentsApi(patientId, params = {}) {
  const response = await callApiGet(`${patient.list}/${patientId}/antecedents${buildQueryString(params)}`);
  return unwrapPaginated(response, params);
}

export async function createPatientAntecedentApi(patientId, payload) {
  const response = await callApiPost(`${patient.list}/${patientId}/antecedents`, payload);
  return unwrapData(response);
}

export async function deletePatientAntecedentApi(patientId, antecedentId) {
  await callApiDelete(`${patient.list}/${patientId}/antecedents/${antecedentId}`);
}

export async function fetchConsultationAntecedentsApi(consultationId, params = {}) {
  const response = await callApiGet(`${clinique.consultations}/${consultationId}/antecedents${buildQueryString(params)}`);
  return unwrapPaginated(response, params);
}

export async function createConsultationAntecedentApi(consultationId, payload) {
  const response = await callApiPost(`${clinique.consultations}/${consultationId}/antecedents`, payload);
  return unwrapData(response);
}

export async function deleteConsultationAntecedentApi(consultationId, antecedentId) {
  await callApiDelete(`${clinique.consultations}/${consultationId}/antecedents/${antecedentId}`);
}
