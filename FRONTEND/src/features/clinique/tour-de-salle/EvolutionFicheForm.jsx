import { useCallback, useEffect, useState } from 'react';
import {
  Accordion,
  AccordionDetails,
  AccordionGroup,
  AccordionSummary,
  Box,
  Button,
  Card,
  Checkbox,
  Chip,
  FormControl,
  FormLabel,
  Radio,
  RadioGroup,
  Stack,
  Textarea,
  Typography,
} from '@mui/joy';
import { ChevronDown, Save } from 'lucide-react';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL } from '../../../theme/lotruPalette.js';
import { getRecordLockReason } from '../consultations/consultationConstants.js';
import { fetchConsultationVitalsApi, updateConsultationApi } from '../consultations/consultationsApi.js';
import PhysicalExamForm from '../consultations/components/PhysicalExamForm.jsx';
import StayDiagnosticsCard from '../diagnostics/StayDiagnosticsCard.jsx';
import DiagnosticsTab from '../diagnostics/DiagnosticsTab.jsx';
import {
  CLINICAL_EVALUATION_OPTIONS,
  DIAGNOSIS_EVOLUTION_MODES,
} from './evolutionSheetConstants.js';
import ComplaintsSection from '../plaintes/ComplaintsSection.jsx';
import {
  buildEvolutionForm,
  formatStayDuration,
  formatTourDate,
  summarizeLatestVitals,
} from './evolutionSheetUtils.js';

export default function EvolutionFicheForm({ consultation, onSaved }) {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();

  const canUpdate = hasPermission(PERMISSIONS.CLINIQUE.CONSULTATION_UPDATE);
  const canReadDiagnostic = hasPermission(PERMISSIONS.CLINIQUE.DIAGNOSTIC_READ);
  const canCreateDiagnostic = hasPermission(PERMISSIONS.CLINIQUE.DIAGNOSTIC_CREATE);
  const canDeleteDiagnostic = hasPermission(PERMISSIONS.CLINIQUE.DIAGNOSTIC_DELETE);

  const recordLockReason = getRecordLockReason(consultation);
  const locked = Boolean(
    consultation?.isClosed
    || consultation?.isEditable === false
    || consultation?.recordWritable === false
    || recordLockReason,
  );
  const readOnly = locked || !canUpdate;

  const [form, setForm] = useState(() => buildEvolutionForm(consultation));
  const [saving, setSaving] = useState(false);
  const [vitalsSummary, setVitalsSummary] = useState('');
  const [stayDiagnosticsTick, setStayDiagnosticsTick] = useState(0);

  useEffect(() => {
    setForm(buildEvolutionForm(consultation));
  }, [consultation]);

  const loadVitals = useCallback(async () => {
    if (!consultation?.id) return;
    try {
      const data = await fetchConsultationVitalsApi(consultation.id);
      setVitalsSummary(summarizeLatestVitals(data));
    } catch {
      setVitalsSummary('');
    }
  }, [consultation?.id]);

  useEffect(() => {
    loadVitals();
  }, [loadVitals]);

  const updateSheet = (path, value) => {
    setForm((current) => {
      const next = {
        ...current,
        evolutionSheet: { ...current.evolutionSheet },
      };
      if (path === 'symptoms.mode') {
        next.evolutionSheet.symptoms = { ...next.evolutionSheet.symptoms, mode: value };
      } else if (path === 'symptoms.freeText') {
        next.evolutionSheet.symptoms = { ...next.evolutionSheet.symptoms, freeText: value };
      } else if (path === 'symptoms.selectedComplaints') {
        next.evolutionSheet.symptoms = { ...next.evolutionSheet.symptoms, selectedComplaints: value };
      } else if (path === 'symptoms') {
        next.evolutionSheet.symptoms = value;
      } else if (path === 'clinicalEvaluation.type') {
        next.evolutionSheet.clinicalEvaluation = { ...next.evolutionSheet.clinicalEvaluation, type: value };
      } else if (path === 'clinicalEvaluation.worseningDetails') {
        next.evolutionSheet.clinicalEvaluation = { ...next.evolutionSheet.clinicalEvaluation, worseningDetails: value };
      } else if (path === 'diagnosisEvolutionMode') {
        next.evolutionSheet.diagnosisEvolutionMode = value;
      } else if (path === 'continueCurrentTreatment') {
        next.evolutionSheet.continueCurrentTreatment = value;
      }
      return next;
    });
  };

  const handleSave = async () => {
    if (readOnly) return;

    const sheet = form.evolutionSheet;
    if (!sheet.clinicalEvaluation.type) {
      showError('Sélectionnez une évaluation clinique.');
      return;
    }
    if (
      sheet.symptoms.mode === 'COMPLAINTS'
      && (sheet.symptoms.selectedComplaints || []).length === 0
      && !sheet.symptoms.freeText?.trim()
    ) {
      showError('Citez au moins une plainte ou précisez le texte libre.');
      return;
    }

    setSaving(true);
    try {
      const updated = await updateConsultationApi(consultation.id, {
        histoireMaladie: form.histoireMaladie,
        physicalExam: form.physicalExam,
        physicalExamText: form.physicalExamText,
        conduireATenir: form.conduireATenir,
        evolutionSheet: sheet,
      });
      setForm(buildEvolutionForm(updated));
      onSaved?.(updated);
      showSuccess('Fiche d’évolution enregistrée.');
    } catch (err) {
      showError(err.message || 'Enregistrement impossible.');
    } finally {
      setSaving(false);
    }
  };

  const stayDuration = formatStayDuration(consultation.hospitalizedAt);
  const previousTour = formatTourDate(consultation.previousTourAt);
  const isEvolved = form.evolutionSheet.diagnosisEvolutionMode === 'EVOLVED';

  return (
    <Stack spacing={2.5}>
      {recordLockReason ? (
        <Typography level="body-sm" color="warning" sx={{ bgcolor: 'warning.50', p: 1.5, borderRadius: 'md' }}>
          {recordLockReason}
        </Typography>
      ) : null}

      {previousTour ? (
        <Chip size="sm" variant="soft" color="primary" sx={{ alignSelf: 'flex-start' }}>
          Reprise de l’évolution du {previousTour}
        </Chip>
      ) : null}

      <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
        <Typography level="title-sm" sx={{ fontWeight: 700, mb: 1.5 }}>Rappel du séjour</Typography>
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} sx={{ mb: 1.5 }}>
          <Box sx={{ flex: 1 }}>
            <Typography level="body-xs" sx={{ color: LOTRU_NEUTRAL[500] }}>Durée d’hospitalisation</Typography>
            <Typography level="body-sm" sx={{ fontWeight: 600 }}>{stayDuration || '—'}</Typography>
          </Box>
          <Box sx={{ flex: 2 }}>
            <Typography level="body-xs" sx={{ color: LOTRU_NEUTRAL[500] }}>Dernières constantes</Typography>
            <Typography level="body-sm" sx={{ fontWeight: 600 }}>{vitalsSummary || '—'}</Typography>
          </Box>
        </Stack>
        {canReadDiagnostic ? (
          <StayDiagnosticsCard key={stayDiagnosticsTick} consultationId={consultation.id} />
        ) : (
          <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600] }}>
            Permission insuffisante pour voir les diagnostics du séjour.
          </Typography>
        )}
      </Card>

      <ComplaintsSection
        symptoms={form.evolutionSheet.symptoms}
        onChange={(symptoms) => updateSheet('symptoms', symptoms)}
        readOnly={readOnly}
        title="Plaintes du jour"
      />

      <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
        <FormControl>
          <FormLabel sx={{ fontWeight: 700 }}>Évolution depuis le dernier passage</FormLabel>
          <Textarea
            minRows={3}
            value={form.histoireMaladie}
            onChange={(event) => setForm((current) => ({ ...current, histoireMaladie: event.target.value }))}
            readOnly={readOnly}
            placeholder="État actuel, ce qui s’améliore ou s’aggrave…"
          />
        </FormControl>
      </Card>

      <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
        <AccordionGroup>
          <Accordion defaultExpanded={false}>
            <AccordionSummary indicator={<ChevronDown size={18} />}>
              <Box>
                <Typography level="title-sm" sx={{ fontWeight: 700 }}>Examen physique</Typography>
                <Typography level="body-xs" sx={{ color: LOTRU_NEUTRAL[500] }}>
                  Optionnel — dérouler pour renseigner les sections concernées
                </Typography>
              </Box>
            </AccordionSummary>
            <AccordionDetails>
              <PhysicalExamForm
                physicalExam={form.physicalExam}
                setPhysicalExam={(value) => setForm((current) => ({ ...current, physicalExam: value }))}
                readOnly={readOnly}
                patientGender={consultation.patientSexe}
              />
              <FormControl sx={{ mt: 1.5 }}>
                <FormLabel>Notes complémentaires</FormLabel>
                <Textarea
                  minRows={2}
                  value={form.physicalExamText}
                  onChange={(event) => setForm((current) => ({ ...current, physicalExamText: event.target.value }))}
                  readOnly={readOnly}
                />
              </FormControl>
            </AccordionDetails>
          </Accordion>
        </AccordionGroup>
      </Card>

      <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
        <Typography level="title-sm" sx={{ fontWeight: 700, mb: 1 }}>Évaluation clinique</Typography>
        <RadioGroup
          value={form.evolutionSheet.clinicalEvaluation.type}
          onChange={(event) => updateSheet('clinicalEvaluation.type', event.target.value)}
        >
          {CLINICAL_EVALUATION_OPTIONS.map((option) => (
            <Radio key={option.value} value={option.value} label={option.label} disabled={readOnly} />
          ))}
        </RadioGroup>
        {form.evolutionSheet.clinicalEvaluation.type === 'WORSENING' ? (
          <FormControl sx={{ mt: 1.5 }}>
            <FormLabel>Motif de l’aggravation</FormLabel>
            <Textarea
              minRows={2}
              value={form.evolutionSheet.clinicalEvaluation.worseningDetails}
              onChange={(event) => updateSheet('clinicalEvaluation.worseningDetails', event.target.value)}
              readOnly={readOnly}
            />
          </FormControl>
        ) : null}
      </Card>

      <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
        <Typography level="title-sm" sx={{ fontWeight: 700, mb: 1 }}>Diagnostics du séjour</Typography>
        <RadioGroup
          value={form.evolutionSheet.diagnosisEvolutionMode}
          onChange={(event) => updateSheet('diagnosisEvolutionMode', event.target.value)}
          sx={{ mb: 1.5 }}
        >
          {DIAGNOSIS_EVOLUTION_MODES.map((option) => (
            <Radio key={option.value} value={option.value} label={option.label} disabled={readOnly} />
          ))}
        </RadioGroup>
        {isEvolved && canReadDiagnostic ? (
          <DiagnosticsTab
            consultationId={consultation.id}
            patientId={consultation.patientId}
            readOnly={locked}
            canCreate={canCreateDiagnostic}
            canDelete={canDeleteDiagnostic}
            stayScope
            onRecordsChange={() => setStayDiagnosticsTick((current) => current + 1)}
          />
        ) : null}
        {isEvolved && !canReadDiagnostic ? (
          <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600] }}>
            Permission insuffisante pour modifier les diagnostics.
          </Typography>
        ) : null}
      </Card>

      <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
        <Typography level="title-sm" sx={{ fontWeight: 700, mb: 1 }}>Conduite à tenir</Typography>
        <Checkbox
          label="Poursuivre l’attitude thérapeutique en cours"
          checked={Boolean(form.evolutionSheet.continueCurrentTreatment)}
          disabled={readOnly}
          onChange={(event) => updateSheet('continueCurrentTreatment', event.target.checked)}
          sx={{ mb: 1.5 }}
        />
        <FormControl>
          <FormLabel>Consignes</FormLabel>
          <Textarea
            minRows={3}
            value={form.conduireATenir}
            onChange={(event) => setForm((current) => ({ ...current, conduireATenir: event.target.value }))}
            readOnly={readOnly}
            placeholder="Surveillance, suite de l’hospitalisation…"
          />
        </FormControl>
      </Card>

      {!readOnly ? (
        <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
          <Button loading={saving} startDecorator={<Save size={16} />} onClick={handleSave}>
            Enregistrer
          </Button>
        </Box>
      ) : null}
    </Stack>
  );
}
