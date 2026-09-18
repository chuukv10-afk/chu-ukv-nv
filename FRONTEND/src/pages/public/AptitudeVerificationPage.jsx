import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import {
  Alert, Box, Button, Card, CardContent, Chip, Divider, FormControl, FormLabel, Input, Sheet, Stack, Typography,
} from '@mui/joy';
import { Mail, Search, ShieldCheck } from 'lucide-react';
import logo from '../../assets/img/logo.jpg';
import { verifyAptitudeCertificateApi } from '../../features/public/aptitudeVerificationApi.js';

const CONTACT_EMAIL = 'doc-verification@chu-ukv.cd';

const STATUT_LABELS = {
  SIGNE: 'Signé',
  ANNULE: 'Annulé',
};

function formatDate(value) {
  if (!value) return '—';
  return new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    timeZone: 'Africa/Kinshasa',
  }).format(new Date(value));
}

function sexeLabel(sexe) {
  if (sexe === 'F') return 'Féminin';
  if (sexe === 'M') return 'Masculin';
  return sexe || '—';
}

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

export default function AptitudeVerificationPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const initialNumero = searchParams.get('numero') || '';
  const [numero, setNumero] = useState(initialNumero);
  const [loading, setLoading] = useState(Boolean(initialNumero.trim()));
  const [error, setError] = useState('');
  const [result, setResult] = useState(null);

  const verify = async (value) => {
    const trimmed = String(value || '').trim();
    if (!trimmed) {
      setError('Indiquez le numéro du certificat.');
      setResult(null);
      return;
    }
    setLoading(true);
    setError('');
    try {
      const data = await verifyAptitudeCertificateApi(trimmed);
      setResult(data);
      setSearchParams({ numero: trimmed }, { replace: true });
    } catch (err) {
      setResult(null);
      setError(err.message || 'Aucun certificat officiel ne correspond à ce numéro.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const fromUrl = (searchParams.get('numero') || '').trim();
    if (!fromUrl) {
      return undefined;
    }
    setNumero(fromUrl);
    verify(fromUrl);
    return undefined;
  }, []);

  const statusColor = result?.authentique ? 'success' : (result?.statut === 'ANNULE' || result?.expired ? 'danger' : 'neutral');
  const statusLabel = result?.authentique
    ? 'Document authentique'
    : result?.statut === 'ANNULE'
      ? 'Certificat annulé'
      : result?.expired
        ? 'Validité échue'
        : (STATUT_LABELS[result?.statut] || result?.statut);

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
              Vérification de certificat
            </Typography>
            <Typography level="body-sm" textColor="neutral.500" sx={{ textAlign: 'center' }}>
              Portail public — Certificat d’aptitude physique, Cliniques Universitaires de l’UKV
            </Typography>
          </Stack>

          <Stack
            component="form"
            spacing={1.5}
            onSubmit={(event) => {
              event.preventDefault();
              verify(numero);
            }}
          >
            <FormControl>
              <FormLabel>Numéro du certificat</FormLabel>
              <Input
                value={numero}
                onChange={(event) => setNumero(event.target.value)}
                placeholder="Ex. CAP-2026-00012"
                sx={{ '--Input-minHeight': '44px', fontSize: { xs: '16px', md: '14px' } }}
              />
            </FormControl>
            <Button
              type="submit"
              size="lg"
              loading={loading}
              startDecorator={<Search size={18} />}
            >
              Vérifier
            </Button>
          </Stack>

          {error ? (
            <Alert color="danger" variant="soft" sx={{ mt: 2 }}>{error}</Alert>
          ) : null}

          {result ? (
            <Stack spacing={2} sx={{ mt: 3 }}>
              <Chip
                color={statusColor}
                variant="soft"
                startDecorator={<ShieldCheck size={16} />}
                sx={{ alignSelf: 'flex-start' }}
              >
                {statusLabel}
              </Chip>

              <Row label="Identité" value={[result.fullName, sexeLabel(result.sexe)].filter(Boolean).join(' · ')} />
              <Row label="Motif" value={[result.motifLabel, result.filiere?.libelle].filter(Boolean).join(' — ')} />
              <Row
                label="Verdict"
                value={result.verdict === 'APTE' ? 'APTE' : result.verdict === 'INAPTE' ? 'INAPTE' : (result.verdict || '—')}
              />
              <Row label="Médecin examinateur" value={result.medecinExaminateur} />

              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
                <Box sx={{ flex: 1 }}>
                  <Row label="N° de certificat" value={result.numero} />
                </Box>
                <Box sx={{ flex: 1 }}>
                  <Row label="Valable jusqu’au" value={formatDate(result.valideJusqua)} />
                </Box>
              </Stack>
              {result.signeAt ? <Row label="Date de signature" value={formatDate(result.signeAt)} /> : null}

              <Divider />

              <Stack direction="row" spacing={1} alignItems="flex-start">
                <Mail size={16} style={{ marginTop: 2, flexShrink: 0 }} />
                <Typography level="body-sm" sx={{ color: 'neutral.600' }}>
                  Pour toute vérification d’un document administratif, écrivez à{' '}
                  <Box
                    component="a"
                    href={`mailto:${result.contactEmail || CONTACT_EMAIL}`}
                    sx={{ color: 'primary.600', fontWeight: 700 }}
                  >
                    {result.contactEmail || CONTACT_EMAIL}
                  </Box>
                </Typography>
              </Stack>
            </Stack>
          ) : null}

          {!result && !error ? (
            <Typography level="body-xs" sx={{ color: 'neutral.500', mt: 2, textAlign: 'center' }}>
              Saisissez le numéro figurant sur le certificat, ou scannez le QR code du document.
            </Typography>
          ) : null}
        </CardContent>
      </Card>
    </Sheet>
  );
}
