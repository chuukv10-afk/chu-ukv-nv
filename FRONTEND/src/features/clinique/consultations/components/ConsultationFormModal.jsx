import {
  Button, Chip, FormControl, FormLabel, Modal, ModalClose, ModalDialog,
  Option, Select, Stack, Textarea, Typography,
} from '@mui/joy';
import ConsultationTransitionPanel from './ConsultationTransitionPanel.jsx';
import {
  CONSULTATION_EDITABLE_STATUTS,
  CONSULTATION_STATUT_COLORS,
  CONSULTATION_STATUT_LABELS,
  CONSULTATION_TYPE_LABELS,
} from '../consultationConstants.js';

function formatDateTime(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('fr-FR');
}

export default function ConsultationFormModal({
  open,
  mode = 'edit',
  consultation = null,
  formValues,
  meta = {},
  loading = false,
  transitionLoading = false,
  error = '',
  canUpdate = false,
  onClose,
  onChange,
  onSubmit,
  onTransition,
}) {
  const isCreate = mode === 'create';
  const editable = isCreate || CONSULTATION_EDITABLE_STATUTS.includes(consultation?.statut);
  const types = meta.creatableTypes ?? Object.keys(CONSULTATION_TYPE_LABELS);

  return (
    <Modal open={open} onClose={loading ? undefined : onClose}>
      <ModalDialog sx={{ width: 'min(720px, 96vw)', maxHeight: '92vh', overflow: 'auto' }}>
        <ModalClose disabled={loading} />
        <Typography level="h4">
          {isCreate ? 'Nouvelle consultation' : 'Consultation'}
        </Typography>

        {!isCreate && consultation && (
          <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap sx={{ mt: 1 }}>
            <Chip size="sm" variant="soft" color={CONSULTATION_STATUT_COLORS[consultation.statut] ?? 'neutral'}>
              {CONSULTATION_STATUT_LABELS[consultation.statut] ?? consultation.statut}
            </Chip>
            {consultation.patientName && (
              <Typography level="body-sm" color="neutral">Patient : {consultation.patientName}</Typography>
            )}
            {consultation.openedBy?.fullName && (
              <Typography level="body-sm" color="neutral">Médecin : {consultation.openedBy.fullName}</Typography>
            )}
            <Typography level="body-sm" color="neutral">Ouverte le {formatDateTime(consultation.consultedAt)}</Typography>
          </Stack>
        )}

        <Stack spacing={2} sx={{ mt: 2 }}>
          {isCreate && (
            <FormControl required>
              <FormLabel>Visite</FormLabel>
              <Typography level="body-sm">
                Visite n° {formValues.visiteId}
              </Typography>
            </FormControl>
          )}

          <FormControl>
            <FormLabel>Type</FormLabel>
            <Select
              value={formValues.typeConsultation ?? 'NORMALE'}
              onChange={(_, value) => onChange?.({ ...formValues, typeConsultation: value })}
              disabled={!editable || loading}
            >
              {types.map((type) => (
                <Option key={type} value={type}>
                  {CONSULTATION_TYPE_LABELS[type] ?? type}
                </Option>
              ))}
            </Select>
          </FormControl>

          <FormControl>
            <FormLabel>Motif</FormLabel>
            <Textarea
              minRows={2}
              value={formValues.motif ?? ''}
              onChange={(event) => onChange?.({ ...formValues, motif: event.target.value })}
              disabled={!editable || loading}
            />
          </FormControl>

          {!isCreate && (
            <>
              <FormControl>
                <FormLabel>Histoire de la maladie</FormLabel>
                <Textarea
                  minRows={3}
                  value={formValues.histoireMaladie ?? ''}
                  onChange={(event) => onChange?.({ ...formValues, histoireMaladie: event.target.value })}
                  disabled={!editable || loading}
                />
              </FormControl>

              <FormControl>
                <FormLabel>Observation clinique</FormLabel>
                <Textarea
                  minRows={3}
                  value={formValues.consultationObservation ?? ''}
                  onChange={(event) => onChange?.({ ...formValues, consultationObservation: event.target.value })}
                  disabled={!editable || loading}
                />
              </FormControl>

              <FormControl>
                <FormLabel>Conduite à tenir</FormLabel>
                <Textarea
                  minRows={2}
                  value={formValues.conduireATenir ?? ''}
                  onChange={(event) => onChange?.({ ...formValues, conduireATenir: event.target.value })}
                  disabled={!editable || loading}
                />
              </FormControl>
            </>
          )}

          {error && (
            <Typography level="body-sm" color="danger">{error}</Typography>
          )}

          {!isCreate && canUpdate && (
            <ConsultationTransitionPanel
              consultation={consultation}
              canUpdate={canUpdate}
              loading={transitionLoading}
              onTransition={onTransition}
            />
          )}

          <Stack direction="row" spacing={1} justifyContent="flex-end">
            <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>
              Fermer
            </Button>
            {(isCreate || editable) && (
              <Button loading={loading} onClick={onSubmit}>
                {isCreate ? 'Créer' : 'Enregistrer'}
              </Button>
            )}
          </Stack>
        </Stack>
      </ModalDialog>
    </Modal>
  );
}
