import { useCallback, useEffect, useState } from 'react';
import { Link as RouterLink, useNavigate } from 'react-router-dom';
import {
  Box, Button, Card, Chip, IconButton, Input, Link, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { ClipboardList, Search, Stethoscope, Trash2 } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import { ROUTES } from '../../../constants/routes.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import {
  CONSULTATION_PAGE_SIZE_OPTIONS,
  CONSULTATION_STATUT_COLORS,
  CONSULTATION_STATUT_LABELS,
  CONSULTATION_TYPE_COLORS,
  CONSULTATION_TYPE_LABELS,
  DEFAULT_CONSULTATION_PAGE_SIZE,
} from './consultationConstants.js';
import {
  deleteConsultationApi,
  fetchConsultationMetaApi,
  fetchConsultationsApi,
} from './consultationsApi.js';
import { getConsultationWorkspacePath } from '../tour-de-salle/tourDeSalleConstants.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_CONSULTATION_PAGE_SIZE, total: 0, totalPages: 0 };

function formatDateTime(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('fr-FR');
}

function StatusChip({ statut }) {
  return (
    <Chip size="sm" variant="soft" color={CONSULTATION_STATUT_COLORS[statut] ?? 'neutral'}>
      {CONSULTATION_STATUT_LABELS[statut] ?? statut}
    </Chip>
  );
}

export default function ConsultationsPage() {
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canRead = hasPermission(PERMISSIONS.CLINIQUE.CONSULTATION_READ);
  const canDelete = hasPermission(PERMISSIONS.CLINIQUE.CONSULTATION_DELETE);
  const showActions = canRead || canDelete;

  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState({ statuts: [] });
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [statutFilter, setStatutFilter] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_CONSULTATION_PAGE_SIZE);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, statutFilter, limit]);

  useEffect(() => {
    fetchConsultationMetaApi().then(setMeta).catch(() => setMeta({ statuts: [] }));
  }, []);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchConsultationsApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        statut: statutFilter || undefined,
      });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (error) {
      setListError(error.message || 'Impossible de charger les consultations.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, statutFilter]);

  useEffect(() => { load(page); }, [load, page]);

  const openConsultation = (item) => {
    navigate(getConsultationWorkspacePath(item));
  };

  const consultationPath = (item) => getConsultationWorkspacePath(item);

  const handleDelete = async (item) => {
    if (!window.confirm('Supprimer cette consultation ?')) return;
    try {
      await deleteConsultationApi(item.id);
      showSuccess('Consultation supprimée.');
      await load(page);
    } catch (error) {
      showError(error.message || 'Suppression impossible.');
    }
  };

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ xs: 'stretch', sm: 'center' }} spacing={2} sx={{ mb: 3 }}>
        <Stack direction="row" spacing={1.5} alignItems="center">
          <ClipboardList size={28} color={LOTRU_PRIMARY[600]} />
          <Box>
            <Typography level="h2">Consultations</Typography>
            <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600] }}>
              Actes médicaux du médecin — toujours rattachés à une visite
            </Typography>
          </Box>
        </Stack>
      </Stack>

      <Card variant="outlined" sx={{ p: 2, mb: 2 }}>
        <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
          <Input
            startDecorator={<Search size={16} />}
            placeholder="Patient, motif, médecin..."
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            sx={{ flex: 1 }}
          />
          <Select
            placeholder="Statut"
            value={statutFilter}
            onChange={(_, value) => setStatutFilter(value ?? '')}
            sx={{ minWidth: 180 }}
          >
            <Option value="">Tous les statuts</Option>
            {(meta.statuts ?? []).map((statut) => (
              <Option key={statut} value={statut}>
                {CONSULTATION_STATUT_LABELS[statut] ?? statut}
              </Option>
            ))}
          </Select>
        </Stack>
      </Card>

      {listError && (
        <Typography level="body-sm" color="danger" sx={{ mb: 2 }}>{listError}</Typography>
      )}

      <Sheet variant="outlined" sx={{ borderRadius: 'md', overflow: 'auto' }}>
        <Table stickyHeader hoverRow sx={{ minWidth: 960 }}>
          <thead>
            <tr>
              <th>Date</th>
              <th>Patient</th>
              <th>Service</th>
              <th>Type</th>
              <th>Motif</th>
              <th>Médecin</th>
              <th>Statut</th>
              {showActions && <th>Actions</th>}
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr><td colSpan={showActions ? 8 : 7}><Typography level="body-sm" sx={{ p: 2 }}>Chargement...</Typography></td></tr>
            ) : items.length === 0 ? (
              <tr><td colSpan={showActions ? 8 : 7}><Typography level="body-sm" sx={{ p: 2 }}>Aucune consultation trouvée.</Typography></td></tr>
            ) : items.map((item) => (
              <tr key={item.id}>
                <td>
                  {canRead ? (
                    <Link
                      component={RouterLink}
                      to={consultationPath(item)}
                      fontWeight="md"
                    >
                      {formatDateTime(item.consultedAt)}
                    </Link>
                  ) : formatDateTime(item.consultedAt)}
                </td>
                <td>
                  {item.patientId ? (
                    <Link component={RouterLink} to={ROUTES.PATIENT.DPI.replace(':patientId', item.patientId)}>
                      {item.patientName ?? item.numDossier ?? '—'}
                    </Link>
                  ) : (item.patientName ?? '—')}
                </td>
                <td>{item.service?.libelle ?? '—'}</td>
                <td>
                  <Chip size="sm" variant="soft" color={CONSULTATION_TYPE_COLORS[item.typeConsultation] ?? 'neutral'}>
                    {CONSULTATION_TYPE_LABELS[item.typeConsultation] ?? item.typeConsultation ?? '—'}
                  </Chip>
                </td>
                <td>
                  <Typography level="body-sm" noWrap sx={{ maxWidth: 200 }} title={item.motif ?? ''}>
                    {item.motif ?? '—'}
                  </Typography>
                </td>
                <td>{item.openedBy?.fullName ?? '—'}</td>
                <td><StatusChip statut={item.statut} /></td>
                {showActions && (
                  <td>
                    <Stack direction="row" spacing={0.75} alignItems="center">
                      {canRead && (
                        <Button
                          size="sm"
                          variant="soft"
                          startDecorator={<Stethoscope size={14} />}
                          onClick={() => openConsultation(item)}
                        >
                          Ouvrir
                        </Button>
                      )}
                      {canDelete && (
                        <IconButton
                          size="sm"
                          variant="plain"
                          color="danger"
                          title="Supprimer"
                          onClick={() => handleDelete(item)}
                        >
                          <Trash2 size={16} />
                        </IconButton>
                      )}
                    </Stack>
                  </td>
                )}
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
        limitOptions={CONSULTATION_PAGE_SIZE_OPTIONS}
        onPageChange={setPage}
        onLimitChange={setLimit}
        sx={{ mt: 2 }}
      />

    </Box>
  );
}
