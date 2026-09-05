import { useCallback, useEffect, useState } from 'react';
import {
  Box,
  Button,
  Chip,
  Modal,
  ModalDialog,
  Stack,
  Typography,
} from '@mui/joy';
import { KeyRound, Shield } from 'lucide-react';
import PermissionPickerPanel from '../../components/PermissionPickerPanel.jsx';
import { fetchRolePermissionsApi, syncRolePermissionsApi } from '../rolesApi.js';

const MODAL_SX = {
  borderRadius: 'xl',
  width: 'min(960px, calc(100vw - 32px))',
  maxWidth: '960px',
  maxHeight: 'min(92vh, 880px)',
  p: 0,
  overflow: 'hidden',
  boxShadow: 'lg',
  display: 'flex',
  flexDirection: 'column',
};

export default function RolePermissionsModal({
  open,
  role,
  loading = false,
  error = '',
  onClose,
  onSaved,
}) {
  const [matrixLoading, setMatrixLoading] = useState(false);
  const [saveLoading, setSaveLoading] = useState(false);
  const [loadError, setLoadError] = useState('');
  const [saveError, setSaveError] = useState('');
  const [permissions, setPermissions] = useState([]);
  const [selectedIds, setSelectedIds] = useState(new Set());

  const loadMatrix = useCallback(async () => {
    if (!role?.id) {
      return;
    }

    setMatrixLoading(true);
    setLoadError('');

    try {
      const data = await fetchRolePermissionsApi(role.id);
      setPermissions(Array.isArray(data?.permissions) ? data.permissions : []);
      setSelectedIds(new Set(data?.assignedPermissionIds ?? []));
    } catch (err) {
      setLoadError(err.message || 'Impossible de charger les permissions du rôle.');
    } finally {
      setMatrixLoading(false);
    }
  }, [role?.id]);

  useEffect(() => {
    if (open && role?.id) {
      setSaveError('');
      loadMatrix();
    }
  }, [open, role?.id, loadMatrix]);

  const handleSave = async () => {
    if (!role?.id) {
      return;
    }

    setSaveLoading(true);
    setSaveError('');

    try {
      await syncRolePermissionsApi(role.id, Array.from(selectedIds));
      onSaved?.();
      onClose();
    } catch (err) {
      setSaveError(err.message || 'Enregistrement impossible.');
    } finally {
      setSaveLoading(false);
    }
  };

  const isBusy = loading || matrixLoading || saveLoading;

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
              <KeyRound size={20} />
            </Box>
            <Box sx={{ flex: 1, minWidth: 0 }}>
              <Typography level="title-lg" sx={{ fontWeight: 700 }}>
                Permissions du rôle
              </Typography>
              <Typography level="body-sm" sx={{ color: 'neutral.500' }} noWrap>
                {role ? `${role.code} — ${role.libelle}` : ''}
              </Typography>
            </Box>
            <Chip size="sm" variant="soft" color="primary" startDecorator={<Shield size={14} />}>
              {selectedIds.size} sélectionnée{selectedIds.size > 1 ? 's' : ''}
            </Chip>
          </Stack>
        </Box>

        <Box sx={{ flex: 1, minHeight: 0, overflow: 'auto', px: 3, py: 2.5 }}>
          <Stack spacing={2} sx={{ height: '100%', minHeight: 320 }}>
            {(error || loadError || saveError) ? (
              <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
                {error || loadError || saveError}
              </Typography>
            ) : null}

            <PermissionPickerPanel
              permissions={permissions}
              selectedIds={selectedIds}
              onSelectedIdsChange={setSelectedIds}
              loading={matrixLoading}
              fillHeight
            />
          </Stack>
        </Box>

        <Box
          sx={{
            flexShrink: 0,
            px: 3,
            py: 2,
            borderTop: '1px solid',
            borderColor: 'divider',
            bgcolor: 'background.surface',
          }}
        >
          <Stack direction="row" spacing={1} justifyContent="flex-end">
            <Button variant="plain" color="neutral" onClick={onClose} disabled={isBusy}>
              Annuler
            </Button>
            <Button loading={saveLoading} onClick={handleSave} disabled={matrixLoading}>
              Enregistrer les permissions
            </Button>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
