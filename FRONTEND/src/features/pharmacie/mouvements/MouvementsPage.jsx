import { useCallback, useEffect, useState } from 'react';
import {
  Box, Card, Chip, FormControl, FormLabel, Input, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { ArrowLeftRight, Search } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import ExportButtons from '../../../components/export/ExportButtons.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { formatDateTime } from '../shared/format.js';
import { fetchMedicamentsActifsApi } from '../medicaments/medicamentsApi.js';
import MedicamentAutocomplete from '../shared/MedicamentAutocomplete.jsx';
import { PERIOD_OPTIONS, resolvePeriodRange } from '../shared/period.js';
import {
  DEFAULT_MOUVEMENT_PAGE_SIZE,
  MOUVEMENT_PAGE_SIZE_OPTIONS,
  MOUVEMENT_TYPE_LABELS,
} from './mouvementConstants.js';
import { exportFichesStockApi, fetchMouvementsApi } from './mouvementsApi.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_MOUVEMENT_PAGE_SIZE, total: 0, totalPages: 0 };

export default function MouvementsPage() {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canExport = hasPermission(PERMISSIONS.PHARMACIE.MOUVEMENT_EXPORT);
  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [medicaments, setMedicaments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [exportLoading, setExportLoading] = useState(null);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [medicamentId, setMedicamentId] = useState('');
  const [period, setPeriod] = useState('all');
  const [customFrom, setCustomFrom] = useState('');
  const [customTo, setCustomTo] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_MOUVEMENT_PAGE_SIZE);

  useEffect(() => {
    fetchMedicamentsActifsApi().then(setMedicaments).catch(() => setMedicaments([]));
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, limit, medicamentId, period, customFrom, customTo]);

  const range = period === 'all' ? {} : resolvePeriodRange(period, customFrom, customTo);
  const filterParams = {
    search: debouncedSearch || undefined,
    medicamentId: medicamentId || undefined,
    dateFrom: range.dateFrom,
    dateTo: range.dateTo,
  };

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchMouvementsApi({
        page: targetPage,
        limit,
        ...filterParams,
      });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (error) {
      setListError(error.message || 'Impossible de charger le journal.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, medicamentId, period, customFrom, customTo]);

  useEffect(() => { load(page); }, [load, page]);

  const handleExport = async (format) => {
    setExportLoading(format);
    try {
      await exportFichesStockApi(format, filterParams);
      showSuccess(format === 'pdf' ? 'Fiche(s) de stock ouverte(s) dans le navigateur.' : 'Fiche(s) de stock Excel téléchargée(s).');
    } catch (error) {
      showError(error.message || 'Génération de la fiche de stock impossible.');
    } finally {
      setExportLoading(null);
    }
  };

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={2.5}>
        <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={1.5}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <ArrowLeftRight size={24} color={LOTRU_PRIMARY[600]} />
            <Box>
              <Typography level="h2" sx={{ fontWeight: 700 }}>Journal de stock</Typography>
              <Typography level="body-md" sx={{ color: 'neutral.500' }}>
                Historique des entrées et sorties. Générez les fiches de stock (kardex) sur la période.
              </Typography>
            </Box>
          </Stack>
          {canExport ? (
            <ExportButtons onExport={handleExport} loading={exportLoading} />
          ) : null}
        </Stack>

        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
          <Stack spacing={1.5}>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
              <Input
                startDecorator={<Search size={16} />}
                placeholder="Médicament, n° lot, type…"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                sx={{ flex: 1 }}
              />
              <Select value={period} onChange={(_, value) => setPeriod(value ?? 'all')} sx={{ minWidth: 200 }}>
                <Option value="all">Tout l’historique</Option>
                {PERIOD_OPTIONS.map((item) => <Option key={item.value} value={item.value}>{item.label}</Option>)}
              </Select>
            </Stack>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} alignItems={{ md: 'flex-end' }}>
              <Box sx={{ flex: 1, minWidth: 240 }}>
                <MedicamentAutocomplete
                  options={medicaments}
                  valueId={medicamentId}
                  placeholder="Tous les médicaments (fiches groupées)"
                  onSelect={(item) => setMedicamentId(item ? String(item.id) : '')}
                />
              </Box>
              {period === 'custom' ? (
                <>
                  <FormControl sx={{ minWidth: 180 }}>
                    <FormLabel>Du</FormLabel>
                    <Input type="date" value={customFrom} onChange={(event) => setCustomFrom(event.target.value)} />
                  </FormControl>
                  <FormControl sx={{ minWidth: 180 }}>
                    <FormLabel>Au</FormLabel>
                    <Input type="date" value={customTo} onChange={(event) => setCustomTo(event.target.value)} />
                  </FormControl>
                </>
              ) : null}
            </Stack>
            {canExport ? (
              <Typography level="body-xs" sx={{ color: 'neutral.500' }}>
                PDF / Excel : une fiche par médicament (stock d’ouverture, mouvements, solde). Sans sélection, tous les produits concernés par la période.
              </Typography>
            ) : null}
          </Stack>
        </Card>

        {listError ? (
          <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
            {listError}
          </Typography>
        ) : null}

        <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto' }}>
          <Table stickyHeader hoverRow sx={{ minWidth: 1040 }}>
            <thead>
              <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Sens</th>
                <th>Médicament</th>
                <th>Lot</th>
                <th>Qté</th>
                <th>Document</th>
                <th>Motif</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={8}><Typography level="body-sm" sx={{ p: 2 }}>Chargement…</Typography></td></tr>
              ) : items.length === 0 ? (
                <tr><td colSpan={8}><Typography level="body-sm" sx={{ p: 2, color: LOTRU_NEUTRAL[600] }}>Aucun mouvement.</Typography></td></tr>
              ) : items.map((item) => (
                <tr key={item.id}>
                  <td>{formatDateTime(item.createdAt)}</td>
                  <td>{MOUVEMENT_TYPE_LABELS[item.type] ?? item.type}</td>
                  <td>
                    <Chip size="sm" variant="soft" color={item.sens === 'ENTREE' ? 'success' : 'danger'}>
                      {item.sens}
                    </Chip>
                  </td>
                  <td>{item.medicament ? `${item.medicament.code} — ${item.medicament.libelle}` : '—'}</td>
                  <td>{item.lot?.numeroLot ?? '—'}</td>
                  <td>{item.quantite}</td>
                  <td>{item.documentType} #{item.documentId}</td>
                  <td>{item.motif || '—'}</td>
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
          limitOptions={MOUVEMENT_PAGE_SIZE_OPTIONS}
          onPageChange={setPage}
          onLimitChange={setLimit}
        />
      </Stack>
    </Box>
  );
}
