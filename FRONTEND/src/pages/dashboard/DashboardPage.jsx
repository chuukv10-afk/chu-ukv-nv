import { Box, Grid, Skeleton, Stack, Typography } from '@mui/joy';
import { ClipboardList, FolderOpen, Network, Users } from 'lucide-react';
import ActivityBarChart from '../../features/dashboard/components/ActivityBarChart.jsx';
import ModuleDonutChart from '../../features/dashboard/components/ModuleDonutChart.jsx';
import StatCard from '../../features/dashboard/components/StatCard.jsx';
import {
  MODULE_DISTRIBUTION,
  useDashboardStats,
  WEEKLY_ACTIVITY,
} from '../../features/dashboard/useDashboardStats.js';
import { useAuth } from '../../hooks/useAuth.js';
import { getDisplayName, getPersonnelTypeLabel } from '../../utils/profile.js';
import { LOTRU_PRIMARY } from '../../theme/lotruPalette.js';

const STAT_CONFIG = [
  {
    key: 'services',
    label: 'Services',
    icon: Network,
    iconBg: '#fce5f3',
    iconColor: '#D81B60',
  },
  {
    key: 'dossiersPatient',
    label: 'Dossiers patient',
    icon: FolderOpen,
    iconBg: '#dcfce3',
    iconColor: '#10B981',
  },
  {
    key: 'personnel',
    label: 'Personnel',
    icon: Users,
    iconBg: '#fef3c7',
    iconColor: '#d97706',
  },
  {
    key: 'consultationsJour',
    label: 'Consultations du jour',
    icon: ClipboardList,
    iconBg: 'primary.50',
    iconColor: LOTRU_PRIMARY[500],
  },
];

function formatStatValue(key, value) {
  if (key === 'dossiersPatient' || key === 'personnel') {
    return value.toLocaleString('fr-FR');
  }

  return String(value);
}

export default function DashboardPage() {
  const { profile, isMedical } = useAuth();
  const { stats, trends, loading } = useDashboardStats();
  const firstName = profile?.prenom || getDisplayName(profile).split(' ')[0];

  return (
    <Stack spacing={3}>
      <Box>
        <Typography level="h2" sx={{ fontWeight: 700, mb: 0.5 }}>
          Overview
        </Typography>
        <Typography level="body-md" sx={{ color: 'neutral.500' }}>
          Bonjour {firstName} · {getPersonnelTypeLabel(profile?.type)}
          {profile?.service ? ` · ${profile.service}` : ''}
          {isMedical ? ' · Accès clinique' : ''}
        </Typography>
      </Box>

      <Box
        sx={{
          display: 'grid',
          gap: 2,
          gridTemplateColumns: {
            xs: '1fr',
            sm: 'repeat(2, 1fr)',
            md: 'repeat(4, 1fr)',
          },
        }}
      >
        {STAT_CONFIG.map((config) =>
          loading ? (
            <Skeleton key={config.key} variant="rectangular" height={140} sx={{ borderRadius: 'lg' }} />
          ) : (
            <StatCard
              key={config.key}
              label={config.label}
              value={formatStatValue(config.key, stats[config.key])}
              trend={trends[config.key]}
              icon={config.icon}
              iconBg={config.iconBg}
              iconColor={config.iconColor}
            />
          ),
        )}
      </Box>

      <Grid container spacing={2}>
        <Grid xs={12} lg={8}>
          <ActivityBarChart data={WEEKLY_ACTIVITY} />
        </Grid>
        <Grid xs={12} lg={4}>
          <ModuleDonutChart data={MODULE_DISTRIBUTION} totalLabel="Activité" />
        </Grid>
      </Grid>
    </Stack>
  );
}
