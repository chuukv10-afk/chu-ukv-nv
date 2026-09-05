import { Award } from 'lucide-react';
import { referentiel } from '../../../api/endpoints.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import CodeLibelleListPage from '../shared/CodeLibelleListPage.jsx';
import { createReferentielApi } from '../shared/referentielApi.js';

const gradesApi = createReferentielApi(referentiel.grades);

export { gradesApi };

export default function GradesPage() {
  return (
    <CodeLibelleListPage
      title="Grades"
      description="Gérez les grades du personnel médical et administratif."
      icon={Award}
      emptyLabel="Aucun grade"
      permissions={{
        create: PERMISSIONS.REFERENTIEL.GRADE_CREATE,
        update: PERMISSIONS.REFERENTIEL.GRADE_UPDATE,
        delete: PERMISSIONS.REFERENTIEL.GRADE_DELETE,
      }}
      api={gradesApi}
      formIcon={Award}
      createTitle="Nouveau grade"
      editTitle="Modifier le grade"
      deleteTitle="Supprimer ce grade ?"
      deleteHint="Le grade ne doit plus être affecté à du personnel."
      usageCountKey="personnelCount"
      usageCountLabel="Personnel"
      createSuccessMessage="Grade créé avec succès."
      updateSuccessMessage="Grade mis à jour avec succès."
      deleteSuccessMessage="Grade supprimé avec succès."
    />
  );
}
