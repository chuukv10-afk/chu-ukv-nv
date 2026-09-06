import {
  Box, Button, Modal, ModalDialog, Stack, Typography,
} from '@mui/joy';
import { AlertTriangle } from 'lucide-react';

export default function ConfirmModal({
  open,
  title = 'Confirmer',
  message = '',
  confirmLabel = 'Confirmer',
  cancelLabel = 'Annuler',
  color = 'danger',
  loading = false,
  onClose,
  onConfirm,
}) {
  return (
    <Modal open={open} onClose={loading ? undefined : onClose}>
      <ModalDialog
        variant="outlined"
        role="alertdialog"
        sx={{ borderRadius: 'xl', maxWidth: 440, width: '100%', p: 3, boxShadow: 'lg' }}
      >
        <Stack spacing={2}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box
              sx={{
                width: 44,
                height: 44,
                borderRadius: 'md',
                bgcolor: `${color}.50`,
                color: `${color}.500`,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                flexShrink: 0,
              }}
            >
              <AlertTriangle size={20} />
            </Box>
            <Box>
              <Typography level="title-lg" sx={{ fontWeight: 700 }}>{title}</Typography>
              {message ? (
                <Typography level="body-sm" sx={{ color: 'neutral.600', mt: 0.5 }}>
                  {message}
                </Typography>
              ) : null}
            </Box>
          </Stack>
          <Stack direction="row" spacing={1} justifyContent="flex-end">
            <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>
              {cancelLabel}
            </Button>
            <Button color={color} loading={loading} onClick={onConfirm}>
              {confirmLabel}
            </Button>
          </Stack>
        </Stack>
      </ModalDialog>
    </Modal>
  );
}
