import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Box, Button, Card, Chip, IconButton, Input, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { ClipboardList, Eye, Plus, Search, Trash2 } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import ConfirmModal from '../../../components/ui/ConfirmModal.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { ROUTES } from '../../../constants/routes.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { formatPrix } from '../shared/format.js';
import {
  DEFAULT_DEMANDE_PAGE_SIZE,
  DEMANDE_PAGE_SIZE_OPTIONS,
  DEMANDE_STATUT_COLORS,
  DEMANDE_STATUT_LABELS,
  DEMANDE_STATUTS,
  PAIEMENT_STATUT_COLORS,
  PAIEMENT_STATUT_LABELS,
} from './demandeConstants.js';
import { deleteDemandeServiceApi, fetchDemandesServiceApi } from './demandesServiceApi.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_DEMANDE_PAGE_SIZE, total: 0, totalPages: 0 };

export default function DemandesServicePage() {
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.PHARMACIE.DEMANDE_SERVICE_CREATE);
  const canDelete = hasPermission(PERMISSIONS.PHARMACIE.DEMANDE_SERVICE_DELETE);

  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [statut, setStatut] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_DEMANDE_PAGE_SIZE);
  const [pendingDelete, setPendingDelete] = useState(null);
  const [confirmLoading, setConfirmLoading] = useState(false);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, limit, statut]);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchDemandesServiceApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        statut: statut || undefined,
      });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (error) {
      setListError(error.message || 'Impossible de charger les demandes.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, statut]);

  useEffect(() => { load(page); }, [load, page]);

  const handleDelete = async () => {
    if (!pendingDelete) return;
    setConfirmLoading(true);
    try {
      await deleteDemandeServiceApi(pendingDelete.id);
      showSuccess('Brouillon supprimé.');
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
            <ClipboardList size={24} color={LOTRU_PRIMARY[600]} />
            <Box>
              <Typography level="h2" sx={{ fontWeight: 700 }}>Demandes de service</Typography>
              <Typography level="body-md" sx={{ color: 'neutral.500' }}>
                Le service prend d’abord, le règlement suit (créance).
              </Typography>
            </Box>
          </Stack>
          {canCreate ? (
            <Button startDecorator={<Plus size={16} />} onClick={() => navigate(ROUTES.PHARMACIE.DEMANDE_SERVICE_NEW)}>
              Nouvelle demande
            </Button>
          ) : null}
        </Stack>

        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
            <Input startDecorator={<Search size={16} />} placeholder="N° ou service…" value={search} onChange={(e) => setSearch(e.target.value)} sx={{ flex: 1 }} />
            <Select placeholder="Tous les statuts" value={statut || null} onChange={(_, value) => setStatut(value ?? '')} sx={{ minWidth: 180 }}>
              <Option value="">Tous les statuts</Option>
              {DEMANDE_STATUTS.map((item) => <Option key={item.value} value={item.value}>{item.label}</Option>)}
            </Select>
          </Stack>
        </Card>

        {listError ? <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>{listError}</Typography> : null}

        <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto' }}>
          <Table stickyHeader hoverRow sx={{ minWidth: 900 }}>
            <thead>
              <tr>
                <th>Numéro</th>
                <th>Service</th>
                <th>Patient</th>
                <th>Montant</th>
                <th>Statut</th>
                <th>Paiement</th>
                <th style={{ textAlign: 'right' }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={7}><Typography level="body-sm" sx={{ p: 2 }}>Chargement…</Typography></td></tr>
              ) : items.length === 0 ? (
                <tr><td colSpan={7}><Typography level="body-sm" sx={{ p: 2, color: LOTRU_NEUTRAL[600] }}>Aucune demande.</Typography></td></tr>
              ) : items.map((item) => (
                <tr key={item.id}>
                  <td><Typography level="body-sm" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>{item.numero}</Typography></td>
                  <td>{item.service?.libelle ?? '—'}</td>
                  <td>{item.visite?.patientName || item.visite?.patient ? (item.visite.patientName || [item.visite.patient?.nom, item.visite.patient?.prenom].filter(Boolean).join(' ')) : '—'}</td>
                  <td>{formatPrix(item.montantTotal)}</td>
                  <td><Chip size="sm" variant="soft" color={DEMANDE_STATUT_COLORS[item.statut] ?? 'neutral'}>{DEMANDE_STATUT_LABELS[item.statut] ?? item.statut}</Chip></td>
                  <td><Chip size="sm" variant="soft" color={PAIEMENT_STATUT_COLORS[item.statutPaiement] ?? 'neutral'}>{PAIEMENT_STATUT_LABELS[item.statutPaiement] ?? item.statutPaiement}</Chip></td>
                  <td style={{ textAlign: 'right' }}>
                    <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                      <IconButton size="sm" variant="plain" onClick={() => navigate(ROUTES.PHARMACIE.DEMANDE_SERVICE_DETAIL.replace(':id', String(item.id)))}>
                        <Eye size={16} />
                      </IconButton>
                      {canDelete && item.statut === 'BROUILLON' ? (
                        <IconButton size="sm" variant="plain" color="danger" onClick={() => setPendingDelete(item)}><Trash2 size={16} /></IconButton>
                      ) : null}
                    </Stack>
                  </td>
                </tr>
              ))}
            </tbody>
          </Table>
        </Sheet>

        <AppPagination page={pagination.page} totalPages={pagination.totalPages} total={pagination.total} limit={limit} limitOptions={DEMANDE_PAGE_SIZE_OPTIONS} onPageChange={setPage} onLimitChange={setLimit} />
      </Stack>
      <ConfirmModal
        open={Boolean(pendingDelete)}
        title="Supprimer le brouillon"
        message={pendingDelete ? `Supprimer ${pendingDelete.numero} ?` : ''}
        confirmLabel="Supprimer"
        loading={confirmLoading}
        onClose={() => setPendingDelete(null)}
        onConfirm={handleDelete}
      />
    </Box>
  );
}
