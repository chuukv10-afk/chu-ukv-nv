import { Box, Card, CardContent, Stack, Typography } from '@mui/joy';
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

export default function ModuleDonutChart({ data, totalLabel = 'Total' }) {
  const total = data.reduce((sum, item) => sum + item.value, 0);

  return (
    <Card variant="outlined" sx={{ height: '100%' }}>
      <CardContent>
        <Stack spacing={2} sx={{ height: '100%' }}>
          <Box>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>
              Répartition par module
            </Typography>
            <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
              Activité globale du SIH
            </Typography>
          </Box>

          <Box sx={{ position: 'relative', width: '100%', height: 260 }}>
            <ResponsiveContainer width="100%" height="100%">
              <PieChart>
                <Pie
                  data={data}
                  dataKey="value"
                  nameKey="name"
                  cx="50%"
                  cy="50%"
                  innerRadius={72}
                  outerRadius={100}
                  paddingAngle={2}
                  stroke="none"
                >
                  {data.map((entry) => (
                    <Cell key={entry.name} fill={entry.color} />
                  ))}
                </Pie>
                <Tooltip
                  contentStyle={{
                    borderRadius: 8,
                    border: '1px solid #E5E7EB',
                  }}
                />
              </PieChart>
            </ResponsiveContainer>
            <Box
              sx={{
                position: 'absolute',
                inset: 0,
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                pointerEvents: 'none',
              }}
            >
              <Typography level="body-xs" sx={{ color: 'neutral.500' }}>
                {totalLabel}
              </Typography>
              <Typography level="h3" sx={{ fontWeight: 700 }}>
                {total.toLocaleString('fr-FR')}
              </Typography>
            </Box>
          </Box>

          <Stack spacing={1}>
            {data.map((item) => (
              <Stack key={item.name} direction="row" alignItems="center" spacing={1}>
                <Box sx={{ width: 10, height: 10, borderRadius: '50%', bgcolor: item.color }} />
                <Typography level="body-sm" sx={{ flex: 1, color: 'neutral.600' }}>
                  {item.name}
                </Typography>
                <Typography level="body-sm" sx={{ fontWeight: 600 }}>
                  {item.value}%
                </Typography>
              </Stack>
            ))}
          </Stack>
        </Stack>
      </CardContent>
    </Card>
  );
}
