import { useEffect, useState } from 'react';
import { Alert, Button, Typography } from '@mui/joy';
import { RefreshCw } from 'lucide-react';
import { isDesktopApp } from './desktop.js';

export default function PwaUpdateBanner() {
  const [update, setUpdate] = useState(null);

  useEffect(() => {
    if (isDesktopApp() || !window.chuUkvSwUpdate) return undefined;
    return window.chuUkvSwUpdate.subscribe(setUpdate);
  }, []);

  if (!update?.needRefresh) return null;

  return (
    <Alert
      color="primary"
      variant="soft"
      sx={{ borderRadius: 0, py: 1 }}
      endDecorator={(
        <Button
          size="sm"
          startDecorator={<RefreshCw size={14} />}
          onClick={() => update.apply()}
        >
          Actualiser
        </Button>
      )}
    >
      <Typography level="title-sm">Nouvelle version disponible</Typography>
      <Typography level="body-xs">Actualisez pour charger les correctifs (dates, synchro). Aucun exe à copier.</Typography>
    </Alert>
  );
}
