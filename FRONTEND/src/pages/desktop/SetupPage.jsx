import { useState } from 'react';
import {
  Button,
  Card,
  CardContent,
  FormControl,
  FormLabel,
  Input,
  Sheet,
  Stack,
  Typography,
} from '@mui/joy';
import { Database } from 'lucide-react';

const DEFAULT_API = 'http://54.155.99.199';

export default function SetupPage({ onDone }) {
  const [siteName, setSiteName] = useState('Pharmacie CHU UKV');
  const [siteCode, setSiteCode] = useState('UKV');
  const [apiBaseUrl, setApiBaseUrl] = useState(DEFAULT_API);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError('');
    if (!siteName.trim() || !apiBaseUrl.trim()) {
      setError('Nom du poste et URL du serveur sont obligatoires.');
      return;
    }
    try {
      setLoading(true);
      await window.electronAPI.setupApp({
        siteName: siteName.trim(),
        siteCode: siteCode.trim() || 'UKV',
        apiBaseUrl: apiBaseUrl.trim().replace(/\/$/, ''),
      });
      onDone?.();
    } catch (err) {
      setError(err?.message || 'Impossible de créer la base SQLite locale.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Sheet
      sx={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        p: 2,
        background: 'background.level2',
      }}
    >
      <Card variant="outlined" sx={{ width: '100%', maxWidth: 480, borderRadius: 'lg' }}>
        <CardContent>
          <Stack spacing={2} component="form" onSubmit={handleSubmit}>
            <Stack direction="row" spacing={1.5} alignItems="center">
              <Database size={22} />
              <BoxTitle />
            </Stack>
            <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
              Premier lancement : une base SQLite est créée sur ce poste. La pharmacie fonctionne
              ensuite comme en ligne, même sans réseau. La synchronisation vers Symfony se fait
              dès que le serveur répond.
            </Typography>
            <FormControl required>
              <FormLabel>Nom du poste</FormLabel>
              <Input value={siteName} onChange={(event) => setSiteName(event.target.value)} />
            </FormControl>
            <FormControl>
              <FormLabel>Code site</FormLabel>
              <Input value={siteCode} onChange={(event) => setSiteCode(event.target.value)} />
            </FormControl>
            <FormControl required>
              <FormLabel>URL du serveur CHU UKV</FormLabel>
              <Input
                value={apiBaseUrl}
                onChange={(event) => setApiBaseUrl(event.target.value)}
                placeholder={DEFAULT_API}
              />
            </FormControl>
            {error ? (
              <Typography color="danger" level="body-sm">{error}</Typography>
            ) : null}
            <Button type="submit" loading={loading}>
              Créer la base locale
            </Button>
          </Stack>
        </CardContent>
      </Card>
    </Sheet>
  );
}

function BoxTitle() {
  return (
    <div>
      <Typography level="title-lg">CHU UKV — Poste pharmacie</Typography>
      <Typography level="body-xs" sx={{ color: 'neutral.500' }}>Configuration SQLite</Typography>
    </div>
  );
}
