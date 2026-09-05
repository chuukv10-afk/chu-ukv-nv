import { useEffect, useState } from 'react';
import {
  Box, Button, FormControl, FormHelperText, FormLabel, Input, Modal, ModalDialog, Option, Select, Stack, Typography,
} from '@mui/joy';
import { Network } from 'lucide-react';
import { fetchDepartementsLookupApi } from '../../departements/departementsApi.js';

const MODAL_SX = { borderRadius: 'xl', maxWidth: 480, p: 0, overflow: 'hidden', boxShadow: 'lg' };

export default function ServiceFormModal({
  open, mode = 'create', initialValues, loading = false, error = '', onClose, onSubmit,
}) {
  const [form, setForm] = useState(initialValues);
  const [departements, setDepartements] = useState([]);
  const isEdit = mode === 'edit';

  useEffect(() => {
    if (open) {
      setForm(initialValues);
      fetchDepartementsLookupApi().then(setDepartements).catch(() => setDepartements([]));
    }
  }, [open, initialValues]);

  const handleChange = (field, value) => setForm((c) => ({ ...c, [field]: value }));

  const handleSubmit = (e) => {
    e.preventDefault();
    onSubmit({
      code: form.code.trim().toUpperCase(),
      libelle: form.libelle.trim(),
      departementId: Number(form.departementId),
    });
  };

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog variant="outlined" sx={MODAL_SX}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider' }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ width: 40, height: 40, borderRadius: 'md', bgcolor: 'primary.50', color: 'primary.600', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Network size={20} />
            </Box>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>{isEdit ? 'Modifier le service' : 'Nouveau service'}</Typography>
          </Stack>
        </Box>
        <Box component="form" onSubmit={handleSubmit} sx={{ p: 3 }}>
          <Stack spacing={2}>
            {error ? <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>{error}</Typography> : null}
            <FormControl required>
              <FormLabel>Code</FormLabel>
              <Input value={form.code} onChange={(e) => handleChange('code', e.target.value.toUpperCase())} disabled={loading || isEdit} slotProps={{ input: { maxLength: 8 } }} />
              {isEdit ? <FormHelperText>Le code ne peut pas être modifié.</FormHelperText> : null}
            </FormControl>
            <FormControl required>
              <FormLabel>Libellé</FormLabel>
              <Input value={form.libelle} onChange={(e) => handleChange('libelle', e.target.value)} disabled={loading} slotProps={{ input: { maxLength: 100 } }} />
            </FormControl>
            <FormControl required>
              <FormLabel>Département</FormLabel>
              <Select value={form.departementId ?? ''} onChange={(_, v) => handleChange('departementId', v)} disabled={loading}>
                {departements.map((d) => <Option key={d.id} value={d.id}>{d.code} — {d.libelle}</Option>)}
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
