export const DEFAULT_CONSULTATION_PAGE_SIZE = 10;
export const CONSULTATION_PAGE_SIZE_OPTIONS = [10, 25, 50];

export const CONSULTATION_STATUT_LABELS = {
  PLANIFIEE: 'Planifiée',
  EN_COURS: 'En cours',
  TERMINEE: 'Terminée',
  ANNULEE: 'Annulée',
};

export const CONSULTATION_STATUT_COLORS = {
  PLANIFIEE: 'neutral',
  EN_COURS: 'primary',
  TERMINEE: 'success',
  ANNULEE: 'danger',
};

export const CONSULTATION_TYPE_LABELS = {
  NORMALE: 'Consultation externe',
  AU_LIT: 'Tour de salle',
  URGENCE: 'Urgence',
  INITIALE: 'Initiale',
  SUIVI: 'Suivi',
  SPECIALISTE: 'Spécialiste',
};

export const CONSULTATION_TYPE_COLORS = {
  NORMALE: 'primary',
  AU_LIT: 'warning',
  URGENCE: 'danger',
  INITIALE: 'neutral',
  SUIVI: 'success',
  SPECIALISTE: 'neutral',
};

export function isBedsideConsultation(consultation) {
  return consultation?.typeConsultation === 'AU_LIT' || consultation?.visiteStatut === 'HOSPITALISE';
}

export function isWardRoundConsultation(consultation) {
  return consultation?.typeConsultation === 'AU_LIT';
}

export function getConsultationKindLabel(consultation) {
  return isWardRoundConsultation(consultation) ? 'Tour de salle' : 'Consultation';
}

export function getRecordLockReason(record) {
  if (record?.patientStatus === 'DECEDE') {
    return 'Patient décédé — dossier en lecture seule.';
  }
  if (record?.patientStatus === 'INACTIF') {
    return 'Patient inactif — réactivez-le pour modifier le dossier.';
  }
  if (record?.dpiStatut && record.dpiStatut !== 'OUVERT') {
    return 'Dossier patient archivé ou fermé — lecture seule.';
  }
  if (record?.recordWritable === false) {
    return 'Ce dossier clinique n’est plus modifiable.';
  }
  return '';
}

export const CONSULTATION_TRANSITION_LABELS = {
  EN_COURS: 'Démarrer',
  TERMINEE: 'Clôturer',
  ANNULEE: 'Annuler',
};

export const CONSULTATION_EDITABLE_STATUTS = ['PLANIFIEE', 'EN_COURS'];

export const VISITE_CONSULTATION_STATUTS = ['EN_COURS', 'HOSPITALISE'];

export const CONSULTATION_ACTIVE_STATUTS = ['PLANIFIEE', 'EN_COURS'];

export function visiteHasActiveConsultation(visite) {
  return Boolean(visite?.hasActiveConsultation ?? visite?.activeConsultationId);
}

export const EMPTY_CONSULTATION_FORM = {
  visiteId: '',
  typeConsultation: 'NORMALE',
  motif: '',
  histoireMaladie: '',
  consultationObservation: '',
  conduireATenir: '',
  statut: '',
};

export function buildEmptyConsultationCreateForm(visiteId = '') {
  return {
    visiteId: visiteId ? String(visiteId) : '',
    typeConsultation: 'NORMALE',
    motif: '',
    statut: 'EN_COURS',
  };
}

import { createEmptyPhysicalExam, normalizePhysicalExam } from './utils/physicalExamSchema.js';

export function buildConsultationEditForm(consultation) {
  return {
    typeConsultation: consultation?.typeConsultation ?? 'NORMALE',
    motif: consultation?.motif ?? '',
    histoireMaladie: consultation?.histoireMaladie ?? '',
    consultationObservation: consultation?.consultationObservation ?? '',
    conduireATenir: consultation?.conduireATenir ?? '',
    statut: '',
  };
}

export function buildClinicalForm(consultation) {
  return {
    motif: consultation?.motif ?? '',
    histoireMaladie: consultation?.histoireMaladie ?? '',
    physicalExam: normalizePhysicalExam(consultation?.physicalExam ?? createEmptyPhysicalExam()),
    physicalExamText: consultation?.physicalExamText ?? consultation?.consultationObservation ?? '',
    complementAnamnese: Array.isArray(consultation?.complementAnamnese) ? consultation.complementAnamnese : [],
    conduireATenir: consultation?.conduireATenir ?? consultation?.followUpPlan ?? '',
  };
}
