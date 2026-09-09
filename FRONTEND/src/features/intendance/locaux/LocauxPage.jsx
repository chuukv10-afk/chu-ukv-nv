import { useCallback, useEffect, useState } from 'react';
import {
  Box, Button, Card, Chip, FormControl, FormLabel, IconButton, Input, Modal, ModalDialog, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { DoorOpen, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import ConfirmModal from '../../../components/ui/ConfirmModal.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { fetchServicesLookupApi } from '../biens/biensApi.js';
import { FAMILLE_STATUTS } from '../familles/familleConstants.js';
import { createLocalApi, deleteLocalApi, fetchLocauxApi, updateLocalApi } from './locauxApi.js';

const EMPTY_FORM = { serviceId: '', code: '', libelle: '', ordre: 0, statut: 'ACTIF' };
const PAGE_SIZES = [10, 25, 50];

export default function IntendanceLocauxPage() {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.INTENDANCE.LOCAL_CREATE);
  const canUpdate = hasPermission(PERMISSIONS.INTENDANCE.LOCAL_UPDATE);
  const canDelete = hasPermission(PERMISSIONS.INTENDANCE.LOCAL_DELETE);
  const showActions = canUpdate || canDelete;

  const [items, setItems] = useState([]);
  const [services, setServices] = useState([]);
  const [pagination, setPagination] = useState({ page: 1, limit: 10, total: 0, totalPages: 0 });
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [serviceFilter, setServiceFilter] = useState('');
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
  useEffect(() => { setPage(1); }, [debouncedSearch, limit, serviceFilter]);

  useEffect(() => {
    fetchServicesLookupApi().then(setServices).catch(() => setServices([]));
  }, []);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchLocauxApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        serviceId: serviceFilter || undefined,
      });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (error) {
      setListError(error.message || 'Impossible de charger les locaux.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, serviceFilter]);

  useEffect(() => { load(page); }, [load, page]);

  const openCreate = () => {
    setFormMode('create');
    setEditing(null);
    setForm({ ...EMPTY_FORM, serviceId: serviceFilter || '' });
    setFormError('');
    setFormOpen(true);
  };

  const openEdit = (item) => {
    setFormMode('edit');
    setEditing(item);
    setForm({
      serviceId: item.service?.id ?? '',
      code: item.code ?? '',
      libelle: item.libelle ?? '',
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
        ordre: Number(form.ordre) || 0,
        statut: form.statut,
      };
      if (formMode === 'create') {
        await createLocalApi({ ...payload, serviceId: Number(form.serviceId), code: form.code.trim().toUpperCase() });
        showSuccess('Local créé.');
        setPage(1);
        await load(1);
      } else {
        await updateLocalApi(editing.id, payload);
        showSuccess('Local mis à jour.');
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
      await deleteLocalApi(pendingDelete.id);
      showSuccess('Local supprimé.');
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
            <DoorOpen size={24} color={LOTRU_PRIMARY[600]} />
            <Box>
              <Typography level="h2" sx={{ fontWeight: 700 }}>Locaux</Typography>
              <Typography level="body-md" sx={{ color: 'neutral.500' }}>Salles et bureaux optionnels, par service.</Typography>
            </Box>
          </Stack>
          {canCreate ? <Button startDecorator={<Plus size={16} />} onClick={openCreate}>Nouveau local</Button> : null}
        </Stack>
        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
            <Input startDecorator={<Search size={16} />} placeholder="Rechercher…" value={search} onChange={(e) => setSearch(e.target.value)} sx={{ flex: 1 }} />
            <Select placeholder="Service" value={serviceFilter} onChange={(_, value) => setServiceFilter(value ?? '')} sx={{ minWidth: 220 }}>
              <Option value="">Tous les services</Option>
              {services.map((item) => <Option key={item.id} value={String(item.id)}>{item.code} — {item.libelle}</Option>)}
            </Select>
          </Stack>
        </Card>
        {listError ? <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>{listError}</Typography> : null}
        <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto' }}>
          <Table stickyHeader hoverRow sx={{ minWidth: 760 }}>
            <thead>
              <tr>
                <th>Service</th><th>Code</th><th>Libellé</th><th>Statut</th>
                {showActions ? <th style={{ textAlign: 'right' }}>Actions</th> : null}
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={showActions ? 5 : 4}><Typography level="body-sm" sx={{ p: 2 }}>Chargement…</Typography></td></tr>
              ) : items.length === 0 ? (
                <tr><td colSpan={showActions ? 5 : 4}><Typography level="body-sm" sx={{ p: 2, color: LOTRU_NEUTRAL[600] }}>Aucun local.</Typography></td></tr>
              ) : items.map((item) => (
                <tr key={item.id}>
                  <td>{item.service ? `${item.service.code} — ${item.service.libelle}` : '—'}</td>
                  <td><Typography level="body-sm" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>{item.code}</Typography></td>
                  <td>{item.libelle}</td>
                  <td><Chip size="sm" variant="soft" color={item.statut === 'ACTIF' ? 'success' : 'neutral'}>{item.statut === 'ACTIF' ? 'Actif' : 'Inactif'}</Chip></td>
                  {showActions ? (
                    <td style={{ textAlign: 'right' }}>
                      {canUpdate ? <IconButton size="sm" variant="plain" onClick={() => openEdit(item)}><Pencil size={16} /></IconButton> : null}
                      {canDelete ? <IconButton size="sm" variant="plain" color="danger" onClick={() => setPendingDelete(item)}><Trash2 size={16} /></IconButton> : null}
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
              <Typography level="title-lg" sx={{ fontWeight: 700 }}>{formMode === 'edit' ? 'Modifier le local' : 'Nouveau local'}</Typography>
            </Box>
            <Stack spacing={2} sx={{ p: 3 }}>
              {formError ? <Typography level="body-sm" color="danger">{formError}</Typography> : null}
              <FormControl required>
                <FormLabel>Service</FormLabel>
                <Select value={form.serviceId === '' ? null : String(form.serviceId)} onChange={(_, value) => setForm((c) => ({ ...c, serviceId: value ?? '' }))} disabled={formLoading || formMode === 'edit'}>
                  {services.map((item) => <Option key={item.id} value={String(item.id)}>{item.code} — {item.libelle}</Option>)}
                </Select>
              </FormControl>
              <FormControl required>
                <FormLabel>Code</FormLabel>
                <Input value={form.code} onChange={(e) => setForm((c) => ({ ...c, code: e.target.value.toUpperCase() }))} disabled={formLoading || formMode === 'edit'} />
              </FormControl>
              <FormControl required>
                <FormLabel>Libellé</FormLabel>
                <Input value={form.libelle} onChange={(e) => setForm((c) => ({ ...c, libelle: e.target.value }))} disabled={formLoading} placeholder="Ex. Salle de déchocage" />
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
        title="Supprimer le local"
        message={pendingDelete ? `Supprimer le local « ${pendingDelete.libelle} » ?` : ''}
        confirmLabel="Supprimer"
        loading={confirmLoading}
        onClose={() => setPendingDelete(null)}
        onConfirm={handleDelete}
      />
    </Box>
  );
}
