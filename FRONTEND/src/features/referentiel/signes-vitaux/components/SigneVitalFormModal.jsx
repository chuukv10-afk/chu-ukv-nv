import { useEffect, useState } from 'react';
import {
  Box, Button, Checkbox, FormControl, FormHelperText, FormLabel, Input, Modal, ModalDialog, Option, Select, Stack, Typography,
} from '@mui/joy';
import { Activity } from 'lucide-react';
import { SIGNE_VITAL_STATUTS } from '../signeVitalConstants.js';

const MODAL_SX = {
  borderRadius: 'xl',
  maxWidth: 520,
  width: '100%',
  p: 0,
  overflow: 'hidden',
  boxShadow: 'lg',
  maxHeight: 'min(90vh, 720px)',
  display: 'flex',
  flexDirection: 'column',
};

export default function SigneVitalFormModal({
  open, mode = 'create', initialValues, loading = false, error = '', onClose, onSubmit,
}) {
  const [form, setForm] = useState(initialValues);
  const isEdit = mode === 'edit';

  useEffect(() => {
    if (open) setForm(initialValues);
  }, [open, initialValues]);

  const handleChange = (field, value) => setForm((current) => ({ ...current, [field]: value }));

  const handleDemandeChange = (checked) => {
    setForm((current) => ({
      ...current,
      demandeAuTriage: checked,
      obligatoireAuTriage: checked ? current.obligatoireAuTriage : false,
    }));
  };

  const handleSubmit = (event) => {
    event.preventDefault();
    onSubmit({
      ...(isEdit ? {} : { code: form.code.trim().toUpperCase() }),
      libelle: form.libelle.trim(),
      unite: form.unite?.trim() || null,
      demandeAuTriage: form.demandeAuTriage,
      obligatoireAuTriage: form.obligatoireAuTriage,
      ordre: Number(form.ordre) || 0,
      statut: form.statut,
    });
  };

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog variant="outlined" sx={MODAL_SX}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider', flexShrink: 0 }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ width: 40, height: 40, borderRadius: 'md', bgcolor: 'primary.50', color: 'primary.600', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Activity size={20} />
            </Box>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>
              {isEdit ? 'Modifier le signe vital' : 'Nouveau signe vital'}
            </Typography>
          </Stack>
        </Box>
        <Box
          component="form"
          onSubmit={handleSubmit}
          sx={{ display: 'flex', flexDirection: 'column', flex: 1, minHeight: 0 }}
        >
          <Box sx={{ p: 3, overflow: 'auto', flex: 1 }}>
            <Stack spacing={2}>
              {error ? <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>{error}</Typography> : null}

              {!isEdit ? (
                <FormControl required>
                  <FormLabel>Code</FormLabel>
                  <Input value={form.code} onChange={(e) => handleChange('code', e.target.value.toUpperCase())} disabled={loading} slotProps={{ input: { maxLength: 15 } }} placeholder="Ex. TA, POULS" />
                </FormControl>
              ) : (
                <FormControl>
                  <FormLabel>Code</FormLabel>
                  <Input value={form.code} disabled readOnly />
                </FormControl>
              )}

              <FormControl required>
                <FormLabel>Libellé</FormLabel>
                <Input value={form.libelle} onChange={(e) => handleChange('libelle', e.target.value)} disabled={loading} slotProps={{ input: { maxLength: 100 } }} placeholder="Ex. Tension artérielle" />
              </FormControl>

              <FormControl>
                <FormLabel>Unité de mesure</FormLabel>
                <Input value={form.unite} onChange={(e) => handleChange('unite', e.target.value)} disabled={loading} slotProps={{ input: { maxLength: 20 } }} placeholder="Ex. mmHg, °C, bpm" />
              </FormControl>

              <FormControl>
                <FormLabel>Ordre d&apos;affichage au triage</FormLabel>
                <Input type="number" value={form.ordre} onChange={(e) => handleChange('ordre', e.target.value)} disabled={loading} slotProps={{ input: { min: 0 } }} />
              </FormControl>

              <Stack spacing={1}>
                <Checkbox
                  label="Demandé au triage"
                  checked={form.demandeAuTriage}
                  onChange={(e) => handleDemandeChange(e.target.checked)}
                  disabled={loading}
                />
                <Checkbox
                  label="Obligatoire au triage"
                  checked={form.obligatoireAuTriage}
                  onChange={(e) => handleChange('obligatoireAuTriage', e.target.checked)}
                  disabled={loading || !form.demandeAuTriage}
                />
                <FormHelperText>
                  Cochez « Demandé au triage » pour inclure ce signe dans le formulaire de triage. « Obligatoire » impose sa saisie.
                </FormHelperText>
              </Stack>

              <FormControl required>
                <FormLabel>Statut</FormLabel>
                <Select value={form.statut} onChange={(_, value) => handleChange('statut', value ?? 'ACTIF')} disabled={loading}>
                  {SIGNE_VITAL_STATUTS.map((item) => (
                    <Option key={item.value} value={item.value}>{item.label}</Option>
                  ))}
                </Select>
              </FormControl>
            </Stack>
          </Box>

          <Stack
            direction="row"
            spacing={1.5}
            justifyContent="flex-end"
            sx={{
              px: 3,
              py: 2,
              borderTop: '1px solid',
              borderColor: 'divider',
              bgcolor: 'background.surface',
              flexShrink: 0,
            }}
          >
            <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>Annuler</Button>
            <Button type="submit" loading={loading}>{isEdit ? 'Enregistrer' : 'Créer le signe vital'}</Button>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
