import { useCallback, useEffect, useState } from 'react';
import {
  Box, Button, Card, Chip, FormControl, FormLabel, IconButton, Input, Modal, ModalDialog, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { Layers, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import ConfirmModal from '../../../components/ui/ConfirmModal.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { FAMILLE_STATUTS } from '../familles/familleConstants.js';
import { fetchFamillesActivesApi } from '../familles/famillesApi.js';
import { createTypeApi, deleteTypeApi, fetchTypesApi, updateTypeApi } from './typesApi.js';

const EMPTY_FORM = { code: '', libelle: '', familleId: '', ordre: 0, statut: 'ACTIF' };
const PAGE_SIZES = [10, 25, 50];

export default function IntendanceTypesPage() {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.INTENDANCE.TYPE_CREATE);
  const canUpdate = hasPermission(PERMISSIONS.INTENDANCE.TYPE_UPDATE);
  const canDelete = hasPermission(PERMISSIONS.INTENDANCE.TYPE_DELETE);
  const showActions = canUpdate || canDelete;

  const [items, setItems] = useState([]);
  const [familles, setFamilles] = useState([]);
  const [pagination, setPagination] = useState({ page: 1, limit: 10, total: 0, totalPages: 0 });
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [familleFilter, setFamilleFilter] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(10);
  const [formOpen, setFormOpen] = useState(false);
  const [formMode, setFormMode] = useState('create');
  const [form, setForm] = useState(EMPTY_FORM);
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');
  const [editing, setEditing] = useState(null);
  const [pendingDelete, setPendingDelete] = useState(null);
  const [confirmLoading, setConfirmLoading] = useState(false);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);
  useEffect(() => { setPage(1); }, [debouncedSearch, limit, familleFilter]);

  useEffect(() => {
    fetchFamillesActivesApi().then(setFamilles).catch(() => setFamilles([]));
  }, []);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchTypesApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        familleId: familleFilter || undefined,
      });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (error) {
      setListError(error.message || 'Impossible de charger les types.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, familleFilter]);

  useEffect(() => { load(page); }, [load, page]);

  const openCreate = () => {
    setFormMode('create');
    setEditing(null);
    setForm(EMPTY_FORM);
    setFormError('');
    setFormOpen(true);
  };

  const openEdit = (item) => {
    setFormMode('edit');
    setEditing(item);
    setForm({
      code: item.code ?? '',
      libelle: item.libelle ?? '',
      familleId: item.famille?.id ?? '',
      ordre: item.ordre ?? 0,
      statut: item.statut ?? 'ACTIF',
    });
    setFormError('');
    setFormOpen(true);
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setFormLoading(true);
    setFormError('');
    try {
      const payload = {
        libelle: form.libelle.trim(),
        familleId: Number(form.familleId),
        ordre: Number(form.ordre) || 0,
        statut: form.statut,
      };
      if (formMode === 'create') {
        await createTypeApi({ ...payload, code: form.code.trim().toUpperCase() });
        showSuccess('Type créé avec succès.');
        setPage(1);
        await load(1);
      } else {
        await updateTypeApi(editing.id, payload);
        showSuccess('Type mis à jour.');
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
      await deleteTypeApi(pendingDelete.id);
      showSuccess('Type supprimé.');
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
            <Layers size={24} color={LOTRU_PRIMARY[600]} />
            <Box>
              <Typography level="h2" sx={{ fontWeight: 700 }}>Types de bien</Typography>
              <Typography level="body-md" sx={{ color: 'neutral.500' }}>Ordinateur, table, lit… — clé des effectifs.</Typography>
            </Box>
          </Stack>
          {canCreate ? <Button startDecorator={<Plus size={16} />} onClick={openCreate}>Nouveau type</Button> : null}
        </Stack>
        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
            <Input startDecorator={<Search size={16} />} placeholder="Rechercher…" value={search} onChange={(e) => setSearch(e.target.value)} sx={{ flex: 1 }} />
            <Select placeholder="Famille" value={familleFilter} onChange={(_, value) => setFamilleFilter(value ?? '')} sx={{ minWidth: 200 }}>
              <Option value="">Toutes les familles</Option>
              {familles.map((item) => <Option key={item.id} value={String(item.id)}>{item.code} — {item.libelle}</Option>)}
            </Select>
          </Stack>
        </Card>
        {listError ? <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>{listError}</Typography> : null}
        <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto' }}>
          <Table stickyHeader hoverRow sx={{ minWidth: 800 }}>
            <thead>
              <tr>
                <th>Code</th><th>Libellé</th><th>Famille</th><th>Statut</th>
                {showActions ? <th style={{ textAlign: 'right' }}>Actions</th> : null}
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={showActions ? 5 : 4}><Typography level="body-sm" sx={{ p: 2 }}>Chargement…</Typography></td></tr>
              ) : items.length === 0 ? (
                <tr><td colSpan={showActions ? 5 : 4}><Typography level="body-sm" sx={{ p: 2, color: LOTRU_NEUTRAL[600] }}>Aucun type.</Typography></td></tr>
              ) : items.map((item) => (
                <tr key={item.id}>
                  <td><Typography level="body-sm" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>{item.code}</Typography></td>
                  <td>{item.libelle}</td>
                  <td>{item.famille ? `${item.famille.code} — ${item.famille.libelle}` : '—'}</td>
                  <td>
                    <Stack direction="row" spacing={0.5}>
                      <Chip size="sm" variant="soft" color={item.statut === 'ACTIF' ? 'success' : 'neutral'}>{item.statut === 'ACTIF' ? 'Actif' : 'Inactif'}</Chip>
                      {item.seed ? <Chip size="sm" variant="outlined">Réf.</Chip> : null}
                    </Stack>
                  </td>
                  {showActions ? (
                    <td style={{ textAlign: 'right' }}>
                      {canUpdate ? <IconButton size="sm" variant="plain" onClick={() => openEdit(item)}><Pencil size={16} /></IconButton> : null}
                      {canDelete && !item.seed ? <IconButton size="sm" variant="plain" color="danger" onClick={() => setPendingDelete(item)}><Trash2 size={16} /></IconButton> : null}
                    </td>
                  ) : null}
                </tr>
              ))}
            </tbody>
          </Table>
        </Sheet>
        <AppPagination page={pagination.page} totalPages={pagination.totalPages} total={pagination.total} limit={limit} limitOptions={PAGE_SIZES} onPageChange={setPage} onLimitChange={setLimit} />
      </Stack>

      <Modal open={formOpen} onClose={() => setFormOpen(false)}>
        <ModalDialog sx={{ borderRadius: 'xl', maxWidth: 520, width: '100%', p: 0, overflow: 'hidden' }}>
          <Box component="form" onSubmit={handleSubmit}>
            <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider' }}>
              <Typography level="title-lg" sx={{ fontWeight: 700 }}>{formMode === 'edit' ? 'Modifier le type' : 'Nouveau type'}</Typography>
            </Box>
            <Stack spacing={2} sx={{ p: 3 }}>
              {formError ? <Typography level="body-sm" color="danger">{formError}</Typography> : null}
              <FormControl required>
                <FormLabel>Code</FormLabel>
                <Input value={form.code} onChange={(e) => setForm((c) => ({ ...c, code: e.target.value.toUpperCase() }))} disabled={formLoading || formMode === 'edit'} />
              </FormControl>
              <FormControl required>
                <FormLabel>Libellé</FormLabel>
                <Input value={form.libelle} onChange={(e) => setForm((c) => ({ ...c, libelle: e.target.value }))} disabled={formLoading} />
              </FormControl>
              <FormControl required>
                <FormLabel>Famille</FormLabel>
                <Select value={form.familleId === '' ? null : String(form.familleId)} onChange={(_, value) => setForm((c) => ({ ...c, familleId: value ?? '' }))} disabled={formLoading}>
                  {familles.map((item) => <Option key={item.id} value={String(item.id)}>{item.code} — {item.libelle}</Option>)}
                </Select>
              </FormControl>
              <FormControl>
                <FormLabel>Ordre</FormLabel>
                <Input type="number" value={form.ordre} onChange={(e) => setForm((c) => ({ ...c, ordre: e.target.value }))} disabled={formLoading} />
              </FormControl>
              <FormControl required>
                <FormLabel>Statut</FormLabel>
                <Select value={form.statut} onChange={(_, value) => setForm((c) => ({ ...c, statut: value ?? 'ACTIF' }))} disabled={formLoading}>
                  {FAMILLE_STATUTS.map((item) => <Option key={item.value} value={item.value}>{item.label}</Option>)}
                </Select>
              </FormControl>
            </Stack>
            <Stack direction="row" spacing={1.5} justifyContent="flex-end" sx={{ px: 3, py: 2, borderTop: '1px solid', borderColor: 'divider' }}>
              <Button variant="plain" color="neutral" onClick={() => setFormOpen(false)} disabled={formLoading}>Annuler</Button>
              <Button type="submit" loading={formLoading}>{formMode === 'edit' ? 'Enregistrer' : 'Créer'}</Button>
            </Stack>
          </Box>
        </ModalDialog>
      </Modal>
      <ConfirmModal
        open={Boolean(pendingDelete)}
        title="Supprimer le type"
        message={pendingDelete ? `Supprimer le type « ${pendingDelete.libelle} » ?` : ''}
        confirmLabel="Supprimer"
        loading={confirmLoading}
        onClose={() => setPendingDelete(null)}
        onConfirm={handleDelete}
      />
    </Box>
  );
}
