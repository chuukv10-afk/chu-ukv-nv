import { useCallback, useEffect, useState } from 'react';
import {
  Box, Card, Chip, IconButton, Input, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { AlertTriangle, Layers, Pencil, Search } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { isLocalId } from '../../../offline/idMap.js';
import PendingSyncChip from '../../../offline/PendingSyncChip.jsx';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { formatDate, formatPrix } from '../shared/format.js';
import LotFormModal from './components/LotFormModal.jsx';
import {
  DEFAULT_LOT_PAGE_SIZE,
  LOT_PAGE_SIZE_OPTIONS,
  LOT_STATUT_COLORS,
  LOT_STATUT_LABELS,
  LOT_STATUTS,
} from './lotConstants.js';
import { fetchLotAlertesApi, fetchLotsApi, updateLotApi } from './lotsApi.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_LOT_PAGE_SIZE, total: 0, totalPages: 0 };
const EMPTY_ALERTES = { perimes: [], peremptionProche: [], stockBas: [] };

export default function LotsPage() {
  const { hasPermission } = usePermissions();
  const { showSuccess } = useToast();
  const canUpdate = hasPermission(PERMISSIONS.PHARMACIE.LOT_UPDATE);

  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [alertes, setAlertes] = useState(EMPTY_ALERTES);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [statut, setStatut] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_LOT_PAGE_SIZE);
  const [editing, setEditing] = useState(null);
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, limit, statut]);

  useEffect(() => {
    fetchLotAlertesApi()
      .then(setAlertes)
      .catch(() => setAlertes(EMPTY_ALERTES));
  }, []);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchLotsApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        statut: statut || undefined,
      });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (error) {
      setListError(error.message || 'Impossible de charger les lots.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, statut]);

  useEffect(() => { load(page); }, [load, page]);

  const handleUpdate = async (payload) => {
    if (!editing) return;
    setFormLoading(true);
    setFormError('');
    try {
      await updateLotApi(editing.id, payload);
      showSuccess('Lot mis à jour.');
      setEditing(null);
      await load(page);
    } catch (error) {
      setFormError(error.message || 'Mise à jour impossible.');
    } finally {
      setFormLoading(false);
    }
  };

  const alerteCount = alertes.perimes.length + alertes.peremptionProche.length + alertes.stockBas.length;

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={2.5}>
        <Stack direction="row" spacing={1.5} alignItems="center">
          <Layers size={24} color={LOTRU_PRIMARY[600]} />
          <Box>
            <Typography level="h2" sx={{ fontWeight: 700 }}>Lots et stock</Typography>
            <Typography level="body-md" sx={{ color: 'neutral.500' }}>
              Quantités restantes, péremption et alertes (FEFO).
            </Typography>
          </Box>
        </Stack>

        {alerteCount > 0 ? (
          <Card variant="soft" color="warning" sx={{ borderRadius: 'lg', p: 2 }}>
            <Stack direction="row" spacing={1} alignItems="center" sx={{ mb: 1 }}>
              <AlertTriangle size={18} />
              <Typography level="title-sm" sx={{ fontWeight: 700 }}>Alertes stock</Typography>
            </Stack>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
              <Typography level="body-sm">{alertes.perimes.length} lot(s) périmé(s)</Typography>
              <Typography level="body-sm">{alertes.peremptionProche.length} lot(s) à péremption proche (90 j)</Typography>
              <Typography level="body-sm">{alertes.stockBas.length} médicament(s) sous seuil</Typography>
            </Stack>
            {alertes.stockBas.length > 0 ? (
              <Typography level="body-xs" sx={{ mt: 1, color: 'neutral.700' }}>
                Stock bas : {alertes.stockBas.map((item) => `${item.code} (${item.stockDisponible}/${item.seuilAlerte})`).join(', ')}
              </Typography>
            ) : null}
          </Card>
        ) : null}

        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
            <Input
              startDecorator={<Search size={16} />}
              placeholder="N° lot, code ou libellé médicament…"
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
              {LOT_STATUTS.map((item) => (
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
          <Table stickyHeader hoverRow sx={{ minWidth: 900 }}>
            <thead>
              <tr>
                <th>Médicament</th>
                <th>N° lot</th>
                <th>Péremption</th>
                <th>Qté restante</th>
                <th>Prix d’achat</th>
                <th>Statut</th>
                {canUpdate ? <th style={{ textAlign: 'right' }}>Actions</th> : null}
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={canUpdate ? 7 : 6}><Typography level="body-sm" sx={{ p: 2 }}>Chargement…</Typography></td></tr>
              ) : items.length === 0 ? (
                <tr><td colSpan={canUpdate ? 7 : 6}><Typography level="body-sm" sx={{ p: 2, color: LOTRU_NEUTRAL[600] }}>Aucun lot.</Typography></td></tr>
              ) : items.map((item) => (
                <tr key={item.id}>
                  <td>{item.medicament ? `${item.medicament.code} — ${item.medicament.libelle}` : '—'}</td>
                  <td><Typography level="body-sm" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>{item.numeroLot}</Typography></td>
                  <td>{formatDate(item.datePeremption)}</td>
                  <td>{item.quantiteRestante}</td>
                  <td>{formatPrix(item.prixAchatUnitaire)}</td>
                  <td>
                    <Stack direction="row" spacing={0.5} flexWrap="wrap" useFlexGap>
                      <Chip size="sm" variant="soft" color={LOT_STATUT_COLORS[item.statut] ?? 'neutral'}>
                        {LOT_STATUT_LABELS[item.statut] ?? item.statut}
                      </Chip>
                      <PendingSyncChip show={item.pendingSync} />
                    </Stack>
                  </td>
                  {canUpdate ? (
                    <td style={{ textAlign: 'right' }}>
                      <IconButton
                        size="sm"
                        variant="plain"
                        disabled={Boolean(item.pendingSync) || isLocalId(item.id)}
                        title={item.pendingSync || isLocalId(item.id) ? 'Synchronisez ce lot avant de le corriger' : 'Corriger le n° ou la péremption'}
                        onClick={() => { setFormError(''); setEditing(item); }}
                      >
                        <Pencil size={16} />
                      </IconButton>
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
          limitOptions={LOT_PAGE_SIZE_OPTIONS}
          onPageChange={setPage}
          onLimitChange={setLimit}
        />
      </Stack>

      <LotFormModal
        open={Boolean(editing)}
        lot={editing}
        loading={formLoading}
        error={formError}
        onClose={() => !formLoading && setEditing(null)}
        onSubmit={handleUpdate}
      />
    </Box>
  );
}
