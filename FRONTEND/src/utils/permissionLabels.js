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
registerLabels(PERMISSIONS.INTENDANCE);
registerLabels(PERMISSIONS.PATIENT);
registerLabels(PERMISSIONS.ADMIN);

PERMISSION_LABELS[PERMISSIONS.PHARMACIE.SYNC_CONFLICT_DELETE] = 'Hors-ligne — supprimer une écriture non synchronisée';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.APTITUDE_SIGN] = 'Aptitude physique — signer';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.APTITUDE_EXPORT] = 'Aptitude physique — imprimer / exporter';
PERMISSION_LABELS[PERMISSIONS.REFERENTIEL.FILIERE_READ] = 'Filières UKV — lire';
PERMISSION_LABELS[PERMISSIONS.REFERENTIEL.FILIERE_CREATE] = 'Filières UKV — créer';
PERMISSION_LABELS[PERMISSIONS.REFERENTIEL.FILIERE_UPDATE] = 'Filières UKV — modifier';
PERMISSION_LABELS[PERMISSIONS.REFERENTIEL.FILIERE_DELETE] = 'Filières UKV — supprimer';
PERMISSION_LABELS[PERMISSIONS.ADMIN.SIGNATURE_READ] = 'Signature — consulter';
PERMISSION_LABELS[PERMISSIONS.ADMIN.SIGNATURE_UPDATE] = 'Signature — lier / remplacer';
PERMISSION_LABELS[PERMISSIONS.ADMIN.DATABASE_MANAGE] = 'Base de données — consulter';
PERMISSION_LABELS[PERMISSIONS.ADMIN.DATABASE_EXPORT] = 'Base de données — exporter';
PERMISSION_LABELS[PERMISSIONS.ADMIN.DATABASE_TRUNCATE] = 'Base de données — vider des tables';
PERMISSION_LABELS[PERMISSIONS.ADMIN.DATABASE_IMPORT] = 'Base de données — importer SQL';
PERMISSION_LABELS[PERMISSIONS.PHARMACIE.LOT_UPDATE] = 'Lot — modifier n° et péremption';
PERMISSION_LABELS[PERMISSIONS.PHARMACIE.VENTE_SAISIE_ANTERIEURE] = 'Vente — enregistrer une vente antérieure';
PERMISSION_LABELS[PERMISSIONS.INTENDANCE.SYNTHESE_READ] = 'Intendance — consulter les effectifs';
PERMISSION_LABELS[PERMISSIONS.INTENDANCE.ETIQUETTE_GENERATE] = 'Intendance — générer les étiquettes';
PERMISSION_LABELS[PERMISSIONS.INTENDANCE.BIEN_EXPORT] = 'Parc — exporter (PDF / Excel)';
PERMISSION_LABELS[PERMISSIONS.INTENDANCE.CAMPAGNE_VISER] = 'Campagne — viser son service';
PERMISSION_LABELS[PERMISSIONS.INTENDANCE.CAMPAGNE_VISER_TOUS] = 'Campagne — viser tous les services';
PERMISSION_LABELS[PERMISSIONS.INTENDANCE.CAMPAGNE_CLOTURER] = 'Campagne — clôturer';
PERMISSION_LABELS[PERMISSIONS.INTENDANCE.TICKET_PRENDRE] = 'Ticket — prendre en charge';
PERMISSION_LABELS[PERMISSIONS.INTENDANCE.TICKET_CLOTURER] = 'Ticket — clôturer';

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
