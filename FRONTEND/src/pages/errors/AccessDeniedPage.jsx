import { Box, Button, Card, Stack, Typography } from '@mui/joy';
import { ShieldOff } from 'lucide-react';
import { Link as RouterLink, useLocation } from 'react-router-dom';
import { ROUTES } from '../../constants/routes.js';
import { buildAccessDeniedMessage, getPermissionLabel, normalizeAccessDeniedMessage } from '../../utils/permissionLabels.js';

export default function AccessDeniedPage() {
  const location = useLocation();
  const permission = location.state?.permission ?? location.state?.requiredPermissions?.[0] ?? null;
  const message = location.state?.message
    ?? normalizeAccessDeniedMessage(location.state?.apiMessage, location.state?.requiredPermissions)
    ?? (permission ? buildAccessDeniedMessage(permission) : 'Accès refusé. Vous n\'avez pas la permission requise pour accéder à cette page.');

  return (
    <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh', p: 3 }}>
      <Card variant="outlined" sx={{ maxWidth: 520, width: '100%', p: { xs: 3, md: 4 }, borderRadius: 'xl' }}>
        <Stack spacing={2.5} alignItems="center" textAlign="center">
          <Box
            sx={{
              width: 64,
              height: 64,
              borderRadius: 'lg',
              bgcolor: 'danger.50',
              color: 'danger.500',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
            }}
          >
            <ShieldOff size={28} />
          </Box>

          <Stack spacing={1}>
            <Typography level="h3" sx={{ fontWeight: 700 }}>Accès refusé</Typography>
            <Typography level="body-md" sx={{ color: 'neutral.600' }}>{message}</Typography>
          </Stack>

          {permission ? (
            <Box sx={{ width: '100%', bgcolor: 'background.level1', borderRadius: 'md', p: 2 }}>
              <Typography level="body-xs" sx={{ color: 'neutral.500', mb: 0.5 }}>Permission manquante</Typography>
              <Typography level="title-sm" sx={{ fontWeight: 600 }}>{getPermissionLabel(permission)}</Typography>
              <Typography level="body-xs" sx={{ color: 'neutral.500', mt: 0.5, fontFamily: 'monospace' }}>{permission}</Typography>
            </Box>
          ) : null}

          <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
            Contactez un administrateur si vous pensez devoir avoir accès à cette fonctionnalité.
          </Typography>

          <Button component={RouterLink} to={ROUTES.DASHBOARD} variant="soft">
            Retour au tableau de bord
          </Button>
        </Stack>
      </Card>
    </Box>
  );
}
