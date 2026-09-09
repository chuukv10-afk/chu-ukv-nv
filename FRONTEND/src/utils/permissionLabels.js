import { PERMISSIONS, ROLES } from '../constants/permissions.js';

const PERMISSION_LABELS = {
  [ROLES.ADMIN]: 'Administrateur système',
  [ROLES.PERSONNEL]: 'Accès personnel à l\'application',
};

function registerLabels(group) {
  Object.values(group).forEach((code) => {
    if (typeof code !== 'string' || PERMISSION_LABELS[code]) {
      return;
    }

    PERMISSION_LABELS[code] = humanizePermissionCode(code);
  });
}

registerLabels(PERMISSIONS.ORGANISATION);
registerLabels(PERMISSIONS.REFERENTIEL);
registerLabels(PERMISSIONS.CLINIQUE);
registerLabels(PERMISSIONS.PHARMACIE);
registerLabels(PERMISSIONS.PATIENT);
registerLabels(PERMISSIONS.ADMIN);

PERMISSION_LABELS[PERMISSIONS.PHARMACIE.SYNC_CONFLICT_DELETE] = 'Hors-ligne — supprimer une écriture non synchronisée';
PERMISSION_LABELS[PERMISSIONS.PHARMACIE.LOT_UPDATE] = 'Lot — modifier n° et péremption';

function humanizePermissionCode(code) {
  if (code.startsWith('ROLE_')) {
    return code.slice(5).toLowerCase().replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());
  }

  const parts = code.split('.');
  const action = parts.pop() ?? 'accès';
  const resource = (parts.pop() ?? 'ressource').replace(/_/g, ' ');

  const actionLabel = {
    read: 'Lecture',
    create: 'Création',
    update: 'Modification',
    delete: 'Suppression',
    assign: 'Affectation',
    export: 'Export',
  }[action] ?? action;

  const resourceLabel = resource.charAt(0).toUpperCase() + resource.slice(1);

  return `${resourceLabel} — ${actionLabel}`;
}

export function getPermissionLabel(code) {
  if (!code) {
    return '';
  }

  return PERMISSION_LABELS[code] ?? humanizePermissionCode(code);
}

export function buildAccessDeniedMessage(permission) {
  if (!permission) {
    return 'Accès refusé. Vous n\'avez pas la permission requise pour accéder à cette page.';
  }

  const label = getPermissionLabel(permission);

  return `Accès refusé. Permission requise : « ${label} ».`;
}

export function normalizeAccessDeniedMessage(message, requiredPermissions = []) {
  const genericMessages = new Set([
    'Access Denied.',
    'Access Denied',
    'Forbidden',
    'Accès refusé.',
    'Accès refusé',
  ]);

  if (requiredPermissions?.length) {
    return buildAccessDeniedMessage(requiredPermissions[0]);
  }

  if (!message || genericMessages.has(message.trim())) {
    return 'Accès refusé. Vous n\'avez pas la permission requise pour effectuer cette action.';
  }

  return message;
}
