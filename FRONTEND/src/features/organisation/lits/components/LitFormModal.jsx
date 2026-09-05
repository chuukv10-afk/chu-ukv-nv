import { useEffect, useState } from 'react';
import {
  Box, Button, FormControl, FormHelperText, FormLabel, Input, Modal, ModalDialog, Option, Select, Stack, Typography,
} from '@mui/joy';
import { BedDouble } from 'lucide-react';
import { fetchChambresLookupApi } from '../../chambres/chambresApi.js';

const MODAL_SX = { borderRadius: 'xl', maxWidth: 480, p: 0, overflow: 'hidden', boxShadow: 'lg' };

export default function LitFormModal({
  open, mode = 'create', initialValues, loading = false, error = '', onClose, onSubmit,
}) {
  const [form, setForm] = useState(initialValues);
  const [chambres, setChambres] = useState([]);
  const isEdit = mode === 'edit';

  useEffect(() => {
    if (open) {
      setForm(initialValues);
      fetchChambresLookupApi().then(setChambres).catch(() => setChambres([]));
    }
  }, [open, initialValues]);

  const handleChange = (field, value) => setForm((current) => ({ ...current, [field]: value }));

  const handleSubmit = (event) => {
    event.preventDefault();
    onSubmit({
      code: form.code.trim().toUpperCase(),
      numeroLit: form.numeroLit.trim(),
      chambreId: Number(form.chambreId),
    });
  };

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog variant="outlined" sx={MODAL_SX}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider' }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ width: 40, height: 40, borderRadius: 'md', bgcolor: 'primary.50', color: 'primary.600', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <BedDouble size={20} />
            </Box>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>{isEdit ? 'Modifier le lit' : 'Nouveau lit'}</Typography>
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
              <FormLabel>Numéro de lit</FormLabel>
              <Input value={form.numeroLit} onChange={(e) => handleChange('numeroLit', e.target.value)} disabled={loading} slotProps={{ input: { maxLength: 15 } }} />
            </FormControl>
            <FormControl required>
              <FormLabel>Chambre</FormLabel>
              <Select value={form.chambreId} onChange={(_, value) => handleChange('chambreId', value ?? '')} disabled={loading} placeholder="Sélectionner une chambre">
                {chambres.map((c) => <Option key={c.id} value={c.id}>{c.code} — {c.libelle}</Option>)}
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
