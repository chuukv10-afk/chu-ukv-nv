import { todayIso } from './format.js';

export const PERIOD_OPTIONS = [
  { value: 'today', label: 'Aujourd’hui' },
  { value: 'week', label: 'Cette semaine' },
  { value: 'month', label: 'Ce mois' },
  { value: 'year', label: 'Cette année' },
  { value: 'custom', label: 'Personnalisé' },
];

function pad(value) {
  return String(value).padStart(2, '0');
}

function toIso(date) {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export function resolvePeriodRange(period, customFrom = '', customTo = '') {
  const today = todayIso();
  const now = new Date();

  if (period === 'today') {
    return { dateFrom: today, dateTo: today };
  }

  if (period === 'week') {
    const weekday = now.getDay();
    const mondayOffset = weekday === 0 ? -6 : 1 - weekday;
    const monday = new Date(now.getFullYear(), now.getMonth(), now.getDate() + mondayOffset);
    return { dateFrom: toIso(monday), dateTo: today };
  }

  if (period === 'month') {
    return { dateFrom: `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`, dateTo: today };
  }

  if (period === 'year') {
    return { dateFrom: `${now.getFullYear()}-01-01`, dateTo: today };
  }

  if (period === 'custom') {
    return {
      dateFrom: customFrom || undefined,
      dateTo: customTo || undefined,
    };
  }

  return {};
}
