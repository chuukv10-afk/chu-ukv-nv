import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Card,
  Chip,
  IconButton,
  Input,
  Option,
  Select,
  Sheet,
  Stack,
  Table,
  Typography,
} from '@mui/joy';
import { Search, Stethoscope, Trash2 } from 'lucide-react';
import AppPagination from '../../../../components/ui/AppPagination.jsx';
import { PERMISSIONS } from '../../../../constants/permissions.js';
import { usePermissions } from '../../../../hooks/usePermissions.js';
import { useToast } from '../../../../hooks/useToast.js';
import {
  CONSULTATION_PAGE_SIZE_OPTIONS,
  CONSULTATION_STATUT_COLORS,
  CONSULTATION_STATUT_LABELS,
  CONSULTATION_TYPE_COLORS,
  CONSULTATION_TYPE_LABELS,
  DEFAULT_CONSULTATION_PAGE_SIZE,
} from '../../../clinique/consultations/consultationConstants.js';
import {
  deleteConsultationApi,
  fetchConsultationMetaApi,
  fetchConsultationsApi,
} from '../../../clinique/consultations/consultationsApi.js';
import { getConsultationWorkspacePath } from '../../../clinique/tour-de-salle/tourDeSalleConstants.js';
import { VISITE_STATUT_COLORS, VISITE_STATUT_LABELS } from '../../../clinique/visites/visiteConstants.js';

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

export default function PatientConsultationsTab({ patientId }) {
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canRead = hasPermission(PERMISSIONS.CLINIQUE.CONSULTATION_READ);
  const canDelete = hasPermission(PERMISSIONS.CLINIQUE.CONSULTATION_DELETE);

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

  useEffect(() => {
    setPage(1);
  }, [debouncedSearch, statutFilter, limit, patientId]);

  useEffect(() => {
    fetchConsultationMetaApi().then(setMeta).catch(() => setMeta({ statuts: [] }));
  }, []);

  const load = useCallback(async (targetPage = page) => {
    if (!patientId || !canRead) {
      setItems([]);
      setLoading(false);
      return;
    }

    setLoading(true);
    setListError('');
    try {
      const result = await fetchConsultationsApi({
        page: targetPage,
        limit,
        patientId,
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
  }, [page, limit, debouncedSearch, statutFilter, patientId, canRead]);

  useEffect(() => {
    load(page);
  }, [load, page]);

  const openConsultation = (item) => {
    navigate(getConsultationWorkspacePath(item));
  };

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

  if (!canRead) {
    return (
      <Typography level="body-sm" color="warning" sx={{ bgcolor: 'warning.50', p: 1.5, borderRadius: 'md' }}>
        Permission insuffisante pour consulter les consultations.
      </Typography>
    );
  }

  return (
    <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5 }}>
      <Stack spacing={2}>
        <Typography level="title-md" sx={{ fontWeight: 700 }}>
          Historique des consultations
        </Typography>
        <Typography level="body-sm" sx={{ color: 'neutral.600' }}>
          Actes médicaux rattachés aux visites de ce dossier. Pour en ouvrir une nouvelle, utilisez l&apos;onglet Visites.
        </Typography>

        <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
          <Input
            startDecorator={<Search size={16} />}
            placeholder="Motif, médecin, service..."
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

        {listError ? (
          <Typography level="body-sm" color="danger">{listError}</Typography>
        ) : null}

        <Sheet variant="outlined" sx={{ borderRadius: 'md', overflow: 'auto' }}>
          <Table stickyHeader hoverRow sx={{ minWidth: 960 }}>
            <thead>
              <tr>
                <th>Date</th>
                <th>Service</th>
                <th>Visite</th>
                <th>Type</th>
                <th>Motif</th>
                <th>Médecin</th>
                <th>Statut</th>
                {(canRead || canDelete) && <th>Actions</th>}
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={canRead || canDelete ? 8 : 7}>
                    <Typography level="body-sm" sx={{ p: 2 }}>Chargement...</Typography>
                  </td>
                </tr>
              ) : items.length === 0 ? (
                <tr>
                  <td colSpan={canRead || canDelete ? 8 : 7}>
                    <Typography level="body-sm" sx={{ p: 2 }}>Aucune consultation enregistrée.</Typography>
                  </td>
                </tr>
              ) : items.map((item) => (
                <tr key={item.id}>
                  <td>{formatDateTime(item.consultedAt)}</td>
                  <td>{item.service?.libelle ?? '—'}</td>
                  <td>
                    {item.visiteStatut ? (
                      <Chip size="sm" variant="outlined" color={VISITE_STATUT_COLORS[item.visiteStatut] ?? 'neutral'}>
                        {VISITE_STATUT_LABELS[item.visiteStatut] ?? item.visiteStatut}
                      </Chip>
                    ) : '—'}
                  </td>
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
                  {(canRead || canDelete) && (
                    <td>
                      <Stack direction="row" spacing={0.75} alignItems="center">
                        {canRead && (
                          <IconButton
                            size="sm"
                            variant="soft"
                            color="primary"
                            title="Ouvrir"
                            onClick={() => openConsultation(item)}
                          >
                            <Stethoscope size={16} />
                          </IconButton>
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
        />
      </Stack>
    </Card>
  );
}
