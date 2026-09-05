import { useCallback, useEffect, useState } from 'react';
import {
  Box,
  Button,
  Card,
  Chip,
  IconButton,
  Input,
  Option,
  Select,
  Sheet,
  Stack,
  Table,
  Typography,
} from '@mui/joy';
import { Filter, KeyRound, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import PermissionDeleteModal from './components/PermissionDeleteModal.jsx';
import PermissionFormModal from './components/PermissionFormModal.jsx';
import {
  DEFAULT_PERMISSION_PAGE_SIZE,
  EMPTY_PERMISSION_FORM,
  PERMISSION_MODULE_LABELS,
  PERMISSION_MODULES,
  PERMISSION_PAGE_SIZE_OPTIONS,
} from './permissionConstants.js';
import {
  createPermissionApi,
  deletePermissionApi,
  fetchPermissionsApi,
  updatePermissionApi,
} from './permissionsApi.js';

function ModuleChip({ module }) {
  const color = {
    ADMIN: 'primary',
    ORGANISATION: 'success',
    REFERENTIEL: 'warning',
    CLINIQUE: 'neutral',
    PATIENT: 'neutral',
    FACTURATION: 'neutral',
  }[module] ?? 'neutral';

  return (
    <Chip size="sm" variant="soft" color={color}>
      {PERMISSION_MODULE_LABELS[module] ?? module}
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

const EMPTY_PAGINATION = {
  page: 1,
  limit: DEFAULT_PERMISSION_PAGE_SIZE,
  total: 0,
  totalPages: 0,
};

export default function PermissionsPage() {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.ADMIN.PERMISSION_CREATE);
  const canUpdate = hasPermission(PERMISSIONS.ADMIN.PERMISSION_UPDATE);
  const canDelete = hasPermission(PERMISSIONS.ADMIN.PERMISSION_DELETE);
  const showActions = canUpdate || canDelete;

  const [permissions, setPermissions] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [moduleFilter, setModuleFilter] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_PERMISSION_PAGE_SIZE);

  const [formOpen, setFormOpen] = useState(false);
  const [formMode, setFormMode] = useState('create');
  const [formValues, setFormValues] = useState(EMPTY_PERMISSION_FORM);
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');
  const [editingPermission, setEditingPermission] = useState(null);

  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deletingPermission, setDeletingPermission] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [deleteError, setDeleteError] = useState('');

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setDebouncedSearch(search.trim());
    }, 300);

    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    setPage(1);
  }, [debouncedSearch, moduleFilter, limit]);

  const loadPermissions = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');

    try {
      const result = await fetchPermissionsApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        module: moduleFilter || undefined,
      });

      setPermissions(result.items);
      setPagination(result.pagination);

      if (result.pagination.totalPages > 0 && targetPage > result.pagination.totalPages) {
        setPage(result.pagination.totalPages);
      }
    } catch (error) {
      setListError(error.message || 'Impossible de charger les permissions.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, moduleFilter]);

  useEffect(() => {
    loadPermissions(page);
  }, [loadPermissions, page]);

  const openCreate = () => {
    setFormMode('create');
    setEditingPermission(null);
    setFormValues(EMPTY_PERMISSION_FORM);
    setFormError('');
    setFormOpen(true);
  };

  const openEdit = (permission) => {
    setFormMode('edit');
    setEditingPermission(permission);
    setFormValues({
      code: permission.code ?? '',
      libelle: permission.libelle ?? '',
      description: permission.description ?? '',
      module: permission.module ?? 'ADMIN',
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
        await createPermissionApi(payload);
        showSuccess('Permission créée avec succès.');
        setPage(1);
      } else {
        await updatePermissionApi(editingPermission.id, payload);
        showSuccess('Permission mise à jour avec succès.');
      }

      setFormOpen(false);
      await loadPermissions(formMode === 'create' ? 1 : page);
    } catch (error) {
      setFormError(error.message || 'Enregistrement impossible.');
    } finally {
      setFormLoading(false);
    }
  };

  const openDelete = (permission) => {
    setDeletingPermission(permission);
    setDeleteError('');
    setDeleteOpen(true);
  };

  const closeDelete = () => {
    if (!deleteLoading) {
      setDeleteOpen(false);
    }
  };

  const handleDelete = async () => {
    if (!deletingPermission) {
      return;
    }

    setDeleteLoading(true);
    setDeleteError('');

    try {
      await deletePermissionApi(deletingPermission.id);
      setDeleteOpen(false);
      showSuccess('Permission supprimée avec succès.');

      const nextPage = permissions.length === 1 && page > 1 ? page - 1 : page;
      setPage(nextPage);
      await loadPermissions(nextPage);
    } catch (error) {
      setDeleteError(error.message || 'Suppression impossible.');
      showError(error.message || 'Suppression impossible.');
    } finally {
      setDeleteLoading(false);
    }
  };

  const hasActiveFilters = Boolean(debouncedSearch || moduleFilter);

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
            Permissions
          </Typography>
          <Typography level="body-md" sx={{ color: 'neutral.500' }}>
            Gérez les permissions fonctionnelles et filtrez-les par module.
          </Typography>
        </Box>

        {canCreate ? (
          <Button startDecorator={<Plus size={18} />} onClick={openCreate} sx={{ alignSelf: { sm: 'center' } }}>
            Nouvelle permission
          </Button>
        ) : null}
      </Stack>

      <Card variant="outlined">
        <Stack spacing={2} sx={{ p: { xs: 2, md: 2.5 } }}>
          <Stack
            direction={{ xs: 'column', md: 'row' }}
            spacing={1.5}
            alignItems={{ xs: 'stretch', md: 'center' }}
          >
            <Input
              size="sm"
              placeholder="Rechercher une permission..."
              startDecorator={<Search size={16} />}
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              sx={{ flex: 1, bgcolor: 'background.level1', border: 'none' }}
            />

            <Select
              size="sm"
              value={moduleFilter}
              onChange={(_, value) => setModuleFilter(value ?? '')}
              placeholder="Tous les modules"
              startDecorator={<Filter size={16} />}
              sx={{ minWidth: { md: 220 }, bgcolor: 'background.level1', border: 'none' }}
            >
              <Option value="">Tous les modules</Option>
              {PERMISSION_MODULES.map((option) => (
                <Option key={option.value} value={option.value}>
                  {option.label}
                </Option>
              ))}
            </Select>
          </Stack>

          <Stack direction="row" justifyContent="space-between" alignItems="center">
            <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
              {pagination.total} permission{pagination.total > 1 ? 's' : ''}
              {moduleFilter ? ` · ${PERMISSION_MODULE_LABELS[moduleFilter]}` : ''}
            </Typography>
            {hasActiveFilters ? (
              <Button
                size="sm"
                variant="plain"
                color="neutral"
                onClick={() => {
                  setSearch('');
                  setModuleFilter('');
                }}
              >
                Réinitialiser les filtres
              </Button>
            ) : null}
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
                '& .perm-col-desktop': {
                  display: { xs: 'none', md: 'table-cell' },
                },
                '& .perm-col-label': {
                  width: { xs: '42%', md: '22%' },
                  pr: { xs: 2, md: 0 },
                  verticalAlign: 'middle',
                },
                '& .perm-col-module': {
                  width: { xs: '30%', md: '14%' },
                  whiteSpace: 'nowrap',
                  verticalAlign: 'middle',
                },
                '& .perm-col-actions': {
                  width: { xs: '28%', md: '96px' },
                  textAlign: 'right',
                  whiteSpace: 'nowrap',
                  verticalAlign: 'middle',
                },
              }}
            >
              <thead>
                <tr>
                  <th className="perm-col-desktop" style={{ width: '24%' }}>Code</th>
                  <th className="perm-col-label">Libellé</th>
                  <th className="perm-col-module">Module</th>
                  <th className="perm-col-desktop" style={{ width: '10%' }}>Rôles</th>
                  <th className="perm-col-desktop" style={{ width: '12%' }}>Créé le</th>
                  {showActions && <th className="perm-col-actions">Actions</th>}
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr>
                    <td colSpan={showActions ? 6 : 5}>
                      <Typography level="body-sm" sx={{ py: 3, textAlign: 'center', color: 'neutral.500' }}>
                        Chargement des permissions...
                      </Typography>
                    </td>
                  </tr>
                ) : null}

                {!loading && permissions.length === 0 ? (
                  <tr>
                    <td colSpan={showActions ? 6 : 5}>
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
                          <KeyRound size={22} />
                        </Box>
                        <Typography level="title-sm" sx={{ fontWeight: 600 }}>
                          Aucune permission trouvée
                        </Typography>
                        <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
                          {hasActiveFilters
                            ? 'Essayez un autre filtre ou terme de recherche.'
                            : 'Commencez par créer une permission.'}
                        </Typography>
                      </Stack>
                    </td>
                  </tr>
                ) : null}

                {!loading
                  ? permissions.map((permission) => (
                      <tr key={permission.id}>
                        <td className="perm-col-desktop">
                          <Typography level="title-sm" sx={{ fontWeight: 600, fontFamily: 'monospace', fontSize: '0.8125rem' }}>
                            {permission.code}
                          </Typography>
                        </td>
                        <td className="perm-col-label">
                          <Typography level="body-sm" sx={{ fontWeight: { xs: 600, md: 400 } }} noWrap>
                            {permission.libelle}
                          </Typography>
                        </td>
                        <td className="perm-col-module">
                          <ModuleChip module={permission.module} />
                        </td>
                        <td className="perm-col-desktop">
                          <Typography level="body-sm">{permission.rolesCount ?? 0}</Typography>
                        </td>
                        <td className="perm-col-desktop">
                          <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
                            {formatDate(permission.createdAt)}
                          </Typography>
                        </td>
                        {showActions && (
                          <td className="perm-col-actions">
                            <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                              {canUpdate ? (
                                <IconButton
                                  size="sm"
                                  variant="plain"
                                  color="neutral"
                                  onClick={() => openEdit(permission)}
                                  title="Modifier"
                                >
                                  <Pencil size={16} />
                                </IconButton>
                              ) : null}
                              {canDelete ? (
                                <IconButton
                                  size="sm"
                                  variant="plain"
                                  color="danger"
                                  onClick={() => openDelete(permission)}
                                  title="Supprimer"
                                  disabled={(permission.rolesCount ?? 0) > 0}
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

          <AppPagination
            page={pagination.page}
            totalPages={pagination.totalPages}
            total={pagination.total}
            limit={pagination.limit}
            onPageChange={setPage}
            onLimitChange={setLimit}
            limitOptions={PERMISSION_PAGE_SIZE_OPTIONS}
            loading={loading}
          />
        </Stack>
      </Card>

      <PermissionFormModal
        open={formOpen}
        mode={formMode}
        initialValues={formValues}
        loading={formLoading}
        error={formError}
        onClose={closeForm}
        onSubmit={handleSubmit}
      />

      <PermissionDeleteModal
        open={deleteOpen}
        permission={deletingPermission}
        loading={deleteLoading}
        error={deleteError}
        onClose={closeDelete}
        onConfirm={handleDelete}
      />
    </Stack>
  );
}
