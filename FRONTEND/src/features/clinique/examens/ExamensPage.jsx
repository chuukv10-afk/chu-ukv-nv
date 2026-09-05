import { useCallback, useEffect, useState } from 'react';
import {
  Box, Button, Card, IconButton, Input, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { Microscope, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import ExportButtons from '../../../components/export/ExportButtons.jsx';
import { clinique, referentiel } from '../../../api/endpoints.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { exportResourceApi } from '../../../utils/exportApi.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { createReferentielApi } from '../../referentiel/shared/referentielApi.js';
import ExamenDeleteModal from './components/ExamenDeleteModal.jsx';
import ExamenFormModal from './components/ExamenFormModal.jsx';
import {
  DEFAULT_EXAMEN_PAGE_SIZE, EMPTY_EXAMEN_FORM, EXAMEN_PAGE_SIZE_OPTIONS,
} from './examenConstants.js';
import { createExamenApi, deleteExamenApi, fetchExamensApi, updateExamenApi } from './examensApi.js';

const typesExamenApi = createReferentielApi(referentiel.typesExamen);
const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_EXAMEN_PAGE_SIZE, total: 0, totalPages: 0 };

function formatDate(value) {
  if (!value) return '—';
  return new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value));
}

export default function ExamensPage() {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.CLINIQUE.EXAMEN_CREATE);
  const canUpdate = hasPermission(PERMISSIONS.CLINIQUE.EXAMEN_UPDATE);
  const canDelete = hasPermission(PERMISSIONS.CLINIQUE.EXAMEN_DELETE);
  const canExport = hasPermission(PERMISSIONS.CLINIQUE.EXAMEN_EXPORT);
  const showActions = canUpdate || canDelete;

  const [items, setItems] = useState([]);
  const [categories, setCategories] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_EXAMEN_PAGE_SIZE);
  const [formOpen, setFormOpen] = useState(false);
  const [formMode, setFormMode] = useState('create');
  const [formValues, setFormValues] = useState(EMPTY_EXAMEN_FORM);
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');
  const [editing, setEditing] = useState(null);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleting, setDeleting] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [deleteError, setDeleteError] = useState('');
  const [exportLoading, setExportLoading] = useState(null);

  useEffect(() => {
    typesExamenApi.fetchList({ page: 1, limit: 100 }).then((result) => setCategories(result.items)).catch(() => setCategories([]));
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, categoryFilter, limit]);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchExamensApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        typeExamenId: categoryFilter || undefined,
      });
      setItems(result.items);
      setPagination(result.pagination);
      if (result.pagination.totalPages > 0 && targetPage > result.pagination.totalPages) setPage(result.pagination.totalPages);
    } catch (error) {
      setListError(error.message || 'Impossible de charger les examens.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, categoryFilter]);

  useEffect(() => { load(page); }, [load, page]);

  const handleSubmit = async (payload) => {
    setFormLoading(true);
    setFormError('');
    try {
      if (formMode === 'create') {
        await createExamenApi(payload);
        showSuccess('Examen créé avec succès.');
        setPage(1);
      } else {
        await updateExamenApi(editing.id, { libelle: payload.libelle, typeExamenId: payload.typeExamenId });
        showSuccess('Examen mis à jour avec succès.');
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
      await deleteExamenApi(deleting.id);
      setDeleteOpen(false);
      showSuccess('Examen supprimé avec succès.');
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
      await exportResourceApi(clinique.examens, format, {
        search: debouncedSearch || undefined,
        typeExamenId: categoryFilter || undefined,
      });
      showSuccess(format === 'pdf' ? 'Export PDF ouvert dans le navigateur.' : 'Export Excel téléchargé.');
    } catch (error) {
      showError(error.message || 'Export impossible.');
    } finally {
      setExportLoading(null);
    }
  };

  const colSpan = showActions ? 6 : 5;

  return (
    <Stack spacing={3}>
      <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ xs: 'stretch', sm: 'flex-start' }} spacing={2}>
        <Box>
          <Typography level="h2" sx={{ fontWeight: 700, mb: 0.5 }}>Examens</Typography>
          <Typography level="body-md" sx={{ color: 'neutral.500' }}>Les examens sont regroupés par catégorie (type d&apos;examen).</Typography>
        </Box>
        {(canExport || canCreate) ? (
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ alignSelf: { sm: 'center' } }}>
            {canExport ? <ExportButtons onExport={handleExport} loading={exportLoading} /> : null}
            {canCreate ? <Button startDecorator={<Plus size={18} />} onClick={() => { setFormMode('create'); setFormValues(EMPTY_EXAMEN_FORM); setFormError(''); setFormOpen(true); }}>Nouvel examen</Button> : null}
          </Stack>
        ) : null}
      </Stack>
      <Card variant="outlined">
        <Stack spacing={2} sx={{ p: { xs: 2, md: 2.5 } }}>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
            <Input size="sm" placeholder="Rechercher..." startDecorator={<Search size={16} />} value={search} onChange={(e) => setSearch(e.target.value)} sx={{ flex: 1, bgcolor: 'background.level1', border: 'none' }} />
            <Select size="sm" value={categoryFilter} onChange={(_, value) => setCategoryFilter(value ?? '')} placeholder="Toutes les catégories" sx={{ minWidth: { md: 220 }, bgcolor: 'background.level1', border: 'none' }}>
              <Option value="">Toutes les catégories</Option>
              {categories.map((c) => <Option key={c.id} value={c.id}>{c.libelle}</Option>)}
            </Select>
          </Stack>
          {listError ? <Typography level="body-sm" color="danger">{listError}</Typography> : null}
          <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto', borderColor: LOTRU_NEUTRAL[200] }}>
            <Table stickyHeader hoverRow sx={{ '--TableCell-headBackground': LOTRU_NEUTRAL[50], '--TableRow-hoverBackground': LOTRU_PRIMARY[50] }}>
              <thead>
                <tr><th>Code</th><th>Libellé</th><th>Catégorie</th><th>Demandes</th><th>Créé le</th>{showActions ? <th style={{ textAlign: 'right' }}>Actions</th> : null}</tr>
              </thead>
              <tbody>
                {loading ? <tr><td colSpan={colSpan}><Typography sx={{ py: 3, textAlign: 'center' }}>Chargement...</Typography></td></tr> : null}
                {!loading && items.length === 0 ? <tr><td colSpan={colSpan}><Stack alignItems="center" sx={{ py: 5 }}><Microscope size={22} /><Typography sx={{ mt: 1 }}>Aucun examen</Typography></Stack></td></tr> : null}
                {!loading ? items.map((item) => (
                  <tr key={item.id}>
                    <td><Typography sx={{ fontWeight: 600 }}>{item.code}</Typography></td>
                    <td>{item.libelle}</td>
                    <td>{item.typeExamen?.libelle ?? '—'}</td>
                    <td>{item.demandeExamenCount ?? 0}</td>
                    <td>{formatDate(item.createdAt)}</td>
                    {showActions ? (
                      <td>
                        <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                          {canUpdate ? <IconButton size="sm" variant="plain" onClick={() => { setFormMode('edit'); setEditing(item); setFormValues({ code: item.code ?? '', libelle: item.libelle ?? '', typeExamenId: item.typeExamenId ?? item.typeExamen?.id ?? '' }); setFormError(''); setFormOpen(true); }}><Pencil size={16} /></IconButton> : null}
                          {canDelete ? <IconButton size="sm" variant="plain" color="danger" onClick={() => { setDeleting(item); setDeleteError(''); setDeleteOpen(true); }} disabled={(item.demandeExamenCount ?? 0) > 0}><Trash2 size={16} /></IconButton> : null}
                        </Stack>
                      </td>
                    ) : null}
                  </tr>
                )) : null}
              </tbody>
            </Table>
          </Sheet>
          <AppPagination page={pagination.page} totalPages={pagination.totalPages} total={pagination.total} limit={pagination.limit} onPageChange={setPage} onLimitChange={setLimit} limitOptions={EXAMEN_PAGE_SIZE_OPTIONS} loading={loading} />
        </Stack>
      </Card>
      <ExamenFormModal open={formOpen} mode={formMode} initialValues={formValues} loading={formLoading} error={formError} onClose={() => !formLoading && setFormOpen(false)} onSubmit={handleSubmit} />
      <ExamenDeleteModal open={deleteOpen} examen={deleting} loading={deleteLoading} error={deleteError} onClose={() => !deleteLoading && setDeleteOpen(false)} onConfirm={handleDelete} />
    </Stack>
  );
}
