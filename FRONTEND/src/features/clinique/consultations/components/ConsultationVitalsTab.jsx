import { useCallback, useEffect, useState } from 'react';
import {
  Button,
  Card,
  Chip,
  FormControl,
  FormLabel,
  Input,
  Option,
  Select,
  Sheet,
  Stack,
  Table,
  Typography,
} from '@mui/joy';
import { Activity, Plus } from 'lucide-react';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../../theme/lotruPalette.js';
import { addConsultationVitalApi, fetchConsultationVitalsApi } from '../consultationsApi.js';

function formatDateTime(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('fr-FR');
}

function formatMesureValue(mesure) {
  const unit = mesure.unite ? ` ${mesure.unite}` : '';
  return `${mesure.valeur ?? '—'}${unit}`;
}

function sourceLabel(source) {
  if (source === 'TRIAGE') return 'Triage';
  if (source === 'CONSULTATION') return 'Consultation';
  return source ?? '—';
}

function sourceColor(source) {
  if (source === 'TRIAGE') return 'neutral';
  if (source === 'CONSULTATION') return 'primary';
  return 'neutral';
}

export default function ConsultationVitalsTab({
  consultationId,
  readOnly = false,
  canAdd = false,
}) {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [vitals, setVitals] = useState({
    triageMesures: [],
    consultationMesures: [],
    signesVitaux: [],
  });
  const [form, setForm] = useState({ signeVitalId: '', valeur: '' });

  const load = useCallback(async () => {
    if (!consultationId) return;
    setLoading(true);
    setError('');
    try {
      const data = await fetchConsultationVitalsApi(consultationId);
      setVitals({
        triageMesures: Array.isArray(data?.triageMesures) ? data.triageMesures : [],
        consultationMesures: Array.isArray(data?.consultationMesures) ? data.consultationMesures : [],
        signesVitaux: Array.isArray(data?.signesVitaux) ? data.signesVitaux : [],
      });
    } catch (err) {
      setError(err.message || 'Impossible de charger les constantes vitales.');
    } finally {
      setLoading(false);
    }
  }, [consultationId]);

  useEffect(() => {
    load();
  }, [load]);

  const allMesures = [
    ...(vitals.triageMesures ?? []),
    ...(vitals.consultationMesures ?? []),
  ].sort((a, b) => new Date(b.measuredAt ?? 0) - new Date(a.measuredAt ?? 0));

  const handleSubmit = async (event) => {
    event.preventDefault();
    if (!canAdd || readOnly) return;

    if (!form.signeVitalId) {
      setError('Sélectionnez un signe vital.');
      return;
    }
    if (!form.valeur.trim()) {
      setError('La valeur est obligatoire.');
      return;
    }

    setSaving(true);
    setError('');
    try {
      const data = await addConsultationVitalApi(consultationId, {
        signeVitalId: Number(form.signeVitalId),
        valeur: form.valeur.trim(),
      });
      setVitals({
        triageMesures: Array.isArray(data?.triageMesures) ? data.triageMesures : [],
        consultationMesures: Array.isArray(data?.consultationMesures) ? data.consultationMesures : [],
        signesVitaux: Array.isArray(data?.signesVitaux) ? data.signesVitaux : vitals.signesVitaux,
      });
      setForm({ signeVitalId: '', valeur: '' });
    } catch (err) {
      setError(err.message || 'Ajout impossible.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Stack spacing={2}>
      <Stack direction="row" spacing={1.5} alignItems="center">
        <Activity size={20} color={LOTRU_PRIMARY[600]} />
        <Typography level="title-md" sx={{ fontWeight: 700, color: LOTRU_NEUTRAL[900] }}>
          Constantes vitales
        </Typography>
      </Stack>

      {error ? (
        <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
          {error}
        </Typography>
      ) : null}

      {loading ? (
        <Typography level="body-sm">Chargement des constantes…</Typography>
      ) : (
        <>
          <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5 }}>
            <Typography level="title-sm" sx={{ fontWeight: 700, mb: 1.5 }}>
              Mesures enregistrées
            </Typography>
            {allMesures.length === 0 ? (
              <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600] }}>
                Aucune mesure enregistrée pour cette visite.
              </Typography>
            ) : (
              <Sheet variant="outlined" sx={{ borderRadius: 'md', overflow: 'auto' }}>
                <Table stickyHeader hoverRow sx={{ minWidth: 640 }}>
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Signe vital</th>
                      <th>Valeur</th>
                      <th>Source</th>
                      <th>Enregistré par</th>
                    </tr>
                  </thead>
                  <tbody>
                    {allMesures.map((mesure) => (
                      <tr key={`${mesure.source}-${mesure.id}`}>
                        <td>{formatDateTime(mesure.measuredAt)}</td>
                        <td>{mesure.libelle ?? mesure.code ?? '—'}</td>
                        <td>{formatMesureValue(mesure)}</td>
                        <td>
                          <Chip size="sm" variant="soft" color={sourceColor(mesure.source)}>
                            {sourceLabel(mesure.source)}
                          </Chip>
                        </td>
                        <td>{mesure.measuredBy?.fullName ?? '—'}</td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
              </Sheet>
            )}
          </Card>

          {canAdd && !readOnly && (
            <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5 }}>
              <Typography level="title-sm" sx={{ fontWeight: 700, mb: 1.5 }}>
                Ajouter une mesure
              </Typography>
              <form onSubmit={handleSubmit}>
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} alignItems={{ md: 'flex-end' }}>
                  <FormControl sx={{ minWidth: 220, flex: 1 }}>
                    <FormLabel>Signe vital</FormLabel>
                    <Select
                      placeholder="Sélectionner…"
                      value={form.signeVitalId}
                      onChange={(_, value) => setForm((current) => ({ ...current, signeVitalId: value ?? '' }))}
                    >
                      {(vitals.signesVitaux ?? []).map((signe) => (
                        <Option key={signe.id} value={String(signe.id)}>
                          {signe.libelle}{signe.unite ? ` (${signe.unite})` : ''}
                        </Option>
                      ))}
                    </Select>
                  </FormControl>
                  <FormControl sx={{ minWidth: 160 }}>
                    <FormLabel>Valeur</FormLabel>
                    <Input
                      value={form.valeur}
                      onChange={(event) => setForm((current) => ({ ...current, valeur: event.target.value }))}
                      placeholder="Saisir la valeur"
                    />
                  </FormControl>
                  <Button type="submit" loading={saving} startDecorator={<Plus size={16} />}>
                    Ajouter
                  </Button>
                </Stack>
              </form>
            </Card>
          )}
        </>
      )}
    </Stack>
  );
}
