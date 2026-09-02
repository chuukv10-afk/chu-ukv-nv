import { Box, Card, CardContent, Stack, Typography } from '@mui/joy';
import { TrendingUp } from 'lucide-react';
import { LOTRU_LAYOUT } from '../../../theme/lotruPalette.js';

export default function StatCard({ label, value, trend, icon: Icon, iconBg, iconColor }) {
  return (
    <Card variant="outlined" sx={{ height: '100%' }}>
      <CardContent>
        <Stack direction="row" justifyContent="space-between" alignItems="flex-start">
          <Box>
            <Typography level="body-sm" sx={{ color: 'neutral.500', mb: 0.5 }}>
              {label}
            </Typography>
            <Typography level="h2" sx={{ fontWeight: 700, fontSize: '1.75rem' }}>
              {value}
            </Typography>
            {trend !== undefined ? (
              <Stack direction="row" spacing={0.5} alignItems="center" sx={{ mt: 1.5 }}>
                <TrendingUp size={14} color={LOTRU_LAYOUT.trendUp} />
                <Typography level="body-xs" sx={{ color: LOTRU_LAYOUT.trendUp, fontWeight: 600 }}>
                  {trend}% vs 7 derniers jours
                </Typography>
              </Stack>
            ) : null}
          </Box>
          <Box
            sx={{
              width: 44,
              height: 44,
              borderRadius: 'md',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              bgcolor: iconBg,
              color: iconColor,
              flexShrink: 0,
            }}
          >
            <Icon size={22} />
          </Box>
        </Stack>
      </CardContent>
    </Card>
  );
}
