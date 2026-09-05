import {
  Box, Button, Modal, ModalDialog, Stack, Typography,
} from '@mui/joy';
import { AlertTriangle } from 'lucide-react';

export default function VisiteDeleteModal({
  open, visite, loading = false, error = '', onClose, onConfirm,
}) {
  const label = visite?.patientName
    ? `Visite de ${visite.patientName} (${visite.statut})`
    : 'cette visite';

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog variant="outlined" sx={{ borderRadius: 'xl', maxWidth: 420 }}>
        <Stack spacing={2}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ color: 'danger.500' }}><AlertTriangle size={22} /></Box>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>Supprimer la visite</Typography>
          </Stack>
          <Typography level="body-md">Confirmez-vous la suppression de <strong>{label}</strong> ?</Typography>
          {(visite?.consultationCount ?? 0) > 0 || (visite?.acteFinancierCount ?? 0) > 0 ? (
            <Typography level="body-sm" color="warning">Cette visite possède des consultations ou actes financiers.</Typography>
          ) : null}
          {error ? <Typography level="body-sm" color="danger">{error}</Typography> : null}
          <Stack direction="row" spacing={1.5} justifyContent="flex-end">
            <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>Annuler</Button>
            <Button color="danger" loading={loading} onClick={onConfirm}>Supprimer</Button>
          </Stack>
        </Stack>
      </ModalDialog>
    </Modal>
  );
}
