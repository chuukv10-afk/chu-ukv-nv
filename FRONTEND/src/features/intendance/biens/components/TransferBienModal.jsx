import { useEffect, useState } from 'react';
import {
  Box, Button, FormControl, FormLabel, Modal, ModalDialog, Option, Select, Stack, Textarea, Typography,
} from '@mui/joy';
import { ArrowLeftRight } from 'lucide-react';
import { fetchLocauxActifsApi } from '../../locaux/locauxApi.js';

const EMPTY = { serviceId: '', localId: '', motif: '' };

export default function TransferBienModal({
  open,
  bien,
  services = [],
  loading = false,
  error = '',
  onClose,
  onSubmit,
}) {
  const [form, setForm] = useState(EMPTY);
  const [locaux, setLocaux] = useState([]);

  useEffect(() => {
    if (open) setForm(EMPTY);
  }, [open, bien?.id]);

  useEffect(() => {
    if (!form.serviceId) {
      setLocaux([]);
      return;
    }
    fetchLocauxActifsApi(form.serviceId).then(setLocaux).catch(() => setLocaux([]));
  }, [form.serviceId]);

  const destinations = services.filter((item) => item.id !== bien?.service?.id);

  const handleSubmit = (event) => {
    event.preventDefault();
    onSubmit({
      serviceId: Number(form.serviceId),
      localId: form.localId ? Number(form.localId) : null,
      motif: form.motif.trim(),
    });
  };

  return (
    <Modal open={open} onClose={loading ? undefined : onClose}>
      <ModalDialog variant="outlined" sx={{ borderRadius: 'xl', maxWidth: 520, width: '100%', p: 0, overflow: 'hidden' }}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider' }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ width: 40, height: 40, borderRadius: 'md', bgcolor: 'primary.50', color: 'primary.600', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <ArrowLeftRight size={20} />
            </Box>
            <Box>
              <Typography level="title-lg" sx={{ fontWeight: 700 }}>Transférer le bien</Typography>
              <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
                {bien?.codeInventaire} — le code inventaire ne change pas.
              </Typography>
            </Box>
          </Stack>
        </Box>
        <Box component="form" onSubmit={handleSubmit}>
          <Stack spacing={2} sx={{ p: 3 }}>
            {error ? <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>{error}</Typography> : null}
            <Typography level="body-sm" sx={{ color: 'neutral.600' }}>
              Service actuel : {bien?.service ? `${bien.service.code} — ${bien.service.libelle}` : '—'}
            </Typography>
            <FormControl required>
              <FormLabel>Service destination</FormLabel>
              <Select
                value={form.serviceId === '' ? null : String(form.serviceId)}
                onChange={(_, value) => setForm((current) => ({ ...current, serviceId: value ?? '', localId: '' }))}
                disabled={loading}
              >
                {destinations.map((item) => (
                  <Option key={item.id} value={String(item.id)}>{item.code} — {item.libelle}</Option>
                ))}
              </Select>
            </FormControl>
            <FormControl>
              <FormLabel>Local destination (optionnel)</FormLabel>
              <Select
                value={form.localId === '' ? null : String(form.localId)}
                onChange={(_, value) => setForm((current) => ({ ...current, localId: value ?? '' }))}
                disabled={loading || !form.serviceId}
              >
                <Option value="">Sans local</Option>
                {locaux.map((item) => (
                  <Option key={item.id} value={String(item.id)}>{item.libelle}</Option>
                ))}
              </Select>
            </FormControl>
            <FormControl required>
              <FormLabel>Motif</FormLabel>
              <Textarea
                minRows={3}
                value={form.motif}
                onChange={(e) => setForm((current) => ({ ...current, motif: e.target.value }))}
                disabled={loading}
                placeholder="Motif obligatoire du transfert"
              />
            </FormControl>
          </Stack>
          <Stack direction="row" spacing={1.5} justifyContent="flex-end" sx={{ px: 3, py: 2, borderTop: '1px solid', borderColor: 'divider' }}>
            <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>Annuler</Button>
            <Button type="submit" loading={loading}>Transférer</Button>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
