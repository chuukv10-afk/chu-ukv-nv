import { Microscope } from 'lucide-react';
import { referentiel } from '../../../api/endpoints.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import CodeLibelleListPage from '../shared/CodeLibelleListPage.jsx';
import { createReferentielApi } from '../shared/referentielApi.js';

const typesExamenApi = createReferentielApi(referentiel.typesExamen);

export { typesExamenApi };

export default function TypesExamenPage() {
  return (
    <CodeLibelleListPage
      title="Types d'examen"
      description="Gérez les types d'examens du référentiel clinique."
      icon={Microscope}
      emptyLabel="Aucun type d'examen"
      permissions={{
        create: PERMISSIONS.REFERENTIEL.TYPE_EXAMEN_CREATE,
        update: PERMISSIONS.REFERENTIEL.TYPE_EXAMEN_UPDATE,
        delete: PERMISSIONS.REFERENTIEL.TYPE_EXAMEN_DELETE,
      }}
      exportPermission={PERMISSIONS.REFERENTIEL.TYPE_EXAMEN_EXPORT}
      exportEndpoint={referentiel.typesExamen}
      api={typesExamenApi}
      formIcon={Microscope}
      createTitle="Nouveau type d'examen"
      editTitle="Modifier le type d'examen"
      deleteTitle="Supprimer ce type d'examen ?"
      deleteHint="Cette catégorie ne doit plus contenir d'examens."
      usageCountKey="examenCount"
      usageCountLabel="Examens"
      createSuccessMessage="Type d'examen créé avec succès."
      updateSuccessMessage="Type d'examen mis à jour avec succès."
      deleteSuccessMessage="Type d'examen supprimé avec succès."
    />
  );
}
