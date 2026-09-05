import { Box, Button, Modal, ModalDialog, Stack, Typography } from '@mui/joy';
import { BedDouble } from 'lucide-react';
import { LOTRU_PRIMARY } from '../../../../theme/lotruPalette.js';

export default function ConsultationHospitalisationOfferModal({
  open,
  patientName = '',
  onLater,
  onAssignNow,
}) {
  return (
    <Modal open={open} onClose={onLater}>
      <ModalDialog sx={{ borderRadius: 'xl', maxWidth: 480, width: '100%' }}>
        <Stack spacing={2} sx={{ py: 0.5 }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box
              sx={{
                width: 40,
                height: 40,
                borderRadius: 'md',
                bgcolor: LOTRU_PRIMARY[50],
                color: LOTRU_PRIMARY[600],
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
              }}
            >
              <BedDouble size={20} />
            </Box>
            <Box>
              <Typography level="title-lg" sx={{ fontWeight: 700 }}>
                Affecter un lit ?
              </Typography>
              <Typography level="body-xs" color="neutral">
                {patientName || 'Patient'}
              </Typography>
            </Box>
          </Stack>

          <Typography level="body-sm" sx={{ color: 'neutral.600' }}>
            La consultation est clôturée. L&apos;hospitalisation est notée.
            Vous pouvez affecter un lit maintenant, ou le faire plus tard
            depuis Visites ou le dossier patient.
          </Typography>

          <Stack direction="row" spacing={1.5} justifyContent="flex-end">
            <Button variant="soft" color="neutral" onClick={onLater}>
              Plus tard
            </Button>
            <Button onClick={onAssignNow}>Affecter un lit maintenant</Button>
          </Stack>
        </Stack>
      </ModalDialog>
    </Modal>
  );
}
