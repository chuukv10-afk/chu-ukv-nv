import { useCallback, useEffect, useState } from 'react';
import { Link as RouterLink, Navigate, useNavigate, useParams } from 'react-router-dom';
import {
  Box,
  Breadcrumbs,
  Button,
  Link,
  Stack,
  Typography,
} from '@mui/joy';
import { ArrowLeft, Construction } from 'lucide-react';
import LoadingSpinner from '../../../components/ui/LoadingSpinner.jsx';
import { ROUTES } from '../../../constants/routes.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { getRecordLockReason, isWardRoundConsultation } from '../consultations/consultationConstants.js';
import { fetchConsultationApi } from '../consultations/consultationsApi.js';
import ConsultationPlaceholderTab from '../consultations/components/ConsultationPlaceholderTab.jsx';
import DemandesExamenTab from '../demandes-examen/DemandesExamenTab.jsx';
import EvolutionFicheForm from './EvolutionFicheForm.jsx';
import VitalsFicheForm from './VitalsFicheForm.jsx';
import {
  getTourDeSalleFiche,
  tourDeSalleHubPath,
} from './tourDeSalleConstants.js';
import { buildPatientDpiPath } from '../../patient/patients/patientDpiTabs.js';

export default function TourDeSalleFichePage() {
  const { id, fiche: ficheSlug } = useParams();
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const fiche = getTourDeSalleFiche(ficheSlug);

  const canReadDemandeExamen = hasPermission(PERMISSIONS.CLINIQUE.DEMANDE_EXAMEN_READ);
  const canCreateDemandeExamen = hasPermission(PERMISSIONS.CLINIQUE.DEMANDE_EXAMEN_CREATE);
  const canCancelDemandeExamen = hasPermission(PERMISSIONS.CLINIQUE.DEMANDE_EXAMEN_CANCEL);
  const canSaisieDemandeExamen = hasPermission(PERMISSIONS.CLINIQUE.DEMANDE_EXAMEN_SAISIE_RESULTAT);
  const canValidateDemandeExamen = hasPermission(PERMISSIONS.CLINIQUE.DEMANDE_EXAMEN_VALIDATE);
  const canCreateDiagnostic = hasPermission(PERMISSIONS.CLINIQUE.DIAGNOSTIC_CREATE);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [consultation, setConsultation] = useState(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const detail = await fetchConsultationApi(id);
      setConsultation(detail);
    } catch (err) {
      setError(err.message || 'Impossible de charger le tour de salle.');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    load();
  }, [load]);

  if (!fiche) {
    return <Navigate to={tourDeSalleHubPath(id)} replace />;
  }

  if (loading) {
    return <LoadingSpinner fullScreen message="Chargement de la fiche..." />;
  }

  if (error || !consultation) {
    return (
      <Box sx={{ p: 3 }}>
        <Typography level="body-md" color="danger">{error || 'Tour de salle introuvable.'}</Typography>
        <Button sx={{ mt: 2 }} startDecorator={<ArrowLeft size={16} />} onClick={() => navigate(ROUTES.PATIENT.LIST)}>
          Retour
        </Button>
      </Box>
    );
  }

  if (!isWardRoundConsultation(consultation)) {
    return <Navigate to={ROUTES.CLINIQUE.CONSULTATIONS_DETAIL.replace(':id', consultation.id)} replace />;
  }

  const Icon = fiche.Icon;
  const hubPath = tourDeSalleHubPath(consultation.id);
  const recordLockReason = getRecordLockReason(consultation);
  const isConsultationLocked = Boolean(
    consultation.isClosed
    || consultation.isEditable === false
    || consultation.recordWritable === false
    || recordLockReason,
  );

  const renderFicheContent = () => {
    if (fiche.slug === 'evolution') {
      return (
        <EvolutionFicheForm
          consultation={consultation}
          onSaved={setConsultation}
        />
      );
    }

    if (fiche.slug === 'signes-vitaux') {
      return <VitalsFicheForm consultation={consultation} />;
    }

    if (fiche.slug === 'examens') {
      if (!canReadDemandeExamen) {
        return (
          <ConsultationPlaceholderTab title="Permission insuffisante pour consulter les demandes d'examen." />
        );
      }

      return (
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
      );
    }

    return (
      <Box
        sx={{
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
          py: 8,
          px: 2,
          textAlign: 'center',
          border: '1px dashed',
          borderColor: 'neutral.outlinedBorder',
          borderRadius: 'lg',
          bgcolor: 'background.surface',
        }}
      >
        <Box
          sx={{
            width: 56,
            height: 56,
            borderRadius: 'md',
            bgcolor: LOTRU_PRIMARY[50],
            color: LOTRU_PRIMARY[600],
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            mb: 2,
          }}
        >
          <Construction size={28} />
        </Box>
        <Typography level="title-md" sx={{ fontWeight: 700, color: LOTRU_NEUTRAL[900], mb: 0.5 }}>
          {fiche.title} — à venir
        </Typography>
        <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600], maxWidth: 420 }}>
          Cette fiche s’ouvrira ici. Le contenu sera branché dans une prochaine étape.
        </Typography>
      </Box>
    );
  };

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={3}>
        <Breadcrumbs>
          <Link component={RouterLink} to={ROUTES.PATIENT.LIST}>Patients</Link>
          {consultation.patientId ? (
            <Link component={RouterLink} to={buildPatientDpiPath(consultation.patientId, 'consultations')}>
              {consultation.patientName ?? consultation.numDossier ?? 'Dossier'}
            </Link>
          ) : null}
          <Link component={RouterLink} to={hubPath}>Tour de salle</Link>
          <Typography>{fiche.title}</Typography>
        </Breadcrumbs>

        <Stack
          direction={{ xs: 'column', sm: 'row' }}
          justifyContent="space-between"
          alignItems={{ xs: 'stretch', sm: 'center' }}
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
              <Icon size={22} />
            </Box>
            <Box>
              <Typography level="h3" sx={{ fontWeight: 700, color: LOTRU_NEUTRAL[900] }}>
                {fiche.title}
              </Typography>
              <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600] }}>
                {consultation.patientName ?? 'Patient'}
                {consultation.numDossier ? ` · ${consultation.numDossier}` : ''}
              </Typography>
            </Box>
          </Stack>

          <Button
            variant="outlined"
            color="neutral"
            startDecorator={<ArrowLeft size={16} />}
            onClick={() => navigate(hubPath)}
          >
            Retour aux fiches
          </Button>
        </Stack>

        {renderFicheContent()}
      </Stack>
    </Box>
  );
}
