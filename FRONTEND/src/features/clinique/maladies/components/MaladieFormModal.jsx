import { useEffect, useState } from 'react';
import {
  Box, Button, FormControl, FormHelperText, FormLabel, Input, Modal, ModalDialog, Option, Select, Stack, Typography,
} from '@mui/joy';
import { HeartPulse } from 'lucide-react';
import { fetchMaladieChapitresApi } from '../maladiesApi.js';

const MODAL_SX = { borderRadius: 'xl', maxWidth: 480, p: 0, overflow: 'hidden', boxShadow: 'lg' };

export default function MaladieFormModal({
  open, mode = 'create', initialValues, loading = false, error = '', onClose, onSubmit,
}) {
  const [form, setForm] = useState(initialValues);
  const [chapitres, setChapitres] = useState([]);
  const isEdit = mode === 'edit';

  useEffect(() => {
    if (open) {
      setForm(initialValues);
      fetchMaladieChapitresApi().then(setChapitres).catch(() => setChapitres([]));
    }
  }, [open, initialValues]);

  const handleChange = (field, value) => setForm((current) => ({ ...current, [field]: value }));

  const handleSubmit = (event) => {
    event.preventDefault();
    if (isEdit) {
      onSubmit({
        libelle: form.libelle.trim(),
        chapitre: form.chapitre?.trim() || null,
      });
      return;
    }

    onSubmit({
      codeCim10: form.codeCim10.trim().toUpperCase(),
      libelle: form.libelle.trim(),
      chapitre: form.chapitre?.trim() || null,
    });
  };

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog variant="outlined" sx={MODAL_SX}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider' }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ width: 40, height: 40, borderRadius: 'md', bgcolor: 'primary.50', color: 'primary.600', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <HeartPulse size={20} />
            </Box>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>{isEdit ? 'Modifier la maladie' : 'Nouvelle maladie CIM-10'}</Typography>
          </Stack>
        </Box>
        <Box component="form" onSubmit={handleSubmit} sx={{ p: 3 }}>
          <Stack spacing={2}>
            {error ? <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>{error}</Typography> : null}
            {!isEdit ? (
              <FormControl required>
                <FormLabel>Code CIM-10</FormLabel>
                <Input value={form.codeCim10} onChange={(e) => handleChange('codeCim10', e.target.value.toUpperCase())} disabled={loading} slotProps={{ input: { maxLength: 15 } }} />
              </FormControl>
            ) : (
              <FormControl>
                <FormLabel>Code CIM-10</FormLabel>
                <Input value={form.codeCim10} disabled readOnly />
                <FormHelperText>Le code n&apos;est pas modifiable.</FormHelperText>
              </FormControl>
            )}
            <FormControl required>
              <FormLabel>Libellé</FormLabel>
              <Input value={form.libelle} onChange={(e) => handleChange('libelle', e.target.value)} disabled={loading} slotProps={{ input: { maxLength: 255 } }} />
            </FormControl>
            <FormControl>
              <FormLabel>Chapitre</FormLabel>
              <Select
                value={form.chapitre ?? ''}
                onChange={(_, value) => handleChange('chapitre', value ?? '')}
                disabled={loading}
                placeholder="Sélectionner un chapitre"
              >
                <Option value="">Aucun chapitre</Option>
                {chapitres.map((chapitre) => <Option key={chapitre} value={chapitre}>{chapitre}</Option>)}
              </Select>
              <FormHelperText>Vous pouvez aussi saisir une valeur personnalisée ci-dessous.</FormHelperText>
              <Input sx={{ mt: 1 }} value={form.chapitre ?? ''} onChange={(e) => handleChange('chapitre', e.target.value)} disabled={loading} slotProps={{ input: { maxLength: 20 } }} placeholder="Ex. A00-B99" />
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
