import { createConsultationApi, fetchConsultationsApi } from './consultationsApi.js';
import { getConsultationWorkspacePath } from '../tour-de-salle/tourDeSalleConstants.js';

export async function openConsultationForVisite({
  visite,
  canCreate,
  navigate,
  typeConsultation,
}) {
  const result = await fetchConsultationsApi({ visiteId: visite.id, limit: 10, page: 1 });
  const active = result.items.find((item) => ['PLANIFIEE', 'EN_COURS'].includes(item.statut));

  if (active) {
    navigate(getConsultationWorkspacePath(active));
    return { created: false, consultation: active };
  }

  if (!canCreate) {
    throw new Error('Aucune consultation active pour cette visite.');
  }

  const resolvedType = typeConsultation
    || (visite.statut === 'HOSPITALISE' ? 'AU_LIT' : 'NORMALE');

  const created = await createConsultationApi({
    visiteId: Number(visite.id),
    typeConsultation: resolvedType,
    motif: visite.triage?.motif ?? '',
    statut: 'EN_COURS',
  });

  navigate(getConsultationWorkspacePath(created));
  return { created: true, consultation: created };
}
