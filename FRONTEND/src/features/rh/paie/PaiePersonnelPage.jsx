import { useCallback, useEffect, useMemo, useState } from 'react';
import { Navigate, useNavigate, useSearchParams } from 'react-router-dom';
import {
  Box, Button, Chip, FormControl, FormLabel, Modal, ModalDialog,
  Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { Scale, Wallet } from 'lucide-react';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { ROUTES } from '../../../constants/routes.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { fetchPaiePeriodesApi, openPaiePeriodeApi } from './paieApi.js';
import {
  PAIE_MOIS,
  PAIE_STATUT_COLORS,
  PAIE_STATUT_LABELS,
  formatPaieMontant,
  kinshasaMonth,
  paieDetailPath,
} from './paieConstants.js';

export default function PaiePersonnelPage() {
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const showList = params.get('liste') === '1';
  const { hasPermission } = usePermissions();
  const { showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.RH.PAIE_CREATE);

  const defaults = kinshasaMonth();
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [otherMonth, setOtherMonth] = useState(false);
  const [annee, setAnnee] = useState(defaults.year);
  const [mois, setMois] = useState(defaults.month);
  const [saving, setSaving] = useState(false);

  const years = useMemo(() => [defaults.year - 1, defaults.year, defaults.year + 1], [defaults.year]);
  const currentLabel = PAIE_MOIS.find((item) => item.value === defaults.month)?.label ?? '';

  const load = useCallback(async () => {
    setLoading(true);
    setListError('');
    try {
      setItems(await fetchPaiePeriodesApi());
    } catch (error) {
      setListError(error.message || 'Impossible de charger la paie.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const brouillon = useMemo(() => items.find((item) => item.statut === 'BROUILLON'), [items]);
  const currentExists = items.some((item) => item.annee === defaults.year && item.mois === defaults.month);

  const prepare = async (targetAnnee, targetMois) => {
    setSaving(true);
    try {
      const created = await openPaiePeriodeApi({ annee: Number(targetAnnee), mois: Number(targetMois) });
      setOtherMonth(false);
      navigate(paieDetailPath(created.id));
    } catch (error) {
      showError(error.message || 'Préparation impossible.');
    } finally {
      setSaving(false);
    }
  };

  if (loading && !showList) {
    return <Typography level="body-sm">Chargement…</Typography>;
  }

  if (!loading && !showList && brouillon) {
    return <Navigate to={paieDetailPath(brouillon.id)} replace />;
  }

  return (
    <Stack spacing={2.5}>
      <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'flex-start' }} spacing={1.5}>
        <Stack direction="row" spacing={1.5} alignItems="center">
          <Wallet size={24} color={LOTRU_PRIMARY[600]} />
          <Box>
            <Typography level="h2" sx={{ fontWeight: 700, mb: 0.5 }}>Prime locale</Typography>
            <Typography level="body-md" sx={{ color: 'neutral.500' }}>
              Un listing par mois, comme le fichier Excel : le montant se calcule tout seul selon le grade et la fonction.
            </Typography>
          </Box>
        </Stack>
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
          <Button variant="outlined" color="neutral" startDecorator={<Scale size={16} />} onClick={() => navigate(ROUTES.RH.PAIE_BAREME)}>
            Tarifs
          </Button>
          {canCreate && !brouillon && !currentExists ? (
            <Button loading={saving} onClick={() => prepare(defaults.year, defaults.month)}>
              Préparer {currentLabel} {defaults.year}
            </Button>
          ) : null}
        </Stack>
      </Stack>

      {listError ? (
        <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
          {listError}
        </Typography>
      ) : null}

      <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto' }}>
        <Table stickyHeader hoverRow sx={{ minWidth: 640 }}>
          <thead>
            <tr>
              <th>Mois</th>
              <th>État</th>
              <th>Agents payés</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr><td colSpan={4}><Typography level="body-sm" sx={{ p: 2 }}>Chargement…</Typography></td></tr>
            ) : items.length === 0 ? (
              <tr>
                <td colSpan={4}>
                  <Typography level="body-sm" sx={{ p: 2, color: LOTRU_NEUTRAL[600] }}>
                    Aucun mois préparé. Cliquez sur Préparer {currentLabel} {defaults.year}.
                  </Typography>
                </td>
              </tr>
            ) : items.map((item) => (
              <tr key={item.id} style={{ cursor: 'pointer' }} onClick={() => navigate(paieDetailPath(item.id))}>
                <td><Typography level="body-sm" sx={{ fontWeight: 600 }}>{item.libelle}</Typography></td>
                <td>
                  <Chip size="sm" variant="soft" color={PAIE_STATUT_COLORS[item.statut] ?? 'neutral'}>
                    {PAIE_STATUT_LABELS[item.statut] ?? item.statut}
                  </Chip>
                </td>
                <td>{item.nbLignesIncluses ?? 0}</td>
                <td>{formatPaieMontant(item.totalNet)}</td>
              </tr>
            ))}
          </tbody>
        </Table>
      </Sheet>

      {canCreate && !brouillon ? (
        <Button variant="plain" size="sm" sx={{ alignSelf: 'flex-start' }} onClick={() => setOtherMonth(true)}>
          Préparer un autre mois
        </Button>
      ) : null}

      <Modal open={otherMonth} onClose={saving ? undefined : () => setOtherMonth(false)}>
        <ModalDialog sx={{ borderRadius: 'lg', width: 400 }}>
          <Typography level="title-lg">Quel mois ?</Typography>
          <Stack spacing={1.5} sx={{ mt: 1 }}>
            <FormControl>
              <FormLabel>Mois</FormLabel>
              <Select value={mois} onChange={(_, value) => setMois(value ?? mois)}>
                {PAIE_MOIS.map((item) => (
                  <Option key={item.value} value={item.value}>{item.label}</Option>
                ))}
              </Select>
            </FormControl>
            <FormControl>
              <FormLabel>Année</FormLabel>
              <Select value={annee} onChange={(_, value) => setAnnee(value ?? annee)}>
                {years.map((year) => (
                  <Option key={year} value={year}>{year}</Option>
                ))}
              </Select>
            </FormControl>
            <Stack direction="row" spacing={1} justifyContent="flex-end">
              <Button variant="plain" color="neutral" disabled={saving} onClick={() => setOtherMonth(false)}>Annuler</Button>
              <Button loading={saving} onClick={() => prepare(annee, mois)}>Préparer</Button>
            </Stack>
          </Stack>
        </ModalDialog>
      </Modal>
    </Stack>
  );
}
