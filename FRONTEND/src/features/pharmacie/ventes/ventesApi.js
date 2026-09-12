import { pharmacie } from '../../../api/endpoints.js';
import {
  callApiDelete,
  callApiGet,
  callApiPost,
  callApiPut,
} from '../../../api/apiClient.js';
import { enqueueMutation } from '../../../offline/outbox.js';
import { assertPharmacyWrite } from '../../../offline/pharmacyRules.js';
import { decrementLocalStock } from '../../../offline/stockLocal.js';
import { priceVenteLignes } from '../../../offline/ventePricing.js';
import { isServerReachable } from '../../../offline/connectivity.js';
import { isHistoriqueDate, toDateVenteIso } from './venteConstants.js';
import { buildQueryString, paginatedResult, unwrapData } from '../shared/pharmacieApi.js';

export async function fetchVentesApi(params = {}) {
  const response = await callApiGet(`${pharmacie.ventes}${buildQueryString(params)}`);
  return paginatedResult(unwrapData(response), params);
}

export async function fetchVenteApi(id) {
  const response = await callApiGet(`${pharmacie.ventes}/${id}`);
  return unwrapData(response);
}

export async function createVenteApi(payload) {
  const response = await callApiPost(pharmacie.ventes, payload);
  return unwrapData(response);
}

export async function updateVenteApi(id, payload) {
  const response = await callApiPut(`${pharmacie.ventes}/${id}`, payload);
  return unwrapData(response);
}

export async function validerVenteApi(id) {
  const response = await callApiPost(`${pharmacie.ventes}/${id}/valider`, {});
  return unwrapData(response);
}

export async function annulerVenteApi(id, motif = '') {
  const response = await callApiPost(`${pharmacie.ventes}/${id}/annuler`, { motif });
  return unwrapData(response);
}

export async function deleteVenteApi(id) {
  return callApiDelete(`${pharmacie.ventes}/${id}`);
}

export async function completeVenteOfflineApi(payload) {
  const checked = await assertPharmacyWrite('pharmacie.vente.complete', payload);
  if (checked.dateVente) checked.dateVente = String(checked.dateVente).slice(0, 10);
  await decrementLocalStock(checked.lignes || []);
  const now = new Date().toISOString();
  const dateVente = toDateVenteIso(checked.dateVente, now);
  const priced = await priceVenteLignes(checked.lignes || []);
  return enqueueMutation({
    action: 'pharmacie.vente.complete',
    module: 'pharmacie',
    endpoint: pharmacie.ventes,
    method: 'POST',
    payload: checked,
    optimistic: {
      numero: 'OFF-VENTE',
      statut: 'VALIDEE',
      clientType: checked.clientType,
      clientNom: checked.clientNom,
      patientId: checked.patientId,
      visiteId: checked.visiteId,
      modePaiement: checked.modePaiement,
      lignes: priced.lignes,
      montantTotal: priced.montantTotal,
      dateVente,
      historique: isHistoriqueDate(dateVente),
      createdAt: now,
    },
  });
}

export function canUseOfflineCaisse() {
  return !isServerReachable() || Boolean(typeof window !== 'undefined' && window.electronAPI?.isDesktop);
}

export async function fetchVisitesHospitaliseesApi(search = '', serviceId) {
  const response = await callApiGet(`${pharmacie.visitesHospitalisees}${buildQueryString({ search, serviceId })}`);
  const data = unwrapData(response);
  return Array.isArray(data) ? data : [];
}
