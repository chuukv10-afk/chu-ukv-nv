import { useCallback, useEffect, useState } from 'react';
import {
  Box, Card, Chip, CircularProgress, FormControl, FormLabel, Input, Option, Select, Sheet, Stack, Typography,
} from '@mui/joy';
import {
  AlertTriangle, BarChart3, Package, ShoppingCart, TrendingUp, Wallet,
} from 'lucide-react';
import {
  Area, AreaChart, Bar, BarChart, CartesianGrid, Cell, Legend, Pie, PieChart,
  ResponsiveContainer, Tooltip, XAxis, YAxis,
} from 'recharts';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { formatPrix } from '../shared/format.js';
import { PERIOD_OPTIONS, resolvePeriodRange } from '../shared/period.js';
import { fetchStatistiquesApi } from './statistiquesApi.js';

const CHART_COLORS = ['#4f46e5', '#0b6bcb', '#16a34a', '#f59e0b', '#e02222', '#9C27B0', '#00BCD4', '#795548'];
const EMPTY_STATS = {
  kpis: {
    totalMedicaments: 0,
    stockValue: '0',
    totalDispensations: 0,
    totalRevenue: '0',
    stockAlerts: 0,
  },
  salesPerDay: [],
  mouvementsPerDay: [],
  categoryDistribution: [],
  topMedicaments: [],
  stockAlerts: [],
};

function KpiCard({ icon, label, value, color = 'neutral' }) {
  return (
    <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2, flex: 1, minWidth: 150 }}>
      <Stack direction="row" spacing={1.5} alignItems="center">
        <Box sx={{ color: `${color}.600` }}>{icon}</Box>
        <Box>
          <Typography level="body-xs" sx={{ color: 'neutral.500', fontWeight: 600, textTransform: 'uppercase' }}>
            {label}
          </Typography>
          <Typography level="title-lg" color={color} sx={{ fontWeight: 800 }}>{value}</Typography>
        </Box>
      </Stack>
    </Card>
  );
}

function ChartCard({ title, children }) {
  return (
    <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5, height: '100%' }}>
      <Typography level="title-md" sx={{ fontWeight: 700, mb: 2 }}>{title}</Typography>
      {children}
    </Card>
  );
}

function EmptyChart() {
  return (
    <Box sx={{ height: 220, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
      <Typography level="body-sm" sx={{ color: 'neutral.400', fontStyle: 'italic' }}>Aucune donnée</Typography>
    </Box>
  );
}

export default function StatistiquesPage() {
  const [stats, setStats] = useState(EMPTY_STATS);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [period, setPeriod] = useState('month');
  const [customFrom, setCustomFrom] = useState('');
  const [customTo, setCustomTo] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    const range = resolvePeriodRange(period, customFrom, customTo);
    try {
      const result = await fetchStatistiquesApi({
        dateFrom: range.dateFrom,
        dateTo: range.dateTo,
      });
      setStats({
        ...EMPTY_STATS,
        ...result,
        kpis: { ...EMPTY_STATS.kpis, ...(result?.kpis ?? {}) },
      });
    } catch (err) {
      setError(err.message || 'Impossible de charger les statistiques.');
      setStats(EMPTY_STATS);
    } finally {
      setLoading(false);
    }
  }, [period, customFrom, customTo]);

  useEffect(() => { load(); }, [load]);

  const kpis = stats.kpis;
  const salesPerDay = (stats.salesPerDay || []).map((item) => ({
    date: formatChartDate(item.date),
    Dispensations: Number(item.count || 0),
    Montant: Number(item.amount || 0),
  }));
  const mouvementsPerDay = (stats.mouvementsPerDay || []).map((item) => ({
    date: formatChartDate(item.date),
    Entrées: Number(item.entrees || 0),
    Sorties: Number(item.sorties || 0),
  }));
  const categoryDistrib = (stats.categoryDistribution || []).map((item) => ({
    name: item.category || 'Autre',
    value: Number(item.count || 0),
  }));
  const topMedicaments = (stats.topMedicaments || []).map((item) => ({
    name: (item.name || '').length > 22 ? `${item.name.substring(0, 22)}…` : item.name,
    count: Number(item.count || 0),
  }));
  const stockAlerts = stats.stockAlerts || [];

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={2.5}>
        <Stack direction={{ xs: 'column', md: 'row' }} justifyContent="space-between" alignItems={{ md: 'center' }} spacing={1.5}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <BarChart3 size={24} color={LOTRU_PRIMARY[600]} />
            <Box>
              <Typography level="h2" sx={{ fontWeight: 700 }}>Statistiques pharmacie</Typography>
              <Typography level="body-md" sx={{ color: 'neutral.500' }}>
                Consommation, valeur du stock, top médicaments et alertes.
              </Typography>
            </Box>
          </Stack>
        </Stack>

        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} alignItems={{ md: 'flex-end' }}>
            <FormControl sx={{ minWidth: 200 }}>
              <FormLabel>Période</FormLabel>
              <Select value={period} onChange={(_, value) => setPeriod(value ?? 'month')}>
                {PERIOD_OPTIONS.map((item) => <Option key={item.value} value={item.value}>{item.label}</Option>)}
              </Select>
            </FormControl>
            {period === 'custom' ? (
              <>
                <FormControl sx={{ minWidth: 180 }}>
                  <FormLabel>Du</FormLabel>
                  <Input type="date" value={customFrom} onChange={(event) => setCustomFrom(event.target.value)} />
                </FormControl>
                <FormControl sx={{ minWidth: 180 }}>
                  <FormLabel>Au</FormLabel>
                  <Input type="date" value={customTo} onChange={(event) => setCustomTo(event.target.value)} />
                </FormControl>
              </>
            ) : null}
          </Stack>
        </Card>

        {error ? (
          <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
            {error}
          </Typography>
        ) : null}

        {loading ? (
          <Box sx={{ py: 8, textAlign: 'center' }}><CircularProgress /></Box>
        ) : (
          <Stack spacing={2}>
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} flexWrap="wrap">
              <KpiCard icon={<Package size={22} />} label="Médicaments actifs" value={kpis.totalMedicaments || 0} />
              <KpiCard icon={<Wallet size={22} />} label="Valeur stock" value={formatPrix(kpis.stockValue)} color="success" />
              <KpiCard icon={<ShoppingCart size={22} />} label="Dispensations" value={kpis.totalDispensations || 0} color="primary" />
              <KpiCard icon={<TrendingUp size={22} />} label="Revenu encaissé" value={formatPrix(kpis.totalRevenue)} color="success" />
              <KpiCard icon={<AlertTriangle size={22} />} label="Alertes stock" value={kpis.stockAlerts || 0} color="warning" />
            </Stack>

            <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', md: '2fr 1fr' }, gap: 2 }}>
              <ChartCard title="Dispensations par jour">
                {salesPerDay.length > 0 ? (
                  <ResponsiveContainer width="100%" height={240}>
                    <AreaChart data={salesPerDay}>
                      <CartesianGrid strokeDasharray="3 3" stroke="#E5E7EB" />
                      <XAxis dataKey="date" tick={{ fontSize: 11, fill: LOTRU_NEUTRAL[500] }} />
                      <YAxis tick={{ fontSize: 11, fill: LOTRU_NEUTRAL[500] }} />
                      <Tooltip />
                      <Legend />
                      <Area type="monotone" dataKey="Dispensations" stroke={LOTRU_PRIMARY[600]} fill={`${LOTRU_PRIMARY[500]}33`} strokeWidth={2} />
                    </AreaChart>
                  </ResponsiveContainer>
                ) : <EmptyChart />}
              </ChartCard>

              <ChartCard title="Répartition par famille">
                {categoryDistrib.length > 0 ? (
                  <ResponsiveContainer width="100%" height={240}>
                    <PieChart>
                      <Pie data={categoryDistrib} cx="50%" cy="50%" innerRadius={40} outerRadius={80} dataKey="value" nameKey="name">
                        {categoryDistrib.map((entry, index) => (
                          <Cell key={entry.name} fill={CHART_COLORS[index % CHART_COLORS.length]} />
                        ))}
                      </Pie>
                      <Tooltip />
                      <Legend />
                    </PieChart>
                  </ResponsiveContainer>
                ) : <EmptyChart />}
              </ChartCard>

              <ChartCard title="Top 10 médicaments sortis">
                {topMedicaments.length > 0 ? (
                  <ResponsiveContainer width="100%" height={280}>
                    <BarChart data={topMedicaments} layout="vertical">
                      <CartesianGrid strokeDasharray="3 3" stroke="#E5E7EB" />
                      <XAxis type="number" tick={{ fontSize: 11, fill: LOTRU_NEUTRAL[500] }} />
                      <YAxis dataKey="name" type="category" width={140} tick={{ fontSize: 11, fill: LOTRU_NEUTRAL[500] }} />
                      <Tooltip />
                      <Bar dataKey="count" name="Quantité" fill={LOTRU_PRIMARY[500]} radius={[0, 6, 6, 0]} />
                    </BarChart>
                  </ResponsiveContainer>
                ) : <EmptyChart />}
              </ChartCard>

              <ChartCard title="Alertes de stock">
                {stockAlerts.length > 0 ? (
                  <Sheet sx={{ overflow: 'auto', maxHeight: 280, borderRadius: 'md' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
                      <thead>
                        <tr style={{ backgroundColor: '#F9FAFB' }}>
                          <th style={{ padding: '8px 12px', textAlign: 'left' }}>Médicament</th>
                          <th style={{ padding: '8px 12px', textAlign: 'right' }}>Stock</th>
                          <th style={{ padding: '8px 12px', textAlign: 'right' }}>Seuil</th>
                          <th style={{ padding: '8px 12px', textAlign: 'center' }}>Statut</th>
                        </tr>
                      </thead>
                      <tbody>
                        {stockAlerts.map((item) => (
                          <tr key={item.id} style={{ borderTop: '1px solid #E5E7EB' }}>
                            <td style={{ padding: '8px 12px' }}>{item.code} — {item.name}</td>
                            <td style={{ padding: '8px 12px', textAlign: 'right', fontWeight: 700, color: item.currentStock === 0 ? '#bc1919' : '#b45309' }}>
                              {item.currentStock}
                            </td>
                            <td style={{ padding: '8px 12px', textAlign: 'right', color: LOTRU_NEUTRAL[400] }}>{item.stockMin}</td>
                            <td style={{ padding: '8px 12px', textAlign: 'center' }}>
                              <Chip size="sm" variant="soft" color={item.currentStock === 0 ? 'danger' : 'warning'}>
                                {item.currentStock === 0 ? 'Rupture' : 'Alerte'}
                              </Chip>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </Sheet>
                ) : (
                  <Box sx={{ py: 4, textAlign: 'center' }}>
                    <Typography level="body-sm" color="success">Aucune alerte de stock</Typography>
                  </Box>
                )}
              </ChartCard>
            </Box>

            <ChartCard title="Entrées et sorties de stock">
              {mouvementsPerDay.length > 0 ? (
                <ResponsiveContainer width="100%" height={260}>
                  <BarChart data={mouvementsPerDay}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#E5E7EB" />
                    <XAxis dataKey="date" tick={{ fontSize: 11, fill: LOTRU_NEUTRAL[500] }} />
                    <YAxis tick={{ fontSize: 11, fill: LOTRU_NEUTRAL[500] }} />
                    <Tooltip />
                    <Legend />
                    <Bar dataKey="Entrées" fill="#16a34a" radius={[6, 6, 0, 0]} maxBarSize={36} />
                    <Bar dataKey="Sorties" fill="#e02222" radius={[6, 6, 0, 0]} maxBarSize={36} />
                  </BarChart>
                </ResponsiveContainer>
              ) : <EmptyChart />}
            </ChartCard>
          </Stack>
        )}
      </Stack>
    </Box>
  );
}

function formatChartDate(value) {
  if (!value) return '';
  const date = new Date(`${value}T00:00:00`);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
}
