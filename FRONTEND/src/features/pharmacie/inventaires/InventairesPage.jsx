import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Box, Button, Card, Chip, FormControl, FormLabel, IconButton, Input, Modal, ModalDialog,
  Option, Select, Sheet, Stack, Table, Textarea, Typography,
} from '@mui/joy';
import { ClipboardCheck, Eye, Plus, Search, Trash2 } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import ConfirmModal from '../../../components/ui/ConfirmModal.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { ROUTES } from '../../../constants/routes.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { formatDate } from '../shared/format.js';
import {
  DEFAULT_INVENTAIRE_PAGE_SIZE,
  INVENTAIRE_PAGE_SIZE_OPTIONS,
  INVENTAIRE_STATUT_COLORS,
  INVENTAIRE_STATUT_LABELS,
  INVENTAIRE_STATUTS,
  defaultInventaireLibelle,
  inventaireDetailPath,
} from './inventaireConstants.js';
import { createInventaireApi, deleteInventaireApi, fetchInventairesApi } from './inventairesApi.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_INVENTAIRE_PAGE_SIZE, total: 0, totalPages: 0 };

export default function InventairesPage() {
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.PHARMACIE.INVENTAIRE_CREATE);

  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [statut, setStatut] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_INVENTAIRE_PAGE_SIZE);
  const [createOpen, setCreateOpen] = useState(false);
  const [libelle, setLibelle] = useState(() => defaultInventaireLibelle());
  const [notes, setNotes] = useState('');
  const [saving, setSaving] = useState(false);
  const [pendingDelete, setPendingDelete] = useState(null);
  const [confirmLoading, setConfirmLoading] = useState(false);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, limit, statut]);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchInventairesApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        statut: statut || undefined,
      });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (error) {
      setListError(error.message || 'Impossible de charger les inventaires.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, statut]);

  useEffect(() => { load(page); }, [load, page]);

  const enCours = useMemo(() => items.find((item) => item.statut === 'EN_COURS'), [items]);

  const handleCreate = async () => {
    const trimmed = libelle.trim();
    if (!trimmed) {
      showError('Indiquez le libellé de la campagne.');
      return;
    }
    setSaving(true);
    try {
      const created = await createInventaireApi({
        libelle: trimmed,
        notes: notes.trim() || null,
      });
      showSuccess('Campagne ouverte. Les lots en stock sont figés.');
      setCreateOpen(false);
      setNotes('');
      setLibelle(defaultInventaireLibelle());
      navigate(inventaireDetailPath(created.id));
    } catch (error) {
      showError(error.message || 'Ouverture impossible.');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!pendingDelete) return;
    setConfirmLoading(true);
    try {
      await deleteInventaireApi(pendingDelete.id);
      showSuccess('Campagne supprimée.');
      setPendingDelete(null);
      await load(page);
    } catch (error) {
      showError(error.message || 'Suppression impossible.');
    } finally {
      setConfirmLoading(false);
    }
  };

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={2.5}>
        <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={1.5}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <ClipboardCheck size={24} color={LOTRU_PRIMARY[600]} />
            <Box>
              <Typography level="h2" sx={{ fontWeight: 700 }}>Inventaire pharmacie</Typography>
              <Typography level="body-md" sx={{ color: 'neutral.500' }}>
                Campagne de comptage : marquer chaque produit comme compté, puis clôturer.
              </Typography>
            </Box>
          </Stack>
          {canCreate ? (
            <Button
              startDecorator={<Plus size={16} />}
              onClick={() => { setLibelle(defaultInventaireLibelle()); setCreateOpen(true); }}
            >
              Nouvelle campagne
            </Button>
          ) : null}
        </Stack>

        {enCours ? (
          <Card variant="soft" color="warning" sx={{ borderRadius: 'lg', p: 2 }}>
            <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={1}>
              <Typography level="body-sm">
                Campagne <strong>{enCours.numero}</strong> en cours — {enCours.produitsComptes}/{enCours.produitsCount} produits comptés.
              </Typography>
              <Button size="sm" variant="solid" color="warning" onClick={() => navigate(inventaireDetailPath(enCours.id))}>
                Continuer le comptage
              </Button>
            </Stack>
          </Card>
        ) : null}

        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
            <Input
              startDecorator={<Search size={16} />}
              placeholder="N° ou libellé…"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              sx={{ flex: 1 }}
            />
            <Select
              placeholder="Tous les statuts"
              value={statut || null}
              onChange={(_, value) => setStatut(value ?? '')}
              sx={{ minWidth: 200 }}
            >
              <Option value="">Tous les statuts</Option>
              {INVENTAIRE_STATUTS.map((item) => (
                <Option key={item.value} value={item.value}>{item.label}</Option>
              ))}
            </Select>
          </Stack>
        </Card>

        {listError ? (
          <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
            {listError}
          </Typography>
        ) : null}

        <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto' }}>
          <Table stickyHeader hoverRow sx={{ minWidth: 860 }}>
            <thead>
              <tr>
                <th>Numéro</th>
                <th>Libellé</th>
                <th>Ouverture</th>
                <th>Produits</th>
                <th>Lots</th>
                <th>Statut</th>
                <th style={{ textAlign: 'right' }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={7}><Typography level="body-sm" sx={{ p: 2 }}>Chargement…</Typography></td></tr>
              ) : items.length === 0 ? (
                <tr><td colSpan={7}><Typography level="body-sm" sx={{ p: 2, color: LOTRU_NEUTRAL[600] }}>Aucune campagne.</Typography></td></tr>
              ) : items.map((item) => (
                <tr key={item.id}>
                  <td><Typography level="body-sm" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>{item.numero}</Typography></td>
                  <td>{item.libelle}</td>
                  <td>{formatDate(item.dateDebut)}</td>
                  <td>{item.produitsComptes ?? 0}/{item.produitsCount ?? 0}</td>
                  <td>{item.lignesComptees ?? 0}/{item.lignesCount ?? 0}</td>
                  <td>
                    <Chip size="sm" variant="soft" color={INVENTAIRE_STATUT_COLORS[item.statut] ?? 'neutral'}>
                      {INVENTAIRE_STATUT_LABELS[item.statut] ?? item.statut}
                    </Chip>
                  </td>
                  <td style={{ textAlign: 'right' }}>
                    <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                      <IconButton
                        size="sm"
                        variant="plain"
                        onClick={() => navigate(inventaireDetailPath(item.id))}
                      >
                        <Eye size={16} />
                      </IconButton>
                      {canCreate && item.statut === 'EN_COURS' && (item.lignesComptees ?? 0) === 0 ? (
                        <IconButton size="sm" variant="plain" color="danger" onClick={() => setPendingDelete(item)}>
                          <Trash2 size={16} />
                        </IconButton>
                      ) : null}
                    </Stack>
                  </td>
                </tr>
              ))}
            </tbody>
          </Table>
        </Sheet>

        <AppPagination
          page={pagination.page}
          totalPages={pagination.totalPages}
          total={pagination.total}
          limit={limit}
          limitOptions={INVENTAIRE_PAGE_SIZE_OPTIONS}
          onPageChange={setPage}
          onLimitChange={setLimit}
        />
      </Stack>

      <Modal open={createOpen} onClose={saving ? undefined : () => setCreateOpen(false)}>
        <ModalDialog sx={{ borderRadius: 'lg', width: '100%', maxWidth: 480 }}>
          <Typography level="title-lg" sx={{ fontWeight: 700 }}>Ouvrir une campagne</Typography>
          <Typography level="body-sm" sx={{ color: 'neutral.600' }}>
            Tous les lots encore en stock (y compris périmés) sont figés. Les ventes restent possibles.
            Un lot trouvé hors SI s’enregistre en réception, pas ici.
          </Typography>
          <Stack spacing={1.5} sx={{ mt: 1 }}>
            <FormControl required>
              <FormLabel>Libellé</FormLabel>
              <Input value={libelle} onChange={(event) => setLibelle(event.target.value)} slotProps={{ input: { maxLength: 150 } }} />
            </FormControl>
            <FormControl>
              <FormLabel>Notes</FormLabel>
              <Textarea minRows={2} value={notes} onChange={(event) => setNotes(event.target.value)} />
            </FormControl>
            <Stack direction="row" spacing={1} justifyContent="flex-end">
              <Button variant="plain" color="neutral" onClick={() => setCreateOpen(false)} disabled={saving}>Annuler</Button>
              <Button loading={saving} onClick={handleCreate}>Ouvrir l’inventaire</Button>
            </Stack>
          </Stack>
        </ModalDialog>
      </Modal>

      <ConfirmModal
        open={Boolean(pendingDelete)}
        title="Supprimer la campagne"
        message={pendingDelete ? `Supprimer ${pendingDelete.numero} ? Aucun lot n’a encore été marqué comme compté.` : ''}
        confirmLabel="Supprimer"
        loading={confirmLoading}
        onClose={() => setPendingDelete(null)}
        onConfirm={handleDelete}
      />
    </Box>
  );
}
