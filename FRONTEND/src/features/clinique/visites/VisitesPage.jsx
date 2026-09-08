import { useCallback, useEffect, useState } from 'react';
import { Link as RouterLink, useNavigate } from 'react-router-dom';
import {
  Box, Button, Card, Chip, IconButton, Input, Link, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { CalendarClock, Pencil, Plus, Search, Stethoscope, Trash2 } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import ExportButtons from '../../../components/export/ExportButtons.jsx';
import { clinique } from '../../../api/endpoints.js';
import { ROUTES } from '../../../constants/routes.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import OfflineHint from '../../../offline/OfflineHint.jsx';
import PendingSyncChip from '../../../offline/PendingSyncChip.jsx';
import { exportResourceApi } from '../../../utils/exportApi.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import VisiteCreateWizardModal from './components/VisiteCreateWizardModal.jsx';
import VisiteDeleteModal from './components/VisiteDeleteModal.jsx';
import VisiteFormModal from './components/VisiteFormModal.jsx';
import VisiteTransitionPanel from './components/VisiteTransitionPanel.jsx';
import {
  DEFAULT_VISITE_PAGE_SIZE,
  EMPTY_VISITE_FORM,
  buildEmptyVisiteCreateForm,
  VISITE_ACTIVE_STATUTS,
  VISITE_PAGE_SIZE_OPTIONS,
  VISITE_STATUT_COLORS,
  VISITE_STATUT_LABELS,
} from './visiteConstants.js';
import {
  createVisiteApi,
  deleteVisiteApi,
  fetchVisiteCreateMetaApi,
  fetchVisiteMetaApi,
  fetchVisitesApi,
  updateVisiteApi,
} from './visitesApi.js';
import { VISITE_CONSULTATION_STATUTS, visiteHasActiveConsultation } from '../consultations/consultationConstants.js';
import { openConsultationForVisite } from '../consultations/openConsultationForVisite.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_VISITE_PAGE_SIZE, total: 0, totalPages: 0 };

function StatusChip({ statut }) {
  return (
    <Chip size="sm" variant="soft" color={VISITE_STATUT_COLORS[statut] ?? 'neutral'}>
      {VISITE_STATUT_LABELS[statut] ?? statut}
    </Chip>
  );
}

function formatDateTime(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('fr-FR');
}

function toLocalInputValue(isoValue) {
  if (!isoValue) return '';
  const date = new Date(isoValue);
  if (Number.isNaN(date.getTime())) return '';
  const offset = date.getTimezoneOffset();
  const local = new Date(date.getTime() - offset * 60000);
  return local.toISOString().slice(0, 16);
}

export default function VisitesPage() {
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.CLINIQUE.VISITE_CREATE)
    && hasPermission(PERMISSIONS.REFERENTIEL.SIGNE_VITAL_READ);
  const canUpdate = hasPermission(PERMISSIONS.CLINIQUE.VISITE_UPDATE);
  const canDelete = hasPermission(PERMISSIONS.CLINIQUE.VISITE_DELETE);
  const canExport = hasPermission(PERMISSIONS.CLINIQUE.VISITE_EXPORT);
  const canReadConsultation = hasPermission(PERMISSIONS.CLINIQUE.CONSULTATION_READ);
  const canCreateConsultation = hasPermission(PERMISSIONS.CLINIQUE.CONSULTATION_CREATE);
  const canUpdateConsultation = hasPermission(PERMISSIONS.CLINIQUE.CONSULTATION_UPDATE);
  const canConsult = canReadConsultation && (canCreateConsultation || canUpdateConsultation);
  const showActions = canConsult || canUpdate || canDelete;

  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState({ services: [], lits: [] });
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [statutFilter, setStatutFilter] = useState('');
  const [serviceFilter, setServiceFilter] = useState('');
  const [pendingHospitalization, setPendingHospitalization] = useState(false);
  const [consultationLoadingId, setConsultationLoadingId] = useState(null);
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_VISITE_PAGE_SIZE);
  const [exportLoading, setExportLoading] = useState(null);
  const [transitionLoadingId, setTransitionLoadingId] = useState(null);

  const [formOpen, setFormOpen] = useState(false);
  const [createWizardOpen, setCreateWizardOpen] = useState(false);
  const [createMeta, setCreateMeta] = useState({ departements: [], services: [], signesVitaux: [] });
  const [formValues, setFormValues] = useState(EMPTY_VISITE_FORM);
  const [createFormValues, setCreateFormValues] = useState(buildEmptyVisiteCreateForm());
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');
  const [editing, setEditing] = useState(null);

  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleting, setDeleting] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [deleteError, setDeleteError] = useState('');

  useEffect(() => {
    fetchVisiteMetaApi().then(setMeta).catch(() => setMeta({ services: [], lits: [] }));
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, statutFilter, serviceFilter, pendingHospitalization, limit]);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchVisitesApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        statut: statutFilter || undefined,
        serviceId: serviceFilter || undefined,
        pendingHospitalization: pendingHospitalization ? 'true' : undefined,
      });
      setItems(result.items);
      setPagination(result.pagination);
      if (result.pagination.totalPages > 0 && targetPage > result.pagination.totalPages) {
        setPage(result.pagination.totalPages);
      }
    } catch (error) {
      setListError(error.message || 'Impossible de charger les visites.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, statutFilter, serviceFilter, pendingHospitalization]);

  useEffect(() => { load(page); }, [load, page]);

  const openCreate = async () => {
    setCreateFormValues(buildEmptyVisiteCreateForm());
    setFormError('');
    try {
      const meta = await fetchVisiteCreateMetaApi();
      setCreateMeta(meta);
      setCreateWizardOpen(true);
    } catch (error) {
      showError(error.message || 'Impossible de charger les données de création.');
    }
  };

  const openEdit = (item) => {
    setEditing(item);
    setFormValues({
      dpiId: item.dpiId ?? '',
      serviceId: item.serviceId ?? '',
      statut: item.statut ?? 'EN_COURS',
      litId: item.litId ?? '',
      sortedPrevuAt: toLocalInputValue(item.sortedPrevuAt),
    });
    setFormError('');
    setFormOpen(true);
  };

  const handleSubmit = async (payload) => {
    setFormLoading(true);
    setFormError('');
    try {
      await updateVisiteApi(editing.id, {
        serviceId: payload.serviceId,
        sortedPrevuAt: payload.sortedPrevuAt,
      });
      showSuccess('Visite mise à jour avec succès.');
      setFormOpen(false);
      await load(page);
    } catch (error) {
      setFormError(error.message || 'Enregistrement impossible.');
    } finally {
      setFormLoading(false);
    }
  };

  const handleCreateSubmit = async (payload) => {
    setFormLoading(true);
    setFormError('');
    try {
      await createVisiteApi(payload);
      showSuccess('Visite créée avec succès.');
      setCreateWizardOpen(false);
      setPage(1);
      await load(1);
    } catch (error) {
      setFormError(error.message || 'Création impossible.');
    } finally {
      setFormLoading(false);
    }
  };

  const handleTransition = async (item, payload) => {
    setTransitionLoadingId(item.id);
    try {
      await updateVisiteApi(item.id, payload);
      showSuccess('Statut de visite mis à jour.');
      await load(page);
    } catch (error) {
      showError(error.message || 'Transition impossible.');
    } finally {
      setTransitionLoadingId(null);
    }
  };

  const handleDelete = async () => {
    if (!deleting) return;
    setDeleteLoading(true);
    setDeleteError('');
    try {
      await deleteVisiteApi(deleting.id);
      setDeleteOpen(false);
      showSuccess('Visite supprimée avec succès.');
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
      await exportResourceApi({
        endpoint: `${clinique.visites}/export`,
        format,
        params: {
          search: debouncedSearch || undefined,
          statut: statutFilter || undefined,
          serviceId: serviceFilter || undefined,
          pendingHospitalization: pendingHospitalization ? 'true' : undefined,
        },
        filenamePrefix: 'visites',
      });
    } catch (error) {
      showError(error.message || 'Export impossible.');
    } finally {
      setExportLoading(null);
    }
  };

  const canDeleteItem = (item) => !VISITE_ACTIVE_STATUTS.includes(item.statut)
    && (item.consultationCount ?? 0) === 0
    && (item.acteFinancierCount ?? 0) === 0;

  const handleOpenConsultation = async (visite) => {
    setConsultationLoadingId(visite.id);
    try {
      const result = await openConsultationForVisite({
        visite,
        canCreate: canCreateConsultation && visite.recordWritable !== false,
        navigate,
      });
      if (result.created) {
        showSuccess('Consultation créée.');
      }
    } catch (error) {
      showError(error.message || 'Impossible d\'ouvrir la consultation.');
    } finally {
      setConsultationLoadingId(null);
    }
  };

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={3}>
        <OfflineHint>
          Nouvelle visite hors-ligne : le DPI doit déjà être en cache. L’admission sera créée au retour du serveur.
        </OfflineHint>
        <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ xs: 'stretch', sm: 'center' }} spacing={2}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ width: 44, height: 44, borderRadius: 'md', bgcolor: LOTRU_PRIMARY[50], color: LOTRU_PRIMARY[600], display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <CalendarClock size={22} />
            </Box>
            <Box>
              <Typography level="h3" sx={{ fontWeight: 700, color: LOTRU_NEUTRAL[900] }}>Visites</Typography>
              <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600] }}>
                Parcours du patient : admission, lit et hospitalisation
              </Typography>
            </Box>
          </Stack>
          <Stack direction="row" spacing={1}>
            {canExport ? <ExportButtons loading={exportLoading} onExport={handleExport} /> : null}
            {canCreate ? <Button startDecorator={<Plus size={18} />} onClick={openCreate}>Nouvelle visite</Button> : null}
          </Stack>
        </Stack>

        <Card variant="outlined" sx={{ borderRadius: 'lg' }}>
          <Stack spacing={2} sx={{ p: 2 }}>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
              <Input startDecorator={<Search size={18} />} placeholder="Patient, n° dossier, service…" value={search} onChange={(e) => setSearch(e.target.value)} sx={{ flex: 1 }} />
              <Select value={statutFilter} onChange={(_, value) => setStatutFilter(value ?? '')} placeholder="Statut" sx={{ minWidth: 160 }}>
                <Option value="">Tous les statuts</Option>
                {Object.entries(VISITE_STATUT_LABELS).map(([value, label]) => (
                  <Option key={value} value={value}>{label}</Option>
                ))}
              </Select>
              <Select value={serviceFilter} onChange={(_, value) => setServiceFilter(value ?? '')} placeholder="Service" sx={{ minWidth: 180 }}>
                <Option value="">Tous les services</Option>
                {meta.services.map((service) => (
                  <Option key={service.id} value={service.id}>{service.libelle}</Option>
                ))}
              </Select>
              <Chip
                variant={pendingHospitalization ? 'solid' : 'outlined'}
                color="warning"
                onClick={() => setPendingHospitalization((current) => !current)}
                sx={{ cursor: 'pointer', alignSelf: { xs: 'stretch', md: 'center' } }}
              >
                À hospitaliser
              </Chip>
            </Stack>

            {listError ? <Typography level="body-sm" color="danger">{listError}</Typography> : null}

            <Sheet variant="outlined" sx={{ borderRadius: 'md', overflow: 'auto' }}>
              <Table stickyHeader hoverRow sx={{ minWidth: 1100 }}>
                <thead>
                  <tr>
                    <th>Patient</th>
                    <th>Service</th>
                    <th>Statut</th>
                    <th>Entrée</th>
                    <th>Lit</th>
                    <th>Nb consultations</th>
                    {showActions ? <th style={{ minWidth: 320 }}>Actions</th> : null}
                  </tr>
                </thead>
                <tbody>
                  {loading ? (
                    <tr><td colSpan={showActions ? 7 : 6}><Typography level="body-sm" sx={{ py: 3, textAlign: 'center' }}>Chargement…</Typography></td></tr>
                  ) : items.length === 0 ? (
                    <tr><td colSpan={showActions ? 7 : 6}><Typography level="body-sm" sx={{ py: 3, textAlign: 'center', color: 'neutral.500' }}>Aucune visite trouvée.</Typography></td></tr>
                  ) : items.map((item) => (
                    <tr key={item.id}>
                      <td>
                        {item.patientId ? (
                          <Link component={RouterLink} to={ROUTES.PATIENT.DPI.replace(':patientId', item.patientId)} fontWeight="md">
                            {item.patientName}
                          </Link>
                        ) : (item.patientName ?? '—')}
                        <Typography level="body-xs" sx={{ fontFamily: 'monospace', color: 'neutral.500' }}>{item.numDossier}</Typography>
                      </td>
                      <td>{item.service?.libelle ?? '—'}</td>
                      <td>
                        <Stack direction="row" spacing={0.5} alignItems="center" flexWrap="wrap" useFlexGap>
                          <StatusChip statut={item.statut} />
                          <PendingSyncChip show={item.pendingSync} />
                          {item.pendingHospitalization ? (
                            <Chip size="sm" variant="soft" color="warning">À hospitaliser</Chip>
                          ) : null}
                        </Stack>
                      </td>
                      <td>{formatDateTime(item.enterAt)}</td>
                      <td>{item.lit ? `${item.lit.code} (${item.lit.bloc ?? item.lit.chambre ?? ''})` : '—'}</td>
                      <td>{item.consultationCount ?? 0}</td>
                      {showActions ? (
                        <td style={{ whiteSpace: 'nowrap' }}>
                          <Stack direction="row" spacing={0.75} alignItems="center" flexWrap="nowrap" useFlexGap>
                            {canConsult && VISITE_CONSULTATION_STATUTS.includes(item.statut) && !item.pendingHospitalization ? (
                              visiteHasActiveConsultation(item) ? (
                                <Button
                                  size="sm"
                                  variant="soft"
                                  color="success"
                                  startDecorator={<Stethoscope size={14} />}
                                  loading={consultationLoadingId === item.id}
                                  onClick={() => handleOpenConsultation(item)}
                                >
                                  {item.statut === 'HOSPITALISE' ? 'Reprendre le tour' : 'Reprendre'}
                                </Button>
                              ) : (
                                <Button
                                  size="sm"
                                  variant="soft"
                                  color="primary"
                                  startDecorator={<Stethoscope size={14} />}
                                  loading={consultationLoadingId === item.id}
                                  onClick={() => handleOpenConsultation(item)}
                                >
                                  {item.statut === 'HOSPITALISE' ? 'Tour de salle' : 'Consulter'}
                                </Button>
                              )
                            ) : null}
                            <VisiteTransitionPanel
                              visite={item}
                              lits={meta.lits}
                              canUpdate={canUpdate}
                              loading={transitionLoadingId === item.id}
                              onTransition={(payload) => handleTransition(item, payload)}
                            />
                            {canUpdate && VISITE_ACTIVE_STATUTS.includes(item.statut) ? (
                              <IconButton size="sm" variant="plain" color="neutral" title="Modifier le service" onClick={() => openEdit(item)}>
                                <Pencil size={16} />
                              </IconButton>
                            ) : null}
                            {canDelete ? (
                              <IconButton
                                size="sm"
                                variant="plain"
                                color="danger"
                                title={canDeleteItem(item) ? 'Supprimer' : 'Suppression impossible : visite encore active ou liée'}
                                disabled={!canDeleteItem(item)}
                                onClick={() => { setDeleting(item); setDeleteError(''); setDeleteOpen(true); }}
                              >
                                <Trash2 size={16} />
                              </IconButton>
                            ) : null}
                          </Stack>
                        </td>
                      ) : null}
                    </tr>
                  ))}
                </tbody>
              </Table>
            </Sheet>

            <AppPagination page={pagination.page} totalPages={pagination.totalPages} total={pagination.total} limit={limit} limitOptions={VISITE_PAGE_SIZE_OPTIONS} onPageChange={setPage} onLimitChange={setLimit} />
          </Stack>
        </Card>
      </Stack>

      <VisiteCreateWizardModal
        open={createWizardOpen}
        initialValues={createFormValues}
        createMeta={createMeta}
        loading={formLoading}
        error={formError}
        onClose={() => setCreateWizardOpen(false)}
        onSubmit={handleCreateSubmit}
      />
      <VisiteFormModal open={formOpen} mode="edit" initialValues={formValues} services={meta.services} lits={meta.lits} loading={formLoading} error={formError} onClose={() => setFormOpen(false)} onSubmit={handleSubmit} />
      <VisiteDeleteModal open={deleteOpen} visite={deleting} loading={deleteLoading} error={deleteError} onClose={() => setDeleteOpen(false)} onConfirm={handleDelete} />
    </Box>
  );
}
