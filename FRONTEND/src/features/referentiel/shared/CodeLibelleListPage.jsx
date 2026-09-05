import { useCallback, useEffect, useState } from 'react';
import {
  Box, Button, Card, IconButton, Input, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import ExportButtons from '../../../components/export/ExportButtons.jsx';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { exportResourceApi } from '../../../utils/exportApi.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import CodeLibelleDeleteModal from './components/CodeLibelleDeleteModal.jsx';
import CodeLibelleFormModal from './components/CodeLibelleFormModal.jsx';
import {
  DEFAULT_REFERENTIEL_PAGE_SIZE,
  EMPTY_CODE_LIBELLE_FORM,
  REFERENTIEL_PAGE_SIZE_OPTIONS,
} from './referentielConstants.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_REFERENTIEL_PAGE_SIZE, total: 0, totalPages: 0 };

function formatDate(value) {
  if (!value) return '—';
  return new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value));
}

export default function CodeLibelleListPage({
  title,
  description,
  icon: EmptyIcon,
  emptyLabel = 'Aucun élément',
  permissions,
  api,
  formIcon,
  createTitle,
  editTitle,
  deleteTitle,
  deleteHint,
  usageCountKey = null,
  usageCountLabel = null,
  codeMaxLength = 8,
  libelleMaxLength = 100,
  createSuccessMessage = 'Élément créé avec succès.',
  updateSuccessMessage = 'Élément mis à jour avec succès.',
  deleteSuccessMessage = 'Élément supprimé avec succès.',
  exportPermission = null,
  exportEndpoint = null,
  buildExportParams = null,
}) {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(permissions.create);
  const canUpdate = hasPermission(permissions.update);
  const canDelete = hasPermission(permissions.delete);
  const canExport = exportPermission ? hasPermission(exportPermission) : false;
  const showActions = canUpdate || canDelete;

  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_REFERENTIEL_PAGE_SIZE);

  const [formOpen, setFormOpen] = useState(false);
  const [formMode, setFormMode] = useState('create');
  const [formValues, setFormValues] = useState(EMPTY_CODE_LIBELLE_FORM);
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');
  const [editing, setEditing] = useState(null);

  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleting, setDeleting] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [deleteError, setDeleteError] = useState('');
  const [exportLoading, setExportLoading] = useState(null);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, limit]);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await api.fetchList({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
      });
      setItems(result.items);
      setPagination(result.pagination);
      if (result.pagination.totalPages > 0 && targetPage > result.pagination.totalPages) {
        setPage(result.pagination.totalPages);
      }
    } catch (error) {
      setListError(error.message || 'Impossible de charger les données.');
    } finally {
      setLoading(false);
    }
  }, [api, page, limit, debouncedSearch]);

  useEffect(() => { load(page); }, [load, page]);

  const openCreate = () => {
    setFormMode('create');
    setEditing(null);
    setFormValues(EMPTY_CODE_LIBELLE_FORM);
    setFormError('');
    setFormOpen(true);
  };

  const openEdit = (item) => {
    setFormMode('edit');
    setEditing(item);
    setFormValues({ code: item.code ?? '', libelle: item.libelle ?? '' });
    setFormError('');
    setFormOpen(true);
  };

  const handleSubmit = async (payload) => {
    setFormLoading(true);
    setFormError('');
    try {
      if (formMode === 'create') {
        await api.create(payload);
        showSuccess(createSuccessMessage);
        setPage(1);
      } else {
        await api.update(editing.id, { libelle: payload.libelle });
        showSuccess(updateSuccessMessage);
      }
      setFormOpen(false);
      await load(formMode === 'create' ? 1 : page);
    } catch (error) {
      setFormError(error.message || 'Enregistrement impossible.');
    } finally {
      setFormLoading(false);
    }
  };

  const handleDelete = async () => {
    if (!deleting) return;
    setDeleteLoading(true);
    setDeleteError('');
    try {
      await api.delete(deleting.id);
      setDeleteOpen(false);
      showSuccess(deleteSuccessMessage);
      const nextPage = items.length === 1 && page > 1 ? page - 1 : page;
      setPage(nextPage);
      await load(nextPage);
    } catch (error) {
      setDeleteError(error.message || 'Suppression impossible.');
      showError(error.message);
    } finally {
      setDeleteLoading(false);
    }
  };

  const resolveExportParams = () => (
    buildExportParams
      ? buildExportParams({ search: debouncedSearch })
      : { search: debouncedSearch || undefined }
  );

  const handleExport = async (format) => {
    if (!exportEndpoint) return;
    setExportLoading(format);
    try {
      await exportResourceApi(exportEndpoint, format, resolveExportParams());
      showSuccess(format === 'pdf' ? 'Export PDF ouvert dans le navigateur.' : 'Export Excel téléchargé.');
    } catch (error) {
      showError(error.message || 'Export impossible.');
    } finally {
      setExportLoading(null);
    }
  };

  const columnCount = showActions ? (usageCountKey ? 5 : 4) : (usageCountKey ? 4 : 3);

  return (
    <Stack spacing={3}>
      <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ xs: 'stretch', sm: 'flex-start' }} spacing={2}>
        <Box>
          <Typography level="h2" sx={{ fontWeight: 700, mb: 0.5 }}>{title}</Typography>
          <Typography level="body-md" sx={{ color: 'neutral.500' }}>{description}</Typography>
        </Box>
        {(canExport || canCreate) ? (
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ alignSelf: { sm: 'center' } }}>
            {canExport ? <ExportButtons onExport={handleExport} loading={exportLoading} /> : null}
            {canCreate ? <Button startDecorator={<Plus size={18} />} onClick={openCreate}>Nouveau</Button> : null}
          </Stack>
        ) : null}
      </Stack>

      <Card variant="outlined">
        <Stack spacing={2} sx={{ p: { xs: 2, md: 2.5 } }}>
          <Input
            size="sm"
            placeholder="Rechercher..."
            startDecorator={<Search size={16} />}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            sx={{ bgcolor: 'background.level1', border: 'none' }}
          />

          {listError ? <Typography level="body-sm" color="danger">{listError}</Typography> : null}

          <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto', borderColor: LOTRU_NEUTRAL[200] }}>
            <Table stickyHeader hoverRow sx={{ '--TableCell-headBackground': LOTRU_NEUTRAL[50], '--TableRow-hoverBackground': LOTRU_PRIMARY[50] }}>
              <thead>
                <tr>
                  <th>Code</th>
                  <th>Libellé</th>
                  {usageCountKey ? <th>{usageCountLabel}</th> : null}
                  <th>Créé le</th>
                  {showActions ? <th style={{ textAlign: 'right' }}>Actions</th> : null}
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr><td colSpan={columnCount}><Typography sx={{ py: 3, textAlign: 'center' }}>Chargement...</Typography></td></tr>
                ) : null}
                {!loading && items.length === 0 ? (
                  <tr><td colSpan={columnCount}>
                    <Stack alignItems="center" sx={{ py: 5 }}>
                      {EmptyIcon ? <EmptyIcon size={22} /> : null}
                      <Typography sx={{ mt: 1 }}>{emptyLabel}</Typography>
                    </Stack>
                  </td></tr>
                ) : null}
                {!loading ? items.map((item) => {
                  const usageCount = usageCountKey ? (item[usageCountKey] ?? 0) : 0;
                  return (
                    <tr key={item.id}>
                      <td><Typography sx={{ fontWeight: 600 }}>{item.code}</Typography></td>
                      <td>{item.libelle}</td>
                      {usageCountKey ? <td>{usageCount}</td> : null}
                      <td>{formatDate(item.createdAt)}</td>
                      {showActions ? (
                        <td>
                          <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                            {canUpdate ? <IconButton size="sm" variant="plain" onClick={() => openEdit(item)}><Pencil size={16} /></IconButton> : null}
                            {canDelete ? (
                              <IconButton
                                size="sm"
                                variant="plain"
                                color="danger"
                                onClick={() => { setDeleting(item); setDeleteError(''); setDeleteOpen(true); }}
                                disabled={usageCountKey ? usageCount > 0 : false}
                              >
                                <Trash2 size={16} />
                              </IconButton>
                            ) : null}
                          </Stack>
                        </td>
                      ) : null}
                    </tr>
                  );
                }) : null}
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
            limitOptions={REFERENTIEL_PAGE_SIZE_OPTIONS}
            loading={loading}
          />
        </Stack>
      </Card>

      <CodeLibelleFormModal
        open={formOpen}
        mode={formMode}
        initialValues={formValues}
        loading={formLoading}
        error={formError}
        onClose={() => !formLoading && setFormOpen(false)}
        onSubmit={handleSubmit}
        icon={formIcon}
        createTitle={createTitle}
        editTitle={editTitle}
        codeMaxLength={codeMaxLength}
        libelleMaxLength={libelleMaxLength}
      />

      <CodeLibelleDeleteModal
        open={deleteOpen}
        item={deleting}
        loading={deleteLoading}
        error={deleteError}
        onClose={() => !deleteLoading && setDeleteOpen(false)}
        onConfirm={handleDelete}
        title={deleteTitle}
        hint={deleteHint}
      />
    </Stack>
  );
}
