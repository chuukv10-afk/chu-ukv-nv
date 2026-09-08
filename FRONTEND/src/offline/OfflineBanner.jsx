import { Alert, Button, Chip, Stack, Typography } from '@mui/joy';
import { CloudOff, RefreshCw, Wifi } from 'lucide-react';
import { useOffline } from './useOffline.js';

export default function OfflineBanner() {
  const { online, serverReachable, syncing, pending, conflicts, lastConflict, syncNow } = useOffline();
  const reachable = online && serverReachable;

  if (reachable && pending === 0 && conflicts === 0 && !syncing) {
    return null;
  }

  const color = !reachable ? 'warning' : conflicts > 0 ? 'danger' : 'primary';
  const title = !reachable
    ? 'Mode hors-ligne'
    : syncing
      ? 'Synchronisation en cours'
      : conflicts > 0
        ? 'Conflits de synchronisation'
        : 'En attente de synchronisation';

  const message = !reachable
    ? 'Le serveur est injoignable. Lecture du cache local et file d’attente des écritures autorisées.'
    : conflicts > 0
      ? `${conflicts} opération(s) à renvoyer. ${lastConflict || 'Le serveur a refusé au moins une écriture.'} Cliquez sur Synchroniser pour réessayer.`
      : `${pending} opération(s) en attente d’envoi.`;

  return (
    <Alert
      color={color}
      variant="soft"
      sx={{ borderRadius: 0, py: 1 }}
      startDecorator={reachable ? <Wifi size={16} /> : <CloudOff size={16} />}
      endDecorator={(
        <Stack direction="row" spacing={1} alignItems="center">
          {pending > 0 ? <Chip size="sm" variant="solid">{pending} en file</Chip> : null}
          {conflicts > 0 ? <Chip size="sm" color="danger" variant="solid">{conflicts} conflit(s)</Chip> : null}
          <Button
            size="sm"
            variant="outlined"
            color={color}
            startDecorator={<RefreshCw size={14} />}
            loading={syncing}
            disabled={!reachable}
            onClick={() => syncNow()}
          >
            Synchroniser
          </Button>
        </Stack>
      )}
    >
      <Typography level="title-sm">{title}</Typography>
      <Typography level="body-xs">{message}</Typography>
    </Alert>
  );
}
