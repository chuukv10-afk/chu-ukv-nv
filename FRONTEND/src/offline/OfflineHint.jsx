import { Alert, Typography } from '@mui/joy';
import { CloudOff } from 'lucide-react';
import { useOffline } from './useOffline.js';

export default function OfflineHint({ children }) {
  const { online, serverReachable } = useOffline();
  if (online && serverReachable) {
    return null;
  }

  return (
    <Alert color="warning" variant="soft" startDecorator={<CloudOff size={16} />}>
      <Typography level="body-sm">
        {children || 'Hors-ligne : l’enregistrement sera mis en file et envoyé au serveur dès la reconnexion.'}
      </Typography>
    </Alert>
  );
}
