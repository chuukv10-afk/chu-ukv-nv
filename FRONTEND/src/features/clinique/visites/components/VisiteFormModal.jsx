import { useEffect, useState } from 'react';
import {
  Box, Button, FormControl, FormHelperText, FormLabel, Input, Modal, ModalDialog, Option, Select, Stack, Typography,
} from '@mui/joy';
import { CalendarClock } from 'lucide-react';
import { EMPTY_VISITE_FORM, VISITE_STATUTS } from '../visiteConstants.js';

const MODAL_SX = { borderRadius: 'xl', maxWidth: 520, p: 0, overflow: 'hidden', boxShadow: 'lg' };
const CREATABLE_STATUTS = VISITE_STATUTS.filter((item) => ['PLANIFIEE', 'EN_COURS'].includes(item.value));

export default function VisiteFormModal({
  open,
  mode = 'create',
  initialValues,
  services = [],
  lits = [],
  loading = false,
  error = '',
  dpiLocked = false,
  onClose,
  onSubmit,
}) {
  const [form, setForm] = useState(initialValues);
  const isEdit = mode === 'edit';
  const needsLit = form.statut === 'HOSPITALISE';

  useEffect(() => {
    if (open) setForm(initialValues);
  }, [open, initialValues]);

  const handleChange = (field, value) => setForm((current) => ({ ...current, [field]: value }));

  const handleSubmit = (event) => {
    event.preventDefault();
    const payload = {
      serviceId: Number(form.serviceId),
      statut: form.statut,
      sortedPrevuAt: form.sortedPrevuAt ? new Date(form.sortedPrevuAt).toISOString() : null,
    };

    if (!isEdit) {
      payload.dpiId = Number(form.dpiId);
    }

    if (needsLit && form.litId) {
      payload.litId = Number(form.litId);
    } else if (!needsLit) {
      payload.litId = null;
    }

    onSubmit(payload);
  };

  const availableLits = lits.filter((lit) => !lit.occupied || String(lit.id) === String(form.litId));

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog variant="outlined" sx={MODAL_SX}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider' }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ width: 40, height: 40, borderRadius: 'md', bgcolor: 'primary.50', color: 'primary.600', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <CalendarClock size={20} />
            </Box>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>
              {isEdit ? 'Modifier la visite' : 'Nouvelle visite'}
            </Typography>
          </Stack>
        </Box>
        <Box component="form" onSubmit={handleSubmit} sx={{ p: 3 }}>
          <Stack spacing={2}>
            {error ? <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>{error}</Typography> : null}

            {!isEdit && !dpiLocked ? (
              <FormControl required>
                <FormLabel>ID dossier (DPI)</FormLabel>
                <Input type="number" value={form.dpiId} onChange={(e) => handleChange('dpiId', e.target.value)} disabled={loading} />
              </FormControl>
            ) : null}

            <FormControl required>
              <FormLabel>Service</FormLabel>
              <Select value={form.serviceId ?? ''} onChange={(_, value) => handleChange('serviceId', value ?? '')} disabled={loading} placeholder="Choisir un service">
                {services.map((service) => (
                  <Option key={service.id} value={service.id}>{service.libelle}{service.departement ? ` — ${service.departement}` : ''}</Option>
                ))}
              </Select>
            </FormControl>

            <FormControl required>
              <FormLabel>Statut initial</FormLabel>
              <Select value={form.statut} onChange={(_, value) => handleChange('statut', value ?? 'EN_COURS')} disabled={loading || isEdit}>
                {(isEdit ? VISITE_STATUTS : CREATABLE_STATUTS).map((item) => (
                  <Option key={item.value} value={item.value}>{item.label}</Option>
                ))}
              </Select>
              {isEdit ? <FormHelperText>Utilisez les actions de transition sur la fiche pour changer le statut.</FormHelperText> : null}
            </FormControl>

            {needsLit ? (
              <FormControl required>
                <FormLabel>Lit</FormLabel>
                <Select value={form.litId ?? ''} onChange={(_, value) => handleChange('litId', value ?? '')} disabled={loading} placeholder="Choisir un lit">
                  {availableLits.map((lit) => (
                    <Option key={lit.id} value={lit.id}>
                      {lit.code} — {lit.numeroLit}{lit.bloc ? ` (${lit.bloc})` : ''}
                    </Option>
                  ))}
                </Select>
              </FormControl>
            ) : null}

            <FormControl>
              <FormLabel>Sortie prévue</FormLabel>
              <Input type="datetime-local" value={form.sortedPrevuAt} onChange={(e) => handleChange('sortedPrevuAt', e.target.value)} disabled={loading} />
            </FormControl>

            <Stack direction="row" spacing={1.5} justifyContent="flex-end" sx={{ pt: 1 }}>
              <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>Annuler</Button>
              <Button type="submit" loading={loading}>{isEdit ? 'Enregistrer' : 'Créer la visite'}</Button>
            </Stack>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
