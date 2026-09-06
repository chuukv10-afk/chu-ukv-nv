import { useEffect, useMemo, useState } from 'react';
import { Button, Stack } from '@mui/joy';
import { Save } from 'lucide-react';
import { useToast } from '../../../hooks/useToast.js';
import { updateConsultationApi } from '../consultations/consultationsApi.js';
import { EMPTY_EVOLUTION_SHEET } from '../tour-de-salle/evolutionSheetConstants.js';
import ComplaintsSection from './ComplaintsSection.jsx';
import { normalizeSymptoms } from './complaintUtils.js';

export default function ConsultationPlaintesTab({
  consultation,
  readOnly = false,
  onSaved,
}) {
  const { showSuccess, showError } = useToast();
  const initialSymptoms = useMemo(
    () => normalizeSymptoms(consultation?.evolutionSheet?.symptoms ?? EMPTY_EVOLUTION_SHEET.symptoms),
    [consultation],
  );

  const [symptoms, setSymptoms] = useState(initialSymptoms);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    setSymptoms(initialSymptoms);
  }, [initialSymptoms]);

  const handleSave = async () => {
    if (!consultation?.id || readOnly) return;

    if (
      symptoms.mode === 'COMPLAINTS'
      && symptoms.selectedComplaints.length === 0
      && !symptoms.freeText?.trim()
    ) {
      showError('Citez au moins une plainte ou précisez le texte libre.');
      return;
    }

    setSaving(true);
    try {
      const evolutionSheet = {
        ...(consultation.evolutionSheet ?? EMPTY_EVOLUTION_SHEET),
        symptoms,
      };
      const updated = await updateConsultationApi(consultation.id, { evolutionSheet });
      onSaved?.(updated);
      showSuccess('Plaintes enregistrées.');
    } catch (err) {
      showError(err.message || 'Enregistrement impossible.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Stack spacing={2}>
      <ComplaintsSection
        symptoms={symptoms}
        onChange={setSymptoms}
        readOnly={readOnly}
        title="Plaintes du patient"
      />
      {!readOnly ? (
        <Stack direction="row" justifyContent="flex-end">
          <Button loading={saving} startDecorator={<Save size={16} />} onClick={handleSave}>
            Enregistrer
          </Button>
        </Stack>
      ) : null}
    </Stack>
  );
}
