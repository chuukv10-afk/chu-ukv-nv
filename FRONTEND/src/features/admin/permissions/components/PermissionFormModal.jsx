import { useEffect, useState } from 'react';
import {
  Box,
  Button,
  FormControl,
  FormHelperText,
  FormLabel,
  Input,
  Modal,
  ModalDialog,
  Option,
  Select,
  Stack,
  Textarea,
  Typography,
} from '@mui/joy';
import { KeyRound } from 'lucide-react';
import { PERMISSION_MODULES } from '../permissionConstants.js';

const MODAL_SX = {
  borderRadius: 'xl',
  maxWidth: 520,
  p: 0,
  overflow: 'hidden',
  boxShadow: 'lg',
};

export default function PermissionFormModal({
  open,
  mode = 'create',
  initialValues,
  loading = false,
  error = '',
  onClose,
  onSubmit,
}) {
  const [form, setForm] = useState(initialValues);
  const isEdit = mode === 'edit';

  useEffect(() => {
    if (open) {
      setForm(initialValues);
    }
  }, [open, initialValues]);

  const handleChange = (field, value) => {
    setForm((current) => ({ ...current, [field]: value }));
  };

  const handleSubmit = (event) => {
    event.preventDefault();
    onSubmit({
      code: form.code.trim().toLowerCase(),
      libelle: form.libelle.trim(),
      description: form.description?.trim() || null,
      module: form.module,
    });
  };

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog variant="outlined" sx={MODAL_SX}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider' }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box
              sx={{
                width: 40,
                height: 40,
                borderRadius: 'md',
                bgcolor: 'primary.50',
                color: 'primary.600',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
              }}
            >
              <KeyRound size={20} />
            </Box>
            <Box>
              <Typography level="title-lg" sx={{ fontWeight: 700 }}>
                {isEdit ? 'Modifier la permission' : 'Nouvelle permission'}
              </Typography>
              <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
                {isEdit
                  ? 'Mettez à jour les informations de la permission.'
                  : 'Définissez le code, le libellé et le module fonctionnel.'}
              </Typography>
            </Box>
          </Stack>
        </Box>

        <Box component="form" onSubmit={handleSubmit} sx={{ p: 3 }}>
          <Stack spacing={2}>
            {error ? (
              <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
                {error}
              </Typography>
            ) : null}

            <FormControl required>
              <FormLabel>Code</FormLabel>
              <Input
                value={form.code}
                onChange={(event) => handleChange('code', event.target.value.toLowerCase())}
                placeholder="admin.role.read"
                disabled={loading}
                slotProps={{ input: { maxLength: 50 } }}
              />
              <FormHelperText>Minuscules, chiffres, points et underscores uniquement.</FormHelperText>
            </FormControl>

            <FormControl required>
              <FormLabel>Libellé</FormLabel>
              <Input
                value={form.libelle}
                onChange={(event) => handleChange('libelle', event.target.value)}
                placeholder="Lire les rôles"
                disabled={loading}
                slotProps={{ input: { maxLength: 255 } }}
              />
            </FormControl>

            <FormControl>
              <FormLabel>Description</FormLabel>
              <Textarea
                value={form.description ?? ''}
                onChange={(event) => handleChange('description', event.target.value)}
                placeholder="Description optionnelle..."
                disabled={loading}
                minRows={2}
                slotProps={{ textarea: { maxLength: 300 } }}
              />
            </FormControl>

            <FormControl required>
              <FormLabel>Module</FormLabel>
              <Select
                value={form.module}
                onChange={(_, value) => handleChange('module', value)}
                disabled={loading}
              >
                {PERMISSION_MODULES.map((option) => (
                  <Option key={option.value} value={option.value}>
                    {option.label}
                  </Option>
                ))}
              </Select>
            </FormControl>

            <Stack direction="row" spacing={1} justifyContent="flex-end" sx={{ pt: 1 }}>
              <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>
                Annuler
              </Button>
              <Button type="submit" loading={loading}>
                {isEdit ? 'Enregistrer' : 'Créer la permission'}
              </Button>
            </Stack>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
