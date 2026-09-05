import { clinique, patient } from '../../../api/endpoints.js';
import {
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

export async function fetchDemandeExamenMetaApi() {
  const response = await callApiGet(`${clinique.demandesExamen}/meta`);
  return unwrapData(response) ?? {};
}

export async function fetchDemandesExamenApi(params = {}) {
  const response = await callApiGet(`${clinique.demandesExamen}${buildQueryString(params)}`);
  return unwrapPaginated(response, params);
}

export async function fetchConsultationDemandesExamenApi(consultationId, params = {}) {
  const response = await callApiGet(
    `${clinique.consultations}/${consultationId}/demandes-examen${buildQueryString(params)}`,
  );
  return unwrapPaginated(response, params);
}

export async function createConsultationDemandeExamenApi(consultationId, payload) {
  const response = await callApiPost(`${clinique.consultations}/${consultationId}/demandes-examen`, payload);
  return unwrapData(response);
}

export async function fetchPatientDemandesExamenApi(patientId, params = {}) {
  const response = await callApiGet(`${patient.list}/${patientId}/demandes-examen${buildQueryString(params)}`);
  return unwrapPaginated(response, params);
}

export async function prendreEnChargeDemandeApi(demandeId) {
  const response = await callApiPost(`${clinique.demandesExamen}/${demandeId}/prendre-en-charge`);
  return unwrapData(response);
}

export async function saisirResultatDemandeApi(demandeId, payload) {
  const response = await callApiPost(`${clinique.demandesExamen}/${demandeId}/resultat`, payload);
  return unwrapData(response);
}

export async function validerDemandeApi(demandeId) {
  const response = await callApiPost(`${clinique.demandesExamen}/${demandeId}/valider`);
  return unwrapData(response);
}

export async function annulerDemandeApi(demandeId) {
  const response = await callApiPost(`${clinique.demandesExamen}/${demandeId}/annuler`);
  return unwrapData(response);
}

export async function createLinkedDiagnosticApi(demandeId, payload) {
  const response = await callApiPost(`${clinique.demandesExamen}/${demandeId}/diagnostics`, payload);
  return unwrapData(response);
}
