import { useEffect, useState } from 'react';
import { fetchDashboardStatsApi } from './dashboardApi.js';

const DEFAULT_STATS = {
  services: 0,
  dossiersPatient: 0,
  personnel: 0,
  consultationsJour: 0,
};

const EMPTY_WEEK = [
  { day: 'Lun', current: 0, previous: 0 },
  { day: 'Mar', current: 0, previous: 0 },
  { day: 'Mer', current: 0, previous: 0 },
  { day: 'Jeu', current: 0, previous: 0 },
  { day: 'Ven', current: 0, previous: 0 },
  { day: 'Sam', current: 0, previous: 0 },
  { day: 'Dim', current: 0, previous: 0 },
];

function readKpi(kpis, key) {
  const item = kpis?.[key];
  if (item && typeof item === 'object' && 'value' in item) {
    return item;
  }

  return { value: Number(item ?? 0), trend: null, trendLabel: null };
}

export function useDashboardStats(enabled = true) {
  const [stats, setStats] = useState(DEFAULT_STATS);
  const [trends, setTrends] = useState({});
  const [trendLabels, setTrendLabels] = useState({});
  const [weeklyActivity, setWeeklyActivity] = useState(EMPTY_WEEK);
  const [moduleDistribution, setModuleDistribution] = useState([]);
  const [loading, setLoading] = useState(Boolean(enabled));
  const [error, setError] = useState('');

  useEffect(() => {
    if (!enabled) {
      setLoading(false);
      return undefined;
    }

    let cancelled = false;

    const load = async () => {
      setLoading(true);
      setError('');

      try {
        const data = await fetchDashboardStatsApi();
        if (cancelled) {
          return;
        }

        const kpis = data?.kpis ?? {};
        const nextStats = { ...DEFAULT_STATS };
        const nextTrends = {};
        const nextLabels = {};

        Object.keys(DEFAULT_STATS).forEach((key) => {
          const kpi = readKpi(kpis, key);
          nextStats[key] = Number(kpi.value ?? 0);
          nextTrends[key] = kpi.trend ?? null;
          nextLabels[key] = kpi.trendLabel ?? null;
        });

        setStats(nextStats);
        setTrends(nextTrends);
        setTrendLabels(nextLabels);
        setWeeklyActivity(Array.isArray(data?.weeklyActivity) && data.weeklyActivity.length
          ? data.weeklyActivity
          : EMPTY_WEEK);
        setModuleDistribution(Array.isArray(data?.moduleDistribution) ? data.moduleDistribution : []);
      } catch (err) {
        if (!cancelled) {
          setError(err.message || 'Impossible de charger le tableau de bord.');
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    };

    load();

    return () => {
      cancelled = true;
    };
  }, [enabled]);

  return {
    stats,
    trends,
    trendLabels,
    weeklyActivity,
    moduleDistribution,
    loading,
    error,
  };
}
