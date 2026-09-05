import { useCallback, useEffect, useState } from 'react';
import { Link as RouterLink, Navigate, useNavigate, useParams } from 'react-router-dom';
import {
  Box,
  Breadcrumbs,
  Button,
  Chip,
  Link,
  Stack,
  Tab,
  TabList,
  TabPanel,
  Tabs,
  Typography,
} from '@mui/joy';
import {
  Activity,
  ArrowLeft,
  BedDouble,
  ClipboardList,
  FlaskConical,
  HeartPulse,
  Pill,
  Printer,
  Save,
  Stethoscope,
  Syringe,
  UserRound,
  XCircle,
} from 'lucide-react';
import LoadingSpinner from '../../../components/ui/LoadingSpinner.jsx';
import { ROUTES } from '../../../constants/routes.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import {
  buildClinicalForm,
  CONSULTATION_STATUT_COLORS,
  CONSULTATION_STATUT_LABELS,
  CONSULTATION_TYPE_COLORS,
  CONSULTATION_TYPE_LABELS,
  getConsultationKindLabel,
  getRecordLockReason,
  isBedsideConsultation,
  isWardRoundConsultation,
} from './consultationConstants.js';
import { tourDeSalleHubPath } from '../tour-de-salle/tourDeSalleConstants.js';
import {
  closeConsultationApi,
  fetchConsultationApi,
  updateConsultationApi,
} from './consultationsApi.js';
import ClinicalExamTab from './components/ClinicalExamTab.jsx';
import ConsultationCloseModal from './components/ConsultationCloseModal.jsx';
import ConsultationHospitalisationOfferModal from './components/ConsultationHospitalisationOfferModal.jsx';
import ConsultationPlaceholderTab from './components/ConsultationPlaceholderTab.jsx';
import ConsultationVitalsTab from './components/ConsultationVitalsTab.jsx';
import AntecedentsTab from '../../patient/antecedents/AntecedentsTab.jsx';
import DiagnosticsTab from '../diagnostics/DiagnosticsTab.jsx';
import StayDiagnosticsCard from '../diagnostics/StayDiagnosticsCard.jsx';
import DemandesExamenTab from '../demandes-examen/DemandesExamenTab.jsx';
import VisiteHospitalisationModal from '../visites/components/VisiteHospitalisationModal.jsx';
import { fetchVisiteHospitalisationMetaApi, updateVisiteApi } from '../visites/visitesApi.js';
import { printPhysicalExamSection } from './utils/consultationPrintUtils.js';

function formatDateTime(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('fr-FR');
}

export default function ConsultationDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();

  const canUpdate = hasPermission(PERMISSIONS.CLINIQUE.CONSULTATION_UPDATE);
  const canClose = hasPermission(PERMISSIONS.CLINIQUE.CONSULTATION_CLOSE);
  const canExport = hasPermission(PERMISSIONS.CLINIQUE.CONSULTATION_EXPORT);
  const canReadDiagnostic = hasPermission(PERMISSIONS.CLINIQUE.DIAGNOSTIC_READ);
  const canCreateDiagnostic = hasPermission(PERMISSIONS.CLINIQUE.DIAGNOSTIC_CREATE);
  const canDeleteDiagnostic = hasPermission(PERMISSIONS.CLINIQUE.DIAGNOSTIC_DELETE);
  const canReadDemandeExamen = hasPermission(PERMISSIONS.CLINIQUE.DEMANDE_EXAMEN_READ);
  const canCreateDemandeExamen = hasPermission(PERMISSIONS.CLINIQUE.DEMANDE_EXAMEN_CREATE);
  const canCancelDemandeExamen = hasPermission(PERMISSIONS.CLINIQUE.DEMANDE_EXAMEN_CANCEL);
  const canSaisieDemandeExamen = hasPermission(PERMISSIONS.CLINIQUE.DEMANDE_EXAMEN_SAISIE_RESULTAT);
  const canValidateDemandeExamen = hasPermission(PERMISSIONS.CLINIQUE.DEMANDE_EXAMEN_VALIDATE);
  const canHospitalize = hasPermission(PERMISSIONS.CLINIQUE.VISITE_UPDATE);

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [closeLoading, setCloseLoading] = useState(false);
  const [error, setError] = useState('');
  const [consultation, setConsultation] = useState(null);
  const [clinicalForm, setClinicalForm] = useState(buildClinicalForm(null));
  const [closeOpen, setCloseOpen] = useState(false);
  const [closeError, setCloseError] = useState('');
  const [hospOfferOpen, setHospOfferOpen] = useState(false);
  const [hospModalOpen, setHospModalOpen] = useState(false);
  const [hospMeta, setHospMeta] = useState({ blocs: [] });
  const [hospMetaLoading, setHospMetaLoading] = useState(false);
  const [hospLoading, setHospLoading] = useState(false);
  const [hospError, setHospError] = useState('');
  const [tabIndex, setTabIndex] = useState(0);
  const [stayDiagnosticsTick, setStayDiagnosticsTick] = useState(0);

  const recordLockReason = getRecordLockReason(consultation);
  const isConsultationLocked = Boolean(
    consultation?.isClosed
    || consultation?.isEditable === false
    || consultation?.recordWritable === false
    || recordLockReason,
  );
  const readOnly = isConsultationLocked || !canUpdate;
  const alreadyHospitalized = Boolean(consultation?.alreadyHospitalized || consultation?.visiteStatut === 'HOSPITALISE');
  const isBedside = isBedsideConsultation(consultation);
  const isWardRound = isWardRoundConsultation(consultation);
  const kindLabel = getConsultationKindLabel(consultation);
  const pendingHospitalization = Boolean(
    consultation?.isClosed
    && consultation?.closeDisposition?.needsHospitalization
    && consultation?.visiteStatut === 'EN_COURS',
  );

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const detail = await fetchConsultationApi(id);
      setConsultation(detail);
      setClinicalForm(buildClinicalForm(detail));
    } catch (err) {
      setError(err.message || 'Impossible de charger la consultation.');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    load();
  }, [load]);

  const updateClinicalField = (field, value) => {
    setClinicalForm((current) => ({
      ...current,
      [field]: typeof value === 'function' ? value(current[field]) : value,
    }));
  };

  const handleSave = async () => {
    if (!consultation || readOnly) return;
    setSaving(true);
    try {
      const updated = await updateConsultationApi(consultation.id, {
        motif: clinicalForm.motif,
        histoireMaladie: clinicalForm.histoireMaladie,
        physicalExamText: clinicalForm.physicalExamText,
        conduireATenir: clinicalForm.conduireATenir,
        complementAnamnese: clinicalForm.complementAnamnese,
        physicalExam: clinicalForm.physicalExam,
      });
      setConsultation(updated);
      setClinicalForm(buildClinicalForm(updated));
      showSuccess('Consultation enregistrée.');
    } catch (err) {
      showError(err.message || 'Enregistrement impossible.');
    } finally {
      setSaving(false);
    }
  };

  const handleClose = async (payload) => {
    if (!consultation) return;
    setCloseLoading(true);
    setCloseError('');
    try {
      const updated = await closeConsultationApi(consultation.id, payload);
      setConsultation(updated);
      setClinicalForm(buildClinicalForm(updated));
      setCloseOpen(false);
      showSuccess('Consultation clôturée.');

      const canAssignBed = payload.needsHospitalization
        && !payload.dischargePatient
        && updated.visiteStatut === 'EN_COURS'
        && canHospitalize
        && updated.visiteId;

      if (canAssignBed) {
        setHospOfferOpen(true);
      }
    } catch (err) {
      setCloseError(err.message || 'Clôture impossible.');
    } finally {
      setCloseLoading(false);
    }
  };

  const openHospitalisationModal = async () => {
    if (!consultation?.visiteId) return;
    setHospOfferOpen(false);
    setHospError('');
    setHospMetaLoading(true);
    setHospModalOpen(true);
    try {
      const meta = await fetchVisiteHospitalisationMetaApi(consultation.visiteId);
      setHospMeta(meta);
    } catch (err) {
      setHospMeta({ blocs: [] });
      setHospError(err.message || 'Impossible de charger les blocs et lits.');
    } finally {
      setHospMetaLoading(false);
    }
  };

  const handleHospitalisationLater = () => {
    setHospOfferOpen(false);
    showSuccess('Hospitalisation reportée. Vous pourrez affecter un lit depuis Visites ou le dossier patient.');
  };

  const handleHospitalisationConfirm = async (litId) => {
    if (!consultation?.visiteId) return;
    setHospLoading(true);
    setHospError('');
    try {
      await updateVisiteApi(consultation.visiteId, { statut: 'HOSPITALISE', litId });
      setHospModalOpen(false);
      showSuccess('Patient hospitalisé. Lit affecté.');
      const refreshed = await fetchConsultationApi(consultation.id);
      setConsultation(refreshed);
      setClinicalForm(buildClinicalForm(refreshed));
    } catch (err) {
      setHospError(err.message || 'Hospitalisation impossible.');
    } finally {
      setHospLoading(false);
    }
  };

  const handlePrint = () => {
    if (!consultation || !canExport) return;
    printPhysicalExamSection(consultation, clinicalForm);
  };

  if (loading) {
    return <LoadingSpinner fullScreen message="Chargement de la consultation..." />;
  }

  if (consultation && isWardRoundConsultation(consultation)) {
    return <Navigate to={tourDeSalleHubPath(consultation.id)} replace />;
  }

  if (error || !consultation) {
    return (
      <Box sx={{ p: 3 }}>
        <Typography level="body-md" color="danger">{error || 'Consultation introuvable.'}</Typography>
        <Button
          sx={{ mt: 2 }}
          startDecorator={<ArrowLeft size={16} />}
          onClick={() => navigate(ROUTES.CLINIQUE.CONSULTATIONS)}
        >
          Retour aux consultations
        </Button>
      </Box>
    );
  }

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={3}>
        <Stack spacing={1}>
          <Breadcrumbs>
            <Link component={RouterLink} to={ROUTES.CLINIQUE.CONSULTATIONS}>Consultations</Link>
            <Typography>{kindLabel}</Typography>
          </Breadcrumbs>

          <Stack
            direction={{ xs: 'column', lg: 'row' }}
            justifyContent="space-between"
            alignItems={{ xs: 'stretch', lg: 'center' }}
            spacing={2}
          >
            <Stack direction="row" spacing={1.5} alignItems="center">
              <Box
                sx={{
                  width: 44,
                  height: 44,
                  borderRadius: 'md',
                  bgcolor: LOTRU_PRIMARY[50],
                  color: LOTRU_PRIMARY[600],
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                }}
              >
                {isBedside ? <BedDouble size={22} /> : <Stethoscope size={22} />}
              </Box>
              <Box>
                <Typography level="h3" sx={{ fontWeight: 700, color: LOTRU_NEUTRAL[900] }}>
                  {kindLabel} — {consultation.patientName ?? 'Patient'}
                </Typography>
                <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap sx={{ mt: 0.5 }}>
                  {consultation.patientId ? (
                    <Link
                      component={RouterLink}
                      to={ROUTES.PATIENT.DPI.replace(':patientId', consultation.patientId)}
                      level="body-sm"
                      sx={{ fontWeight: 600 }}
                    >
                      {consultation.numDossier ?? 'Dossier patient'}
                    </Link>
                  ) : (
                    <Typography level="body-sm" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>
                      {consultation.numDossier ?? '—'}
                    </Typography>
                  )}
                  <Chip size="sm" variant="soft" color={CONSULTATION_STATUT_COLORS[consultation.statut] ?? 'neutral'}>
                    {CONSULTATION_STATUT_LABELS[consultation.statut] ?? consultation.statut}
                  </Chip>
                  <Chip
                    size="sm"
                    variant="soft"
                    color={CONSULTATION_TYPE_COLORS[consultation.typeConsultation] ?? 'neutral'}
                  >
                    {CONSULTATION_TYPE_LABELS[consultation.typeConsultation] ?? consultation.typeConsultation}
                  </Chip>
                  {alreadyHospitalized && (
                    <Chip size="sm" variant="soft" color="warning">Hospitalisé</Chip>
                  )}
                  {consultation.isClosed && (
                    <Chip size="sm" variant="soft" color="neutral">Lecture seule</Chip>
                  )}
                  {pendingHospitalization && (
                    <Chip size="sm" variant="soft" color="warning">À hospitaliser</Chip>
                  )}
                </Stack>
                <Typography level="body-xs" sx={{ color: LOTRU_NEUTRAL[600], mt: 0.5 }}>
                  {formatDateTime(consultation.consultedAt)}
                  {consultation.service?.libelle ? ` · ${consultation.service.libelle}` : ''}
                  {consultation.openedBy?.fullName ? ` · Dr. ${consultation.openedBy.fullName}` : ''}
                </Typography>
              </Box>
            </Stack>

            <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
              <Button
                variant="outlined"
                color="neutral"
                startDecorator={<ArrowLeft size={16} />}
                onClick={() => navigate(ROUTES.CLINIQUE.CONSULTATIONS)}
              >
                Retour
              </Button>
              {canExport && (
                <Button
                  variant="outlined"
                  color="neutral"
                  startDecorator={<Printer size={16} />}
                  onClick={handlePrint}
                >
                  Imprimer
                </Button>
              )}
              {!readOnly && canUpdate && (
                <Button loading={saving} startDecorator={<Save size={16} />} onClick={handleSave}>
                  Enregistrer
                </Button>
              )}
              {pendingHospitalization && canHospitalize && (
                <Button
                  color="warning"
                  startDecorator={<BedDouble size={16} />}
                  onClick={openHospitalisationModal}
                >
                  Affecter un lit
                </Button>
              )}
              {!consultation.isClosed && canClose && !readOnly && (
                <Button
                  color="danger"
                  variant="soft"
                  startDecorator={<XCircle size={16} />}
                  onClick={() => setCloseOpen(true)}
                >
                  {isWardRound ? 'Clôturer le tour' : 'Clôturer'}
                </Button>
              )}
            </Stack>
          </Stack>
        </Stack>

        {recordLockReason ? (
          <Typography level="body-sm" color="warning" sx={{ bgcolor: 'warning.50', p: 1.5, borderRadius: 'md' }}>
            {recordLockReason}
          </Typography>
        ) : null}

        <Tabs value={tabIndex} onChange={(_, value) => setTabIndex(value ?? 0)}>
          <TabList sx={{ flexWrap: 'wrap' }}>
            <Tab><Stethoscope size={16} style={{ marginRight: 6 }} />{isWardRound ? 'Évolution' : 'Examen clinique'}</Tab>
            <Tab><Activity size={16} style={{ marginRight: 6 }} />Constantes</Tab>
            <Tab><UserRound size={16} style={{ marginRight: 6 }} />Antécédents</Tab>
            <Tab><HeartPulse size={16} style={{ marginRight: 6 }} />{isBedside ? 'Diagnostics du séjour' : 'Diagnostics'}</Tab>
            <Tab><Pill size={16} style={{ marginRight: 6 }} />Prescriptions</Tab>
            <Tab><FlaskConical size={16} style={{ marginRight: 6 }} />Examens</Tab>
            <Tab><Syringe size={16} style={{ marginRight: 6 }} />Actes</Tab>
            <Tab><ClipboardList size={16} style={{ marginRight: 6 }} />Nursing</Tab>
          </TabList>

          <TabPanel value={0} sx={{ p: 0, pt: 2 }}>
            <ClinicalExamTab
              motif={clinicalForm.motif}
              setMotif={(value) => updateClinicalField('motif', value)}
              histoireMaladie={clinicalForm.histoireMaladie}
              setHistoireMaladie={(value) => updateClinicalField('histoireMaladie', value)}
              physicalExam={clinicalForm.physicalExam}
              setPhysicalExam={(value) => updateClinicalField('physicalExam', value)}
              physicalExamText={clinicalForm.physicalExamText}
              setPhysicalExamText={(value) => updateClinicalField('physicalExamText', value)}
              complementAnamnese={clinicalForm.complementAnamnese}
              setComplementAnamnese={(value) => updateClinicalField('complementAnamnese', value)}
              conduireATenir={clinicalForm.conduireATenir}
              setConduireATenir={(value) => updateClinicalField('conduireATenir', value)}
              onPrint={handlePrint}
              readOnly={readOnly}
              patientGender={consultation.patientSexe}
              typeConsultation={consultation.typeConsultation}
              isBedside={isWardRound}
              header={isBedside && canReadDiagnostic ? (
                <StayDiagnosticsCard
                  key={stayDiagnosticsTick}
                  consultationId={consultation.id}
                  onOpenDiagnostics={() => setTabIndex(3)}
                />
              ) : null}
            />
          </TabPanel>

          <TabPanel value={1} sx={{ p: 0, pt: 2 }}>
            <ConsultationVitalsTab
              consultationId={consultation.id}
              readOnly={readOnly}
              canAdd={canUpdate}
            />
          </TabPanel>

          <TabPanel value={2} sx={{ p: 0, pt: 2 }}>
            <AntecedentsTab
              consultationId={consultation.id}
              patientId={consultation.patientId}
              readOnly={readOnly}
              canCreate={canUpdate}
              canDelete={canUpdate}
            />
          </TabPanel>

          <TabPanel value={3} sx={{ p: 0, pt: 2 }}>
            {canReadDiagnostic ? (
              <DiagnosticsTab
                consultationId={consultation.id}
                patientId={consultation.patientId}
                readOnly={isConsultationLocked}
                canCreate={canCreateDiagnostic}
                canDelete={canDeleteDiagnostic}
                stayScope={isBedside}
                onRecordsChange={() => setStayDiagnosticsTick((current) => current + 1)}
              />
            ) : (
              <ConsultationPlaceholderTab title="Permission insuffisante pour consulter les diagnostics." />
            )}
          </TabPanel>

          <TabPanel value={4} sx={{ p: 0, pt: 2 }}>
            <ConsultationPlaceholderTab title="Prescriptions — Module à venir" />
          </TabPanel>

          <TabPanel value={5} sx={{ p: 0, pt: 2 }}>
            {canReadDemandeExamen ? (
              <DemandesExamenTab
                consultationId={consultation.id}
                patientId={consultation.patientId}
                readOnly={isConsultationLocked}
                canCreate={canCreateDemandeExamen}
                canCancel={canCancelDemandeExamen}
                canSaisie={canSaisieDemandeExamen}
                canValidate={canValidateDemandeExamen}
                canCreateDiagnostic={canCreateDiagnostic}
              />
            ) : (
              <ConsultationPlaceholderTab title="Permission insuffisante pour consulter les demandes d'examen." />
            )}
          </TabPanel>

          <TabPanel value={6} sx={{ p: 0, pt: 2 }}>
            <ConsultationPlaceholderTab title="Actes — Module à venir" />
          </TabPanel>

          <TabPanel value={7} sx={{ p: 0, pt: 2 }}>
            <ConsultationPlaceholderTab title="Nursing — Module à venir" />
          </TabPanel>
        </Tabs>
      </Stack>

      <ConsultationCloseModal
        open={closeOpen}
        loading={closeLoading}
        error={closeError}
        alreadyHospitalized={alreadyHospitalized}
        isBedside={isBedside}
        onClose={() => setCloseOpen(false)}
        onSubmit={handleClose}
      />

      <ConsultationHospitalisationOfferModal
        open={hospOfferOpen}
        patientName={consultation.patientName}
        onLater={handleHospitalisationLater}
        onAssignNow={openHospitalisationModal}
      />

      <VisiteHospitalisationModal
        open={hospModalOpen}
        visite={{
          id: consultation.visiteId,
          patientName: consultation.patientName,
          litId: null,
        }}
        blocs={hospMeta.blocs ?? []}
        metaLoading={hospMetaLoading}
        loading={hospLoading}
        error={hospError}
        onClose={() => setHospModalOpen(false)}
        onConfirm={handleHospitalisationConfirm}
      />
    </Box>
  );
}
