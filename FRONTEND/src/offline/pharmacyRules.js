import { readCache, readNamedCache } from './cache.js';

const VENTE_MODES = ['ESPECES', 'MOBILE'];
const VENTE_CLIENTS = ['PATIENT', 'PASSANT'];
const AJUSTEMENT_TYPES = ['AJUSTEMENT_PLUS', 'AJUSTEMENT_MOINS', 'SORTIE_PERTE', 'SORTIE_PEREMPTION'];
const STATUTS = ['ACTIF', 'INACTIF'];
const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

function fail(message) {
  const error = new Error(message);
  error.offline = true;
  error.pharmacyRule = true;
  return error;
}

export function isPresentId(value) {
  if (value == null || value === '') return false;
  const raw = String(value).trim();
  return raw !== '' && raw !== '0' && raw !== 'NaN' && raw !== 'null' && raw !== 'undefined';
}

function text(value) {
  return String(value ?? '').trim();
}

function assertMaxLen(value, max, message) {
  if (value != null && String(value).length > max) {
    throw fail(message);
  }
}

function assertIsoDate(value, blankMessage, invalidMessage) {
  const raw = text(value);
  if (!raw) throw fail(blankMessage);
  if (!ISO_DATE.test(raw) || Number.isNaN(Date.parse(`${raw}T00:00:00`))) {
    throw fail(invalidMessage);
  }
  return raw;
}

function assertPrix(value, message) {
  const raw = text(value).replace(',', '.');
  if (!raw) throw fail(message);
  const amount = Number(raw);
  if (!Number.isFinite(amount) || amount < 0) {
    throw fail(message);
  }
}

function assertPositiveQty(value, message = 'La quantité doit être supérieure à 0.') {
  const qty = Number(value);
  if (!Number.isFinite(qty) || qty <= 0) {
    throw fail(message);
  }
}

function assertStatut(value) {
  if (!value) return;
  if (!STATUTS.includes(String(value).toUpperCase())) {
    throw fail('Statut invalide.');
  }
}

function namedItems(raw) {
  if (Array.isArray(raw)) return raw;
  if (Array.isArray(raw?.items)) return raw.items;
  if (Array.isArray(raw?.data)) return raw.data;
  if (Array.isArray(raw?.data?.items)) return raw.data.items;
  return [];
}

async function findNamedItem(named, id) {
  if (!isPresentId(id)) return null;
  const items = namedItems(await readNamedCache(named));
  const needle = String(id);
  return items.find((item) => String(item?.id) === needle) ?? null;
}

async function loadEntity(basePath, id) {
  if (!isPresentId(id)) return null;
  const cached = await readCache(`${basePath}/${id}`);
  return cached?.data ?? cached ?? null;
}

function isSameCalendarDay(iso) {
  if (!iso) return true;
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return true;
  const now = new Date();
  return date.getFullYear() === now.getFullYear()
    && date.getMonth() === now.getMonth()
    && date.getDate() === now.getDate();
}

function normalizeDocumentLignes(lignes = []) {
  return (Array.isArray(lignes) ? lignes : []).map((ligne) => ({
    ...ligne,
    medicamentId: ligne?.medicamentId ?? ligne?.medicament?.id,
    lotId: ligne?.lotId ?? ligne?.lot?.id,
    numeroLot: ligne?.numeroLot ?? ligne?.lot?.numeroLot,
  }));
}

function assertLignesMedicaments(lignes, { requireLotNumero = false } = {}) {
  const normalized = normalizeDocumentLignes(lignes);
  if (normalized.length < 1) {
    throw fail('Ajoutez au moins une ligne.');
  }
  for (const ligne of normalized) {
    if (!isPresentId(ligne?.medicamentId)) {
      throw fail('Le médicament est obligatoire.');
    }
    assertPositiveQty(ligne?.quantite);
    if (requireLotNumero) {
      const numeroLot = text(ligne?.numeroLot);
      if (!numeroLot) throw fail('Le n° de lot est obligatoire.');
      assertMaxLen(numeroLot, 40, 'Le n° de lot est trop long.');
    }
  }
  return normalized;
}

export function assertVentePayload(payload = {}) {
  const clientType = text(payload.clientType).toUpperCase();
  const mode = text(payload.modePaiement).toUpperCase();
  if (!mode || !VENTE_MODES.includes(mode)) {
    throw fail('Le mode de paiement est obligatoire.');
  }
  if (clientType && !VENTE_CLIENTS.includes(clientType) && !isPresentId(payload.visiteId)) {
    throw fail('Type de client invalide.');
  }

  if (isPresentId(payload.visiteId)) {
    // Origine hospitalisée : le nom passant n'est pas exigé.
  } else if (clientType === 'PATIENT') {
    if (!text(payload.patientId)) {
      throw fail('Le patient est obligatoire pour une vente patient.');
    }
  } else {
    const nom = text(payload.clientNom);
    if (!nom) {
      throw fail('Le nom du passant est obligatoire.');
    }
    assertMaxLen(nom, 150, 'Le nom du passant est trop long.');
  }

  payload.lignes = assertLignesMedicaments(payload.lignes || []);
}

export function assertReceptionPayload(payload = {}) {
  if (!isPresentId(payload.fournisseurId)) {
    throw fail('Le fournisseur est obligatoire.');
  }
  const dateReception = assertIsoDate(
    payload.dateReception,
    'La date de réception est obligatoire.',
    'Date de réception invalide (format AAAA-MM-JJ).',
  );
  assertMaxLen(payload.referenceExterne, 80, 'La référence externe est trop longue.');
  payload.lignes = assertLignesMedicaments(payload.lignes || [], { requireLotNumero: true });
  for (const ligne of payload.lignes || []) {
    const datePeremption = assertIsoDate(
      ligne.datePeremption,
      'La date de péremption est obligatoire.',
      'Date de péremption invalide (format AAAA-MM-JJ).',
    );
    if (datePeremption <= dateReception) {
      throw fail('La péremption doit être postérieure à la date de réception.');
    }
    assertPrix(ligne.prixAchatUnitaire, 'Le prix d\'achat est obligatoire.');
  }
}

export function assertDemandePayload(payload = {}) {
  if (!isPresentId(payload.serviceId)) {
    throw fail('Le service est obligatoire.');
  }
  assertMaxLen(payload.motif, 255, 'Le motif est trop long.');
  payload.lignes = assertLignesMedicaments(payload.lignes || []);
}

export function assertAjustementPayload(payload = {}) {
  if (!isPresentId(payload.lotId)) {
    throw fail('Le lot est obligatoire.');
  }
  const type = text(payload.type).toUpperCase();
  if (!AJUSTEMENT_TYPES.includes(type)) {
    throw fail('Type d\'ajustement invalide.');
  }
  assertPositiveQty(payload.quantite);
  const motif = text(payload.motif);
  if (!motif) throw fail('Le motif est obligatoire.');
  assertMaxLen(motif, 255, 'Le motif est trop long.');
}

export function assertLotPayload(payload = {}) {
  const numeroLot = text(payload.numeroLot);
  if (!numeroLot) throw fail('Le numéro de lot est obligatoire.');
  assertMaxLen(numeroLot, 40, 'Le numéro de lot est trop long.');
  assertIsoDate(
    payload.datePeremption,
    'La date de péremption est obligatoire.',
    'Date de péremption invalide (format AAAA-MM-JJ).',
  );
}

function assertCodeLibelle(payload, { codeRequired, codeMax, libelleMax }) {
  if (codeRequired) {
    const code = text(payload.code);
    if (!code) throw fail('Le code est obligatoire.');
    assertMaxLen(code, codeMax, 'Le code est trop long.');
  }
  const libelle = text(payload.libelle);
  if (!libelle) throw fail('Le libellé est obligatoire.');
  assertMaxLen(libelle, libelleMax, 'Le libellé est trop long.');
  assertStatut(payload.statut);
}

export function assertMedicamentPayload(payload = {}, { isCreate = false } = {}) {
  assertCodeLibelle(payload, { codeRequired: isCreate, codeMax: 20, libelleMax: 150 });
  assertMaxLen(payload.dci, 150, 'La DCI est trop longue.');
  assertMaxLen(payload.forme, 80, 'La forme est trop longue.');
  assertMaxLen(payload.dosage, 50, 'Le dosage est trop long.');
  if (!isPresentId(payload.uniteId)) throw fail('L\'unité est obligatoire.');
  if (!isPresentId(payload.familleId)) throw fail('La famille est obligatoire.');
  assertPrix(payload.prixVente, 'Le prix de vente est obligatoire.');
  const seuil = Number(payload.seuilAlerte ?? 0);
  if (!Number.isFinite(seuil) || seuil < 0) {
    throw fail('Le seuil d\'alerte est invalide.');
  }
}

async function assertMedicamentsActifs(lignes = []) {
  for (const ligne of lignes) {
    const medicament = await findNamedItem('pharmacie.medicaments', ligne.medicamentId);
    if (medicament && medicament.statut && medicament.statut !== 'ACTIF') {
      throw fail('Ce médicament est inactif.');
    }
  }
}

async function assertFournisseurActif(fournisseurId) {
  const fournisseur = await findNamedItem('pharmacie.fournisseurs', fournisseurId);
  if (fournisseur && fournisseur.statut && fournisseur.statut !== 'ACTIF') {
    throw fail('Ce fournisseur est inactif.');
  }
}

async function assertVisiteHospitalisee(visiteId) {
  if (!isPresentId(visiteId)) return;
  const cached = await readCache('/api/v1/pharmacie/visites-hospitalisees');
  const items = namedItems(cached);
  if (items.length === 0) return;
  const visite = items.find((item) => String(item?.id) === String(visiteId));
  if (!visite) {
    throw fail('La visite doit être hospitalisée.');
  }
  if (visite.statut && visite.statut !== 'HOSPITALISE') {
    throw fail('La visite doit être hospitalisée.');
  }
}

async function hydrateFromCache(action, payload) {
  const next = { ...payload };
  if (action.startsWith('pharmacie.vente.') && isPresentId(next.id) && (!next.lignes || next.lignes.length === 0 || !next.clientType)) {
    const cached = await loadEntity('/api/v1/pharmacie/ventes', next.id);
    if (cached) {
      if (!next.clientType) next.clientType = cached.clientType;
      if (next.clientNom == null) next.clientNom = cached.clientNom;
      if (next.patientId == null) next.patientId = cached.patientId ?? cached.patient?.id;
      if (next.visiteId == null) next.visiteId = cached.visiteId ?? cached.visite?.id;
      if (!next.modePaiement) next.modePaiement = cached.modePaiement;
      if (!Array.isArray(next.lignes) || next.lignes.length === 0) {
        next.lignes = cached.lignes || [];
      }
      next.lignes = normalizeDocumentLignes(next.lignes);
      if (!next.dateVente) next.dateVente = cached.dateVente || cached.createdAt;
    }
  }
  if (action.startsWith('pharmacie.reception.') && isPresentId(next.id) && (!next.lignes || next.lignes.length === 0)) {
    const cached = await loadEntity('/api/v1/pharmacie/receptions', next.id);
    if (cached) {
      if (!isPresentId(next.fournisseurId)) next.fournisseurId = cached.fournisseurId;
      if (!next.dateReception) next.dateReception = cached.dateReception;
      if (next.referenceExterne == null) next.referenceExterne = cached.referenceExterne;
      next.lignes = normalizeDocumentLignes(cached.lignes || []);
    }
  }
  if (action.startsWith('pharmacie.demande_service.') && isPresentId(next.id) && (!next.lignes || next.lignes.length === 0)) {
    const cached = await loadEntity('/api/v1/pharmacie/demandes-service', next.id);
    if (cached) {
      if (!isPresentId(next.serviceId)) next.serviceId = cached.serviceId;
      if (next.visiteId == null) next.visiteId = cached.visiteId;
      next.lignes = normalizeDocumentLignes(cached.lignes || []);
    }
  }
  return next;
}

export async function assertPharmacyWrite(action, payload = {}) {
  if (!String(action || '').startsWith('pharmacie.')) {
    return payload;
  }

  const next = await hydrateFromCache(action, payload);

  if (
    action === 'pharmacie.vente.create'
    || action === 'pharmacie.vente.update'
    || action === 'pharmacie.vente.complete'
    || action === 'pharmacie.vente.create_and_valider'
    || action === 'pharmacie.vente.valider'
  ) {
    assertVentePayload(next);
    await assertMedicamentsActifs(next.lignes);
    await assertVisiteHospitalisee(next.visiteId);
  }

  if (action === 'pharmacie.vente.annuler') {
    if (!isSameCalendarDay(next.dateVente) && !text(next.motif)) {
      throw fail('Le motif est obligatoire pour une annulation hors délai.');
    }
    assertMaxLen(next.motif, 255, 'Le motif est trop long.');
  }

  if (action === 'pharmacie.reception.create' || action === 'pharmacie.reception.update' || action === 'pharmacie.reception.valider') {
    assertReceptionPayload(next);
    await assertMedicamentsActifs(next.lignes);
    await assertFournisseurActif(next.fournisseurId);
  }

  if (action === 'pharmacie.demande_service.create' || action === 'pharmacie.demande_service.update' || action === 'pharmacie.demande_service.envoyer') {
    assertDemandePayload(next);
    await assertMedicamentsActifs(next.lignes);
  }

  if (action === 'pharmacie.demande_service.delivrer') {
    if (!Array.isArray(next.lignes) || next.lignes.length < 1) {
      throw fail('Impossible d\'envoyer une demande sans ligne.');
    }
    await assertMedicamentsActifs(next.lignes);
  }

  if (action === 'pharmacie.demande_service.refuser') {
    if (!text(next.motif)) throw fail('Le motif de refus est obligatoire.');
    assertMaxLen(next.motif, 255, 'Le motif de refus est trop long.');
  }

  if (action === 'pharmacie.demande_service.regler') {
    const mode = text(next.modePaiement).toUpperCase();
    if (!VENTE_MODES.includes(mode)) {
      throw fail('Le mode de paiement est obligatoire.');
    }
  }

  if (action === 'pharmacie.ajustement.create') {
    assertAjustementPayload(next);
  }

  if (action === 'pharmacie.lot.update') {
    assertLotPayload(next);
  }

  if (action === 'pharmacie.medicament.create') {
    assertMedicamentPayload(next, { isCreate: true });
  }
  if (action === 'pharmacie.medicament.update') {
    assertMedicamentPayload(next, { isCreate: false });
  }

  if (action === 'pharmacie.unite.create') {
    assertCodeLibelle(next, { codeRequired: true, codeMax: 15, libelleMax: 100 });
  }
  if (action === 'pharmacie.unite.update') {
    assertCodeLibelle(next, { codeRequired: false, codeMax: 15, libelleMax: 100 });
  }

  if (action === 'pharmacie.famille.create') {
    assertCodeLibelle(next, { codeRequired: true, codeMax: 15, libelleMax: 100 });
  }
  if (action === 'pharmacie.famille.update') {
    assertCodeLibelle(next, { codeRequired: false, codeMax: 15, libelleMax: 100 });
  }

  if (action === 'pharmacie.fournisseur.create') {
    assertCodeLibelle(next, { codeRequired: true, codeMax: 15, libelleMax: 150 });
    assertMaxLen(next.telephone, 20, 'Le téléphone est trop long.');
    assertMaxLen(next.adresse, 200, 'L\'adresse est trop longue.');
  }
  if (action === 'pharmacie.fournisseur.update') {
    assertCodeLibelle(next, { codeRequired: false, codeMax: 15, libelleMax: 150 });
    assertMaxLen(next.telephone, 20, 'Le téléphone est trop long.');
    assertMaxLen(next.adresse, 200, 'L\'adresse est trop longue.');
  }

  return next;
}
