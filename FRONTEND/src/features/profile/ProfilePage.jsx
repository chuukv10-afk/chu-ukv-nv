import { useEffect, useState } from 'react';
import { Alert, Box, Button, Card, Stack, Typography } from '@mui/joy';
import { PenLine, Trash2 } from 'lucide-react';
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
import { fetchMe } from '../auth/authService.js';
import { deleteMySignatureApi, uploadMySignatureApi } from './profileApi.js';

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
  const canUpdate = hasPermission(PERMISSIONS.ADMIN.SIGNATURE_UPDATE);

  const [preview, setPreview] = useState(null);
  const [saving, setSaving] = useState(false);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [error, setError] = useState('');

  const displayName = getDisplayName(profile);
  const hasSignature = Boolean(profile?.signatureUrl);

  const refreshProfile = async (previousUrl) => {
    if (previousUrl) {
      invalidateAvatarCache(previousUrl);
    }
    await fetchMe(dispatch);
  };

  const handleFile = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) {
      return;
    }

    const validationError = validateSignatureFile(file);
    if (validationError) {
      setError(validationError);
      return;
    }

    setSaving(true);
    setError('');
    try {
      setPreview(await readFilePreview(file));
      await uploadMySignatureApi(file);
      await refreshProfile(profile?.signatureUrl);
      setPreview(null);
      showSuccess('Signature enregistrée.');
    } catch (err) {
      setPreview(null);
      setError(err.message || 'Impossible d\'enregistrer la signature.');
      showError(err.message || 'Impossible d\'enregistrer la signature.');
    } finally {
      setSaving(false);
    }
  };

  const confirmDelete = async () => {
    setDeleteLoading(true);
    try {
      const previousUrl = profile?.signatureUrl;
      await deleteMySignatureApi();
      await refreshProfile(previousUrl);
      setPreview(null);
      setDeleteOpen(false);
      showSuccess('Signature supprimée.');
    } catch (err) {
      showError(err.message || 'Suppression impossible.');
    } finally {
      setDeleteLoading(false);
    }
  };

  return (
    <Stack spacing={2}>
      <Box>
        <Typography level="h2" sx={{ fontWeight: 700 }}>Mon profil</Typography>
        <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
          Liez votre signature manuscrite : elle sera insérée automatiquement sur les documents que vous signez.
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

      <Card variant="outlined">
        <Stack spacing={2}>
          <Typography level="title-md">Signature manuscrite</Typography>
          <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
            Image JPG, PNG ou WebP — 2 Mo maximum. Fond transparent recommandé (PNG).
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
            <SignaturePreview src={profile?.signatureUrl} localSrc={preview} />
          </Box>

          {canUpdate ? (
            <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
              <Button component="label" startDecorator={<PenLine size={16} />} loading={saving} disabled={saving}>
                {hasSignature ? 'Remplacer la signature' : 'Lier ma signature'}
                <input hidden type="file" accept={AVATAR_ACCEPT} onChange={handleFile} disabled={saving} />
              </Button>
              {hasSignature ? (
                <Button
                  color="danger"
                  variant="plain"
                  startDecorator={<Trash2 size={16} />}
                  disabled={saving}
                  onClick={() => setDeleteOpen(true)}
                >
                  Supprimer
                </Button>
              ) : null}
            </Stack>
          ) : (
            <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
              Vous n’avez pas la permission de modifier la signature.
            </Typography>
          )}
        </Stack>
      </Card>

      <ConfirmModal
        open={deleteOpen}
        title="Supprimer la signature ?"
        message="Les prochains documents que vous signerez seront générés sans image de signature."
        confirmLabel="Supprimer"
        loading={deleteLoading}
        onClose={() => { if (!deleteLoading) setDeleteOpen(false); }}
        onConfirm={confirmDelete}
      />
    </Stack>
  );
}
