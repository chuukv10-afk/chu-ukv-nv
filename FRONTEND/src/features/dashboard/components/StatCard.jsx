import { TrendingDown, TrendingUp } from 'lucide-react';
import { Box, Card, CardContent, Stack, Typography } from '@mui/joy';
import { LOTRU_DANGER, LOTRU_LAYOUT, LOTRU_NEUTRAL } from '../../../theme/lotruPalette.js';

export default function StatCard({ label, value, trend, trendLabel, icon: Icon, iconBg, iconColor }) {
  const showTrend = trend !== null && trend !== undefined && trendLabel;
  const isUp = Number(trend) > 0;
  const isDown = Number(trend) < 0;
  const TrendIcon = isDown ? TrendingDown : TrendingUp;
  const trendColor = isDown ? LOTRU_DANGER[500] : isUp ? LOTRU_LAYOUT.trendUp : LOTRU_NEUTRAL[500];

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
            {showTrend ? (
              <Stack direction="row" spacing={0.5} alignItems="center" sx={{ mt: 1.5 }}>
                {isUp || isDown ? <TrendIcon size={14} color={trendColor} /> : null}
                <Typography level="body-xs" sx={{ color: trendColor, fontWeight: 600 }}>
                  {isUp ? '+' : ''}
                  {trend}% {trendLabel}
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
