import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Box, Button, Card, Checkbox, Chip, IconButton, Input, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { FileText, Pencil, Plus, Printer, Search, Trash2, Upload } from 'lucide-react';
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
  downloadAptitudeImportTemplateApi,
  exportAptitudesApi,
  fetchAptitudeFilieresApi,
  fetchAptitudeOrganisationsApi,
  fetchAptitudeServicesApi,
  fetchAptitudesApi,
  importAptitudeEtudiantsApi,
  markAptitudesPrintedApi,
  openAptitudeBatchPdfApi,
  openAptitudePdfApi,
} from './aptitudeApi.js';
import { aptitudeFilterSx } from './aptitudeUi.js';

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
  const canImport = canCreate && hasPermission(PERMISSIONS.PATIENT.PATIENT_CREATE);

  const [items, setItems] = useState([]);
  const [services, setServices] = useState([]);
  const [filieres, setFilieres] = useState([]);
  const [organisations, setOrganisations] = useState([]);
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
  const [imprime, setImprime] = useState('');
  const [selectedIds, setSelectedIds] = useState([]);
  const [batchPdfLoading, setBatchPdfLoading] = useState(false);
  const [printConfirmOpen, setPrintConfirmOpen] = useState(false);
  const [printConfirmIds, setPrintConfirmIds] = useState([]);
  const [printConfirmLoading, setPrintConfirmLoading] = useState(false);
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_APTITUDE_PAGE_SIZE);
  const [exportLoading, setExportLoading] = useState(null);
  const [pdfLoadingId, setPdfLoadingId] = useState(null);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleting, setDeleting] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [importOpen, setImportOpen] = useState(false);
  const [importLoading, setImportLoading] = useState(false);
  const [importError, setImportError] = useState('');
  const [importResult, setImportResult] = useState(null);

  const yearOptions = useMemo(() => {
    const current = new Date().getFullYear();
    return [current, current + 1, current - 1, current - 2, current - 3];
  }, []);

  useEffect(() => {
    fetchAptitudeServicesApi().then(setServices).catch(() => setServices([]));
    fetchAptitudeFilieresApi().then(setFilieres).catch(() => setFilieres([]));
    fetchAptitudeOrganisationsApi().then(setOrganisations).catch(() => setOrganisations([]));
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    setPage(1);
    setSelectedIds([]);
  }, [debouncedSearch, annee, statut, verdict, motif, serviceId, filiereId, imprime, limit]);

  const filters = useMemo(() => ({
    search: debouncedSearch || undefined,
    annee: annee || undefined,
    statut: statut || undefined,
    verdict: verdict || undefined,
    motif: motif || undefined,
    serviceId: serviceId || undefined,
    filiereId: filiereId && filiereId !== 'none' ? filiereId : undefined,
    sansFiliere: filiereId === 'none' ? true : undefined,
    imprime: imprime || undefined,
  }), [debouncedSearch, annee, statut, verdict, motif, serviceId, filiereId, imprime]);

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
      setPrintConfirmIds([id]);
      setPrintConfirmOpen(true);
    } catch (err) {
      showError(err.message || 'Impossible de générer le PDF.');
    } finally {
      setPdfLoadingId(null);
    }
  };

  const printablePageIds = items.filter((item) => item.statut !== 'BROUILLON').map((item) => item.id);
  const allPageSelected = printablePageIds.length > 0 && printablePageIds.every((id) => selectedIds.includes(id));

  const toggleOne = (id) => {
    setSelectedIds((current) => (current.includes(id) ? current.filter((value) => value !== id) : [...current, id]));
  };

  const togglePage = () => {
    setSelectedIds((current) => {
      if (allPageSelected) {
        return current.filter((id) => !printablePageIds.includes(id));
      }
      return [...new Set([...current, ...printablePageIds])];
    });
  };

  const handleBatchPdf = async () => {
    if (!canExport || !selectedIds.length) return;
    setBatchPdfLoading(true);
    try {
      await openAptitudeBatchPdfApi(selectedIds);
      setPrintConfirmIds(selectedIds);
      setPrintConfirmOpen(true);
    } catch (err) {
      showError(err.message || 'Impossible de générer le PDF groupé.');
    } finally {
      setBatchPdfLoading(false);
    }
  };

  const confirmPrinted = async () => {
    if (!printConfirmIds.length) return;
    setPrintConfirmLoading(true);
    try {
      const result = await markAptitudesPrintedApi(printConfirmIds);
      showSuccess(`${result?.updated ?? printConfirmIds.length} certificat(s) marqué(s) comme imprimé(s).`);
      setPrintConfirmOpen(false);
      setPrintConfirmIds([]);
      setSelectedIds([]);
      load(page);
    } catch (err) {
      showError(err.message || 'Impossible de marquer l’impression.');
    } finally {
      setPrintConfirmLoading(false);
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

  const handleImport = async (payload) => {
    setImportLoading(true);
    setImportError('');
    try {
      const result = await importAptitudeEtudiantsApi(payload);
      setImportResult(result);
      const errors = Array.isArray(result?.errors) ? result.errors.length : 0;
      showSuccess(
        `Import : ${result?.createdDpis ?? 0} DPI, ${result?.createdPatients ?? 0} nouveau(x)${errors ? `, ${errors} ligne(s) en erreur` : ''}.`,
      );
      load(page);
    } catch (err) {
      setImportError(err.message || 'Import impossible.');
    } finally {
      setImportLoading(false);
    }
  };

  const handleDownloadTemplate = async () => {
    try {
      await downloadAptitudeImportTemplateApi();
    } catch (err) {
      showError(err.message || 'Impossible de télécharger le modèle.');
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
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} flexWrap="wrap" useFlexGap sx={{ width: { xs: '100%', md: 'auto' } }}>
          <Button variant="outlined" onClick={() => navigate(ROUTES.CLINIQUE.APTITUDE_STATS)} sx={{ width: { xs: '100%', sm: 'auto' } }}>
            Statistiques
          </Button>
          {canExport ? <ExportButtons onExport={handleExport} loading={exportLoading} /> : null}
          <Button
            variant="outlined"
            startDecorator={<Printer size={18} />}
            disabled={!canExport || !selectedIds.length}
            loading={batchPdfLoading}
            title={!canExport ? 'Permission requise pour imprimer' : undefined}
            onClick={handleBatchPdf}
            sx={{ width: { xs: '100%', sm: 'auto' } }}
          >
            Imprimer la sélection{selectedIds.length ? ` (${selectedIds.length})` : ''}
          </Button>
          <Button
            variant="outlined"
            startDecorator={<Upload size={18} />}
            disabled={!canImport}
            title={!canImport ? 'Permission requise pour importer' : undefined}
            onClick={() => { setImportError(''); setImportResult(null); setImportOpen(true); }}
            sx={{ width: { xs: '100%', sm: 'auto' } }}
          >
            Importer des étudiants
          </Button>
          <Button
            startDecorator={<Plus size={18} />}
            disabled={!canCreate}
            title={!canCreate ? 'Permission requise pour créer' : undefined}
            onClick={() => navigate(ROUTES.CLINIQUE.APTITUDE_NEW)}
            sx={{ bgcolor: LOTRU_PRIMARY[500], width: { xs: '100%', sm: 'auto' } }}
          >
            Nouveau certificat
          </Button>
        </Stack>
      </Stack>

      <Card variant="outlined">
        <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} flexWrap="wrap" useFlexGap>
          <Input
            placeholder="Rechercher un candidat, un n°…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            startDecorator={<Search size={16} />}
            sx={{ ...aptitudeFilterSx, flex: { xs: '1 1 100%', md: 1 } }}
          />
          <Select placeholder="Année" value={annee} onChange={(_, v) => setAnnee(v ?? '')} sx={aptitudeFilterSx}>
            <Option value="">Toutes</Option>
            {yearOptions.map((y) => <Option key={y} value={String(y)}>{y}</Option>)}
          </Select>
          <Select placeholder="Statut" value={statut} onChange={(_, v) => setStatut(v ?? '')} sx={aptitudeFilterSx}>
            <Option value="">Tous</Option>
            {APTITUDE_STATUTS.map((s) => <Option key={s} value={s}>{APTITUDE_STATUT_LABELS[s]}</Option>)}
          </Select>
          <Select placeholder="Verdict" value={verdict} onChange={(_, v) => setVerdict(v ?? '')} sx={aptitudeFilterSx}>
            <Option value="">Tous</Option>
            {Object.entries(APTITUDE_VERDICT_LABELS).map(([k, l]) => <Option key={k} value={k}>{l}</Option>)}
          </Select>
          <Select placeholder="Motif" value={motif} onChange={(_, v) => setMotif(v ?? '')} sx={aptitudeFilterSx}>
            <Option value="">Tous</Option>
            {APTITUDE_MOTIFS.map((m) => <Option key={m.value} value={m.value}>{m.label}</Option>)}
          </Select>
          <Select placeholder="Service" value={serviceId} onChange={(_, v) => setServiceId(v ?? '')} sx={aptitudeFilterSx}>
            <Option value="">Tous</Option>
            {services.map((s) => <Option key={s.id} value={String(s.id)}>{s.libelle}</Option>)}
          </Select>
          <Select placeholder="Filière" value={filiereId} onChange={(_, v) => setFiliereId(v ?? '')} sx={aptitudeFilterSx}>
            <Option value="">Toutes</Option>
            <Option value="none">Non renseignée</Option>
            {filieres.map((f) => <Option key={f.id} value={String(f.id)}>{f.code} — {f.libelle}</Option>)}
          </Select>
          <Select placeholder="Impression" value={imprime} onChange={(_, v) => setImprime(v ?? '')} sx={aptitudeFilterSx}>
            <Option value="">Tous</Option>
            <Option value="oui">Déjà imprimés</Option>
            <Option value="non">Non imprimés</Option>
          </Select>
        </Stack>
      </Card>

      {listError ? (
        <Typography color="danger" level="body-sm">{listError}</Typography>
      ) : null}

      <Stack spacing={1.25} sx={{ display: { xs: 'flex', md: 'none' } }}>
        {loading ? (
          <Typography level="body-sm" sx={{ color: 'neutral.500' }}>Chargement…</Typography>
        ) : items.length === 0 ? (
          <Typography level="body-sm" sx={{ color: 'neutral.500' }}>Aucun certificat.</Typography>
        ) : items.map((item) => (
          <Card key={item.id} variant="outlined" sx={{ p: 1.5 }}>
            <Stack spacing={1.25}>
              <Stack direction="row" justifyContent="space-between" alignItems="flex-start" spacing={1}>
                <Stack direction="row" spacing={1} alignItems="flex-start" sx={{ minWidth: 0 }}>
                  {canExport ? (
                    <Checkbox
                      checked={selectedIds.includes(item.id)}
                      disabled={item.statut === 'BROUILLON'}
                      onChange={() => toggleOne(item.id)}
                      sx={{ mt: 0.4 }}
                    />
                  ) : null}
                  <Box sx={{ minWidth: 0 }}>
                    <Typography level="title-sm" sx={{ fontWeight: 700 }}>{item.fullName}</Typography>
                    <Typography level="body-xs" sx={{ color: 'neutral.500' }}>
                      {item.numero || 'Sans numéro'} · {item.sexe || '—'}
                    </Typography>
                  </Box>
                </Stack>
                <Chip size="sm" color={APTITUDE_STATUT_COLORS[item.statut] || 'neutral'} variant="soft">
                  {APTITUDE_STATUT_LABELS[item.statut] || item.statut}
                </Chip>
              </Stack>
              <Typography level="body-xs" sx={{ color: 'neutral.600' }}>
                {[item.service?.libelle, item.motifLabel, item.filiere?.libelle].filter(Boolean).join(' · ') || '—'}
              </Typography>
              <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
                {item.verdict ? (
                  <Chip size="sm" color={item.verdict === 'APTE' ? 'success' : 'danger'} variant="soft">{item.verdict}</Chip>
                ) : null}
                {item.statut !== 'BROUILLON' ? (
                  <Chip size="sm" color={item.imprime ? 'success' : 'warning'} variant="soft">
                    {item.imprime ? 'Imprimé' : 'Non imprimé'}
                  </Chip>
                ) : null}
                {item.signeAt ? (
                  <Typography level="body-xs" sx={{ color: 'neutral.500', alignSelf: 'center' }}>
                    Signé le {formatDate(item.signeAt)}
                  </Typography>
                ) : null}
              </Stack>
              <Stack direction="row" spacing={1} justifyContent="flex-end">
                <IconButton
                  size="md"
                  variant="soft"
                  onClick={() => navigate(ROUTES.CLINIQUE.APTITUDE_DETAIL.replace(':id', item.id))}
                >
                  <Pencil size={18} />
                </IconButton>
                {item.statut !== 'BROUILLON' ? (
                  <IconButton
                    size="md"
                    variant="soft"
                    loading={pdfLoadingId === item.id}
                    disabled={!canExport || Boolean(pdfLoadingId)}
                    title={canExport ? 'Générer le PDF' : 'Permission requise pour imprimer'}
                    onClick={() => { if (canExport) handlePdf(item.id); }}
                  >
                    <FileText size={18} />
                  </IconButton>
                ) : null}
                {item.statut === 'BROUILLON' ? (
                  <IconButton
                    size="md"
                    variant="soft"
                    color="danger"
                    disabled={!canDelete}
                    title={canDelete ? 'Supprimer' : 'Permission requise pour supprimer'}
                    onClick={() => { if (canDelete) { setDeleting(item); setDeleteOpen(true); } }}
                  >
                    <Trash2 size={18} />
                  </IconButton>
                ) : null}
              </Stack>
            </Stack>
          </Card>
        ))}
      </Stack>

      <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto', display: { xs: 'none', md: 'block' }, WebkitOverflowScrolling: 'touch' }}>
        <Table stickyHeader sx={{ minWidth: 960 }}>
          <thead>
            <tr>
              {canExport ? (
                <th style={{ width: 40 }}>
                  <Checkbox
                    checked={allPageSelected}
                    indeterminate={selectedIds.some((id) => printablePageIds.includes(id)) && !allPageSelected}
                    disabled={!printablePageIds.length}
                    onChange={togglePage}
                  />
                </th>
              ) : null}
              <th>N°</th>
              <th>Candidat</th>
              <th>Service</th>
              <th>Motif</th>
              <th>Filière</th>
              <th>Verdict</th>
              <th>Statut</th>
              <th>Impression</th>
              <th>Signé le</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr><td colSpan={canExport ? 11 : 10} style={{ color: LOTRU_NEUTRAL[500] }}>Chargement…</td></tr>
            ) : items.length === 0 ? (
              <tr><td colSpan={canExport ? 11 : 10} style={{ color: LOTRU_NEUTRAL[500] }}>Aucun certificat.</td></tr>
            ) : items.map((item) => (
              <tr key={item.id}>
                {canExport ? (
                  <td>
                    <Checkbox
                      checked={selectedIds.includes(item.id)}
                      disabled={item.statut === 'BROUILLON'}
                      onChange={() => toggleOne(item.id)}
                    />
                  </td>
                ) : null}
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
                <td>
                  {item.statut === 'BROUILLON' ? '—' : (
                    <Chip size="sm" color={item.imprime ? 'success' : 'warning'} variant="soft">
                      {item.imprime ? 'Imprimé' : 'Non imprimé'}
                    </Chip>
                  )}
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
                    {item.statut !== 'BROUILLON' ? (
                      <IconButton
                        size="sm"
                        variant="plain"
                        loading={pdfLoadingId === item.id}
                        disabled={!canExport || Boolean(pdfLoadingId)}
                        title={canExport ? 'Générer le PDF' : 'Permission requise pour imprimer'}
                        onClick={() => { if (canExport) handlePdf(item.id); }}
                      >
                        <FileText size={16} />
                      </IconButton>
                    ) : null}
                    {item.statut === 'BROUILLON' ? (
                      <IconButton
                        size="sm"
                        variant="plain"
                        color="danger"
                        disabled={!canDelete}
                        title={canDelete ? 'Supprimer' : 'Permission requise pour supprimer'}
                        onClick={() => { if (canDelete) { setDeleting(item); setDeleteOpen(true); } }}
                      >
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

      <AptitudeImportModal
        open={importOpen}
        organisations={organisations}
        yearOptions={yearOptions}
        loading={importLoading}
        error={importError}
        result={importResult}
        onClose={() => { if (!importLoading) setImportOpen(false); }}
        onDownloadTemplate={handleDownloadTemplate}
        onImport={handleImport}
      />

      <ConfirmModal
        open={printConfirmOpen}
        title="Confirmer l’impression"
        message={printConfirmIds.length > 1
          ? `Marquer ces ${printConfirmIds.length} certificats comme déjà imprimés ?`
          : 'Marquer ce certificat comme déjà imprimé ?'}
        confirmLabel="Oui, déjà imprimé"
        loading={printConfirmLoading}
        onClose={() => { if (!printConfirmLoading) { setPrintConfirmOpen(false); setPrintConfirmIds([]); } }}
        onConfirm={confirmPrinted}
      />
    </Stack>
  );
}
