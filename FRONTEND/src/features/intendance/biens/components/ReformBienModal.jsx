import { useEffect, useState } from 'react';
import {
  Box, Button, FormControl, FormLabel, Modal, ModalDialog, Stack, Textarea, Typography,
} from '@mui/joy';
import { Ban } from 'lucide-react';

export default function ReformBienModal({
  open,
  bien,
  loading = false,
  error = '',
  onClose,
  onSubmit,
}) {
  const [motif, setMotif] = useState('');

  useEffect(() => {
    if (open) setMotif('');
  }, [open, bien?.id]);

  const handleSubmit = (event) => {
    event.preventDefault();
    onSubmit({ motif: motif.trim() });
  };

  return (
    <Modal open={open} onClose={loading ? undefined : onClose}>
      <ModalDialog variant="outlined" sx={{ borderRadius: 'xl', maxWidth: 480, width: '100%', p: 0, overflow: 'hidden' }}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider' }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ width: 40, height: 40, borderRadius: 'md', bgcolor: 'danger.50', color: 'danger.600', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Ban size={20} />
            </Box>
            <Box>
              <Typography level="title-lg" sx={{ fontWeight: 700 }}>Réformer le bien</Typography>
              <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
                {bien?.codeInventaire} passera à l’état Réformé (R).
              </Typography>
            </Box>
          </Stack>
        </Box>
        <Box component="form" onSubmit={handleSubmit}>
          <Stack spacing={2} sx={{ p: 3 }}>
            {error ? <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>{error}</Typography> : null}
            <Typography level="body-sm" sx={{ color: 'neutral.600' }}>
              La réforme sort le bien du parc actif. Le code inventaire reste réservé.
            </Typography>
            <FormControl required>
              <FormLabel>Motif de réforme</FormLabel>
              <Textarea
                minRows={3}
                value={motif}
                onChange={(e) => setMotif(e.target.value)}
                disabled={loading}
                placeholder="Motif obligatoire"
              />
            </FormControl>
          </Stack>
          <Stack direction="row" spacing={1.5} justifyContent="flex-end" sx={{ px: 3, py: 2, borderTop: '1px solid', borderColor: 'divider' }}>
            <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>Annuler</Button>
            <Button type="submit" color="danger" loading={loading}>Réformer</Button>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
