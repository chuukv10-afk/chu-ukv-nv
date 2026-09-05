import { useEffect, useState } from 'react';
import {
  Box, Button, FormControl, FormHelperText, FormLabel, Input, Modal, ModalDialog, Option, Select, Stack, Typography,
} from '@mui/joy';
import { DoorOpen } from 'lucide-react';
import { fetchBlocsLookupApi } from '../../blocs/blocsApi.js';
import { CHAMBRE_TYPES } from '../chambreConstants.js';

const MODAL_SX = { borderRadius: 'xl', maxWidth: 480, p: 0, overflow: 'hidden', boxShadow: 'lg' };

export default function ChambreFormModal({
  open, mode = 'create', initialValues, loading = false, error = '', onClose, onSubmit,
}) {
  const [form, setForm] = useState(initialValues);
  const [blocs, setBlocs] = useState([]);
  const isEdit = mode === 'edit';

  useEffect(() => {
    if (open) {
      setForm(initialValues);
      fetchBlocsLookupApi().then(setBlocs).catch(() => setBlocs([]));
    }
  }, [open, initialValues]);

  const handleChange = (field, value) => setForm((current) => ({ ...current, [field]: value }));

  const handleSubmit = (event) => {
    event.preventDefault();
    onSubmit({
      code: form.code.trim().toUpperCase(),
      libelle: form.libelle.trim(),
      type: form.type,
      blocId: Number(form.blocId),
    });
  };

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog variant="outlined" sx={MODAL_SX}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider' }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ width: 40, height: 40, borderRadius: 'md', bgcolor: 'primary.50', color: 'primary.600', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <DoorOpen size={20} />
            </Box>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>{isEdit ? 'Modifier la chambre' : 'Nouvelle chambre'}</Typography>
          </Stack>
        </Box>
        <Box component="form" onSubmit={handleSubmit} sx={{ p: 3 }}>
          <Stack spacing={2}>
            {error ? <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>{error}</Typography> : null}
            <FormControl required>
              <FormLabel>Code</FormLabel>
              <Input value={form.code} onChange={(e) => handleChange('code', e.target.value.toUpperCase())} disabled={loading || isEdit} slotProps={{ input: { maxLength: 8 } }} />
              {isEdit ? <FormHelperText>Le code n&apos;est pas modifiable.</FormHelperText> : null}
            </FormControl>
            <FormControl required>
              <FormLabel>Libellé</FormLabel>
              <Input value={form.libelle} onChange={(e) => handleChange('libelle', e.target.value)} disabled={loading} slotProps={{ input: { maxLength: 50 } }} />
            </FormControl>
            <FormControl required>
              <FormLabel>Type</FormLabel>
              <Select value={form.type} onChange={(_, value) => handleChange('type', value)} disabled={loading}>
                {CHAMBRE_TYPES.map((t) => <Option key={t.value} value={t.value}>{t.label}</Option>)}
              </Select>
            </FormControl>
            <FormControl required>
              <FormLabel>Bloc</FormLabel>
              <Select value={form.blocId} onChange={(_, value) => handleChange('blocId', value ?? '')} disabled={loading} placeholder="Sélectionner un bloc">
                {blocs.map((b) => <Option key={b.id} value={b.id}>{b.code} — {b.libelle}</Option>)}
              </Select>
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
