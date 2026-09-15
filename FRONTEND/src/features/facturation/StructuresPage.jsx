import { useCallback, useEffect, useState } from 'react';
import {
  Box, Button, Card, Chip, IconButton, Input, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { Handshake, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import AppPagination from '../../components/ui/AppPagination.jsx';
import ConfirmModal from '../../components/ui/ConfirmModal.jsx';
import { PERMISSIONS } from '../../constants/permissions.js';
import { usePermissions } from '../../hooks/usePermissions.js';
import { useToast } from '../../hooks/useToast.js';
import PendingSyncChip from '../../offline/PendingSyncChip.jsx';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../theme/lotruPalette.js';
import StructureFormModal from './components/StructureFormModal.jsx';
import {
  DEFAULT_STRUCTURE_PAGE_SIZE,
  EMPTY_STRUCTURE_FORM,
  STRUCTURE_PAGE_SIZE_OPTIONS,
  STRUCTURE_STATUT_LABELS,
  STRUCTURE_TYPE_LABELS,
  STRUCTURE_TYPES,
} from './facturationConstants.js';
import {
  createStructureApi,
  deleteStructureApi,
  fetchStructuresApi,
  updateStructureApi,
} from './facturationApi.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_STRUCTURE_PAGE_SIZE, total: 0, totalPages: 0 };

export default function StructuresPage() {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.FACTURATION.STRUCTURE_CREATE);
  const canUpdate = hasPermission(PERMISSIONS.FACTURATION.STRUCTURE_UPDATE);
  const canDelete = hasPermission(PERMISSIONS.FACTURATION.STRUCTURE_DELETE);
  const showActions = canUpdate || canDelete;

  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_STRUCTURE_PAGE_SIZE);

  const [formOpen, setFormOpen] = useState(false);
  const [formMode, setFormMode] = useState('create');
  const [formValues, setFormValues] = useState(EMPTY_STRUCTURE_FORM);
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');
  const [editing, setEditing] = useState(null);
  const [pendingDelete, setPendingDelete] = useState(null);
  const [confirmLoading, setConfirmLoading] = useState(false);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, typeFilter, limit]);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchStructuresApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        type: typeFilter || undefined,
      });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (error) {
      setListError(error.message || 'Impossible de charger les structures.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, typeFilter]);

  useEffect(() => { load(page); }, [load, page]);

  const openCreate = () => {
    setFormMode('create');
    setEditing(null);
    setFormValues(EMPTY_STRUCTURE_FORM);
    setFormError('');
    setFormOpen(true);
  };

  const openEdit = (item) => {
    setFormMode('edit');
    setEditing(item);
    setFormValues({
      code: item.code ?? '',
      libelle: item.libelle ?? '',
      type: item.type ?? 'MUTUELLE',
      telephone: item.telephone ?? '',
      adresse: item.adresse ?? '',
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
        await createStructureApi(payload);
        showSuccess('Structure créée avec succès.');
        setPage(1);
        await load(1);
      } else {
        await updateStructureApi(editing.id, payload);
        showSuccess('Structure mise à jour avec succès.');
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
      await deleteStructureApi(pendingDelete.id);
      showSuccess('Structure supprimée.');
      setPendingDelete(null);
      await load(page);
    } catch (error) {
      showError(error.message || 'Suppression impossible.');
    } finally {
      setConfirmLoading(false);
    }
  };

  const colSpan = showActions ? 6 : 5;

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={2.5}>
        <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={1.5}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Handshake size={24} color={LOTRU_PRIMARY[600]} />
            <Box>
              <Typography level="h2" sx={{ fontWeight: 700 }}>Structures</Typography>
              <Typography level="body-md" sx={{ color: 'neutral.500' }}>
                Mutuelles, ONG, assurances, entreprises et partenaires liés aux catégories A1 et C.
              </Typography>
            </Box>
          </Stack>
          {canCreate ? (
            <Button startDecorator={<Plus size={16} />} onClick={openCreate}>Nouvelle structure</Button>
          ) : null}
        </Stack>

        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
            <Input
              startDecorator={<Search size={16} />}
              placeholder="Rechercher par code ou libellé…"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              sx={{ flex: 1 }}
            />
            <Select
              value={typeFilter}
              onChange={(_, value) => setTypeFilter(value ?? '')}
              placeholder="Type"
              sx={{ minWidth: 180 }}
            >
              <Option value="">Tous les types</Option>
              {STRUCTURE_TYPES.map((item) => (
                <Option key={item.value} value={item.value}>{item.label}</Option>
              ))}
            </Select>
          </Stack>
        </Card>

        {listError ? (
          <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
            {listError}
          </Typography>
        ) : null}

        <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto' }}>
          <Table stickyHeader hoverRow sx={{ minWidth: 820 }}>
            <thead>
              <tr>
                <th>Code</th>
                <th>Libellé</th>
                <th>Type</th>
                <th>Téléphone</th>
                <th>Statut</th>
                {showActions ? <th style={{ textAlign: 'right' }}>Actions</th> : null}
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={colSpan}><Typography level="body-sm" sx={{ p: 2 }}>Chargement…</Typography></td></tr>
              ) : items.length === 0 ? (
                <tr><td colSpan={colSpan}><Typography level="body-sm" sx={{ p: 2, color: LOTRU_NEUTRAL[600] }}>Aucune structure.</Typography></td></tr>
              ) : items.map((item) => (
                <tr key={item.id}>
                  <td><Typography level="body-sm" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>{item.code}</Typography></td>
                  <td>{item.libelle}</td>
                  <td>{STRUCTURE_TYPE_LABELS[item.type] ?? item.type}</td>
                  <td>{item.telephone || '—'}</td>
                  <td>
                    <Stack direction="row" spacing={0.5} flexWrap="wrap" useFlexGap>
                      <Chip size="sm" variant="soft" color={item.statut === 'ACTIF' ? 'success' : 'neutral'}>
                        {STRUCTURE_STATUT_LABELS[item.statut] ?? item.statut}
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
          limitOptions={STRUCTURE_PAGE_SIZE_OPTIONS}
          onPageChange={setPage}
          onLimitChange={setLimit}
        />
      </Stack>

      <StructureFormModal
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
        title="Supprimer la structure"
        message={pendingDelete ? `Supprimer la structure « ${pendingDelete.libelle} » ?` : ''}
        confirmLabel="Supprimer"
        loading={confirmLoading}
        onClose={() => setPendingDelete(null)}
        onConfirm={handleDelete}
      />
    </Box>
  );
}
