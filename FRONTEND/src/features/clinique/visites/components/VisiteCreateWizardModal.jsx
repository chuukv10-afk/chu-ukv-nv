import { useEffect, useMemo, useState } from 'react';
import {
  Box, Button, FormControl, FormHelperText, FormLabel, Input, Modal, ModalDialog, Option, Select, Stack, Step, StepIndicator, Stepper, Textarea, Typography,
} from '@mui/joy';
import { CalendarClock, ChevronLeft, ChevronRight } from 'lucide-react';
import { buildEmptyVisiteCreateForm, PRIORITE_OPTIONS, TYPE_ENTREE_OPTIONS } from '../visiteConstants.js';

const MODAL_SX = {
  borderRadius: 'xl',
  maxWidth: 640,
  width: '100%',
  p: 0,
  overflow: 'hidden',
  boxShadow: 'lg',
  maxHeight: 'min(92vh, 780px)',
  display: 'flex',
  flexDirection: 'column',
};

const STEPS = ['Triage', 'Orientation', 'Confirmation'];

function resolveStatutLabel(typeEntree) {
  return typeEntree === 'RENDEZ_VOUS' ? 'Planifiée' : 'En cours';
}

export default function VisiteCreateWizardModal({
  open,
  initialValues = buildEmptyVisiteCreateForm(),
  createMeta = {},
  loading = false,
  error = '',
  dpiLocked = false,
  onClose,
  onSubmit,
}) {
  const [step, setStep] = useState(0);
  const [form, setForm] = useState(initialValues);

  const signesVitaux = createMeta.signesVitaux ?? [];
  const departements = createMeta.departements ?? [];
  const services = createMeta.services ?? [];
  const typesEntree = (createMeta.typesEntree?.length ? createMeta.typesEntree : TYPE_ENTREE_OPTIONS);
  const prioriteOptions = (createMeta.prioriteOptions?.length ? createMeta.prioriteOptions : PRIORITE_OPTIONS);

  const filteredServices = useMemo(
    () => services.filter((service) => String(service.departementId ?? '') === String(form.departementId ?? '')),
    [services, form.departementId],
  );

  useEffect(() => {
    if (open) {
      setForm(initialValues);
      setStep(0);
    }
  }, [open, initialValues]);

  const handleChange = (field, value) => setForm((current) => ({ ...current, [field]: value }));

  const handleSigneChange = (signeId, value) => {
    setForm((current) => ({
      ...current,
      signesVitaux: { ...current.signesVitaux, [signeId]: value },
    }));
  };

  const validateStep = (targetStep = step) => {
    if (targetStep === 0) {
      if (!form.typeEntree) return 'Le type d\'entrée est obligatoire.';
      if (!form.motif?.trim()) return 'Le motif de venue est obligatoire.';
      for (const signe of signesVitaux) {
        if (signe.obligatoireAuTriage && !form.signesVitaux?.[signe.id]?.trim()) {
          return `Le signe vital « ${signe.libelle} » est obligatoire.`;
        }
      }
    }

    if (targetStep === 1) {
      if (!form.departementId) return 'Le département est obligatoire.';
      if (!form.serviceId) return 'Le service est obligatoire.';
    }

    if (targetStep === 2 && !dpiLocked && !form.dpiId) {
      return 'L\'identifiant du dossier (DPI) est obligatoire.';
    }

    return '';
  };

  const goNext = () => {
    const validationError = validateStep(step);
    if (validationError) return;
    setStep((current) => Math.min(current + 1, STEPS.length - 1));
  };

  const goBack = () => setStep((current) => Math.max(current - 1, 0));

  const handleSubmit = (event) => {
    event.preventDefault();
    const validationError = validateStep(2);
    if (validationError) return;

    const payload = {
      dpiId: Number(form.dpiId),
      serviceId: Number(form.serviceId),
      typeEntree: form.typeEntree,
      motif: form.motif.trim(),
      priorite: form.priorite ? Number(form.priorite) : null,
      signesVitaux: signesVitaux
        .map((signe) => ({
          signeVitalId: signe.id,
          valeur: form.signesVitaux?.[signe.id]?.trim() ?? '',
        }))
        .filter((item) => item.valeur !== ''),
      sortedPrevuAt: form.sortedPrevuAt ? new Date(form.sortedPrevuAt).toISOString() : null,
    };

    onSubmit(payload);
  };

  const stepError = validateStep(step);

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog variant="outlined" sx={MODAL_SX}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider', flexShrink: 0 }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ width: 40, height: 40, borderRadius: 'md', bgcolor: 'primary.50', color: 'primary.600', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <CalendarClock size={20} />
            </Box>
            <Box sx={{ flex: 1 }}>
              <Typography level="title-lg" sx={{ fontWeight: 700 }}>Nouvelle visite</Typography>
              <Typography level="body-xs" color="neutral">Triage, orientation et création</Typography>
            </Box>
          </Stack>
          <Stepper sx={{ mt: 2 }} size="sm">
            {STEPS.map((label, index) => (
              <Step key={label} indicator={<StepIndicator variant={index <= step ? 'solid' : 'soft'}>{index + 1}</StepIndicator>}>
                {label}
              </Step>
            ))}
          </Stepper>
        </Box>

        <Box
          component="form"
          onSubmit={step === STEPS.length - 1 ? handleSubmit : (event) => { event.preventDefault(); goNext(); }}
          sx={{ display: 'flex', flexDirection: 'column', flex: 1, minHeight: 0 }}
        >
          <Box sx={{ p: 3, overflow: 'auto', flex: 1 }}>
            <Stack spacing={2}>
              {error ? <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>{error}</Typography> : null}
              {stepError && step < 2 ? <Typography level="body-sm" color="warning" sx={{ bgcolor: 'warning.50', p: 1.5, borderRadius: 'md' }}>{stepError}</Typography> : null}

              {step === 0 ? (
                <>
                  <FormControl required>
                    <FormLabel>Type d&apos;entrée</FormLabel>
                    <Select value={form.typeEntree} onChange={(_, value) => handleChange('typeEntree', value ?? 'CONSULTATION')} disabled={loading}>
                      {typesEntree.map((item) => (
                        <Option key={item.value} value={item.value}>{item.label}</Option>
                      ))}
                    </Select>
                    <FormHelperText>Statut initial : {resolveStatutLabel(form.typeEntree)}</FormHelperText>
                  </FormControl>

                  <FormControl>
                    <FormLabel>Priorité</FormLabel>
                    <Select value={form.priorite ?? 3} onChange={(_, value) => handleChange('priorite', value ?? 3)} disabled={loading}>
                      {prioriteOptions.map((item) => (
                        <Option key={item.value} value={item.value}>{item.label}</Option>
                      ))}
                    </Select>
                  </FormControl>

                  <FormControl required>
                    <FormLabel>Motif de venue</FormLabel>
                    <Textarea minRows={2} value={form.motif} onChange={(e) => handleChange('motif', e.target.value)} disabled={loading} placeholder="Décrivez brièvement le motif…" />
                  </FormControl>

                  {signesVitaux.length > 0 ? (
                    <Stack spacing={1.5}>
                      <Typography level="title-sm" sx={{ fontWeight: 700 }}>Signes vitaux</Typography>
                      {signesVitaux.map((signe) => (
                        <FormControl key={signe.id} required={signe.obligatoireAuTriage}>
                          <FormLabel>{signe.libelle}{signe.unite ? ` (${signe.unite})` : ''}</FormLabel>
                          <Input
                            value={form.signesVitaux?.[signe.id] ?? ''}
                            onChange={(e) => handleSigneChange(signe.id, e.target.value)}
                            disabled={loading}
                            placeholder={signe.code}
                          />
                        </FormControl>
                      ))}
                    </Stack>
                  ) : (
                    <Typography level="body-sm" color="neutral">Aucun signe vital configuré pour le triage.</Typography>
                  )}
                </>
              ) : null}

              {step === 1 ? (
                <>
                  <FormControl required>
                    <FormLabel>Département</FormLabel>
                    <Select
                      value={form.departementId ?? ''}
                      onChange={(_, value) => setForm((current) => ({ ...current, departementId: value ?? '', serviceId: '' }))}
                      disabled={loading}
                      placeholder="Choisir un département"
                    >
                      {departements.map((departement) => (
                        <Option key={departement.id} value={departement.id}>{departement.libelle}</Option>
                      ))}
                    </Select>
                  </FormControl>

                  <FormControl required>
                    <FormLabel>Service</FormLabel>
                    <Select
                      value={form.serviceId ?? ''}
                      onChange={(_, value) => handleChange('serviceId', value ?? '')}
                      disabled={loading || !form.departementId}
                      placeholder={form.departementId ? 'Choisir un service' : 'Sélectionnez d\'abord un département'}
                    >
                      {filteredServices.map((service) => (
                        <Option key={service.id} value={service.id}>{service.libelle}</Option>
                      ))}
                    </Select>
                  </FormControl>
                </>
              ) : null}

              {step === 2 ? (
                <>
                  {!dpiLocked ? (
                    <FormControl required>
                      <FormLabel>ID dossier (DPI)</FormLabel>
                      <Input type="number" value={form.dpiId} onChange={(e) => handleChange('dpiId', e.target.value)} disabled={loading} />
                    </FormControl>
                  ) : null}

                  <Box sx={{ p: 2, borderRadius: 'md', bgcolor: 'neutral.50' }}>
                    <Stack spacing={0.75}>
                      <Typography level="body-sm"><strong>Type :</strong> {typesEntree.find((item) => item.value === form.typeEntree)?.label ?? form.typeEntree}</Typography>
                      <Typography level="body-sm"><strong>Statut :</strong> {resolveStatutLabel(form.typeEntree)}</Typography>
                      <Typography level="body-sm"><strong>Motif :</strong> {form.motif || '—'}</Typography>
                      <Typography level="body-sm"><strong>Service :</strong> {filteredServices.find((item) => String(item.id) === String(form.serviceId))?.libelle ?? '—'}</Typography>
                      <Typography level="body-xs" color="neutral">Un acte CONSULTATION sera généré automatiquement (en attente de paiement).</Typography>
                    </Stack>
                  </Box>

                  <FormControl>
                    <FormLabel>Sortie prévue (optionnel)</FormLabel>
                    <Input type="datetime-local" value={form.sortedPrevuAt} onChange={(e) => handleChange('sortedPrevuAt', e.target.value)} disabled={loading} />
                  </FormControl>
                </>
              ) : null}
            </Stack>
          </Box>

          <Stack direction="row" spacing={1.5} justifyContent="space-between" sx={{ px: 3, py: 2, borderTop: '1px solid', borderColor: 'divider', flexShrink: 0 }}>
            <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>Annuler</Button>
            <Stack direction="row" spacing={1}>
              {step > 0 ? (
                <Button variant="outlined" color="neutral" startDecorator={<ChevronLeft size={16} />} onClick={goBack} disabled={loading}>
                  Précédent
                </Button>
              ) : null}
              {step < STEPS.length - 1 ? (
                <Button endDecorator={<ChevronRight size={16} />} onClick={goNext} disabled={loading || Boolean(stepError)}>
                  Suivant
                </Button>
              ) : (
                <Button type="submit" loading={loading} disabled={Boolean(validateStep(2))}>
                  Créer la visite
                </Button>
              )}
            </Stack>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
