import { useEffect, useState } from 'react';
import {
  Box, Button, FormControl, FormLabel, Input, Modal, ModalDialog, Stack, Typography,
} from '@mui/joy';
import { Layers } from 'lucide-react';

const MODAL_SX = {
  borderRadius: 'xl',
  maxWidth: 480,
  width: '100%',
  p: 0,
  overflow: 'hidden',
  boxShadow: 'lg',
};

export default function LotFormModal({
  open,
  lot,
  loading = false,
  error = '',
  onClose,
  onSubmit,
}) {
  const [form, setForm] = useState({ numeroLot: '', datePeremption: '' });

  useEffect(() => {
    if (open && lot) {
      setForm({
        numeroLot: lot.numeroLot ?? '',
        datePeremption: lot.datePeremption ?? '',
      });
    }
  }, [open, lot]);

  const handleSubmit = (event) => {
    event.preventDefault();
    onSubmit({
      numeroLot: form.numeroLot.trim().toUpperCase(),
      datePeremption: form.datePeremption,
    });
  };

  const medicamentLabel = lot?.medicament
    ? `${lot.medicament.code} — ${lot.medicament.libelle}`
    : '—';

  return (
    <Modal open={open} onClose={loading ? undefined : onClose}>
      <ModalDialog variant="outlined" sx={MODAL_SX}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider' }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ width: 40, height: 40, borderRadius: 'md', bgcolor: 'primary.50', color: 'primary.600', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Layers size={20} />
            </Box>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>Corriger le lot</Typography>
          </Stack>
        </Box>
        <Box component="form" onSubmit={handleSubmit}>
          <Box sx={{ p: 3 }}>
            <Stack spacing={2}>
              {error ? (
                <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
                  {error}
                </Typography>
              ) : null}
              <FormControl>
                <FormLabel>Médicament</FormLabel>
                <Input value={medicamentLabel} disabled readOnly />
              </FormControl>
              <FormControl required>
                <FormLabel>N° lot</FormLabel>
                <Input
                  value={form.numeroLot}
                  onChange={(event) => setForm((current) => ({ ...current, numeroLot: event.target.value.toUpperCase() }))}
                  disabled={loading}
                  slotProps={{ input: { maxLength: 40 } }}
                />
              </FormControl>
              <FormControl required>
                <FormLabel>Date de péremption</FormLabel>
                <Input
                  type="date"
                  value={form.datePeremption}
                  onChange={(event) => setForm((current) => ({ ...current, datePeremption: event.target.value }))}
                  disabled={loading}
                />
              </FormControl>
              <Typography level="body-xs" sx={{ color: 'neutral.500' }}>
                La quantité ne se corrige pas ici : utilisez un ajustement de stock.
              </Typography>
            </Stack>
          </Box>
          <Stack direction="row" spacing={1.5} justifyContent="flex-end" sx={{ px: 3, py: 2, borderTop: '1px solid', borderColor: 'divider' }}>
            <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>Annuler</Button>
            <Button type="submit" loading={loading}>Enregistrer</Button>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
