import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Box,
  Button,
  Card,
  Checkbox,
  FormControl,
  FormLabel,
  Grid,
  Input,
  Sheet,
  Stack,
  Table,
  Typography,
} from '@mui/joy';
import { Save } from 'lucide-react';
import {
  CartesianGrid,
  Legend,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import { LOTRU_NEUTRAL } from '../../../theme/lotruPalette.js';
import { getRecordLockReason } from '../consultations/consultationConstants.js';
import {
  addConsultationVitalsPriseApi,
  fetchConsultationVitalsApi,
} from '../consultations/consultationsApi.js';
import {
  buildChartRows,
  CHART_COLORS,
  collectMesures,
  formatDateTime,
  groupPrises,
  latestValueBySigne,
} from './vitalsPriseUtils.js';

const PRISE_PAGE_SIZE_OPTIONS = [10, 25, 50];

export default function VitalsFicheForm({ consultation }) {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canUpdate = hasPermission(PERMISSIONS.CLINIQUE.CONSULTATION_UPDATE);
  const locked = Boolean(
    consultation?.isClosed
    || consultation?.isEditable === false
    || consultation?.recordWritable === false
    || getRecordLockReason(consultation),
  );
  const readOnly = locked || !canUpdate;

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [vitals, setVitals] = useState({
    triageMesures: [],
    consultationMesures: [],
    signesVitaux: [],
  });
  const [formValues, setFormValues] = useState({});
  const [selectedSigneIds, setSelectedSigneIds] = useState([]);
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(10);

  const load = useCallback(async () => {
    if (!consultation?.id) return;
    setLoading(true);
    try {
      const data = await fetchConsultationVitalsApi(consultation.id);
      const next = {
        triageMesures: Array.isArray(data?.triageMesures) ? data.triageMesures : [],
        consultationMesures: Array.isArray(data?.consultationMesures) ? data.consultationMesures : [],
        signesVitaux: Array.isArray(data?.signesVitaux) ? data.signesVitaux : [],
      };
      setVitals(next);
      setFormValues(latestValueBySigne(collectMesures(next)));
    } catch (err) {
      showError(err.message || 'Impossible de charger les constantes.');
    } finally {
      setLoading(false);
    }
  }, [consultation?.id]);

  useEffect(() => {
    load();
  }, [load]);

  const signes = vitals.signesVitaux;
  const prises = useMemo(
    () => groupPrises(collectMesures(vitals)),
    [vitals],
  );
  const totalPages = Math.max(1, Math.ceil(prises.length / limit));
  const currentPage = Math.min(page, totalPages);
  const pagedPrises = useMemo(
    () => prises.slice((currentPage - 1) * limit, currentPage * limit),
    [prises, currentPage, limit],
  );
  const chartRows = useMemo(
    () => buildChartRows(prises, selectedSigneIds),
    [prises, selectedSigneIds],
  );

  const toggleSigne = (signeId) => {
    setSelectedSigneIds((current) => (
      current.includes(signeId)
        ? current.filter((id) => id !== signeId)
        : [...current, signeId]
    ));
  };

  const handleSave = async () => {
    if (readOnly) return;
    const mesures = signes
      .map((signe) => ({
        signeVitalId: Number(signe.id),
        valeur: String(formValues[String(signe.id)] ?? '').trim(),
      }))
      .filter((item) => item.valeur !== '');

    if (mesures.length === 0) {
      showError('Saisissez au moins une valeur.');
      return;
    }

    setSaving(true);
    try {
      const data = await addConsultationVitalsPriseApi(consultation.id, { mesures });
      const next = {
        triageMesures: Array.isArray(data?.triageMesures) ? data.triageMesures : [],
        consultationMesures: Array.isArray(data?.consultationMesures) ? data.consultationMesures : [],
        signesVitaux: Array.isArray(data?.signesVitaux) ? data.signesVitaux : signes,
      };
      setVitals(next);
      setFormValues(latestValueBySigne(collectMesures(next)));
      setPage(1);
      showSuccess('Prise de constantes enregistrée.');
    } catch (err) {
      showError(err.message || 'Enregistrement impossible.');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <Typography level="body-sm">Chargement des constantes…</Typography>;
  }

  return (
    <Stack spacing={2.5}>
      {getRecordLockReason(consultation) ? (
        <Typography level="body-sm" color="warning" sx={{ bgcolor: 'warning.50', p: 1.5, borderRadius: 'md' }}>
          {getRecordLockReason(consultation)}
        </Typography>
      ) : null}

      <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
        <Typography level="title-sm" sx={{ fontWeight: 700, mb: 0.5 }}>Nouvelle prise</Typography>
        <Typography level="body-xs" sx={{ color: LOTRU_NEUTRAL[500], mb: 1.5 }}>
          Formulaire prérempli avec les dernières valeurs. Modifiez seulement ce qui a changé.
        </Typography>
        {signes.length === 0 ? (
          <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600] }}>
            Aucun signe vital actif dans le catalogue.
          </Typography>
        ) : (
          <Grid container spacing={1.5}>
            {signes.map((signe) => (
              <Grid key={signe.id} xs={12} sm={6} md={4}>
                <FormControl size="sm">
                  <FormLabel>{signe.libelle}</FormLabel>
                  <Input
                    value={formValues[String(signe.id)] ?? ''}
                    onChange={(event) => setFormValues((current) => ({
                      ...current,
                      [String(signe.id)]: event.target.value,
                    }))}
                    endDecorator={signe.unite || undefined}
                    readOnly={readOnly}
                    placeholder={signe.unite ? `En ${signe.unite}` : 'Valeur'}
                  />
                </FormControl>
              </Grid>
            ))}
          </Grid>
        )}
        {!readOnly && signes.length > 0 ? (
          <Box sx={{ display: 'flex', justifyContent: 'flex-end', mt: 2 }}>
            <Button loading={saving} startDecorator={<Save size={16} />} onClick={handleSave}>
              Enregistrer la prise
            </Button>
          </Box>
        ) : null}
      </Card>

      <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
        <Typography level="title-sm" sx={{ fontWeight: 700, mb: 1 }}>Prises du séjour</Typography>
        {prises.length === 0 ? (
          <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600] }}>
            Aucune prise enregistrée.
          </Typography>
        ) : (
          <Sheet variant="outlined" sx={{ borderRadius: 'md', overflow: 'auto' }}>
            <Table stickyHeader size="sm" sx={{ minWidth: 640 }}>
              <thead>
                <tr>
                  <th>Heure</th>
                  {signes.map((signe) => (
                    <th key={signe.id}>{signe.libelle}</th>
                  ))}
                  <th>Par</th>
                </tr>
              </thead>
              <tbody>
                {pagedPrises.map((prise) => (
                  <tr key={prise.key}>
                    <td>{formatDateTime(prise.measuredAt)}</td>
                    {signes.map((signe) => (
                      <td key={signe.id}>
                        {prise.values[String(signe.id)]?.valeur ?? '—'}
                      </td>
                    ))}
                    <td>{prise.measuredBy}</td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </Sheet>
        )}
        {prises.length > 0 ? (
          <Box sx={{ mt: 1.5 }}>
            <AppPagination
              page={currentPage}
              totalPages={prises.length === 0 ? 0 : totalPages}
              total={prises.length}
              limit={limit}
              limitOptions={PRISE_PAGE_SIZE_OPTIONS}
              onPageChange={setPage}
              onLimitChange={(value) => {
                setLimit(value);
                setPage(1);
              }}
            />
          </Box>
        ) : null}
      </Card>

      <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
        <Typography level="title-sm" sx={{ fontWeight: 700, mb: 0.5 }}>Graphique</Typography>
        <Typography level="body-xs" sx={{ color: LOTRU_NEUTRAL[500], mb: 1.5 }}>
          Cochez les signes vitaux à afficher.
        </Typography>
        <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap sx={{ mb: 2 }}>
          {signes.map((signe) => (
            <Checkbox
              key={signe.id}
              size="sm"
              label={signe.unite ? `${signe.libelle} (${signe.unite})` : signe.libelle}
              checked={selectedSigneIds.includes(String(signe.id))}
              onChange={() => toggleSigne(String(signe.id))}
            />
          ))}
        </Stack>
        {selectedSigneIds.length === 0 ? (
          <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600] }}>
            Aucun signe sélectionné.
          </Typography>
        ) : prises.length < 2 ? (
          <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600] }}>
            Le graphique apparaît après au moins deux prises.
          </Typography>
        ) : (
          <Box sx={{ width: '100%', height: 320 }}>
            <ResponsiveContainer width="100%" height="100%">
              <LineChart data={chartRows}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E5E7EB" />
                <XAxis dataKey="time" tick={{ fill: '#6B7280', fontSize: 12 }} />
                <YAxis tick={{ fill: '#6B7280', fontSize: 12 }} />
                <Tooltip />
                <Legend />
                {selectedSigneIds.map((signeId, index) => {
                  const signe = signes.find((item) => String(item.id) === signeId);
                  return (
                    <Line
                      key={signeId}
                      type="monotone"
                      dataKey={signeId}
                      name={signe?.libelle ?? signeId}
                      stroke={CHART_COLORS[index % CHART_COLORS.length]}
                      dot
                      connectNulls
                    />
                  );
                })}
              </LineChart>
            </ResponsiveContainer>
          </Box>
        )}
      </Card>
    </Stack>
  );
}
