import { useEffect, useState } from 'react';
import {
  Button, Chip, IconButton, Modal, ModalDialog, ModalClose, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { Trash2 } from 'lucide-react';
import ConfirmModal from '../components/ui/ConfirmModal.jsx';
import { PERMISSIONS } from '../constants/permissions.js';
import { usePermissions } from '../hooks/usePermissions.js';
import { formatDateTime } from '../features/pharmacie/shared/format.js';
import {
  discardAllUnsynced,
  discardUnsynced,
  listUnsyncedMutations,
} from './outbox.js';

const ACTION_LABELS = {
  'pharmacie.vente.complete': 'Vente hors-ligne',
  'pharmacie.vente.create': 'Création de vente',
  'pharmacie.vente.create_and_valider': 'Vente créée et validée',
  'pharmacie.vente.update': 'Modification de vente',
  'pharmacie.vente.valider': 'Validation de vente',
  'pharmacie.vente.annuler': 'Annulation de vente',
  'pharmacie.reception.create': 'Réception',
  'pharmacie.reception.update': 'Modification de réception',
  'pharmacie.reception.valider': 'Validation de réception',
  'pharmacie.demande_service.create': 'Demande de service',
  'pharmacie.demande_service.delivrer': 'Délivrance service',
  'pharmacie.ajustement.create': 'Ajustement de stock',
  'pharmacie.lot.update': 'Correction de lot',
  'pharmacie.medicament.create': 'Médicament',
  'pharmacie.fournisseur.create': 'Fournisseur',
};

const STATUS_CHIPS = {
  pending: { color: 'primary', label: 'En file' },
  conflict: { color: 'danger', label: 'Refusé' },
  rejected: { color: 'danger', label: 'Rejeté' },
};

function actionLabel(action) {
  return ACTION_LABELS[action] || action || 'Opération';
}

export default function ConflictPanel({ open, onClose }) {
  const { isAdmin, hasPermission } = usePermissions();
  const canDelete = isAdmin || hasPermission(PERMISSIONS.PHARMACIE.SYNC_CONFLICT_DELETE);
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(false);
  const [pending, setPending] = useState(null);

  const reload = async () => {
    setItems(await listUnsyncedMutations());
  };

  useEffect(() => {
    if (!open) return undefined;
    let cancelled = false;
    listUnsyncedMutations().then((rows) => {
      if (!cancelled) setItems(rows);
    });
    return () => { cancelled = true; };
  }, [open]);

  const handleDiscard = async () => {
    if (!pending) return;
    setLoading(true);
    try {
      if (pending === 'all') {
        await discardAllUnsynced();
      } else {
        await discardUnsynced(pending.clientId);
      }
      await reload();
      setPending(null);
      const remaining = await listUnsyncedMutations();
      if (remaining.length === 0) onClose();
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <Modal open={open} onClose={onClose}>
        <ModalDialog sx={{ borderRadius: 'xl', width: '100%', maxWidth: 820, p: 2.5 }}>
          <ModalClose />
          <Typography level="title-lg" sx={{ fontWeight: 700 }}>Écritures hors-ligne</Typography>
          <Typography level="body-sm" sx={{ color: 'neutral.600', mb: 1.5 }}>
            File d’attente et refus serveur. La synchro n’envoie que les opérations encore en file.
            {canDelete
              ? ' En tant qu’administrateur, vous pouvez supprimer définitivement une écriture non synchronisée (le stock local est rétabli si besoin).'
              : ''}
          </Typography>

          {items.length === 0 ? (
            <Typography level="body-sm">Aucune écriture en attente ou refusée.</Typography>
          ) : (
            <Sheet variant="outlined" sx={{ borderRadius: 'md', overflow: 'auto', maxHeight: 420 }}>
              <Table stickyHeader size="sm">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Opération</th>
                    <th>Statut</th>
                    <th>Message</th>
                    {canDelete ? <th style={{ width: 56 }} /> : null}
                  </tr>
                </thead>
                <tbody>
                  {items.map((item) => {
                    const status = STATUS_CHIPS[item.status] || STATUS_CHIPS.conflict;
                    return (
                      <tr key={item.clientId || item.id}>
                        <td>{formatDateTime(item.createdAt)}</td>
                        <td>
                          <Stack spacing={0.25}>
                            <Typography level="body-sm">{actionLabel(item.action)}</Typography>
                            {item.optimistic?.numero ? (
                              <Chip size="sm" variant="soft">{item.optimistic.numero}</Chip>
                            ) : null}
                          </Stack>
                        </td>
                        <td>
                          <Chip size="sm" variant="soft" color={status.color}>{status.label}</Chip>
                        </td>
                        <td>
                          <Typography level="body-xs">{item.message || '—'}</Typography>
                        </td>
                        {canDelete ? (
                          <td>
                            <IconButton
                              size="sm"
                              variant="plain"
                              color="danger"
                              title="Supprimer cette écriture locale"
                              onClick={() => setPending(item)}
                            >
                              <Trash2 size={16} />
                            </IconButton>
                          </td>
                        ) : null}
                      </tr>
                    );
                  })}
                </tbody>
              </Table>
            </Sheet>
          )}

          <Stack direction="row" spacing={1} justifyContent="flex-end" sx={{ mt: 1.5 }}>
            {canDelete && items.length > 1 ? (
              <Button color="danger" variant="outlined" onClick={() => setPending('all')}>
                Tout supprimer
              </Button>
            ) : null}
            <Button variant="plain" color="neutral" onClick={onClose}>Fermer</Button>
          </Stack>
        </ModalDialog>
      </Modal>

      <ConfirmModal
        open={Boolean(pending)}
        title={pending === 'all' ? 'Supprimer toutes les écritures non synchronisées ?' : 'Supprimer cette écriture locale ?'}
        message="Elle ne sera plus envoyée au serveur. Si le stock local avait déjà été modifié, il sera rétabli. Cette action est irréversible."
        confirmLabel="Supprimer"
        loading={loading}
        onClose={() => !loading && setPending(null)}
        onConfirm={handleDiscard}
      />
    </>
  );
}
