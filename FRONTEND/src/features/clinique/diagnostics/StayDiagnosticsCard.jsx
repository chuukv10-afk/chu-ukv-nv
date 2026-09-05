import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Box,
  Button,
  Card,
  Chip,
  CircularProgress,
  Divider,
  Stack,
  Typography,
} from '@mui/joy';
import { HeartPulse } from 'lucide-react';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { CONSULTATION_TYPE_LABELS } from '../consultations/consultationConstants.js';
import {
  DIAGNOSTIC_CERTITUDE_COLORS,
  DIAGNOSTIC_CERTITUDE_LABELS,
  DIAGNOSTIC_TYPE_COLORS,
  DIAGNOSTIC_TYPE_LABELS,
} from './diagnosticConstants.js';
import { fetchConsultationDiagnosticsApi } from './diagnosticsApi.js';

function latestByMaladie(records) {
  const seen = new Map();
  records.forEach((record) => {
    const key = record.maladie?.id ?? record.id;
    if (!seen.has(key)) {
      seen.set(key, record);
    }
  });
  return Array.from(seen.values());
}

export default function StayDiagnosticsCard({
  consultationId,
  onOpenDiagnostics,
}) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [records, setRecords] = useState([]);

  const load = useCallback(async () => {
    if (!consultationId) return;

    setLoading(true);
    setError('');
    try {
      const result = await fetchConsultationDiagnosticsApi(consultationId, {
        page: 1,
        limit: 200,
        scope: 'visite',
      });
      setRecords(Array.isArray(result.items) ? result.items : []);
    } catch (err) {
      setError(err.message || 'Impossible de charger les diagnostics du séjour.');
      setRecords([]);
    } finally {
      setLoading(false);
    }
  }, [consultationId]);

  useEffect(() => {
    load();
  }, [load]);

  const latest = useMemo(() => latestByMaladie(records), [records]);

  return (
    <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5, bgcolor: LOTRU_PRIMARY[50] }}>
      <Stack spacing={1.5}>
        <Stack
          direction={{ xs: 'column', sm: 'row' }}
          justifyContent="space-between"
          alignItems={{ xs: 'stretch', sm: 'center' }}
          spacing={1}
        >
          <Stack direction="row" spacing={1} alignItems="center">
            <HeartPulse size={18} color={LOTRU_PRIMARY[700]} />
            <Box>
              <Typography level="title-sm" sx={{ fontWeight: 700, color: LOTRU_NEUTRAL[900] }}>
                Diagnostics du séjour
              </Typography>
              <Typography level="body-xs" sx={{ color: LOTRU_NEUTRAL[600] }}>
                Reprise des diagnostics déjà posés depuis l&apos;admission.
              </Typography>
            </Box>
          </Stack>
          {onOpenDiagnostics ? (
            <Button size="sm" variant="soft" color="primary" onClick={onOpenDiagnostics}>
              Voir / ajouter
            </Button>
          ) : null}
        </Stack>

        <Divider />

        {loading ? (
          <Box sx={{ display: 'flex', justifyContent: 'center', py: 1.5 }}>
            <CircularProgress size="sm" />
          </Box>
        ) : error ? (
          <Typography level="body-sm" color="danger">{error}</Typography>
        ) : latest.length === 0 ? (
          <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600] }}>
            Aucun diagnostic sur ce séjour. Posez-en un depuis l&apos;onglet Diagnostics si l&apos;état le justifie.
          </Typography>
        ) : (
          <Stack spacing={1}>
            {latest.map((record) => (
              <Stack
                key={record.id}
                direction={{ xs: 'column', md: 'row' }}
                spacing={1}
                alignItems={{ xs: 'flex-start', md: 'center' }}
                justifyContent="space-between"
              >
                <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
                  <Chip size="sm" variant="outlined" color="primary">
                    {record.maladie?.codeCim10 ?? '—'}
                  </Chip>
                  <Typography level="body-sm" sx={{ fontWeight: 600 }}>
                    {record.maladie?.libelle ?? '—'}
                  </Typography>
                  <Chip size="sm" variant="soft" color={DIAGNOSTIC_TYPE_COLORS[record.type] ?? 'neutral'}>
                    {DIAGNOSTIC_TYPE_LABELS[record.type] ?? record.type}
                  </Chip>
                  <Chip size="sm" variant="soft" color={DIAGNOSTIC_CERTITUDE_COLORS[record.certitude] ?? 'neutral'}>
                    {DIAGNOSTIC_CERTITUDE_LABELS[record.certitude] ?? record.certitude}
                  </Chip>
                  {record.fromCurrentConsultation ? (
                    <Chip size="sm" variant="solid" color="warning">Ce tour</Chip>
                  ) : (
                    <Chip size="sm" variant="soft" color="neutral">
                      {CONSULTATION_TYPE_LABELS[record.consultation?.typeConsultation]
                        ?? 'Séjour'}
                    </Chip>
                  )}
                </Stack>
              </Stack>
            ))}
          </Stack>
        )}
      </Stack>
    </Card>
  );
}
