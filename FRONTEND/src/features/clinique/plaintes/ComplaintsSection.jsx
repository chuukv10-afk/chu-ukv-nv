import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Box,
  Card,
  Chip,
  ChipDelete,
  CircularProgress,
  FormControl,
  FormLabel,
  Input,
  Radio,
  RadioGroup,
  Sheet,
  Stack,
  Textarea,
  Typography,
} from '@mui/joy';
import { Search } from 'lucide-react';
import { LOTRU_NEUTRAL } from '../../../theme/lotruPalette.js';
import { fetchPlaintesActivesApi } from '../../referentiel/plaintes/plaintesApi.js';
import {
  isComplaintSelected,
  normalizeSelectedComplaint,
  normalizeSymptoms,
  toggleComplaintSelection,
} from './complaintUtils.js';

export default function ComplaintsSection({
  symptoms,
  onChange,
  readOnly = false,
  title = 'Plaintes',
  showModeRadios = true,
}) {
  const normalized = useMemo(() => normalizeSymptoms(symptoms), [symptoms]);
  const [actives, setActives] = useState([]);
  const [loadingActives, setLoadingActives] = useState(true);
  const [query, setQuery] = useState('');

  const updateSymptoms = useCallback((patch) => {
    onChange?.({
      ...normalized,
      ...patch,
    });
  }, [normalized, onChange]);

  const loadActives = useCallback(async () => {
    setLoadingActives(true);
    try {
      const items = await fetchPlaintesActivesApi();
      setActives(Array.isArray(items) ? items : []);
    } catch {
      setActives([]);
    } finally {
      setLoadingActives(false);
    }
  }, []);

  useEffect(() => {
    loadActives();
  }, [loadActives]);

  const suggestions = useMemo(() => {
    const term = query.trim().toLowerCase();
    const filtered = term
      ? actives.filter((item) => (
        item.libelle?.toLowerCase().includes(term)
        || item.code?.toLowerCase().includes(term)
      ))
      : actives;
    return filtered.slice(0, 15);
  }, [actives, query]);

  const handleToggle = (plainte) => {
    if (readOnly) return;
    const nextSelected = toggleComplaintSelection(normalized.selectedComplaints, plainte);
    updateSymptoms({
      mode: nextSelected.length > 0 ? 'COMPLAINTS' : normalized.mode,
      selectedComplaints: nextSelected,
    });
  };

  const handleRemove = (item) => {
    if (readOnly) return;
    const normalizedItem = normalizeSelectedComplaint(item);
    if (!normalizedItem) return;
    const nextSelected = normalized.selectedComplaints.filter((current) => {
      const currentNormalized = normalizeSelectedComplaint(current);
      if (!currentNormalized) return false;
      if (normalizedItem.id && currentNormalized.id) return currentNormalized.id !== normalizedItem.id;
      return currentNormalized.libelle.toLowerCase() !== normalizedItem.libelle.toLowerCase();
    });
    updateSymptoms({ selectedComplaints: nextSelected });
  };

  return (
    <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5 }}>
      <Typography level="title-sm" sx={{ fontWeight: 700, mb: 1.5 }}>{title}</Typography>

      {showModeRadios ? (
        <RadioGroup
          value={normalized.mode}
          onChange={(event) => updateSymptoms({ mode: event.target.value })}
          sx={{ mb: normalized.mode === 'COMPLAINTS' ? 1.5 : 0 }}
        >
          <Radio value="NONE" label="Pas de plaintes" disabled={readOnly} />
          <Radio value="COMPLAINTS" label="Plaintes" disabled={readOnly} />
        </RadioGroup>
      ) : null}

      {(showModeRadios ? normalized.mode === 'COMPLAINTS' : true) ? (
        <Stack spacing={1.5}>
          {!readOnly ? (
            <FormControl>
              <FormLabel>Rechercher une plainte</FormLabel>
              <Input
                startDecorator={<Search size={16} />}
                placeholder="Rechercher une plainte…"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
              />
            </FormControl>
          ) : null}

          {!readOnly && loadingActives ? (
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
              <CircularProgress size="sm" />
              <Typography level="body-xs" sx={{ color: LOTRU_NEUTRAL[500] }}>Chargement des plaintes…</Typography>
            </Box>
          ) : null}

          {!readOnly && !loadingActives && suggestions.length > 0 ? (
            <Sheet variant="outlined" sx={{ borderRadius: 'md', p: 1.5 }}>
              <Typography level="body-xs" sx={{ color: LOTRU_NEUTRAL[500], mb: 1 }}>
                Suggestions du référentiel
              </Typography>
              <Stack direction="row" spacing={0.75} flexWrap="wrap" useFlexGap>
                {suggestions.map((plainte) => {
                  const selected = isComplaintSelected(normalized.selectedComplaints, plainte);
                  return (
                    <Chip
                      key={plainte.id ?? plainte.code}
                      size="sm"
                      variant={selected ? 'solid' : 'soft'}
                      color={selected ? 'primary' : 'neutral'}
                      onClick={() => handleToggle(plainte)}
                      sx={{ cursor: readOnly ? 'default' : 'pointer' }}
                    >
                      {plainte.libelle}
                    </Chip>
                  );
                })}
              </Stack>
            </Sheet>
          ) : null}

          <Box>
            <Typography level="body-xs" sx={{ color: LOTRU_NEUTRAL[500], mb: 0.75 }}>
              Plaintes enregistrées
            </Typography>
            {normalized.selectedComplaints.length === 0 ? (
              <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[400] }}>
                Aucune plainte sélectionnée.
              </Typography>
            ) : (
              <Stack direction="row" spacing={0.75} flexWrap="wrap" useFlexGap>
                {normalized.selectedComplaints.map((item) => {
                  const label = normalizeSelectedComplaint(item)?.libelle ?? '';
                  return (
                    <Chip
                      key={`${item?.id ?? 'x'}-${label}`}
                      size="md"
                      variant="soft"
                      color="primary"
                      endDecorator={
                        readOnly ? null : (
                          <ChipDelete onDelete={() => handleRemove(item)} />
                        )
                      }
                    >
                      {label}
                    </Chip>
                  );
                })}
              </Stack>
            )}
          </Box>

          <FormControl>
            <FormLabel>Précisions</FormLabel>
            <Textarea
              minRows={2}
              value={normalized.freeText}
              onChange={(event) => updateSymptoms({ freeText: event.target.value })}
              readOnly={readOnly}
              placeholder="Décrire les plaintes…"
            />
          </FormControl>
        </Stack>
      ) : null}
    </Card>
  );
}
