import { useEffect, useState } from 'react';
import {
  Button,
  Chip,
  FormControl,
  FormLabel,
  Input,
  Modal,
  ModalClose,
  ModalDialog,
  Radio,
  RadioGroup,
  Stack,
  Textarea,
  Typography,
} from '@mui/joy';
import { CircleCheck } from 'lucide-react';

const EMPTY_CLOSE_FORM = {
  needsHospitalization: '',
  staysHospitalized: '',
  wantsAppointment: '',
  nextAppointmentAt: '',
  hospitalizationPatientOpinion: '',
  hospitalizationObservation: '',
};

export function buildCloseConsultationPayload(form, {
  alreadyHospitalized = false,
  admittedFollowUp = false,
} = {}) {
  if (admittedFollowUp) {
    return {
      needsHospitalization: false,
      dischargePatient: false,
      wantsAppointment: false,
      nextAppointmentAt: null,
      hospitalizationPatientOpinion: null,
      hospitalizationObservation: null,
    };
  }

  if (alreadyHospitalized) {
    const dischargePatient = form.staysHospitalized === 'no';
    const wantsAppointment = dischargePatient && form.wantsAppointment === 'yes';
    let nextAppointmentAt = null;
    if (wantsAppointment && form.nextAppointmentAt) {
      nextAppointmentAt = new Date(form.nextAppointmentAt).toISOString();
    }

    return {
      needsHospitalization: false,
      dischargePatient,
      wantsAppointment,
      nextAppointmentAt,
      hospitalizationPatientOpinion: null,
      hospitalizationObservation: null,
    };
  }

  const needsHospitalization = form.needsHospitalization === 'yes';
  const wantsAppointment = !needsHospitalization && form.wantsAppointment === 'yes';

  let nextAppointmentAt = null;
  if (wantsAppointment && form.nextAppointmentAt) {
    nextAppointmentAt = new Date(form.nextAppointmentAt).toISOString();
  }

  let hospitalizationPatientOpinion = null;
  let hospitalizationObservation = null;
  if (needsHospitalization) {
    hospitalizationPatientOpinion = form.hospitalizationPatientOpinion === 'non_favorable'
      ? 'NON_FAVORABLE'
      : 'FAVORABLE';
    if (hospitalizationPatientOpinion === 'NON_FAVORABLE') {
      hospitalizationObservation = form.hospitalizationObservation.trim();
    }
  }

  return {
    needsHospitalization,
    dischargePatient: false,
    wantsAppointment,
    nextAppointmentAt,
    hospitalizationPatientOpinion,
    hospitalizationObservation,
  };
}

export function validateCloseForm(form, {
  alreadyHospitalized = false,
  admittedFollowUp = false,
} = {}) {
  if (admittedFollowUp) {
    return '';
  }

  if (alreadyHospitalized) {
    if (!form.staysHospitalized) {
      return 'Indiquez si le malade reste hospitalisé.';
    }
    if (form.staysHospitalized === 'no') {
      if (!form.wantsAppointment) {
        return 'Indiquez si un rendez-vous doit être donné au malade.';
      }
      if (form.wantsAppointment === 'yes' && !form.nextAppointmentAt) {
        return 'La date et l\'heure du rendez-vous sont obligatoires.';
      }
    }
    return '';
  }

  if (!form.needsHospitalization) {
    return 'Indiquez si le malade doit être hospitalisé.';
  }

  if (form.needsHospitalization === 'no') {
    if (!form.wantsAppointment) {
      return 'Indiquez si un rendez-vous doit être donné au malade.';
    }
    if (form.wantsAppointment === 'yes' && !form.nextAppointmentAt) {
      return 'La date et l\'heure du rendez-vous sont obligatoires.';
    }
    return '';
  }

  if (!form.hospitalizationPatientOpinion) {
    return 'L\'avis du malade sur l\'hospitalisation est obligatoire.';
  }

  if (form.hospitalizationPatientOpinion === 'non_favorable' && !form.hospitalizationObservation?.trim()) {
    return 'Une observation est requise lorsque l\'avis est non favorable.';
  }

  return '';
}

export default function ConsultationCloseModal({
  open,
  loading = false,
  error = '',
  alreadyHospitalized = false,
  admittedFollowUp = false,
  isBedside = false,
  onClose,
  onSubmit,
}) {
  const [form, setForm] = useState(EMPTY_CLOSE_FORM);
  const [localError, setLocalError] = useState('');

  useEffect(() => {
    if (open) {
      setForm(EMPTY_CLOSE_FORM);
      setLocalError('');
    }
  }, [open]);

  const updateField = (field, value) => {
    setForm((current) => {
      const next = { ...current, [field]: value };

      if (field === 'needsHospitalization' || field === 'staysHospitalized') {
        next.wantsAppointment = '';
        next.nextAppointmentAt = '';
        next.hospitalizationPatientOpinion = '';
        next.hospitalizationObservation = '';
      }

      if (field === 'wantsAppointment' && value !== 'yes') {
        next.nextAppointmentAt = '';
      }

      if (field === 'hospitalizationPatientOpinion' && value !== 'non_favorable') {
        next.hospitalizationObservation = '';
      }

      return next;
    });
  };

  const handleSubmit = () => {
    const validationError = validateCloseForm(form, { alreadyHospitalized, admittedFollowUp });
    if (validationError) {
      setLocalError(validationError);
      return;
    }
    setLocalError('');
    onSubmit(buildCloseConsultationPayload(form, { alreadyHospitalized, admittedFollowUp }));
  };

  const displayError = localError || error;
  const title = isBedside ? 'Clôturer le tour de salle' : 'Clôturer la consultation';
  const subtitle = admittedFollowUp
    ? 'Le malade est déjà hospitalisé. La consultation sera clôturée sans modifier le séjour.'
    : alreadyHospitalized
    ? 'Le malade est déjà hospitalisé. Indiquez s’il reste au lit ou s’il peut sortir.'
    : 'Renseignez l’orientation du malade avant la clôture de la consultation.';

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog sx={{ borderRadius: 'xl', maxWidth: 560, width: '100%' }}>
        <ModalClose />
        <Stack spacing={2} sx={{ py: 1 }}>
          <Stack spacing={1} alignItems="center">
            <CircleCheck size={48} color="var(--joy-palette-danger-500)" />
            <Typography level="title-lg" sx={{ fontWeight: 700, textAlign: 'center' }}>
              {title}
            </Typography>
            <Typography level="body-sm" sx={{ color: 'neutral.500', textAlign: 'center' }}>
              {subtitle}
            </Typography>
          </Stack>

          {admittedFollowUp ? (
            <Chip size="sm" variant="soft" color="warning">
              Patient hospitalisé — aucune nouvelle demande d&apos;admission n&apos;est nécessaire.
            </Chip>
          ) : alreadyHospitalized ? (
            <>
              <Chip size="sm" variant="soft" color="warning">
                Patient déjà hospitalisé — ce n’est plus une admission.
              </Chip>

              <FormControl>
                <FormLabel>Le malade reste-t-il hospitalisé ?</FormLabel>
                <RadioGroup
                  value={form.staysHospitalized}
                  onChange={(event) => updateField('staysHospitalized', event.target.value)}
                >
                  <Radio value="yes" label="Oui — le tour de salle se termine, le malade reste au lit" />
                  <Radio value="no" label="Non — préparer la sortie" />
                </RadioGroup>
              </FormControl>

              {form.staysHospitalized === 'no' && (
                <>
                  <FormControl>
                    <FormLabel>Faut-il donner un rendez-vous au malade ?</FormLabel>
                    <RadioGroup
                      value={form.wantsAppointment}
                      onChange={(event) => updateField('wantsAppointment', event.target.value)}
                    >
                      <Radio value="no" label="Non" />
                      <Radio value="yes" label="Oui" />
                    </RadioGroup>
                  </FormControl>

                  {form.wantsAppointment === 'yes' && (
                    <FormControl>
                      <FormLabel>Date et heure du rendez-vous</FormLabel>
                      <Input
                        type="datetime-local"
                        value={form.nextAppointmentAt}
                        onChange={(event) => updateField('nextAppointmentAt', event.target.value)}
                      />
                    </FormControl>
                  )}
                </>
              )}
            </>
          ) : (
            <>
              <FormControl>
                <FormLabel>Le malade doit-il être hospitalisé ?</FormLabel>
                <RadioGroup
                  value={form.needsHospitalization}
                  onChange={(event) => updateField('needsHospitalization', event.target.value)}
                >
                  <Radio value="no" label="Non" />
                  <Radio value="yes" label="Oui" />
                </RadioGroup>
              </FormControl>

              {form.needsHospitalization === 'no' && (
                <>
                  <FormControl>
                    <FormLabel>Faut-il donner un rendez-vous au malade ?</FormLabel>
                    <RadioGroup
                      value={form.wantsAppointment}
                      onChange={(event) => updateField('wantsAppointment', event.target.value)}
                    >
                      <Radio value="no" label="Non" />
                      <Radio value="yes" label="Oui" />
                    </RadioGroup>
                  </FormControl>

                  {form.wantsAppointment === 'yes' && (
                    <FormControl>
                      <FormLabel>Date et heure du rendez-vous</FormLabel>
                      <Input
                        type="datetime-local"
                        value={form.nextAppointmentAt}
                        onChange={(event) => updateField('nextAppointmentAt', event.target.value)}
                      />
                    </FormControl>
                  )}
                </>
              )}

              {form.needsHospitalization === 'yes' && (
                <>
                  <FormControl>
                    <FormLabel>Avis du malade sur l&apos;hospitalisation</FormLabel>
                    <RadioGroup
                      value={form.hospitalizationPatientOpinion}
                      onChange={(event) => updateField('hospitalizationPatientOpinion', event.target.value)}
                    >
                      <Radio value="favorable" label="Favorable" />
                      <Radio value="non_favorable" label="Non favorable" />
                    </RadioGroup>
                  </FormControl>

                  {form.hospitalizationPatientOpinion === 'non_favorable' && (
                    <FormControl>
                      <FormLabel>Observations du médecin</FormLabel>
                      <Textarea
                        minRows={3}
                        value={form.hospitalizationObservation}
                        onChange={(event) => updateField('hospitalizationObservation', event.target.value)}
                        placeholder="Observations liées au refus ou aux réserves du malade…"
                      />
                    </FormControl>
                  )}

                  {form.hospitalizationPatientOpinion === 'favorable' && (
                    <Chip size="sm" variant="soft" color="warning">
                      Après clôture, vous pourrez affecter un lit maintenant ou plus tard.
                    </Chip>
                  )}
                </>
              )}
            </>
          )}

          {displayError ? (
            <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
              {displayError}
            </Typography>
          ) : null}

          <Stack direction="row" spacing={1.5} justifyContent="flex-end">
            <Button variant="soft" color="neutral" onClick={onClose}>Annuler</Button>
            <Button color="danger" loading={loading} onClick={handleSubmit}>
              {isBedside ? 'Clôturer le tour' : 'Clôturer'}
            </Button>
          </Stack>
        </Stack>
      </ModalDialog>
    </Modal>
  );
}
