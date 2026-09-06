import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Box, Button, Chip, Modal, ModalDialog, Option, Select, Sheet, Stack, Table, Typography, FormControl, FormLabel,
} from '@mui/joy';
import { Receipt } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { ROUTES } from '../../../constants/routes.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { formatPrix } from '../shared/format.js';
import { DEFAULT_DEMANDE_PAGE_SIZE, DEMANDE_PAGE_SIZE_OPTIONS } from './demandeConstants.js';
import { fetchDemandesServiceApi, reglerDemandeServiceApi } from './demandesServiceApi.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_DEMANDE_PAGE_SIZE, total: 0, totalPages: 0 };

export default function CreancesPage() {
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canRegler = hasPermission(PERMISSIONS.PHARMACIE.DEMANDE_SERVICE_REGLER);

  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_DEMANDE_PAGE_SIZE);
  const [error, setError] = useState('');
  const [pending, setPending] = useState(null);
  const [modePaiement, setModePaiement] = useState('ESPECES');
  const [saving, setSaving] = useState(false);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setError('');
    try {
      const result = await fetchDemandesServiceApi({ page: targetPage, limit, statutPaiement: 'IMPAYEE' });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (err) {
      setError(err.message || 'Impossible de charger les créances.');
    } finally {
      setLoading(false);
    }
  }, [page, limit]);

  useEffect(() => { load(page); }, [load, page]);

  const handleRegler = async () => {
    if (!pending) return;
    setSaving(true);
    try {
      await reglerDemandeServiceApi(pending.id, modePaiement);
      showSuccess('Créance réglée.');
      setPending(null);
      await load(page);
    } catch (err) {
      showError(err.message || 'Règlement impossible.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={2.5}>
        <Stack direction="row" spacing={1.5} alignItems="center">
          <Receipt size={24} color={LOTRU_PRIMARY[600]} />
          <Box>
            <Typography level="h2" sx={{ fontWeight: 700 }}>Créances services</Typography>
            <Typography level="body-md" sx={{ color: 'neutral.500' }}>Demandes délivrées non encore payées.</Typography>
          </Box>
        </Stack>
        {error ? <Typography level="body-sm" color="danger">{error}</Typography> : null}
        <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto' }}>
          <Table stickyHeader hoverRow sx={{ minWidth: 780 }}>
            <thead>
              <tr>
                <th>Numéro</th>
                <th>Service</th>
                <th>Montant</th>
                <th>Paiement</th>
                <th style={{ textAlign: 'right' }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={5}><Typography level="body-sm" sx={{ p: 2 }}>Chargement…</Typography></td></tr>
              ) : items.length === 0 ? (
                <tr><td colSpan={5}><Typography level="body-sm" sx={{ p: 2, color: LOTRU_NEUTRAL[600] }}>Aucune créance.</Typography></td></tr>
              ) : items.map((item) => (
                <tr key={item.id}>
                  <td><Typography level="body-sm" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>{item.numero}</Typography></td>
                  <td>{item.service?.libelle ?? '—'}</td>
                  <td>{formatPrix(item.montantTotal)}</td>
                  <td><Chip size="sm" variant="soft" color="warning">Impayée</Chip></td>
                  <td style={{ textAlign: 'right' }}>
                    <Stack direction="row" spacing={1} justifyContent="flex-end">
                      <Button size="sm" variant="plain" onClick={() => navigate(ROUTES.PHARMACIE.DEMANDE_SERVICE_DETAIL.replace(':id', String(item.id)))}>Voir</Button>
                      {canRegler ? <Button size="sm" onClick={() => { setPending(item); setModePaiement('ESPECES'); }}>Régler</Button> : null}
                    </Stack>
                  </td>
                </tr>
              ))}
            </tbody>
          </Table>
        </Sheet>
        <AppPagination page={pagination.page} totalPages={pagination.totalPages} total={pagination.total} limit={limit} limitOptions={DEMANDE_PAGE_SIZE_OPTIONS} onPageChange={setPage} onLimitChange={setLimit} />
      </Stack>
      <Modal open={Boolean(pending)} onClose={() => setPending(null)}>
        <ModalDialog sx={{ maxWidth: 420, width: '100%' }}>
          <Typography level="title-lg" sx={{ fontWeight: 700 }}>Régler {pending?.numero}</Typography>
          <Typography level="body-sm">Montant : {formatPrix(pending?.montantTotal)}</Typography>
          <FormControl required>
            <FormLabel>Paiement</FormLabel>
            <Select value={modePaiement} onChange={(_, value) => setModePaiement(value ?? 'ESPECES')}>
              <Option value="ESPECES">Espèces</Option>
              <Option value="MOBILE">Mobile money</Option>
            </Select>
          </FormControl>
          <Stack direction="row" spacing={1} justifyContent="flex-end">
            <Button variant="plain" onClick={() => setPending(null)}>Fermer</Button>
            <Button color="success" loading={saving} onClick={handleRegler}>Encaisser</Button>
          </Stack>
        </ModalDialog>
      </Modal>
    </Box>
  );
}
