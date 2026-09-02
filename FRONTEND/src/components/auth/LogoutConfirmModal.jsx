import {
  Box,
  Button,
  Modal,
  ModalDialog,
  Stack,
  Typography,
} from '@mui/joy';
import { LogOut } from 'lucide-react';

export default function LogoutConfirmModal({ open, onClose, onConfirm, loading = false }) {
  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog
        variant="outlined"
        role="alertdialog"
        aria-labelledby="logout-dialog-title"
        aria-describedby="logout-dialog-desc"
        sx={{
          borderRadius: 'xl',
          maxWidth: 400,
          p: 3,
          boxShadow: 'lg',
        }}
      >
        <Stack spacing={2}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Stack
              alignItems="center"
              justifyContent="center"
              sx={{
                width: 44,
                height: 44,
                borderRadius: 'md',
                bgcolor: 'danger.50',
                color: 'danger.500',
                flexShrink: 0,
              }}
            >
              <LogOut size={20} />
            </Stack>
            <Box>
              <Typography id="logout-dialog-title" level="title-lg" sx={{ fontWeight: 700 }}>
                Se déconnecter ?
              </Typography>
              <Typography id="logout-dialog-desc" level="body-sm" sx={{ color: 'neutral.500' }}>
                Vous devrez vous reconnecter pour accéder à l&apos;application.
              </Typography>
            </Box>
          </Stack>

          <Stack direction="row" spacing={1} justifyContent="flex-end">
            <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>
              Annuler
            </Button>
            <Button
              variant="solid"
              color="danger"
              loading={loading}
              onClick={onConfirm}
              startDecorator={<LogOut size={16} />}
            >
              Déconnexion
            </Button>
          </Stack>
        </Stack>
      </ModalDialog>
    </Modal>
  );
}
