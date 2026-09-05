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
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { isWardRoundConsultation } from '../consultations/consultationConstants.js';
import { fetchConsultationApi } from '../consultations/consultationsApi.js';
import EvolutionFicheForm from './EvolutionFicheForm.jsx';
import VitalsFicheForm from './VitalsFicheForm.jsx';
import {
  getTourDeSalleFiche,
  tourDeSalleHubPath,
} from './tourDeSalleConstants.js';

export default function TourDeSalleFichePage() {
  const { id, fiche: ficheSlug } = useParams();
  const navigate = useNavigate();
  const fiche = getTourDeSalleFiche(ficheSlug);
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
        <Button sx={{ mt: 2 }} startDecorator={<ArrowLeft size={16} />} onClick={() => navigate(ROUTES.CLINIQUE.CONSULTATIONS)}>
          Retour aux consultations
        </Button>
      </Box>
    );
  }

  if (!isWardRoundConsultation(consultation)) {
    return <Navigate to={ROUTES.CLINIQUE.CONSULTATIONS_DETAIL.replace(':id', consultation.id)} replace />;
  }

  const Icon = fiche.Icon;
  const hubPath = tourDeSalleHubPath(consultation.id);

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={3}>
        <Breadcrumbs>
          <Link component={RouterLink} to={ROUTES.CLINIQUE.CONSULTATIONS}>Consultations</Link>
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

        {fiche.slug === 'evolution' ? (
          <EvolutionFicheForm
            consultation={consultation}
            onSaved={setConsultation}
          />
        ) : fiche.slug === 'signes-vitaux' ? (
          <VitalsFicheForm consultation={consultation} />
        ) : (
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
        )}
      </Stack>
    </Box>
  );
}
