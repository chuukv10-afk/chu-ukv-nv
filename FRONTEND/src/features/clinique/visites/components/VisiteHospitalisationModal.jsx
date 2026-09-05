import { useEffect, useMemo, useState } from 'react';
import {
  Box, Button, FormControl, FormHelperText, FormLabel, Modal, ModalDialog, Option, Select, Stack, Typography,
} from '@mui/joy';
import { BedDouble } from 'lucide-react';
import { LOTRU_PRIMARY } from '../../../../theme/lotruPalette.js';

const MODAL_SX = {
  borderRadius: 'xl',
  maxWidth: 520,
  width: '100%',
  p: 0,
  overflow: 'hidden',
  boxShadow: 'lg',
  maxHeight: 'min(90vh, 720px)',
  display: 'flex',
  flexDirection: 'column',
};

function findLitPath(blocs, litId) {
  if (!litId) {
    return { blocId: '', chambreId: '', litId: '' };
  }

  for (const bloc of blocs) {
    for (const chambre of bloc.chambres ?? []) {
      const lit = (chambre.lits ?? []).find((item) => String(item.id) === String(litId));
      if (lit) {
        return {
          blocId: String(bloc.id),
          chambreId: String(chambre.id),
          litId: String(lit.id),
        };
      }
    }
  }

  return { blocId: '', chambreId: '', litId: String(litId) };
}

export default function VisiteHospitalisationModal({
  open,
  visite,
  blocs = [],
  metaLoading = false,
  loading = false,
  error = '',
  onClose,
  onConfirm,
}) {
  const currentLitId = visite?.litId ?? visite?.lit?.id ?? '';
  const [blocId, setBlocId] = useState('');
  const [chambreId, setChambreId] = useState('');
  const [litId, setLitId] = useState('');
  const [localError, setLocalError] = useState('');

  const hierarchy = useMemo(() => blocs ?? [], [blocs]);

  const selectedBloc = hierarchy.find((bloc) => String(bloc.id) === String(blocId));
  const chambres = selectedBloc?.chambres ?? [];
  const selectedChambre = chambres.find((chambre) => String(chambre.id) === String(chambreId));
  const availableLits = selectedChambre?.lits ?? [];

  useEffect(() => {
    if (!open) return;
    const initial = findLitPath(hierarchy, currentLitId);
    setBlocId(initial.blocId);
    setChambreId(initial.chambreId);
    setLitId(initial.litId);
    setLocalError('');
  }, [open, hierarchy, currentLitId]);

  const handleBlocChange = (value) => {
    setBlocId(value ?? '');
    setChambreId('');
    setLitId('');
    setLocalError('');
  };

  const handleChambreChange = (value) => {
    setChambreId(value ?? '');
    setLitId('');
    setLocalError('');
  };

  const handleSubmit = (event) => {
    event.preventDefault();
    if (!blocId) {
      setLocalError('Veuillez sélectionner un bloc.');
      return;
    }
    if (!chambreId) {
      setLocalError('Veuillez sélectionner une chambre.');
      return;
    }
    if (!litId) {
      setLocalError('Veuillez sélectionner un lit.');
      return;
    }
    setLocalError('');
    onConfirm(Number(litId));
  };

  const displayError = error || localError;
  const hasOptions = hierarchy.length > 0;
  const isBusy = loading || metaLoading;

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog variant="outlined" sx={MODAL_SX}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider', flexShrink: 0 }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ width: 40, height: 40, borderRadius: 'md', bgcolor: LOTRU_PRIMARY[50], color: LOTRU_PRIMARY[600], display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <BedDouble size={20} />
            </Box>
            <Box>
              <Typography level="title-lg" sx={{ fontWeight: 700 }}>Hospitaliser le patient</Typography>
              <Typography level="body-xs" color="neutral">
                {visite?.patientName ?? visite?.service?.libelle ?? 'Visite'}
              </Typography>
            </Box>
          </Stack>
        </Box>

        <Box
          component="form"
          onSubmit={handleSubmit}
          sx={{ display: 'flex', flexDirection: 'column', flex: 1, minHeight: 0 }}
        >
          <Box sx={{ p: 3, overflow: 'auto', flex: 1 }}>
            <Stack spacing={2}>
              {displayError ? (
                <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
                  {displayError}
                </Typography>
              ) : null}

              {metaLoading ? (
                <Typography level="body-sm" color="neutral">Chargement des blocs et lits…</Typography>
              ) : null}

              <FormControl required>
                <FormLabel>Bloc</FormLabel>
                <Select
                  value={blocId}
                  onChange={(_, value) => handleBlocChange(value ?? '')}
                  placeholder="Choisir un bloc"
                  disabled={isBusy || !hasOptions}
                >
                  {hierarchy.map((bloc) => (
                    <Option key={bloc.id} value={String(bloc.id)}>
                      {bloc.libelle}{bloc.code ? ` (${bloc.code})` : ''}
                    </Option>
                  ))}
                </Select>
              </FormControl>

              <FormControl required>
                <FormLabel>Chambre</FormLabel>
                <Select
                  value={chambreId}
                  onChange={(_, value) => handleChambreChange(value ?? '')}
                  placeholder={blocId ? 'Choisir une chambre' : 'Sélectionnez d\'abord un bloc'}
                  disabled={isBusy || !blocId || chambres.length === 0}
                >
                  {chambres.map((chambre) => (
                    <Option key={chambre.id} value={String(chambre.id)}>
                      {chambre.libelle}{chambre.code ? ` (${chambre.code})` : ''}
                    </Option>
                  ))}
                </Select>
                {blocId && chambres.length === 0 ? (
                  <FormHelperText>Aucune chambre avec lit disponible dans ce bloc.</FormHelperText>
                ) : null}
              </FormControl>

              <FormControl required>
                <FormLabel>Lit</FormLabel>
                <Select
                  value={litId}
                  onChange={(_, value) => {
                    setLitId(value ?? '');
                    setLocalError('');
                  }}
                  placeholder={chambreId ? 'Choisir un lit' : 'Sélectionnez d\'abord une chambre'}
                  disabled={isBusy || !chambreId || availableLits.length === 0}
                >
                  {availableLits.map((lit) => (
                    <Option key={lit.id} value={String(lit.id)}>
                      {lit.code} — Lit {lit.numeroLit}
                    </Option>
                  ))}
                </Select>
                {chambreId && availableLits.length === 0 ? (
                  <FormHelperText>Aucun lit disponible dans cette chambre.</FormHelperText>
                ) : null}
              </FormControl>

              {!metaLoading && !hasOptions ? (
                <FormHelperText>
                  Aucun lit disponible. Vérifiez que des lits sont rattachés à une chambre et un bloc dans Organisation.
                </FormHelperText>
              ) : null}
            </Stack>
          </Box>

          <Stack
            direction="row"
            spacing={1.5}
            justifyContent="flex-end"
            sx={{ px: 3, py: 2, borderTop: '1px solid', borderColor: 'divider', flexShrink: 0 }}
          >
            <Button variant="plain" color="neutral" onClick={onClose} disabled={isBusy}>Annuler</Button>
            <Button type="submit" loading={loading} disabled={isBusy || !hasOptions}>
              Confirmer l&apos;hospitalisation
            </Button>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
