import { Activity, FileAxis3D, FlaskConical, History, Pill } from 'lucide-react';
import { ROUTES } from '../../../constants/routes.js';
import { isWardRoundConsultation } from '../consultations/consultationConstants.js';
import { getConsultationBackPath } from '../../patient/patients/patientDpiTabs.js';

export const TOUR_DE_SALLE_FICHES = [
  {
    slug: 'historique',
    title: 'Historique des évolutions',
    shortTitle: 'Historique',
    Icon: History,
    color: 'neutral',
  },
  {
    slug: 'evolution',
    title: 'Fiche d’évolution',
    shortTitle: 'Évolution',
    Icon: FileAxis3D,
    color: 'primary',
  },
  {
    slug: 'signes-vitaux',
    title: 'Fiche des signes vitaux',
    shortTitle: 'Signes vitaux',
    Icon: Activity,
    color: 'warning',
  },
  {
    slug: 'traitement',
    title: 'Fiche de traitement',
    shortTitle: 'Traitement',
    Icon: Pill,
    color: 'danger',
  },
  {
    slug: 'examens',
    title: 'Examens paracliniques',
    shortTitle: 'Examens',
    Icon: FlaskConical,
    color: 'success',
  },
];

export function tourDeSalleHubPath(consultationId) {
  return ROUTES.CLINIQUE.TOUR_DE_SALLE.replace(':id', String(consultationId));
}

export function tourDeSalleFichePath(consultationId, slug) {
  return ROUTES.CLINIQUE.TOUR_DE_SALLE_FICHE
    .replace(':id', String(consultationId))
    .replace(':fiche', slug);
}

export function getTourDeSalleFiche(slug) {
  return TOUR_DE_SALLE_FICHES.find((fiche) => fiche.slug === slug) ?? null;
}

export function getConsultationWorkspacePath(consultation) {
  if (!consultation?.id) return getConsultationBackPath(consultation);
  if (isWardRoundConsultation(consultation)) {
    return tourDeSalleHubPath(consultation.id);
  }
  return ROUTES.CLINIQUE.CONSULTATIONS_DETAIL.replace(':id', String(consultation.id));
}
