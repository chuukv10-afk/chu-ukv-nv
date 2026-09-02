import { useEffect, useState } from 'react';

const DEFAULT_STATS = {
  services: 0,
  dossiersPatient: 0,
  personnel: 0,
  consultationsJour: 0,
};

/** Données mock en attendant les endpoints agrégés backend */
const MOCK_TRENDS = {
  services: 8,
  dossiersPatient: 24,
  personnel: 5,
  consultationsJour: 18,
};

const MOCK_STATS = {
  services: 42,
  dossiersPatient: 1280,
  personnel: 356,
  consultationsJour: 87,
};

export function useDashboardStats() {
  const [stats, setStats] = useState(DEFAULT_STATS);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;

    const load = async () => {
      try {
        // TODO: brancher les endpoints agrégés (services, patients, personnel, consultations)
        if (!cancelled) {
          setStats(MOCK_STATS);
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
  }, []);

  return { stats, trends: MOCK_TRENDS, loading };
}

export const WEEKLY_ACTIVITY = [
  { day: 'Lun', current: 64, previous: 52 },
  { day: 'Mar', current: 72, previous: 58 },
  { day: 'Mer', current: 81, previous: 65 },
  { day: 'Jeu', current: 77, previous: 70 },
  { day: 'Ven', current: 87, previous: 74 },
  { day: 'Sam', current: 45, previous: 38 },
  { day: 'Dim', current: 32, previous: 28 },
];

export const MODULE_DISTRIBUTION = [
  { name: 'Patients', value: 42, color: '#6366f1' },
  { name: 'Consultations', value: 28, color: '#D81B60' },
  { name: 'Examens', value: 18, color: '#F4C430' },
  { name: 'Personnel', value: 12, color: '#06AED4' },
];
