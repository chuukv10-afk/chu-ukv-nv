import { useEffect, useState } from 'react';
import {
  Box, Button, Checkbox, Modal, ModalDialog, Stack, Typography,
} from '@mui/joy';
import { FileText } from 'lucide-react';
import { INVENTAIRE_EXPORT_COLUMNS, INVENTAIRE_PDF_DEFAULT_COLUMNS } from '../inventaireConstants.js';

export default function InventaireExportModal({
  open,
  loading = false,
  onClose,
  onConfirm,
}) {
  const [selected, setSelected] = useState(INVENTAIRE_PDF_DEFAULT_COLUMNS);

  useEffect(() => {
    if (open) setSelected(INVENTAIRE_PDF_DEFAULT_COLUMNS);
  }, [open]);

  const toggle = (key, required) => {
    if (required) return;
    setSelected((current) => (
      current.includes(key)
        ? current.filter((item) => item !== key)
        : [...current, key]
    ));
  };

  const selectDefaults = () => setSelected(INVENTAIRE_PDF_DEFAULT_COLUMNS);
  const selectAll = () => setSelected(INVENTAIRE_EXPORT_COLUMNS.map((item) => item.key));

  return (
    <Modal open={open} onClose={loading ? undefined : onClose}>
      <ModalDialog
        variant="outlined"
        sx={{ borderRadius: 'xl', maxWidth: 520, width: '100%', p: 3, boxShadow: 'lg' }}
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
              <FileText size={20} />
            </Box>
            <Box>
              <Typography level="title-lg" sx={{ fontWeight: 700 }}>
                Exporter l’inventaire en PDF
              </Typography>
              <Typography level="body-sm" sx={{ color: 'neutral.600', mt: 0.5 }}>
                Précisez les colonnes à retenir sur la fiche.
              </Typography>
            </Box>
          </Stack>

          <Stack direction="row" spacing={1}>
            <Button size="sm" variant="plain" disabled={loading} onClick={selectDefaults}>
              Colonnes habituelles
            </Button>
            <Button size="sm" variant="plain" disabled={loading} onClick={selectAll}>
              Toutes
            </Button>
          </Stack>

          <Box
            sx={{
              display: 'grid',
              gridTemplateColumns: { xs: '1fr', sm: '1fr 1fr' },
              gap: 1,
            }}
          >
            {INVENTAIRE_EXPORT_COLUMNS.map((item) => (
              <Checkbox
                key={item.key}
                checked={selected.includes(item.key)}
                disabled={loading || item.required}
                onChange={() => toggle(item.key, item.required)}
                label={item.required ? `${item.label} (obligatoire)` : item.label}
              />
            ))}
          </Box>

          <Stack direction="row" spacing={1} justifyContent="flex-end">
            <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>
              Annuler
            </Button>
            <Button loading={loading} onClick={() => onConfirm(selected)}>
              Générer le PDF
            </Button>
          </Stack>
        </Stack>
      </ModalDialog>
    </Modal>
  );
}
