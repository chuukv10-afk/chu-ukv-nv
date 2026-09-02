import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Box,
  Button,
  Card,
  Chip,
  IconButton,
  Input,
  Sheet,
  Stack,
  Table,
  Typography,
} from '@mui/joy';
import { Pencil, Plus, Search, Shield, Trash2, Users } from 'lucide-react';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import RoleDeleteModal from './components/RoleDeleteModal.jsx';
import RoleFormModal from './components/RoleFormModal.jsx';
import { EMPTY_ROLE_FORM, ROLE_PERIMETRE_LABELS } from './roleConstants.js';
import {
  createRoleApi,
  deleteRoleApi,
  fetchRolesApi,
  updateRoleApi,
} from './rolesApi.js';

function PerimetreChip({ perimetre }) {
  const color = {
    GLOBAL: 'neutral',
    DEPARTEMENT: 'warning',
    SERVICE: 'primary',
  }[perimetre] ?? 'neutral';

  return (
    <Chip size="sm" variant="soft" color={color}>
      {ROLE_PERIMETRE_LABELS[perimetre] ?? perimetre}
    </Chip>
  );
}

function formatDate(value) {
  if (!value) {
    return '—';
  }

  return new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(value));
}

export default function RolesPage() {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.ADMIN.ROLE_CREATE);
  const canUpdate = hasPermission(PERMISSIONS.ADMIN.ROLE_UPDATE);
  const canDelete = hasPermission(PERMISSIONS.ADMIN.ROLE_DELETE);
  const showActions = canUpdate || canDelete;

  const [roles, setRoles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');

  const [formOpen, setFormOpen] = useState(false);
  const [formMode, setFormMode] = useState('create');
  const [formValues, setFormValues] = useState(EMPTY_ROLE_FORM);
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');
  const [editingRole, setEditingRole] = useState(null);

  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deletingRole, setDeletingRole] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [deleteError, setDeleteError] = useState('');

  const loadRoles = useCallback(async () => {
    setLoading(true);
    setListError('');

    try {
      const data = await fetchRolesApi();
      setRoles(data);
    } catch (error) {
      setListError(error.message || 'Impossible de charger les rôles.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadRoles();
  }, [loadRoles]);

  const filteredRoles = useMemo(() => {
    const query = search.trim().toLowerCase();
    if (!query) {
      return roles;
    }

    return roles.filter(
      (role) =>
        role.code?.toLowerCase().includes(query) ||
        role.libelle?.toLowerCase().includes(query) ||
        ROLE_PERIMETRE_LABELS[role.perimetre]?.toLowerCase().includes(query),
    );
  }, [roles, search]);

  const openCreate = () => {
    setFormMode('create');
    setEditingRole(null);
    setFormValues(EMPTY_ROLE_FORM);
    setFormError('');
    setFormOpen(true);
  };

  const openEdit = (role) => {
    setFormMode('edit');
    setEditingRole(role);
    setFormValues({
      code: role.code ?? '',
      libelle: role.libelle ?? '',
      perimetre: role.perimetre ?? 'GLOBAL',
      system: role.system,
      personnelCount: role.personnelCount,
    });
    setFormError('');
    setFormOpen(true);
  };

  const closeForm = () => {
    if (!formLoading) {
      setFormOpen(false);
    }
  };

  const handleSubmit = async (payload) => {
    setFormLoading(true);
    setFormError('');

    try {
      if (formMode === 'create') {
        await createRoleApi(payload);
        showSuccess('Rôle créé avec succès.');
      } else {
        await updateRoleApi(editingRole.id, payload);
        showSuccess('Rôle mis à jour avec succès.');
      }

      setFormOpen(false);
      await loadRoles();
    } catch (error) {
      setFormError(error.message || 'Enregistrement impossible.');
    } finally {
      setFormLoading(false);
    }
  };

  const openDelete = (role) => {
    setDeletingRole(role);
    setDeleteError('');
    setDeleteOpen(true);
  };

  const closeDelete = () => {
    if (!deleteLoading) {
      setDeleteOpen(false);
    }
  };

  const handleDelete = async () => {
    if (!deletingRole) {
      return;
    }

    setDeleteLoading(true);
    setDeleteError('');

    try {
      await deleteRoleApi(deletingRole.id);
      setDeleteOpen(false);
      showSuccess('Rôle supprimé avec succès.');
      await loadRoles();
    } catch (error) {
      setDeleteError(error.message || 'Suppression impossible.');
      showError(error.message || 'Suppression impossible.');
    } finally {
      setDeleteLoading(false);
    }
  };

  return (
    <Stack spacing={3}>
      <Stack
        direction={{ xs: 'column', sm: 'row' }}
        justifyContent="space-between"
        alignItems={{ xs: 'stretch', sm: 'flex-start' }}
        spacing={2}
      >
        <Box>
          <Typography level="h2" sx={{ fontWeight: 700, mb: 0.5 }}>
            Rôles
          </Typography>
          <Typography level="body-md" sx={{ color: 'neutral.500' }}>
            Gérez les rôles et leurs périmètres d&apos;affectation du personnel.
          </Typography>
        </Box>

        {canCreate ? (
          <Button startDecorator={<Plus size={18} />} onClick={openCreate} sx={{ alignSelf: { sm: 'center' } }}>
            Nouveau rôle
          </Button>
        ) : null}
      </Stack>

      <Card variant="outlined">
        <Stack spacing={2} sx={{ p: { xs: 2, md: 2.5 } }}>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} justifyContent="space-between">
            <Input
              size="sm"
              placeholder="Rechercher un rôle..."
              startDecorator={<Search size={16} />}
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              sx={{ maxWidth: { sm: 320 }, bgcolor: 'background.level1', border: 'none' }}
            />
            <Typography level="body-sm" sx={{ color: 'neutral.500', alignSelf: 'center' }}>
              {filteredRoles.length} rôle{filteredRoles.length > 1 ? 's' : ''}
            </Typography>
          </Stack>

          {listError ? (
            <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
              {listError}
            </Typography>
          ) : null}

          <Sheet
            variant="outlined"
            sx={{
              borderRadius: 'lg',
              overflow: 'auto',
              borderColor: LOTRU_NEUTRAL[200],
            }}
          >
            <Table
              stickyHeader
              hoverRow
              sx={{
                '--TableCell-headBackground': LOTRU_NEUTRAL[50],
                '--TableRow-hoverBackground': LOTRU_PRIMARY[50],
                tableLayout: { xs: 'fixed', md: 'auto' },
                width: '100%',
                '& thead th': {
                  fontWeight: 600,
                  color: 'neutral.600',
                  fontSize: '0.8125rem',
                },
                '& .roles-col-desktop': {
                  display: { xs: 'none', md: 'table-cell' },
                },
                '& .roles-col-label': {
                  width: { xs: '46%', md: '24%' },
                  pr: { xs: 2, md: 0 },
                  verticalAlign: 'middle',
                },
                '& .roles-col-perimetre': {
                  width: { xs: '32%', md: '14%' },
                  pl: { xs: 1, md: 0 },
                  pr: { xs: 1.5, md: 0 },
                  whiteSpace: 'nowrap',
                  verticalAlign: 'middle',
                },
                '& .roles-col-actions': {
                  width: { xs: '22%', md: '96px' },
                  textAlign: 'right',
                  whiteSpace: 'nowrap',
                  verticalAlign: 'middle',
                },
              }}
            >
              <thead>
                <tr>
                  <th className="roles-col-desktop" style={{ width: '14%' }}>Code</th>
                  <th className="roles-col-label">Libellé</th>
                  <th className="roles-col-perimetre">Périmètre</th>
                  <th className="roles-col-desktop" style={{ width: '10%' }}>Permissions</th>
                  <th className="roles-col-desktop" style={{ width: '10%' }}>Personnel</th>
                  <th className="roles-col-desktop" style={{ width: '12%' }}>Créé le</th>
                  <th className="roles-col-desktop" style={{ width: '10%' }}>Type</th>
                  {showActions && <th className="roles-col-actions">Actions</th>}
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr>
                    <td colSpan={showActions ? 8 : 7}>
                      <Typography level="body-sm" sx={{ py: 3, textAlign: 'center', color: 'neutral.500' }}>
                        Chargement des rôles...
                      </Typography>
                    </td>
                  </tr>
                ) : null}

                {!loading && filteredRoles.length === 0 ? (
                  <tr>
                    <td colSpan={showActions ? 8 : 7}>
                      <Stack alignItems="center" spacing={1} sx={{ py: 5 }}>
                        <Box
                          sx={{
                            width: 48,
                            height: 48,
                            borderRadius: 'md',
                            bgcolor: 'primary.50',
                            color: 'primary.600',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                          }}
                        >
                          <Shield size={22} />
                        </Box>
                        <Typography level="title-sm" sx={{ fontWeight: 600 }}>
                          Aucun rôle trouvé
                        </Typography>
                        <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
                          {search ? 'Essayez un autre terme de recherche.' : 'Commencez par créer un rôle.'}
                        </Typography>
                      </Stack>
                    </td>
                  </tr>
                ) : null}

                {!loading
                  ? filteredRoles.map((role) => (
                      <tr key={role.id}>
                        <td className="roles-col-desktop">
                          <Typography level="title-sm" sx={{ fontWeight: 600 }}>
                            {role.code}
                          </Typography>
                        </td>
                        <td className="roles-col-label">
                          <Typography level="body-sm" sx={{ fontWeight: { xs: 600, md: 400 } }} noWrap>
                            {role.libelle}
                          </Typography>
                        </td>
                        <td className="roles-col-perimetre">
                          <PerimetreChip perimetre={role.perimetre} />
                        </td>
                        <td className="roles-col-desktop">
                          <Typography level="body-sm">{role.permissionsCount ?? 0}</Typography>
                        </td>
                        <td className="roles-col-desktop">
                          <Stack direction="row" spacing={0.5} alignItems="center">
                            <Users size={14} color={LOTRU_NEUTRAL[400]} />
                            <Typography level="body-sm">{role.personnelCount ?? 0}</Typography>
                          </Stack>
                        </td>
                        <td className="roles-col-desktop">
                          <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
                            {formatDate(role.createdAt)}
                          </Typography>
                        </td>
                        <td className="roles-col-desktop">
                          {role.system ? (
                            <Chip size="sm" variant="soft" color="primary">
                              Système
                            </Chip>
                          ) : (
                            <Chip size="sm" variant="outlined" color="neutral">
                              Custom
                            </Chip>
                          )}
                        </td>
                        {showActions && (
                          <td className="roles-col-actions">
                            <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                              {canUpdate ? (
                                <IconButton
                                  size="sm"
                                  variant="plain"
                                  color="neutral"
                                  onClick={() => openEdit(role)}
                                  title="Modifier"
                                >
                                  <Pencil size={16} />
                                </IconButton>
                              ) : null}
                              {canDelete && !role.system ? (
                                <IconButton
                                  size="sm"
                                  variant="plain"
                                  color="danger"
                                  onClick={() => openDelete(role)}
                                  title="Supprimer"
                                  disabled={(role.personnelCount ?? 0) > 0}
                                >
                                  <Trash2 size={16} />
                                </IconButton>
                              ) : null}
                            </Stack>
                          </td>
                        )}
                      </tr>
                    ))
                  : null}
              </tbody>
            </Table>
          </Sheet>
        </Stack>
      </Card>

      <RoleFormModal
        open={formOpen}
        mode={formMode}
        initialValues={formValues}
        loading={formLoading}
        error={formError}
        onClose={closeForm}
        onSubmit={handleSubmit}
      />

      <RoleDeleteModal
        open={deleteOpen}
        role={deletingRole}
        loading={deleteLoading}
        error={deleteError}
        onClose={closeDelete}
        onConfirm={handleDelete}
      />
    </Stack>
  );
}
