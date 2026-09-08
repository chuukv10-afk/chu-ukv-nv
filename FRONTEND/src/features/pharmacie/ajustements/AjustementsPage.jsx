import { useEffect, useState } from 'react';
import {
  Box, Button, Card, FormControl, FormHelperText, FormLabel, Input, Option, Select, Stack, Typography,
} from '@mui/joy';
import { SlidersHorizontal } from 'lucide-react';
import ConfirmModal from '../../../components/ui/ConfirmModal.jsx';
import { useToast } from '../../../hooks/useToast.js';
import OfflineHint from '../../../offline/OfflineHint.jsx';
import { LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { createAjustementApi } from './ajustementsApi.js';
import { fetchLotsApi } from '../lots/lotsApi.js';
import { fetchMedicamentsActifsApi } from '../medicaments/medicamentsApi.js';
import MedicamentAutocomplete from '../shared/MedicamentAutocomplete.jsx';

const TYPES = [
  { value: 'AJUSTEMENT_PLUS', label: 'Ajustement + (entrée)' },
  { value: 'AJUSTEMENT_MOINS', label: 'Ajustement − (sortie)' },
  { value: 'SORTIE_PERTE', label: 'Perte' },
  { value: 'SORTIE_PEREMPTION', label: 'Péremption' },
];

export default function AjustementsPage() {
  const { showSuccess, showError } = useToast();
  const [medicaments, setMedicaments] = useState([]);
  const [lots, setLots] = useState([]);
  const [medicamentId, setMedicamentId] = useState('');
  const [lotId, setLotId] = useState('');
  const [type, setType] = useState('AJUSTEMENT_MOINS');
  const [quantite, setQuantite] = useState(1);
  const [motif, setMotif] = useState('');
  const [saving, setSaving] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);

  useEffect(() => {
    fetchMedicamentsActifsApi().then(setMedicaments).catch(() => setMedicaments([]));
  }, []);

  const loadLots = (id) => {
    if (!id) {
      setLots([]);
      setLotId('');
      return;
    }
    fetchLotsApi({ page: 1, limit: 100, medicamentId: id })
      .then((result) => setLots(result.items))
      .catch(() => setLots([]));
  };

  useEffect(() => {
    loadLots(medicamentId);
  }, [medicamentId]);

  const handleSubmit = async () => {
    setSaving(true);
    try {
      await createAjustementApi({
        lotId: Number(lotId),
        type,
        quantite: Number(quantite),
        motif: motif.trim(),
      });
      showSuccess('Ajustement enregistré.');
      setConfirmOpen(false);
      setQuantite(1);
      setMotif('');
      loadLots(medicamentId);
    } catch (error) {
      showError(error.message || 'Ajustement impossible.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={2.5} sx={{ maxWidth: 720 }}>
        <OfflineHint>
          Ajustement hors-ligne : le stock local est mis à jour tout de suite. Le serveur rejoue le mouvement à la reconnexion.
        </OfflineHint>
        <Stack direction="row" spacing={1.5} alignItems="center">
          <SlidersHorizontal size={24} color={LOTRU_PRIMARY[600]} />
          <Box>
            <Typography level="h2" sx={{ fontWeight: 700 }}>Ajustements de stock</Typography>
            <Typography level="body-md" sx={{ color: 'neutral.500' }}>
              Pertes, péremption ou correction d’inventaire. Un motif est obligatoire.
            </Typography>
          </Box>
        </Stack>
        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5 }}>
          <Stack spacing={2}>
            <FormControl required>
              <FormLabel>Médicament</FormLabel>
              <MedicamentAutocomplete options={medicaments} valueId={medicamentId} onSelect={(item) => { setMedicamentId(item ? String(item.id) : ''); setLotId(''); }} />
            </FormControl>
            <FormControl required>
              <FormLabel>Lot</FormLabel>
              <Select value={lotId || null} onChange={(_, value) => setLotId(value ?? '')} disabled={!medicamentId} placeholder="Choisir un lot">
                {lots.map((lot) => (
                  <Option key={lot.id} value={String(lot.id)}>{lot.numeroLot} — reste {lot.quantiteRestante}</Option>
                ))}
              </Select>
            </FormControl>
            <FormControl required>
              <FormLabel>Type</FormLabel>
              <Select value={type} onChange={(_, value) => setType(value ?? 'AJUSTEMENT_MOINS')}>
                {TYPES.map((item) => <Option key={item.value} value={item.value}>{item.label}</Option>)}
              </Select>
            </FormControl>
            <FormControl required>
              <FormLabel>Quantité</FormLabel>
              <Input type="number" value={quantite} onChange={(e) => setQuantite(e.target.value)} slotProps={{ input: { min: 1 } }} />
            </FormControl>
            <FormControl required>
              <FormLabel>Motif</FormLabel>
              <Input value={motif} onChange={(e) => setMotif(e.target.value)} slotProps={{ input: { maxLength: 255 } }} />
              <FormHelperText>Visible dans le journal de stock.</FormHelperText>
            </FormControl>
            <Button onClick={() => setConfirmOpen(true)} disabled={!lotId || !motif.trim()}>Enregistrer l’ajustement</Button>
          </Stack>
        </Card>
      </Stack>
      <ConfirmModal
        open={confirmOpen}
        title="Confirmer l’ajustement"
        message="Le stock du lot sera modifié immédiatement."
        confirmLabel="Confirmer"
        color="warning"
        loading={saving}
        onClose={() => setConfirmOpen(false)}
        onConfirm={handleSubmit}
      />
    </Box>
  );
}
