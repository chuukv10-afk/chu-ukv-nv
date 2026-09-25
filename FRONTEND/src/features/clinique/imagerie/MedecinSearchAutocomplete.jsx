import { useEffect, useMemo, useState } from 'react';
import Autocomplete from '@mui/joy/Autocomplete';
import AutocompleteOption from '@mui/joy/AutocompleteOption';
import { CircularProgress, ListItemContent, Typography } from '@mui/joy';
import { Search } from 'lucide-react';
import { medecinLabel } from './imagerieConstants.js';
import { fetchMedecinsImagerieApi } from './imagerieApi.js';

export default function MedecinSearchAutocomplete({
  value = null,
  disabled = false,
  placeholder = 'Rechercher un médecin…',
  freeSolo = true,
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
        const items = await fetchMedecinsImagerieApi(query.trim());
        if (!cancelled) setOptions(items);
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
      freeSolo={freeSolo}
      autoHighlight
      openOnFocus
      blurOnSelect
      filterOptions={(items) => items}
      getOptionLabel={(option) => {
        if (!option) return '';
        if (typeof option === 'string') return option;
        return medecinLabel(option);
      }}
      isOptionEqualToValue={(option, selected) => (
        Boolean(option?.id) && Boolean(selected?.id) && String(option.id) === String(selected.id)
      )}
      onInputChange={(_, next, reason) => {
        if (reason === 'input') {
          setQuery(next);
          onSelect(next.trim() ? { id: '', nom: next.trim() } : null);
        }
        if (reason === 'clear') {
          setQuery('');
          onSelect(null);
        }
      }}
      onChange={(_, selected) => {
        if (!selected) {
          onSelect(null);
          return;
        }
        if (typeof selected === 'string') {
          onSelect(selected.trim() ? { id: '', nom: selected.trim() } : null);
          return;
        }
        onSelect(selected);
      }}
      noOptionsText={loading ? 'Recherche…' : query.trim() ? 'Aucun médecin trouvé — le nom saisi sera retenu' : 'Tapez pour rechercher un médecin'}
      startDecorator={loading ? <CircularProgress size="sm" /> : <Search size={16} />}
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
          },
        },
      }}
      renderOption={(props, option) => {
        const { key, ...optionProps } = props;
        return (
          <AutocompleteOption key={option.id ?? key} {...optionProps}>
            <ListItemContent>
              <Typography level="title-sm">{medecinLabel(option)}</Typography>
            </ListItemContent>
          </AutocompleteOption>
        );
      }}
    />
  );
}
