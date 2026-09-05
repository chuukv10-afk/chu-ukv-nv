import {
  Box, Button, Modal, ModalDialog, Stack, Typography,
} from '@mui/joy';
import { AlertTriangle } from 'lucide-react';

export default function PatientDeleteModal({
  open, patient, loading = false, error = '', onClose, onConfirm,
}) {
  const fullName = patient?.fullName || [patient?.nom, patient?.postNom, patient?.prenom].filter(Boolean).join(' ');

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog variant="outlined" sx={{ borderRadius: 'xl', maxWidth: 420 }}>
        <Stack spacing={2}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ color: 'danger.500' }}><AlertTriangle size={22} /></Box>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>Supprimer le patient</Typography>
          </Stack>
          <Typography level="body-md">
            Confirmez-vous la suppression de <strong>{fullName || 'ce patient'}</strong>
            {patient?.numDossier ? ` (dossier ${patient.numDossier})` : ''} ?
          </Typography>
          {(patient?.visiteCount ?? 0) > 0 || (patient?.antecedentCount ?? 0) > 0 ? (
            <Typography level="body-sm" color="warning">
              Ce patient possède des visites ou antécédents et ne peut pas être supprimé.
            </Typography>
          ) : null}
          {error ? <Typography level="body-sm" color="danger">{error}</Typography> : null}
          <Stack direction="row" spacing={1.5} justifyContent="flex-end">
            <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>Annuler</Button>
            <Button
              color="danger"
              loading={loading}
              disabled={(patient?.visiteCount ?? 0) > 0 || (patient?.antecedentCount ?? 0) > 0}
              onClick={onConfirm}
            >
              Supprimer
            </Button>
          </Stack>
        </Stack>
      </ModalDialog>
    </Modal>
  );
}
