import { useEffect, useState } from 'react';
import {
  Box, Button, FormControl, FormLabel, Input, Modal, ModalDialog, Option, Select, Stack, Typography,
} from '@mui/joy';
import { Truck } from 'lucide-react';
import { FOURNISSEUR_STATUTS } from '../fournisseurConstants.js';

const MODAL_SX = {
  borderRadius: 'xl',
  maxWidth: 560,
  width: '100%',
  p: 0,
  overflow: 'hidden',
  boxShadow: 'lg',
  maxHeight: 'min(90vh, 820px)',
  display: 'flex',
  flexDirection: 'column',
};

export default function FournisseurFormModal({
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
      telephone: form.telephone.trim() || null,
      adresse: form.adresse.trim() || null,
      statut: form.statut,
    });
  };

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog variant="outlined" sx={MODAL_SX}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider', flexShrink: 0 }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ width: 40, height: 40, borderRadius: 'md', bgcolor: 'primary.50', color: 'primary.600', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Truck size={20} />
            </Box>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>
              {isEdit ? 'Modifier le fournisseur' : 'Nouveau fournisseur'}
            </Typography>
          </Stack>
        </Box>
        <Box component="form" onSubmit={handleSubmit} sx={{ display: 'flex', flexDirection: 'column', minHeight: 0 }}>
          <Box sx={{ p: 3, overflow: 'auto' }}>
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
                    placeholder="Ex. SUP01"
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
                  slotProps={{ input: { maxLength: 150 } }}
                  placeholder="Ex. Pharmakina"
                />
              </FormControl>

              <FormControl>
                <FormLabel>Téléphone</FormLabel>
                <Input
                  value={form.telephone}
                  onChange={(e) => handleChange('telephone', e.target.value)}
                  disabled={loading}
                  slotProps={{ input: { maxLength: 20 } }}
                />
              </FormControl>

              <FormControl>
                <FormLabel>Adresse</FormLabel>
                <Input
                  value={form.adresse}
                  onChange={(e) => handleChange('adresse', e.target.value)}
                  disabled={loading}
                  slotProps={{ input: { maxLength: 200 } }}
                />
              </FormControl>

              <FormControl required>
                <FormLabel>Statut</FormLabel>
                <Select value={form.statut} onChange={(_, value) => handleChange('statut', value ?? 'ACTIF')} disabled={loading}>
                  {FOURNISSEUR_STATUTS.map((item) => (
                    <Option key={item.value} value={item.value}>{item.label}</Option>
                  ))}
                </Select>
              </FormControl>
            </Stack>
          </Box>

          <Stack direction="row" spacing={1.5} justifyContent="flex-end" sx={{ px: 3, py: 2, borderTop: '1px solid', borderColor: 'divider', flexShrink: 0 }}>
            <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>Annuler</Button>
            <Button type="submit" loading={loading}>{isEdit ? 'Enregistrer' : 'Créer le fournisseur'}</Button>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
