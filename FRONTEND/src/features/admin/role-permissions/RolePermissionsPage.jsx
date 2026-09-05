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
import { Filter, Link2, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { PERMISSION_MODULE_LABELS, PERMISSION_MODULES } from '../permissions/permissionConstants.js';
import { ROLE_PERIMETRE_LABELS } from '../roles/roleConstants.js';
import RolePermissionClearModal from './components/RolePermissionClearModal.jsx';
import RolePermissionFormModal from './components/RolePermissionFormModal.jsx';
import {
  DEFAULT_ROLE_PERMISSION_PAGE_SIZE,
  ROLE_PERMISSION_PAGE_SIZE_OPTIONS,
} from './rolePermissionConstants.js';
import {
  clearRolePermissionAssignmentApi,
  createRolePermissionAssignmentApi,
  fetchRolePermissionsListApi,
  removeRolePermissionApi,
  updateRolePermissionAssignmentApi,
} from './rolePermissionsApi.js';

const PREVIEW_LIMIT = 4;

const EMPTY_PAGINATION = {
  page: 1,
  limit: DEFAULT_ROLE_PERMISSION_PAGE_SIZE,
  total: 0,
  totalPages: 0,
};

function PermissionPreview({ permissions, onRemove, canRemove, removingId }) {
  if (!permissions?.length) {
    return (
      <Typography level="body-sm" sx={{ color: 'neutral.400', fontStyle: 'italic' }}>
        Aucune permission
      </Typography>
    );
  }

  const visible = permissions.slice(0, PREVIEW_LIMIT);
  const remaining = permissions.length - visible.length;

  return (
    <Stack direction="row" spacing={0.5} useFlexGap flexWrap="wrap">
      {visible.map((permission) => (
        <Chip
          key={permission.id}
          size="sm"
          variant="soft"
          color="neutral"
          endDecorator={
            canRemove ? (
              <IconButton
                size="sm"
                variant="plain"
                color="danger"
                onClick={() => onRemove?.(permission)}
                disabled={removingId === permission.id}
                sx={{ '--IconButton-size': '20px', minHeight: 20, minWidth: 20 }}
              >
                ×
              </IconButton>
            ) : null
          }
        >
          {permission.code}
        </Chip>
      ))}
      {remaining > 0 ? (
        <Chip size="sm" variant="outlined" color="neutral">
          +{remaining} autre{remaining > 1 ? 's' : ''}
        </Chip>
      ) : null}
    </Stack>
  );
}

export default function RolePermissionsPage() {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canAssign = hasPermission(PERMISSIONS.ADMIN.ROLE_PERMISSION_ASSIGN);
  const canDelete = hasPermission(PERMISSIONS.ADMIN.ROLE_PERMISSION_DELETE);

  const [assignments, setAssignments] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [moduleFilter, setModuleFilter] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_ROLE_PERMISSION_PAGE_SIZE);
  const [removingId, setRemovingId] = useState('');

  const [formOpen, setFormOpen] = useState(false);
  const [formMode, setFormMode] = useState('create');
  const [editingAssignment, setEditingAssignment] = useState(null);
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');

  const [clearOpen, setClearOpen] = useState(false);
  const [clearingAssignment, setClearingAssignment] = useState(null);
  const [clearLoading, setClearLoading] = useState(false);
  const [clearError, setClearError] = useState('');

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    setPage(1);
  }, [debouncedSearch, moduleFilter, limit]);

  const loadAssignments = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');

    try {
      const result = await fetchRolePermissionsListApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        module: moduleFilter || undefined,
      });

      setAssignments(result.items);
      setPagination(result.pagination);

      if (result.pagination.totalPages > 0 && targetPage > result.pagination.totalPages) {
        setPage(result.pagination.totalPages);
      }
    } catch (error) {
      setListError(error.message || 'Impossible de charger les affectations.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, moduleFilter]);

  useEffect(() => {
    loadAssignments(page);
  }, [loadAssignments, page]);

  const openCreate = () => {
    setFormMode('create');
    setEditingAssignment(null);
    setFormError('');
    setFormOpen(true);
  };

  const openEdit = (assignment) => {
    setFormMode('edit');
    setEditingAssignment(assignment);
    setFormError('');
    setFormOpen(true);
  };

  const closeForm = () => {
    if (!formLoading) {
      setFormOpen(false);
    }
  };

  const handleSubmit = async ({ roleId, permissionIds }) => {
    setFormLoading(true);
    setFormError('');

    try {
      if (formMode === 'create') {
        await createRolePermissionAssignmentApi({ roleId, permissionIds });
        showSuccess('Affectation créée avec succès.');
        setPage(1);
      } else {
        await updateRolePermissionAssignmentApi(roleId, permissionIds);
        showSuccess('Affectation mise à jour avec succès.');
      }

      setFormOpen(false);
      await loadAssignments(formMode === 'create' ? 1 : page);
    } catch (error) {
      setFormError(error.message || 'Enregistrement impossible.');
    } finally {
      setFormLoading(false);
    }
  };

  const openClear = (assignment) => {
    setClearingAssignment(assignment);
    setClearError('');
    setClearOpen(true);
  };

  const closeClear = () => {
    if (!clearLoading) {
      setClearOpen(false);
    }
  };

  const handleClear = async () => {
    if (!clearingAssignment?.role?.id) {
      return;
    }

    setClearLoading(true);
    setClearError('');

    try {
      await clearRolePermissionAssignmentApi(clearingAssignment.role.id);
      setClearOpen(false);
      showSuccess('Toutes les permissions ont été retirées du rôle.');
      await loadAssignments(page);
    } catch (error) {
      setClearError(error.message || 'Suppression impossible.');
      showError(error.message || 'Suppression impossible.');
    } finally {
      setClearLoading(false);
    }
  };

  const handleRemovePermission = async (assignment, permission) => {
    if (!assignment?.role?.id || !permission?.id) {
      return;
    }

    setRemovingId(permission.id);

    try {
      await removeRolePermissionApi(assignment.role.id, permission.id);
      showSuccess('Permission retirée du rôle.');
      await loadAssignments(page);
    } catch (error) {
      showError(error.message || 'Suppression impossible.');
    } finally {
      setRemovingId('');
    }
  };

  const hasActiveFilters = Boolean(debouncedSearch || moduleFilter);
  const showActions = canAssign || canDelete;

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
            Affectations permissions
          </Typography>
          <Typography level="body-md" sx={{ color: 'neutral.500' }}>
            Attribuez et gérez les permissions associées à chaque rôle.
          </Typography>
        </Box>

        {canAssign ? (
          <Button startDecorator={<Plus size={18} />} onClick={openCreate} sx={{ alignSelf: { sm: 'center' } }}>
            Nouvelle affectation
          </Button>
        ) : null}
      </Stack>

      <Card variant="outlined">
        <Stack spacing={2} sx={{ p: { xs: 2, md: 2.5 } }}>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
            <Input
              size="sm"
              placeholder="Rechercher un rôle..."
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
              {pagination.total} rôle{pagination.total > 1 ? 's' : ''}
              {moduleFilter ? ` · module ${PERMISSION_MODULE_LABELS[moduleFilter]}` : ''}
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
            sx={{ borderRadius: 'lg', overflow: 'auto', borderColor: LOTRU_NEUTRAL[200] }}
          >
            <Table
              stickyHeader
              hoverRow
              sx={{
                '--TableCell-headBackground': LOTRU_NEUTRAL[50],
                '--TableRow-hoverBackground': LOTRU_PRIMARY[50],
                '& thead th': { fontWeight: 600, color: 'neutral.600', fontSize: '0.8125rem' },
              }}
            >
              <thead>
                <tr>
                  <th style={{ width: '16%' }}>Rôle</th>
                  <th style={{ width: '14%' }}>Périmètre</th>
                  <th>Permissions affectées</th>
                  <th style={{ width: '8%' }}>Total</th>
                  {showActions && <th style={{ width: '96px', textAlign: 'right' }}>Actions</th>}
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr>
                    <td colSpan={showActions ? 5 : 4}>
                      <Typography level="body-sm" sx={{ py: 3, textAlign: 'center', color: 'neutral.500' }}>
                        Chargement des affectations...
                      </Typography>
                    </td>
                  </tr>
                ) : null}

                {!loading && assignments.length === 0 ? (
                  <tr>
                    <td colSpan={showActions ? 5 : 4}>
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
                          <Link2 size={22} />
                        </Box>
                        <Typography level="title-sm" sx={{ fontWeight: 600 }}>
                          Aucune affectation trouvée
                        </Typography>
                        <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
                          {hasActiveFilters
                            ? 'Essayez un autre filtre ou terme de recherche.'
                            : 'Commencez par affecter des permissions à un rôle.'}
                        </Typography>
                      </Stack>
                    </td>
                  </tr>
                ) : null}

                {!loading
                  ? assignments.map((assignment) => (
                      <tr key={assignment.id}>
                        <td>
                          <Typography level="title-sm" sx={{ fontWeight: 600 }}>
                            {assignment.role?.code}
                          </Typography>
                          <Typography level="body-xs" sx={{ color: 'neutral.500' }}>
                            {assignment.role?.libelle}
                          </Typography>
                        </td>
                        <td>
                          <Chip size="sm" variant="soft" color="neutral">
                            {ROLE_PERIMETRE_LABELS[assignment.role?.perimetre] ?? assignment.role?.perimetre}
                          </Chip>
                        </td>
                        <td>
                          <PermissionPreview
                            permissions={assignment.permissions}
                            canRemove={canDelete}
                            removingId={removingId}
                            onRemove={(permission) => handleRemovePermission(assignment, permission)}
                          />
                        </td>
                        <td>
                          <Typography level="body-sm">{assignment.permissionsCount ?? 0}</Typography>
                        </td>
                        {showActions && (
                          <td>
                            <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                              {canAssign ? (
                                <IconButton
                                  size="sm"
                                  variant="plain"
                                  color="neutral"
                                  onClick={() => openEdit(assignment)}
                                  title="Modifier l'affectation"
                                >
                                  <Pencil size={16} />
                                </IconButton>
                              ) : null}
                              {canDelete && (assignment.permissionsCount ?? 0) > 0 ? (
                                <IconButton
                                  size="sm"
                                  variant="plain"
                                  color="danger"
                                  onClick={() => openClear(assignment)}
                                  title="Retirer toutes les permissions"
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
            limitOptions={ROLE_PERMISSION_PAGE_SIZE_OPTIONS}
            loading={loading}
          />
        </Stack>
      </Card>

      <RolePermissionFormModal
        open={formOpen}
        mode={formMode}
        assignment={editingAssignment}
        loading={formLoading}
        error={formError}
        onClose={closeForm}
        onSubmit={handleSubmit}
      />

      <RolePermissionClearModal
        open={clearOpen}
        assignment={clearingAssignment}
        loading={clearLoading}
        error={clearError}
        onClose={closeClear}
        onConfirm={handleClear}
      />
    </Stack>
  );
}
