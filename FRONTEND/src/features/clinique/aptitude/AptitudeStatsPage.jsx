import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Box, Button, Card, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { BarChart3, GraduationCap, Printer, ShieldCheck, ShieldX } from 'lucide-react';
import ExportButtons from '../../../components/export/ExportButtons.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { ROUTES } from '../../../constants/routes.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import {
  APTITUDE_MOTIFS,
  APTITUDE_STATUT_LABELS,
  APTITUDE_STATUTS,
  APTITUDE_VERDICT_LABELS,
} from './aptitudeConstants.js';
import {
  exportAptitudeStatsApi,
  exportAptitudesApi,
  fetchAptitudeStatsApi,
} from './aptitudeApi.js';

const EMPTY_STATS = {
  totals: { total: 0, apte: 0, inapte: 0, brouillon: 0, signe: 0, annule: 0 },
  byFiliere: [],
};

function KpiCard({ icon, label, value, color = 'neutral' }) {
  return (
    <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2, flex: 1, minWidth: 150 }}>
      <Stack direction="row" spacing={1.5} alignItems="center">
        <Box sx={{ color: `${color}.600` }}>{icon}</Box>
        <Box>
          <Typography level="body-xs" sx={{ color: 'neutral.500', fontWeight: 600, textTransform: 'uppercase' }}>
            {label}
          </Typography>
          <Typography level="title-lg" color={color} sx={{ fontWeight: 800 }}>{value}</Typography>
        </Box>
      </Stack>
    </Card>
  );
}

function rowKey(row) {
  return row.filiereId == null ? 'none' : String(row.filiereId);
}

export default function AptitudeStatsPage() {
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showError } = useToast();
  const canExport = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_EXPORT);

  const yearOptions = useMemo(() => {
    const current = new Date().getFullYear();
    return [current, current - 1, current - 2, current - 3];
  }, []);

  const [annee, setAnnee] = useState(String(new Date().getFullYear()));
  const [statut, setStatut] = useState('');
  const [verdict, setVerdict] = useState('');
  const [motif, setMotif] = useState('ADMISSION_UKV');
  const [stats, setStats] = useState(EMPTY_STATS);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [selectedKey, setSelectedKey] = useState(null);
  const [exportLoading, setExportLoading] = useState(null);
  const [listExportLoading, setListExportLoading] = useState(null);

  const filters = useMemo(() => ({
    annee: annee || undefined,
    statut: statut || undefined,
    verdict: verdict || undefined,
    motif: motif || undefined,
  }), [annee, statut, verdict, motif]);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const result = await fetchAptitudeStatsApi(filters);
      setStats(result);
    } catch (err) {
      setError(err.message || 'Impossible de charger les statistiques.');
      setStats(EMPTY_STATS);
    } finally {
      setLoading(false);
    }
  }, [filters]);

  useEffect(() => { load(); }, [load]);
  useEffect(() => { setSelectedKey(null); }, [filters]);

  const selectedRow = stats.byFiliere.find((row) => rowKey(row) === selectedKey) ?? null;

  const handleExportStats = async (format) => {
    setExportLoading(format);
    try {
      await exportAptitudeStatsApi(format, filters);
    } catch (err) {
      showError(err.message || 'Export du rapport impossible.');
    } finally {
      setExportLoading(null);
    }
  };

  const handleExportList = async (format) => {
    if (!selectedRow) return;
    setListExportLoading(format);
    try {
      await exportAptitudesApi(format, {
        ...filters,
        filiereId: selectedRow.filiereId ?? undefined,
        sansFiliere: selectedRow.filiereId == null ? true : undefined,
      });
    } catch (err) {
      showError(err.message || 'Export de la liste impossible.');
    } finally {
      setListExportLoading(null);
    }
  };

  return (
    <Stack spacing={2}>
      <Stack direction={{ xs: 'column', md: 'row' }} justifyContent="space-between" spacing={2}>
        <Box>
          <Typography level="h2" sx={{ fontWeight: 700 }}>Statistique Aptitude Physique</Typography>
          <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
            Effectifs par filière pour les certificats d’admission UKV.
          </Typography>
        </Box>
        <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
          {canExport ? <ExportButtons onExport={handleExportStats} loading={exportLoading} /> : null}
          <Button variant="outlined" onClick={() => navigate(ROUTES.CLINIQUE.APTITUDES)}>
            Voir les certificats
          </Button>
        </Stack>
      </Stack>

      <Card variant="outlined">
        <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} flexWrap="wrap" useFlexGap>
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
          <Select placeholder="Motif" value={motif} onChange={(_, v) => setMotif(v ?? '')} sx={{ minWidth: 220 }}>
            <Option value="">Tous</Option>
            {APTITUDE_MOTIFS.map((m) => <Option key={m.value} value={m.value}>{m.label}</Option>)}
          </Select>
        </Stack>
      </Card>

      <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
        <KpiCard icon={<BarChart3 size={22} />} label="Total" value={stats.totals.total} />
        <KpiCard icon={<ShieldCheck size={22} />} label="APTE" value={stats.totals.apte} color="success" />
        <KpiCard icon={<ShieldX size={22} />} label="INAPTE" value={stats.totals.inapte} color="danger" />
        <KpiCard icon={<GraduationCap size={22} />} label="Filières" value={stats.byFiliere.filter((r) => r.filiereId != null).length} />
      </Stack>

      {error ? <Typography color="danger" level="body-sm">{error}</Typography> : null}

      {selectedRow && canExport ? (
        <Card variant="soft" color="primary">
          <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" spacing={1} alignItems="center">
            <Typography level="body-sm">
              Liste sélectionnée : <strong>{selectedRow.libelle}</strong> ({selectedRow.total} certificat{selectedRow.total > 1 ? 's' : ''})
            </Typography>
            <Button
              size="sm"
              startDecorator={<Printer size={16} />}
              loading={listExportLoading === 'pdf'}
              onClick={() => handleExportList('pdf')}
              sx={{ bgcolor: LOTRU_PRIMARY[500] }}
            >
              Imprimer la liste
            </Button>
          </Stack>
        </Card>
      ) : (
        <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
          Cliquez une filière pour imprimer sa liste.
        </Typography>
      )}

      <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto' }}>
        <Table stickyHeader hoverRow>
          <thead>
            <tr>
              <th>Filière</th>
              <th>Code</th>
              <th>Total</th>
              <th>APTE</th>
              <th>INAPTE</th>
              <th>Brouillon</th>
              <th>Signé</th>
              <th>Annulé</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr><td colSpan={8} style={{ color: LOTRU_NEUTRAL[500] }}>Chargement…</td></tr>
            ) : stats.byFiliere.length === 0 ? (
              <tr><td colSpan={8} style={{ color: LOTRU_NEUTRAL[500] }}>Aucune filière enregistrée.</td></tr>
            ) : stats.byFiliere.map((row) => {
              const key = rowKey(row);
              const selected = key === selectedKey;
              return (
                <tr
                  key={key}
                  onClick={() => setSelectedKey(selected ? null : key)}
                  style={{
                    cursor: 'pointer',
                    background: selected ? 'rgba(11, 107, 203, 0.08)' : undefined,
                  }}
                >
                  <td>
                    <Typography level="title-sm">{row.libelle}</Typography>
                  </td>
                  <td>{row.code || '—'}</td>
                  <td><strong>{row.total}</strong></td>
                  <td>{row.apte}</td>
                  <td>{row.inapte}</td>
                  <td>{row.brouillon}</td>
                  <td>{row.signe}</td>
                  <td>{row.annule}</td>
                </tr>
              );
            })}
          </tbody>
        </Table>
      </Sheet>
    </Stack>
  );
}
