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
import { isHistoriqueDate, toDateVenteIso } from '../ventes/venteConstants.js';
import { buildQueryString, paginatedResult, unwrapData } from '../shared/pharmacieApi.js';

export async function fetchDemandesServiceApi(params = {}) {
  const response = await callApiGet(`${pharmacie.demandesService}${buildQueryString(params)}`);
  return paginatedResult(unwrapData(response), params);
}

export async function fetchDemandeServiceApi(id) {
  const response = await callApiGet(`${pharmacie.demandesService}/${id}`);
  return unwrapData(response);
}

export async function createDemandeServiceApi(payload) {
  const response = await callApiPost(pharmacie.demandesService, payload);
  return unwrapData(response);
}

export async function createAndDelivrerDemandeServiceApi(payload) {
  const response = await callApiPost(`${pharmacie.demandesService}/create-and-delivrer`, payload);
  return unwrapData(response);
}

export async function completeDemandeOfflineApi(payload) {
  const checked = await assertPharmacyWrite('pharmacie.demande_service.create_and_delivrer', payload);
  if (checked.dateLivraison) checked.dateLivraison = String(checked.dateLivraison).slice(0, 10);
  await decrementLocalStock(checked.lignes || []);
  const now = new Date().toISOString();
  const dateLivraison = toDateVenteIso(checked.dateLivraison, now);
  const montantTotal = (checked.lignes || []).reduce((sum, ligne) => {
    const prix = Number(ligne.prixUnitaire || 0);
    const qty = Number(ligne.quantite || 0);
    return sum + prix * qty;
  }, 0);
  return enqueueMutation({
    action: 'pharmacie.demande_service.create_and_delivrer',
    module: 'pharmacie',
    endpoint: pharmacie.demandesService,
    method: 'POST',
    payload: checked,
    optimistic: {
      numero: 'OFF-DEM',
      statut: 'DELIVREE',
      statutPaiement: 'IMPAYEE',
      serviceId: checked.serviceId,
      visiteId: checked.visiteId,
      motif: checked.motif,
      lignes: checked.lignes || [],
      montantTotal,
      montantPaye: 0,
      montantReste: montantTotal,
      dateLivraison,
      delivreeAt: dateLivraison,
      historique: isHistoriqueDate(dateLivraison),
      createdAt: now,
    },
  });
}

export async function updateDemandeServiceApi(id, payload) {
  const response = await callApiPut(`${pharmacie.demandesService}/${id}`, payload);
  return unwrapData(response);
}

export async function envoyerDemandeServiceApi(id) {
  const response = await callApiPost(`${pharmacie.demandesService}/${id}/envoyer`, {});
  return unwrapData(response);
}

export async function delivrerDemandeServiceApi(id) {
  const response = await callApiPost(`${pharmacie.demandesService}/${id}/delivrer`, {});
  return unwrapData(response);
}

export async function refuserDemandeServiceApi(id, motif) {
  const response = await callApiPost(`${pharmacie.demandesService}/${id}/refuser`, { motif });
  return unwrapData(response);
}

export async function reglerDemandeServiceApi(id, modePaiement, montant) {
  const payload = { modePaiement };
  if (montant != null && montant !== '') {
    payload.montant = Number(montant);
  }
  const response = await callApiPost(`${pharmacie.demandesService}/${id}/regler`, payload);
  return unwrapData(response);
}

export async function deleteDemandeServiceApi(id) {
  return callApiDelete(`${pharmacie.demandesService}/${id}`);
}

export async function fetchServicesActifsPharmacieApi() {
  const response = await callApiGet(pharmacie.servicesActifs);
  const data = unwrapData(response);
  return Array.isArray(data) ? data : [];
}
