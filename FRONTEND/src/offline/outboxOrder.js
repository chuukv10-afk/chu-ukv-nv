import { makeLocalLotId } from './stockLocal.js';
import { isLocalId, resolveServerId } from './idMap.js';

const ACTION_RANK = [
  [/^pharmacie\.(unite|famille|fournisseur)\.create$/, 10],
  [/^pharmacie\.medicament\.create$/, 20],
  [/^patient\.create$/, 20],
  [/^clinique\.visite\.create$/, 22],
  [/^clinique\.consultation\.(create|update)$/, 24],
  [/^pharmacie\.(unite|famille|fournisseur|medicament)\.update$/, 25],
  [/^pharmacie\.reception\.create$/, 30],
  [/^pharmacie\.reception\.update$/, 32],
  [/^pharmacie\.reception\.delete$/, 33],
  [/^pharmacie\.reception\.valider$/, 40],
  [/^pharmacie\.lot\.update$/, 45],
  [/^pharmacie\.ajustement\.create$/, 50],
  [/^pharmacie\.vente\.create$/, 60],
  [/^pharmacie\.vente\.complete$/, 60],
  [/^pharmacie\.vente\.create_and_valider$/, 60],
  [/^pharmacie\.vente\.update$/, 60],
  [/^pharmacie\.vente\.valider$/, 61],
  [/^pharmacie\.demande_service\.create$/, 60],
  [/^pharmacie\.demande_service\.update$/, 60],
  [/^pharmacie\.demande_service\.envoyer$/, 61],
  [/^pharmacie\.demande_service\.delivrer$/, 62],
  [/^pharmacie\.demande_service\.regler$/, 63],
  [/^pharmacie\.vente\.annuler$/, 70],
  [/\.delete$/, 90],
];

export function rankForAction(action = '') {
  const match = ACTION_RANK.find(([pattern]) => pattern.test(action));
  return match ? match[1] : 55;
}

export function sortMutationsForPush(mutations = []) {
  return [...mutations].sort((left, right) => {
    const rankDiff = rankForAction(left.action) - rankForAction(right.action);
    if (rankDiff !== 0) return rankDiff;
    return String(left.createdAt || '').localeCompare(String(right.createdAt || ''));
  });
}

const ID_KEYS = [
  'id',
  'medicamentId',
  'lotId',
  'uniteId',
  'familleId',
  'fournisseurId',
  'serviceId',
  'visiteId',
  'receptionId',
  'patientId',
  'dpiId',
];

export function collectLocalIds(value, acc = []) {
  if (value == null || value === '') return acc;
  if (Array.isArray(value)) {
    value.forEach((item) => collectLocalIds(item, acc));
    return acc;
  }
  if (typeof value !== 'object') {
    if (isLocalId(value) && !acc.includes(String(value))) acc.push(String(value));
    return acc;
  }
  for (const key of ID_KEYS) {
    if (value[key] != null && value[key] !== '') {
      collectLocalIds(value[key], acc);
    }
  }
  if (Array.isArray(value.lignes)) {
    collectLocalIds(value.lignes, acc);
  }
  return acc;
}

export function mutationProducesLocalId(mutation, localId) {
  const needle = String(localId);
  if (String(mutation.optimistic?.id) === needle) return true;
  const lignes = mutation.payload?.lignes || mutation.optimistic?.lignes || [];
  if (mutation.action === 'pharmacie.reception.valider' || mutation.action === 'pharmacie.reception.create') {
    return lignes.some((ligne) => {
      if (String(ligne.lotId) === needle) return true;
      const generated = makeLocalLotId(ligne.medicamentId, ligne.numeroLot);
      return generated === needle;
    });
  }
  return false;
}

export async function canPushMutation(item) {
  const resolvedOwnId = item.payload?.id != null
    ? await resolveServerId(item.payload.id)
    : item.optimistic?.id;
  const needsOwnServerId = /\.(update|valider|envoyer|delivrer|refuser|regler|annuler|delete)$/.test(item.action || '');
  if (needsOwnServerId && isLocalId(resolvedOwnId)) {
    return false;
  }

  const ownId = item.optimistic?.id;
  const localIds = collectLocalIds(item.payload).filter((id) => String(id) !== String(ownId));
  for (const id of localIds) {
    const resolved = await resolveServerId(id);
    if (isLocalId(resolved)) {
      return false;
    }
  }
  return true;
}
