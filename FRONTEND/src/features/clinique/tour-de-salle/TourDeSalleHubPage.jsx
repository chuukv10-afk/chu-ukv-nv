import { useCallback, useEffect, useState } from 'react';
import { Navigate, useNavigate, useParams } from 'react-router-dom';
import { Box, Button, Stack, Typography } from '@mui/joy';
import { ArrowLeft, XCircle } from 'lucide-react';
import LoadingSpinner from '../../../components/ui/LoadingSpinner.jsx';
import { ROUTES } from '../../../constants/routes.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL } from '../../../theme/lotruPalette.js';
import {
  getRecordLockReason,
  isWardRoundConsultation,
} from '../consultations/consultationConstants.js';
import { closeConsultationApi, fetchConsultationApi } from '../consultations/consultationsApi.js';
import ConsultationCloseModal from '../consultations/components/ConsultationCloseModal.jsx';
import { getConsultationBackPath } from '../../patient/patients/patientDpiTabs.js';
import { TOUR_DE_SALLE_FICHES, tourDeSalleFichePath } from './tourDeSalleConstants.js';

function formatTourDate(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return date.toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export default function TourDeSalleHubPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canClose = hasPermission(PERMISSIONS.CLINIQUE.CONSULTATION_CLOSE);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [consultation, setConsultation] = useState(null);
  const [closeOpen, setCloseOpen] = useState(false);
  const [closeLoading, setCloseLoading] = useState(false);
  const [closeError, setCloseError] = useState('');

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

  if (loading) {
    return <LoadingSpinner fullScreen message="Chargement du tour de salle..." />;
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

  const tourDate = formatTourDate(consultation.consultedAt);
  const alreadyHospitalized = Boolean(
    consultation.alreadyHospitalized || consultation.visiteStatut === 'HOSPITALISE',
  );
  const locked = Boolean(
    consultation.isClosed
    || consultation.isEditable === false
    || consultation.recordWritable === false
    || getRecordLockReason(consultation),
  );
  const showClose = !consultation.isClosed && canClose && !locked;
  const backPath = getConsultationBackPath(consultation);

  const handleCloseTour = async (payload) => {
    setCloseLoading(true);
    setCloseError('');
    try {
      const updated = await closeConsultationApi(consultation.id, payload);
      setConsultation(updated);
      setCloseOpen(false);
      showSuccess('Tour de salle clôturé.');
    } catch (err) {
      setCloseError(err.message || 'Clôture impossible.');
      showError(err.message || 'Clôture impossible.');
    } finally {
      setCloseLoading(false);
    }
  };

  return (
    <Box
      sx={{
        minHeight: '70vh',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        px: 2,
        py: 4,
      }}
    >
      <Stack spacing={0.5} sx={{ mb: 4, textAlign: 'center' }}>
        <Typography level="title-lg" sx={{ fontWeight: 700, color: LOTRU_NEUTRAL[900] }}>
          Tour de salle
        </Typography>
        <Typography level="body-sm" sx={{ fontWeight: 600, color: LOTRU_NEUTRAL[800] }}>
          {consultation.patientName ?? 'Patient'}
        </Typography>
        {consultation.numDossier ? (
          <Typography level="body-sm" sx={{ fontFamily: 'monospace', fontWeight: 600, color: LOTRU_NEUTRAL[700] }}>
            {consultation.numDossier}
          </Typography>
        ) : null}
        {tourDate ? (
          <Typography level="body-xs" sx={{ color: LOTRU_NEUTRAL[500] }}>
            {tourDate}
          </Typography>
        ) : null}
        {consultation.isClosed ? (
          <Typography level="body-xs" sx={{ color: LOTRU_NEUTRAL[500], mt: 0.5 }}>
            Tour clôturé
          </Typography>
        ) : null}
      </Stack>

      <Box
        sx={{
          display: 'flex',
          flexWrap: 'wrap',
          justifyContent: 'center',
          gap: 3,
          maxWidth: 720,
        }}
      >
        {TOUR_DE_SALLE_FICHES.map((fiche) => {
          const Icon = fiche.Icon;
          return (
            <Box
              key={fiche.slug}
              role="button"
              tabIndex={0}
              onClick={() => navigate(tourDeSalleFichePath(consultation.id, fiche.slug))}
              onKeyDown={(event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                  event.preventDefault();
                  navigate(tourDeSalleFichePath(consultation.id, fiche.slug));
                }
              }}
              sx={{
                width: 112,
                cursor: 'pointer',
                textAlign: 'center',
                '&:hover .fiche-icon': {
                  bgcolor: `${fiche.color}.softHoverBg`,
                },
              }}
            >
              <Box
                className="fiche-icon"
                sx={{
                  width: 72,
                  height: 72,
                  mx: 'auto',
                  mb: 1,
                  borderRadius: 'lg',
                  bgcolor: `${fiche.color}.softBg`,
                  color: `${fiche.color}.plainColor`,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                }}
              >
                <Icon size={30} />
              </Box>
              <Typography level="body-xs" sx={{ fontWeight: 600, color: LOTRU_NEUTRAL[800], lineHeight: 1.3 }}>
                {fiche.shortTitle}
              </Typography>
            </Box>
          );
        })}
      </Box>

      <Stack direction="row" spacing={1} sx={{ mt: 5 }}>
        <Button
          variant="plain"
          color="neutral"
          size="sm"
          startDecorator={<ArrowLeft size={14} />}
          onClick={() => navigate(backPath)}
        >
          Retour
        </Button>
        {showClose ? (
          <Button
            variant="soft"
            color="danger"
            size="sm"
            startDecorator={<XCircle size={14} />}
            onClick={() => setCloseOpen(true)}
          >
            Clôturer le tour
          </Button>
        ) : null}
      </Stack>

      <ConsultationCloseModal
        open={closeOpen}
        loading={closeLoading}
        error={closeError}
        alreadyHospitalized={alreadyHospitalized}
        isBedside
        onClose={() => setCloseOpen(false)}
        onSubmit={handleCloseTour}
      />
    </Box>
  );
}
