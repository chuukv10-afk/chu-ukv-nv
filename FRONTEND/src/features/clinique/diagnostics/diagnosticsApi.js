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

export async function fetchDiagnosticMetaApi() {
  const response = await callApiGet(`${clinique.diagnostics}/meta`);
  return unwrapData(response) ?? {};
}

export async function fetchConsultationDiagnosticsApi(consultationId, params = {}) {
  const response = await callApiGet(`${clinique.consultations}/${consultationId}/diagnostics${buildQueryString(params)}`);
  return unwrapPaginated(response, params);
}

export async function createConsultationDiagnosticApi(consultationId, payload) {
  const response = await callApiPost(`${clinique.consultations}/${consultationId}/diagnostics`, payload);
  return unwrapData(response);
}

export async function deleteConsultationDiagnosticApi(consultationId, diagnosticId) {
  await callApiDelete(`${clinique.consultations}/${consultationId}/diagnostics/${diagnosticId}`);
}

export async function fetchPatientDiagnosticsApi(patientId, params = {}) {
  const response = await callApiGet(`${patient.list}/${patientId}/diagnostics${buildQueryString(params)}`);
  return unwrapPaginated(response, params);
}
