import { Box, Card, CardContent, Stack, Typography } from '@mui/joy';
import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import { LOTRU_LAYOUT, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';

export default function ActivityBarChart({ data }) {
  return (
    <Card variant="outlined" sx={{ height: '100%' }}>
      <CardContent>
        <Stack spacing={2} sx={{ height: '100%' }}>
          <Box>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>
              Activité hospitalière
            </Typography>
            <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
              Consultations — semaine en cours vs semaine précédente
            </Typography>
          </Box>

          <Box sx={{ width: '100%', height: 320 }}>
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={data} barGap={4} barCategoryGap="20%">
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E5E7EB" />
                <XAxis
                  dataKey="day"
                  axisLine={false}
                  tickLine={false}
                  tick={{ fill: '#6B7280', fontSize: 12 }}
                />
                <YAxis axisLine={false} tickLine={false} tick={{ fill: '#6B7280', fontSize: 12 }} />
                <Tooltip
                  contentStyle={{
                    borderRadius: 8,
                    border: '1px solid #E5E7EB',
                    boxShadow: '0 4px 12px rgba(0,0,0,0.06)',
                  }}
                />
                <Legend wrapperStyle={{ fontSize: 12, paddingTop: 12 }} />
                <Bar
                  dataKey="current"
                  name="Cette semaine"
                  fill={LOTRU_PRIMARY[500]}
                  radius={[6, 6, 0, 0]}
                  maxBarSize={36}
                />
                <Bar
                  dataKey="previous"
                  name="Semaine précédente"
                  fill={LOTRU_LAYOUT.chartLavender}
                  radius={[6, 6, 0, 0]}
                  maxBarSize={36}
                />
              </BarChart>
            </ResponsiveContainer>
          </Box>
        </Stack>
      </CardContent>
    </Card>
  );
}
