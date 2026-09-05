import { IconButton, Option, Select, Stack, Typography } from '@mui/joy';
import { ChevronLeft, ChevronRight } from 'lucide-react';

export default function AppPagination({
  page = 1,
  totalPages = 0,
  total = 0,
  limit = 10,
  onPageChange,
  onLimitChange,
  limitOptions = [10, 25, 50],
  loading = false,
}) {
  const canGoPrev = page > 1;
  const canGoNext = totalPages > 0 && page < totalPages;
  const from = total === 0 ? 0 : (page - 1) * limit + 1;
  const to = total === 0 ? 0 : Math.min(page * limit, total);

  return (
    <Stack
      direction={{ xs: 'column', sm: 'row' }}
      spacing={1.5}
      alignItems={{ xs: 'stretch', sm: 'center' }}
      justifyContent="space-between"
    >
      <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
        {total === 0
          ? 'Aucun résultat'
          : `${from}–${to} sur ${total} résultat${total > 1 ? 's' : ''}`}
      </Typography>

      <Stack direction="row" spacing={1} alignItems="center" justifyContent={{ xs: 'space-between', sm: 'flex-end' }}>
        {onLimitChange ? (
          <Stack direction="row" spacing={1} alignItems="center">
            <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
              Par page
            </Typography>
            <Select
              size="sm"
              value={limit}
              onChange={(_, value) => onLimitChange(value)}
              disabled={loading}
              sx={{ minWidth: 72 }}
            >
              {limitOptions.map((option) => (
                <Option key={option} value={option}>
                  {option}
                </Option>
              ))}
            </Select>
          </Stack>
        ) : null}

        <Stack direction="row" spacing={0.5} alignItems="center">
          <IconButton
            size="sm"
            variant="outlined"
            color="neutral"
            disabled={!canGoPrev || loading}
            onClick={() => onPageChange(page - 1)}
            aria-label="Page précédente"
          >
            <ChevronLeft size={16} />
          </IconButton>

          <Typography level="body-sm" sx={{ minWidth: 88, textAlign: 'center', color: 'neutral.600' }}>
            Page {totalPages === 0 ? 0 : page} / {totalPages}
          </Typography>

          <IconButton
            size="sm"
            variant="outlined"
            color="neutral"
            disabled={!canGoNext || loading}
            onClick={() => onPageChange(page + 1)}
            aria-label="Page suivante"
          >
            <ChevronRight size={16} />
          </IconButton>
        </Stack>
      </Stack>
    </Stack>
  );
}
