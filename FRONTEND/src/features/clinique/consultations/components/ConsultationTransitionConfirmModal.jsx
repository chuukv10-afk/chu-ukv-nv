import {
  Button, Modal, ModalClose, ModalDialog, Stack, Typography,
} from '@mui/joy';
import { CONSULTATION_STATUT_LABELS } from '../consultationConstants.js';

export default function ConsultationTransitionConfirmModal({
  open,
  statut,
  consultation,
  loading = false,
  onClose,
  onConfirm,
}) {
  const isClose = statut === 'TERMINEE';
  const title = isClose ? 'Clôturer la consultation' : 'Annuler la consultation';
  const message = isClose
    ? 'Confirmez-vous la clôture de cette consultation ? Les champs cliniques ne pourront plus être modifiés.'
    : 'Confirmez-vous l\'annulation de cette consultation ?';

  return (
    <Modal open={open} onClose={loading ? undefined : onClose}>
      <ModalDialog variant="outlined" role="alertdialog" sx={{ maxWidth: 440 }}>
        <ModalClose disabled={loading} />
        <Typography level="h4">{title}</Typography>
        <Typography level="body-sm" sx={{ mt: 1 }}>
          {message}
        </Typography>
        {consultation?.patientName && (
          <Typography level="body-sm" color="neutral">
            Patient : {consultation.patientName}
          </Typography>
        )}
        {consultation?.statut && (
          <Typography level="body-sm" color="neutral">
            Statut actuel : {CONSULTATION_STATUT_LABELS[consultation.statut] ?? consultation.statut}
          </Typography>
        )}
        <Stack direction="row" spacing={1} justifyContent="flex-end" sx={{ mt: 2 }}>
          <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>
            Retour
          </Button>
          <Button
            variant="solid"
            color={isClose ? 'neutral' : 'danger'}
            loading={loading}
            onClick={onConfirm}
          >
            {isClose ? 'Clôturer' : 'Annuler la consultation'}
          </Button>
        </Stack>
      </ModalDialog>
    </Modal>
  );
}
