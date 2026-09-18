import { useEffect, useState } from 'react';
import { FormControl, FormHelperText, FormLabel, Option, Select } from '@mui/joy';
import { GraduationCap } from 'lucide-react';
import { callApiGet } from '../../../api/apiClient.js';
import { referentiel } from '../../../api/endpoints.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import CodeLibelleListPage from '../shared/CodeLibelleListPage.jsx';
import { createReferentielApi } from '../shared/referentielApi.js';

const filieresApi = createReferentielApi(referentiel.filieres);

export { filieresApi };

export default function FilieresPage() {
  const [organisations, setOrganisations] = useState([]);

  useEffect(() => {
    callApiGet(`${referentiel.filieres}/lookups/organisations`)
      .then((response) => {
        const data = response?.data ?? response;
        setOrganisations(Array.isArray(data) ? data : []);
      })
      .catch(() => setOrganisations([]));
  }, []);

  return (
    <CodeLibelleListPage
      title="Filières"
      description="Facultés / filières rattachées à une organisation partenaire (université)."
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
      deleteHint="La filière ne doit plus être liée à un certificat d’aptitude ni à un étudiant (DPI)."
      usageCountKey="certificatCount"
      usageCountLabel="Certificats"
      codeMaxLength={12}
      libelleMaxLength={150}
      extraFormDefaults={{ organisationId: null }}
      mapExtraForm={(item) => ({ organisationId: item.organisation?.id ?? item.organisationId ?? null })}
      extraColumn={{
        header: 'Organisation',
        render: (item) => item.organisation?.libelle ?? '—',
      }}
      renderExtraFields={(form, onChange) => (
        <FormControl required>
          <FormLabel>Organisation partenaire</FormLabel>
          <Select
            value={form.organisationId != null ? String(form.organisationId) : ''}
            onChange={(_, value) => onChange('organisationId', value ? Number(value) : null)}
            placeholder="Choisir…"
          >
            {organisations.map((item) => (
              <Option key={item.id} value={String(item.id)}>{item.code} — {item.libelle}</Option>
            ))}
          </Select>
          <FormHelperText>Pour l’UKV, créez d’abord l’organisation de type Université.</FormHelperText>
        </FormControl>
      )}
      createSuccessMessage="Filière créée avec succès."
      updateSuccessMessage="Filière mise à jour avec succès."
      deleteSuccessMessage="Filière supprimée avec succès."
    />
  );
}
