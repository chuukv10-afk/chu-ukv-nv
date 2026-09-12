import { GraduationCap } from 'lucide-react';
import { referentiel } from '../../../api/endpoints.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import CodeLibelleListPage from '../shared/CodeLibelleListPage.jsx';
import { createReferentielApi } from '../shared/referentielApi.js';

const filieresApi = createReferentielApi(referentiel.filieres);

export { filieresApi };

export default function FilieresPage() {
  return (
    <CodeLibelleListPage
      title="Filières UKV"
      description="Gérez les filières universitaires proposées lors d’une admission UKV."
      icon={GraduationCap}
      emptyLabel="Aucune filière"
      permissions={{
        create: PERMISSIONS.REFERENTIEL.FILIERE_CREATE,
        update: PERMISSIONS.REFERENTIEL.FILIERE_UPDATE,
        delete: PERMISSIONS.REFERENTIEL.FILIERE_DELETE,
      }}
      api={filieresApi}
      formIcon={GraduationCap}
      createTitle="Nouvelle filière"
      editTitle="Modifier la filière"
      deleteTitle="Supprimer cette filière ?"
      deleteHint="La filière ne doit plus être liée à un certificat d’aptitude."
      usageCountKey="certificatCount"
      usageCountLabel="Certificats"
      codeMaxLength={12}
      libelleMaxLength={150}
      createSuccessMessage="Filière créée avec succès."
      updateSuccessMessage="Filière mise à jour avec succès."
      deleteSuccessMessage="Filière supprimée avec succès."
    />
  );
}
