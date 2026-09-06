import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Box, Button, Card, Chip, IconButton, Input, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { FolderOpen, Pencil, Plus, Search, Trash2, Users } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import ExportButtons from '../../../components/export/ExportButtons.jsx';
import { patient } from '../../../api/endpoints.js';
import { ROUTES } from '../../../constants/routes.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import OfflineHint from '../../../offline/OfflineHint.jsx';
import { exportResourceApi } from '../../../utils/exportApi.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import PatientDeleteModal from './components/PatientDeleteModal.jsx';
import PatientFormModal from './components/PatientFormModal.jsx';
import {
  DEFAULT_PATIENT_PAGE_SIZE,
  DPI_STATUT_COLORS,
  DPI_STATUT_LABELS,
  EMPTY_PATIENT_FORM,
  PATIENT_PAGE_SIZE_OPTIONS,
  PATIENT_SEX_LABELS,
  PATIENT_STATUS_COLORS,
  PATIENT_STATUS_LABELS,
} from './patientConstants.js';
import {
  createPatientApi,
  deletePatientApi,
  fetchPatientApi,
  fetchPatientsApi,
  updatePatientApi,
} from './patientsApi.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_PATIENT_PAGE_SIZE, total: 0, totalPages: 0 };

function StatusChip({ status }) {
  return (
    <Chip size="sm" variant="soft" color={PATIENT_STATUS_COLORS[status] ?? 'neutral'}>
      {PATIENT_STATUS_LABELS[status] ?? status}
    </Chip>
  );
}

function DpiChip({ statut }) {
  return (
    <Chip size="sm" variant="outlined" color={DPI_STATUT_COLORS[statut] ?? 'neutral'}>
      {DPI_STATUT_LABELS[statut] ?? statut}
    </Chip>
  );
}

function isItemLocked(item) {
  return (item.visiteCount ?? 0) > 0 || (item.antecedentCount ?? 0) > 0;
}

function formatDate(value) {
  if (!value) return '—';
  const [year, month, day] = value.split('-');
  if (!year || !month || !day) return value;
  return `${day}/${month}/${year}`;
}

export default function PatientsPage() {
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.PATIENT.PATIENT_CREATE);
  const canUpdate = hasPermission(PERMISSIONS.PATIENT.PATIENT_UPDATE);
  const canDelete = hasPermission(PERMISSIONS.PATIENT.PATIENT_DELETE);
  const canExport = hasPermission(PERMISSIONS.PATIENT.PATIENT_EXPORT);
  const canOpenDpi = hasPermission(PERMISSIONS.PATIENT.DPI_READ);
  const showActions = canUpdate || canDelete || canOpenDpi;

  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [sexeFilter, setSexeFilter] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_PATIENT_PAGE_SIZE);
  const [exportLoading, setExportLoading] = useState(null);

  const [formOpen, setFormOpen] = useState(false);
  const [formMode, setFormMode] = useState('create');
  const [formValues, setFormValues] = useState(EMPTY_PATIENT_FORM);
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');
  const [editing, setEditing] = useState(null);

  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleting, setDeleting] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [deleteError, setDeleteError] = useState('');

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, statusFilter, sexeFilter, limit]);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchPatientsApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        status: statusFilter || undefined,
        sexe: sexeFilter || undefined,
      });
      setItems(result.items);
      setPagination(result.pagination);
      if (result.pagination.totalPages > 0 && targetPage > result.pagination.totalPages) {
        setPage(result.pagination.totalPages);
      }
    } catch (error) {
      setListError(error.message || 'Impossible de charger les patients.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, statusFilter, sexeFilter]);

  useEffect(() => { load(page); }, [load, page]);

  const openCreate = () => {
    setFormMode('create');
    setEditing(null);
    setFormValues(EMPTY_PATIENT_FORM);
    setFormError('');
    setFormOpen(true);
  };

  const openEdit = async (item) => {
    setFormMode('edit');
    setEditing(item);
    setFormError('');
    setFormOpen(true);
    try {
      const detail = await fetchPatientApi(item.id);
      setEditing(detail);
      setFormValues({
        nom: detail.nom ?? '',
        postNom: detail.postNom ?? '',
        prenom: detail.prenom ?? '',
        telephone: detail.telephone ?? '',
        adresse: detail.adresse ?? '',
        lieuNaissance: detail.lieuNaissance ?? '',
        dateNaissance: detail.dateNaissance ?? '',
        sexe: detail.sexe ?? 'M',
        groupeSanguin: detail.groupeSanguin ?? '',
        personneAprevenir: detail.personneAprevenir ?? '',
        contactAPrevenir: detail.contactAPrevenir ?? '',
        status: detail.status ?? 'ACTIF',
      });
    } catch (error) {
      setFormError(error.message || 'Impossible de charger les détails du patient.');
    }
  };

  const handleSubmit = async (payload) => {
    setFormLoading(true);
    setFormError('');
    try {
      if (formMode === 'create') {
        const created = await createPatientApi(payload);
        showSuccess('Patient enregistré. Dossier DPI créé automatiquement.');
        setFormOpen(false);
        setPage(1);
        await load(1);
        if (canOpenDpi && created?.id) {
          navigate(ROUTES.PATIENT.DPI.replace(':patientId', created.id));
        }
      } else {
        await updatePatientApi(editing.id, payload);
        showSuccess('Patient mis à jour avec succès.');
        setFormOpen(false);
        await load(page);
      }
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
      await deletePatientApi(deleting.id);
      setDeleteOpen(false);
      showSuccess('Patient supprimé avec succès.');
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
        endpoint: `${patient.list}/export`,
        format,
        params: {
          search: debouncedSearch || undefined,
          status: statusFilter || undefined,
          sexe: sexeFilter || undefined,
        },
        filenamePrefix: 'patients',
      });
    } catch (error) {
      showError(error.message || 'Export impossible.');
    } finally {
      setExportLoading(null);
    }
  };

  const openDpi = (item) => {
    navigate(ROUTES.PATIENT.DPI.replace(':patientId', item.id));
  };

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={3}>
        <OfflineHint>
          Création de patient hors-ligne : le dossier sera envoyé au serveur à la reconnexion. Évitez les doublons si un autre poste crée le même patient.
        </OfflineHint>
        <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ xs: 'stretch', sm: 'center' }} spacing={2}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ width: 44, height: 44, borderRadius: 'md', bgcolor: LOTRU_PRIMARY[50], color: LOTRU_PRIMARY[600], display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Users size={22} />
            </Box>
            <Box>
              <Typography level="h3" sx={{ fontWeight: 700, color: LOTRU_NEUTRAL[900] }}>Patients</Typography>
              <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600] }}>
                Enregistrement et accès aux dossiers patients (DPI)
              </Typography>
            </Box>
          </Stack>
          <Stack direction="row" spacing={1}>
            {canExport ? (
              <ExportButtons loading={exportLoading} onExport={handleExport} />
            ) : null}
            {canCreate ? (
              <Button startDecorator={<Plus size={18} />} onClick={openCreate}>
                Nouveau patient
              </Button>
            ) : null}
          </Stack>
        </Stack>

        <Card variant="outlined" sx={{ borderRadius: 'lg' }}>
          <Stack spacing={2} sx={{ p: 2 }}>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
              <Input
                startDecorator={<Search size={18} />}
                placeholder="Rechercher (nom, téléphone, n° dossier…)"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                sx={{ flex: 1 }}
              />
              <Select
                value={statusFilter}
                onChange={(_, value) => setStatusFilter(value ?? '')}
                placeholder="Statut"
                sx={{ minWidth: 160 }}
              >
                <Option value="">Tous les statuts</Option>
                {Object.entries(PATIENT_STATUS_LABELS).map(([value, label]) => (
                  <Option key={value} value={value}>{label}</Option>
                ))}
              </Select>
              <Select
                value={sexeFilter}
                onChange={(_, value) => setSexeFilter(value ?? '')}
                placeholder="Sexe"
                sx={{ minWidth: 140 }}
              >
                <Option value="">Tous</Option>
                <Option value="M">Masculin</Option>
                <Option value="F">Féminin</Option>
              </Select>
            </Stack>

            {listError ? (
              <Typography level="body-sm" color="danger">{listError}</Typography>
            ) : null}

            <Sheet variant="outlined" sx={{ borderRadius: 'md', overflow: 'auto' }}>
              <Table stickyHeader hoverRow sx={{ minWidth: 960 }}>
                <thead>
                  <tr>
                    <th>N° dossier</th>
                    <th>Identité</th>
                    <th>Sexe</th>
                    <th>Date naiss.</th>
                    <th>Téléphone</th>
                    <th>Statut</th>
                    <th>DPI</th>
                    {showActions ? <th style={{ width: 120 }}>Actions</th> : null}
                  </tr>
                </thead>
                <tbody>
                  {loading ? (
                    <tr>
                      <td colSpan={showActions ? 8 : 7}>
                        <Typography level="body-sm" sx={{ py: 3, textAlign: 'center' }}>Chargement…</Typography>
                      </td>
                    </tr>
                  ) : items.length === 0 ? (
                    <tr>
                      <td colSpan={showActions ? 8 : 7}>
                        <Typography level="body-sm" sx={{ py: 3, textAlign: 'center', color: 'neutral.500' }}>
                          Aucun patient trouvé.
                        </Typography>
                      </td>
                    </tr>
                  ) : items.map((item) => (
                    <tr key={item.id}>
                      <td>
                        <Typography level="body-sm" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>
                          {item.numDossier ?? '—'}
                        </Typography>
                      </td>
                      <td>
                        <Typography level="body-sm" sx={{ fontWeight: 600 }}>{item.fullName}</Typography>
                      </td>
                      <td>{PATIENT_SEX_LABELS[item.sexe] ?? item.sexe}</td>
                      <td>{formatDate(item.dateNaissance)}</td>
                      <td>{item.telephone ?? '—'}</td>
                      <td><StatusChip status={item.status} /></td>
                      <td><DpiChip statut={item.dpiStatut} /></td>
                      {showActions ? (
                        <td>
                          <Stack direction="row" spacing={0.5}>
                            {canOpenDpi ? (
                              <IconButton size="sm" variant="plain" color="primary" title="Ouvrir le DPI" onClick={() => openDpi(item)}>
                                <FolderOpen size={16} />
                              </IconButton>
                            ) : null}
                            {canUpdate ? (
                              <IconButton size="sm" variant="plain" color="neutral" title="Modifier" onClick={() => openEdit(item)}>
                                <Pencil size={16} />
                              </IconButton>
                            ) : null}
                            {canDelete ? (
                              <IconButton
                                size="sm"
                                variant="plain"
                                color="danger"
                                title={isItemLocked(item) ? 'Patient lié à des visites ou antécédents' : 'Supprimer'}
                                disabled={isItemLocked(item)}
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

            <AppPagination
              page={pagination.page}
              totalPages={pagination.totalPages}
              total={pagination.total}
              limit={limit}
              limitOptions={PATIENT_PAGE_SIZE_OPTIONS}
              onPageChange={setPage}
              onLimitChange={setLimit}
            />
          </Stack>
        </Card>
      </Stack>

      <PatientFormModal
        open={formOpen}
        mode={formMode}
        initialValues={formValues}
        loading={formLoading}
        error={formError}
        readOnlyIdentity={formMode === 'edit' && editing?.status === 'DECEDE'}
        onClose={() => setFormOpen(false)}
        onSubmit={handleSubmit}
      />

      <PatientDeleteModal
        open={deleteOpen}
        patient={deleting}
        loading={deleteLoading}
        error={deleteError}
        onClose={() => setDeleteOpen(false)}
        onConfirm={handleDelete}
      />
    </Box>
  );
}
