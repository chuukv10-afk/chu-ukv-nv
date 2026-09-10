import { Alert, Box, Grid, Skeleton, Stack, Typography } from '@mui/joy';
import { ClipboardList, FolderOpen, Network, Users } from 'lucide-react';
import ActivityBarChart from '../../features/dashboard/components/ActivityBarChart.jsx';
import ModuleDonutChart from '../../features/dashboard/components/ModuleDonutChart.jsx';
import StatCard from '../../features/dashboard/components/StatCard.jsx';
import { useDashboardStats } from '../../features/dashboard/useDashboardStats.js';
import { useAuth } from '../../hooks/useAuth.js';
import { usePermissions } from '../../hooks/usePermissions.js';
import { PERMISSIONS } from '../../constants/permissions.js';
import { getDisplayName, getPersonnelTypeLabel } from '../../utils/profile.js';
import { LOTRU_PRIMARY } from '../../theme/lotruPalette.js';
import WelcomeHome from './WelcomeHome.jsx';

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

function formatStatValue(value) {
  return Number(value || 0).toLocaleString('fr-FR');
}

export default function DashboardPage() {
  const { profile, isMedical } = useAuth();
  const { isAdmin, hasPermission } = usePermissions();
  const canViewDashboard = isAdmin || hasPermission(PERMISSIONS.ADMIN.DASHBOARD_VIEW);
  const { stats, trends, trendLabels, weeklyActivity, moduleDistribution, loading, error } =
    useDashboardStats(canViewDashboard);
  const firstName = profile?.prenom || getDisplayName(profile).split(' ')[0];

  if (!canViewDashboard) {
    return <WelcomeHome />;
  }

  return (
    <Stack spacing={3}>
      <Box>
        <Typography level="h2" sx={{ fontWeight: 700, mb: 0.5 }}>
          Tableau de bord
        </Typography>
        <Typography level="body-md" sx={{ color: 'neutral.500' }}>
          Bonjour {firstName} · {getPersonnelTypeLabel(profile?.type)}
          {profile?.service ? ` · ${profile.service}` : ''}
          {isMedical ? ' · Accès clinique' : ''}
        </Typography>
      </Box>

      {error ? (
        <Alert color="danger" variant="soft">
          {error}
        </Alert>
      ) : null}

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
              value={formatStatValue(stats[config.key])}
              trend={trends[config.key]}
              trendLabel={trendLabels[config.key]}
              icon={config.icon}
              iconBg={config.iconBg}
              iconColor={config.iconColor}
            />
          ),
        )}
      </Box>

      <Grid container spacing={2}>
        <Grid xs={12} lg={8}>
          {loading ? (
            <Skeleton variant="rectangular" height={400} sx={{ borderRadius: 'lg' }} />
          ) : (
            <ActivityBarChart data={weeklyActivity} />
          )}
        </Grid>
        <Grid xs={12} lg={4}>
          {loading ? (
            <Skeleton variant="rectangular" height={400} sx={{ borderRadius: 'lg' }} />
          ) : (
            <ModuleDonutChart data={moduleDistribution} totalLabel="Activité" />
          )}
        </Grid>
      </Grid>
    </Stack>
  );
}
