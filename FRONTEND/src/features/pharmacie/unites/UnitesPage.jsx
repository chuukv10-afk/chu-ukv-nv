import { useCallback, useEffect, useState } from 'react';
import {
  Box, Button, Card, Chip, IconButton, Input, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { Pencil, Pill, Plus, Search, Trash2 } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import ConfirmModal from '../../../components/ui/ConfirmModal.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import PendingSyncChip from '../../../offline/PendingSyncChip.jsx';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import UniteFormModal from './components/UniteFormModal.jsx';
import {
  DEFAULT_UNITE_PAGE_SIZE,
  EMPTY_UNITE_FORM,
  UNITE_PAGE_SIZE_OPTIONS,
  UNITE_STATUT_LABELS,
} from './uniteConstants.js';
import {
  createUniteApi,
  deleteUniteApi,
  fetchUnitesApi,
  updateUniteApi,
} from './unitesApi.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_UNITE_PAGE_SIZE, total: 0, totalPages: 0 };

export default function UnitesPage() {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.PHARMACIE.UNITE_CREATE);
  const canUpdate = hasPermission(PERMISSIONS.PHARMACIE.UNITE_UPDATE);
  const canDelete = hasPermission(PERMISSIONS.PHARMACIE.UNITE_DELETE);
  const showActions = canUpdate || canDelete;

  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_UNITE_PAGE_SIZE);

  const [formOpen, setFormOpen] = useState(false);
  const [formMode, setFormMode] = useState('create');
  const [formValues, setFormValues] = useState(EMPTY_UNITE_FORM);
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');
  const [editing, setEditing] = useState(null);
  const [pendingDelete, setPendingDelete] = useState(null);
  const [confirmLoading, setConfirmLoading] = useState(false);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, limit]);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchUnitesApi({ page: targetPage, limit, search: debouncedSearch || undefined });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (error) {
      setListError(error.message || 'Impossible de charger les unités.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch]);

  useEffect(() => { load(page); }, [load, page]);

  const openCreate = () => {
    setFormMode('create');
    setEditing(null);
    setFormValues(EMPTY_UNITE_FORM);
    setFormError('');
    setFormOpen(true);
  };

  const openEdit = (item) => {
    setFormMode('edit');
    setEditing(item);
    setFormValues({
      code: item.code ?? '',
      libelle: item.libelle ?? '',
      ordre: item.ordre ?? 0,
      statut: item.statut ?? 'ACTIF',
    });
    setFormError('');
    setFormOpen(true);
  };

  const handleSubmit = async (payload) => {
    setFormLoading(true);
    setFormError('');
    try {
      if (formMode === 'create') {
        await createUniteApi(payload);
        showSuccess('Unité créée avec succès.');
        setPage(1);
        await load(1);
      } else {
        await updateUniteApi(editing.id, payload);
        showSuccess('Unité mise à jour avec succès.');
        await load(page);
      }
      setFormOpen(false);
    } catch (error) {
      setFormError(error.message || 'Enregistrement impossible.');
    } finally {
      setFormLoading(false);
    }
  };

  const handleDelete = async () => {
    if (!pendingDelete) return;
    setConfirmLoading(true);
    try {
      await deleteUniteApi(pendingDelete.id);
      showSuccess('Unité supprimée.');
      setPendingDelete(null);
      await load(page);
    } catch (error) {
      showError(error.message || 'Suppression impossible.');
    } finally {
      setConfirmLoading(false);
    }
  };

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={2.5}>
        <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={1.5}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Pill size={24} color={LOTRU_PRIMARY[600]} />
            <Box>
              <Typography level="h2" sx={{ fontWeight: 700 }}>Unités de médicament</Typography>
              <Typography level="body-md" sx={{ color: 'neutral.500' }}>
                Unités de stock et de vente (comprimé, ampoule, flacon…).
              </Typography>
            </Box>
          </Stack>
          {canCreate ? (
            <Button startDecorator={<Plus size={16} />} onClick={openCreate}>Nouvelle unité</Button>
          ) : null}
        </Stack>

        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
          <Input
            startDecorator={<Search size={16} />}
            placeholder="Rechercher par code ou libellé…"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />
        </Card>

        {listError ? (
          <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
            {listError}
          </Typography>
        ) : null}

        <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto' }}>
          <Table stickyHeader hoverRow sx={{ minWidth: 720 }}>
            <thead>
              <tr>
                <th>Code</th>
                <th>Libellé</th>
                <th>Ordre</th>
                <th>Statut</th>
                {showActions ? <th style={{ textAlign: 'right' }}>Actions</th> : null}
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={showActions ? 5 : 4}><Typography level="body-sm" sx={{ p: 2 }}>Chargement…</Typography></td></tr>
              ) : items.length === 0 ? (
                <tr><td colSpan={showActions ? 5 : 4}><Typography level="body-sm" sx={{ p: 2, color: LOTRU_NEUTRAL[600] }}>Aucune unité.</Typography></td></tr>
              ) : items.map((item) => (
                <tr key={item.id}>
                  <td><Typography level="body-sm" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>{item.code}</Typography></td>
                  <td>{item.libelle}</td>
                  <td>{item.ordre ?? 0}</td>
                  <td>
                    <Stack direction="row" spacing={0.5} flexWrap="wrap" useFlexGap>
                      <Chip size="sm" variant="soft" color={item.statut === 'ACTIF' ? 'success' : 'neutral'}>
                        {UNITE_STATUT_LABELS[item.statut] ?? item.statut}
                      </Chip>
                      <PendingSyncChip show={item.pendingSync} />
                    </Stack>
                  </td>
                  {showActions ? (
                    <td style={{ textAlign: 'right' }}>
                      <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                        {canUpdate ? (
                          <IconButton size="sm" variant="plain" onClick={() => openEdit(item)}><Pencil size={16} /></IconButton>
                        ) : null}
                        {canDelete ? (
                          <IconButton size="sm" variant="plain" color="danger" onClick={() => setPendingDelete(item)}><Trash2 size={16} /></IconButton>
                        ) : null}
                      </Stack>
                    </td>
                  ) : null}
                </tr>
              ))}
            </tbody>
          </Table>
        </Sheet>

        <AppPagination
          page={pagination.page}
          totalPages={pagination.totalPages}
          total={pagination.total}
          limit={limit}
          limitOptions={UNITE_PAGE_SIZE_OPTIONS}
          onPageChange={setPage}
          onLimitChange={setLimit}
        />
      </Stack>

      <UniteFormModal
        open={formOpen}
        mode={formMode}
        initialValues={formValues}
        loading={formLoading}
        error={formError}
        onClose={() => setFormOpen(false)}
        onSubmit={handleSubmit}
      />
      <ConfirmModal
        open={Boolean(pendingDelete)}
        title="Supprimer l’unité"
        message={pendingDelete ? `Supprimer l’unité « ${pendingDelete.libelle} » ?` : ''}
        confirmLabel="Supprimer"
        loading={confirmLoading}
        onClose={() => setPendingDelete(null)}
        onConfirm={handleDelete}
      />
    </Box>
  );
}
