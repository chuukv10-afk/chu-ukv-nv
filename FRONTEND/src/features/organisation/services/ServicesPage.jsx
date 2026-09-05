import { useCallback, useEffect, useState } from 'react';
import {
  Box, Button, Card, IconButton, Input, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { Network, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import ExportButtons from '../../../components/export/ExportButtons.jsx';
import { organisation } from '../../../api/endpoints.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { exportResourceApi } from '../../../utils/exportApi.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { fetchDepartementsLookupApi } from '../departements/departementsApi.js';
import ServiceDeleteModal from './components/ServiceDeleteModal.jsx';
import ServiceFormModal from './components/ServiceFormModal.jsx';
import { DEFAULT_SERVICE_PAGE_SIZE, EMPTY_SERVICE_FORM, SERVICE_PAGE_SIZE_OPTIONS } from './serviceConstants.js';
import { createServiceApi, deleteServiceApi, fetchServicesApi, updateServiceApi } from './servicesApi.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_SERVICE_PAGE_SIZE, total: 0, totalPages: 0 };

function formatDate(value) {
  if (!value) return '—';
  return new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value));
}

export default function ServicesPage() {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.ORGANISATION.SERVICE_CREATE);
  const canUpdate = hasPermission(PERMISSIONS.ORGANISATION.SERVICE_UPDATE);
  const canDelete = hasPermission(PERMISSIONS.ORGANISATION.SERVICE_DELETE);
  const canExport = hasPermission(PERMISSIONS.ORGANISATION.SERVICE_EXPORT);
  const showActions = canUpdate || canDelete;

  const [items, setItems] = useState([]);
  const [departements, setDepartements] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [departementFilter, setDepartementFilter] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_SERVICE_PAGE_SIZE);

  const [formOpen, setFormOpen] = useState(false);
  const [formMode, setFormMode] = useState('create');
  const [formValues, setFormValues] = useState(EMPTY_SERVICE_FORM);
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');
  const [editing, setEditing] = useState(null);

  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleting, setDeleting] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [deleteError, setDeleteError] = useState('');
  const [exportLoading, setExportLoading] = useState(null);

  useEffect(() => {
    fetchDepartementsLookupApi().then(setDepartements).catch(() => setDepartements([]));
  }, []);

  useEffect(() => {
    const t = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(t);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, departementFilter, limit]);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchServicesApi({
        page: targetPage, limit, search: debouncedSearch || undefined,
        departementId: departementFilter || undefined,
      });
      setItems(result.items);
      setPagination(result.pagination);
      if (result.pagination.totalPages > 0 && targetPage > result.pagination.totalPages) setPage(result.pagination.totalPages);
    } catch (e) {
      setListError(e.message || 'Impossible de charger les services.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, departementFilter]);

  useEffect(() => { load(page); }, [load, page]);

  const openCreate = () => {
    setFormMode('create'); setEditing(null); setFormValues(EMPTY_SERVICE_FORM); setFormError(''); setFormOpen(true);
  };
  const openEdit = (item) => {
    setFormMode('edit'); setEditing(item);
    setFormValues({ code: item.code ?? '', libelle: item.libelle ?? '', departementId: item.departementId ?? item.departement?.id ?? '' });
    setFormError(''); setFormOpen(true);
  };
  const handleSubmit = async (payload) => {
    setFormLoading(true); setFormError('');
    try {
      if (formMode === 'create') {
        await createServiceApi(payload);
        showSuccess('Service créé avec succès.');
        setPage(1);
      } else {
        await updateServiceApi(editing.id, { libelle: payload.libelle, departementId: payload.departementId });
        showSuccess('Service mis à jour avec succès.');
      }
      setFormOpen(false); await load(formMode === 'create' ? 1 : page);
    } catch (e) { setFormError(e.message || 'Enregistrement impossible.'); }
    finally { setFormLoading(false); }
  };
  const handleDelete = async () => {
    if (!deleting) return;
    setDeleteLoading(true); setDeleteError('');
    try {
      await deleteServiceApi(deleting.id);
      setDeleteOpen(false); showSuccess('Service supprimé avec succès.');
      const next = items.length === 1 && page > 1 ? page - 1 : page;
      setPage(next); await load(next);
    } catch (e) { setDeleteError(e.message || 'Suppression impossible.'); showError(e.message); }
    finally { setDeleteLoading(false); }
  };

  const handleExport = async (format) => {
    setExportLoading(format);
    try {
      await exportResourceApi(organisation.services, format, {
        search: debouncedSearch || undefined,
        departementId: departementFilter || undefined,
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
          <Typography level="h2" sx={{ fontWeight: 700, mb: 0.5 }}>Services</Typography>
          <Typography level="body-md" sx={{ color: 'neutral.500' }}>Gérez les services rattachés aux départements.</Typography>
        </Box>
        {(canExport || canCreate) ? (
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ alignSelf: { sm: 'center' } }}>
            {canExport ? <ExportButtons onExport={handleExport} loading={exportLoading} /> : null}
            {canCreate ? <Button startDecorator={<Plus size={18} />} onClick={openCreate}>Nouveau service</Button> : null}
          </Stack>
        ) : null}
      </Stack>

      <Card variant="outlined">
        <Stack spacing={2} sx={{ p: { xs: 2, md: 2.5 } }}>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
            <Input size="sm" placeholder="Rechercher..." startDecorator={<Search size={16} />} value={search} onChange={(e) => setSearch(e.target.value)} sx={{ flex: 1, bgcolor: 'background.level1', border: 'none' }} />
            <Select size="sm" value={departementFilter} onChange={(_, v) => setDepartementFilter(v ?? '')} placeholder="Tous les départements" sx={{ minWidth: { md: 220 }, bgcolor: 'background.level1', border: 'none' }}>
              <Option value="">Tous les départements</Option>
              {departements.map((d) => <Option key={d.id} value={d.id}>{d.libelle}</Option>)}
            </Select>
          </Stack>

          {listError ? <Typography level="body-sm" color="danger">{listError}</Typography> : null}

          <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto', borderColor: LOTRU_NEUTRAL[200] }}>
            <Table stickyHeader hoverRow sx={{ '--TableCell-headBackground': LOTRU_NEUTRAL[50], '--TableRow-hoverBackground': LOTRU_PRIMARY[50] }}>
              <thead>
                <tr>
                  <th>Code</th><th>Libellé</th><th>Département</th><th>Personnel</th><th>Créé le</th>
                  {showActions && <th style={{ textAlign: 'right' }}>Actions</th>}
                </tr>
              </thead>
              <tbody>
                {loading ? <tr><td colSpan={showActions ? 6 : 5}><Typography sx={{ py: 3, textAlign: 'center' }}>Chargement...</Typography></td></tr> : null}
                {!loading && items.length === 0 ? (
                  <tr><td colSpan={showActions ? 6 : 5}>
                    <Stack alignItems="center" sx={{ py: 5 }}><Network size={22} /><Typography sx={{ mt: 1 }}>Aucun service</Typography></Stack>
                  </td></tr>
                ) : null}
                {!loading ? items.map((item) => (
                  <tr key={item.id}>
                    <td><Typography sx={{ fontWeight: 600 }}>{item.code}</Typography></td>
                    <td>{item.libelle}</td>
                    <td>{item.departement?.libelle ?? '—'}</td>
                    <td>{item.personnelCount ?? 0}</td>
                    <td>{formatDate(item.createdAt)}</td>
                    {showActions && (
                      <td>
                        <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                          {canUpdate ? <IconButton size="sm" variant="plain" onClick={() => openEdit(item)}><Pencil size={16} /></IconButton> : null}
                          {canDelete ? <IconButton size="sm" variant="plain" color="danger" onClick={() => { setDeleting(item); setDeleteOpen(true); }} disabled={(item.personnelCount ?? 0) > 0}><Trash2 size={16} /></IconButton> : null}
                        </Stack>
                      </td>
                    )}
                  </tr>
                )) : null}
              </tbody>
            </Table>
          </Sheet>

          <AppPagination page={pagination.page} totalPages={pagination.totalPages} total={pagination.total} limit={pagination.limit} onPageChange={setPage} onLimitChange={setLimit} limitOptions={SERVICE_PAGE_SIZE_OPTIONS} loading={loading} />
        </Stack>
      </Card>

      <ServiceFormModal open={formOpen} mode={formMode} initialValues={formValues} loading={formLoading} error={formError} onClose={() => !formLoading && setFormOpen(false)} onSubmit={handleSubmit} />
      <ServiceDeleteModal open={deleteOpen} service={deleting} loading={deleteLoading} error={deleteError} onClose={() => !deleteLoading && setDeleteOpen(false)} onConfirm={handleDelete} />
    </Stack>
  );
}
