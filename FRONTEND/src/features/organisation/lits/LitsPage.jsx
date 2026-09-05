import { useCallback, useEffect, useState } from 'react';
import {
  Box, Button, Card, IconButton, Input, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { BedDouble, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import ExportButtons from '../../../components/export/ExportButtons.jsx';
import { organisation } from '../../../api/endpoints.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { exportResourceApi } from '../../../utils/exportApi.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { fetchChambresLookupApi } from '../chambres/chambresApi.js';
import LitDeleteModal from './components/LitDeleteModal.jsx';
import LitFormModal from './components/LitFormModal.jsx';
import { DEFAULT_LIT_PAGE_SIZE, EMPTY_LIT_FORM, LIT_PAGE_SIZE_OPTIONS } from './litConstants.js';
import { createLitApi, deleteLitApi, fetchLitsApi, updateLitApi } from './litsApi.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_LIT_PAGE_SIZE, total: 0, totalPages: 0 };

function formatDate(value) {
  if (!value) return '—';
  return new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value));
}

export default function LitsPage() {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.ORGANISATION.LIT_CREATE);
  const canUpdate = hasPermission(PERMISSIONS.ORGANISATION.LIT_UPDATE);
  const canDelete = hasPermission(PERMISSIONS.ORGANISATION.LIT_DELETE);
  const canExport = hasPermission(PERMISSIONS.ORGANISATION.LIT_EXPORT);
  const showActions = canUpdate || canDelete;

  const [items, setItems] = useState([]);
  const [chambres, setChambres] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [chambreFilter, setChambreFilter] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_LIT_PAGE_SIZE);
  const [formOpen, setFormOpen] = useState(false);
  const [formMode, setFormMode] = useState('create');
  const [formValues, setFormValues] = useState(EMPTY_LIT_FORM);
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');
  const [editing, setEditing] = useState(null);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleting, setDeleting] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [deleteError, setDeleteError] = useState('');
  const [exportLoading, setExportLoading] = useState(null);

  useEffect(() => {
    fetchChambresLookupApi().then(setChambres).catch(() => setChambres([]));
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, chambreFilter, limit]);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchLitsApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        chambreId: chambreFilter || undefined,
      });
      setItems(result.items);
      setPagination(result.pagination);
      if (result.pagination.totalPages > 0 && targetPage > result.pagination.totalPages) setPage(result.pagination.totalPages);
    } catch (error) {
      setListError(error.message || 'Impossible de charger les lits.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, chambreFilter]);

  useEffect(() => { load(page); }, [load, page]);

  const handleSubmit = async (payload) => {
    setFormLoading(true);
    setFormError('');
    try {
      if (formMode === 'create') {
        await createLitApi(payload);
        showSuccess('Lit créé avec succès.');
        setPage(1);
      } else {
        await updateLitApi(editing.id, { numeroLit: payload.numeroLit, chambreId: payload.chambreId });
        showSuccess('Lit mis à jour avec succès.');
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
      await deleteLitApi(deleting.id);
      setDeleteOpen(false);
      showSuccess('Lit supprimé avec succès.');
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

  const handleExport = async (format) => {
    setExportLoading(format);
    try {
      await exportResourceApi(organisation.lits, format, {
        search: debouncedSearch || undefined,
        chambreId: chambreFilter || undefined,
      });
      showSuccess(format === 'pdf' ? 'Export PDF ouvert dans le navigateur.' : 'Export Excel téléchargé.');
    } catch (error) {
      showError(error.message || 'Export impossible.');
    } finally {
      setExportLoading(null);
    }
  };

  const colSpan = showActions ? 7 : 6;

  return (
    <Stack spacing={3}>
      <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ xs: 'stretch', sm: 'flex-start' }} spacing={2}>
        <Box>
          <Typography level="h2" sx={{ fontWeight: 700, mb: 0.5 }}>Lits</Typography>
          <Typography level="body-md" sx={{ color: 'neutral.500' }}>Un lit appartient à une chambre.</Typography>
        </Box>
        {(canExport || canCreate) ? (
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ alignSelf: { sm: 'center' } }}>
            {canExport ? <ExportButtons onExport={handleExport} loading={exportLoading} /> : null}
            {canCreate ? <Button startDecorator={<Plus size={18} />} onClick={() => { setFormMode('create'); setFormValues(EMPTY_LIT_FORM); setFormError(''); setFormOpen(true); }}>Nouveau lit</Button> : null}
          </Stack>
        ) : null}
      </Stack>
      <Card variant="outlined">
        <Stack spacing={2} sx={{ p: { xs: 2, md: 2.5 } }}>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
            <Input size="sm" placeholder="Rechercher..." startDecorator={<Search size={16} />} value={search} onChange={(e) => setSearch(e.target.value)} sx={{ flex: 1, bgcolor: 'background.level1', border: 'none' }} />
            <Select size="sm" value={chambreFilter} onChange={(_, value) => setChambreFilter(value ?? '')} placeholder="Toutes les chambres" sx={{ minWidth: { md: 220 }, bgcolor: 'background.level1', border: 'none' }}>
              <Option value="">Toutes les chambres</Option>
              {chambres.map((c) => <Option key={c.id} value={c.id}>{c.libelle}</Option>)}
            </Select>
          </Stack>
          {listError ? <Typography level="body-sm" color="danger">{listError}</Typography> : null}
          <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto', borderColor: LOTRU_NEUTRAL[200] }}>
            <Table stickyHeader hoverRow sx={{ '--TableCell-headBackground': LOTRU_NEUTRAL[50], '--TableRow-hoverBackground': LOTRU_PRIMARY[50] }}>
              <thead>
                <tr><th>Code</th><th>Numéro</th><th>Bloc</th><th>Chambre</th><th>Visites</th><th>Créé le</th>{showActions ? <th style={{ textAlign: 'right' }}>Actions</th> : null}</tr>
              </thead>
              <tbody>
                {loading ? <tr><td colSpan={colSpan}><Typography sx={{ py: 3, textAlign: 'center' }}>Chargement...</Typography></td></tr> : null}
                {!loading && items.length === 0 ? <tr><td colSpan={colSpan}><Stack alignItems="center" sx={{ py: 5 }}><BedDouble size={22} /><Typography sx={{ mt: 1 }}>Aucun lit</Typography></Stack></td></tr> : null}
                {!loading ? items.map((item) => (
                  <tr key={item.id}>
                    <td><Typography sx={{ fontWeight: 600 }}>{item.code}</Typography></td>
                    <td>{item.numeroLit}</td>
                    <td>{item.bloc?.libelle ?? '—'}</td>
                    <td>{item.chambre?.libelle ?? '—'}</td>
                    <td>{item.visiteCount ?? 0}</td>
                    <td>{formatDate(item.createdAt)}</td>
                    {showActions ? (
                      <td>
                        <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                          {canUpdate ? <IconButton size="sm" variant="plain" onClick={() => { setFormMode('edit'); setEditing(item); setFormValues({ code: item.code ?? '', numeroLit: item.numeroLit ?? '', chambreId: item.chambreId ?? item.chambre?.id ?? '' }); setFormError(''); setFormOpen(true); }}><Pencil size={16} /></IconButton> : null}
                          {canDelete ? <IconButton size="sm" variant="plain" color="danger" onClick={() => { setDeleting(item); setDeleteError(''); setDeleteOpen(true); }} disabled={(item.visiteCount ?? 0) > 0}><Trash2 size={16} /></IconButton> : null}
                        </Stack>
                      </td>
                    ) : null}
                  </tr>
                )) : null}
              </tbody>
            </Table>
          </Sheet>
          <AppPagination page={pagination.page} totalPages={pagination.totalPages} total={pagination.total} limit={pagination.limit} onPageChange={setPage} onLimitChange={setLimit} limitOptions={LIT_PAGE_SIZE_OPTIONS} loading={loading} />
        </Stack>
      </Card>
      <LitFormModal open={formOpen} mode={formMode} initialValues={formValues} loading={formLoading} error={formError} onClose={() => !formLoading && setFormOpen(false)} onSubmit={handleSubmit} />
      <LitDeleteModal open={deleteOpen} lit={deleting} loading={deleteLoading} error={deleteError} onClose={() => !deleteLoading && setDeleteOpen(false)} onConfirm={handleDelete} />
    </Stack>
  );
}
