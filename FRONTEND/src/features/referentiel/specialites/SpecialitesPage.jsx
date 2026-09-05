import { Stethoscope } from 'lucide-react';
import { referentiel } from '../../../api/endpoints.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import CodeLibelleListPage from '../shared/CodeLibelleListPage.jsx';
import { createReferentielApi } from '../shared/referentielApi.js';

const specialitesApi = createReferentielApi(referentiel.specialites);

export { specialitesApi };

export default function SpecialitesPage() {
  return (
    <CodeLibelleListPage
      title="Spécialités"
      description="Gérez les spécialités médicales du personnel."
      icon={Stethoscope}
      emptyLabel="Aucune spécialité"
      permissions={{
        create: PERMISSIONS.REFERENTIEL.SPECIALITE_CREATE,
        update: PERMISSIONS.REFERENTIEL.SPECIALITE_UPDATE,
        delete: PERMISSIONS.REFERENTIEL.SPECIALITE_DELETE,
      }}
      exportPermission={PERMISSIONS.REFERENTIEL.SPECIALITE_EXPORT}
      exportEndpoint={referentiel.specialites}
      api={specialitesApi}
      formIcon={Stethoscope}
      createTitle="Nouvelle spécialité"
      editTitle="Modifier la spécialité"
      deleteTitle="Supprimer cette spécialité ?"
      deleteHint="La spécialité ne doit plus être affectée à du personnel."
      usageCountKey="personnelCount"
      usageCountLabel="Personnel"
      createSuccessMessage="Spécialité créée avec succès."
      updateSuccessMessage="Spécialité mise à jour avec succès."
      deleteSuccessMessage="Spécialité supprimée avec succès."
    />
  );
}
