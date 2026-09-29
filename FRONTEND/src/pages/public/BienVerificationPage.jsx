import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import {
  Alert, Box, Button, Card, CardContent, Chip, FormControl, FormLabel, Input, Sheet, Stack, Typography,
} from '@mui/joy';
import { Search, ShieldCheck } from 'lucide-react';
import logo from '../../assets/img/logo.jpg';
import { BIEN_ETAT_COLORS, BIEN_ETAT_LABELS } from '../../features/intendance/biens/bienConstants.js';
import { verifyBienApi } from '../../features/public/bienVerificationApi.js';

function Row({ label, value }) {
  return (
    <Stack spacing={0.25}>
      <Typography level="body-xs" sx={{ color: 'neutral.500', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
        {label}
      </Typography>
      <Typography level="title-sm" sx={{ fontWeight: 700 }}>{value || '—'}</Typography>
    </Stack>
  );
}

export default function BienVerificationPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const initialCode = searchParams.get('code') || '';
  const [code, setCode] = useState(initialCode);
  const [loading, setLoading] = useState(Boolean(initialCode.trim()));
  const [error, setError] = useState('');
  const [result, setResult] = useState(null);

  const verify = async (value) => {
    const trimmed = String(value || '').trim();
    if (!trimmed) {
      setError('Indiquez le code inventaire.');
      setResult(null);
      return;
    }
    setLoading(true);
    setError('');
    try {
      const data = await verifyBienApi(trimmed);
      setResult(data);
      setSearchParams({ code: trimmed }, { replace: true });
    } catch (err) {
      setResult(null);
      setError(err.message || 'Aucun équipement ne correspond à ce code.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const fromUrl = (searchParams.get('code') || '').trim();
    if (!fromUrl) {
      return undefined;
    }
    setCode(fromUrl);
    verify(fromUrl);
    return undefined;
    // Le QR ouvre la page avec le code déjà dans l’adresse.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const etatLabel = result ? (BIEN_ETAT_LABELS[result.etat] || result.etat) : '';
  const etatColor = result ? (BIEN_ETAT_COLORS[result.etat] || 'neutral') : 'neutral';

  return (
    <Sheet
      sx={{
        minHeight: '100vh',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        background: 'background.level2',
        p: { xs: 2, md: 3 },
      }}
    >
      <Card
        variant="plain"
        sx={{
          width: '100%',
          maxWidth: 560,
          borderRadius: 'xl',
          bgcolor: 'background.surface',
          boxShadow: 'md',
          my: { xs: 2, md: 5 },
        }}
      >
        <CardContent sx={{ p: { xs: 2.5, md: 3.5 } }}>
          <Stack alignItems="center" spacing={1} sx={{ mb: 3 }}>
            <Box
              component="img"
              src={logo}
              alt="CHU UKV"
              sx={{ width: 72, height: 72, objectFit: 'contain', borderRadius: 'sm' }}
            />
            <Typography level="h3" fontWeight="xl" sx={{ textAlign: 'center' }}>
              Vérification d’équipement
            </Typography>
            <Typography level="body-sm" textColor="neutral.500" sx={{ textAlign: 'center' }}>
              Portail public — Parc de l’intendance, Cliniques Universitaires de l’UKV
            </Typography>
          </Stack>

          <Stack
            component="form"
            spacing={1.5}
            onSubmit={(event) => {
              event.preventDefault();
              verify(code);
            }}
          >
            <FormControl>
              <FormLabel>Code inventaire</FormLabel>
              <Input
                value={code}
                onChange={(event) => setCode(event.target.value.toUpperCase())}
                placeholder="Ex. CHUB-PHARMO-2026-001-001"
                sx={{ '--Input-minHeight': '44px', fontSize: { xs: '16px', md: '14px' } }}
              />
            </FormControl>
            <Button type="submit" size="lg" loading={loading} startDecorator={<Search size={18} />}>
              Vérifier
            </Button>
          </Stack>

          {error ? <Alert color="danger" variant="soft" sx={{ mt: 2 }}>{error}</Alert> : null}

          {result ? (
            <Stack spacing={2} sx={{ mt: 3 }}>
              <Chip
                color={result.enregistre ? 'success' : 'neutral'}
                variant="soft"
                startDecorator={<ShieldCheck size={16} />}
                sx={{ alignSelf: 'flex-start' }}
              >
                Équipement enregistré
              </Chip>
              <Row label="Nom" value={result.nom} />
              <Row label="Code" value={result.codeInventaire} />
              <Row label="Famille" value={result.famille} />
              <Row label="Service" value={result.service} />
              <Row label="Local" value={result.local} />
              <Chip color={etatColor} variant="soft" sx={{ alignSelf: 'flex-start' }}>{etatLabel}</Chip>
              <Row label="Marque" value={[result.marque, result.modele].filter(Boolean).join(' · ')} />
              <Row label="N° de série" value={result.numeroSerie} />
            </Stack>
          ) : null}

          {!result && !error ? (
            <Typography level="body-xs" sx={{ color: 'neutral.500', mt: 2, textAlign: 'center' }}>
              Scannez le QR code de l’étiquette, ou saisissez le code inventaire.
            </Typography>
          ) : null}
        </CardContent>
      </Card>
    </Sheet>
  );
}
