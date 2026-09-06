export const VISITE_STATUTS = [
  { value: 'PLANIFIEE', label: 'Planifiée', color: 'primary' },
  { value: 'EN_COURS', label: 'En cours', color: 'success' },
  { value: 'HOSPITALISE', label: 'Hospitalisé', color: 'warning' },
  { value: 'TERMINEE', label: 'Terminée', color: 'neutral' },
  { value: 'ANNULEE', label: 'Annulée', color: 'danger' },
];

export const VISITE_STATUT_LABELS = VISITE_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const VISITE_STATUT_COLORS = VISITE_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.color;
  return acc;
}, {});

export const VISITE_ACTIVE_STATUTS = ['PLANIFIEE', 'EN_COURS', 'HOSPITALISE'];

export const DEFAULT_VISITE_PAGE_SIZE = 10;
export const VISITE_PAGE_SIZE_OPTIONS = [10, 25, 50];

export const EMPTY_VISITE_FORM = {
  dpiId: '',
  serviceId: '',
  statut: 'EN_COURS',
  litId: '',
  sortedPrevuAt: '',
};

export const TYPE_ENTREE_OPTIONS = [
  { value: 'RENDEZ_VOUS', label: 'Rendez-vous' },
  { value: 'CONSULTATION', label: 'Consultation' },
  { value: 'URGENCE', label: 'Urgence' },
];

export const TYPE_ENTREE_LABELS = TYPE_ENTREE_OPTIONS.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const PRIORITE_OPTIONS = [
  { value: 1, label: '1 — Très urgent' },
  { value: 2, label: '2 — Urgent' },
  { value: 3, label: '3 — Normal' },
  { value: 4, label: '4 — Peu urgent' },
  { value: 5, label: '5 — Non urgent' },
];

export function buildEmptyVisiteCreateForm(dpiId = '') {
  return {
    dpiId: dpiId ?? '',
    typeEntree: 'CONSULTATION',
    motif: '',
    priorite: 3,
    signesVitaux: {},
    departementId: '',
    serviceId: '',
    sortedPrevuAt: '',
  };
}

export const VISITE_TRANSITION_LABELS = {
  EN_COURS: 'Démarrer',
  HOSPITALISE: 'Hospitaliser',
  TERMINEE: 'Clôturer',
  ANNULEE: 'Annuler',
};

export function filterVisiteAllowedTransitions(visite, transitions = visite?.allowedTransitions ?? []) {
  const hasActiveConsultation = Boolean(
    visite?.hasActiveConsultation ?? visite?.activeConsultationId,
  );
  const isHospitalized = visite?.statut === 'HOSPITALISE';

  return transitions.filter((statut) => {
    if (statut !== 'TERMINEE' && statut !== 'ANNULEE') {
      return true;
    }
    return !hasActiveConsultation && !isHospitalized;
  });
}

export function canDischargeHospitalization(visite) {
  return Boolean(
    visite?.canDischargeHospitalization
    ?? (visite?.statut === 'HOSPITALISE' && !visite?.hasActiveConsultation && !visite?.activeConsultationId),
  );
}
