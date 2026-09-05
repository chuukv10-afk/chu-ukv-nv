import {
  Box, Button, Modal, ModalDialog, Stack, Typography,
} from '@mui/joy';
import { Ban, CircleCheck } from 'lucide-react';
import { LOTRU_PRIMARY } from '../../../../theme/lotruPalette.js';

const CONFIG = {
  TERMINEE: {
    icon: CircleCheck,
    title: 'Clôturer la visite ?',
    description: 'Le patient sera enregistré comme sorti et la visite passera au statut « Terminée ».',
    confirmLabel: 'Clôturer',
  },
  ANNULEE: {
    icon: Ban,
    title: 'Annuler la visite ?',
    description: 'Cette action est définitive. La visite passera au statut « Annulée ».',
    confirmLabel: 'Annuler la visite',
  },
};

export default function VisiteTransitionConfirmModal({
  open,
  statut,
  visite,
  loading = false,
  onClose,
  onConfirm,
}) {
  if (!statut || !CONFIG[statut]) {
    return null;
  }

  const { icon: Icon, title, description, confirmLabel } = CONFIG[statut];
  const contextLabel = visite?.patientName
    ? `${visite.patientName}${visite?.service?.libelle ? ` — ${visite.service.libelle}` : ''}`
    : visite?.service?.libelle ?? 'cette visite';

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog
        variant="outlined"
        role="alertdialog"
        sx={{ borderRadius: 'xl', maxWidth: 460, p: 0, overflow: 'hidden', boxShadow: 'lg' }}
      >
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider' }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ width: 40, height: 40, borderRadius: 'md', bgcolor: LOTRU_PRIMARY[50], color: LOTRU_PRIMARY[600], display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Icon size={20} />
            </Box>
            <Box>
              <Typography level="title-lg" sx={{ fontWeight: 700 }}>{title}</Typography>
              <Typography level="body-xs" color="neutral">{contextLabel}</Typography>
            </Box>
          </Stack>
        </Box>

        <Box sx={{ p: 3 }}>
          <Stack spacing={2}>
            <Typography level="body-sm" color="neutral">{description}</Typography>
            <Stack direction="row" spacing={1.5} justifyContent="flex-end">
              <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>Retour</Button>
              <Button
                loading={loading}
                onClick={onConfirm}
                color={statut === 'ANNULEE' ? 'danger' : 'primary'}
                variant={statut === 'TERMINEE' ? 'outlined' : 'solid'}
              >
                {confirmLabel}
              </Button>
            </Stack>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
