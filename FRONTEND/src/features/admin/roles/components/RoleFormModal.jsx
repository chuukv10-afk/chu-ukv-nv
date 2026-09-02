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
  Typography,
} from '@mui/joy';
import { Shield } from 'lucide-react';
import { ROLE_PERIMETRES } from '../roleConstants.js';

const MODAL_SX = {
  borderRadius: 'xl',
  maxWidth: 480,
  p: 0,
  overflow: 'hidden',
  boxShadow: 'lg',
};

export default function RoleFormModal({
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
  const isSystem = Boolean(initialValues?.system);
  const lockScope = isEdit && (isSystem || (initialValues?.personnelCount ?? 0) > 0);

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
      code: form.code.trim().toUpperCase(),
      libelle: form.libelle.trim(),
      perimetre: form.perimetre,
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
              <Shield size={20} />
            </Box>
            <Box>
              <Typography level="title-lg" sx={{ fontWeight: 700 }}>
                {isEdit ? 'Modifier le rôle' : 'Nouveau rôle'}
              </Typography>
              <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
                {isEdit
                  ? 'Mettez à jour les informations du rôle.'
                  : 'Le périmètre détermine ce qui sera requis lors de l\'affectation à un agent.'}
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
                onChange={(event) => handleChange('code', event.target.value.toUpperCase())}
                placeholder="CHEF_SERVICE"
                disabled={loading || (isEdit && isSystem)}
                slotProps={{ input: { maxLength: 20 } }}
              />
              <FormHelperText>Lettres, chiffres et underscores uniquement.</FormHelperText>
            </FormControl>

            <FormControl required>
              <FormLabel>Libellé</FormLabel>
              <Input
                value={form.libelle}
                onChange={(event) => handleChange('libelle', event.target.value)}
                placeholder="Chef de service"
                disabled={loading}
                slotProps={{ input: { maxLength: 100 } }}
              />
            </FormControl>

            <FormControl required>
              <FormLabel>Périmètre d&apos;affectation</FormLabel>
              <Select
                value={form.perimetre}
                onChange={(_, value) => handleChange('perimetre', value)}
                disabled={loading || lockScope}
              >
                {ROLE_PERIMETRES.map((option) => (
                  <Option key={option.value} value={option.value}>
                    {option.label}
                  </Option>
                ))}
              </Select>
              <FormHelperText>
                {form.perimetre === 'GLOBAL' && 'Aucune cible organisationnelle requise.'}
                {form.perimetre === 'DEPARTEMENT' && 'Un département sera exigé à l\'affectation.'}
                {form.perimetre === 'SERVICE' && 'Un service sera exigé à l\'affectation.'}
              </FormHelperText>
            </FormControl>

            {lockScope ? (
              <Typography level="body-xs" sx={{ color: 'neutral.500' }}>
                Le périmètre ne peut pas être modifié pour un rôle système ou déjà affecté.
              </Typography>
            ) : null}

            <Stack direction="row" spacing={1} justifyContent="flex-end" sx={{ pt: 1 }}>
              <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>
                Annuler
              </Button>
              <Button type="submit" loading={loading}>
                {isEdit ? 'Enregistrer' : 'Créer le rôle'}
              </Button>
            </Stack>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
