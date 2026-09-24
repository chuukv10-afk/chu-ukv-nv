import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Box, Button, Card, Chip, IconButton, Input, Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { Eye, Plus, Printer, Receipt, Search, Trash2 } from 'lucide-react';
import AppPagination from '../../components/ui/AppPagination.jsx';
import ConfirmModal from '../../components/ui/ConfirmModal.jsx';
import { PERMISSIONS } from '../../constants/permissions.js';
import { ROUTES } from '../../constants/routes.js';
import { usePermissions } from '../../hooks/usePermissions.js';
import { useToast } from '../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../theme/lotruPalette.js';
import { formatDate } from '../pharmacie/shared/format.js';
import {
  CATEGORIES_TARIFAIRES,
  CATEGORIE_TARIFAIRE_LABELS,
  DEFAULT_FACTURE_PAGE_SIZE,
  FACTURE_PAGE_SIZE_OPTIONS,
  FACTURE_PAIEMENT_COLORS,
  FACTURE_PAIEMENT_LABELS,
  FACTURE_STATUT_COLORS,
  FACTURE_STATUT_LABELS,
  FACTURE_STATUTS,
  formatFc,
  patientLabel,
} from './facturationConstants.js';
import { deleteFactureApi, fetchFacturesApi, openFacturePdfApi } from './facturationApi.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_FACTURE_PAGE_SIZE, total: 0, totalPages: 0 };

export default function FacturesPage() {
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.FACTURATION.FACTURE_CREATE);
  const canDelete = hasPermission(PERMISSIONS.FACTURATION.FACTURE_DELETE);
  const canExport = hasPermission(PERMISSIONS.FACTURATION.FACTURE_EXPORT);

  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [statut, setStatut] = useState('');
  const [categorie, setCategorie] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_FACTURE_PAGE_SIZE);
  const [pendingDelete, setPendingDelete] = useState(null);
  const [confirmLoading, setConfirmLoading] = useState(false);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, limit, statut, categorie]);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchFacturesApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        statut: statut || undefined,
        categorieTarifaire: categorie || undefined,
      });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (error) {
      setListError(error.message || 'Impossible de charger les factures.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch, statut, categorie]);

  useEffect(() => { load(page); }, [load, page]);

  const handleDelete = async () => {
    if (!pendingDelete) return;
    setConfirmLoading(true);
    try {
      await deleteFactureApi(pendingDelete.id);
      showSuccess('Facture supprimée.');
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
            <Receipt size={24} color={LOTRU_PRIMARY[600]} />
            <Box>
              <Typography level="h2" sx={{ fontWeight: 700 }}>Factures</Typography>
              <Typography level="body-md" sx={{ color: 'neutral.500' }}>
                Facturation de l’établissement : patient, catégorie tarifaire et actes de la grille.
              </Typography>
            </Box>
          </Stack>
          {canCreate ? (
            <Button startDecorator={<Plus size={16} />} onClick={() => navigate(ROUTES.FACTURATION.FACTURE_NEW)}>
              Nouvelle facture
            </Button>
          ) : null}
        </Stack>

        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
            <Input
              startDecorator={<Search size={16} />}
              placeholder="N° facture, patient, code UKV, DPI…"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              sx={{ flex: 1 }}
            />
            <Select
              placeholder="Tous les statuts"
              value={statut || null}
              onChange={(_, value) => setStatut(value ?? '')}
              sx={{ minWidth: 180 }}
            >
              <Option value="">Tous les statuts</Option>
              {FACTURE_STATUTS.map((item) => (
                <Option key={item.value} value={item.value}>{item.label}</Option>
              ))}
            </Select>
            <Select
              placeholder="Toutes les catégories"
              value={categorie || null}
              onChange={(_, value) => setCategorie(value ?? '')}
              sx={{ minWidth: 220 }}
            >
              <Option value="">Toutes les catégories</Option>
              {CATEGORIES_TARIFAIRES.map((item) => (
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
          <Table stickyHeader hoverRow sx={{ minWidth: 1100 }}>
            <thead>
              <tr>
                <th>Numéro</th>
                <th>Date</th>
                <th>Patient</th>
                <th>Catégorie</th>
                <th>Structure</th>
                <th>Montant</th>
                <th>Paiement</th>
                <th>Statut</th>
                <th style={{ textAlign: 'right' }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={9}><Typography level="body-sm" sx={{ p: 2 }}>Chargement…</Typography></td></tr>
              ) : items.length === 0 ? (
                <tr>
                  <td colSpan={9}>
                    <Typography level="body-sm" sx={{ p: 2, color: LOTRU_NEUTRAL[600] }}>Aucune facture.</Typography>
                  </td>
                </tr>
              ) : items.map((item) => (
                <tr key={item.id}>
                  <td>
                    <Typography level="body-sm" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>{item.numero}</Typography>
                  </td>
                  <td>{formatDate(item.dateFacture)}</td>
                  <td>{patientLabel(item.patient) || '—'}</td>
                  <td>{CATEGORIE_TARIFAIRE_LABELS[item.categorieTarifaire] ?? item.categorieTarifaire}</td>
                  <td>{item.structure?.libelle || '—'}</td>
                  <td>
                    {Number(item.remiseMontant) > 0 || Number(item.montantBrut) > Number(item.montantTotal) ? (
                      <Stack>
                        <Typography level="body-xs" sx={{ textDecoration: 'line-through', color: 'neutral.500' }}>
                          {formatFc(item.montantBrut)}
                        </Typography>
                        <Typography level="body-sm">{formatFc(item.montantTotal)}</Typography>
                      </Stack>
                    ) : formatFc(item.montantTotal)}
                  </td>
                  <td>
                    <Stack spacing={0.25}>
                      <Chip size="sm" variant="soft" color={FACTURE_PAIEMENT_COLORS[item.statutPaiement] ?? 'neutral'}>
                        {FACTURE_PAIEMENT_LABELS[item.statutPaiement] ?? item.statutPaiement ?? 'Non payée'}
                      </Chip>
                      {Number(item.montantPaye) > 0 ? (
                        <Typography level="body-xs" sx={{ color: 'neutral.500' }}>
                          {formatFc(item.montantPaye)} / reste {formatFc(item.montantReste)}
                        </Typography>
                      ) : null}
                    </Stack>
                  </td>
                  <td>
                    <Chip size="sm" variant="soft" color={FACTURE_STATUT_COLORS[item.statut] ?? 'neutral'}>
                      {FACTURE_STATUT_LABELS[item.statut] ?? item.statut}
                    </Chip>
                  </td>
                  <td style={{ textAlign: 'right' }}>
                    <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                      <IconButton
                        size="sm"
                        variant="plain"
                        onClick={() => navigate(ROUTES.FACTURATION.FACTURE_DETAIL.replace(':id', String(item.id)))}
                      >
                        <Eye size={16} />
                      </IconButton>
                      {canExport ? (
                        <IconButton
                          size="sm"
                          variant="plain"
                          onClick={() => openFacturePdfApi(item.id).catch((error) => showError(error.message || 'Impression impossible.'))}
                        >
                          <Printer size={16} />
                        </IconButton>
                      ) : null}
                      {canDelete && item.statut === 'BROUILLON' ? (
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
          limitOptions={FACTURE_PAGE_SIZE_OPTIONS}
          onPageChange={setPage}
          onLimitChange={setLimit}
        />
      </Stack>
      <ConfirmModal
        open={Boolean(pendingDelete)}
        title="Supprimer la facture"
        message={pendingDelete ? `Supprimer le brouillon ${pendingDelete.numero} ?` : ''}
        confirmLabel="Supprimer"
        loading={confirmLoading}
        onClose={() => setPendingDelete(null)}
        onConfirm={handleDelete}
      />
    </Box>
  );
}
