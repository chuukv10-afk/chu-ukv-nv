import { createConsultationApi, fetchConsultationsApi } from './consultationsApi.js';
import { getConsultationWorkspacePath } from '../tour-de-salle/tourDeSalleConstants.js';

function visiteHasPendingHospitalization(visite) {
  return Boolean(visite?.pendingHospitalization);
}

export async function openConsultationForVisite({
  visite,
  canCreate,
  navigate,
  typeConsultation,
}) {
  if (visiteHasPendingHospitalization(visite)) {
    throw new Error(
      'Hospitalisation en attente pour cette visite. Affectez un lit via « Hospitaliser » avant d\'ouvrir une nouvelle consultation.',
    );
  }

  const result = await fetchConsultationsApi({ visiteId: visite.id, limit: 10, page: 1 });
  const active = result.items.find((item) => ['PLANIFIEE', 'EN_COURS'].includes(item.statut));

  if (active) {
    if (typeConsultation) {
      throw new Error('Une consultation est déjà en cours pour cette visite. Reprenez-la avant d\'en créer une nouvelle.');
    }
    navigate(getConsultationWorkspacePath(active));
    return { created: false, consultation: active };
  }

  if (!canCreate) {
    throw new Error('Aucune consultation active pour cette visite.');
  }

  const resolvedType = typeConsultation
    || (visite.statut === 'HOSPITALISE' ? 'AU_LIT' : 'NORMALE');

  const created = await createConsultationApi({
    visiteId: visite.id,
    typeConsultation: resolvedType,
    motif: visite.triage?.motif ?? visite.motif ?? '',
    statut: 'EN_COURS',
    patientName: visite.patientName,
  });

  navigate(getConsultationWorkspacePath(created));
  return { created: true, consultation: created };
}
