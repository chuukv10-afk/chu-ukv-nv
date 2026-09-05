import { useState } from 'react';
import { Button, Stack } from '@mui/joy';
import ConsultationTransitionConfirmModal from './ConsultationTransitionConfirmModal.jsx';
import { CONSULTATION_TRANSITION_LABELS } from '../consultationConstants.js';

const TRANSITION_ORDER = ['EN_COURS', 'TERMINEE', 'ANNULEE'];

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

export default function ConsultationTransitionPanel({
  consultation,
  canUpdate = false,
  loading = false,
  onTransition,
}) {
  const [confirmStatut, setConfirmStatut] = useState(null);
  const transitions = sortTransitions(consultation?.allowedTransitions ?? []);

  if (!canUpdate || transitions.length === 0) {
    return null;
  }

  const handleAction = (statut) => {
    if (statut === 'TERMINEE' || statut === 'ANNULEE') {
      setConfirmStatut(statut);
      return;
    }
    onTransition?.({ statut });
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
              loading={loading && statut !== confirmStatut}
              onClick={() => handleAction(statut)}
            >
              {CONSULTATION_TRANSITION_LABELS[statut] ?? statut}
            </Button>
          );
        })}
      </Stack>

      <ConsultationTransitionConfirmModal
        open={confirmStatut !== null}
        statut={confirmStatut}
        consultation={consultation}
        loading={loading}
        onClose={() => setConfirmStatut(null)}
        onConfirm={handleConfirmTransition}
      />
    </>
  );
}
