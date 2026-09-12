import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Box, Button, Card, Chip, IconButton, Input, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { FileText, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import ConfirmModal from '../../../components/ui/ConfirmModal.jsx';
import ExportButtons from '../../../components/export/ExportButtons.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { ROUTES } from '../../../constants/routes.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import {
  APTITUDE_MOTIFS,
  APTITUDE_PAGE_SIZE_OPTIONS,
  APTITUDE_STATUT_COLORS,
  APTITUDE_STATUT_LABELS,
  APTITUDE_STATUTS,
  APTITUDE_VERDICT_LABELS,
  DEFAULT_APTITUDE_PAGE_SIZE,
} from './aptitudeConstants.js';
import {
  deleteAptitudeApi,
  exportAptitudesApi,
  fetchAptitudeFilieresApi,
  fetchAptitudeServicesApi,
  fetchAptitudesApi,
  openAptitudePdfApi,
} from './aptitudeApi.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_APTITUDE_PAGE_SIZE, total: 0, totalPages: 0 };

function formatDate(value) {
  if (!value) return '—';
  return new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value));
}

export default function AptitudesPage() {
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_CREATE);
  const canDelete = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_DELETE);
  const canExport = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_EXPORT);

  const [items, setItems] = useState([]);
  const [services, setServices] = useState([]);
  const [filieres, setFilieres] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [annee, setAnnee] = useState('');
  const [statut, setStatut] = useState('');
  const [verdict, setVerdict] = useState('');
  const [motif, setMotif] = useState('');
  const [serviceId, setServiceId] = useState('');
  const [filiereId, setFiliereId] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_APTITUDE_PAGE_SIZE);
  const [exportLoading, setExportLoading] = useState(null);
  const [pdfLoadingId, setPdfLoadingId] = useState(null);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleting, setDeleting] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  const yearOptions = useMemo(() => {
    const current = new Date().getFullYear();
    return [current, current - 1, current - 2, current - 3];
  }, []);

  useEffect(() => {
    fetchAptitudeServicesApi().then(setServices).catch(() => setServices([]));
    fetchAptitudeFilieresApi().then(setFilieres).catch(() => setFilieres([]));
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, annee, statut, verdict, motif, serviceId, filiereId, limit]);

  const filters = useMemo(() => ({
    search: debouncedSearch || undefined,
    annee: annee || undefined,
    statut: statut || undefined,
    verdict: verdict || undefined,
    motif: motif || undefined,
    serviceId: serviceId || undefined,
    filiereId: filiereId && filiereId !== 'none' ? filiereId : undefined,
    sansFiliere: filiereId === 'none' ? true : undefined,
  }), [debouncedSearch, annee, statut, verdict, motif, serviceId, filiereId]);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchAptitudesApi({ page: targetPage, limit, ...filters });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (err) {
      setListError(err.message || 'Impossible de charger les certificats.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, filters]);

  useEffect(() => { load(page); }, [load, page]);

  const handleExport = async (format) => {
    setExportLoading(format);
    try {
      await exportAptitudesApi(format, filters);
    } catch (err) {
      showError(err.message || 'Export impossible.');
    } finally {
      setExportLoading(null);
    }
  };

  const handlePdf = async (id) => {
    setPdfLoadingId(id);
    try {
      await openAptitudePdfApi(id);
    } catch (err) {
      showError(err.message || 'Impossible de générer le PDF.');
    } finally {
      setPdfLoadingId(null);
    }
  };

  const confirmDelete = async () => {
    if (!deleting) return;
    setDeleteLoading(true);
    try {
      await deleteAptitudeApi(deleting.id);
      showSuccess('Brouillon supprimé.');
      setDeleteOpen(false);
      setDeleting(null);
      load(page);
    } catch (err) {
      showError(err.message || 'Suppression impossible.');
    } finally {
      setDeleteLoading(false);
    }
  };

  return (
    <Stack spacing={2}>
      <Stack direction={{ xs: 'column', md: 'row' }} justifyContent="space-between" spacing={2}>
        <Box>
          <Typography level="h2" sx={{ fontWeight: 700 }}>Aptitude physique</Typography>
          <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
            Enregistrement et génération des certificats d’aptitude physique.
          </Typography>
        </Box>
        <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
          <Button variant="outlined" onClick={() => navigate(ROUTES.CLINIQUE.APTITUDE_STATS)}>
            Statistiques
          </Button>
          {canExport ? <ExportButtons onExport={handleExport} loading={exportLoading} /> : null}
          {canCreate ? (
            <Button
              startDecorator={<Plus size={18} />}
              onClick={() => navigate(ROUTES.CLINIQUE.APTITUDE_NEW)}
              sx={{ bgcolor: LOTRU_PRIMARY[500] }}
            >
              Nouveau certificat
            </Button>
          ) : null}
        </Stack>
      </Stack>

      <Card variant="outlined">
        <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} flexWrap="wrap" useFlexGap>
          <Input
            placeholder="Rechercher un candidat, un n°…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            startDecorator={<Search size={16} />}
            sx={{ minWidth: 240, flex: 1 }}
          />
          <Select placeholder="Année" value={annee} onChange={(_, v) => setAnnee(v ?? '')} sx={{ minWidth: 110 }}>
            <Option value="">Toutes</Option>
            {yearOptions.map((y) => <Option key={y} value={String(y)}>{y}</Option>)}
          </Select>
          <Select placeholder="Statut" value={statut} onChange={(_, v) => setStatut(v ?? '')} sx={{ minWidth: 140 }}>
            <Option value="">Tous</Option>
            {APTITUDE_STATUTS.map((s) => <Option key={s} value={s}>{APTITUDE_STATUT_LABELS[s]}</Option>)}
          </Select>
          <Select placeholder="Verdict" value={verdict} onChange={(_, v) => setVerdict(v ?? '')} sx={{ minWidth: 120 }}>
            <Option value="">Tous</Option>
            {Object.entries(APTITUDE_VERDICT_LABELS).map(([k, l]) => <Option key={k} value={k}>{l}</Option>)}
          </Select>
          <Select placeholder="Motif" value={motif} onChange={(_, v) => setMotif(v ?? '')} sx={{ minWidth: 180 }}>
            <Option value="">Tous</Option>
            {APTITUDE_MOTIFS.map((m) => <Option key={m.value} value={m.value}>{m.label}</Option>)}
          </Select>
          <Select placeholder="Service" value={serviceId} onChange={(_, v) => setServiceId(v ?? '')} sx={{ minWidth: 180 }}>
            <Option value="">Tous</Option>
            {services.map((s) => <Option key={s.id} value={String(s.id)}>{s.libelle}</Option>)}
          </Select>
          <Select placeholder="Filière" value={filiereId} onChange={(_, v) => setFiliereId(v ?? '')} sx={{ minWidth: 200 }}>
            <Option value="">Toutes</Option>
            <Option value="none">Non renseignée</Option>
            {filieres.map((f) => <Option key={f.id} value={String(f.id)}>{f.code} — {f.libelle}</Option>)}
          </Select>
        </Stack>
      </Card>

      {listError ? (
        <Typography color="danger" level="body-sm">{listError}</Typography>
      ) : null}

      <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto' }}>
        <Table stickyHeader>
          <thead>
            <tr>
              <th>N°</th>
              <th>Candidat</th>
              <th>Service</th>
              <th>Motif</th>
              <th>Filière</th>
              <th>Verdict</th>
              <th>Statut</th>
              <th>Signé le</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr><td colSpan={9} style={{ color: LOTRU_NEUTRAL[500] }}>Chargement…</td></tr>
            ) : items.length === 0 ? (
              <tr><td colSpan={9} style={{ color: LOTRU_NEUTRAL[500] }}>Aucun certificat.</td></tr>
            ) : items.map((item) => (
              <tr key={item.id}>
                <td>{item.numero || '—'}</td>
                <td>
                  <Typography level="title-sm">{item.fullName}</Typography>
                  <Typography level="body-xs" sx={{ color: 'neutral.500' }}>{item.sexe}</Typography>
                </td>
                <td>{item.service?.libelle || '—'}</td>
                <td>{item.motifLabel || '—'}</td>
                <td>{item.filiere?.libelle || (item.motif === 'ADMISSION_UKV' ? 'Non renseignée' : '—')}</td>
                <td>
                  {item.verdict ? (
                    <Chip size="sm" color={item.verdict === 'APTE' ? 'success' : 'danger'} variant="soft">
                      {item.verdict}
                    </Chip>
                  ) : '—'}
                </td>
                <td>
                  <Chip size="sm" color={APTITUDE_STATUT_COLORS[item.statut] || 'neutral'} variant="soft">
                    {APTITUDE_STATUT_LABELS[item.statut] || item.statut}
                    {item.expired ? ' · expiré' : ''}
                  </Chip>
                </td>
                <td>{formatDate(item.signeAt)}</td>
                <td>
                  <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                    <IconButton
                      size="sm"
                      variant="plain"
                      onClick={() => navigate(ROUTES.CLINIQUE.APTITUDE_DETAIL.replace(':id', item.id))}
                    >
                      <Pencil size={16} />
                    </IconButton>
                    {canExport && item.statut !== 'BROUILLON' ? (
                      <IconButton
                        size="sm"
                        variant="plain"
                        loading={pdfLoadingId === item.id}
                        disabled={Boolean(pdfLoadingId)}
                        title="Générer le PDF"
                        onClick={() => handlePdf(item.id)}
                      >
                        <FileText size={16} />
                      </IconButton>
                    ) : null}
                    {canDelete && item.statut === 'BROUILLON' ? (
                      <IconButton size="sm" variant="plain" color="danger" onClick={() => { setDeleting(item); setDeleteOpen(true); }}>
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
        limitOptions={APTITUDE_PAGE_SIZE_OPTIONS}
        onPageChange={setPage}
        onLimitChange={setLimit}
        loading={loading}
      />

      <ConfirmModal
        open={deleteOpen}
        title="Supprimer le brouillon"
        message={deleting ? `Supprimer le certificat de ${deleting.fullName} ?` : ''}
        confirmLabel="Supprimer"
        loading={deleteLoading}
        onClose={() => { if (!deleteLoading) { setDeleteOpen(false); setDeleting(null); } }}
        onConfirm={confirmDelete}
      />
    </Stack>
  );
}
