import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Box, Button, Card, Chip, FormControl, FormLabel, Input, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { Eye, Wallet } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { ROUTES } from '../../../constants/routes.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { formatDateTime, formatPrix } from '../shared/format.js';
import { PERIOD_OPTIONS, resolvePeriodRange } from '../shared/period.js';
import { fetchRecettesApi } from './recettesApi.js';

const PAGE_SIZE = 15;
const PAGE_SIZE_OPTIONS = [15, 25, 50];
const EMPTY_PAGINATION = { page: 1, limit: PAGE_SIZE, total: 0, totalPages: 0 };
const EMPTY_TOTAUX = {
  encaisseVentes: '0',
  encaisseServices: '0',
  encaisseTotal: '0',
  creancesOuvertes: '0',
  creancesCount: 0,
};

export default function RecettesPage() {
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const canCreances = hasPermission(PERMISSIONS.PHARMACIE.DEMANDE_SERVICE_READ);
  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [totaux, setTotaux] = useState(EMPTY_TOTAUX);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [type, setType] = useState('');
  const [period, setPeriod] = useState('today');
  const [customFrom, setCustomFrom] = useState('');
  const [customTo, setCustomTo] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(PAGE_SIZE);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, type, period, customFrom, customTo, limit]);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setError('');
    const range = resolvePeriodRange(period, customFrom, customTo);
    try {
      const result = await fetchRecettesApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        type: type || undefined,
        dateFrom: range.dateFrom,
        dateTo: range.dateTo,
      });
      setItems(result.items);
      setPagination(result.pagination);
      setTotaux(result.totaux);
    } catch (err) {
      setError(err.message || 'Impossible de charger le suivi des recettes.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, type, period, customFrom, customTo]);

  useEffect(() => { load(page); }, [load, page]);

  const openDetail = (item) => {
    if (item.type === 'VENTE') {
      navigate(ROUTES.PHARMACIE.VENTE_DETAIL.replace(':id', String(item.id)));
      return;
    }
    navigate(ROUTES.PHARMACIE.DEMANDE_SERVICE_DETAIL.replace(':id', String(item.id)));
  };

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={2.5}>
        <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={1.5}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Wallet size={24} color={LOTRU_PRIMARY[600]} />
            <Box>
              <Typography level="h2" sx={{ fontWeight: 700 }}>Recettes pharmacie</Typography>
              <Typography level="body-md" sx={{ color: 'neutral.500' }}>
                Suivi de l’argent : ventes validées et demandes de service délivrées.
              </Typography>
            </Box>
          </Stack>
          {canCreances ? (
            <Button variant="outlined" onClick={() => navigate(ROUTES.PHARMACIE.CREANCES)}>
              Voir les créances ({totaux.creancesCount})
            </Button>
          ) : null}
        </Stack>

        <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
          <SummaryCard title="Encaissé ventes" value={formatPrix(totaux.encaisseVentes)} color="primary" />
          <SummaryCard title="Encaissé services" value={formatPrix(totaux.encaisseServices)} color="success" />
          <SummaryCard title="Total encaissé" value={formatPrix(totaux.encaisseTotal)} />
          <SummaryCard title="Créances ouvertes" value={formatPrix(totaux.creancesOuvertes)} color="warning" hint={`${totaux.creancesCount} demande(s) impayée(s)`} />
        </Stack>

        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
          <Stack spacing={1.5}>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
              <Input placeholder="N°, client, service…" value={search} onChange={(e) => setSearch(e.target.value)} sx={{ flex: 1 }} />
              <Select value={type || null} onChange={(_, value) => setType(value ?? '')} placeholder="Toutes les origines" sx={{ minWidth: 200 }}>
                <Option value="">Ventes + services</Option>
                <Option value="VENTE">Ventes seulement</Option>
                <Option value="SERVICE">Services seulement</Option>
              </Select>
              <Select value={period} onChange={(_, value) => setPeriod(value ?? 'today')} sx={{ minWidth: 200 }}>
                {PERIOD_OPTIONS.map((item) => <Option key={item.value} value={item.value}>{item.label}</Option>)}
              </Select>
            </Stack>
            {period === 'custom' ? (
              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
                <FormControl sx={{ minWidth: 180 }}>
                  <FormLabel>Du</FormLabel>
                  <Input type="date" value={customFrom} onChange={(e) => setCustomFrom(e.target.value)} />
                </FormControl>
                <FormControl sx={{ minWidth: 180 }}>
                  <FormLabel>Au</FormLabel>
                  <Input type="date" value={customTo} onChange={(e) => setCustomTo(e.target.value)} />
                </FormControl>
              </Stack>
            ) : null}
          </Stack>
        </Card>

        {error ? <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>{error}</Typography> : null}

        <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto' }}>
          <Table stickyHeader hoverRow sx={{ minWidth: 880 }}>
            <thead>
              <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Numéro</th>
                <th>Client / service</th>
                <th>Montant</th>
                <th>Statut</th>
                <th style={{ textAlign: 'right' }}>Détail</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={7}><Typography level="body-sm" sx={{ p: 2 }}>Chargement…</Typography></td></tr>
              ) : items.length === 0 ? (
                <tr><td colSpan={7}><Typography level="body-sm" sx={{ p: 2, color: LOTRU_NEUTRAL[600] }}>Aucune recette sur cette période.</Typography></td></tr>
              ) : items.map((item) => (
                <tr key={`${item.type}-${item.id}`}>
                  <td>{formatDateTime(item.date)}</td>
                  <td>
                    <Chip size="sm" variant="soft" color={item.type === 'VENTE' ? 'primary' : 'success'}>
                      {item.type === 'VENTE' ? (item.origine === 'HOSPITALISE' ? 'Vente hosp.' : 'Vente') : 'Service'}
                    </Chip>
                  </td>
                  <td><Typography level="body-sm" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>{item.numero}</Typography></td>
                  <td>{item.libelle}</td>
                  <td>{formatPrix(item.montant)}</td>
                  <td>
                    <Chip size="sm" variant="soft" color={item.statut === 'IMPAYEE' ? 'warning' : 'success'}>
                      {item.statut === 'IMPAYEE' ? 'Impayée' : 'Encaissée'}
                    </Chip>
                  </td>
                  <td style={{ textAlign: 'right' }}>
                    <Button size="sm" variant="plain" startDecorator={<Eye size={14} />} onClick={() => openDetail(item)}>Voir</Button>
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
          limitOptions={PAGE_SIZE_OPTIONS}
          onPageChange={setPage}
          onLimitChange={setLimit}
        />
      </Stack>
    </Box>
  );
}

function SummaryCard({ title, value, hint, color = 'neutral' }) {
  return (
    <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2, flex: 1, minWidth: 160 }}>
      <Typography level="body-xs" sx={{ color: 'neutral.500', fontWeight: 600, textTransform: 'uppercase' }}>{title}</Typography>
      <Typography level="title-lg" color={color} sx={{ fontWeight: 800, mt: 0.5 }}>{value}</Typography>
      {hint ? <Typography level="body-xs" sx={{ color: 'neutral.500', mt: 0.5 }}>{hint}</Typography> : null}
    </Card>
  );
}
