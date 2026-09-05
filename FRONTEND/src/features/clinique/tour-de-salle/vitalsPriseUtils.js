export function parseVitalNumber(value) {
  if (value === null || value === undefined || value === '') return null;
  const text = String(value).trim().replace(',', '.');
  const match = text.match(/-?\d+(\.\d+)?/);
  if (!match) return null;
  const number = Number(match[0]);
  return Number.isFinite(number) ? number : null;
}

export function formatDateTime(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export function collectMesures(vitals) {
  return [
    ...(Array.isArray(vitals?.consultationMesures) ? vitals.consultationMesures : []),
    ...(Array.isArray(vitals?.triageMesures) ? vitals.triageMesures : []),
  ].sort((a, b) => new Date(b.measuredAt ?? 0) - new Date(a.measuredAt ?? 0));
}

export function latestValueBySigne(mesures) {
  const latest = {};
  mesures.forEach((mesure) => {
    const id = String(mesure.signeVitalId ?? '');
    if (!id || latest[id] !== undefined) return;
    latest[id] = mesure.valeur ?? '';
  });
  return latest;
}

export function groupPrises(mesures) {
  const groups = new Map();

  mesures.forEach((mesure) => {
    const key = mesure.measuredAt || `${mesure.source}-${mesure.id}`;
    if (!groups.has(key)) {
      groups.set(key, {
        key,
        measuredAt: mesure.measuredAt,
        measuredBy: mesure.measuredBy?.fullName ?? '—',
        source: mesure.source,
        values: {},
      });
    }
    const signeId = String(mesure.signeVitalId ?? mesure.id);
    groups.get(key).values[signeId] = mesure;
  });

  return Array.from(groups.values())
    .sort((a, b) => new Date(b.measuredAt ?? 0) - new Date(a.measuredAt ?? 0));
}

export function buildChartRows(prises, selectedSigneIds) {
  return [...prises]
    .filter((prise) => prise.measuredAt)
    .sort((a, b) => new Date(a.measuredAt) - new Date(b.measuredAt))
    .map((prise) => {
      const row = { time: formatDateTime(prise.measuredAt) };
      selectedSigneIds.forEach((signeId) => {
        row[signeId] = parseVitalNumber(prise.values[signeId]?.valeur);
      });
      return row;
    });
}

export const CHART_COLORS = [
  '#4f46e5',
  '#d97706',
  '#059669',
  '#e02222',
  '#2563eb',
  '#7c3aed',
  '#0d9488',
  '#be185d',
];
