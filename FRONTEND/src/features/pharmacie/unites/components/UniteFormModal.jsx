import { useEffect, useState } from 'react';
import {
  Box, Button, FormControl, FormLabel, Input, Modal, ModalDialog, Option, Select, Stack, Typography,
} from '@mui/joy';
import { Pill } from 'lucide-react';
import { UNITE_STATUTS } from '../uniteConstants.js';

const MODAL_SX = {
  borderRadius: 'xl',
  maxWidth: 520,
  width: '100%',
  p: 0,
  overflow: 'hidden',
  boxShadow: 'lg',
};

export default function UniteFormModal({
  open, mode = 'create', initialValues, loading = false, error = '', onClose, onSubmit,
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
      ...(isEdit ? {} : { code: form.code.trim().toUpperCase() }),
      libelle: form.libelle.trim(),
      ordre: Number(form.ordre) || 0,
      statut: form.statut,
    });
  };

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog variant="outlined" sx={MODAL_SX}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider' }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ width: 40, height: 40, borderRadius: 'md', bgcolor: 'primary.50', color: 'primary.600', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Pill size={20} />
            </Box>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>
              {isEdit ? 'Modifier l\'unité' : 'Nouvelle unité'}
            </Typography>
          </Stack>
        </Box>
        <Box component="form" onSubmit={handleSubmit}>
          <Box sx={{ p: 3 }}>
            <Stack spacing={2}>
              {error ? (
                <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
                  {error}
                </Typography>
              ) : null}

              {!isEdit ? (
                <FormControl required>
                  <FormLabel>Code</FormLabel>
                  <Input
                    value={form.code}
                    onChange={(e) => handleChange('code', e.target.value.toUpperCase())}
                    disabled={loading}
                    slotProps={{ input: { maxLength: 15 } }}
                    placeholder="Ex. CPR"
                  />
                </FormControl>
              ) : (
                <FormControl>
                  <FormLabel>Code</FormLabel>
                  <Input value={form.code} disabled readOnly />
                </FormControl>
              )}

              <FormControl required>
                <FormLabel>Libellé</FormLabel>
                <Input
                  value={form.libelle}
                  onChange={(e) => handleChange('libelle', e.target.value)}
                  disabled={loading}
                  slotProps={{ input: { maxLength: 100 } }}
                  placeholder="Ex. Comprimé"
                />
              </FormControl>

              <FormControl>
                <FormLabel>Ordre d&apos;affichage</FormLabel>
                <Input
                  type="number"
                  value={form.ordre}
                  onChange={(e) => handleChange('ordre', e.target.value)}
                  disabled={loading}
                  slotProps={{ input: { min: 0 } }}
                />
              </FormControl>

              <FormControl required>
                <FormLabel>Statut</FormLabel>
                <Select value={form.statut} onChange={(_, value) => handleChange('statut', value ?? 'ACTIF')} disabled={loading}>
                  {UNITE_STATUTS.map((item) => (
                    <Option key={item.value} value={item.value}>{item.label}</Option>
                  ))}
                </Select>
              </FormControl>
            </Stack>
          </Box>

          <Stack direction="row" spacing={1.5} justifyContent="flex-end" sx={{ px: 3, py: 2, borderTop: '1px solid', borderColor: 'divider' }}>
            <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>Annuler</Button>
            <Button type="submit" loading={loading}>{isEdit ? 'Enregistrer' : 'Créer l\'unité'}</Button>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
