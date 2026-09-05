import {
  Box,
  Button,
  Modal,
  ModalDialog,
  Stack,
  Typography,
} from '@mui/joy';
import { Trash2 } from 'lucide-react';

export default function RolePermissionClearModal({
  open,
  assignment,
  loading = false,
  error = '',
  onClose,
  onConfirm,
}) {
  const role = assignment?.role;

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog
        variant="outlined"
        role="alertdialog"
        sx={{ borderRadius: 'xl', maxWidth: 440, p: 3, boxShadow: 'lg' }}
      >
        <Stack spacing={2}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box
              sx={{
                width: 44,
                height: 44,
                borderRadius: 'md',
                bgcolor: 'danger.50',
                color: 'danger.500',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                flexShrink: 0,
              }}
            >
              <Trash2 size={20} />
            </Box>
            <Box>
              <Typography level="title-lg" sx={{ fontWeight: 700 }}>
                Retirer toutes les permissions ?
              </Typography>
              <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
                {role ? `${role.code} — ${role.libelle}` : ''}
              </Typography>
            </Box>
          </Stack>

          {error ? (
            <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
              {error}
            </Typography>
          ) : (
            <Typography level="body-sm" sx={{ color: 'neutral.600' }}>
              {assignment?.permissionsCount ?? 0} permission{(assignment?.permissionsCount ?? 0) > 1 ? 's' : ''} seront retirées de ce rôle.
            </Typography>
          )}

          <Stack direction="row" spacing={1} justifyContent="flex-end">
            <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>
              Annuler
            </Button>
            <Button variant="solid" color="danger" loading={loading} onClick={onConfirm}>
              Tout retirer
            </Button>
          </Stack>
        </Stack>
      </ModalDialog>
    </Modal>
  );
}
