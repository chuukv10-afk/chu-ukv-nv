import { useCallback, useEffect, useState } from 'react';
import {
  Box, Button, Card, Chip, IconButton, Input, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { Building2, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import ExportButtons from '../../../components/export/ExportButtons.jsx';
import { organisation } from '../../../api/endpoints.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { exportResourceApi } from '../../../utils/exportApi.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import DepartementDeleteModal from './components/DepartementDeleteModal.jsx';
import DepartementFormModal from './components/DepartementFormModal.jsx';
import {
  DEFAULT_DEPARTEMENT_PAGE_SIZE, DEPARTEMENT_PAGE_SIZE_OPTIONS, DEPARTEMENT_TYPE_LABELS, DEPARTEMENT_TYPES, EMPTY_DEPARTEMENT_FORM,
} from './departementConstants.js';
import {
  createDepartementApi, deleteDepartementApi, fetchDepartementsApi, updateDepartementApi,
} from './departementsApi.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_DEPARTEMENT_PAGE_SIZE, total: 0, totalPages: 0 };

function formatDate(value) {
  if (!value) return '—';
  return new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value));
}

export default function DepartementsPage() {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.ORGANISATION.DEPARTEMENT_CREATE);
  const canUpdate = hasPermission(PERMISSIONS.ORGANISATION.DEPARTEMENT_UPDATE);
  const canDelete = hasPermission(PERMISSIONS.ORGANISATION.DEPARTEMENT_DELETE);
  const canExport = hasPermission(PERMISSIONS.ORGANISATION.DEPARTEMENT_EXPORT);
  const showActions = canUpdate || canDelete;

  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_DEPARTEMENT_PAGE_SIZE);

  const [formOpen, setFormOpen] = useState(false);
  const [formMode, setFormMode] = useState('create');
  const [formValues, setFormValues] = useState(EMPTY_DEPARTEMENT_FORM);
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');
  const [editing, setEditing] = useState(null);

  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleting, setDeleting] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [deleteError, setDeleteError] = useState('');
  const [exportLoading, setExportLoading] = useState(null);

  useEffect(() => {
    const t = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(t);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, typeFilter, limit]);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchDepartementsApi({
        page: targetPage, limit, search: debouncedSearch || undefined, type: typeFilter || undefined,
      });
      setItems(result.items);
      setPagination(result.pagination);
      if (result.pagination.totalPages > 0 && targetPage > result.pagination.totalPages) setPage(result.pagination.totalPages);
    } catch (e) {
      setListError(e.message || 'Impossible de charger les départements.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, typeFilter]);

  useEffect(() => { load(page); }, [load, page]);

  const openCreate = () => {
    setFormMode('create'); setEditing(null); setFormValues(EMPTY_DEPARTEMENT_FORM); setFormError(''); setFormOpen(true);
  };
  const openEdit = (item) => {
    setFormMode('edit'); setEditing(item);
    setFormValues({ code: item.code ?? '', libelle: item.libelle ?? '', type: item.type ?? 'CLINIQUE' });
    setFormError(''); setFormOpen(true);
  };
  const handleSubmit = async (payload) => {
    setFormLoading(true); setFormError('');
    try {
      if (formMode === 'create') { await createDepartementApi(payload); showSuccess('Département créé avec succès.'); setPage(1); }
      else { await updateDepartementApi(editing.id, payload); showSuccess('Département mis à jour avec succès.'); }
      setFormOpen(false); await load(formMode === 'create' ? 1 : page);
    } catch (e) { setFormError(e.message || 'Enregistrement impossible.'); }
    finally { setFormLoading(false); }
  };
  const handleDelete = async () => {
    if (!deleting) return;
    setDeleteLoading(true); setDeleteError('');
    try {
      await deleteDepartementApi(deleting.id);
      setDeleteOpen(false); showSuccess('Département supprimé avec succès.');
      const next = items.length === 1 && page > 1 ? page - 1 : page;
      setPage(next); await load(next);
    } catch (e) { setDeleteError(e.message || 'Suppression impossible.'); showError(e.message); }
    finally { setDeleteLoading(false); }
  };

  const handleExport = async (format) => {
    setExportLoading(format);
    try {
      await exportResourceApi(organisation.departements, format, {
        search: debouncedSearch || undefined,
        type: typeFilter || undefined,
      });
      showSuccess(format === 'pdf' ? 'Export PDF ouvert dans le navigateur.' : 'Export Excel téléchargé.');
    } catch (e) {
      showError(e.message || 'Export impossible.');
    } finally {
      setExportLoading(null);
    }
  };

  return (
    <Stack spacing={3}>
      <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ xs: 'stretch', sm: 'flex-start' }} spacing={2}>
        <Box>
          <Typography level="h2" sx={{ fontWeight: 700, mb: 0.5 }}>Départements</Typography>
          <Typography level="body-md" sx={{ color: 'neutral.500' }}>Gérez la structure des départements de l&apos;établissement.</Typography>
        </Box>
        {(canExport || canCreate) ? (
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ alignSelf: { sm: 'center' } }}>
            {canExport ? <ExportButtons onExport={handleExport} loading={exportLoading} /> : null}
            {canCreate ? <Button startDecorator={<Plus size={18} />} onClick={openCreate}>Nouveau département</Button> : null}
          </Stack>
        ) : null}
      </Stack>

      <Card variant="outlined">
        <Stack spacing={2} sx={{ p: { xs: 2, md: 2.5 } }}>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
            <Input size="sm" placeholder="Rechercher..." startDecorator={<Search size={16} />} value={search} onChange={(e) => setSearch(e.target.value)} sx={{ flex: 1, bgcolor: 'background.level1', border: 'none' }} />
            <Select size="sm" value={typeFilter} onChange={(_, v) => setTypeFilter(v ?? '')} placeholder="Tous les types" sx={{ minWidth: { md: 200 }, bgcolor: 'background.level1', border: 'none' }}>
              <Option value="">Tous les types</Option>
              {DEPARTEMENT_TYPES.map((t) => <Option key={t.value} value={t.value}>{t.label}</Option>)}
            </Select>
          </Stack>

          {listError ? <Typography level="body-sm" color="danger">{listError}</Typography> : null}

          <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto', borderColor: LOTRU_NEUTRAL[200] }}>
            <Table stickyHeader hoverRow sx={{ '--TableCell-headBackground': LOTRU_NEUTRAL[50], '--TableRow-hoverBackground': LOTRU_PRIMARY[50] }}>
              <thead>
                <tr>
                  <th>Code</th><th>Libellé</th><th>Type</th><th>Services</th><th>Créé le</th>
                  {showActions && <th style={{ textAlign: 'right' }}>Actions</th>}
                </tr>
              </thead>
              <tbody>
                {loading ? <tr><td colSpan={showActions ? 6 : 5}><Typography sx={{ py: 3, textAlign: 'center' }}>Chargement...</Typography></td></tr> : null}
                {!loading && items.length === 0 ? (
                  <tr><td colSpan={showActions ? 6 : 5}>
                    <Stack alignItems="center" sx={{ py: 5 }}><Building2 size={22} /><Typography sx={{ mt: 1 }}>Aucun département</Typography></Stack>
                  </td></tr>
                ) : null}
                {!loading ? items.map((item) => (
                  <tr key={item.id}>
                    <td><Typography sx={{ fontWeight: 600 }}>{item.code}</Typography></td>
                    <td>{item.libelle}</td>
                    <td><Chip size="sm" variant="soft">{DEPARTEMENT_TYPE_LABELS[item.type] ?? item.type}</Chip></td>
                    <td>{item.servicesCount ?? 0}</td>
                    <td>{formatDate(item.createdAt)}</td>
                    {showActions && (
                      <td>
                        <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                          {canUpdate ? <IconButton size="sm" variant="plain" onClick={() => openEdit(item)}><Pencil size={16} /></IconButton> : null}
                          {canDelete ? <IconButton size="sm" variant="plain" color="danger" onClick={() => { setDeleting(item); setDeleteOpen(true); }} disabled={(item.servicesCount ?? 0) > 0}><Trash2 size={16} /></IconButton> : null}
                        </Stack>
                      </td>
                    )}
                  </tr>
                )) : null}
              </tbody>
            </Table>
          </Sheet>

          <AppPagination page={pagination.page} totalPages={pagination.totalPages} total={pagination.total} limit={pagination.limit} onPageChange={setPage} onLimitChange={setLimit} limitOptions={DEPARTEMENT_PAGE_SIZE_OPTIONS} loading={loading} />
        </Stack>
      </Card>

      <DepartementFormModal open={formOpen} mode={formMode} initialValues={formValues} loading={formLoading} error={formError} onClose={() => !formLoading && setFormOpen(false)} onSubmit={handleSubmit} />
      <DepartementDeleteModal open={deleteOpen} departement={deleting} loading={deleteLoading} error={deleteError} onClose={() => !deleteLoading && setDeleteOpen(false)} onConfirm={handleDelete} />
    </Stack>
  );
}
