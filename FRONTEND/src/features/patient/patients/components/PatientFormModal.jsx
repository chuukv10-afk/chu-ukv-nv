import { useEffect, useState } from 'react';
import {
  Box, Button, FormControl, FormHelperText, FormLabel, Input, Modal, ModalDialog, Option, Select, Stack, Typography,
} from '@mui/joy';
import { UserRound } from 'lucide-react';
import {
  GROUPE_SANGUIN_OPTIONS,
  PATIENT_SEXES,
  PATIENT_STATUSES,
} from '../patientConstants.js';

const MODAL_SX = { borderRadius: 'xl', maxWidth: 560, p: 0, overflow: 'hidden', boxShadow: 'lg' };

export default function PatientFormModal({
  open,
  mode = 'create',
  initialValues,
  loading = false,
  error = '',
  readOnlyIdentity = false,
  onClose,
  onSubmit,
}) {
  const [form, setForm] = useState(initialValues);
  const isEdit = mode === 'edit';
  const identityDisabled = loading || (isEdit && readOnlyIdentity);

  useEffect(() => {
    if (open) {
      setForm(initialValues);
    }
  }, [open, initialValues]);

  const handleChange = (field, value) => setForm((current) => ({ ...current, [field]: value }));

  const handleSubmit = (event) => {
    event.preventDefault();
    onSubmit({
      nom: form.nom.trim(),
      postNom: form.postNom.trim(),
      prenom: form.prenom?.trim() || null,
      telephone: form.telephone?.trim() || null,
      adresse: form.adresse?.trim() || null,
      lieuNaissance: form.lieuNaissance?.trim() || null,
      dateNaissance: form.dateNaissance,
      sexe: form.sexe,
      groupeSanguin: form.groupeSanguin?.trim() || null,
      personneAprevenir: form.personneAprevenir?.trim() || null,
      contactAPrevenir: form.contactAPrevenir?.trim() || null,
      status: form.status,
    });
  };

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog variant="outlined" sx={MODAL_SX}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider' }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ width: 40, height: 40, borderRadius: 'md', bgcolor: 'primary.50', color: 'primary.600', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <UserRound size={20} />
            </Box>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>
              {isEdit ? 'Modifier le patient' : 'Nouveau patient'}
            </Typography>
          </Stack>
        </Box>
        <Box component="form" onSubmit={handleSubmit} sx={{ p: 3, maxHeight: '70vh', overflow: 'auto' }}>
          <Stack spacing={2}>
            {error ? (
              <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
                {error}
              </Typography>
            ) : null}
            {readOnlyIdentity ? (
              <Typography level="body-sm" color="warning" sx={{ bgcolor: 'warning.50', p: 1.5, borderRadius: 'md' }}>
                Patient décédé : seul le statut peut être modifié.
              </Typography>
            ) : null}
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
              <FormControl required sx={{ flex: 1 }}>
                <FormLabel>Nom</FormLabel>
                <Input value={form.nom} onChange={(e) => handleChange('nom', e.target.value)} disabled={identityDisabled} slotProps={{ input: { maxLength: 50 } }} />
              </FormControl>
              <FormControl required sx={{ flex: 1 }}>
                <FormLabel>Post-nom</FormLabel>
                <Input value={form.postNom} onChange={(e) => handleChange('postNom', e.target.value)} disabled={identityDisabled} slotProps={{ input: { maxLength: 50 } }} />
              </FormControl>
            </Stack>
            <FormControl sx={{ flex: 1 }}>
              <FormLabel>Prénom</FormLabel>
              <Input value={form.prenom} onChange={(e) => handleChange('prenom', e.target.value)} disabled={identityDisabled} slotProps={{ input: { maxLength: 50 } }} />
            </FormControl>
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
              <FormControl required sx={{ flex: 1 }}>
                <FormLabel>Date de naissance</FormLabel>
                <Input type="date" value={form.dateNaissance} onChange={(e) => handleChange('dateNaissance', e.target.value)} disabled={identityDisabled} />
              </FormControl>
              <FormControl required sx={{ flex: 1 }}>
                <FormLabel>Sexe</FormLabel>
                <Select value={form.sexe} onChange={(_, value) => handleChange('sexe', value ?? 'M')} disabled={identityDisabled}>
                  {PATIENT_SEXES.map((item) => (
                    <Option key={item.value} value={item.value}>{item.label}</Option>
                  ))}
                </Select>
              </FormControl>
            </Stack>
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
              <FormControl sx={{ flex: 1 }}>
                <FormLabel>Téléphone</FormLabel>
                <Input value={form.telephone} onChange={(e) => handleChange('telephone', e.target.value)} disabled={identityDisabled} slotProps={{ input: { maxLength: 15 } }} />
              </FormControl>
              <FormControl sx={{ flex: 1 }}>
                <FormLabel>Groupe sanguin</FormLabel>
                <Select
                  value={form.groupeSanguin ?? ''}
                  onChange={(_, value) => handleChange('groupeSanguin', value ?? '')}
                  disabled={identityDisabled}
                  placeholder="Non renseigné"
                >
                  <Option value="">Non renseigné</Option>
                  {GROUPE_SANGUIN_OPTIONS.map((value) => (
                    <Option key={value} value={value}>{value}</Option>
                  ))}
                </Select>
              </FormControl>
            </Stack>
            <FormControl>
              <FormLabel>Adresse</FormLabel>
              <Input value={form.adresse} onChange={(e) => handleChange('adresse', e.target.value)} disabled={identityDisabled} slotProps={{ input: { maxLength: 100 } }} />
            </FormControl>
            <FormControl>
              <FormLabel>Lieu de naissance</FormLabel>
              <Input value={form.lieuNaissance} onChange={(e) => handleChange('lieuNaissance', e.target.value)} disabled={identityDisabled} slotProps={{ input: { maxLength: 30 } }} />
            </FormControl>
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
              <FormControl sx={{ flex: 1 }}>
                <FormLabel>Personne à prévenir</FormLabel>
                <Input value={form.personneAprevenir} onChange={(e) => handleChange('personneAprevenir', e.target.value)} disabled={identityDisabled} slotProps={{ input: { maxLength: 50 } }} />
              </FormControl>
              <FormControl sx={{ flex: 1 }}>
                <FormLabel>Contact urgence</FormLabel>
                <Input value={form.contactAPrevenir} onChange={(e) => handleChange('contactAPrevenir', e.target.value)} disabled={identityDisabled} slotProps={{ input: { maxLength: 20 } }} />
              </FormControl>
            </Stack>
            {isEdit ? (
              <FormControl required>
                <FormLabel>Statut</FormLabel>
                <Select value={form.status} onChange={(_, value) => handleChange('status', value ?? 'ACTIF')} disabled={loading}>
                  {PATIENT_STATUSES.map((item) => (
                    <Option key={item.value} value={item.value}>{item.label}</Option>
                  ))}
                </Select>
                {readOnlyIdentity ? (
                  <FormHelperText>Changez le statut pour réactiver la modification des données identitaires.</FormHelperText>
                ) : null}
              </FormControl>
            ) : null}
            <Stack direction="row" spacing={1.5} justifyContent="flex-end" sx={{ pt: 1 }}>
              <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>Annuler</Button>
              <Button type="submit" loading={loading}>
                {isEdit ? 'Enregistrer' : 'Créer le patient'}
              </Button>
            </Stack>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
