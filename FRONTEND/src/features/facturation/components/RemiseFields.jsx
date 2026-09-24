import { FormControl, FormLabel, Input, Option, Select, Stack, Typography } from '@mui/joy';
import { formatFc, formatRemiseLabel, REMISE_NONE, REMISE_TYPES } from '../facturationConstants.js';

export default function RemiseFields({
  type,
  valeur,
  montant = 0,
  disabled = false,
  compact = false,
  label = 'Remise',
  onChange,
}) {
  const active = type && type !== REMISE_NONE;
  const summary = formatRemiseLabel(type, valeur, montant);

  if (disabled && !active) {
    return compact ? null : (
      <Typography level="body-sm" sx={{ color: 'neutral.500' }}>Sans remise</Typography>
    );
  }

  if (disabled) {
    return (
      <Typography level="body-sm" color="warning">
        {summary || formatFc(montant)}
      </Typography>
    );
  }

  return (
    <Stack direction={compact ? 'column' : { xs: 'column', sm: 'row' }} spacing={1} alignItems={compact ? 'stretch' : { sm: 'flex-end' }}>
      <FormControl sx={{ minWidth: compact ? 0 : 160, flex: compact ? undefined : 1 }}>
        {compact ? null : <FormLabel>{label}</FormLabel>}
        <Select
          size={compact ? 'sm' : 'md'}
          value={type || REMISE_NONE}
          onChange={(_, value) => onChange(value || REMISE_NONE, value === REMISE_NONE ? '' : valeur)}
        >
          {REMISE_TYPES.map((item) => (
            <Option key={item.value} value={item.value}>{item.label}</Option>
          ))}
        </Select>
      </FormControl>
      {active ? (
        <FormControl sx={{ minWidth: compact ? 0 : 120, width: compact ? '100%' : 140 }}>
          {compact ? null : <FormLabel>{type === 'POURCENTAGE' ? 'Taux (%)' : 'Montant (FC)'}</FormLabel>}
          <Input
            size={compact ? 'sm' : 'md'}
            type="number"
            value={valeur}
            placeholder={type === 'POURCENTAGE' ? '%' : 'FC'}
            slotProps={{ input: { min: 0, max: type === 'POURCENTAGE' ? 100 : undefined, step: 'any' } }}
            onChange={(event) => onChange(type, event.target.value)}
          />
        </FormControl>
      ) : null}
    </Stack>
  );
}
