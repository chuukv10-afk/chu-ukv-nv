import { useCallback, useEffect, useRef, useState } from 'react';
import {
  Box, Button, Card, Input, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { Search, Upload, Wallet } from 'lucide-react';
import AppPagination from '../../components/ui/AppPagination.jsx';
import ExportButtons from '../../components/export/ExportButtons.jsx';
import { facturation } from '../../api/endpoints.js';
import { PERMISSIONS } from '../../constants/permissions.js';
import { usePermissions } from '../../hooks/usePermissions.js';
import { useToast } from '../../hooks/useToast.js';
import { exportResourceApi } from '../../utils/exportApi.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../theme/lotruPalette.js';
import {
  ACTE_PAGE_SIZE_OPTIONS,
  DEFAULT_ACTE_PAGE_SIZE,
} from './facturationConstants.js';
import {
  fetchActesFinanciersApi,
  fetchActesFinanciersMetaApi,
  importGrilleTarifaireApi,
} from './facturationApi.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_ACTE_PAGE_SIZE, total: 0, totalPages: 0 };

function formatCdf(value) {
  if (value === null || value === undefined || value === '') return '—';
  const amount = Number(value);
  if (Number.isNaN(amount)) return String(value);
  return amount.toLocaleString('fr-CD', { maximumFractionDigits: 2 });
}

export default function ActesFinanciersPage() {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canImport = hasPermission(PERMISSIONS.FACTURATION.ACTE_IMPORT);
  const canExport = hasPermission(PERMISSIONS.FACTURATION.ACTE_EXPORT);
  const fileInputRef = useRef(null);

  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [serviceFilter, setServiceFilter] = useState('');
  const [serviceGrilles, setServiceGrilles] = useState([]);
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_ACTE_PAGE_SIZE);
  const [exportLoading, setExportLoading] = useState(null);
  const [importLoading, setImportLoading] = useState(false);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, serviceFilter, limit]);

  useEffect(() => {
    fetchActesFinanciersMetaApi()
      .then((meta) => setServiceGrilles(Array.isArray(meta.serviceGrilles) ? meta.serviceGrilles : []))
      .catch(() => setServiceGrilles([]));
  }, []);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchActesFinanciersApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        serviceGrille: serviceFilter || undefined,
      });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (error) {
      setListError(error.message || 'Impossible de charger la grille tarifaire.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, serviceFilter]);

  useEffect(() => { load(page); }, [load, page]);

  const handleExport = async (format) => {
    setExportLoading(format);
    try {
      await exportResourceApi(facturation.actes, format, {
        search: debouncedSearch || undefined,
        serviceGrille: serviceFilter || undefined,
      });
    } catch (error) {
      showError(error.message || 'Export impossible.');
    } finally {
      setExportLoading(null);
    }
  };

  const handleImport = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    setImportLoading(true);
    try {
      const result = await importGrilleTarifaireApi(file);
      showSuccess(`Import terminé : ${result.imported ?? 0} créé(s), ${result.updated ?? 0} mis à jour.`);
      const meta = await fetchActesFinanciersMetaApi();
      setServiceGrilles(Array.isArray(meta.serviceGrilles) ? meta.serviceGrilles : []);
      setPage(1);
      await load(1);
    } catch (error) {
      showError(error.message || 'Import impossible.');
    } finally {
      setImportLoading(false);
    }
  };

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={2.5}>
        <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={1.5}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Wallet size={24} color={LOTRU_PRIMARY[600]} />
            <Box>
              <Typography level="h2" sx={{ fontWeight: 700 }}>Grille tarifaire</Typography>
              <Typography level="body-md" sx={{ color: 'neutral.500' }}>
                Catalogue importé (A0, A1, A, B, C). Aucune saisie acte par acte.
              </Typography>
            </Box>
          </Stack>
          <Stack direction="row" spacing={1}>
            {canExport ? (
              <ExportButtons loading={exportLoading} onExport={handleExport} />
            ) : null}
            {canImport ? (
              <>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept=".xlsx,.xls"
                  hidden
                  onChange={handleImport}
                />
                <Button
                  startDecorator={<Upload size={16} />}
                  loading={importLoading}
                  onClick={() => fileInputRef.current?.click()}
                >
                  Importer Excel
                </Button>
              </>
            ) : null}
          </Stack>
        </Stack>

        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
            <Input
              startDecorator={<Search size={16} />}
              placeholder="Rechercher un acte…"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              sx={{ flex: 1 }}
            />
            <Select
              value={serviceFilter}
              onChange={(_, value) => setServiceFilter(value ?? '')}
              placeholder="Service"
              sx={{ minWidth: 220 }}
            >
              <Option value="">Tous les services</Option>
              {serviceGrilles.map((service) => (
                <Option key={service} value={service}>{service}</Option>
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
          <Table stickyHeader hoverRow sx={{ minWidth: 1100 }}>
            <thead>
              <tr>
                <th>Service</th>
                <th>Acte</th>
                <th style={{ textAlign: 'right' }}>A0</th>
                <th style={{ textAlign: 'right' }}>A1</th>
                <th style={{ textAlign: 'right' }}>A</th>
                <th style={{ textAlign: 'right' }}>B</th>
                <th style={{ textAlign: 'right' }}>C</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={7}><Typography level="body-sm" sx={{ p: 2 }}>Chargement…</Typography></td></tr>
              ) : items.length === 0 ? (
                <tr>
                  <td colSpan={7}>
                    <Typography level="body-sm" sx={{ p: 2, color: LOTRU_NEUTRAL[600] }}>
                      Aucun acte. Importez la grille consolidée CHHU.
                    </Typography>
                  </td>
                </tr>
              ) : items.map((item) => (
                <tr key={item.id}>
                  <td>
                    <Typography level="body-sm" sx={{ fontWeight: 600 }}>{item.serviceGrille || '—'}</Typography>
                    {item.sousCategorie ? (
                      <Typography level="body-xs" sx={{ color: 'neutral.500' }}>{item.sousCategorie}</Typography>
                    ) : null}
                  </td>
                  <td>{item.libelle}</td>
                  <td style={{ textAlign: 'right' }}>{formatCdf(item.tarifA0)}</td>
                  <td style={{ textAlign: 'right' }}>{formatCdf(item.tarifA1)}</td>
                  <td style={{ textAlign: 'right' }}>{formatCdf(item.tarifA)}</td>
                  <td style={{ textAlign: 'right' }}>{formatCdf(item.tarifB)}</td>
                  <td style={{ textAlign: 'right' }}>{formatCdf(item.tarifC)}</td>
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
          limitOptions={ACTE_PAGE_SIZE_OPTIONS}
          onPageChange={setPage}
          onLimitChange={setLimit}
        />
      </Stack>
    </Box>
  );
}
