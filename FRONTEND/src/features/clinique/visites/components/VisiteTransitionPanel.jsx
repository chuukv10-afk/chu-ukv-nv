import { useState } from 'react';
import { Button, Stack } from '@mui/joy';
import { fetchVisiteHospitalisationMetaApi } from '../visitesApi.js';
import VisiteHospitalisationModal from './VisiteHospitalisationModal.jsx';
import VisiteTransitionConfirmModal from './VisiteTransitionConfirmModal.jsx';
import { VISITE_TRANSITION_LABELS } from '../visiteConstants.js';

const TRANSITION_ORDER = ['EN_COURS', 'HOSPITALISE', 'TERMINEE', 'ANNULEE'];

function sortTransitions(transitions) {
  return [...transitions].sort(
    (left, right) => TRANSITION_ORDER.indexOf(left) - TRANSITION_ORDER.indexOf(right),
  );
}

function transitionProps(statut) {
  if (statut === 'TERMINEE') {
    return { variant: 'outlined', color: 'neutral' };
  }
  if (statut === 'ANNULEE') {
    return { variant: 'outlined', color: 'danger' };
  }
  return { variant: 'soft', color: 'primary' };
}

export default function VisiteTransitionPanel({
  visite,
  canUpdate = false,
  loading = false,
  error = '',
  onTransition,
}) {
  const [hospModalOpen, setHospModalOpen] = useState(false);
  const [hospMeta, setHospMeta] = useState({ blocs: [] });
  const [hospMetaLoading, setHospMetaLoading] = useState(false);
  const [hospMetaError, setHospMetaError] = useState('');
  const [confirmStatut, setConfirmStatut] = useState(null);
  const recordWritable = visite?.recordWritable !== false;
  const transitions = sortTransitions(visite?.allowedTransitions ?? []).filter((statut) => {
    if (recordWritable) {
      return true;
    }
    return statut === 'TERMINEE' || statut === 'ANNULEE';
  });

  if (!canUpdate || transitions.length === 0) {
    return null;
  }

  const openHospitalisationModal = async () => {
    setHospMetaError('');
    setHospMetaLoading(true);
    setHospModalOpen(true);
    try {
      const meta = await fetchVisiteHospitalisationMetaApi(visite?.id);
      setHospMeta(meta);
    } catch (err) {
      setHospMeta({ blocs: [] });
      setHospMetaError(err.message || 'Impossible de charger les blocs et lits.');
    } finally {
      setHospMetaLoading(false);
    }
  };

  const handleAction = (statut) => {
    if (statut === 'HOSPITALISE') {
      openHospitalisationModal();
      return;
    }
    if (statut === 'TERMINEE' || statut === 'ANNULEE') {
      setConfirmStatut(statut);
      return;
    }
    onTransition?.({ statut });
  };

  const handleHospitalisation = (litId) => {
    setHospModalOpen(false);
    onTransition?.({ statut: 'HOSPITALISE', litId });
  };

  const handleConfirmTransition = () => {
    if (!confirmStatut) return;
    onTransition?.({ statut: confirmStatut });
    setConfirmStatut(null);
  };

  return (
    <>
      <Stack direction="row" spacing={0.75} alignItems="center" flexWrap="nowrap" useFlexGap sx={{ whiteSpace: 'nowrap' }}>
        {transitions.map((statut) => {
          const props = transitionProps(statut);
          return (
            <Button
              key={statut}
              size="sm"
              variant={props.variant}
              color={props.color}
              loading={loading && statut !== 'HOSPITALISE' && statut !== confirmStatut}
              onClick={() => handleAction(statut)}
            >
              {VISITE_TRANSITION_LABELS[statut] ?? statut}
            </Button>
          );
        })}
      </Stack>

      <VisiteHospitalisationModal
        open={hospModalOpen}
        visite={visite}
        blocs={hospMeta.blocs ?? []}
        metaLoading={hospMetaLoading}
        loading={loading}
        error={hospMetaError || error}
        onClose={() => setHospModalOpen(false)}
        onConfirm={handleHospitalisation}
      />

      <VisiteTransitionConfirmModal
        open={confirmStatut !== null}
        statut={confirmStatut}
        visite={visite}
        loading={loading}
        onClose={() => setConfirmStatut(null)}
        onConfirm={handleConfirmTransition}
      />
    </>
  );
}
