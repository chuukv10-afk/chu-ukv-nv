import { useEffect, useMemo, useState } from 'react';
import Autocomplete from '@mui/joy/Autocomplete';
import AutocompleteOption from '@mui/joy/AutocompleteOption';
import { CircularProgress, ListItemContent, Typography } from '@mui/joy';
import { Search } from 'lucide-react';
import { fetchPatientsApi } from '../../../patient/patients/patientsApi.js';
import { aptitudeFieldSx, patientSearchLabel, patientSearchMeta } from '../aptitudeUi.js';

export default function PatientSearchAutocomplete({
  value = null,
  disabled = false,
  placeholder = 'Nom, postnom, code UKV, n° de dossier…',
  onSelect,
}) {
  const [query, setQuery] = useState('');
  const [options, setOptions] = useState([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (disabled) return undefined;
    let cancelled = false;
    const timer = window.setTimeout(async () => {
      setLoading(true);
      try {
        const result = await fetchPatientsApi({
          page: 1,
          limit: 12,
          search: query.trim() || undefined,
        });
        if (!cancelled) setOptions(result.items);
      } catch {
        if (!cancelled) setOptions([]);
      } finally {
        if (!cancelled) setLoading(false);
      }
    }, 250);
    return () => {
      cancelled = true;
      window.clearTimeout(timer);
    };
  }, [query, disabled]);

  const mergedOptions = useMemo(() => {
    if (!value?.id) return options;
    if (options.some((item) => String(item.id) === String(value.id))) return options;
    return [value, ...options];
  }, [options, value]);

  return (
    <Autocomplete
      options={mergedOptions}
      value={value}
      loading={loading}
      disabled={disabled}
      placeholder={placeholder}
      autoHighlight
      openOnFocus
      blurOnSelect
      filterOptions={(items) => items}
      getOptionLabel={patientSearchLabel}
      isOptionEqualToValue={(option, selected) => String(option.id) === String(selected.id)}
      onInputChange={(_, next, reason) => {
        if (reason === 'input') setQuery(next);
        if (reason === 'clear') {
          setQuery('');
          onSelect(null);
        }
      }}
      onChange={(_, selected) => onSelect(selected)}
      noOptionsText={loading ? 'Recherche…' : query.trim() ? 'Aucun patient trouvé' : 'Tapez pour rechercher un patient'}
      startDecorator={loading ? <CircularProgress size="sm" /> : <Search size={16} />}
      sx={aptitudeFieldSx}
      slotProps={{
        input: {
          autoComplete: 'off',
          autoCorrect: 'off',
          spellCheck: false,
          inputMode: 'search',
          enterKeyHint: 'search',
        },
        listbox: {
          sx: {
            zIndex: 1400,
            maxHeight: { xs: '50vh', md: 320 },
            '--ListItem-minHeight': '52px',
          },
        },
      }}
      renderOption={(props, option) => {
        const { key, ...optionProps } = props;
        const meta = patientSearchMeta(option);
        return (
          <AutocompleteOption key={option.id ?? key} {...optionProps}>
            <ListItemContent>
              <Typography level="title-sm">{patientSearchLabel(option)}</Typography>
              {meta ? (
                <Typography level="body-xs" sx={{ color: 'neutral.500' }}>{meta}</Typography>
              ) : null}
            </ListItemContent>
          </AutocompleteOption>
        );
      }}
    />
  );
}
