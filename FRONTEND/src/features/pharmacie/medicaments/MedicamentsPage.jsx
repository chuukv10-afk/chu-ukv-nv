import { useCallback, useEffect, useState } from 'react';
import {
  Box, Button, Card, Chip, IconButton, Input, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { Package, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import ConfirmModal from '../../../components/ui/ConfirmModal.jsx';
import ExportButtons from '../../../components/export/ExportButtons.jsx';
import { pharmacie } from '../../../api/endpoints.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { exportResourceApi } from '../../../utils/exportApi.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { fetchFamillesActivesApi } from '../familles/famillesApi.js';
import { fetchUnitesActivesApi } from '../unites/unitesApi.js';
import MedicamentFormModal from './components/MedicamentFormModal.jsx';
import {
  DEFAULT_MEDICAMENT_PAGE_SIZE,
  EMPTY_MEDICAMENT_FORM,
  MEDICAMENT_PAGE_SIZE_OPTIONS,
  formatPrixVente,
} from './medicamentConstants.js';
import {
  createMedicamentApi,
  deleteMedicamentApi,
  fetchMedicamentsApi,
  updateMedicamentApi,
} from './medicamentsApi.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_MEDICAMENT_PAGE_SIZE, total: 0, totalPages: 0 };

export default function MedicamentsPage() {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.PHARMACIE.MEDICAMENT_CREATE);
  const canUpdate = hasPermission(PERMISSIONS.PHARMACIE.MEDICAMENT_UPDATE);
  const canDelete = hasPermission(PERMISSIONS.PHARMACIE.MEDICAMENT_DELETE);
  const canExport = hasPermission(PERMISSIONS.PHARMACIE.MEDICAMENT_EXPORT);
  const showActions = canUpdate || canDelete;

  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_MEDICAMENT_PAGE_SIZE);

  const [unites, setUnites] = useState([]);
  const [familles, setFamilles] = useState([]);

  const [formOpen, setFormOpen] = useState(false);
  const [formMode, setFormMode] = useState('create');
  const [formValues, setFormValues] = useState(EMPTY_MEDICAMENT_FORM);
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');
  const [editing, setEditing] = useState(null);
  const [pendingDelete, setPendingDelete] = useState(null);
  const [confirmLoading, setConfirmLoading] = useState(false);
  const [exportLoading, setExportLoading] = useState(null);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, limit]);

  useEffect(() => {
    let cancelled = false;
    const loadLookups = async () => {
      try {
        const [uniteItems, familleItems] = await Promise.all([
          fetchUnitesActivesApi(),
          fetchFamillesActivesApi(),
        ]);
        if (!cancelled) {
          setUnites(uniteItems);
          setFamilles(familleItems);
        }
      } catch {
        if (!cancelled) {
          setUnites([]);
          setFamilles([]);
        }
      }
    };
    loadLookups();
    return () => { cancelled = true; };
  }, []);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchMedicamentsApi({ page: targetPage, limit, search: debouncedSearch || undefined });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (error) {
      setListError(error.message || 'Impossible de charger les médicaments.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch]);

  useEffect(() => { load(page); }, [load, page]);

  const openCreate = () => {
    setFormMode('create');
    setEditing(null);
    setFormValues(EMPTY_MEDICAMENT_FORM);
    setFormError('');
    setFormOpen(true);
  };

  const openEdit = (item) => {
    setFormMode('edit');
    setEditing(item);
    setFormValues({
      code: item.code ?? '',
      libelle: item.libelle ?? '',
      dci: item.dci ?? '',
      forme: item.forme ?? '',
      dosage: item.dosage ?? '',
      uniteId: item.uniteId ? String(item.uniteId) : '',
      familleId: item.familleId ? String(item.familleId) : '',
      prixVente: item.prixVente ?? '',
      seuilAlerte: item.seuilAlerte ?? 0,
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
        await createMedicamentApi(payload);
        showSuccess('Médicament créé avec succès.');
        setPage(1);
        await load(1);
      } else {
        await updateMedicamentApi(editing.id, payload);
        showSuccess('Médicament mis à jour avec succès.');
        await load(page);
      }
      setFormOpen(false);
    } catch (error) {
      setFormError(error.message || 'Enregistrement impossible.');
    } finally {
      setFormLoading(false);
    }
  };

  const handleExport = async (format) => {
    setExportLoading(format);
    try {
      await exportResourceApi(pharmacie.medicaments, format, {
        search: debouncedSearch || undefined,
      });
      showSuccess(format === 'pdf' ? 'Export PDF ouvert dans le navigateur.' : 'Export Excel téléchargé.');
    } catch (error) {
      showError(error.message || 'Export impossible.');
    } finally {
      setExportLoading(null);
    }
  };

  const handleDelete = async () => {
    if (!pendingDelete) return;
    setConfirmLoading(true);
    try {
      await deleteMedicamentApi(pendingDelete.id);
      showSuccess('Médicament supprimé.');
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
            <Package size={24} color={LOTRU_PRIMARY[600]} />
            <Box>
              <Typography level="h2" sx={{ fontWeight: 700 }}>Médicaments</Typography>
              <Typography level="body-md" sx={{ color: 'neutral.500' }}>
                Catalogue pharmacie : spécialité, unité, famille et tarif de vente.
              </Typography>
            </Box>
          </Stack>
          {(canExport || canCreate) ? (
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
              {canExport ? <ExportButtons onExport={handleExport} loading={exportLoading} /> : null}
              {canCreate ? (
                <Button startDecorator={<Plus size={16} />} onClick={openCreate}>Nouveau médicament</Button>
              ) : null}
            </Stack>
          ) : null}
        </Stack>

        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
          <Input
            startDecorator={<Search size={16} />}
            placeholder="Rechercher par code, libellé ou DCI…"
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
          <Table stickyHeader hoverRow sx={{ minWidth: 780 }}>
            <thead>
              <tr>
                <th>Libellé</th>
                <th>Unité</th>
                <th>Famille</th>
                <th>Prix de vente</th>
                <th>Stock</th>
                {showActions ? <th style={{ textAlign: 'right' }}>Actions</th> : null}
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={showActions ? 6 : 5}><Typography level="body-sm" sx={{ p: 2 }}>Chargement…</Typography></td></tr>
              ) : items.length === 0 ? (
                <tr><td colSpan={showActions ? 6 : 5}><Typography level="body-sm" sx={{ p: 2, color: LOTRU_NEUTRAL[600] }}>Aucun médicament.</Typography></td></tr>
              ) : items.map((item) => (
                <tr key={item.id}>
                  <td>
                    <Typography level="body-sm" sx={{ fontWeight: 600 }}>{item.libelle}</Typography>
                    {item.dosage || item.forme ? (
                      <Typography level="body-xs" sx={{ color: LOTRU_NEUTRAL[500] }}>
                        {[item.forme, item.dosage].filter(Boolean).join(' · ')}
                      </Typography>
                    ) : null}
                  </td>
                  <td>{item.unite?.code ?? '—'}</td>
                  <td>{item.famille?.libelle ?? '—'}</td>
                  <td>{formatPrixVente(item.prixVente)}</td>
                  <td>
                    <Chip
                      size="sm"
                      variant="soft"
                      color={(item.stockDisponible ?? 0) <= (item.seuilAlerte ?? 0) ? 'warning' : 'success'}
                    >
                      {item.stockDisponible ?? 0}
                    </Chip>
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
          limitOptions={MEDICAMENT_PAGE_SIZE_OPTIONS}
          onPageChange={setPage}
          onLimitChange={setLimit}
        />
      </Stack>

      <MedicamentFormModal
        open={formOpen}
        mode={formMode}
        initialValues={formValues}
        unites={unites}
        familles={familles}
        loading={formLoading}
        error={formError}
        onClose={() => setFormOpen(false)}
        onSubmit={handleSubmit}
      />
      <ConfirmModal
        open={Boolean(pendingDelete)}
        title="Supprimer le médicament"
        message={pendingDelete ? `Supprimer le médicament « ${pendingDelete.libelle} » ?` : ''}
        confirmLabel="Supprimer"
        loading={confirmLoading}
        onClose={() => setPendingDelete(null)}
        onConfirm={handleDelete}
      />
    </Box>
  );
}
