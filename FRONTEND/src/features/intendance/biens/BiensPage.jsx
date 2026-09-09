import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Box, Button, Card, Checkbox, Chip, FormControl, FormLabel, IconButton, Input, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { Archive, ArrowLeftRight, Ban, History, Pencil, Plus, QrCode, Search, Trash2 } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import ConfirmModal from '../../../components/ui/ConfirmModal.jsx';
import ExportButtons from '../../../components/export/ExportButtons.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { fetchFamillesActivesApi } from '../familles/famillesApi.js';
import { fetchLocauxActifsApi } from '../locaux/locauxApi.js';
import { fetchTypesActifsApi } from '../types/typesApi.js';
import {
  BIEN_ETAT_COLORS,
  BIEN_ETAT_LABELS,
  BIEN_ETATS,
  BIEN_PAGE_SIZE_OPTIONS,
  DEFAULT_BIEN_PAGE_SIZE,
  EMPTY_BIEN_FORM,
} from './bienConstants.js';
import {
  createBienApi,
  createGroupeBiensApi,
  deleteBienApi,
  exportBiensApi,
  fetchBiensApi,
  fetchEffectifsApi,
  fetchHistoriqueBienApi,
  fetchServicesLookupApi,
  openEtiquettesApi,
  reformerBienApi,
  transfererBienApi,
  updateBienApi,
} from './biensApi.js';
import BienFormModal from './components/BienFormModal.jsx';
import HistoriqueBienModal from './components/HistoriqueBienModal.jsx';
import ReformBienModal from './components/ReformBienModal.jsx';
import TransferBienModal from './components/TransferBienModal.jsx';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_BIEN_PAGE_SIZE, total: 0, totalPages: 0 };

export default function IntendanceBiensPage() {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.INTENDANCE.BIEN_CREATE);
  const canUpdate = hasPermission(PERMISSIONS.INTENDANCE.BIEN_UPDATE);
  const canDelete = hasPermission(PERMISSIONS.INTENDANCE.BIEN_DELETE);
  const canExport = hasPermission(PERMISSIONS.INTENDANCE.BIEN_EXPORT);
  const canEtiquette = hasPermission(PERMISSIONS.INTENDANCE.ETIQUETTE_GENERATE);
  const canSynthese = hasPermission(PERMISSIONS.INTENDANCE.SYNTHESE_READ);

  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [serviceFilter, setServiceFilter] = useState('');
  const [localFilter, setLocalFilter] = useState('');
  const [familleFilter, setFamilleFilter] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [etatFilter, setEtatFilter] = useState('');
  const [includeReformes, setIncludeReformes] = useState(false);
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_BIEN_PAGE_SIZE);

  const [services, setServices] = useState([]);
  const [familles, setFamilles] = useState([]);
  const [types, setTypes] = useState([]);
  const [locauxFilter, setLocauxFilter] = useState([]);
  const [effectifs, setEffectifs] = useState(null);

  const [formOpen, setFormOpen] = useState(false);
  const [formMode, setFormMode] = useState('create');
  const [formValues, setFormValues] = useState(EMPTY_BIEN_FORM);
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');
  const [editing, setEditing] = useState(null);

  const [pendingDelete, setPendingDelete] = useState(null);
  const [confirmLoading, setConfirmLoading] = useState(false);
  const [transferTarget, setTransferTarget] = useState(null);
  const [transferLoading, setTransferLoading] = useState(false);
  const [transferError, setTransferError] = useState('');
  const [reformTarget, setReformTarget] = useState(null);
  const [reformLoading, setReformLoading] = useState(false);
  const [reformError, setReformError] = useState('');
  const [historiqueTarget, setHistoriqueTarget] = useState(null);
  const [historiqueItems, setHistoriqueItems] = useState([]);
  const [historiqueLoading, setHistoriqueLoading] = useState(false);
  const [exportLoading, setExportLoading] = useState(null);
  const [selectedIds, setSelectedIds] = useState([]);
  const [etiquetteLoading, setEtiquetteLoading] = useState(false);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    setPage(1);
    setSelectedIds([]);
  }, [debouncedSearch, limit, serviceFilter, localFilter, familleFilter, typeFilter, etatFilter, includeReformes]);

  useEffect(() => {
    let cancelled = false;
    Promise.all([
      fetchServicesLookupApi(),
      fetchFamillesActivesApi(),
      fetchTypesActifsApi(),
    ]).then(([serviceItems, familleItems, typeItems]) => {
      if (cancelled) return;
      setServices(serviceItems);
      setFamilles(familleItems);
      setTypes(typeItems);
    }).catch(() => {
      if (cancelled) return;
      setServices([]);
      setFamilles([]);
      setTypes([]);
    });
    return () => { cancelled = true; };
  }, []);

  useEffect(() => {
    if (!serviceFilter) {
      setLocauxFilter([]);
      setLocalFilter('');
      return;
    }
    fetchLocauxActifsApi(serviceFilter).then(setLocauxFilter).catch(() => setLocauxFilter([]));
    setLocalFilter('');
  }, [serviceFilter]);

  const listParams = useMemo(() => ({
    page,
    limit,
    search: debouncedSearch || undefined,
    serviceId: serviceFilter || undefined,
    localId: localFilter || undefined,
    familleId: familleFilter || undefined,
    typeId: typeFilter || undefined,
    etat: etatFilter || undefined,
    includeReformes: includeReformes || etatFilter === 'R' || undefined,
  }), [page, limit, debouncedSearch, serviceFilter, localFilter, familleFilter, typeFilter, etatFilter, includeReformes]);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchBiensApi({ ...listParams, page: targetPage });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (error) {
      setListError(error.message || 'Impossible de charger le parc.');
    } finally {
      setLoading(false);
    }
  }, [listParams, page]);

  useEffect(() => { load(page); }, [load, page]);

  useEffect(() => {
    if (!canSynthese) {
      setEffectifs(null);
      return;
    }
    const { page: _page, limit: _limit, ...filters } = listParams;
    fetchEffectifsApi(filters).then(setEffectifs).catch(() => setEffectifs(null));
  }, [canSynthese, listParams]);

  const filteredTypes = useMemo(
    () => (familleFilter ? types.filter((item) => String(item.famille?.id) === String(familleFilter)) : types),
    [types, familleFilter],
  );

  const openCreate = () => {
    setFormMode('create');
    setEditing(null);
    setFormValues({ ...EMPTY_BIEN_FORM, serviceId: serviceFilter || '', typeId: typeFilter || '' });
    setFormError('');
    setFormOpen(true);
  };

  const openEdit = (item) => {
    setFormMode('edit');
    setEditing(item);
    setFormValues({
      ...EMPTY_BIEN_FORM,
      copies: 1,
      typeId: item.type?.id ? String(item.type.id) : '',
      serviceId: item.service?.id ? String(item.service.id) : '',
      localId: item.local?.id ? String(item.local.id) : '',
      codeInventaire: item.codeInventaire ?? '',
      precision: item.precision ?? '',
      marque: item.marque ?? '',
      modele: item.modele ?? '',
      numeroSerie: item.numeroSerie ?? '',
      complementLocalisation: item.complementLocalisation ?? '',
      etat: item.etat ?? 'F',
      dateAcquisition: item.dateAcquisition ?? '',
      observation: item.observation ?? '',
    });
    setFormError('');
    setFormOpen(true);
  };

  const handleSubmit = async (payload) => {
    setFormLoading(true);
    setFormError('');
    try {
      if (formMode === 'create' && Number(payload.copies) > 1) {
        const { copies, codes, ...rest } = payload;
        await createGroupeBiensApi({ copies, codes, ...rest });
        showSuccess(`${copies} fiches créées.`);
        setPage(1);
        await load(1);
      } else if (formMode === 'create') {
        await createBienApi(payload);
        showSuccess('Bien enregistré.');
        setPage(1);
        await load(1);
      } else {
        await updateBienApi(editing.id, payload);
        showSuccess('Bien mis à jour.');
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
      await deleteBienApi(pendingDelete.id);
      showSuccess('Bien supprimé.');
      setPendingDelete(null);
      await load(page);
    } catch (error) {
      showError(error.message || 'Suppression impossible.');
    } finally {
      setConfirmLoading(false);
    }
  };

  const handleTransfer = async (payload) => {
    if (!transferTarget) return;
    setTransferLoading(true);
    setTransferError('');
    try {
      await transfererBienApi(transferTarget.id, payload);
      showSuccess('Bien transféré. Le code inventaire est inchangé.');
      setTransferTarget(null);
      await load(page);
    } catch (error) {
      setTransferError(error.message || 'Transfert impossible.');
    } finally {
      setTransferLoading(false);
    }
  };

  const handleReform = async (payload) => {
    if (!reformTarget) return;
    setReformLoading(true);
    setReformError('');
    try {
      await reformerBienApi(reformTarget.id, payload);
      showSuccess('Bien réformé.');
      setReformTarget(null);
      await load(page);
    } catch (error) {
      setReformError(error.message || 'Réforme impossible.');
    } finally {
      setReformLoading(false);
    }
  };

  const openHistorique = async (item) => {
    setHistoriqueTarget(item);
    setHistoriqueLoading(true);
    try {
      setHistoriqueItems(await fetchHistoriqueBienApi(item.id));
    } catch (error) {
      showError(error.message || 'Historique indisponible.');
      setHistoriqueItems([]);
    } finally {
      setHistoriqueLoading(false);
    }
  };

  const pageIds = items.map((item) => item.id);
  const allPageSelected = pageIds.length > 0 && pageIds.every((id) => selectedIds.includes(id));

  const toggleOne = (id) => {
    setSelectedIds((current) => (current.includes(id) ? current.filter((item) => item !== id) : [...current, id]));
  };

  const togglePage = () => {
    setSelectedIds((current) => {
      if (allPageSelected) {
        return current.filter((id) => !pageIds.includes(id));
      }
      return [...new Set([...current, ...pageIds])];
    });
  };

  const handleExport = async (format) => {
    setExportLoading(format);
    try {
      const { page: _page, limit: _limit, ...filters } = listParams;
      await exportBiensApi(format, filters);
      showSuccess(format === 'pdf' ? 'Export PDF ouvert dans le navigateur.' : 'Export Excel téléchargé.');
    } catch (error) {
      showError(error.message || 'Export impossible.');
    } finally {
      setExportLoading(null);
    }
  };

  const handleEtiquettes = async () => {
    if (!selectedIds.length) {
      showError('Sélectionnez au moins un bien.');
      return;
    }
    setEtiquetteLoading(true);
    try {
      await openEtiquettesApi(selectedIds);
      showSuccess('Étiquettes ouvertes. Vous pouvez les imprimer.');
    } catch (error) {
      showError(error.message || 'Génération des étiquettes impossible.');
    } finally {
      setEtiquetteLoading(false);
    }
  };

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={2.5}>
        <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={1.5}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Archive size={24} color={LOTRU_PRIMARY[600]} />
            <Box>
              <Typography level="h2" sx={{ fontWeight: 700 }}>Parc</Typography>
              <Typography level="body-md" sx={{ color: 'neutral.500' }}>
                Une fiche = un objet physique. Le code inventaire est unique et ne change pas au transfert.
              </Typography>
            </Box>
          </Stack>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
            {canExport ? <ExportButtons onExport={handleExport} loading={exportLoading} /> : null}
            {canEtiquette ? (
              <Button
                variant="outlined"
                startDecorator={<QrCode size={16} />}
                loading={etiquetteLoading}
                disabled={!selectedIds.length}
                onClick={handleEtiquettes}
              >
                Générer les étiquettes
              </Button>
            ) : null}
            {canCreate ? <Button startDecorator={<Plus size={16} />} onClick={openCreate}>Nouveau bien</Button> : null}
          </Stack>
        </Stack>

        {canSynthese && effectifs ? (
          <Card variant="soft" color="primary" sx={{ borderRadius: 'lg', p: 2 }}>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} alignItems={{ md: 'center' }} justifyContent="space-between">
              <Typography level="title-sm">
                Effectifs : {effectifs.parcActif} au parc actif / {effectifs.total} fiche{effectifs.total > 1 ? 's' : ''}
                {effectifs.sansLocal ? ` · ${effectifs.sansLocal} sans local` : ''}
              </Typography>
              <Stack direction="row" spacing={0.75} flexWrap="wrap" useFlexGap>
                {(effectifs.parEtat ?? []).map((row) => (
                  <Chip key={row.etat} size="sm" variant="outlined" color={BIEN_ETAT_COLORS[row.etat] ?? 'neutral'}>
                    {row.etat} {row.total}
                  </Chip>
                ))}
              </Stack>
            </Stack>
          </Card>
        ) : null}

        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
          <Stack spacing={1.5}>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
              <Input startDecorator={<Search size={16} />} placeholder="Code, marque, série…" value={search} onChange={(e) => setSearch(e.target.value)} sx={{ flex: 1 }} />
              <Select placeholder="Service" value={serviceFilter === '' ? null : serviceFilter} onChange={(_, value) => setServiceFilter(value ?? '')} sx={{ minWidth: 200 }}>
                <Option value="">Tous les services</Option>
                {services.map((item) => <Option key={item.id} value={String(item.id)}>{item.code} — {item.libelle}</Option>)}
              </Select>
              <Select placeholder="Local" value={localFilter === '' ? null : localFilter} onChange={(_, value) => setLocalFilter(value ?? '')} sx={{ minWidth: 180 }} disabled={!serviceFilter}>
                <Option value="">Tous les locaux</Option>
                {locauxFilter.map((item) => <Option key={item.id} value={String(item.id)}>{item.libelle}</Option>)}
              </Select>
            </Stack>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} alignItems={{ md: 'center' }}>
              <Select placeholder="Famille" value={familleFilter === '' ? null : familleFilter} onChange={(_, value) => { setFamilleFilter(value ?? ''); setTypeFilter(''); }} sx={{ minWidth: 180 }}>
                <Option value="">Toutes les familles</Option>
                {familles.map((item) => <Option key={item.id} value={String(item.id)}>{item.code} — {item.libelle}</Option>)}
              </Select>
              <Select placeholder="Type" value={typeFilter === '' ? null : typeFilter} onChange={(_, value) => setTypeFilter(value ?? '')} sx={{ minWidth: 200 }}>
                <Option value="">Tous les types</Option>
                {filteredTypes.map((item) => <Option key={item.id} value={String(item.id)}>{item.libelle}</Option>)}
              </Select>
              <Select placeholder="État" value={etatFilter === '' ? null : etatFilter} onChange={(_, value) => setEtatFilter(value ?? '')} sx={{ minWidth: 200 }}>
                <Option value="">Tous les états</Option>
                {BIEN_ETATS.map((item) => <Option key={item.value} value={item.value}>{item.label}</Option>)}
              </Select>
              <FormControl orientation="horizontal" sx={{ gap: 1 }}>
                <Checkbox checked={includeReformes} onChange={(e) => setIncludeReformes(e.target.checked)} />
                <FormLabel>Inclure les réformés</FormLabel>
              </FormControl>
            </Stack>
          </Stack>
        </Card>

        {listError ? <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>{listError}</Typography> : null}

        {canEtiquette && selectedIds.length > 0 ? (
          <Card variant="soft" sx={{ borderRadius: 'lg', p: 1.5 }}>
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} alignItems={{ sm: 'center' }} justifyContent="space-between">
              <Typography level="body-sm">{selectedIds.length} bien{selectedIds.length > 1 ? 's' : ''} sélectionné{selectedIds.length > 1 ? 's' : ''}</Typography>
              <Stack direction="row" spacing={1}>
                <Button variant="plain" color="neutral" onClick={() => setSelectedIds([])}>Vider</Button>
                <Button startDecorator={<QrCode size={16} />} loading={etiquetteLoading} onClick={handleEtiquettes}>
                  Générer les étiquettes
                </Button>
              </Stack>
            </Stack>
          </Card>
        ) : null}

        <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto' }}>
          <Table stickyHeader hoverRow sx={{ minWidth: 1100 }}>
            <thead>
              <tr>
                {canEtiquette ? (
                  <th style={{ width: 44 }}>
                    <Checkbox
                      checked={allPageSelected}
                      indeterminate={selectedIds.some((id) => pageIds.includes(id)) && !allPageSelected}
                      onChange={togglePage}
                      disabled={loading || items.length === 0}
                    />
                  </th>
                ) : null}
                <th>Code</th>
                <th>Type</th>
                <th>Service</th>
                <th>Local</th>
                <th>État</th>
                <th>Marque / modèle</th>
                <th style={{ textAlign: 'right' }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={canEtiquette ? 8 : 7}><Typography level="body-sm" sx={{ p: 2 }}>Chargement…</Typography></td></tr>
              ) : items.length === 0 ? (
                <tr><td colSpan={canEtiquette ? 8 : 7}><Typography level="body-sm" sx={{ p: 2, color: LOTRU_NEUTRAL[600] }}>Aucun bien.</Typography></td></tr>
              ) : items.map((item) => {
                const isReforme = item.etat === 'R';
                return (
                  <tr key={item.id}>
                    {canEtiquette ? (
                      <td>
                        <Checkbox checked={selectedIds.includes(item.id)} onChange={() => toggleOne(item.id)} />
                      </td>
                    ) : null}
                    <td>
                      <Typography level="body-sm" sx={{ fontFamily: 'monospace', fontWeight: 700 }}>{item.codeInventaire}</Typography>
                      {item.precision ? <Typography level="body-xs" sx={{ color: 'neutral.500' }}>{item.precision}</Typography> : null}
                    </td>
                    <td>
                      {item.type?.libelle ?? '—'}
                      {item.famille ? <Typography level="body-xs" sx={{ color: 'neutral.500' }}>{item.famille.code}</Typography> : null}
                    </td>
                    <td>{item.service ? `${item.service.code} — ${item.service.libelle}` : '—'}</td>
                    <td>{item.local?.libelle ?? '—'}</td>
                    <td>
                      <Chip size="sm" variant="soft" color={BIEN_ETAT_COLORS[item.etat] ?? 'neutral'}>
                        {item.etat} · {BIEN_ETAT_LABELS[item.etat] ?? item.etat}
                      </Chip>
                    </td>
                    <td>{[item.marque, item.modele].filter(Boolean).join(' ') || '—'}</td>
                    <td style={{ textAlign: 'right' }}>
                      <Stack direction="row" spacing={0.25} justifyContent="flex-end">
                        <IconButton size="sm" variant="plain" title="Historique" onClick={() => openHistorique(item)}>
                          <History size={16} />
                        </IconButton>
                        {canUpdate && !isReforme ? (
                          <IconButton size="sm" variant="plain" title="Modifier" onClick={() => openEdit(item)}>
                            <Pencil size={16} />
                          </IconButton>
                        ) : null}
                        {canUpdate && !isReforme ? (
                          <IconButton size="sm" variant="plain" title="Transférer" onClick={() => { setTransferError(''); setTransferTarget(item); }}>
                            <ArrowLeftRight size={16} />
                          </IconButton>
                        ) : null}
                        {canUpdate && !isReforme ? (
                          <IconButton size="sm" variant="plain" color="warning" title="Réformer" onClick={() => { setReformError(''); setReformTarget(item); }}>
                            <Ban size={16} />
                          </IconButton>
                        ) : null}
                        {canDelete ? (
                          <IconButton size="sm" variant="plain" color="danger" title="Supprimer" onClick={() => setPendingDelete(item)}>
                            <Trash2 size={16} />
                          </IconButton>
                        ) : null}
                      </Stack>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </Table>
        </Sheet>
        <AppPagination page={pagination.page} totalPages={pagination.totalPages} total={pagination.total} limit={limit} limitOptions={BIEN_PAGE_SIZE_OPTIONS} onPageChange={setPage} onLimitChange={setLimit} />
      </Stack>

      <BienFormModal
        open={formOpen}
        mode={formMode}
        initialValues={formValues}
        types={types}
        services={services}
        loading={formLoading}
        error={formError}
        onClose={() => setFormOpen(false)}
        onSubmit={handleSubmit}
      />
      <TransferBienModal
        open={Boolean(transferTarget)}
        bien={transferTarget}
        services={services}
        loading={transferLoading}
        error={transferError}
        onClose={() => setTransferTarget(null)}
        onSubmit={handleTransfer}
      />
      <ReformBienModal
        open={Boolean(reformTarget)}
        bien={reformTarget}
        loading={reformLoading}
        error={reformError}
        onClose={() => setReformTarget(null)}
        onSubmit={handleReform}
      />
      <HistoriqueBienModal
        open={Boolean(historiqueTarget)}
        bien={historiqueTarget}
        items={historiqueItems}
        loading={historiqueLoading}
        onClose={() => setHistoriqueTarget(null)}
      />
      <ConfirmModal
        open={Boolean(pendingDelete)}
        title="Supprimer la fiche"
        message={pendingDelete ? `Supprimer « ${pendingDelete.codeInventaire} » ? Uniquement si le bien n’a jamais été transféré.` : ''}
        confirmLabel="Supprimer"
        loading={confirmLoading}
        onClose={() => setPendingDelete(null)}
        onConfirm={handleDelete}
      />
    </Box>
  );
}
