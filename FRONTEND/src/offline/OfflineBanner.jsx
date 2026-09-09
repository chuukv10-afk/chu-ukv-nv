import { useState } from 'react';
import { Alert, Button, Chip, Stack, Typography } from '@mui/joy';
import { CloudOff, RefreshCw, Wifi } from 'lucide-react';
import { useOffline } from './useOffline.js';
import ConflictPanel from './ConflictPanel.jsx';

export default function OfflineBanner() {
  const { online, serverReachable, syncing, pending, conflicts, lastConflict, syncNow } = useOffline();
  const [conflictsOpen, setConflictsOpen] = useState(false);
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
    ? (typeof window !== 'undefined' && window.electronAPI
      ? 'Poste SQLite actif. Ventes, réceptions, lots et stock restent opérationnels. La synchro partira dès que le serveur répond.'
      : 'Le serveur est injoignable. Lecture du cache local et file d’attente des écritures autorisées.')
    : conflicts > 0
      ? `${conflicts} opération(s) refusée(s). ${lastConflict || 'Le serveur a refusé au moins une écriture.'}`
      : `${pending} opération(s) en attente d’envoi.`;

  return (
    <>
      <Alert
        color={color}
        variant="soft"
        sx={{ borderRadius: 0, py: 1 }}
        startDecorator={reachable ? <Wifi size={16} /> : <CloudOff size={16} />}
        endDecorator={(
          <Stack direction="row" spacing={1} alignItems="center">
            {pending > 0 ? (
              <Chip size="sm" variant="solid" onClick={() => setConflictsOpen(true)} sx={{ cursor: 'pointer' }}>
                {pending} en file
              </Chip>
            ) : null}
            {conflicts > 0 ? (
              <Chip size="sm" color="danger" variant="solid" onClick={() => setConflictsOpen(true)} sx={{ cursor: 'pointer' }}>
                {conflicts} conflit(s)
              </Chip>
            ) : null}
            {(pending > 0 || conflicts > 0) ? (
              <Button size="sm" variant="plain" color={conflicts > 0 ? 'danger' : 'neutral'} onClick={() => setConflictsOpen(true)}>
                Gérer
              </Button>
            ) : null}
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
      <ConflictPanel open={conflictsOpen} onClose={() => setConflictsOpen(false)} />
    </>
  );
}
