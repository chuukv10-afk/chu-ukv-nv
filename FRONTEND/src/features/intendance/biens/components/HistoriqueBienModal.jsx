import { Box, Chip, Modal, ModalDialog, Stack, Typography } from '@mui/joy';
import { History } from 'lucide-react';
import { BIEN_ETAT_LABELS, HISTORIQUE_TYPE_LABELS } from '../bienConstants.js';

function formatDate(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('fr-FR');
}

function serviceLabel(item) {
  return item ? `${item.code} — ${item.libelle}` : '—';
}

export default function HistoriqueBienModal({ open, bien, items = [], loading = false, onClose }) {
  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog variant="outlined" sx={{ borderRadius: 'xl', maxWidth: 640, width: '100%', p: 0, overflow: 'hidden', maxHeight: 'min(92vh, 760px)', display: 'flex', flexDirection: 'column' }}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider', flexShrink: 0 }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ width: 40, height: 40, borderRadius: 'md', bgcolor: 'neutral.100', color: 'neutral.700', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <History size={20} />
            </Box>
            <Box>
              <Typography level="title-lg" sx={{ fontWeight: 700 }}>Historique</Typography>
              <Typography level="body-sm" sx={{ color: 'neutral.500' }}>{bien?.codeInventaire}</Typography>
            </Box>
          </Stack>
        </Box>
        <Stack spacing={1.5} sx={{ p: 3, overflow: 'auto' }}>
          {loading ? (
            <Typography level="body-sm">Chargement…</Typography>
          ) : items.length === 0 ? (
            <Typography level="body-sm" sx={{ color: 'neutral.500' }}>Aucun événement.</Typography>
          ) : items.map((ligne) => (
            <Box key={ligne.id} sx={{ p: 1.5, borderRadius: 'md', border: '1px solid', borderColor: 'divider' }}>
              <Stack direction="row" justifyContent="space-between" alignItems="flex-start" spacing={1}>
                <Chip size="sm" variant="soft">{HISTORIQUE_TYPE_LABELS[ligne.type] ?? ligne.type}</Chip>
                <Typography level="body-xs" sx={{ color: 'neutral.500' }}>{formatDate(ligne.createdAt)}</Typography>
              </Stack>
              {ligne.motif ? <Typography level="body-sm" sx={{ mt: 0.75 }}>{ligne.motif}</Typography> : null}
              {ligne.serviceAvant || ligne.serviceApres ? (
                <Typography level="body-xs" sx={{ color: 'neutral.600', mt: 0.5 }}>
                  Service : {serviceLabel(ligne.serviceAvant)} → {serviceLabel(ligne.serviceApres)}
                </Typography>
              ) : null}
              {ligne.localAvant || ligne.localApres ? (
                <Typography level="body-xs" sx={{ color: 'neutral.600', mt: 0.5 }}>
                  Local : {ligne.localAvant?.libelle ?? '—'} → {ligne.localApres?.libelle ?? '—'}
                </Typography>
              ) : null}
              {ligne.etatAvant || ligne.etatApres ? (
                <Typography level="body-xs" sx={{ color: 'neutral.600', mt: 0.5 }}>
                  État : {BIEN_ETAT_LABELS[ligne.etatAvant] ?? ligne.etatAvant ?? '—'} → {BIEN_ETAT_LABELS[ligne.etatApres] ?? ligne.etatApres ?? '—'}
                </Typography>
              ) : null}
              {ligne.codeAvant || ligne.codeApres ? (
                <Typography level="body-xs" sx={{ color: 'neutral.600', mt: 0.5, fontFamily: 'monospace' }}>
                  {ligne.codeAvant ?? '—'} → {ligne.codeApres ?? '—'}
                </Typography>
              ) : null}
              {ligne.createdBy ? (
                <Typography level="body-xs" sx={{ color: 'neutral.500', mt: 0.5 }}>
                  {ligne.createdBy.prenom} {ligne.createdBy.nom}
                </Typography>
              ) : null}
            </Box>
          ))}
        </Stack>
      </ModalDialog>
    </Modal>
  );
}
