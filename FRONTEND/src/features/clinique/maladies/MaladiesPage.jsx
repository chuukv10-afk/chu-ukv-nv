import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Box, Button, Card, Checkbox, IconButton, Input, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { HeartPulse, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import ExportButtons from '../../../components/export/ExportButtons.jsx';
import { clinique } from '../../../api/endpoints.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { exportResourceApi } from '../../../utils/exportApi.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import MaladieBulkDeleteModal from './components/MaladieBulkDeleteModal.jsx';
import MaladieDeleteModal from './components/MaladieDeleteModal.jsx';
import MaladieFormModal from './components/MaladieFormModal.jsx';
import {
  DEFAULT_MALADIE_PAGE_SIZE, EMPTY_MALADIE_FORM, MALADIE_PAGE_SIZE_OPTIONS,
} from './maladieConstants.js';
import {
  bulkDeleteMaladiesApi,
  createMaladieApi,
  deleteMaladieApi,
  fetchMaladieChapitresApi,
  fetchMaladiesApi,
  updateMaladieApi,
} from './maladiesApi.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_MALADIE_PAGE_SIZE, total: 0, totalPages: 0 };

function isItemLocked(item) {
  return (item.antecedentCount ?? 0) > 0 || (item.diagnosticCount ?? 0) > 0;
}

export default function MaladiesPage() {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.CLINIQUE.MALADIE_CREATE);
  const canUpdate = hasPermission(PERMISSIONS.CLINIQUE.MALADIE_UPDATE);
  const canDelete = hasPermission(PERMISSIONS.CLINIQUE.MALADIE_DELETE);
  const canExport = hasPermission(PERMISSIONS.CLINIQUE.MALADIE_EXPORT);
  const showActions = canUpdate || canDelete;
  const showSelection = canDelete;

  const [items, setItems] = useState([]);
  const [chapitres, setChapitres] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [chapitreFilter, setChapitreFilter] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_MALADIE_PAGE_SIZE);
  const [selectedIds, setSelectedIds] = useState([]);
  const [exportLoading, setExportLoading] = useState(null);

  const [formOpen, setFormOpen] = useState(false);
  const [formMode, setFormMode] = useState('create');
  const [formValues, setFormValues] = useState(EMPTY_MALADIE_FORM);
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');
  const [editing, setEditing] = useState(null);

  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleting, setDeleting] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [deleteError, setDeleteError] = useState('');

  const [bulkDeleteOpen, setBulkDeleteOpen] = useState(false);
  const [bulkDeleteLoading, setBulkDeleteLoading] = useState(false);
  const [bulkDeleteError, setBulkDeleteError] = useState('');

  useEffect(() => {
    fetchMaladieChapitresApi().then(setChapitres).catch(() => setChapitres([]));
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, chapitreFilter, limit]);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchMaladiesApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        chapitre: chapitreFilter || undefined,
      });
      setItems(result.items);
      setPagination(result.pagination);
      setSelectedIds((current) => current.filter((id) => result.items.some((item) => item.id === id)));
      if (result.pagination.totalPages > 0 && targetPage > result.pagination.totalPages) {
        setPage(result.pagination.totalPages);
      }
    } catch (error) {
      setListError(error.message || 'Impossible de charger les maladies.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, chapitreFilter]);

  useEffect(() => { load(page); }, [load, page]);

  const selectableIds = useMemo(
    () => items.filter((item) => !isItemLocked(item)).map((item) => item.id),
    [items],
  );

  const allSelectedOnPage = selectableIds.length > 0 && selectableIds.every((id) => selectedIds.includes(id));
  const someSelectedOnPage = selectableIds.some((id) => selectedIds.includes(id));

  const toggleSelectAll = () => {
    if (allSelectedOnPage) {
      setSelectedIds((current) => current.filter((id) => !selectableIds.includes(id)));
      return;
    }
    setSelectedIds((current) => [...new Set([...current, ...selectableIds])]);
  };

  const toggleSelectOne = (id) => {
    setSelectedIds((current) => (
      current.includes(id) ? current.filter((value) => value !== id) : [...current, id]
    ));
  };

  const openCreate = () => {
    setFormMode('create');
    setEditing(null);
    setFormValues(EMPTY_MALADIE_FORM);
    setFormError('');
    setFormOpen(true);
  };

  const openEdit = (item) => {
    setFormMode('edit');
    setEditing(item);
    setFormValues({
      codeCim10: item.codeCim10 ?? '',
      libelle: item.libelle ?? '',
      chapitre: item.chapitre ?? '',
    });
    setFormError('');
    setFormOpen(true);
  };

  const handleSubmit = async (payload) => {
    setFormLoading(true);
    setFormError('');
    try {
      if (formMode === 'create') {
        await createMaladieApi(payload);
        showSuccess('Maladie créée avec succès.');
        setPage(1);
      } else {
        await updateMaladieApi(editing.id, payload);
        showSuccess('Maladie mise à jour avec succès.');
      }
      setFormOpen(false);
      await load(formMode === 'create' ? 1 : page);
      fetchMaladieChapitresApi().then(setChapitres).catch(() => setChapitres([]));
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
      await deleteMaladieApi(deleting.id);
      setDeleteOpen(false);
      showSuccess('Maladie supprimée avec succès.');
      setSelectedIds((current) => current.filter((id) => id !== deleting.id));
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

  const handleBulkDelete = async () => {
    if (selectedIds.length === 0) return;
    setBulkDeleteLoading(true);
    setBulkDeleteError('');
    try {
      const result = await bulkDeleteMaladiesApi(selectedIds);
      setBulkDeleteOpen(false);
      setSelectedIds([]);
      showSuccess(`${result.deleted ?? 0} maladie(s) supprimée(s).`);
      if ((result.blocked ?? 0) > 0) {
        showError(`${result.blocked} maladie(s) n'ont pas pu être supprimées car elles sont utilisées.`);
      }
      await load(page);
    } catch (error) {
      setBulkDeleteError(error.message || 'Suppression impossible.');
      showError(error.message);
    } finally {
      setBulkDeleteLoading(false);
    }
  };

  const handleExport = async (format) => {
    setExportLoading(format);
    try {
      await exportResourceApi(clinique.maladies, format, {
        search: debouncedSearch || undefined,
        chapitre: chapitreFilter || undefined,
      });
      showSuccess(format === 'pdf' ? 'Export PDF ouvert dans le navigateur.' : 'Export Excel téléchargé.');
    } catch (error) {
      showError(error.message || 'Export impossible.');
    } finally {
      setExportLoading(null);
    }
  };

  const colSpan = (showSelection ? 1 : 0) + (showActions ? 5 : 4);

  return (
    <Stack spacing={3}>
      <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ xs: 'stretch', sm: 'flex-start' }} spacing={2}>
        <Box>
          <Typography level="h2" sx={{ fontWeight: 700, mb: 0.5 }}>Maladies (CIM-10)</Typography>
          <Typography level="body-md" sx={{ color: 'neutral.500' }}>Référentiel des maladies CIM-10 pour diagnostics et antécédents.</Typography>
        </Box>
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ alignSelf: { sm: 'center' } }}>
          {canExport ? <ExportButtons onExport={handleExport} loading={exportLoading} /> : null}
          {canDelete && selectedIds.length > 0 ? (
            <Button color="danger" variant="soft" startDecorator={<Trash2 size={18} />} onClick={() => { setBulkDeleteError(''); setBulkDeleteOpen(true); }}>
              Supprimer ({selectedIds.length})
            </Button>
          ) : null}
          {canCreate ? <Button startDecorator={<Plus size={18} />} onClick={openCreate}>Nouvelle maladie</Button> : null}
        </Stack>
      </Stack>

      <Card variant="outlined">
        <Stack spacing={2} sx={{ p: { xs: 2, md: 2.5 } }}>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
            <Input size="sm" placeholder="Rechercher par code CIM-10 ou libellé..." startDecorator={<Search size={16} />} value={search} onChange={(e) => setSearch(e.target.value)} sx={{ flex: 1, bgcolor: 'background.level1', border: 'none' }} />
            <Select size="sm" value={chapitreFilter} onChange={(_, value) => setChapitreFilter(value ?? '')} placeholder="Tous les chapitres" sx={{ minWidth: { md: 220 }, bgcolor: 'background.level1', border: 'none' }}>
              <Option value="">Tous les chapitres</Option>
              {chapitres.map((chapitre) => <Option key={chapitre} value={chapitre}>{chapitre}</Option>)}
            </Select>
          </Stack>

          {listError ? <Typography level="body-sm" color="danger">{listError}</Typography> : null}

          <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto', borderColor: LOTRU_NEUTRAL[200] }}>
            <Table stickyHeader hoverRow sx={{ '--TableCell-headBackground': LOTRU_NEUTRAL[50], '--TableRow-hoverBackground': LOTRU_PRIMARY[50] }}>
              <thead>
                <tr>
                  {showSelection ? (
                    <th style={{ width: 44 }}>
                      <Checkbox
                        size="sm"
                        checked={allSelectedOnPage}
                        indeterminate={someSelectedOnPage && !allSelectedOnPage}
                        disabled={selectableIds.length === 0}
                        onChange={toggleSelectAll}
                      />
                    </th>
                  ) : null}
                  <th>Code CIM-10</th>
                  <th>Libellé</th>
                  <th>Chapitre</th>
                  {showActions ? <th style={{ textAlign: 'right' }}>Actions</th> : null}
                </tr>
              </thead>
              <tbody>
                {loading ? <tr><td colSpan={colSpan}><Typography sx={{ py: 3, textAlign: 'center' }}>Chargement...</Typography></td></tr> : null}
                {!loading && items.length === 0 ? (
                  <tr><td colSpan={colSpan}>
                    <Stack alignItems="center" sx={{ py: 5 }}><HeartPulse size={22} /><Typography sx={{ mt: 1 }}>Aucune maladie</Typography></Stack>
                  </td></tr>
                ) : null}
                {!loading ? items.map((item) => {
                  const locked = isItemLocked(item);
                  return (
                    <tr key={item.id}>
                      {showSelection ? (
                        <td>
                          <Checkbox
                            size="sm"
                            checked={selectedIds.includes(item.id)}
                            disabled={locked}
                            onChange={() => toggleSelectOne(item.id)}
                          />
                        </td>
                      ) : null}
                      <td><Typography sx={{ fontWeight: 600 }}>{item.codeCim10}</Typography></td>
                      <td>{item.libelle}</td>
                      <td>{item.chapitre ?? '—'}</td>
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
                                disabled={locked}
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

          <AppPagination page={pagination.page} totalPages={pagination.totalPages} total={pagination.total} limit={pagination.limit} onPageChange={setPage} onLimitChange={setLimit} limitOptions={MALADIE_PAGE_SIZE_OPTIONS} loading={loading} />
        </Stack>
      </Card>

      <MaladieFormModal open={formOpen} mode={formMode} initialValues={formValues} loading={formLoading} error={formError} onClose={() => !formLoading && setFormOpen(false)} onSubmit={handleSubmit} />
      <MaladieDeleteModal open={deleteOpen} maladie={deleting} loading={deleteLoading} error={deleteError} onClose={() => !deleteLoading && setDeleteOpen(false)} onConfirm={handleDelete} />
      <MaladieBulkDeleteModal open={bulkDeleteOpen} count={selectedIds.length} loading={bulkDeleteLoading} error={bulkDeleteError} onClose={() => !bulkDeleteLoading && setBulkDeleteOpen(false)} onConfirm={handleBulkDelete} />
    </Stack>
  );
}
