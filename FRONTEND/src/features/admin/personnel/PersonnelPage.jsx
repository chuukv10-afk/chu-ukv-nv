import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
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
  ToggleButtonGroup,
  Typography,
} from '@mui/joy';
import { FileSpreadsheet, FileText, LayoutGrid, List, Pencil, Plus, Search, Trash2, Users } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import { admin, rh } from '../../../api/endpoints.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { ROUTES } from '../../../constants/routes.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import AuthAvatar from '../../../components/ui/AuthAvatar.jsx';
import { getDisplayName, getInitials } from '../../../utils/profile.js';
import RoleAssignmentLabel from '../../../components/common/RoleAssignmentLabel.jsx';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import PersonnelDeleteModal from './components/PersonnelDeleteModal.jsx';
import PersonnelFormModal from './components/PersonnelFormModal.jsx';
import PersonnelGrid from './components/PersonnelGrid.jsx';
import {
  DEFAULT_PERSONNEL_PAGE_SIZE,
  EMPTY_PERSONNEL_FORM,
  PERSONNEL_PAGE_SIZE_OPTIONS,
  PERSONNEL_STATUS_COLORS,
  PERSONNEL_STATUS_LABELS,
  PERSONNEL_SEXES,
  PERSONNEL_TYPE_LABELS,
  PERSONNEL_VIEW_GRID,
  PERSONNEL_VIEW_STORAGE_KEY,
  PERSONNEL_VIEW_TABLE,
} from './personnelConstants.js';
import {
  createPersonnelApi,
  deletePersonnelApi,
  deletePersonnelAvatarApi,
  exportPersonnelsApi,
  fetchPersonnelApi,
  fetchPersonnelLookupsApi,
  fetchPersonnelsApi,
  updatePersonnelApi,
  uploadPersonnelAvatarApi,
  uploadPersonnelSignatureApi,
  deletePersonnelSignatureApi,
} from './personnelApi.js';

const EMPTY_PAGINATION = {
  page: 1,
  limit: DEFAULT_PERSONNEL_PAGE_SIZE,
  total: 0,
  totalPages: 0,
};

function StatusChip({ status }) {
  return (
    <Chip size="sm" variant="soft" color={PERSONNEL_STATUS_COLORS[status] ?? 'neutral'}>
      {PERSONNEL_STATUS_LABELS[status] ?? status}
    </Chip>
  );
}

export default function PersonnelPage({ variant = 'admin' }) {
  const navigate = useNavigate();
  const isRh = variant === 'rh';
  const apiBase = isRh ? rh.personnels : admin.personnels;
  const apiOptions = { base: apiBase };
  const permissionGroup = isRh ? PERMISSIONS.RH : PERMISSIONS.ADMIN;
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(permissionGroup.PERSONNEL_CREATE);
  const canUpdate = hasPermission(permissionGroup.PERSONNEL_UPDATE);
  const canDelete = hasPermission(permissionGroup.PERSONNEL_DELETE);
  const canExport = hasPermission(permissionGroup.PERSONNEL_EXPORT);
  const showActions = canUpdate || canDelete;
  const showRoles = !isRh;

  const [personnels, setPersonnels] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [serviceFilter, setServiceFilter] = useState('');
  const [sexeFilter, setSexeFilter] = useState('');
  const [services, setServices] = useState([]);
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_PERSONNEL_PAGE_SIZE);

  const [formOpen, setFormOpen] = useState(false);
  const [formMode, setFormMode] = useState('create');
  const [formValues, setFormValues] = useState(EMPTY_PERSONNEL_FORM);
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');
  const [editingPersonnel, setEditingPersonnel] = useState(null);

  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deletingPersonnel, setDeletingPersonnel] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [deleteError, setDeleteError] = useState('');
  const [exportLoading, setExportLoading] = useState(null);
  const [viewMode, setViewMode] = useState(() => {
    try {
      const stored = window.localStorage.getItem(PERSONNEL_VIEW_STORAGE_KEY);
      return stored === PERSONNEL_VIEW_GRID ? PERSONNEL_VIEW_GRID : PERSONNEL_VIEW_TABLE;
    } catch {
      return PERSONNEL_VIEW_TABLE;
    }
  });

  const changeViewMode = (_, value) => {
    if (!value) return;
    setViewMode(value);
    try {
      window.localStorage.setItem(PERSONNEL_VIEW_STORAGE_KEY, value);
    } catch {
      /* ignore quota / private mode */
    }
  };

  useEffect(() => {
    fetchPersonnelLookupsApi({ includeRoles: showRoles })
      .then((lookups) => setServices(lookups.services ?? []))
      .catch(() => setServices([]));
  }, [showRoles]);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    setPage(1);
  }, [debouncedSearch, statusFilter, typeFilter, serviceFilter, sexeFilter, limit]);

  const loadPersonnels = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchPersonnelsApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        status: statusFilter || undefined,
        type: typeFilter || undefined,
        serviceId: serviceFilter || undefined,
        sexe: sexeFilter || undefined,
      }, apiOptions);
      setPersonnels(result.items);
      setPagination(result.pagination);
      if (result.pagination.totalPages > 0 && targetPage > result.pagination.totalPages) {
        setPage(result.pagination.totalPages);
      }
    } catch (error) {
      setListError(error.message || 'Impossible de charger le personnel.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, statusFilter, typeFilter, serviceFilter, sexeFilter, apiBase]);

  useEffect(() => {
    loadPersonnels(page);
  }, [loadPersonnels, page]);

  const openCreate = () => {
    if (isRh) {
      navigate(ROUTES.RH.PERSONNEL_NEW);
      return;
    }
    setFormMode('create');
    setEditingPersonnel(null);
    setFormValues(EMPTY_PERSONNEL_FORM);
    setFormError('');
    setFormOpen(true);
  };

  const openEdit = async (personnel) => {
    if (isRh) {
      navigate(`${ROUTES.RH.PERSONNEL}/${personnel.id}`);
      return;
    }
    setFormMode('edit');
    setFormError('');
    setFormLoading(true);
    setFormOpen(true);

    try {
      const detail = await fetchPersonnelApi(personnel.id, apiOptions);
      setEditingPersonnel(detail);
      setFormValues({
        nom: detail.nom ?? '',
        postNom: detail.postNom ?? '',
        prenom: detail.prenom ?? '',
        telephone: detail.telephone ?? '',
        matricule: detail.matricule ?? '',
        sexe: detail.sexe ?? 'M',
        type: detail.type ?? 'MEDICAL',
        status: detail.status ?? 'ACTIF',
        password: '',
        adresse: detail.adresse ?? '',
        lieuNaissance: detail.lieuNaissance ?? '',
        cnome: detail.cnome ?? '',
        gradeId: detail.grade?.id ?? null,
        fonctionId: detail.fonction?.id ?? null,
        serviceId: detail.service?.id ?? null,
        specialiteIds: detail.specialiteIds ?? [],
        roleAssignments: detail.roleAssignments ?? [],
        avatarUrl: detail.avatarUrl ?? null,
        signatureUrl: detail.signatureUrl ?? null,
      });
    } catch (error) {
      setFormError(error.message || 'Impossible de charger le personnel.');
    } finally {
      setFormLoading(false);
    }
  };

  const closeForm = () => {
    if (!formLoading) setFormOpen(false);
  };

  const handleSubmit = async (payload, avatarOptions = {}) => {
    setFormLoading(true);
    setFormError('');
    try {
      const requestPayload = { ...payload };
      if (isRh) {
        if (formMode === 'create') {
          requestPayload.roleAssignments = [];
        } else {
          delete requestPayload.roleAssignments;
        }
      }

      if (formMode === 'create') {
        const created = await createPersonnelApi(requestPayload, apiOptions);
        if (avatarOptions.avatarFile) {
          await uploadPersonnelAvatarApi(created.id, avatarOptions.avatarFile, apiOptions);
        }
        if (avatarOptions.signatureFile) {
          await uploadPersonnelSignatureApi(created.id, avatarOptions.signatureFile, apiOptions);
        }
        showSuccess(isRh ? 'Personnel créé avec succès.' : 'Utilisateur créé avec succès.');
        setPage(1);
      } else {
        const updatePayload = { ...requestPayload };
        if (!updatePayload.password) delete updatePayload.password;
        await updatePersonnelApi(editingPersonnel.id, updatePayload, apiOptions);

        if (avatarOptions.avatarFile) {
          await uploadPersonnelAvatarApi(editingPersonnel.id, avatarOptions.avatarFile, apiOptions);
        } else if (avatarOptions.removeAvatar) {
          await deletePersonnelAvatarApi(editingPersonnel.id, apiOptions);
        }
        if (avatarOptions.signatureFile) {
          await uploadPersonnelSignatureApi(editingPersonnel.id, avatarOptions.signatureFile, apiOptions);
        } else if (avatarOptions.removeSignature) {
          await deletePersonnelSignatureApi(editingPersonnel.id, apiOptions);
        }

        showSuccess(isRh ? 'Personnel mis à jour avec succès.' : 'Utilisateur mis à jour avec succès.');
      }
      setFormOpen(false);
      await loadPersonnels(formMode === 'create' ? 1 : page);
    } catch (error) {
      setFormError(error.message || 'Enregistrement impossible.');
    } finally {
      setFormLoading(false);
    }
  };

  const openDelete = (personnel) => {
    setDeletingPersonnel(personnel);
    setDeleteError('');
    setDeleteOpen(true);
  };

  const closeDelete = () => {
    if (!deleteLoading) setDeleteOpen(false);
  };

  const handleDelete = async () => {
    if (!deletingPersonnel) return;
    setDeleteLoading(true);
    setDeleteError('');
    try {
      await deletePersonnelApi(deletingPersonnel.id, apiOptions);
      setDeleteOpen(false);
      showSuccess(isRh ? 'Personnel supprimé avec succès.' : 'Utilisateur supprimé avec succès.');
      const nextPage = personnels.length === 1 && page > 1 ? page - 1 : page;
      setPage(nextPage);
      await loadPersonnels(nextPage);
    } catch (error) {
      setDeleteError(error.message || 'Suppression impossible.');
      showError(error.message || 'Suppression impossible.');
    } finally {
      setDeleteLoading(false);
    }
  };

  const hasActiveFilters = Boolean(debouncedSearch || statusFilter || typeFilter || serviceFilter || sexeFilter);

  const resetFilters = () => {
    setSearch('');
    setStatusFilter('');
    setTypeFilter('');
    setServiceFilter('');
    setSexeFilter('');
  };

  const buildExportParams = () => ({
    search: debouncedSearch || undefined,
    status: statusFilter || undefined,
    type: typeFilter || undefined,
    serviceId: serviceFilter || undefined,
    sexe: sexeFilter || undefined,
  });

  const handleExport = async (format) => {
    setExportLoading(format);
    try {
      await exportPersonnelsApi(format, buildExportParams(), apiOptions);
      showSuccess(format === 'pdf' ? 'Export PDF ouvert dans le navigateur.' : 'Export Excel téléchargé.');
    } catch (error) {
      showError(error.message || 'Export impossible.');
    } finally {
      setExportLoading(null);
    }
  };

  return (
    <Stack spacing={3}>
      <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ xs: 'stretch', sm: 'flex-start' }} spacing={2}>
        <Box>
          <Typography level="h2" sx={{ fontWeight: 700, mb: 0.5 }}>
            {isRh ? 'Personnel' : 'Utilisateurs'}
          </Typography>
          <Typography level="body-md" sx={{ color: 'neutral.500' }}>
            {isRh
              ? 'Gérez les fiches du personnel (identité, grade, fonction, service).'
              : 'Gérez les comptes utilisateurs, leurs informations et leurs rôles.'}
          </Typography>
        </Box>
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ alignSelf: { sm: 'center' } }}>
          {canExport ? (
            <>
              <Button
                variant="outlined"
                color="neutral"
                startDecorator={<FileSpreadsheet size={18} />}
                loading={exportLoading === 'xlsx'}
                disabled={Boolean(exportLoading)}
                onClick={() => handleExport('xlsx')}
              >
                Excel
              </Button>
              <Button
                variant="outlined"
                color="neutral"
                startDecorator={<FileText size={18} />}
                loading={exportLoading === 'pdf'}
                disabled={Boolean(exportLoading)}
                onClick={() => handleExport('pdf')}
              >
                PDF
              </Button>
            </>
          ) : null}
          {canCreate ? (
            <Button startDecorator={<Plus size={18} />} onClick={openCreate}>
              {isRh ? 'Nouveau personnel' : 'Nouvel utilisateur'}
            </Button>
          ) : null}
        </Stack>
      </Stack>

      <Card variant="outlined">
        <Stack spacing={2} sx={{ p: { xs: 2, md: 2.5 } }}>
          <Stack spacing={1.5}>
            <Input
              size="sm"
              placeholder="Rechercher (nom, matricule, téléphone)..."
              startDecorator={<Search size={16} />}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              sx={{ bgcolor: 'background.level1', border: 'none' }}
            />
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} flexWrap="wrap">
              <Select
                size="sm"
                value={serviceFilter}
                onChange={(_, v) => setServiceFilter(v ?? '')}
                placeholder="Tous les services"
                sx={{ minWidth: { md: 180 }, flex: { md: 1 }, bgcolor: 'background.level1', border: 'none' }}
              >
                <Option value="">Tous les services</Option>
                {services.map((service) => (
                  <Option key={service.id} value={service.id}>{service.libelle}</Option>
                ))}
              </Select>
              <Select
                size="sm"
                value={sexeFilter}
                onChange={(_, v) => setSexeFilter(v ?? '')}
                placeholder="Tous les sexes"
                sx={{ minWidth: { md: 160 }, bgcolor: 'background.level1', border: 'none' }}
              >
                <Option value="">Tous les sexes</Option>
                {PERSONNEL_SEXES.map((item) => (
                  <Option key={item.value} value={item.value}>{item.label}</Option>
                ))}
              </Select>
              <Select
                size="sm"
                value={statusFilter}
                onChange={(_, v) => setStatusFilter(v ?? '')}
                placeholder="Tous les statuts"
                sx={{ minWidth: { md: 160 }, bgcolor: 'background.level1', border: 'none' }}
              >
                <Option value="">Tous les statuts</Option>
                {Object.entries(PERSONNEL_STATUS_LABELS).filter(([k]) => k !== 'SUPPRIME').map(([value, label]) => (
                  <Option key={value} value={value}>{label}</Option>
                ))}
              </Select>
              <Select
                size="sm"
                value={typeFilter}
                onChange={(_, v) => setTypeFilter(v ?? '')}
                placeholder="Tous les types"
                sx={{ minWidth: { md: 160 }, bgcolor: 'background.level1', border: 'none' }}
              >
                <Option value="">Tous les types</Option>
                {Object.entries(PERSONNEL_TYPE_LABELS).map(([value, label]) => (
                  <Option key={value} value={value}>{label}</Option>
                ))}
              </Select>
            </Stack>
          </Stack>

          <Stack direction="row" justifyContent="space-between" alignItems="center" spacing={1}>
            <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
              {pagination.total} agent{pagination.total > 1 ? 's' : ''}
            </Typography>
            <Stack direction="row" spacing={1} alignItems="center">
              {hasActiveFilters ? (
                <Button size="sm" variant="plain" color="neutral" onClick={resetFilters}>
                  Réinitialiser les filtres
                </Button>
              ) : null}
              <ToggleButtonGroup
                size="sm"
                variant="outlined"
                value={viewMode}
                onChange={changeViewMode}
                sx={{ bgcolor: 'background.level1' }}
              >
                <IconButton value={PERSONNEL_VIEW_TABLE} aria-label="Vue tableau" title="Tableau">
                  <List size={16} />
                </IconButton>
                <IconButton value={PERSONNEL_VIEW_GRID} aria-label="Vue grille" title="Grille">
                  <LayoutGrid size={16} />
                </IconButton>
              </ToggleButtonGroup>
            </Stack>
          </Stack>

          {listError ? (
            <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>{listError}</Typography>
          ) : null}

          {viewMode === PERSONNEL_VIEW_GRID ? (
            <PersonnelGrid
              personnels={personnels}
              loading={loading}
              showActions={showActions}
              canUpdate={canUpdate}
              canDelete={canDelete}
              onEdit={openEdit}
              onDelete={openDelete}
              hideRoles={!showRoles}
            />
          ) : (
          <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto', borderColor: LOTRU_NEUTRAL[200] }}>
            <Table
              stickyHeader
              hoverRow
              sx={{
                '--TableCell-headBackground': LOTRU_NEUTRAL[50],
                '--TableRow-hoverBackground': LOTRU_PRIMARY[50],
                tableLayout: { xs: 'fixed', md: 'auto' },
                width: '100%',
                '& thead th': { fontWeight: 600, color: 'neutral.600', fontSize: '0.8125rem' },
                '& .pers-col-desktop': { display: { xs: 'none', md: 'table-cell' } },
                '& .pers-col-agent': {
                  width: { xs: '38%', md: 'auto' },
                  minWidth: { md: 180 },
                  pr: { xs: 1.5, md: 0 },
                  verticalAlign: 'middle',
                },
                '& .pers-col-type': {
                  width: { xs: '22%', md: 'auto' },
                  whiteSpace: 'nowrap',
                  verticalAlign: 'middle',
                },
                '& .pers-col-status': {
                  width: { xs: '24%', md: 'auto' },
                  whiteSpace: 'nowrap',
                  verticalAlign: 'middle',
                },
                '& .pers-col-roles': {
                  minWidth: { md: 180 },
                  maxWidth: { md: 280 },
                  verticalAlign: 'middle',
                },
                '& .pers-col-actions': {
                  width: { xs: '16%', md: 96 },
                  minWidth: { md: 96 },
                  textAlign: 'right',
                  whiteSpace: 'nowrap',
                  verticalAlign: 'middle',
                },
              }}
            >
              <thead>
                <tr>
                  <th className="pers-col-agent">Agent</th>
                  <th className="pers-col-desktop">Matricule</th>
                  <th className="pers-col-desktop">Téléphone</th>
                  <th className="pers-col-type">Type</th>
                  <th className="pers-col-status">Statut</th>
                  <th className="pers-col-desktop">Service</th>
                  {showRoles ? <th className="pers-col-desktop pers-col-roles">Rôles</th> : null}
                  {showActions ? <th className="pers-col-actions">Actions</th> : null}
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr><td colSpan={(showRoles ? 7 : 6) + (showActions ? 1 : 0)}><Typography level="body-sm" sx={{ py: 3, textAlign: 'center', color: 'neutral.500' }}>Chargement...</Typography></td></tr>
                ) : null}

                {!loading && personnels.length === 0 ? (
                  <tr>
                    <td colSpan={(showRoles ? 7 : 6) + (showActions ? 1 : 0)}>
                      <Stack alignItems="center" spacing={1} sx={{ py: 5 }}>
                        <Box sx={{ width: 48, height: 48, borderRadius: 'md', bgcolor: 'primary.50', color: 'primary.600', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                          <Users size={22} />
                        </Box>
                        <Typography level="title-sm" sx={{ fontWeight: 600 }}>
                          {isRh ? 'Aucun personnel trouvé' : 'Aucun utilisateur trouvé'}
                        </Typography>
                      </Stack>
                    </td>
                  </tr>
                ) : null}

                {!loading ? personnels.map((personnel) => (
                  <tr key={personnel.id}>
                    <td className="pers-col-agent">
                      <Stack direction="row" spacing={1.25} alignItems="center">
                        <AuthAvatar
                          src={personnel.avatarUrl}
                          fallback={getInitials(personnel)}
                          size="sm"
                          sx={{ bgcolor: 'primary.50', color: 'primary.700', fontWeight: 600, flexShrink: 0 }}
                        />
                        <Box sx={{ minWidth: 0 }}>
                          <Typography level="body-sm" sx={{ fontWeight: 600 }}>{getDisplayName(personnel)}</Typography>
                          <Typography level="body-xs" sx={{ color: 'neutral.500', display: { xs: 'none', md: 'block' } }}>
                            {[personnel.grade?.libelle, personnel.fonction?.libelle].filter(Boolean).join(' · ') || '—'}
                          </Typography>
                        </Box>
                      </Stack>
                    </td>
                    <td className="pers-col-desktop"><Typography level="body-sm">{personnel.matricule || '—'}</Typography></td>
                    <td className="pers-col-desktop"><Typography level="body-sm">{personnel.telephone}</Typography></td>
                    <td className="pers-col-type"><Chip size="sm" variant="soft" color="neutral">{PERSONNEL_TYPE_LABELS[personnel.type] ?? personnel.type}</Chip></td>
                    <td className="pers-col-status"><StatusChip status={personnel.status} /></td>
                    <td className="pers-col-desktop"><Typography level="body-sm">{personnel.service?.libelle ?? '—'}</Typography></td>
                    {showRoles ? (
                      <td className="pers-col-desktop pers-col-roles">
                        <Stack direction="row" spacing={0.5} useFlexGap flexWrap="wrap">
                          {(personnel.roleAssignments ?? []).slice(0, 2).map((assignment) => (
                            <Chip key={assignment.id} size="sm" variant="outlined" color="primary">
                              <RoleAssignmentLabel
                                assignment={{
                                  roleLibelle: assignment.roleLibelle,
                                  roleCode: assignment.roleCode,
                                  service: assignment.serviceLibelle,
                                  departement: assignment.departementLibelle,
                                }}
                                preferCode
                                component="span"
                              />
                            </Chip>
                          ))}
                          {(personnel.roleAssignments?.length ?? 0) > 2 ? (
                            <Chip size="sm" variant="soft" color="neutral">+{personnel.roleAssignments.length - 2}</Chip>
                          ) : null}
                        </Stack>
                      </td>
                    ) : null}
                    {showActions ? (
                      <td className="pers-col-actions">
                        <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                          {canUpdate ? (
                            <IconButton size="sm" variant="plain" color="neutral" onClick={() => openEdit(personnel)} title="Modifier">
                              <Pencil size={16} />
                            </IconButton>
                          ) : null}
                          {canDelete ? (
                            <IconButton size="sm" variant="plain" color="danger" onClick={() => openDelete(personnel)} title="Supprimer">
                              <Trash2 size={16} />
                            </IconButton>
                          ) : null}
                        </Stack>
                      </td>
                    ) : null}
                  </tr>
                )) : null}
              </tbody>
            </Table>
          </Sheet>
          )}

          <AppPagination
            page={pagination.page}
            totalPages={pagination.totalPages}
            total={pagination.total}
            limit={pagination.limit}
            onPageChange={setPage}
            onLimitChange={setLimit}
            limitOptions={PERSONNEL_PAGE_SIZE_OPTIONS}
            loading={loading}
          />
        </Stack>
      </Card>

      {isRh ? null : (
        <PersonnelFormModal
          open={formOpen}
          mode={formMode}
          initialValues={formValues}
          loading={formLoading}
          error={formError}
          onClose={closeForm}
          onSubmit={handleSubmit}
          hideRoles={!showRoles}
          variant={variant}
        />
      )}

      <PersonnelDeleteModal
        open={deleteOpen}
        personnel={deletingPersonnel}
        loading={deleteLoading}
        error={deleteError}
        onClose={closeDelete}
        onConfirm={handleDelete}
      />
    </Stack>
  );
}
