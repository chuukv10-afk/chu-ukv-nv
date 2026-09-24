import { useEffect, useState } from 'react';
import {
  Box, Button, FormControl, FormLabel, Input, Modal, ModalDialog, Option, Select, Stack, Typography,
} from '@mui/joy';
import { Wallet } from 'lucide-react';
import { formatFc, REGLEMENT_MODES, todayIsoKinshasa } from '../facturationConstants.js';

const MODAL_SX = {
  borderRadius: 'xl',
  maxWidth: 480,
  width: '100%',
  p: 0,
  overflow: 'hidden',
  boxShadow: 'lg',
};

function emptyReglementForm(reste) {
  return {
    montant: reste > 0 ? String(reste) : '',
    mode: 'ESPECES',
    dateReglement: todayIsoKinshasa(),
    notes: '',
  };
}

export default function FactureReglementModal({
  open,
  reste = 0,
  loading = false,
  error = '',
  onClose,
  onSubmit,
}) {
  const [form, setForm] = useState(() => emptyReglementForm(reste));

  useEffect(() => {
    if (open) setForm(emptyReglementForm(reste));
  }, [open, reste]);

  const handleSubmit = (event) => {
    event.preventDefault();
    onSubmit({
      montant: String(form.montant || '').replace(',', '.'),
      mode: form.mode,
      dateReglement: form.dateReglement,
      notes: form.notes.trim() || null,
    });
  };

  return (
    <Modal open={open} onClose={loading ? undefined : onClose}>
      <ModalDialog variant="outlined" sx={MODAL_SX}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider' }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{
              width: 40, height: 40, borderRadius: 'md', bgcolor: 'primary.50', color: 'primary.600',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
            }}
            >
              <Wallet size={20} />
            </Box>
            <Box>
              <Typography level="title-lg" sx={{ fontWeight: 700 }}>Régler la facture</Typography>
              <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
                Reste à payer : {formatFc(reste)}
              </Typography>
            </Box>
          </Stack>
        </Box>
        <Box component="form" onSubmit={handleSubmit} sx={{ p: 3 }}>
          <Stack spacing={2}>
            {error ? (
              <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
                {error}
              </Typography>
            ) : null}
            <FormControl required>
              <FormLabel>Montant</FormLabel>
              <Input
                type="number"
                value={form.montant}
                slotProps={{ input: { min: 0.01, step: '0.01', max: reste } }}
                onChange={(event) => setForm((current) => ({ ...current, montant: event.target.value }))}
              />
            </FormControl>
            <FormControl required>
              <FormLabel>Mode</FormLabel>
              <Select
                value={form.mode}
                onChange={(_, value) => setForm((current) => ({ ...current, mode: value || 'ESPECES' }))}
              >
                {REGLEMENT_MODES.map((item) => (
                  <Option key={item.value} value={item.value}>{item.label}</Option>
                ))}
              </Select>
            </FormControl>
            <FormControl required>
              <FormLabel>Date</FormLabel>
              <Input
                type="date"
                value={form.dateReglement}
                onChange={(event) => setForm((current) => ({ ...current, dateReglement: event.target.value }))}
              />
            </FormControl>
            <FormControl>
              <FormLabel>Notes</FormLabel>
              <Input
                value={form.notes}
                onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))}
              />
            </FormControl>
            <Stack direction="row" spacing={1} justifyContent="flex-end">
              <Button variant="plain" disabled={loading} onClick={onClose}>Annuler</Button>
              <Button type="submit" loading={loading}>Enregistrer le règlement</Button>
            </Stack>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
