import { useCallback, useEffect, useState } from 'react';
import {
  Box,
  Button,
  Chip,
  FormControl,
  FormHelperText,
  FormLabel,
  Modal,
  ModalDialog,
  Option,
  Select,
  Stack,
  Typography,
} from '@mui/joy';
import { Link2, Shield } from 'lucide-react';
import PermissionPickerPanel from '../../components/PermissionPickerPanel.jsx';
import { fetchRolesApi } from '../../roles/rolesApi.js';
import { fetchRolePermissionAssignmentApi } from '../rolePermissionsApi.js';

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

export default function RolePermissionFormModal({
  open,
  mode = 'create',
  assignment = null,
  loading = false,
  error = '',
  onClose,
  onSubmit,
}) {
  const isEdit = mode === 'edit';
  const [roles, setRoles] = useState([]);
  const [rolesLoading, setRolesLoading] = useState(false);
  const [matrixLoading, setMatrixLoading] = useState(false);
  const [roleId, setRoleId] = useState('');
  const [permissions, setPermissions] = useState([]);
  const [selectedIds, setSelectedIds] = useState(new Set());
  const [loadError, setLoadError] = useState('');

  const loadRoles = useCallback(async () => {
    setRolesLoading(true);
    try {
      const data = await fetchRolesApi();
      setRoles(data);
    } catch (err) {
      setLoadError(err.message || 'Impossible de charger les rôles.');
    } finally {
      setRolesLoading(false);
    }
  }, []);

  const loadMatrix = useCallback(async (targetRoleId) => {
    if (!targetRoleId) {
      setPermissions([]);
      setSelectedIds(new Set());
      return;
    }

    setMatrixLoading(true);
    setLoadError('');

    try {
      const data = await fetchRolePermissionAssignmentApi(targetRoleId);
      setPermissions(Array.isArray(data?.permissions) ? data.permissions : []);
      setSelectedIds(new Set(data?.assignedPermissionIds ?? []));
    } catch (err) {
      setLoadError(err.message || 'Impossible de charger les permissions du rôle.');
    } finally {
      setMatrixLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!open) {
      return;
    }

    setLoadError('');
    loadRoles();

    if (isEdit && assignment?.role?.id) {
      setRoleId(assignment.role.id);
      loadMatrix(assignment.role.id);
      return;
    }

    setRoleId('');
    setPermissions([]);
    setSelectedIds(new Set());
  }, [open, isEdit, assignment, loadRoles, loadMatrix]);

  useEffect(() => {
    if (open && !isEdit && roleId) {
      loadMatrix(roleId);
    }
  }, [open, isEdit, roleId, loadMatrix]);

  const handleRoleChange = (_, value) => {
    setRoleId(value ?? '');
  };

  const handleSubmit = (event) => {
    event.preventDefault();

    if (!roleId) {
      return;
    }

    onSubmit({
      roleId,
      permissionIds: Array.from(selectedIds),
    });
  };

  const isBusy = loading || rolesLoading || matrixLoading;
  const selectedRole = roles.find((role) => role.id === roleId) ?? assignment?.role;

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
              <Link2 size={20} />
            </Box>
            <Box sx={{ flex: 1, minWidth: 0 }}>
              <Typography level="title-lg" sx={{ fontWeight: 700 }}>
                {isEdit ? 'Modifier l\'affectation' : 'Nouvelle affectation'}
              </Typography>
              <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
                {isEdit
                  ? 'Mettez à jour les permissions associées à ce rôle.'
                  : 'Sélectionnez un rôle et les permissions à lui attribuer.'}
              </Typography>
            </Box>
            <Chip size="sm" variant="soft" color="primary" startDecorator={<Shield size={14} />}>
              {selectedIds.size} sélectionnée{selectedIds.size > 1 ? 's' : ''}
            </Chip>
          </Stack>
        </Box>

        <Box
          component="form"
          onSubmit={handleSubmit}
          sx={{
            display: 'flex',
            flexDirection: 'column',
            flex: 1,
            minHeight: 0,
          }}
        >
          <Box sx={{ flex: 1, minHeight: 0, overflow: 'auto', px: 3, py: 2.5 }}>
            <Stack spacing={2} sx={{ height: roleId ? '100%' : 'auto', minHeight: roleId ? 320 : 'auto' }}>
              {(error || loadError) ? (
                <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
                  {error || loadError}
                </Typography>
              ) : null}

              <FormControl required>
                <FormLabel>Rôle</FormLabel>
                {isEdit ? (
                  <Typography level="body-sm" sx={{ fontWeight: 600, py: 0.75 }}>
                    {selectedRole ? `${selectedRole.code} — ${selectedRole.libelle}` : '—'}
                  </Typography>
                ) : (
                  <Select
                    value={roleId}
                    onChange={handleRoleChange}
                    placeholder="Choisir un rôle..."
                    disabled={isBusy}
                  >
                    {roles.map((role) => (
                      <Option key={role.id} value={role.id}>
                        {role.code} — {role.libelle}
                      </Option>
                    ))}
                  </Select>
                )}
                <FormHelperText>
                  {isEdit
                    ? 'Le rôle ne peut pas être modifié lors d\'une édition.'
                    : 'Choisissez le rôle cible de l\'affectation.'}
                </FormHelperText>
              </FormControl>

              {roleId ? (
                <PermissionPickerPanel
                  permissions={permissions}
                  selectedIds={selectedIds}
                  onSelectedIdsChange={setSelectedIds}
                  loading={matrixLoading}
                  fillHeight
                />
              ) : (
                <Typography level="body-sm" sx={{ color: 'neutral.500', textAlign: 'center', py: 4 }}>
                  Sélectionnez un rôle pour afficher les permissions disponibles.
                </Typography>
              )}
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
              <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>
                Annuler
              </Button>
              <Button type="submit" loading={loading} disabled={!roleId || isBusy}>
                {isEdit ? 'Enregistrer' : 'Créer l\'affectation'}
              </Button>
            </Stack>
          </Box>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
