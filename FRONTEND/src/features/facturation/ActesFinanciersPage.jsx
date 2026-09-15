import { useCallback, useEffect, useRef, useState } from 'react';
import {
  Box, Button, Card, Chip, IconButton, Input, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { Pencil, Plus, Search, Trash2, Upload, Wallet } from 'lucide-react';
import AppPagination from '../../components/ui/AppPagination.jsx';
import ConfirmModal from '../../components/ui/ConfirmModal.jsx';
import ExportButtons from '../../components/export/ExportButtons.jsx';
import { facturation } from '../../api/endpoints.js';
import { PERMISSIONS } from '../../constants/permissions.js';
import { usePermissions } from '../../hooks/usePermissions.js';
import { useToast } from '../../hooks/useToast.js';
import { exportResourceApi } from '../../utils/exportApi.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../theme/lotruPalette.js';
import ActeFormModal from './components/ActeFormModal.jsx';
import {
  ACTE_PAGE_SIZE_OPTIONS,
  DEFAULT_ACTE_PAGE_SIZE,
  EMPTY_ACTE_FORM,
} from './facturationConstants.js';
import {
  createActeFinancierApi,
  deleteActeFinancierApi,
  fetchActesFinanciersApi,
  fetchActesFinanciersMetaApi,
  importGrilleTarifaireApi,
  updateActeFinancierApi,
} from './facturationApi.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_ACTE_PAGE_SIZE, total: 0, totalPages: 0 };

function formatCdf(value) {
  if (value === null || value === undefined || value === '') return '—';
  const amount = Number(value);
  if (Number.isNaN(amount)) return String(value);
  return amount.toLocaleString('fr-CD', { maximumFractionDigits: 2 });
}

export default function ActesFinanciersPage() {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.FACTURATION.ACTE_CREATE);
  const canUpdate = hasPermission(PERMISSIONS.FACTURATION.ACTE_UPDATE);
  const canDelete = hasPermission(PERMISSIONS.FACTURATION.ACTE_DELETE);
  const canImport = hasPermission(PERMISSIONS.FACTURATION.ACTE_IMPORT);
  const canExport = hasPermission(PERMISSIONS.FACTURATION.ACTE_EXPORT);
  const showActions = canUpdate || canDelete;
  const fileInputRef = useRef(null);

  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [serviceFilter, setServiceFilter] = useState('');
  const [serviceGrilles, setServiceGrilles] = useState([]);
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_ACTE_PAGE_SIZE);
  const [exportLoading, setExportLoading] = useState(null);
  const [importLoading, setImportLoading] = useState(false);

  const [formOpen, setFormOpen] = useState(false);
  const [formMode, setFormMode] = useState('create');
  const [formValues, setFormValues] = useState(EMPTY_ACTE_FORM);
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');
  const [editing, setEditing] = useState(null);
  const [pendingDelete, setPendingDelete] = useState(null);
  const [confirmLoading, setConfirmLoading] = useState(false);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, serviceFilter, limit]);

  const refreshMeta = useCallback(async () => {
    try {
      const meta = await fetchActesFinanciersMetaApi();
      setServiceGrilles(Array.isArray(meta.serviceGrilles) ? meta.serviceGrilles : []);
    } catch {
      setServiceGrilles([]);
    }
  }, []);

  useEffect(() => { refreshMeta(); }, [refreshMeta]);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchActesFinanciersApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        serviceGrille: serviceFilter || undefined,
      });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (error) {
      setListError(error.message || 'Impossible de charger la grille tarifaire.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, serviceFilter]);

  useEffect(() => { load(page); }, [load, page]);

  const handleExport = async (format) => {
    setExportLoading(format);
    try {
      await exportResourceApi(facturation.actes, format, {
        search: debouncedSearch || undefined,
        serviceGrille: serviceFilter || undefined,
      });
    } catch (error) {
      showError(error.message || 'Export impossible.');
    } finally {
      setExportLoading(null);
    }
  };

  const handleImport = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    setImportLoading(true);
    try {
      const result = await importGrilleTarifaireApi(file);
      showSuccess(`Import terminé : ${result.imported ?? 0} créé(s), ${result.updated ?? 0} mis à jour.`);
      await refreshMeta();
      setPage(1);
      await load(1);
    } catch (error) {
      showError(error.message || 'Import impossible.');
    } finally {
      setImportLoading(false);
    }
  };

  const openCreate = () => {
    setFormMode('create');
    setEditing(null);
    setFormValues(EMPTY_ACTE_FORM);
    setFormError('');
    setFormOpen(true);
  };

  const openEdit = (item) => {
    setFormMode('edit');
    setEditing(item);
    setFormValues({
      code: item.code ?? '',
      serviceGrille: item.serviceGrille ?? '',
      sousCategorie: item.sousCategorie ?? '',
      libelle: item.libelle ?? '',
      tarifA0: item.tarifA0 ?? '0',
      tarifA1: item.tarifA1 ?? '0',
      tarifA: item.tarifA ?? '0',
      tarifB: item.tarifB ?? '0',
      tarifC: item.tarifC ?? '0',
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
        await createActeFinancierApi(payload);
        showSuccess('Acte créé avec succès.');
        await refreshMeta();
        setPage(1);
        await load(1);
      } else {
        await updateActeFinancierApi(editing.id, payload);
        showSuccess('Acte mis à jour.');
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
      await deleteActeFinancierApi(pendingDelete.id);
      showSuccess('Acte supprimé.');
      setPendingDelete(null);
      await load(page);
    } catch (error) {
      showError(error.message || 'Suppression impossible.');
    } finally {
      setConfirmLoading(false);
    }
  };

  const colSpan = showActions ? 8 : 7;

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={2.5}>
        <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={1.5}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Wallet size={24} color={LOTRU_PRIMARY[600]} />
            <Box>
              <Typography level="h2" sx={{ fontWeight: 700 }}>Grille tarifaire</Typography>
              <Typography level="body-md" sx={{ color: 'neutral.500' }}>
                Catalogue des actes (A0, A1, A, B, C). Import Excel ou saisie manuelle.
              </Typography>
            </Box>
          </Stack>
          <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
            {canExport ? (
              <ExportButtons loading={exportLoading} onExport={handleExport} />
            ) : null}
            {canImport ? (
              <>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept=".xlsx,.xls"
                  hidden
                  onChange={handleImport}
                />
                <Button
                  startDecorator={<Upload size={16} />}
                  loading={importLoading}
                  onClick={() => fileInputRef.current?.click()}
                >
                  Importer Excel
                </Button>
              </>
            ) : null}
            {canCreate ? (
              <Button startDecorator={<Plus size={16} />} onClick={openCreate}>Nouvel acte</Button>
            ) : null}
          </Stack>
        </Stack>

        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
            <Input
              startDecorator={<Search size={16} />}
              placeholder="Rechercher un acte…"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              sx={{ flex: 1 }}
            />
            <Select
              value={serviceFilter}
              onChange={(_, value) => setServiceFilter(value ?? '')}
              placeholder="Service"
              sx={{ minWidth: 220 }}
            >
              <Option value="">Tous les services</Option>
              {serviceGrilles.map((service) => (
                <Option key={service} value={service}>{service}</Option>
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
          <Table stickyHeader hoverRow sx={{ minWidth: 1180 }}>
            <thead>
              <tr>
                <th>Service</th>
                <th>Acte</th>
                <th style={{ textAlign: 'right' }}>A0</th>
                <th style={{ textAlign: 'right' }}>A1</th>
                <th style={{ textAlign: 'right' }}>A</th>
                <th style={{ textAlign: 'right' }}>B</th>
                <th style={{ textAlign: 'right' }}>C</th>
                {showActions ? <th style={{ textAlign: 'right' }}>Actions</th> : null}
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={colSpan}><Typography level="body-sm" sx={{ p: 2 }}>Chargement…</Typography></td></tr>
              ) : items.length === 0 ? (
                <tr>
                  <td colSpan={colSpan}>
                    <Typography level="body-sm" sx={{ p: 2, color: LOTRU_NEUTRAL[600] }}>
                      Aucun acte. Créez-en un ou importez la grille consolidée CHHU.
                    </Typography>
                  </td>
                </tr>
              ) : items.map((item) => (
                <tr key={item.id}>
                  <td>
                    <Typography level="body-sm" sx={{ fontWeight: 600 }}>{item.serviceGrille || '—'}</Typography>
                    {item.sousCategorie ? (
                      <Typography level="body-xs" sx={{ color: 'neutral.500' }}>{item.sousCategorie}</Typography>
                    ) : null}
                  </td>
                  <td>
                    <Typography level="body-sm">{item.libelle}</Typography>
                    {item.statut === 'INACTIF' ? (
                      <Chip size="sm" variant="soft" color="neutral" sx={{ mt: 0.5 }}>Inactif</Chip>
                    ) : null}
                  </td>
                  <td style={{ textAlign: 'right' }}>{formatCdf(item.tarifA0)}</td>
                  <td style={{ textAlign: 'right' }}>{formatCdf(item.tarifA1)}</td>
                  <td style={{ textAlign: 'right' }}>{formatCdf(item.tarifA)}</td>
                  <td style={{ textAlign: 'right' }}>{formatCdf(item.tarifB)}</td>
                  <td style={{ textAlign: 'right' }}>{formatCdf(item.tarifC)}</td>
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
          limitOptions={ACTE_PAGE_SIZE_OPTIONS}
          onPageChange={setPage}
          onLimitChange={setLimit}
        />
      </Stack>

      <ActeFormModal
        open={formOpen}
        mode={formMode}
        initialValues={formValues}
        serviceGrilles={serviceGrilles}
        loading={formLoading}
        error={formError}
        onClose={() => setFormOpen(false)}
        onSubmit={handleSubmit}
      />
      <ConfirmModal
        open={Boolean(pendingDelete)}
        title="Supprimer l'acte"
        message={pendingDelete ? `Supprimer l'acte « ${pendingDelete.libelle} » ?` : ''}
        confirmLabel="Supprimer"
        loading={confirmLoading}
        onClose={() => setPendingDelete(null)}
        onConfirm={handleDelete}
      />
    </Box>
  );
}
