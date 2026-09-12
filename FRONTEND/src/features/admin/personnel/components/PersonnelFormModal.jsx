import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Avatar,
  Box,
  Button,
  Divider,
  FormControl,
  FormHelperText,
  FormLabel,
  IconButton,
  Input,
  Modal,
  ModalDialog,
  Option,
  Select,
  Stack,
  Typography,
} from '@mui/joy';
import { Plus, Trash2, UserCog, Camera, X } from 'lucide-react';
import AuthAvatar from '../../../../components/ui/AuthAvatar.jsx';
import { getInitials } from '../../../../utils/profile.js';
import {
  AVATAR_ACCEPT,
  fetchAuthenticatedAvatarUrl,
  readFilePreview,
  validateAvatarFile,
} from '../../../../utils/avatar.js';
import { fetchPersonnelLookupsApi } from '../personnelApi.js';
import {
  EMPTY_PERSONNEL_FORM,
  EMPTY_ROLE_ASSIGNMENT,
  isMedicalPersonnelType,
  PERSONNEL_STATUSES,
  PERSONNEL_TYPES,
} from '../personnelConstants.js';

const MODAL_SX = {
  borderRadius: 'xl',
  width: 'min(960px, calc(100vw - 32px))',
  maxWidth: '960px',
  maxHeight: 'min(92vh, 900px)',
  p: 0,
  overflow: 'hidden',
  boxShadow: 'lg',
  display: 'flex',
  flexDirection: 'column',
};

function buildInitialForm(initialValues, isEdit) {
  return {
    ...EMPTY_PERSONNEL_FORM,
    ...initialValues,
    password: isEdit ? '' : (initialValues?.password ?? ''),
    specialiteIds: initialValues?.specialiteIds ?? [],
    roleAssignments: initialValues?.roleAssignments?.length
      ? initialValues.roleAssignments
        .filter((item) => item.roleCode !== 'PERSONNEL')
        .map((item) => ({
          roleId: item.roleId ?? '',
          serviceId: item.serviceId ?? null,
          departementId: item.departementId ?? null,
        }))
      : [],
  };
}

export default function PersonnelFormModal({
  open,
  mode = 'create',
  initialValues,
  loading = false,
  error = '',
  onClose,
  onSubmit,
}) {
  const isEdit = mode === 'edit';
  const [form, setForm] = useState(() => buildInitialForm(initialValues, isEdit));
  const [lookups, setLookups] = useState({
    grades: [],
    services: [],
    departements: [],
    specialites: [],
    roles: [],
  });
  const [lookupsLoading, setLookupsLoading] = useState(false);
  const [loadError, setLoadError] = useState('');
  const [avatarFile, setAvatarFile] = useState(null);
  const [avatarPreview, setAvatarPreview] = useState(null);
  const [removeAvatar, setRemoveAvatar] = useState(false);
  const [avatarError, setAvatarError] = useState('');
  const [signatureFile, setSignatureFile] = useState(null);
  const [signaturePreview, setSignaturePreview] = useState(null);
  const [removeSignature, setRemoveSignature] = useState(false);

  const loadLookups = useCallback(async () => {
    setLookupsLoading(true);
    setLoadError('');
    try {
      const data = await fetchPersonnelLookupsApi();
      setLookups(data);
    } catch (err) {
      setLoadError(err.message || 'Impossible de charger les listes de référence.');
    } finally {
      setLookupsLoading(false);
    }
  }, []);

  useEffect(() => {
    if (open) {
      setForm(buildInitialForm(initialValues, isEdit));
      setAvatarFile(null);
      setRemoveAvatar(false);
      setAvatarError('');
      setAvatarPreview(null);
      setSignatureFile(null);
      setRemoveSignature(false);
      setSignaturePreview(null);
      loadLookups();

      if (initialValues?.avatarUrl) {
        fetchAuthenticatedAvatarUrl(initialValues.avatarUrl)
          .then((url) => {
            if (url) setAvatarPreview(url);
          })
          .catch(() => setAvatarPreview(null));
      }
      if (initialValues?.signatureUrl) {
        fetchAuthenticatedAvatarUrl(initialValues.signatureUrl)
          .then((url) => {
            if (url) setSignaturePreview(url);
          })
          .catch(() => setSignaturePreview(null));
      }
    }
  }, [open, initialValues, isEdit, loadLookups]);

  const rolesById = useMemo(
    () => Object.fromEntries(lookups.roles.map((role) => [role.id, role])),
    [lookups.roles],
  );

  const handleChange = (field, value) => {
    setForm((current) => ({ ...current, [field]: value }));
  };

  const handleRoleAssignmentChange = (index, field, value) => {
    setForm((current) => {
      const nextAssignments = [...current.roleAssignments];
      nextAssignments[index] = {
        ...nextAssignments[index],
        [field]: value,
        ...(field === 'roleId' ? { serviceId: null, departementId: null } : {}),
      };
      return { ...current, roleAssignments: nextAssignments };
    });
  };

  const addRoleAssignment = () => {
    setForm((current) => ({
      ...current,
      roleAssignments: [...current.roleAssignments, { ...EMPTY_ROLE_ASSIGNMENT }],
    }));
  };

  const removeRoleAssignment = (index) => {
    setForm((current) => ({
      ...current,
      roleAssignments: current.roleAssignments.filter((_, i) => i !== index),
    }));
  };

  const handleAvatarChange = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) {
      return;
    }

    const validationError = validateAvatarFile(file);
    if (validationError) {
      setAvatarError(validationError);
      return;
    }

    try {
      const preview = await readFilePreview(file);
      setAvatarFile(file);
      setAvatarPreview(preview);
      setRemoveAvatar(false);
      setAvatarError('');
    } catch (err) {
      setAvatarError(err.message || 'Impossible de charger la photo.');
    }
  };

  const handleRemoveAvatar = () => {
    setAvatarFile(null);
    setAvatarPreview(null);
    setRemoveAvatar(true);
    setAvatarError('');
  };

  const handleSignatureChange = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) {
      return;
    }
    const validationError = validateAvatarFile(file);
    if (validationError) {
      setAvatarError(validationError.replace('photo', 'signature'));
      return;
    }
    try {
      setSignatureFile(file);
      setSignaturePreview(await readFilePreview(file));
      setRemoveSignature(false);
      setAvatarError('');
    } catch (err) {
      setAvatarError(err.message || 'Impossible de charger la signature.');
    }
  };

  const handleRemoveSignature = () => {
    setSignatureFile(null);
    setSignaturePreview(null);
    setRemoveSignature(true);
  };

  const handleSubmit = (event) => {
    event.preventDefault();

    const payload = {
      nom: form.nom.trim(),
      postNom: form.postNom.trim(),
      prenom: form.prenom?.trim() || null,
      telephone: form.telephone.trim(),
      matricule: form.matricule.trim(),
      sexe: form.sexe,
      type: form.type,
      status: form.status,
      adresse: form.adresse?.trim() || null,
      lieuNaissance: form.lieuNaissance?.trim() || null,
      cnome: form.cnome?.trim() || null,
      gradeId: form.gradeId || null,
      serviceId: form.serviceId || null,
      specialiteIds: form.specialiteIds ?? [],
      roleAssignments: form.roleAssignments
        .filter((item) => item.roleId && rolesById[item.roleId]?.code !== 'PERSONNEL')
        .map((item) => ({
          roleId: item.roleId,
          serviceId: item.serviceId || null,
          departementId: item.departementId || null,
        })),
    };

    if (!isEdit || form.password?.trim()) {
      payload.password = form.password?.trim();
    }

    onSubmit(payload, {
      avatarFile,
      removeAvatar,
      signatureFile,
      removeSignature,
    });
  };

  const isBusy = loading || lookupsLoading;
  const showMedicalFields = isMedicalPersonnelType(form.type);

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog variant="outlined" sx={MODAL_SX}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider', flexShrink: 0 }}>
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
              <UserCog size={20} />
            </Box>
            <Box>
              <Typography level="title-lg" sx={{ fontWeight: 700 }}>
                {isEdit ? 'Modifier le personnel' : 'Nouveau personnel'}
              </Typography>
              <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
                {isEdit
                  ? 'Mettez à jour les informations et les rôles du personnel.'
                  : 'Créez un compte personnel avec ses informations et rôles.'}
              </Typography>
            </Box>
          </Stack>
        </Box>

        <Box
          component="form"
          onSubmit={handleSubmit}
          sx={{ display: 'flex', flexDirection: 'column', flex: 1, minHeight: 0 }}
        >
          <Box sx={{ flex: 1, minHeight: 0, overflow: 'auto', px: 3, py: 2.5 }}>
            <Stack spacing={2.5}>
              {(error || loadError || avatarError) ? (
                <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
                  {error || loadError || avatarError}
                </Typography>
              ) : null}

              <Typography level="title-sm" sx={{ fontWeight: 700 }}>Photo de profil</Typography>
              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} alignItems={{ sm: 'center' }}>
                <Box sx={{ position: 'relative', width: 'fit-content' }}>
                  {avatarPreview ? (
                    <Avatar src={avatarPreview} size="lg" sx={{ width: 96, height: 96, fontSize: 'xl' }} />
                  ) : (
                    <AuthAvatar
                      src={!removeAvatar ? initialValues?.avatarUrl : null}
                      fallback={getInitials(form)}
                      size="lg"
                      sx={{ width: 96, height: 96, fontSize: 'xl', bgcolor: 'primary.50', color: 'primary.700' }}
                    />
                  )}
                </Box>

                <Stack spacing={1}>
                  <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
                    <Button
                      component="label"
                      size="sm"
                      variant="outlined"
                      startDecorator={<Camera size={16} />}
                      disabled={isBusy}
                    >
                      Choisir une photo
                      <input
                        hidden
                        type="file"
                        accept={AVATAR_ACCEPT}
                        onChange={handleAvatarChange}
                        disabled={isBusy}
                      />
                    </Button>
                    {(avatarPreview || (!removeAvatar && initialValues?.avatarUrl)) ? (
                      <Button
                        size="sm"
                        variant="plain"
                        color="danger"
                        startDecorator={<X size={16} />}
                        onClick={handleRemoveAvatar}
                        disabled={isBusy}
                      >
                        Supprimer
                      </Button>
                    ) : null}
                  </Stack>
                  <Typography level="body-xs" sx={{ color: 'neutral.500' }}>
                    JPG, PNG ou WebP — 2 Mo maximum. La photo sera enregistrée avec le formulaire.
                  </Typography>
                </Stack>
              </Stack>

              <Typography level="title-sm" sx={{ fontWeight: 700 }}>Signature manuscrite</Typography>
              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} alignItems={{ sm: 'center' }}>
                <Box
                  sx={{
                    width: 160,
                    height: 72,
                    border: '1px dashed',
                    borderColor: 'neutral.300',
                    borderRadius: 'md',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    bgcolor: 'background.level1',
                    p: 1,
                  }}
                >
                  {signaturePreview && !removeSignature ? (
                    <Box component="img" src={signaturePreview} alt="Signature" sx={{ maxHeight: 56, maxWidth: '100%' }} />
                  ) : (
                    <Typography level="body-xs" sx={{ color: 'neutral.400' }}>Aucune</Typography>
                  )}
                </Box>
                <Stack spacing={1}>
                  <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
                    <Button component="label" size="sm" variant="outlined" disabled={isBusy}>
                      Choisir une signature
                      <input hidden type="file" accept={AVATAR_ACCEPT} onChange={handleSignatureChange} disabled={isBusy} />
                    </Button>
                    {(signaturePreview || (!removeSignature && initialValues?.signatureUrl)) ? (
                      <Button size="sm" variant="plain" color="danger" startDecorator={<X size={16} />} onClick={handleRemoveSignature} disabled={isBusy}>
                        Supprimer
                      </Button>
                    ) : null}
                  </Stack>
                  <Typography level="body-xs" sx={{ color: 'neutral.500' }}>
                    JPG, PNG ou WebP — 2 Mo. Insérée sur les documents signés par cet agent.
                  </Typography>
                </Stack>
              </Stack>

              <Divider />

              <Typography level="title-sm" sx={{ fontWeight: 700 }}>Identité</Typography>
              <Typography level="body-xs" sx={{ color: 'neutral.500', mt: -1.5 }}>
                Seuls le nom, le téléphone, le matricule et le mot de passe (à la création) sont obligatoires.
              </Typography>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Nom</FormLabel>
                  <Input value={form.nom} onChange={(e) => handleChange('nom', e.target.value)} disabled={isBusy} />
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Post-nom</FormLabel>
                  <Input value={form.postNom} onChange={(e) => handleChange('postNom', e.target.value)} disabled={isBusy} placeholder="Optionnel" />
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Prénom</FormLabel>
                  <Input value={form.prenom} onChange={(e) => handleChange('prenom', e.target.value)} disabled={isBusy} placeholder="Optionnel" />
                </FormControl>
              </Stack>

              <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Matricule</FormLabel>
                  <Input value={form.matricule} onChange={(e) => handleChange('matricule', e.target.value)} disabled={isBusy} />
                </FormControl>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Téléphone</FormLabel>
                  <Input value={form.telephone} onChange={(e) => handleChange('telephone', e.target.value)} disabled={isBusy} />
                </FormControl>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Sexe</FormLabel>
                  <Select value={form.sexe} onChange={(_, v) => handleChange('sexe', v)} disabled={isBusy}>
                    <Option value="M">Masculin</Option>
                    <Option value="F">Féminin</Option>
                  </Select>
                </FormControl>
              </Stack>

              <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Adresse</FormLabel>
                  <Input value={form.adresse} onChange={(e) => handleChange('adresse', e.target.value)} disabled={isBusy} placeholder="Optionnel" />
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Lieu de naissance</FormLabel>
                  <Input value={form.lieuNaissance} onChange={(e) => handleChange('lieuNaissance', e.target.value)} disabled={isBusy} placeholder="Optionnel" />
                </FormControl>
              </Stack>

              <Divider />

              <Typography level="title-sm" sx={{ fontWeight: 700 }}>Professionnel</Typography>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Type</FormLabel>
                  <Select value={form.type} onChange={(_, v) => handleChange('type', v)} disabled={isBusy}>
                    {PERSONNEL_TYPES.map((item) => (
                      <Option key={item.value} value={item.value}>{item.label}</Option>
                    ))}
                  </Select>
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Grade</FormLabel>
                  <Select
                    value={form.gradeId ?? ''}
                    onChange={(_, v) => handleChange('gradeId', v || null)}
                    placeholder="Aucun"
                    disabled={isBusy}
                  >
                    <Option value="">Aucun</Option>
                    {lookups.grades.map((grade) => (
                      <Option key={grade.id} value={grade.id}>{grade.libelle}</Option>
                    ))}
                  </Select>
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Service</FormLabel>
                  <Select
                    value={form.serviceId ?? ''}
                    onChange={(_, v) => handleChange('serviceId', v || null)}
                    placeholder="Aucun"
                    disabled={isBusy}
                  >
                    <Option value="">Aucun</Option>
                    {lookups.services.map((service) => (
                      <Option key={service.id} value={service.id}>{service.libelle}</Option>
                    ))}
                  </Select>
                </FormControl>
              </Stack>

              <FormControl>
                <FormLabel>Spécialités</FormLabel>
                <Select
                  multiple
                  value={form.specialiteIds ?? []}
                  onChange={(_, v) => handleChange('specialiteIds', v)}
                  disabled={isBusy}
                  placeholder="Optionnel"
                >
                  {lookups.specialites.map((specialite) => (
                    <Option key={specialite.id} value={specialite.id}>{specialite.libelle}</Option>
                  ))}
                </Select>
                <FormHelperText>Optionnel — réservé au personnel médical/paramédical.</FormHelperText>
              </FormControl>

              {showMedicalFields ? (
                <FormControl sx={{ maxWidth: { md: 360 } }}>
                  <FormLabel>N° ordre (CNOM)</FormLabel>
                  <Input
                    value={form.cnome}
                    onChange={(e) => handleChange('cnome', e.target.value)}
                    disabled={isBusy}
                    placeholder="Optionnel"
                  />
                  <FormHelperText>Numéro d&apos;inscription au Conseil National de l&apos;Ordre des Médecins.</FormHelperText>
                </FormControl>
              ) : null}

              <Divider />

              <Typography level="title-sm" sx={{ fontWeight: 700 }}>Compte</Typography>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                <FormControl required={!isEdit} sx={{ flex: 1 }}>
                  <FormLabel>Mot de passe</FormLabel>
                  <Input
                    type="password"
                    value={form.password}
                    onChange={(e) => handleChange('password', e.target.value)}
                    disabled={isBusy}
                    placeholder={isEdit ? 'Laisser vide pour ne pas changer' : ''}
                  />
                  {isEdit ? (
                    <FormHelperText>Laisser vide pour conserver le mot de passe actuel.</FormHelperText>
                  ) : null}
                </FormControl>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Statut</FormLabel>
                  <Select value={form.status} onChange={(_, v) => handleChange('status', v)} disabled={isBusy}>
                    {PERSONNEL_STATUSES.filter((s) => s.value !== 'SUPPRIME').map((item) => (
                      <Option key={item.value} value={item.value}>{item.label}</Option>
                    ))}
                  </Select>
                </FormControl>
              </Stack>

              <Divider />

              <Stack direction="row" justifyContent="space-between" alignItems="center">
                <Typography level="title-sm" sx={{ fontWeight: 700 }}>Rôles</Typography>
                <Button size="sm" variant="outlined" startDecorator={<Plus size={16} />} onClick={addRoleAssignment} disabled={isBusy}>
                  Ajouter un rôle
                </Button>
              </Stack>

              {form.roleAssignments.length === 0 ? (
                <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
                  Aucun rôle supplémentaire. Le rôle PERSONNEL sera assigné automatiquement.
                </Typography>
              ) : null}

              {form.roleAssignments.map((assignment, index) => {
                const selectedRole = rolesById[assignment.roleId];
                const perimetre = selectedRole?.perimetre;

                return (
                  <Stack
                    key={`assignment-${index}`}
                    direction={{ xs: 'column', md: 'row' }}
                    spacing={1.5}
                    alignItems={{ md: 'flex-end' }}
                    sx={{ p: 1.5, border: '1px solid', borderColor: 'divider', borderRadius: 'lg' }}
                  >
                    <FormControl required sx={{ flex: 1.4 }}>
                      <FormLabel>Rôle</FormLabel>
                      <Select
                        value={assignment.roleId}
                        onChange={(_, v) => handleRoleAssignmentChange(index, 'roleId', v ?? '')}
                        placeholder="Choisir un rôle"
                        disabled={isBusy}
                      >
                        {lookups.roles
                          .filter((role) => role.code !== 'PERSONNEL')
                          .map((role) => (
                          <Option key={role.id} value={role.id}>{role.libelle}</Option>
                        ))}
                      </Select>
                    </FormControl>

                    {perimetre === 'SERVICE' ? (
                      <FormControl required sx={{ flex: 1 }}>
                        <FormLabel>Service cible</FormLabel>
                        <Select
                          value={assignment.serviceId ?? ''}
                          onChange={(_, v) => handleRoleAssignmentChange(index, 'serviceId', v || null)}
                          disabled={isBusy}
                        >
                          {lookups.services.map((service) => (
                            <Option key={service.id} value={service.id}>{service.libelle}</Option>
                          ))}
                        </Select>
                      </FormControl>
                    ) : null}

                    {perimetre === 'DEPARTEMENT' ? (
                      <FormControl required sx={{ flex: 1 }}>
                        <FormLabel>Département cible</FormLabel>
                        <Select
                          value={assignment.departementId ?? ''}
                          onChange={(_, v) => handleRoleAssignmentChange(index, 'departementId', v || null)}
                          disabled={isBusy}
                        >
                          {lookups.departements.map((departement) => (
                            <Option key={departement.id} value={departement.id}>{departement.libelle}</Option>
                          ))}
                        </Select>
                      </FormControl>
                    ) : null}

                    <IconButton
                      variant="plain"
                      color="danger"
                      onClick={() => removeRoleAssignment(index)}
                      disabled={isBusy}
                      sx={{ alignSelf: { xs: 'flex-end', md: 'center' } }}
                    >
                      <Trash2 size={16} />
                    </IconButton>
                  </Stack>
                );
              })}
            </Stack>
          </Box>

          <Box sx={{ flexShrink: 0, px: 3, py: 2, borderTop: '1px solid', borderColor: 'divider', bgcolor: 'background.surface' }}>
            <Stack direction="row" spacing={1} justifyContent="flex-end">
              <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>
                Annuler
              </Button>
              <Button type="submit" loading={loading} disabled={isBusy}>
                {isEdit ? 'Enregistrer' : 'Créer le personnel'}
              </Button>
            </Stack>
          </Box>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
