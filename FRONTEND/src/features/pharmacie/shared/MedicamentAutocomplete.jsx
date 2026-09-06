import Autocomplete from '@mui/joy/Autocomplete';
import AutocompleteOption from '@mui/joy/AutocompleteOption';
import { ListItemContent, Typography } from '@mui/joy';
import { formatPrix } from './format.js';

function labelOf(item) {
  if (!item) return '';
  const extra = [item.dci, item.forme, item.dosage].filter(Boolean).join(' · ');
  return extra ? `${item.code} — ${item.libelle} (${extra})` : `${item.code} — ${item.libelle}`;
}

function filterMedicaments(options, state) {
  const query = String(state.inputValue ?? '').trim().toLowerCase();
  const source = !query
    ? options
    : options.filter((item) => [item.code, item.libelle, item.dci, item.forme, item.dosage]
      .some((value) => String(value ?? '').toLowerCase().includes(query)));
  return source.slice(0, 40);
}

export default function MedicamentAutocomplete({
  options = [],
  valueId = '',
  disabled = false,
  placeholder = 'Rechercher un médicament…',
  onSelect,
}) {
  const value = options.find((item) => String(item.id) === String(valueId)) ?? null;

  return (
    <Autocomplete
      options={options}
      value={value}
      disabled={disabled}
      placeholder={placeholder}
      sx={{ width: '100%' }}
      getOptionLabel={labelOf}
      isOptionEqualToValue={(option, selected) => String(option.id) === String(selected.id)}
      filterOptions={filterMedicaments}
      onChange={(_, selected) => onSelect(selected)}
      slotProps={{ input: { autoComplete: 'off' } }}
      renderOption={(props, option) => (
        <AutocompleteOption {...props} key={option.id}>
          <ListItemContent>
            <Typography level="title-sm">{option.code} — {option.libelle}</Typography>
            <Typography level="body-xs" sx={{ color: 'neutral.500' }}>
              {[option.dci, option.forme, option.dosage].filter(Boolean).join(' · ') || '—'}
              {option.prixVente != null ? ` · vente ${formatPrix(option.prixVente)}` : ''}
              {option.stockDisponible != null ? ` · stock ${option.stockDisponible}` : ''}
            </Typography>
          </ListItemContent>
        </AutocompleteOption>
      )}
    />
  );
}
