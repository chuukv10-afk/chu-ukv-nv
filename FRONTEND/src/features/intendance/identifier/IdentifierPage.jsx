import { useState } from 'react';
import {
  Alert, Box, Button, Card, Chip, Divider, Input, Stack, Typography,
} from '@mui/joy';
import { ScanLine, Search, TriangleAlert } from 'lucide-react';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { BIEN_ETAT_COLORS, BIEN_ETAT_LABELS } from '../biens/bienConstants.js';
import QrScannerPanel from '../biens/components/QrScannerPanel.jsx';
import { fetchBienByCodeApi } from '../biens/biensApi.js';

function formatDate(value) {
  if (!value) return '—';
  const [year, month, day] = String(value).split('-');
  if (!year || !month || !day) return value;
  return `${day}/${month}/${year}`;
}

export default function IntendanceIdentifierPage() {
  const { hasPermission } = usePermissions();
  const { showInfo } = useToast();
  const canSignaler = hasPermission(PERMISSIONS.INTENDANCE.TICKET_CREATE);

  const [code, setCode] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [bien, setBien] = useState(null);

  const lookup = async (raw) => {
    const needle = String(raw ?? '').trim();
    if (!needle) {
      setError('Saisissez ou scannez un code inventaire.');
      return;
    }
    setLoading(true);
    setError('');
    try {
      const found = await fetchBienByCodeApi(needle);
      setBien(found);
      setCode(found.codeInventaire ?? needle);
    } catch (err) {
      setBien(null);
      setError(err.message || 'Aucun bien trouvé pour ce code.');
    } finally {
      setLoading(false);
    }
  };

  const handleSearch = (event) => {
    event.preventDefault();
    lookup(code);
  };

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={2.5} sx={{ maxWidth: 860 }}>
        <Stack direction="row" spacing={1.5} alignItems="center">
          <ScanLine size={24} color={LOTRU_PRIMARY[600]} />
          <Box>
            <Typography level="h2" sx={{ fontWeight: 700 }}>Identifier un équipement</Typography>
            <Typography level="body-md" sx={{ color: 'neutral.500' }}>
              Scannez l’étiquette ou recherchez le code inventaire pour afficher la fiche.
            </Typography>
          </Box>
        </Stack>

        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5 }}>
          <Stack spacing={2}>
            <Box component="form" onSubmit={handleSearch}>
              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
                <Input
                  startDecorator={<Search size={16} />}
                  placeholder="Ex. CHUB-CHIR-EQ-26-1001"
                  value={code}
                  onChange={(e) => setCode(e.target.value.toUpperCase())}
                  sx={{ flex: 1 }}
                />
                <Button type="submit" loading={loading}>Rechercher</Button>
              </Stack>
            </Box>
            <Divider>ou</Divider>
            <QrScannerPanel disabled={loading} onDetected={lookup} />
            {error ? <Alert color="danger" variant="soft">{error}</Alert> : null}
          </Stack>
        </Card>

        {bien ? (
          <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5 }}>
            <Stack spacing={2}>
              <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'flex-start' }} spacing={1.5}>
                <Box>
                  <Typography level="body-xs" sx={{ color: 'neutral.500' }}>Code inventaire</Typography>
                  <Typography level="title-lg" sx={{ fontFamily: 'monospace', fontWeight: 800 }}>
                    {bien.codeInventaire}
                  </Typography>
                  {bien.precision ? <Typography level="body-sm" sx={{ color: 'neutral.600' }}>{bien.precision}</Typography> : null}
                </Box>
                <Chip size="lg" variant="soft" color={BIEN_ETAT_COLORS[bien.etat] ?? 'neutral'}>
                  {bien.etat} · {BIEN_ETAT_LABELS[bien.etat] ?? bien.etat}
                </Chip>
              </Stack>
              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} useFlexGap flexWrap="wrap">
                <Box sx={{ minWidth: 180 }}>
                  <Typography level="body-xs" sx={{ color: 'neutral.500' }}>Type</Typography>
                  <Typography level="body-md">{bien.type?.libelle ?? '—'}</Typography>
                </Box>
                <Box sx={{ minWidth: 140 }}>
                  <Typography level="body-xs" sx={{ color: 'neutral.500' }}>Famille</Typography>
                  <Typography level="body-md">{bien.famille ? `${bien.famille.code} — ${bien.famille.libelle}` : '—'}</Typography>
                </Box>
                <Box sx={{ minWidth: 180 }}>
                  <Typography level="body-xs" sx={{ color: 'neutral.500' }}>Service d’affectation</Typography>
                  <Typography level="body-md">{bien.service ? `${bien.service.code} — ${bien.service.libelle}` : '—'}</Typography>
                </Box>
                <Box sx={{ minWidth: 140 }}>
                  <Typography level="body-xs" sx={{ color: 'neutral.500' }}>Local</Typography>
                  <Typography level="body-md">{bien.local?.libelle ?? '—'}</Typography>
                </Box>
                <Box sx={{ minWidth: 140 }}>
                  <Typography level="body-xs" sx={{ color: 'neutral.500' }}>Date d’acquisition</Typography>
                  <Typography level="body-md">{formatDate(bien.dateAcquisition)}</Typography>
                </Box>
                <Box sx={{ minWidth: 160 }}>
                  <Typography level="body-xs" sx={{ color: 'neutral.500' }}>Marque / modèle</Typography>
                  <Typography level="body-md">{[bien.marque, bien.modele].filter(Boolean).join(' ') || '—'}</Typography>
                </Box>
              </Stack>
              {canSignaler ? (
                <Button
                  variant="outlined"
                  color="warning"
                  startDecorator={<TriangleAlert size={16} />}
                  onClick={() => showInfo('Le signalement d’anomalie sera disponible prochainement.')}
                >
                  Signaler un problème
                </Button>
              ) : null}
            </Stack>
          </Card>
        ) : null}
      </Stack>
    </Box>
  );
}
