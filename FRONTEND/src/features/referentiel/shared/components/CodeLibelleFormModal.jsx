import { useEffect, useState } from 'react';
import {
  Box, Button, FormControl, FormHelperText, FormLabel, Input, Modal, ModalDialog, Stack, Typography,
} from '@mui/joy';

const MODAL_SX = { borderRadius: 'xl', maxWidth: 480, p: 0, overflow: 'hidden', boxShadow: 'lg' };

export default function CodeLibelleFormModal({
  open,
  mode = 'create',
  initialValues,
  loading = false,
  error = '',
  onClose,
  onSubmit,
  icon: Icon,
  createTitle = 'Nouvel élément',
  editTitle = 'Modifier',
  codeMaxLength = 8,
  libelleMaxLength = 100,
}) {
  const [form, setForm] = useState(initialValues);
  const isEdit = mode === 'edit';

  useEffect(() => {
    if (open) setForm(initialValues);
  }, [open, initialValues]);

  const handleChange = (field, value) => setForm((current) => ({ ...current, [field]: value }));

  const handleSubmit = (event) => {
    event.preventDefault();
    onSubmit({
      code: form.code.trim().toUpperCase(),
      libelle: form.libelle.trim(),
    });
  };

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog variant="outlined" sx={MODAL_SX}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider' }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            {Icon ? (
              <Box sx={{ width: 40, height: 40, borderRadius: 'md', bgcolor: 'primary.50', color: 'primary.600', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <Icon size={20} />
              </Box>
            ) : null}
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>{isEdit ? editTitle : createTitle}</Typography>
          </Stack>
        </Box>
        <Box component="form" onSubmit={handleSubmit} sx={{ p: 3 }}>
          <Stack spacing={2}>
            {error ? <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>{error}</Typography> : null}
            <FormControl required>
              <FormLabel>Code</FormLabel>
              <Input value={form.code} onChange={(e) => handleChange('code', e.target.value.toUpperCase())} disabled={loading || isEdit} slotProps={{ input: { maxLength: codeMaxLength } }} />
              {isEdit ? <FormHelperText>Le code n&apos;est pas modifiable.</FormHelperText> : null}
            </FormControl>
            <FormControl required>
              <FormLabel>Libellé</FormLabel>
              <Input value={form.libelle} onChange={(e) => handleChange('libelle', e.target.value)} disabled={loading} slotProps={{ input: { maxLength: libelleMaxLength } }} />
            </FormControl>
            <Stack direction="row" spacing={1} justifyContent="flex-end" sx={{ pt: 1 }}>
              <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>Annuler</Button>
              <Button type="submit" loading={loading}>{isEdit ? 'Enregistrer' : 'Créer'}</Button>
            </Stack>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
