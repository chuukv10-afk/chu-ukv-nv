import {
  Box, Button, Modal, ModalDialog, Stack, Typography,
} from '@mui/joy';
import { LogOut } from 'lucide-react';
import { LOTRU_PRIMARY } from '../../../../theme/lotruPalette.js';

export default function HospitalisationDischargeModal({
  open,
  visite,
  loading = false,
  onClose,
  onConfirm,
}) {
  const contextLabel = visite?.patientName
    ? `${visite.patientName}${visite?.service?.libelle ? ` — ${visite.service.libelle}` : ''}`
    : visite?.service?.libelle ?? 'ce séjour';

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog
        variant="outlined"
        role="alertdialog"
        sx={{ borderRadius: 'xl', maxWidth: 480, p: 0, overflow: 'hidden', boxShadow: 'lg' }}
      >
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider' }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ width: 40, height: 40, borderRadius: 'md', bgcolor: LOTRU_PRIMARY[50], color: LOTRU_PRIMARY[600], display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <LogOut size={20} />
            </Box>
            <Box>
              <Typography level="title-lg" sx={{ fontWeight: 700 }}>Terminer le séjour ?</Typography>
              <Typography level="body-xs" color="neutral">{contextLabel}</Typography>
            </Box>
          </Stack>
        </Box>

        <Box sx={{ p: 3 }}>
          <Stack spacing={2}>
            <Typography level="body-sm" color="neutral">
              Le patient sera enregistré comme sorti et l&apos;hospitalisation passera au statut « Terminée ».
              Aucune consultation ne doit être en cours.
            </Typography>
            <Stack direction="row" spacing={1.5} justifyContent="flex-end">
              <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>Retour</Button>
              <Button loading={loading} onClick={onConfirm} color="primary" variant="solid">
                Terminer le séjour
              </Button>
            </Stack>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
