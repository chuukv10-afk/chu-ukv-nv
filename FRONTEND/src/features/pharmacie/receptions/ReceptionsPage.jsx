import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Box, Button, Card, Chip, IconButton, Input, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { Eye, PackagePlus, Plus, Search, Trash2 } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import ConfirmModal from '../../../components/ui/ConfirmModal.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { ROUTES } from '../../../constants/routes.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import PendingSyncChip from '../../../offline/PendingSyncChip.jsx';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { formatDate } from '../shared/format.js';
import {
  DEFAULT_RECEPTION_PAGE_SIZE,
  RECEPTION_PAGE_SIZE_OPTIONS,
  RECEPTION_STATUT_COLORS,
  RECEPTION_STATUT_LABELS,
  RECEPTION_STATUTS,
} from './receptionConstants.js';
import { deleteReceptionApi, fetchReceptionsApi } from './receptionsApi.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_RECEPTION_PAGE_SIZE, total: 0, totalPages: 0 };

export default function ReceptionsPage() {
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.PHARMACIE.RECEPTION_CREATE);
  const canDelete = hasPermission(PERMISSIONS.PHARMACIE.RECEPTION_DELETE);
  const showActions = true;

  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [statut, setStatut] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_RECEPTION_PAGE_SIZE);
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
      const result = await fetchReceptionsApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        statut: statut || undefined,
      });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (error) {
      setListError(error.message || 'Impossible de charger les réceptions.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, statut]);

  useEffect(() => { load(page); }, [load, page]);

  const handleDelete = async () => {
    if (!pendingDelete) return;
    setConfirmLoading(true);
    try {
      await deleteReceptionApi(pendingDelete.id);
      showSuccess('Réception supprimée.');
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
            <PackagePlus size={24} color={LOTRU_PRIMARY[600]} />
            <Box>
              <Typography level="h2" sx={{ fontWeight: 700 }}>Réceptions</Typography>
              <Typography level="body-md" sx={{ color: 'neutral.500' }}>
                Entrées fournisseur : lots, péremption et prix d’achat.
              </Typography>
            </Box>
          </Stack>
          {canCreate ? (
            <Button startDecorator={<Plus size={16} />} onClick={() => navigate(ROUTES.PHARMACIE.RECEPTION_NEW)}>
              Nouvelle réception
            </Button>
          ) : null}
        </Stack>

        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
            <Input
              startDecorator={<Search size={16} />}
              placeholder="N° réception, fournisseur, référence…"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              sx={{ flex: 1 }}
            />
            <Select
              placeholder="Tous les statuts"
              value={statut || null}
              onChange={(_, value) => setStatut(value ?? '')}
              sx={{ minWidth: 200 }}
            >
              <Option value="">Tous les statuts</Option>
              {RECEPTION_STATUTS.map((item) => (
                <Option key={item.value} value={item.value}>{item.label}</Option>
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
          <Table stickyHeader hoverRow sx={{ minWidth: 860 }}>
            <thead>
              <tr>
                <th>Numéro</th>
                <th>Date</th>
                <th>Fournisseur</th>
                <th>Référence</th>
                <th>Lignes</th>
                <th>Statut</th>
                {showActions ? <th style={{ textAlign: 'right' }}>Actions</th> : null}
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={7}><Typography level="body-sm" sx={{ p: 2 }}>Chargement…</Typography></td></tr>
              ) : items.length === 0 ? (
                <tr><td colSpan={7}><Typography level="body-sm" sx={{ p: 2, color: LOTRU_NEUTRAL[600] }}>Aucune réception.</Typography></td></tr>
              ) : items.map((item) => (
                <tr key={item.id}>
                  <td><Typography level="body-sm" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>{item.numero}</Typography></td>
                  <td>{formatDate(item.dateReception)}</td>
                  <td>{item.fournisseur?.libelle ?? '—'}</td>
                  <td>{item.referenceExterne || '—'}</td>
                  <td>{item.lignesCount ?? 0}</td>
                  <td>
                    <Stack direction="row" spacing={0.5} flexWrap="wrap" useFlexGap>
                      <Chip size="sm" variant="soft" color={RECEPTION_STATUT_COLORS[item.statut] ?? 'neutral'}>
                        {RECEPTION_STATUT_LABELS[item.statut] ?? item.statut}
                      </Chip>
                      <PendingSyncChip show={item.pendingSync} />
                    </Stack>
                  </td>
                  <td style={{ textAlign: 'right' }}>
                    <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                      <IconButton
                        size="sm"
                        variant="plain"
                        onClick={() => navigate(ROUTES.PHARMACIE.RECEPTION_DETAIL.replace(':id', String(item.id)))}
                      >
                        <Eye size={16} />
                      </IconButton>
                      {canDelete && item.statut === 'BROUILLON' ? (
                        <IconButton size="sm" variant="plain" color="danger" onClick={() => setPendingDelete(item)}>
                          <Trash2 size={16} />
                        </IconButton>
                      ) : null}
                    </Stack>
                  </td>
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
          limitOptions={RECEPTION_PAGE_SIZE_OPTIONS}
          onPageChange={setPage}
          onLimitChange={setLimit}
        />
      </Stack>
      <ConfirmModal
        open={Boolean(pendingDelete)}
        title="Supprimer la réception"
        message={pendingDelete ? `Supprimer le brouillon ${pendingDelete.numero} ?` : ''}
        confirmLabel="Supprimer"
        loading={confirmLoading}
        onClose={() => setPendingDelete(null)}
        onConfirm={handleDelete}
      />
    </Box>
  );
}
