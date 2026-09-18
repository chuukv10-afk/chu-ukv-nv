import { useEffect, useState } from 'react';
import {
  Alert, Box, Button, Card, FormControl, FormHelperText, FormLabel, Input, Radio, RadioGroup, Stack, Tab, TabList, TabPanel, Tabs, Typography,
} from '@mui/joy';
import { Camera, Eye, EyeOff, PenLine, Save, Trash2 } from 'lucide-react';
import ConfirmModal from '../../components/ui/ConfirmModal.jsx';
import AuthAvatar from '../../components/ui/AuthAvatar.jsx';
import { PERMISSIONS } from '../../constants/permissions.js';
import { useAppDispatch } from '../../hooks/useAppStore.js';
import { useAuth } from '../../hooks/useAuth.js';
import { usePermissions } from '../../hooks/usePermissions.js';
import { useToast } from '../../hooks/useToast.js';
import {
  AVATAR_ACCEPT,
  fetchAuthenticatedAvatarUrl,
  invalidateAvatarCache,
  readFilePreview,
  validateAvatarFile,
} from '../../utils/avatar.js';
import { getDisplayName, getInitials, getPersonnelTypeLabel } from '../../utils/profile.js';
import {
  PERSONNEL_STATUS_LABELS,
  PERSONNEL_TYPE_LABELS,
} from '../admin/personnel/personnelConstants.js';
import { fetchMe } from '../auth/authService.js';
import {
  changeMyPasswordApi,
  deleteMyAvatarApi,
  deleteMySignatureApi,
  updateMyProfileApi,
  uploadMyAvatarApi,
  uploadMySignatureApi,
} from './profileApi.js';

const TABS = {
  IDENTITE: 'identite',
  AUTH: 'auth',
  AFFECTATION: 'affectation',
  PHOTO: 'photo',
  SIGNATURE: 'signature',
};

const FIELD_SX = {
  width: '100%',
  minWidth: 0,
  '--Input-minHeight': { xs: '44px', md: '36px' },
  '--Select-minHeight': { xs: '44px', md: '36px' },
  fontSize: { xs: '16px', md: '14px' },
};

function emptyIdentity(profile) {
  return {
    nom: profile?.nom ?? '',
    postNom: profile?.postNom ?? '',
    prenom: profile?.prenom ?? '',
    sexe: profile?.sexe === 'F' ? 'F' : 'M',
    adresse: profile?.adresse ?? '',
    lieuNaissance: profile?.lieuNaissance ?? '',
  };
}

function validateSignatureFile(file) {
  const error = validateAvatarFile(file);
  return error ? error.replace('photo', 'signature') : null;
}

function SignaturePreview({ src, localSrc }) {
  const [blobUrl, setBlobUrl] = useState(null);

  useEffect(() => {
    let cancelled = false;
    if (localSrc || !src) {
      setBlobUrl(null);
      return undefined;
    }
    fetchAuthenticatedAvatarUrl(src)
      .then((url) => { if (!cancelled) setBlobUrl(url); })
      .catch(() => { if (!cancelled) setBlobUrl(null); });
    return () => { cancelled = true; };
  }, [src, localSrc]);

  const display = localSrc || blobUrl;
  if (!display) {
    return (
      <Typography level="body-sm" sx={{ color: 'neutral.400' }}>
        Aucune signature liée
      </Typography>
    );
  }

  return (
    <Box
      component="img"
      src={display}
      alt="Signature"
      sx={{ maxHeight: 80, maxWidth: '100%', objectFit: 'contain' }}
    />
  );
}

export default function ProfilePage() {
  const dispatch = useAppDispatch();
  const { profile } = useAuth();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canViewIdentite = hasPermission(PERMISSIONS.ADMIN.PROFIL_IDENTITE_READ) || hasPermission(PERMISSIONS.ADMIN.PROFIL_IDENTITE_UPDATE);
  const canUpdateIdentite = hasPermission(PERMISSIONS.ADMIN.PROFIL_IDENTITE_UPDATE);
  const canViewAuth = hasPermission(PERMISSIONS.ADMIN.PROFIL_AUTH_READ) || hasPermission(PERMISSIONS.ADMIN.PROFIL_AUTH_UPDATE);
  const canUpdateAuth = hasPermission(PERMISSIONS.ADMIN.PROFIL_AUTH_UPDATE);
  const canViewAffectation = hasPermission(PERMISSIONS.ADMIN.PROFIL_AFFECTATION_READ);
  const canViewPhoto = hasPermission(PERMISSIONS.ADMIN.PROFIL_PHOTO_READ) || hasPermission(PERMISSIONS.ADMIN.PROFIL_PHOTO_UPDATE);
  const canUpdatePhoto = hasPermission(PERMISSIONS.ADMIN.PROFIL_PHOTO_UPDATE);
  const canViewSignature = hasPermission(PERMISSIONS.ADMIN.SIGNATURE_READ) || hasPermission(PERMISSIONS.ADMIN.SIGNATURE_UPDATE);
  const canUpdateSignature = hasPermission(PERMISSIONS.ADMIN.SIGNATURE_UPDATE);

  const [tab, setTab] = useState(TABS.IDENTITE);
  const [identity, setIdentity] = useState(() => emptyIdentity(profile));
  const [identitySaving, setIdentitySaving] = useState(false);
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [newPasswordConfirm, setNewPasswordConfirm] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [passwordSaving, setPasswordSaving] = useState(false);
  const [avatarPreview, setAvatarPreview] = useState(null);
  const [avatarSaving, setAvatarSaving] = useState(false);
  const [signaturePreview, setSignaturePreview] = useState(null);
  const [signatureSaving, setSignatureSaving] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    setIdentity(emptyIdentity(profile));
  }, [profile]);

  useEffect(() => {
    const available = [
      canViewIdentite ? TABS.IDENTITE : null,
      canViewAuth ? TABS.AUTH : null,
      canViewAffectation ? TABS.AFFECTATION : null,
      canViewPhoto ? TABS.PHOTO : null,
      canViewSignature ? TABS.SIGNATURE : null,
    ].filter(Boolean);
    if (available.length > 0 && !available.includes(tab)) {
      setTab(available[0]);
    }
  }, [canViewIdentite, canViewAuth, canViewAffectation, canViewPhoto, canViewSignature, tab]);

  const displayName = getDisplayName(profile);
  const hasAvatar = Boolean(profile?.avatarUrl);
  const hasSignature = Boolean(profile?.signatureUrl);

  const setIdentityField = (key, value) => setIdentity((prev) => ({ ...prev, [key]: value }));

  const refreshProfile = async (previousUrl) => {
    if (previousUrl) invalidateAvatarCache(previousUrl);
    await fetchMe(dispatch);
  };

  const handleIdentitySave = async () => {
    if (!canUpdateIdentite) return;
    if (!identity.nom.trim()) {
      setError('Le nom est obligatoire.');
      setTab(TABS.IDENTITE);
      return;
    }
    setIdentitySaving(true);
    setError('');
    try {
      await updateMyProfileApi({
        nom: identity.nom.trim(),
        postNom: identity.postNom.trim() || null,
        prenom: identity.prenom.trim() || null,
        sexe: identity.sexe,
        adresse: identity.adresse.trim() || null,
        lieuNaissance: identity.lieuNaissance.trim() || null,
      });
      await refreshProfile();
      showSuccess('Informations personnelles enregistrées.');
    } catch (err) {
      setError(err.message || 'Impossible d’enregistrer le profil.');
      showError(err.message || 'Impossible d’enregistrer le profil.');
    } finally {
      setIdentitySaving(false);
    }
  };

  const handlePasswordSave = async () => {
    if (!canUpdateAuth) return;
    if (!currentPassword || !newPassword || !newPasswordConfirm) {
      setError('Renseignez le mot de passe actuel et le nouveau mot de passe.');
      setTab(TABS.AUTH);
      return;
    }
    if (newPassword !== newPasswordConfirm) {
      setError('Les deux mots de passe ne correspondent pas.');
      setTab(TABS.AUTH);
      return;
    }
    setPasswordSaving(true);
    setError('');
    try {
      await changeMyPasswordApi({
        currentPassword,
        newPassword,
        newPasswordConfirm,
      });
      setCurrentPassword('');
      setNewPassword('');
      setNewPasswordConfirm('');
      showSuccess('Mot de passe mis à jour.');
    } catch (err) {
      setError(err.message || 'Impossible de changer le mot de passe.');
      showError(err.message || 'Impossible de changer le mot de passe.');
    } finally {
      setPasswordSaving(false);
    }
  };

  const handleAvatarFile = async (event) => {
    if (!canUpdatePhoto) return;
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    const validationError = validateAvatarFile(file);
    if (validationError) {
      setError(validationError);
      setTab(TABS.PHOTO);
      return;
    }
    setAvatarSaving(true);
    setError('');
    try {
      setAvatarPreview(await readFilePreview(file));
      await uploadMyAvatarApi(file);
      await refreshProfile(profile?.avatarUrl);
      setAvatarPreview(null);
      showSuccess('Photo de profil enregistrée.');
    } catch (err) {
      setAvatarPreview(null);
      setError(err.message || 'Impossible d’enregistrer la photo.');
      showError(err.message || 'Impossible d’enregistrer la photo.');
    } finally {
      setAvatarSaving(false);
    }
  };

  const handleSignatureFile = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    const validationError = validateSignatureFile(file);
    if (validationError) {
      setError(validationError);
      setTab(TABS.SIGNATURE);
      return;
    }
    setSignatureSaving(true);
    setError('');
    try {
      setSignaturePreview(await readFilePreview(file));
      await uploadMySignatureApi(file);
      await refreshProfile(profile?.signatureUrl);
      setSignaturePreview(null);
      showSuccess('Signature enregistrée.');
    } catch (err) {
      setSignaturePreview(null);
      setError(err.message || 'Impossible d’enregistrer la signature.');
      showError(err.message || 'Impossible d’enregistrer la signature.');
    } finally {
      setSignatureSaving(false);
    }
  };

  const confirmDelete = async () => {
    if (!deleteTarget) return;
    setDeleteLoading(true);
    try {
      if (deleteTarget === 'avatar') {
        const previousUrl = profile?.avatarUrl;
        await deleteMyAvatarApi();
        await refreshProfile(previousUrl);
        setAvatarPreview(null);
        showSuccess('Photo de profil supprimée.');
      } else {
        const previousUrl = profile?.signatureUrl;
        await deleteMySignatureApi();
        await refreshProfile(previousUrl);
        setSignaturePreview(null);
        showSuccess('Signature supprimée.');
      }
      setDeleteTarget(null);
    } catch (err) {
      showError(err.message || 'Suppression impossible.');
    } finally {
      setDeleteLoading(false);
    }
  };

  return (
    <Stack spacing={2}>
      <Box>
        <Typography level="h2" sx={{ fontWeight: 700, fontSize: { xs: '1.35rem', md: '1.75rem' } }}>
          Mon profil
        </Typography>
        <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
          Chaque onglet est soumis à une permission. L’administration peut autoriser une section pour une période donnée.
        </Typography>
      </Box>

      <Card variant="outlined">
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} alignItems={{ sm: 'center' }}>
          <AuthAvatar
            src={profile?.avatarUrl}
            fallback={getInitials(profile)}
            size="lg"
            sx={{ width: 72, height: 72, fontSize: 'xl' }}
          />
          <Box>
            <Typography level="title-lg">{displayName}</Typography>
            <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
              {[getPersonnelTypeLabel(profile?.type), profile?.matricule, profile?.grade].filter(Boolean).join(' · ')}
            </Typography>
            {profile?.service ? (
              <Typography level="body-sm" sx={{ color: 'neutral.500' }}>{profile.service}</Typography>
            ) : null}
          </Box>
        </Stack>
      </Card>

      {error ? <Alert color="danger" variant="soft">{error}</Alert> : null}

      <Card variant="outlined" sx={{ p: { xs: 1.5, md: 2 } }}>
        <Tabs
          value={tab}
          onChange={(_, value) => {
            if (value === TABS.IDENTITE && !canViewIdentite) return;
            if (value === TABS.AUTH && !canViewAuth) return;
            if (value === TABS.AFFECTATION && !canViewAffectation) return;
            if (value === TABS.PHOTO && !canViewPhoto) return;
            if (value === TABS.SIGNATURE && !canViewSignature) return;
            if (value) setTab(value);
          }}
        >
          <TabList
            sx={{
              overflowX: 'auto',
              flexWrap: 'nowrap',
              WebkitOverflowScrolling: 'touch',
              '&::-webkit-scrollbar': { height: 0 },
            }}
          >
            <Tab value={TABS.IDENTITE} disabled={!canViewIdentite} title={!canViewIdentite ? 'Permission requise pour consulter les informations personnelles' : undefined} sx={{ flex: '0 0 auto', minHeight: 44, whiteSpace: 'nowrap' }}>Informations personnelles</Tab>
            <Tab value={TABS.AUTH} disabled={!canViewAuth} title={!canViewAuth ? 'Permission requise pour consulter l’authentification' : undefined} sx={{ flex: '0 0 auto', minHeight: 44, whiteSpace: 'nowrap' }}>Authentification</Tab>
            <Tab value={TABS.AFFECTATION} disabled={!canViewAffectation} title={!canViewAffectation ? 'Permission requise pour consulter l’affectation' : undefined} sx={{ flex: '0 0 auto', minHeight: 44, whiteSpace: 'nowrap' }}>Affectation</Tab>
            <Tab value={TABS.PHOTO} disabled={!canViewPhoto} title={!canViewPhoto ? 'Permission requise pour consulter la photo' : undefined} sx={{ flex: '0 0 auto', minHeight: 44, whiteSpace: 'nowrap' }}>Photo de profil</Tab>
            <Tab
              value={TABS.SIGNATURE}
              disabled={!canViewSignature}
              title={!canViewSignature ? 'Permission requise pour consulter la signature' : undefined}
              sx={{ flex: '0 0 auto', minHeight: 44, whiteSpace: 'nowrap' }}
            >
              Signature
            </Tab>
          </TabList>

          <TabPanel value={TABS.IDENTITE} sx={{ px: 0, pt: 2.5 }}>
            <Stack spacing={2}>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Nom</FormLabel>
                  <Input value={identity.nom} sx={FIELD_SX} disabled={!canUpdateIdentite} onChange={(e) => setIdentityField('nom', e.target.value)} />
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Post-nom</FormLabel>
                  <Input value={identity.postNom} sx={FIELD_SX} disabled={!canUpdateIdentite} onChange={(e) => setIdentityField('postNom', e.target.value)} />
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Prénom</FormLabel>
                  <Input value={identity.prenom} sx={FIELD_SX} disabled={!canUpdateIdentite} onChange={(e) => setIdentityField('prenom', e.target.value)} />
                </FormControl>
              </Stack>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Sexe</FormLabel>
                  <RadioGroup
                    orientation="horizontal"
                    value={identity.sexe}
                    onChange={(e) => setIdentityField('sexe', e.target.value)}
                    sx={{ gap: 2, minHeight: 44, alignItems: 'center' }}
                  >
                    <Radio value="M" label="Masculin" disabled={!canUpdateIdentite} />
                    <Radio value="F" label="Féminin" disabled={!canUpdateIdentite} />
                  </RadioGroup>
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Lieu de naissance</FormLabel>
                  <Input value={identity.lieuNaissance} sx={FIELD_SX} disabled={!canUpdateIdentite} onChange={(e) => setIdentityField('lieuNaissance', e.target.value)} />
                </FormControl>
              </Stack>
              <FormControl>
                <FormLabel>Adresse</FormLabel>
                <Input value={identity.adresse} sx={FIELD_SX} disabled={!canUpdateIdentite} onChange={(e) => setIdentityField('adresse', e.target.value)} />
              </FormControl>
              {canUpdateIdentite ? (
                <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="flex-end">
                  <Button size="lg" startDecorator={<Save size={16} />} loading={identitySaving} onClick={handleIdentitySave}>
                    Enregistrer
                  </Button>
                </Stack>
              ) : (
                <FormHelperText>Consultation uniquement. Permission requise pour modifier cette section.</FormHelperText>
              )}
            </Stack>
          </TabPanel>

          <TabPanel value={TABS.AUTH} sx={{ px: 0, pt: 2.5 }}>
            <Stack spacing={2}>
              <FormControl>
                <FormLabel>Téléphone (identifiant)</FormLabel>
                <Input value={profile?.telephone ?? ''} disabled sx={FIELD_SX} />
                <FormHelperText>Le numéro de connexion ne peut pas être modifié ici. Contactez l’administration.</FormHelperText>
              </FormControl>
              <FormControl required>
                <FormLabel>Mot de passe actuel</FormLabel>
                <Input
                  type={showPassword ? 'text' : 'password'}
                  value={currentPassword}
                  sx={FIELD_SX}
                  disabled={!canUpdateAuth}
                  onChange={(e) => setCurrentPassword(e.target.value)}
                  endDecorator={(
                    <Button
                      size="sm"
                      variant="plain"
                      color="neutral"
                      disabled={!canUpdateAuth}
                      onClick={() => setShowPassword((current) => !current)}
                    >
                      {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                    </Button>
                  )}
                />
              </FormControl>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Nouveau mot de passe</FormLabel>
                  <Input type={showPassword ? 'text' : 'password'} value={newPassword} sx={FIELD_SX} disabled={!canUpdateAuth} onChange={(e) => setNewPassword(e.target.value)} />
                </FormControl>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Confirmer</FormLabel>
                  <Input type={showPassword ? 'text' : 'password'} value={newPasswordConfirm} sx={FIELD_SX} disabled={!canUpdateAuth} onChange={(e) => setNewPasswordConfirm(e.target.value)} />
                </FormControl>
              </Stack>
              <FormHelperText>Au moins 6 caractères. Le téléphone de connexion ne peut pas être modifié ici.</FormHelperText>
              {canUpdateAuth ? (
                <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="flex-end">
                  <Button size="lg" startDecorator={<Save size={16} />} loading={passwordSaving} onClick={handlePasswordSave}>
                    Changer le mot de passe
                  </Button>
                </Stack>
              ) : (
                <FormHelperText>Permission requise pour modifier le mot de passe.</FormHelperText>
              )}
            </Stack>
          </TabPanel>

          <TabPanel value={TABS.AFFECTATION} sx={{ px: 0, pt: 2.5 }}>
            <Stack spacing={2}>
              <Alert variant="soft" color="neutral">
                L’affectation est gérée par l’administration. Ces informations sont consultables uniquement.
              </Alert>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Matricule</FormLabel>
                  <Input value={profile?.matricule || '—'} disabled sx={FIELD_SX} />
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Type</FormLabel>
                  <Input value={PERSONNEL_TYPE_LABELS[profile?.type] || profile?.type || '—'} disabled sx={FIELD_SX} />
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Statut</FormLabel>
                  <Input value={PERSONNEL_STATUS_LABELS[profile?.status] || profile?.status || '—'} disabled sx={FIELD_SX} />
                </FormControl>
              </Stack>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Grade</FormLabel>
                  <Input value={profile?.grade || '—'} disabled sx={FIELD_SX} />
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Fonction</FormLabel>
                  <Input value={profile?.fonction || '—'} disabled sx={FIELD_SX} />
                </FormControl>
              </Stack>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Département</FormLabel>
                  <Input value={profile?.departement || '—'} disabled sx={FIELD_SX} />
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Service</FormLabel>
                  <Input value={profile?.service || '—'} disabled sx={FIELD_SX} />
                </FormControl>
              </Stack>
            </Stack>
          </TabPanel>

          <TabPanel value={TABS.PHOTO} sx={{ px: 0, pt: 2.5 }}>
            <Stack spacing={2} alignItems={{ xs: 'stretch', sm: 'flex-start' }}>
              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} alignItems={{ sm: 'center' }}>
                {avatarPreview ? (
                  <Box
                    component="img"
                    src={avatarPreview}
                    alt="Aperçu"
                    sx={{ width: 96, height: 96, borderRadius: '50%', objectFit: 'cover' }}
                  />
                ) : (
                  <AuthAvatar
                    src={profile?.avatarUrl}
                    fallback={getInitials(profile)}
                    sx={{ width: 96, height: 96, fontSize: 'xl' }}
                  />
                )}
                <Stack spacing={1}>
                  <Typography level="title-sm" sx={{ fontWeight: 700 }}>
                    Photo de profil
                  </Typography>
                  <Typography level="body-xs" sx={{ color: 'neutral.500' }}>
                    JPG, PNG ou WebP — 50 Mo maximum.
                  </Typography>
                </Stack>
              </Stack>
              <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
                <Button
                  component="label"
                  startDecorator={<Camera size={16} />}
                  loading={avatarSaving}
                  disabled={!canUpdatePhoto || avatarSaving}
                  title={!canUpdatePhoto ? 'Permission requise pour modifier la photo' : undefined}
                >
                  {hasAvatar ? 'Remplacer la photo' : 'Choisir une photo'}
                  <input hidden type="file" accept={AVATAR_ACCEPT} onChange={handleAvatarFile} disabled={!canUpdatePhoto || avatarSaving} />
                </Button>
                {hasAvatar ? (
                  <Button
                    color="danger"
                    variant="plain"
                    startDecorator={<Trash2 size={16} />}
                    disabled={!canUpdatePhoto || avatarSaving}
                    title={!canUpdatePhoto ? 'Permission requise pour modifier la photo' : undefined}
                    onClick={() => { if (canUpdatePhoto) setDeleteTarget('avatar'); }}
                  >
                    Supprimer
                  </Button>
                ) : null}
              </Stack>
            </Stack>
          </TabPanel>

          <TabPanel value={TABS.SIGNATURE} sx={{ px: 0, pt: 2.5 }}>
            <Stack spacing={2}>
              <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
                Image JPG, PNG ou WebP — 50 Mo maximum. Fond transparent recommandé (PNG).
              </Typography>
              <Box
                sx={{
                  minHeight: 96,
                  maxWidth: 320,
                  border: '1px dashed',
                  borderColor: 'neutral.300',
                  borderRadius: 'md',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  bgcolor: 'background.level1',
                  p: 1.5,
                }}
              >
                <SignaturePreview src={profile?.signatureUrl} localSrc={signaturePreview} />
              </Box>
              <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
                <Button
                  component="label"
                  startDecorator={<PenLine size={16} />}
                  loading={signatureSaving}
                  disabled={!canUpdateSignature || signatureSaving}
                  title={!canUpdateSignature ? 'Permission requise pour modifier la signature' : undefined}
                >
                  {hasSignature ? 'Remplacer la signature' : 'Lier ma signature'}
                  <input hidden type="file" accept={AVATAR_ACCEPT} onChange={handleSignatureFile} disabled={!canUpdateSignature || signatureSaving} />
                </Button>
                {hasSignature ? (
                  <Button
                    color="danger"
                    variant="plain"
                    startDecorator={<Trash2 size={16} />}
                    disabled={!canUpdateSignature || signatureSaving}
                    title={!canUpdateSignature ? 'Permission requise pour modifier la signature' : undefined}
                    onClick={() => { if (canUpdateSignature) setDeleteTarget('signature'); }}
                  >
                    Supprimer
                  </Button>
                ) : null}
              </Stack>
            </Stack>
          </TabPanel>
        </Tabs>
      </Card>

      <ConfirmModal
        open={Boolean(deleteTarget)}
        title={deleteTarget === 'avatar' ? 'Supprimer la photo ?' : 'Supprimer la signature ?'}
        message={deleteTarget === 'avatar'
          ? 'Votre photo de profil sera retirée.'
          : 'Les prochains documents que vous signerez seront générés sans image de signature.'}
        confirmLabel="Supprimer"
        loading={deleteLoading}
        onClose={() => { if (!deleteLoading) setDeleteTarget(null); }}
        onConfirm={confirmDelete}
      />
    </Stack>
  );
}
