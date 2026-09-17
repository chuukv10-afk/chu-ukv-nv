import { useEffect, useState } from 'react';
import {
  Box, Button, Checkbox, Modal, ModalDialog, Stack, Typography,
} from '@mui/joy';
import { FileSpreadsheet, FileText } from 'lucide-react';

export default function CatalogueExportModal({
  open,
  format = 'xlsx',
  canIncludeValeur = false,
  loading = false,
  onClose,
  onConfirm,
}) {
  const [includeValeur, setIncludeValeur] = useState(false);
  const isPdf = format === 'pdf';

  useEffect(() => {
    if (open) {
      setIncludeValeur(false);
    }
  }, [open]);

  return (
    <Modal open={open} onClose={loading ? undefined : onClose}>
      <ModalDialog
        variant="outlined"
        sx={{ borderRadius: 'xl', maxWidth: 460, width: '100%', p: 3, boxShadow: 'lg' }}
      >
        <Stack spacing={2}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box
              sx={{
                width: 44,
                height: 44,
                borderRadius: 'md',
                bgcolor: 'primary.50',
                color: 'primary.600',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                flexShrink: 0,
              }}
            >
              {isPdf ? <FileText size={20} /> : <FileSpreadsheet size={20} />}
            </Box>
            <Box>
              <Typography level="title-lg" sx={{ fontWeight: 700 }}>
                {isPdf ? 'Exporter le catalogue en PDF' : 'Exporter le catalogue en Excel'}
              </Typography>
              <Typography level="body-sm" sx={{ color: 'neutral.600', mt: 0.5 }}>
                L’export reprend la recherche et le filtre de statut affichés.
              </Typography>
            </Box>
          </Stack>

          {canIncludeValeur ? (
            <Checkbox
              checked={includeValeur}
              disabled={loading}
              onChange={(event) => setIncludeValeur(event.target.checked)}
              label="Inclure la valeur du stock (prix total par médicament et total général)"
            />
          ) : null}

          <Stack direction="row" spacing={1} justifyContent="flex-end">
            <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>
              Annuler
            </Button>
            <Button loading={loading} onClick={() => onConfirm(includeValeur)}>
              Exporter
            </Button>
          </Stack>
        </Stack>
      </ModalDialog>
    </Modal>
  );
}
