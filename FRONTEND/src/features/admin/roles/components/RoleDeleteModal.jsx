import {
  Box,
  Button,
  Modal,
  ModalDialog,
  Stack,
  Typography,
} from '@mui/joy';
import { Trash2 } from 'lucide-react';

export default function RoleDeleteModal({
  open,
  role,
  loading = false,
  error = '',
  onClose,
  onConfirm,
}) {
  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog
        variant="outlined"
        role="alertdialog"
        sx={{ borderRadius: 'xl', maxWidth: 420, p: 3, boxShadow: 'lg' }}
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
                Supprimer ce rôle ?
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
              Cette action est définitive. Le rôle ne doit plus être affecté à du personnel.
            </Typography>
          )}

          <Stack direction="row" spacing={1} justifyContent="flex-end">
            <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>
              Annuler
            </Button>
            <Button variant="solid" color="danger" loading={loading} onClick={onConfirm}>
              Supprimer
            </Button>
          </Stack>
        </Stack>
      </ModalDialog>
    </Modal>
  );
}
