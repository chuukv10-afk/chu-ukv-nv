import { createEmptyPhysicalExam, normalizePhysicalExam } from '../consultations/utils/physicalExamSchema.js';
import { EMPTY_EVOLUTION_SHEET } from './evolutionSheetConstants.js';

export function buildEvolutionForm(consultation) {
  const sheet = consultation?.evolutionSheet ?? EMPTY_EVOLUTION_SHEET;

  return {
    histoireMaladie: consultation?.histoireMaladie ?? '',
    physicalExam: normalizePhysicalExam(consultation?.physicalExam ?? createEmptyPhysicalExam()),
    physicalExamText: consultation?.physicalExamText ?? '',
    conduireATenir: consultation?.conduireATenir ?? '',
    evolutionSheet: {
      ...EMPTY_EVOLUTION_SHEET,
      ...sheet,
      symptoms: {
        ...EMPTY_EVOLUTION_SHEET.symptoms,
        ...(sheet.symptoms ?? {}),
        selectedComplaints: Array.isArray(sheet.symptoms?.selectedComplaints)
          ? sheet.symptoms.selectedComplaints
          : [],
      },
      clinicalEvaluation: {
        ...EMPTY_EVOLUTION_SHEET.clinicalEvaluation,
        ...(sheet.clinicalEvaluation ?? {}),
      },
    },
  };
}

export function formatStayDuration(hospitalizedAt) {
  if (!hospitalizedAt) return '';
  const startedAt = new Date(hospitalizedAt);
  if (Number.isNaN(startedAt.getTime())) return '';
  const diffMs = Date.now() - startedAt.getTime();
  if (diffMs <= 0) return '0 h';
  const totalHours = Math.floor(diffMs / (1000 * 60 * 60));
  const days = Math.floor(totalHours / 24);
  const hours = totalHours % 24;
  if (days <= 0) return `${hours} h`;
  if (hours <= 0) return `${days} j`;
  return `${days} j ${hours} h`;
}

export function formatTourDate(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return date.toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export function summarizeLatestVitals(vitals) {
  const mesures = [
    ...(Array.isArray(vitals?.consultationMesures) ? vitals.consultationMesures : []),
    ...(Array.isArray(vitals?.triageMesures) ? vitals.triageMesures : []),
  ];
  if (mesures.length === 0) return '';

  const latestByCode = new Map();
  mesures.forEach((mesure) => {
    const code = mesure.code || mesure.libelle;
    if (!code || latestByCode.has(code)) return;
    latestByCode.set(code, mesure);
  });

  return Array.from(latestByCode.values())
    .slice(0, 6)
    .map((mesure) => {
      const unit = mesure.unite ? ` ${mesure.unite}` : '';
      return `${mesure.libelle ?? mesure.code} ${mesure.valeur ?? '—'}${unit}`;
    })
    .join(' · ');
}
