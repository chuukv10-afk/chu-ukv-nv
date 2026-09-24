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
registerLabels(PERMISSIONS.FACTURATION);
registerLabels(PERMISSIONS.ADMIN);
registerLabels(PERMISSIONS.RH);

PERMISSION_LABELS[PERMISSIONS.PHARMACIE.SYNC_CONFLICT_DELETE] = 'Hors-ligne — supprimer une écriture non synchronisée';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.APTITUDE_DELETE] = 'Aptitude physique — supprimer un brouillon';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.APTITUDE_DELETE_DEFINITIF] = 'Aptitude physique — supprimer définitivement (y compris signé)';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.APTITUDE_SIGN] = 'Aptitude physique — signer';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.APTITUDE_EXPORT] = 'Aptitude physique — imprimer / exporter';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.APTITUDE_IDENTITE_READ] = 'Aptitude — voir l’identité';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.APTITUDE_IDENTITE_UPDATE] = 'Aptitude — modifier l’identité';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.APTITUDE_IMC_READ] = 'Aptitude — voir l’IMC';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.APTITUDE_IMC_UPDATE] = 'Aptitude — saisir l’IMC';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.APTITUDE_PIGNET_READ] = 'Aptitude — voir l’indice de Pignet';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.APTITUDE_PIGNET_UPDATE] = 'Aptitude — saisir l’indice de Pignet';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.APTITUDE_RUFFIER_READ] = 'Aptitude — voir Ruffier-Dickson';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.APTITUDE_RUFFIER_UPDATE] = 'Aptitude — saisir Ruffier-Dickson';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.APTITUDE_VERDICT_READ] = 'Aptitude — voir le verdict';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.APTITUDE_VERDICT_UPDATE] = 'Aptitude — modifier le verdict';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.IMAGERIE_READ] = 'Imagerie — consulter';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.IMAGERIE_CREATE] = 'Imagerie — créer une étude';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.IMAGERIE_UPDATE] = 'Imagerie — modifier / annuler';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.IMAGERIE_DELETE] = 'Imagerie — supprimer';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.IMAGERIE_UPLOAD] = 'Imagerie — téléverser des images';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.IMAGERIE_INTERPRET] = 'Imagerie — voir et rédiger l\'interprétation';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.IMAGERIE_VALIDATE] = 'Imagerie — valider le compte-rendu';
PERMISSION_LABELS[PERMISSIONS.CLINIQUE.IMAGERIE_EXPORT] = 'Imagerie — imprimer le compte-rendu';
PERMISSION_LABELS[PERMISSIONS.REFERENTIEL.FONCTION_READ] = 'Fonctions — lire';
PERMISSION_LABELS[PERMISSIONS.REFERENTIEL.FONCTION_CREATE] = 'Fonctions — créer';
PERMISSION_LABELS[PERMISSIONS.REFERENTIEL.FONCTION_UPDATE] = 'Fonctions — modifier';
PERMISSION_LABELS[PERMISSIONS.REFERENTIEL.FONCTION_DELETE] = 'Fonctions — supprimer';
PERMISSION_LABELS[PERMISSIONS.REFERENTIEL.FILIERE_READ] = 'Filières — lire';
PERMISSION_LABELS[PERMISSIONS.REFERENTIEL.FILIERE_CREATE] = 'Filières — créer';
PERMISSION_LABELS[PERMISSIONS.REFERENTIEL.FILIERE_UPDATE] = 'Filières — modifier';
PERMISSION_LABELS[PERMISSIONS.REFERENTIEL.FILIERE_DELETE] = 'Filières — supprimer';
PERMISSION_LABELS[PERMISSIONS.REFERENTIEL.ORGANISATION_PARTENAIRE_READ] = 'Organisations partenaires — lire';
PERMISSION_LABELS[PERMISSIONS.REFERENTIEL.ORGANISATION_PARTENAIRE_CREATE] = 'Organisations partenaires — créer';
PERMISSION_LABELS[PERMISSIONS.REFERENTIEL.ORGANISATION_PARTENAIRE_UPDATE] = 'Organisations partenaires — modifier';
PERMISSION_LABELS[PERMISSIONS.REFERENTIEL.ORGANISATION_PARTENAIRE_DELETE] = 'Organisations partenaires — supprimer';
PERMISSION_LABELS[PERMISSIONS.ADMIN.SIGNATURE_READ] = 'Signature — consulter';
PERMISSION_LABELS[PERMISSIONS.ADMIN.SIGNATURE_UPDATE] = 'Signature — lier / remplacer';
PERMISSION_LABELS[PERMISSIONS.ADMIN.PROFIL_IDENTITE_READ] = 'Profil — voir les informations personnelles';
PERMISSION_LABELS[PERMISSIONS.ADMIN.PROFIL_IDENTITE_UPDATE] = 'Profil — modifier les informations personnelles';
PERMISSION_LABELS[PERMISSIONS.ADMIN.PROFIL_AUTH_READ] = 'Profil — voir l’authentification';
PERMISSION_LABELS[PERMISSIONS.ADMIN.PROFIL_AUTH_UPDATE] = 'Profil — modifier le mot de passe';
PERMISSION_LABELS[PERMISSIONS.ADMIN.PROFIL_AFFECTATION_READ] = 'Profil — voir l’affectation';
PERMISSION_LABELS[PERMISSIONS.ADMIN.PROFIL_PHOTO_READ] = 'Profil — voir la photo';
PERMISSION_LABELS[PERMISSIONS.ADMIN.PROFIL_PHOTO_UPDATE] = 'Profil — modifier la photo';
PERMISSION_LABELS[PERMISSIONS.ADMIN.DATABASE_MANAGE] = 'Base de données — consulter';
PERMISSION_LABELS[PERMISSIONS.ADMIN.DATABASE_EXPORT] = 'Base de données — exporter';
PERMISSION_LABELS[PERMISSIONS.ADMIN.DATABASE_TRUNCATE] = 'Base de données — vider des tables';
PERMISSION_LABELS[PERMISSIONS.ADMIN.DATABASE_IMPORT] = 'Base de données — importer SQL';
PERMISSION_LABELS[PERMISSIONS.PHARMACIE.LOT_UPDATE] = 'Lot — modifier n° et péremption';
PERMISSION_LABELS[PERMISSIONS.PHARMACIE.INVENTAIRE_READ] = 'Inventaire pharmacie — consulter';
PERMISSION_LABELS[PERMISSIONS.PHARMACIE.INVENTAIRE_CREATE] = 'Inventaire pharmacie — ouvrir / supprimer';
PERMISSION_LABELS[PERMISSIONS.PHARMACIE.INVENTAIRE_SAISIR] = 'Inventaire pharmacie — saisir / marquer compté';
PERMISSION_LABELS[PERMISSIONS.PHARMACIE.INVENTAIRE_CLOTURER] = 'Inventaire pharmacie — clôturer';
PERMISSION_LABELS[PERMISSIONS.PHARMACIE.INVENTAIRE_ECARTER] = 'Inventaire pharmacie — écarter les non comptés';
PERMISSION_LABELS[PERMISSIONS.PHARMACIE.MEDICAMENT_EXPORT_VALEUR] = 'Catalogue — inclure la valeur du stock à l’export';
PERMISSION_LABELS[PERMISSIONS.PHARMACIE.VENTE_VALIDER] = 'Vente — encaisser ou enregistrer un bon pour';
PERMISSION_LABELS[PERMISSIONS.PHARMACIE.VENTE_SAISIE_ANTERIEURE] = 'Vente — enregistrer une vente antérieure';
PERMISSION_LABELS[PERMISSIONS.PHARMACIE.DEMANDE_SERVICE_SAISIE_ANTERIEURE] = 'Service — enregistrer un approvisionnement antérieur';
PERMISSION_LABELS[PERMISSIONS.INTENDANCE.SYNTHESE_READ] = 'Intendance — consulter les effectifs';
PERMISSION_LABELS[PERMISSIONS.INTENDANCE.ETIQUETTE_GENERATE] = 'Intendance — générer les étiquettes';
PERMISSION_LABELS[PERMISSIONS.INTENDANCE.BIEN_EXPORT] = 'Parc — exporter (PDF / Excel)';
PERMISSION_LABELS[PERMISSIONS.INTENDANCE.CAMPAGNE_VISER] = 'Campagne — viser son service';
PERMISSION_LABELS[PERMISSIONS.INTENDANCE.CAMPAGNE_VISER_TOUS] = 'Campagne — viser tous les services';
PERMISSION_LABELS[PERMISSIONS.INTENDANCE.CAMPAGNE_CLOTURER] = 'Campagne — clôturer';
PERMISSION_LABELS[PERMISSIONS.INTENDANCE.TICKET_PRENDRE] = 'Ticket — prendre en charge';
PERMISSION_LABELS[PERMISSIONS.INTENDANCE.TICKET_CLOTURER] = 'Ticket — clôturer';
PERMISSION_LABELS[PERMISSIONS.FACTURATION.STRUCTURE_READ] = 'Facturation — lire les structures';
PERMISSION_LABELS[PERMISSIONS.FACTURATION.STRUCTURE_CREATE] = 'Facturation — créer une structure';
PERMISSION_LABELS[PERMISSIONS.FACTURATION.STRUCTURE_UPDATE] = 'Facturation — modifier une structure';
PERMISSION_LABELS[PERMISSIONS.FACTURATION.STRUCTURE_DELETE] = 'Facturation — supprimer une structure';
PERMISSION_LABELS[PERMISSIONS.FACTURATION.ACTE_READ] = 'Grille tarifaire — consulter';
PERMISSION_LABELS[PERMISSIONS.FACTURATION.ACTE_CREATE] = 'Grille tarifaire — créer un acte';
PERMISSION_LABELS[PERMISSIONS.FACTURATION.ACTE_UPDATE] = 'Grille tarifaire — modifier un acte';
PERMISSION_LABELS[PERMISSIONS.FACTURATION.ACTE_DELETE] = 'Grille tarifaire — supprimer un acte';
PERMISSION_LABELS[PERMISSIONS.FACTURATION.ACTE_IMPORT] = 'Grille tarifaire — importer Excel';
PERMISSION_LABELS[PERMISSIONS.FACTURATION.ACTE_EXPORT] = 'Grille tarifaire — exporter';
PERMISSION_LABELS[PERMISSIONS.FACTURATION.FACTURE_READ] = 'Factures — consulter';
PERMISSION_LABELS[PERMISSIONS.FACTURATION.FACTURE_CREATE] = 'Factures — créer';
PERMISSION_LABELS[PERMISSIONS.FACTURATION.FACTURE_UPDATE] = 'Factures — modifier';
PERMISSION_LABELS[PERMISSIONS.FACTURATION.FACTURE_DELETE] = 'Factures — supprimer un brouillon';
PERMISSION_LABELS[PERMISSIONS.FACTURATION.FACTURE_VALIDER] = 'Factures — valider';
PERMISSION_LABELS[PERMISSIONS.FACTURATION.FACTURE_EXPORT] = 'Factures — exporter';
PERMISSION_LABELS[PERMISSIONS.FACTURATION.FACTURE_REMISE] = 'Factures — appliquer une remise';
PERMISSION_LABELS[PERMISSIONS.RH.PERSONNEL_READ] = 'RH — consulter les fiches du personnel';
PERMISSION_LABELS[PERMISSIONS.RH.PERSONNEL_CREATE] = 'RH — créer une fiche personnel';
PERMISSION_LABELS[PERMISSIONS.RH.PERSONNEL_UPDATE] = 'RH — modifier une fiche personnel';
PERMISSION_LABELS[PERMISSIONS.RH.PERSONNEL_DELETE] = 'RH — supprimer une fiche personnel';
PERMISSION_LABELS[PERMISSIONS.RH.PERSONNEL_EXPORT] = 'RH — exporter le personnel';
PERMISSION_LABELS[PERMISSIONS.RH.PAIE_READ] = 'RH — consulter la prime locale';
PERMISSION_LABELS[PERMISSIONS.RH.PAIE_CREATE] = 'RH — préparer un mois de prime';
PERMISSION_LABELS[PERMISSIONS.RH.PAIE_UPDATE] = 'RH — ajuster les montants de prime';
PERMISSION_LABELS[PERMISSIONS.RH.PAIE_VALIDATE] = 'RH — clôturer un mois de prime';

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
