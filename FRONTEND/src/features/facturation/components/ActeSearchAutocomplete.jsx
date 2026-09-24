import { useEffect, useState } from 'react';
import Autocomplete from '@mui/joy/Autocomplete';
import AutocompleteOption from '@mui/joy/AutocompleteOption';
import { CircularProgress, ListItemContent, Typography } from '@mui/joy';
import { Search } from 'lucide-react';
import { fetchFactureActesApi } from '../facturationApi.js';
import { formatFc, tarifActePour } from '../facturationConstants.js';

export default function ActeSearchAutocomplete({
  categorieTarifaire = 'A',
  disabled = false,
  placeholder = 'Rechercher un acte (code, libellé, service)…',
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
        const result = await fetchFactureActesApi({
          page: 1,
          limit: 20,
          search: query.trim() || undefined,
          statut: 'ACTIF',
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

  return (
    <Autocomplete
      options={options}
      value={null}
      loading={loading}
      disabled={disabled}
      placeholder={placeholder}
      autoHighlight
      openOnFocus
      blurOnSelect
      filterOptions={(items) => items}
      getOptionLabel={(acte) => (acte ? `${acte.code} — ${acte.libelle}` : '')}
      isOptionEqualToValue={(option, selected) => String(option.id) === String(selected.id)}
      onInputChange={(_, next, reason) => {
        if (reason === 'input') setQuery(next);
        if (reason === 'clear') setQuery('');
      }}
      onChange={(_, selected) => {
        if (selected) {
          onSelect(selected);
          setQuery('');
        }
      }}
      noOptionsText={loading ? 'Recherche…' : query.trim() ? 'Aucun acte trouvé' : 'Tapez pour rechercher un acte'}
      startDecorator={loading ? <CircularProgress size="sm" /> : <Search size={16} />}
      sx={{ width: '100%' }}
      slotProps={{
        input: { autoComplete: 'off' },
        listbox: { sx: { zIndex: 1400, maxHeight: 320 } },
      }}
      renderOption={(props, option) => {
        const { key, ...optionProps } = props;
        return (
          <AutocompleteOption key={option.id ?? key} {...optionProps}>
            <ListItemContent>
              <Typography level="title-sm">{option.code} — {option.libelle}</Typography>
              <Typography level="body-xs" sx={{ color: 'neutral.500' }}>
                {[option.serviceGrille, option.sousCategorie].filter(Boolean).join(' · ') || '—'}
                {' · '}
                {formatFc(tarifActePour(option, categorieTarifaire))}
              </Typography>
            </ListItemContent>
          </AutocompleteOption>
        );
      }}
    />
  );
}
