import { useCallback, useEffect, useState } from 'react';
import { Link as RouterLink, useNavigate, useParams } from 'react-router-dom';
import {
  Box, Breadcrumbs, Button, Card, Chip, FormControl, FormLabel, Input, Link, Option, Select, Stack, Tab, TabList, TabPanel, Tabs, Typography,
} from '@mui/joy';
import { ArrowLeft, BedDouble, CalendarClock, FlaskConical, FolderOpen, HeartPulse, Pencil, Plus, RotateCcw, UserRound } from 'lucide-react';
import LoadingSpinner from '../../../components/ui/LoadingSpinner.jsx';
import { ROUTES } from '../../../constants/routes.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import VisiteCreateWizardModal from '../../clinique/visites/components/VisiteCreateWizardModal.jsx';
import PatientVisitesTable from '../../clinique/visites/components/PatientVisitesTable.jsx';
import PatientHospitalisationsTab from '../../clinique/visites/components/PatientHospitalisationsTab.jsx';
import { openConsultationForVisite } from '../../clinique/consultations/openConsultationForVisite.js';
import {
  buildEmptyVisiteCreateForm,
  VISITE_ACTIVE_STATUTS,
} from '../../clinique/visites/visiteConstants.js';
import {
  createVisiteApi,
  fetchVisiteCreateMetaApi,
  fetchVisiteMetaApi,
  fetchVisitesApi,
  updateVisiteApi,
} from '../../clinique/visites/visitesApi.js';
import PatientFormModal from './components/PatientFormModal.jsx';
import {
  DPI_STATUTS,
  DPI_STATUT_COLORS,
  DPI_STATUT_LABELS,
  EMPTY_PATIENT_FORM,
  PATIENT_SEX_LABELS,
  PATIENT_STATUS_COLORS,
  PATIENT_STATUS_LABELS,
} from './patientConstants.js';
import {
  fetchPatientDpiApi,
  updatePatientApi,
  updatePatientDpiApi,
} from './patientsApi.js';
import AntecedentsTab from '../antecedents/AntecedentsTab.jsx';
import DiagnosticsTab from '../../clinique/diagnostics/DiagnosticsTab.jsx';
import DemandesExamenTab from '../../clinique/demandes-examen/DemandesExamenTab.jsx';

function InfoRow({ label, value }) {
  return (
    <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ py: 0.75 }}>
      <Typography level="body-sm" sx={{ minWidth: 180, color: LOTRU_NEUTRAL[600], fontWeight: 600 }}>{label}</Typography>
      <Typography level="body-sm">{value ?? '—'}</Typography>
    </Stack>
  );
}

function formatDate(value) {
  if (!value) return '—';
  const [year, month, day] = value.split('-');
  if (!year || !month || !day) return value;
  return `${day}/${month}/${year}`;
}

export default function PatientDpiPage() {
  const { patientId } = useParams();
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canUpdatePatient = hasPermission(PERMISSIONS.PATIENT.PATIENT_UPDATE);
  const canUpdateDpi = hasPermission(PERMISSIONS.PATIENT.DPI_UPDATE);
  const canCreateVisite = hasPermission(PERMISSIONS.CLINIQUE.VISITE_CREATE);
  const canReadVisite = hasPermission(PERMISSIONS.CLINIQUE.VISITE_READ);
  const canReadSignesVitaux = hasPermission(PERMISSIONS.REFERENTIEL.SIGNE_VITAL_READ);
  const canUpdateVisite = hasPermission(PERMISSIONS.CLINIQUE.VISITE_UPDATE);
  const canReadConsultation = hasPermission(PERMISSIONS.CLINIQUE.CONSULTATION_READ);
  const canCreateConsultation = hasPermission(PERMISSIONS.CLINIQUE.CONSULTATION_CREATE);
  const canUpdateConsultation = hasPermission(PERMISSIONS.CLINIQUE.CONSULTATION_UPDATE);
  const canConsult = canReadConsultation && (canCreateConsultation || canUpdateConsultation);
  const canReadAntecedent = hasPermission(PERMISSIONS.PATIENT.DPI_ANTECEDENT_READ);
  const canCreateAntecedent = hasPermission(PERMISSIONS.PATIENT.DPI_ANTECEDENT_CREATE);
  const canDeleteAntecedent = hasPermission(PERMISSIONS.PATIENT.DPI_ANTECEDENT_DELETE);
  const canReadDiagnostic = hasPermission(PERMISSIONS.CLINIQUE.DIAGNOSTIC_READ);
  const canReadDemandeExamen = hasPermission(PERMISSIONS.CLINIQUE.DEMANDE_EXAMEN_READ);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [data, setData] = useState(null);
  const [dpiStatut, setDpiStatut] = useState('OUVERT');
  const [dpiSaving, setDpiSaving] = useState(false);

  const [visites, setVisites] = useState([]);
  const [visitesLoading, setVisitesLoading] = useState(false);
  const [visitesError, setVisitesError] = useState('');
  const [visitEnterFrom, setVisitEnterFrom] = useState('');
  const [visitEnterTo, setVisitEnterTo] = useState('');
  const [visiteMeta, setVisiteMeta] = useState({ services: [], lits: [] });
  const [visiteFormOpen, setVisiteFormOpen] = useState(false);
  const [visiteCreateMeta, setVisiteCreateMeta] = useState({ departements: [], services: [], signesVitaux: [] });
  const [visiteCreateValues, setVisiteCreateValues] = useState(buildEmptyVisiteCreateForm());
  const [visiteFormLoading, setVisiteFormLoading] = useState(false);
  const [visiteFormError, setVisiteFormError] = useState('');
  const [visiteTransitionLoadingId, setVisiteTransitionLoadingId] = useState(null);

  const [consultationLoadingId, setConsultationLoadingId] = useState(null);

  const [formOpen, setFormOpen] = useState(false);
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const result = await fetchPatientDpiApi(patientId);
      setData(result);
      setDpiStatut(result?.dpi?.statut ?? result?.patient?.dpi?.statut ?? 'OUVERT');
    } catch (err) {
      setError(err.message || 'Impossible de charger le dossier patient.');
    } finally {
      setLoading(false);
    }
  }, [patientId]);

  useEffect(() => { load(); }, [load]);

  const loadVisites = useCallback(async () => {
    const dpiId = data?.dpi?.id ?? data?.patient?.dpi?.id;
    if (!canReadVisite || (!patientId && !dpiId)) return;

    setVisitesLoading(true);
    setVisitesError('');
    try {
      const result = await fetchVisitesApi({
        patientId,
        dpiId: dpiId || undefined,
        limit: 50,
        page: 1,
        enterFrom: visitEnterFrom || undefined,
        enterTo: visitEnterTo || undefined,
      });
      setVisites(result.items);
    } catch (err) {
      setVisites([]);
      setVisitesError(err.message || 'Impossible de charger les visites.');
    } finally {
      setVisitesLoading(false);
    }
  }, [canReadVisite, patientId, data, visitEnterFrom, visitEnterTo]);

  useEffect(() => {
    if (!canReadVisite || !data) return;
    loadVisites();
    fetchVisiteMetaApi().then(setVisiteMeta).catch(() => setVisiteMeta({ services: [], lits: [] }));
  }, [canReadVisite, data, loadVisites]);

  const patient = data?.patient ?? data;
  const dpi = data?.dpi ?? patient?.dpi;
  const hasActiveVisite = visites.some((item) => VISITE_ACTIVE_STATUTS.includes(item.statut));
  const patientWritable = patient?.status === 'ACTIF' && dpi?.statut === 'OUVERT';
  const canOpenVisite = canCreateVisite && canReadSignesVitaux && patientWritable && !hasActiveVisite;

  const handleDpiSave = async () => {
    setDpiSaving(true);
    try {
      const result = await updatePatientDpiApi(patientId, { statut: dpiStatut });
      setData(result);
      showSuccess('Statut DPI mis à jour.');
    } catch (err) {
      showError(err.message || 'Mise à jour impossible.');
    } finally {
      setDpiSaving(false);
    }
  };

  const openEdit = () => {
    setFormError('');
    setFormOpen(true);
  };

  const handlePatientUpdate = async (payload) => {
    setFormLoading(true);
    setFormError('');
    try {
      const updated = await updatePatientApi(patientId, payload);
      setData((current) => ({
        ...current,
        patient: updated,
        dpi: updated?.dpi ?? current?.dpi,
      }));
      setFormOpen(false);
      showSuccess('Patient mis à jour avec succès.');
      await load();
    } catch (err) {
      setFormError(err.message || 'Enregistrement impossible.');
    } finally {
      setFormLoading(false);
    }
  };

  const openCreateVisite = async () => {
    setVisiteFormError('');
    setVisiteCreateValues(buildEmptyVisiteCreateForm(dpi?.id ?? ''));
    try {
      const meta = await fetchVisiteCreateMetaApi();
      setVisiteCreateMeta(meta);
      setVisiteFormOpen(true);
    } catch (err) {
      showError(err.message || 'Impossible de charger les données de création.');
    }
  };

  const handleCreateVisite = async (payload) => {
    setVisiteFormLoading(true);
    setVisiteFormError('');
    try {
      await createVisiteApi({ ...payload, dpiId: dpi?.id ?? payload.dpiId });
      setVisiteFormOpen(false);
      showSuccess('Visite créée avec succès.');
      await loadVisites();
      await load();
    } catch (err) {
      setVisiteFormError(err.message || 'Création impossible.');
    } finally {
      setVisiteFormLoading(false);
    }
  };

  const handleVisiteTransition = async (visite, payload) => {
    setVisiteTransitionLoadingId(visite.id);
    try {
      await updateVisiteApi(visite.id, payload);
      showSuccess('Statut de visite mis à jour.');
      await loadVisites();
    } catch (err) {
      showError(err.message || 'Transition impossible.');
    } finally {
      setVisiteTransitionLoadingId(null);
    }
  };

  const handleOpenConsultation = async (visite, typeConsultation) => {
    setConsultationLoadingId(visite.id);
    try {
      const result = await openConsultationForVisite({
        visite,
        canCreate: canCreateConsultation && patientWritable,
        navigate,
        typeConsultation,
      });
      if (result.created) {
        showSuccess('Consultation créée.');
        await loadVisites();
      }
      return result;
    } catch (err) {
      showError(err.message || 'Impossible d\'ouvrir la consultation.');
      return null;
    } finally {
      setConsultationLoadingId(null);
    }
  };

  if (loading) {
    return <LoadingSpinner fullScreen message="Chargement du dossier patient..." />;
  }

  if (error || !patient) {
    return (
      <Box sx={{ p: 3 }}>
        <Typography level="body-md" color="danger">{error || 'Dossier introuvable.'}</Typography>
        <Button sx={{ mt: 2 }} startDecorator={<ArrowLeft size={16} />} onClick={() => navigate(ROUTES.PATIENT.LIST)}>
          Retour à la liste
        </Button>
      </Box>
    );
  }

  const formValues = {
    nom: patient.nom ?? '',
    postNom: patient.postNom ?? '',
    prenom: patient.prenom ?? '',
    telephone: patient.telephone ?? '',
    adresse: patient.adresse ?? '',
    lieuNaissance: patient.lieuNaissance ?? '',
    dateNaissance: patient.dateNaissance ?? '',
    sexe: patient.sexe ?? 'M',
    groupeSanguin: patient.groupeSanguin ?? '',
    personneAprevenir: patient.personneAprevenir ?? '',
    contactAPrevenir: patient.contactAPrevenir ?? '',
    status: patient.status ?? 'ACTIF',
  };

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={3}>
        <Stack spacing={1}>
          <Breadcrumbs>
            <Link component={RouterLink} to={ROUTES.PATIENT.LIST}>Patients</Link>
            <Typography>Dossier DPI</Typography>
          </Breadcrumbs>
          <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ xs: 'stretch', sm: 'center' }} spacing={2}>
            <Stack direction="row" spacing={1.5} alignItems="center">
              <Box sx={{ width: 44, height: 44, borderRadius: 'md', bgcolor: LOTRU_PRIMARY[50], color: LOTRU_PRIMARY[600], display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <FolderOpen size={22} />
              </Box>
              <Box>
                <Typography level="h3" sx={{ fontWeight: 700 }}>{patient.fullName}</Typography>
                <Stack direction="row" spacing={1} alignItems="center" sx={{ mt: 0.5 }}>
                  <Typography level="body-sm" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>
                    {dpi?.numDossier ?? patient.numDossier}
                  </Typography>
                  <Chip size="sm" variant="soft" color={PATIENT_STATUS_COLORS[patient.status] ?? 'neutral'}>
                    {PATIENT_STATUS_LABELS[patient.status] ?? patient.status}
                  </Chip>
                  <Chip size="sm" variant="outlined" color={DPI_STATUT_COLORS[dpi?.statut] ?? 'neutral'}>
                    {DPI_STATUT_LABELS[dpi?.statut] ?? dpi?.statut}
                  </Chip>
                </Stack>
              </Box>
            </Stack>
            <Stack direction="row" spacing={1}>
              <Button variant="outlined" color="neutral" startDecorator={<ArrowLeft size={16} />} onClick={() => navigate(ROUTES.PATIENT.LIST)}>
                Retour
              </Button>
              {canUpdatePatient ? (
                <Button startDecorator={<Pencil size={16} />} onClick={openEdit}>
                  Modifier l&apos;identité
                </Button>
              ) : null}
            </Stack>
          </Stack>
        </Stack>

        <Tabs defaultValue={0}>
          <TabList>
            <Tab><UserRound size={16} style={{ marginRight: 6 }} />Identité</Tab>
            <Tab><FolderOpen size={16} style={{ marginRight: 6 }} />Dossier (DPI)</Tab>
            <Tab disabled={!canReadAntecedent}><UserRound size={16} style={{ marginRight: 6 }} />Antécédents</Tab>
            <Tab disabled={!canReadDiagnostic}><HeartPulse size={16} style={{ marginRight: 6 }} />Diagnostics</Tab>
            <Tab disabled={!canReadDemandeExamen}><FlaskConical size={16} style={{ marginRight: 6 }} />Examens</Tab>
            <Tab><CalendarClock size={16} style={{ marginRight: 6 }} />Visites</Tab>
            <Tab disabled={!canReadVisite}><BedDouble size={16} style={{ marginRight: 6 }} />Hospitalisation</Tab>
          </TabList>

          <TabPanel value={0} sx={{ p: 0, pt: 2 }}>
            <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5 }}>
              <InfoRow label="Nom complet" value={patient.fullName} />
              <InfoRow label="Date de naissance" value={formatDate(patient.dateNaissance)} />
              <InfoRow label="Lieu de naissance" value={patient.lieuNaissance} />
              <InfoRow label="Sexe" value={PATIENT_SEX_LABELS[patient.sexe] ?? patient.sexe} />
              <InfoRow label="Téléphone" value={patient.telephone} />
              <InfoRow label="Adresse" value={patient.adresse} />
              <InfoRow label="Groupe sanguin" value={patient.groupeSanguin} />
              <InfoRow label="Personne à prévenir" value={patient.personneAprevenir} />
              <InfoRow label="Contact urgence" value={patient.contactAPrevenir} />
            </Card>
          </TabPanel>

          <TabPanel value={1} sx={{ p: 0, pt: 2 }}>
            <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5 }}>
              <Stack spacing={2}>
                <InfoRow label="Numéro de dossier" value={dpi?.numDossier} />
                <InfoRow label="Visites enregistrées" value={String(dpi?.visiteCount ?? 0)} />
                <InfoRow label="Antécédents enregistrés" value={String(dpi?.antecedentCount ?? 0)} />
                {canUpdateDpi ? (
                  <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} alignItems={{ sm: 'flex-end' }}>
                    <FormControl sx={{ minWidth: 220 }}>
                      <FormLabel>Statut du dossier</FormLabel>
                      <Select value={dpiStatut} onChange={(_, value) => setDpiStatut(value ?? 'OUVERT')}>
                        {DPI_STATUTS.map((item) => (
                          <Option key={item.value} value={item.value}>{item.label}</Option>
                        ))}
                      </Select>
                    </FormControl>
                    <Button loading={dpiSaving} onClick={handleDpiSave} disabled={dpiStatut === dpi?.statut}>
                      Enregistrer le statut
                    </Button>
                  </Stack>
                ) : (
                  <InfoRow label="Statut" value={DPI_STATUT_LABELS[dpi?.statut] ?? dpi?.statut} />
                )}
              </Stack>
            </Card>
          </TabPanel>

          <TabPanel value={2} sx={{ p: 0, pt: 2 }}>
            {canReadAntecedent ? (
              <AntecedentsTab
                patientId={patientId}
                readOnly={!patientWritable}
                canCreate={canCreateAntecedent && patientWritable}
                canDelete={canDeleteAntecedent && patientWritable}
                onChanged={load}
              />
            ) : (
              <Typography level="body-sm" color="warning" sx={{ bgcolor: 'warning.50', p: 1.5, borderRadius: 'md' }}>
                Permission insuffisante pour consulter les antécédents.
              </Typography>
            )}
          </TabPanel>

          <TabPanel value={3} sx={{ p: 0, pt: 2 }}>
            {canReadDiagnostic ? (
              <DiagnosticsTab
                patientId={patientId}
                readOnly
                showConsultationContext
              />
            ) : (
              <Typography level="body-sm" color="warning" sx={{ bgcolor: 'warning.50', p: 1.5, borderRadius: 'md' }}>
                Permission insuffisante pour consulter les diagnostics.
              </Typography>
            )}
          </TabPanel>

          <TabPanel value={4} sx={{ p: 0, pt: 2 }}>
            {canReadDemandeExamen ? (
              <DemandesExamenTab
                patientId={patientId}
                readOnly
                showConsultationContext
              />
            ) : (
              <Typography level="body-sm" color="warning" sx={{ bgcolor: 'warning.50', p: 1.5, borderRadius: 'md' }}>
                Permission insuffisante pour consulter les demandes d&apos;examen.
              </Typography>
            )}
          </TabPanel>

          <TabPanel value={5} sx={{ p: 0, pt: 2 }}>
            <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5 }}>
              <Stack spacing={2}>
                <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={1.5}>
                  <Typography level="title-md" sx={{ fontWeight: 700 }}>Historique des visites</Typography>
                  {canOpenVisite ? (
                    <Button size="sm" startDecorator={<Plus size={16} />} onClick={openCreateVisite}>
                      Nouvelle visite
                    </Button>
                  ) : null}
                </Stack>
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} alignItems={{ md: 'flex-end' }}>
                  <FormControl sx={{ minWidth: 160 }}>
                    <FormLabel>Du</FormLabel>
                    <Input type="date" value={visitEnterFrom} onChange={(e) => setVisitEnterFrom(e.target.value)} disabled={!canReadVisite} />
                  </FormControl>
                  <FormControl sx={{ minWidth: 160 }}>
                    <FormLabel>Au</FormLabel>
                    <Input type="date" value={visitEnterTo} onChange={(e) => setVisitEnterTo(e.target.value)} disabled={!canReadVisite} />
                  </FormControl>
                  <Button
                    variant="outlined"
                    color="neutral"
                    startDecorator={<RotateCcw size={16} />}
                    onClick={() => { setVisitEnterFrom(''); setVisitEnterTo(''); }}
                    disabled={!canReadVisite || (!visitEnterFrom && !visitEnterTo)}
                  >
                    Réinitialiser
                  </Button>
                </Stack>

                {!canReadVisite ? (
                  <Typography level="body-sm" color="warning" sx={{ bgcolor: 'warning.50', p: 1.5, borderRadius: 'md' }}>
                    Permission insuffisante pour consulter l&apos;historique des visites.
                  </Typography>
                ) : null}
                {visitesError ? (
                  <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>{visitesError}</Typography>
                ) : null}
                {hasActiveVisite ? (
                  <Typography level="body-sm" color="warning" sx={{ bgcolor: 'warning.50', p: 1.5, borderRadius: 'md' }}>
                    Une visite active existe déjà pour ce dossier.
                  </Typography>
                ) : null}
                {visitesLoading ? (
                  <Typography level="body-sm">Chargement des visites…</Typography>
                ) : visites.length === 0 ? (
                  <Typography level="body-sm" color="neutral">
                    {visitEnterFrom || visitEnterTo ? 'Aucune visite sur cette période.' : 'Aucune visite enregistrée.'}
                  </Typography>
                ) : (
                  <PatientVisitesTable
                    visites={visites}
                    lits={visiteMeta.lits}
                    canUpdate={canUpdateVisite}
                    canConsult={canConsult}
                    consultationLoadingId={consultationLoadingId}
                    transitionLoadingId={visiteTransitionLoadingId}
                    onTransition={handleVisiteTransition}
                    onOpenConsultation={handleOpenConsultation}
                  />
                )}
              </Stack>
            </Card>
          </TabPanel>

          <TabPanel value={6} sx={{ p: 0, pt: 2 }}>
            {canReadVisite ? (
              <PatientHospitalisationsTab
                visites={visites}
                loading={visitesLoading}
                error={visitesError}
                canCreateConsultation={canCreateConsultation}
                recordWritable={patientWritable}
                consultationLoadingId={consultationLoadingId}
                onCreateConsultation={handleOpenConsultation}
              />
            ) : (
              <Typography level="body-sm" color="warning" sx={{ bgcolor: 'warning.50', p: 1.5, borderRadius: 'md' }}>
                Permission insuffisante pour consulter les hospitalisations.
              </Typography>
            )}
          </TabPanel>
        </Tabs>
      </Stack>

      <PatientFormModal
        open={formOpen}
        mode="edit"
        initialValues={formValues}
        loading={formLoading}
        error={formError}
        readOnlyIdentity={patient.status === 'DECEDE'}
        onClose={() => setFormOpen(false)}
        onSubmit={handlePatientUpdate}
      />

      <VisiteCreateWizardModal
        open={visiteFormOpen}
        initialValues={visiteCreateValues}
        createMeta={visiteCreateMeta}
        loading={visiteFormLoading}
        error={visiteFormError}
        dpiLocked
        onClose={() => setVisiteFormOpen(false)}
        onSubmit={handleCreateVisite}
      />
    </Box>
  );
}
