import { useEffect, useState } from 'react';
import {
  Box, Button, FormControl, FormLabel, Input, Modal, ModalDialog, Option, Select, Stack, Typography,
} from '@mui/joy';
import { Wallet } from 'lucide-react';
import { ACTE_STATUTS } from '../facturationConstants.js';

const MODAL_SX = {
  borderRadius: 'xl',
  maxWidth: 640,
  width: '100%',
  p: 0,
  overflow: 'hidden',
  boxShadow: 'lg',
  maxHeight: 'min(90vh, 860px)',
  display: 'flex',
  flexDirection: 'column',
};

function tarifValue(value) {
  if (value === null || value === undefined) return '0';
  return String(value);
}

export default function ActeFormModal({
  open,
  mode = 'create',
  initialValues,
  serviceGrilles = [],
  loading = false,
  error = '',
  onClose,
  onSubmit,
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
      serviceGrille: form.serviceGrille.trim(),
      sousCategorie: form.sousCategorie.trim() || null,
      libelle: form.libelle.trim(),
      tarifA0: tarifValue(form.tarifA0),
      tarifA1: tarifValue(form.tarifA1),
      tarifA: tarifValue(form.tarifA),
      tarifB: tarifValue(form.tarifB),
      tarifC: tarifValue(form.tarifC),
      statut: form.statut,
    });
  };

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog variant="outlined" sx={MODAL_SX}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider', flexShrink: 0 }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{
              width: 40, height: 40, borderRadius: 'md', bgcolor: 'primary.50', color: 'primary.600',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
            }}
            >
              <Wallet size={20} />
            </Box>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>
              {isEdit ? 'Modifier l\'acte' : 'Nouvel acte tarifaire'}
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

              {isEdit ? (
                <FormControl>
                  <FormLabel>Code</FormLabel>
                  <Input value={form.code} disabled readOnly />
                </FormControl>
              ) : null}

              <FormControl required>
                <FormLabel>Service</FormLabel>
                <Select
                  value={form.serviceGrille || null}
                  onChange={(_, value) => handleChange('serviceGrille', value ?? '')}
                  disabled={loading}
                  placeholder="Choisir un service"
                >
                  {Array.from(new Set([
                    ...serviceGrilles,
                    ...(form.serviceGrille ? [form.serviceGrille] : []),
                  ])).map((service) => (
                    <Option key={service} value={service}>{service}</Option>
                  ))}
                </Select>
              </FormControl>

              <FormControl required>
                <FormLabel>Libellé de l'acte</FormLabel>
                <Input
                  value={form.libelle}
                  onChange={(e) => handleChange('libelle', e.target.value)}
                  disabled={loading}
                  slotProps={{ input: { maxLength: 180 } }}
                  placeholder="Ex. Consultation médicale Jour"
                />
              </FormControl>

              <FormControl>
                <FormLabel>Sous-catégorie</FormLabel>
                <Input
                  value={form.sousCategorie}
                  onChange={(e) => handleChange('sousCategorie', e.target.value)}
                  disabled={loading}
                  slotProps={{ input: { maxLength: 80 } }}
                />
              </FormControl>

              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
                {['A0', 'A1', 'A', 'B', 'C'].map((code) => {
                  const field = code === 'A' ? 'tarifA' : `tarif${code}`;
                  return (
                    <FormControl key={code} sx={{ flex: 1 }}>
                      <FormLabel>Tarif {code} (FC)</FormLabel>
                      <Input
                        type="number"
                        value={form[field]}
                        onChange={(e) => handleChange(field, e.target.value)}
                        disabled={loading}
                        slotProps={{ input: { min: 0, step: '0.01' } }}
                      />
                    </FormControl>
                  );
                })}
              </Stack>

              <FormControl required>
                <FormLabel>Statut</FormLabel>
                <Select
                  value={form.statut}
                  onChange={(_, value) => handleChange('statut', value ?? 'ACTIF')}
                  disabled={loading}
                >
                  {ACTE_STATUTS.map((item) => (
                    <Option key={item.value} value={item.value}>{item.label}</Option>
                  ))}
                </Select>
              </FormControl>
            </Stack>
          </Box>

          <Stack direction="row" spacing={1.5} justifyContent="flex-end" sx={{ px: 3, py: 2, borderTop: '1px solid', borderColor: 'divider', flexShrink: 0 }}>
            <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>Annuler</Button>
            <Button type="submit" loading={loading}>{isEdit ? 'Enregistrer' : 'Créer l\'acte'}</Button>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
