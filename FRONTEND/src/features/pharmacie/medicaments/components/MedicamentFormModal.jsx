import { useEffect, useState } from 'react';
import {
  Box, Button, FormControl, FormLabel, Input, Modal, ModalDialog, Option, Select, Stack, Typography,
} from '@mui/joy';
import { Package } from 'lucide-react';
import { MEDICAMENT_STATUTS } from '../medicamentConstants.js';

const MODAL_SX = {
  borderRadius: 'xl',
  maxWidth: 640,
  width: '100%',
  p: 0,
  overflow: 'hidden',
  boxShadow: 'lg',
  maxHeight: 'min(90vh, 820px)',
  display: 'flex',
  flexDirection: 'column',
};

export default function MedicamentFormModal({
  open,
  mode = 'create',
  initialValues,
  unites = [],
  familles = [],
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
      ...(isEdit ? {} : { code: form.code.trim().toUpperCase() }),
      libelle: form.libelle.trim(),
      dci: form.dci.trim() || null,
      forme: form.forme.trim() || null,
      dosage: form.dosage.trim() || null,
      uniteId: Number(form.uniteId),
      familleId: Number(form.familleId),
      prixVente: String(form.prixVente).replace(',', '.'),
      seuilAlerte: Number(form.seuilAlerte) || 0,
      statut: form.statut,
    });
  };

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog variant="outlined" sx={MODAL_SX}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider', flexShrink: 0 }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ width: 40, height: 40, borderRadius: 'md', bgcolor: 'primary.50', color: 'primary.600', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Package size={20} />
            </Box>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>
              {isEdit ? 'Modifier le médicament' : 'Nouveau médicament'}
            </Typography>
          </Stack>
        </Box>
        <Box
          component="form"
          onSubmit={handleSubmit}
          sx={{ display: 'flex', flexDirection: 'column', flex: 1, minHeight: 0 }}
        >
          <Box sx={{ p: 3, overflow: 'auto', flex: 1, minHeight: 0 }}>
            <Stack spacing={2}>
              {error ? (
                <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
                  {error}
                </Typography>
              ) : null}

              {!isEdit ? (
                <FormControl required>
                  <FormLabel>Code interne</FormLabel>
                  <Input
                    value={form.code}
                    onChange={(e) => handleChange('code', e.target.value.toUpperCase())}
                    disabled={loading}
                    slotProps={{ input: { maxLength: 20 } }}
                    placeholder="Ex. PARA500"
                  />
                </FormControl>
              ) : (
                <FormControl>
                  <FormLabel>Code interne</FormLabel>
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
                  placeholder="Ex. Paracétamol 500 mg"
                />
              </FormControl>

              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>DCI</FormLabel>
                  <Input
                    value={form.dci}
                    onChange={(e) => handleChange('dci', e.target.value)}
                    disabled={loading}
                    slotProps={{ input: { maxLength: 150 } }}
                    placeholder="Ex. Paracetamol"
                  />
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Dosage</FormLabel>
                  <Input
                    value={form.dosage}
                    onChange={(e) => handleChange('dosage', e.target.value)}
                    disabled={loading}
                    slotProps={{ input: { maxLength: 50 } }}
                    placeholder="Ex. 500 mg"
                  />
                </FormControl>
              </Stack>

              <FormControl>
                <FormLabel>Forme</FormLabel>
                <Input
                  value={form.forme}
                  onChange={(e) => handleChange('forme', e.target.value)}
                  disabled={loading}
                  slotProps={{ input: { maxLength: 80 } }}
                  placeholder="Ex. Comprimé"
                />
              </FormControl>

              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Unité</FormLabel>
                  <Select
                    value={form.uniteId || null}
                    onChange={(_, value) => handleChange('uniteId', value ?? '')}
                    disabled={loading}
                    placeholder="Choisir…"
                  >
                    {unites.map((item) => (
                      <Option key={item.id} value={String(item.id)}>
                        {item.code} — {item.libelle}
                      </Option>
                    ))}
                  </Select>
                </FormControl>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Famille</FormLabel>
                  <Select
                    value={form.familleId || null}
                    onChange={(_, value) => handleChange('familleId', value ?? '')}
                    disabled={loading}
                    placeholder="Choisir…"
                  >
                    {familles.map((item) => (
                      <Option key={item.id} value={String(item.id)}>
                        {item.code} — {item.libelle}
                      </Option>
                    ))}
                  </Select>
                </FormControl>
              </Stack>

              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Prix de vente (FC)</FormLabel>
                  <Input
                    type="number"
                    value={form.prixVente}
                    onChange={(e) => handleChange('prixVente', e.target.value)}
                    disabled={loading}
                    slotProps={{ input: { min: 0, step: '0.01' } }}
                    placeholder="0"
                  />
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Seuil d&apos;alerte</FormLabel>
                  <Input
                    type="number"
                    value={form.seuilAlerte}
                    onChange={(e) => handleChange('seuilAlerte', e.target.value)}
                    disabled={loading}
                    slotProps={{ input: { min: 0 } }}
                  />
                </FormControl>
              </Stack>

              <FormControl required>
                <FormLabel>Statut</FormLabel>
                <Select value={form.statut} onChange={(_, value) => handleChange('statut', value ?? 'ACTIF')} disabled={loading}>
                  {MEDICAMENT_STATUTS.map((item) => (
                    <Option key={item.value} value={item.value}>{item.label}</Option>
                  ))}
                </Select>
              </FormControl>
            </Stack>
          </Box>

          <Stack direction="row" spacing={1.5} justifyContent="flex-end" sx={{ px: 3, py: 2, borderTop: '1px solid', borderColor: 'divider', flexShrink: 0, bgcolor: 'background.body' }}>
            <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>Annuler</Button>
            <Button type="submit" loading={loading}>{isEdit ? 'Enregistrer' : 'Créer le médicament'}</Button>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
