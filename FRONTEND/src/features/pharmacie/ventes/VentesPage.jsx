import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Box, Button, Card, Chip, FormControl, FormLabel, IconButton, Input, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { Eye, Plus, Printer, Search, ShoppingCart, Trash2 } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import ConfirmModal from '../../../components/ui/ConfirmModal.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { ROUTES } from '../../../constants/routes.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { formatDateTime, formatPatientName, formatPrix, todayIso } from '../shared/format.js';
import { PERIOD_OPTIONS, resolvePeriodRange } from '../shared/period.js';
import {
  DATE_STOCK_OUVERTURE,
  DEFAULT_VENTE_PAGE_SIZE,
  VENTE_PAGE_SIZE_OPTIONS,
  VENTE_STATUT_COLORS,
  VENTE_STATUT_LABELS,
  VENTE_STATUTS,
} from './venteConstants.js';

const VENTE_PERIOD_OPTIONS = [
  ...PERIOD_OPTIONS.slice(0, -1),
  { value: 'since_ouverture', label: 'Depuis le 28/08' },
  PERIOD_OPTIONS[PERIOD_OPTIONS.length - 1],
];
import PendingSyncChip from '../../../offline/PendingSyncChip.jsx';
import { deleteVenteApi, fetchVenteApi, fetchVentesApi } from './ventesApi.js';
import { printVenteTicket } from './printVenteTicket.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_VENTE_PAGE_SIZE, total: 0, totalPages: 0 };

function clientKind(item) {
  if (item.origine === 'HOSPITALISE') return 'Hospitalisé';
  if (item.clientType === 'PATIENT') return 'Patient';
  return 'Passant';
}

function clientLabel(item) {
  if (item.origine === 'HOSPITALISE' || item.clientType === 'PATIENT') {
    const name = formatPatientName(item.patient);
    if (name && name !== '—') return name;
    return item.clientNom || 'Patient';
  }
  return item.clientNom || 'Passant';
}

export default function VentesPage() {
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.PHARMACIE.VENTE_CREATE);
  const canSaisirAnterieure = hasPermission(PERMISSIONS.PHARMACIE.VENTE_SAISIE_ANTERIEURE);
  const canDelete = hasPermission(PERMISSIONS.PHARMACIE.VENTE_DELETE);

  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [statut, setStatut] = useState('');
  const [period, setPeriod] = useState('today');
  const [customFrom, setCustomFrom] = useState('');
  const [customTo, setCustomTo] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_VENTE_PAGE_SIZE);
  const [pendingDelete, setPendingDelete] = useState(null);
  const [confirmLoading, setConfirmLoading] = useState(false);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, limit, statut, period, customFrom, customTo]);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const range = period === 'since_ouverture'
        ? { dateFrom: DATE_STOCK_OUVERTURE, dateTo: todayIso() }
        : resolvePeriodRange(period, customFrom, customTo);
      const result = await fetchVentesApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        statut: statut || undefined,
        dateFrom: range.dateFrom,
        dateTo: range.dateTo,
      });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (error) {
      setListError(error.message || 'Impossible de charger les ventes.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, statut, period, customFrom, customTo]);

  useEffect(() => { load(page); }, [load, page]);

  const handleDelete = async () => {
    if (!pendingDelete) return;
    setConfirmLoading(true);
    try {
      await deleteVenteApi(pendingDelete.id);
      showSuccess('Brouillon de vente supprimé.');
      setPendingDelete(null);
      await load(page);
    } catch (error) {
      showError(error.message || 'Suppression impossible.');
    } finally {
      setConfirmLoading(false);
    }
  };

  const handlePrint = async (item) => {
    try {
      const detail = item.lignes ? item : await fetchVenteApi(item.id);
      printVenteTicket(detail);
    } catch (error) {
      showError(error.message || 'Impression impossible.');
    }
  };

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={2.5}>
        <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={1.5}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <ShoppingCart size={24} color={LOTRU_PRIMARY[600]} />
            <Box>
              <Typography level="h2" sx={{ fontWeight: 700 }}>Ventes caisse</Typography>
              <Typography level="body-md" sx={{ color: 'neutral.500' }}>
                Passant ou patient, paiement immédiat, sortie FEFO.
                {canSaisirAnterieure
                  ? ' Les ventes antérieures n’apparaissent pas dans « Aujourd’hui » : choisissez Ce mois, Cette année ou Depuis le 28/08.'
                  : ''}
              </Typography>
            </Box>
          </Stack>
          <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
            {canSaisirAnterieure ? (
              <Button
                variant="outlined"
                startDecorator={<Plus size={16} />}
                onClick={() => navigate(ROUTES.PHARMACIE.VENTE_ANTERIEURE_NEW)}
              >
                Vente antérieure
              </Button>
            ) : null}
            {canCreate ? (
              <Button startDecorator={<Plus size={16} />} onClick={() => navigate(ROUTES.PHARMACIE.VENTE_NEW)}>
                Nouvelle vente
              </Button>
            ) : null}
          </Stack>
        </Stack>

        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
          <Stack spacing={1.5}>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
              <Input
                startDecorator={<Search size={16} />}
                placeholder="N° vente, client…"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                sx={{ flex: 1 }}
              />
              <Select
                placeholder="Tous les statuts"
                value={statut || null}
                onChange={(_, value) => setStatut(value ?? '')}
                sx={{ minWidth: 180 }}
              >
                <Option value="">Tous les statuts</Option>
                {VENTE_STATUTS.map((item) => (
                  <Option key={item.value} value={item.value}>{item.label}</Option>
                ))}
              </Select>
              <Select
                value={period}
                onChange={(_, value) => setPeriod(value ?? 'today')}
                sx={{ minWidth: 200 }}
              >
                {VENTE_PERIOD_OPTIONS.map((item) => (
                  <Option key={item.value} value={item.value}>{item.label}</Option>
                ))}
              </Select>
            </Stack>
            {period === 'custom' ? (
              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
                <FormControl sx={{ minWidth: 180 }}>
                  <FormLabel>Du</FormLabel>
                  <Input type="date" value={customFrom} onChange={(event) => setCustomFrom(event.target.value)} />
                </FormControl>
                <FormControl sx={{ minWidth: 180 }}>
                  <FormLabel>Au</FormLabel>
                  <Input type="date" value={customTo} onChange={(event) => setCustomTo(event.target.value)} />
                </FormControl>
              </Stack>
            ) : null}
          </Stack>
        </Card>

        {listError ? (
          <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
            {listError}
          </Typography>
        ) : null}

        <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto' }}>
          <Table stickyHeader hoverRow sx={{ minWidth: 920 }}>
            <thead>
              <tr>
                <th>Numéro</th>
                <th>Date</th>
                <th>Client</th>
                <th>Paiement</th>
                <th>Montant</th>
                <th>Statut</th>
                <th style={{ textAlign: 'right' }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={7}><Typography level="body-sm" sx={{ p: 2 }}>Chargement…</Typography></td></tr>
              ) : items.length === 0 ? (
                <tr><td colSpan={7}><Typography level="body-sm" sx={{ p: 2, color: LOTRU_NEUTRAL[600] }}>Aucune vente.</Typography></td></tr>
              ) : items.map((item) => (
                <tr key={item.id}>
                  <td><Typography level="body-sm" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>{item.numero}</Typography></td>
                  <td>{formatDateTime(item.dateVente || item.createdAt)}</td>
                  <td>
                    {clientKind(item)} — {clientLabel(item)}
                  </td>
                  <td>{item.modePaiement}</td>
                  <td>{formatPrix(item.montantTotal)}</td>
                  <td>
                    <Stack direction="row" spacing={0.5} flexWrap="wrap" useFlexGap>
                      <Chip size="sm" variant="soft" color={VENTE_STATUT_COLORS[item.statut] ?? 'neutral'}>
                        {VENTE_STATUT_LABELS[item.statut] ?? item.statut}
                      </Chip>
                      {item.historique ? (
                        <Chip size="sm" variant="soft" color="warning">Antérieure</Chip>
                      ) : null}
                      <PendingSyncChip show={item.pendingSync} />
                    </Stack>
                  </td>
                  <td style={{ textAlign: 'right' }}>
                    <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                      <IconButton
                        size="sm"
                        variant="plain"
                        onClick={() => navigate(ROUTES.PHARMACIE.VENTE_DETAIL.replace(':id', String(item.id)))}
                      >
                        <Eye size={16} />
                      </IconButton>
                      {item.statut !== 'BROUILLON' ? (
                        <IconButton size="sm" variant="plain" onClick={() => handlePrint(item)} title="Imprimer le ticket">
                          <Printer size={16} />
                        </IconButton>
                      ) : null}
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
          limitOptions={VENTE_PAGE_SIZE_OPTIONS}
          onPageChange={setPage}
          onLimitChange={setLimit}
        />
      </Stack>
      <ConfirmModal
        open={Boolean(pendingDelete)}
        title="Supprimer le brouillon"
        message={pendingDelete ? `Supprimer le brouillon ${pendingDelete.numero} ?` : ''}
        confirmLabel="Supprimer"
        loading={confirmLoading}
        onClose={() => setPendingDelete(null)}
        onConfirm={handleDelete}
      />
    </Box>
  );
}
