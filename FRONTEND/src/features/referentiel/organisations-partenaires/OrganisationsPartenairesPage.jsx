import { FormControl, FormLabel, Option, Select } from '@mui/joy';
import { Building2 } from 'lucide-react';
import { referentiel } from '../../../api/endpoints.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import CodeLibelleListPage from '../shared/CodeLibelleListPage.jsx';
import { createReferentielApi } from '../shared/referentielApi.js';

const organisationsApi = createReferentielApi(referentiel.organisationsPartenaires);

export const TYPE_INSTITUTION_LABELS = {
  UNIVERSITE: 'Université',
  INSTITUT_SUPERIEUR: 'Institut supérieur',
  ECOLE: 'École',
  ENTREPRISE: 'Entreprise',
  ONG: 'ONG',
  ADMINISTRATION: 'Administration',
  AUTRE: 'Autre',
};

export { organisationsApi };

export default function OrganisationsPartenairesPage() {

  return (
    <CodeLibelleListPage
      title="Organisations partenaires"
      description="Universités, instituts, écoles et autres institutions qui envoient des candidats (import Excel → DPI)."
      icon={Building2}
      emptyLabel="Aucune organisation partenaire"
      permissions={{
        create: PERMISSIONS.REFERENTIEL.ORGANISATION_PARTENAIRE_CREATE,
        update: PERMISSIONS.REFERENTIEL.ORGANISATION_PARTENAIRE_UPDATE,
        delete: PERMISSIONS.REFERENTIEL.ORGANISATION_PARTENAIRE_DELETE,
      }}
      api={organisationsApi}
      formIcon={Building2}
      createTitle="Nouvelle organisation"
      editTitle="Modifier l’organisation"
      deleteTitle="Supprimer cette organisation ?"
      deleteHint="Elle ne doit plus avoir de filières ni d’étudiants liés."
      usageCountKey="filiereCount"
      usageCountLabel="Filières"
      codeMaxLength={15}
      libelleMaxLength={150}
      extraFormDefaults={{ typeInstitution: 'UNIVERSITE', statut: 'ACTIF' }}
      mapExtraForm={(item) => ({
        typeInstitution: item.typeInstitution ?? 'UNIVERSITE',
        statut: item.statut ?? 'ACTIF',
      })}
      extraColumn={{
        header: 'Type d’institution',
        render: (item) => TYPE_INSTITUTION_LABELS[item.typeInstitution] ?? item.typeInstitution ?? '—',
      }}
      renderExtraFields={(form, onChange) => (
        <>
          <FormControl required>
            <FormLabel>Type d’institution</FormLabel>
            <Select
              value={form.typeInstitution ?? 'UNIVERSITE'}
              onChange={(_, value) => onChange('typeInstitution', value || 'UNIVERSITE')}
            >
              {Object.entries(TYPE_INSTITUTION_LABELS).map(([value, label]) => (
                <Option key={value} value={value}>{label}</Option>
              ))}
            </Select>
          </FormControl>
          <FormControl required>
            <FormLabel>Statut</FormLabel>
            <Select
              value={form.statut ?? 'ACTIF'}
              onChange={(_, value) => onChange('statut', value || 'ACTIF')}
            >
              <Option value="ACTIF">Actif</Option>
              <Option value="INACTIF">Inactif</Option>
            </Select>
          </FormControl>
        </>
      )}
      createSuccessMessage="Organisation partenaire créée avec succès."
      updateSuccessMessage="Organisation partenaire mise à jour avec succès."
      deleteSuccessMessage="Organisation partenaire supprimée avec succès."
    />
  );
}
