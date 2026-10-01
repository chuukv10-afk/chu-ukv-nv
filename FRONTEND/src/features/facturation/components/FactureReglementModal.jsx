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

function emptyReglementForm() {
  return {
    couverture: 'total',
    montant: '',
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
  const [form, setForm] = useState(() => emptyReglementForm());
  const [localError, setLocalError] = useState('');
  const resteValue = Number(reste) || 0;
  const isTotal = form.couverture !== 'partiel';

  useEffect(() => {
    if (open) {
      setForm(emptyReglementForm());
      setLocalError('');
    }
  }, [open, reste]);

  const handleSubmit = (event) => {
    event.preventDefault();
    const raw = isTotal ? String(resteValue) : String(form.montant || '').replace(',', '.');
    const montant = Number(raw);
    if (isTotal) {
      if (!(resteValue > 0)) {
        setLocalError('Cette facture n’a plus rien à payer.');
        return;
      }
    } else if (!(montant > 0) || montant >= resteValue - 0.0001) {
      setLocalError('Pour un paiement partiel, indiquez un montant inférieur au reste à payer.');
      return;
    }
    setLocalError('');
    onSubmit({
      montant: raw,
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
              <Typography level="title-lg" sx={{ fontWeight: 700 }}>Marquer comme payée</Typography>
              <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
                Reste à payer : {formatFc(resteValue)}
              </Typography>
            </Box>
          </Stack>
        </Box>
        <Box component="form" onSubmit={handleSubmit} sx={{ p: 3 }}>
          <Stack spacing={2}>
            {error || localError ? (
              <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
                {localError || error}
              </Typography>
            ) : null}
            <FormControl required>
              <FormLabel>Paiement</FormLabel>
              <Select
                value={form.couverture}
                onChange={(_, value) => {
                  setLocalError('');
                  setForm((current) => ({ ...current, couverture: value || 'total' }));
                }}
              >
                <Option value="total">Payée totalement</Option>
                <Option value="partiel">Payée partiellement</Option>
              </Select>
            </FormControl>
            <FormControl required>
              <FormLabel>Montant</FormLabel>
              <Input
                type="number"
                value={isTotal ? String(resteValue) : form.montant}
                disabled={isTotal}
                slotProps={{ input: { min: 0.01, step: '0.01', max: isTotal ? undefined : resteValue } }}
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
              <Button type="submit" loading={loading}>
                {isTotal ? 'Marquer payée totalement' : 'Enregistrer le paiement partiel'}
              </Button>
            </Stack>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
