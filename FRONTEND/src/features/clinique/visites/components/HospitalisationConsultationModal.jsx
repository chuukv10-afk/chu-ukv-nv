import { useEffect, useState } from 'react';
import {
  Button, FormControl, FormLabel, Modal, ModalDialog, Radio, RadioGroup, Stack, Typography,
} from '@mui/joy';
import { Stethoscope } from 'lucide-react';

export default function HospitalisationConsultationModal({
  open,
  visite,
  loading = false,
  error = '',
  onClose,
  onSubmit,
}) {
  const [typeConsultation, setTypeConsultation] = useState('AU_LIT');

  useEffect(() => {
    if (open) {
      setTypeConsultation('AU_LIT');
    }
  }, [open]);

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog sx={{ borderRadius: 'xl', maxWidth: 460, width: '100%' }}>
        <Stack spacing={2}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Stethoscope size={22} />
            <BoxTitle patientName={visite?.patientName} />
          </Stack>

          <Typography level="body-sm" sx={{ color: 'neutral.600' }}>
            Choisissez le type d’acte pour cette hospitalisation en cours.
          </Typography>

          <FormControl>
            <FormLabel>Type de consultation</FormLabel>
            <RadioGroup
              value={typeConsultation}
              onChange={(event) => setTypeConsultation(event.target.value)}
            >
              <Radio value="AU_LIT" label="Tour de salle — visite au chevet" />
              <Radio value="NORMALE" label="Consultation externe — acte en salle de consultation" />
            </RadioGroup>
          </FormControl>

          {error ? (
            <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
              {error}
            </Typography>
          ) : null}

          <Stack direction="row" spacing={1.5} justifyContent="flex-end">
            <Button variant="soft" color="neutral" onClick={onClose} disabled={loading}>
              Annuler
            </Button>
            <Button loading={loading} onClick={() => onSubmit(typeConsultation)}>
              Créer la consultation
            </Button>
          </Stack>
        </Stack>
      </ModalDialog>
    </Modal>
  );
}

function BoxTitle({ patientName }) {
  return (
    <div>
      <Typography level="title-lg" sx={{ fontWeight: 700 }}>
        Nouvelle consultation
      </Typography>
      <Typography level="body-xs" color="neutral">
        {patientName || 'Hospitalisation en cours'}
      </Typography>
    </div>
  );
}
