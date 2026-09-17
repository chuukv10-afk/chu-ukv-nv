import { useEffect, useState } from 'react';
import { FormControl, FormHelperText, FormLabel, Option, Select } from '@mui/joy';
import { Briefcase } from 'lucide-react';
import { organisation, referentiel } from '../../../api/endpoints.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { createReferentielApi } from '../shared/referentielApi.js';
import CodeLibelleListPage from '../shared/CodeLibelleListPage.jsx';

const fonctionsApi = createReferentielApi(referentiel.fonctions);
const servicesApi = createReferentielApi(organisation.services);

export { fonctionsApi };

export default function FonctionsPage() {
  const [services, setServices] = useState([]);

  useEffect(() => {
    servicesApi.fetchLookup()
      .then(setServices)
      .catch(() => setServices([]));
  }, []);

  return (
    <CodeLibelleListPage
      title="Fonctions"
      description="Gérez les fonctions RH du personnel (poste officiel, distinct du rôle d’accès)."
      icon={Briefcase}
      emptyLabel="Aucune fonction"
      permissions={{
        create: PERMISSIONS.REFERENTIEL.FONCTION_CREATE,
        update: PERMISSIONS.REFERENTIEL.FONCTION_UPDATE,
        delete: PERMISSIONS.REFERENTIEL.FONCTION_DELETE,
      }}
      api={fonctionsApi}
      formIcon={Briefcase}
      createTitle="Nouvelle fonction"
      editTitle="Modifier la fonction"
      deleteTitle="Supprimer cette fonction ?"
      deleteHint="La fonction ne doit plus être affectée à du personnel."
      usageCountKey="personnelCount"
      usageCountLabel="Personnel"
      codeMaxLength={12}
      libelleMaxLength={150}
      extraFormDefaults={{ serviceId: null }}
      mapExtraForm={(item) => ({ serviceId: item.service?.id ?? item.serviceId ?? null })}
      extraColumn={{
        header: 'Service habituel',
        render: (item) => item.service?.libelle ?? '—',
      }}
      renderExtraFields={(form, onChange) => (
        <FormControl>
          <FormLabel>Service habituel</FormLabel>
          <Select
            value={form.serviceId ?? ''}
            onChange={(_, value) => onChange('serviceId', value || null)}
            placeholder="Aucun (poste transversal)"
          >
            <Option value="">Aucun</Option>
            {services.map((service) => (
              <Option key={service.id} value={service.id}>{service.libelle}</Option>
            ))}
          </Select>
          <FormHelperText>
            Préremplit le service sur la fiche agent. L’affectation reste modifiable.
          </FormHelperText>
        </FormControl>
      )}
      createSuccessMessage="Fonction créée avec succès."
      updateSuccessMessage="Fonction mise à jour avec succès."
      deleteSuccessMessage="Fonction supprimée avec succès."
    />
  );
}
