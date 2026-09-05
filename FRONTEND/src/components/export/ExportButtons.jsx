import { Button, Stack } from '@mui/joy';
import { FileSpreadsheet, FileText } from 'lucide-react';

export default function ExportButtons({
  onExport,
  loading = null,
  disabled = false,
  size = 'md',
}) {
  return (
    <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
      <Button
        variant="outlined"
        color="neutral"
        size={size}
        startDecorator={<FileSpreadsheet size={18} />}
        loading={loading === 'xlsx'}
        disabled={disabled || Boolean(loading)}
        onClick={() => onExport('xlsx')}
      >
        Excel
      </Button>
      <Button
        variant="outlined"
        color="neutral"
        size={size}
        startDecorator={<FileText size={18} />}
        loading={loading === 'pdf'}
        disabled={disabled || Boolean(loading)}
        onClick={() => onExport('pdf')}
      >
        PDF
      </Button>
    </Stack>
  );
}
