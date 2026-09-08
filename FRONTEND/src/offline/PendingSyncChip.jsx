import { Chip } from '@mui/joy';

export default function PendingSyncChip({ show }) {
  if (!show) return null;
  return <Chip size="sm" variant="soft" color="warning">À synchroniser</Chip>;
}
