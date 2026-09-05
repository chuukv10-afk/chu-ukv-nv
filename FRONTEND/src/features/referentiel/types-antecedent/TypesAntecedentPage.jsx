import { History } from 'lucide-react';
import { referentiel } from '../../../api/endpoints.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import CodeLibelleListPage from '../shared/CodeLibelleListPage.jsx';
import { createReferentielApi } from '../shared/referentielApi.js';

const typesAntecedentApi = createReferentielApi(referentiel.typesAntecedent);

export { typesAntecedentApi };

export default function TypesAntecedentPage() {
  return (
    <CodeLibelleListPage
      title="Types d'antécédent"
      description="Gérez les types d'antécédents médicaux des patients."
      icon={History}
      emptyLabel="Aucun type d'antécédent"
      permissions={{
        create: PERMISSIONS.REFERENTIEL.TYPE_ANTECEDENT_CREATE,
        update: PERMISSIONS.REFERENTIEL.TYPE_ANTECEDENT_UPDATE,
        delete: PERMISSIONS.REFERENTIEL.TYPE_ANTECEDENT_DELETE,
      }}
      api={typesAntecedentApi}
      formIcon={History}
      createTitle="Nouveau type d'antécédent"
      editTitle="Modifier le type d'antécédent"
      deleteTitle="Supprimer ce type d'antécédent ?"
      deleteHint="Le type ne doit plus être utilisé dans des antécédents patients."
      usageCountKey="antecedentCount"
      usageCountLabel="Antécédents"
      libelleMaxLength={50}
      createSuccessMessage="Type d'antécédent créé avec succès."
      updateSuccessMessage="Type d'antécédent mis à jour avec succès."
      deleteSuccessMessage="Type d'antécédent supprimé avec succès."
    />
  );
}
