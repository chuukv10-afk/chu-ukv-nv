import { useEffect, useState } from 'react';
import { Box, Card, Chip, Sheet, Stack, Table, Typography } from '@mui/joy';
import { AlertTriangle } from 'lucide-react';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { formatDate, formatPrix } from '../shared/format.js';
import { fetchLotAlertesApi } from '../lots/lotsApi.js';

export default function AlertesPage() {
  const [alertes, setAlertes] = useState({ perimes: [], peremptionProche: [], stockBas: [] });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchLotAlertesApi()
      .then(setAlertes)
      .catch((err) => setError(err.message || 'Impossible de charger les alertes.'))
      .finally(() => setLoading(false));
  }, []);

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={2.5}>
        <Stack direction="row" spacing={1.5} alignItems="center">
          <AlertTriangle size={24} color={LOTRU_PRIMARY[600]} />
          <Box>
            <Typography level="h2" sx={{ fontWeight: 700 }}>Alertes stock</Typography>
            <Typography level="body-md" sx={{ color: 'neutral.500' }}>
              Péremption, lots proches (90 j) et médicaments sous seuil.
            </Typography>
          </Box>
        </Stack>
        {error ? <Typography color="danger">{error}</Typography> : null}
        {loading ? <Typography>Chargement…</Typography> : (
          <>
            <Section title="Lots périmés" items={alertes.perimes} kind="lot" empty="Aucun lot périmé." color="danger" />
            <Section title="Péremption proche (90 j)" items={alertes.peremptionProche} kind="lot" empty="Aucun lot à péremption proche." color="warning" />
            <Section title="Stock bas" items={alertes.stockBas} kind="stock" empty="Aucun médicament sous seuil." color="warning" />
          </>
        )}
      </Stack>
    </Box>
  );
}

function Section({ title, items, kind, empty, color }) {
  return (
    <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
      <Stack direction="row" spacing={1} alignItems="center" sx={{ mb: 1.5 }}>
        <Typography level="title-md" sx={{ fontWeight: 700 }}>{title}</Typography>
        <Chip size="sm" variant="soft" color={color}>{items.length}</Chip>
      </Stack>
      <Sheet variant="outlined" sx={{ borderRadius: 'md', overflow: 'auto' }}>
        <Table hoverRow sx={{ minWidth: 640 }}>
          <thead>
            <tr>
              <th>Médicament</th>
              {kind === 'lot' ? <><th>Lot</th><th>Péremption</th><th>Qté</th><th>Prix d’achat</th></> : <><th>Stock</th><th>Seuil</th></>}
            </tr>
          </thead>
          <tbody>
            {items.length === 0 ? (
              <tr><td colSpan={kind === 'lot' ? 5 : 3}><Typography level="body-sm" sx={{ p: 1.5, color: LOTRU_NEUTRAL[600] }}>{empty}</Typography></td></tr>
            ) : items.map((item) => (
              <tr key={`${kind}-${item.id}`}>
                <td>{item.medicament ? `${item.medicament.code} — ${item.medicament.libelle}` : `${item.code} — ${item.libelle}`}</td>
                {kind === 'lot' ? (
                  <>
                    <td>{item.numeroLot}</td>
                    <td>{formatDate(item.datePeremption)}</td>
                    <td>{item.quantiteRestante}</td>
                    <td>{formatPrix(item.prixAchatUnitaire)}</td>
                  </>
                ) : (
                  <>
                    <td>{item.stockDisponible}</td>
                    <td>{item.seuilAlerte}</td>
                  </>
                )}
              </tr>
            ))}
          </tbody>
        </Table>
      </Sheet>
    </Card>
  );
}
