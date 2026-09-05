import { useCallback, useEffect, useState } from 'react';
import {
  Box, Button, Card, Chip, IconButton, Input, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { Activity, Check, Pencil, Plus, Search, Trash2, X } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import SigneVitalFormModal from './components/SigneVitalFormModal.jsx';
import {
  DEFAULT_SIGNE_VITAL_PAGE_SIZE,
  EMPTY_SIGNE_VITAL_FORM,
  SIGNE_VITAL_PAGE_SIZE_OPTIONS,
  SIGNE_VITAL_STATUT_LABELS,
} from './signeVitalConstants.js';
import {
  createSigneVitalApi,
  deleteSigneVitalApi,
  fetchSignesVitauxApi,
  updateSigneVitalApi,
} from './signesVitauxApi.js';

const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_SIGNE_VITAL_PAGE_SIZE, total: 0, totalPages: 0 };

function BoolIcon({ value }) {
  return value ? <Check size={16} color="var(--joy-palette-success-600)" /> : <X size={16} color="var(--joy-palette-neutral-400)" />;
}

export default function SignesVitauxPage() {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCreate = hasPermission(PERMISSIONS.REFERENTIEL.SIGNE_VITAL_CREATE);
  const canUpdate = hasPermission(PERMISSIONS.REFERENTIEL.SIGNE_VITAL_UPDATE);
  const canDelete = hasPermission(PERMISSIONS.REFERENTIEL.SIGNE_VITAL_DELETE);
  const showActions = canUpdate || canDelete;

  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_SIGNE_VITAL_PAGE_SIZE);

  const [formOpen, setFormOpen] = useState(false);
  const [formMode, setFormMode] = useState('create');
  const [formValues, setFormValues] = useState(EMPTY_SIGNE_VITAL_FORM);
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');
  const [editing, setEditing] = useState(null);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, limit]);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchSignesVitauxApi({ page: targetPage, limit, search: debouncedSearch || undefined });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (error) {
      setListError(error.message || 'Impossible de charger les signes vitaux.');
    } finally {
      setLoading(false);
    }
  }, [page, limit, debouncedSearch]);

  useEffect(() => { load(page); }, [load, page]);

  const openCreate = () => {
    setFormMode('create');
    setEditing(null);
    setFormValues(EMPTY_SIGNE_VITAL_FORM);
    setFormError('');
    setFormOpen(true);
  };

  const openEdit = (item) => {
    setFormMode('edit');
    setEditing(item);
    setFormValues({
      code: item.code ?? '',
      libelle: item.libelle ?? '',
      unite: item.unite ?? '',
      demandeAuTriage: item.demandeAuTriage ?? false,
      obligatoireAuTriage: item.obligatoireAuTriage ?? false,
      ordre: item.ordre ?? 0,
      statut: item.statut ?? 'ACTIF',
    });
    setFormError('');
    setFormOpen(true);
  };

  const handleSubmit = async (payload) => {
    setFormLoading(true);
    setFormError('');
    try {
      if (formMode === 'create') {
        await createSigneVitalApi(payload);
        showSuccess('Signe vital créé avec succès.');
        setPage(1);
        await load(1);
      } else {
        await updateSigneVitalApi(editing.id, payload);
        showSuccess('Signe vital mis à jour avec succès.');
        await load(page);
      }
      setFormOpen(false);
    } catch (error) {
      setFormError(error.message || 'Enregistrement impossible.');
    } finally {
      setFormLoading(false);
    }
  };

  const handleDelete = async (item) => {
    if (!window.confirm(`Supprimer le signe vital « ${item.libelle} » ?`)) return;
    try {
      await deleteSigneVitalApi(item.id);
      showSuccess('Signe vital supprimé avec succès.');
      await load(page);
    } catch (error) {
      showError(error.message || 'Suppression impossible.');
    }
  };

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={3}>
        <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ xs: 'stretch', sm: 'center' }} spacing={2}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ width: 44, height: 44, borderRadius: 'md', bgcolor: LOTRU_PRIMARY[50], color: LOTRU_PRIMARY[600], display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Activity size={22} />
            </Box>
            <Box>
              <Typography level="h3" sx={{ fontWeight: 700, color: LOTRU_NEUTRAL[900] }}>Signes vitaux</Typography>
              <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600] }}>
                Référentiel des constantes à saisir au triage
              </Typography>
            </Box>
          </Stack>
          {canCreate ? (
            <Button startDecorator={<Plus size={18} />} onClick={openCreate}>Nouveau signe vital</Button>
          ) : null}
        </Stack>

        <Card variant="outlined" sx={{ borderRadius: 'lg' }}>
          <Stack spacing={2} sx={{ p: 2 }}>
            <Input startDecorator={<Search size={18} />} placeholder="Rechercher par code ou libellé…" value={search} onChange={(e) => setSearch(e.target.value)} sx={{ maxWidth: 420 }} />
            {listError ? <Typography level="body-sm" color="danger">{listError}</Typography> : null}

            <Sheet variant="outlined" sx={{ borderRadius: 'md', overflow: 'auto' }}>
              <Table stickyHeader hoverRow sx={{ minWidth: 900 }}>
                <thead>
                  <tr>
                    <th>Code</th>
                    <th>Libellé</th>
                    <th>Unité</th>
                    <th>Ordre</th>
                    <th>Au triage</th>
                    <th>Obligatoire</th>
                    <th>Statut</th>
                    {showActions ? <th style={{ width: 90 }}>Actions</th> : null}
                  </tr>
                </thead>
                <tbody>
                  {loading ? (
                    <tr><td colSpan={showActions ? 8 : 7}><Typography level="body-sm" sx={{ py: 3, textAlign: 'center' }}>Chargement…</Typography></td></tr>
                  ) : items.length === 0 ? (
                    <tr><td colSpan={showActions ? 8 : 7}><Typography level="body-sm" sx={{ py: 3, textAlign: 'center', color: 'neutral.500' }}>Aucun signe vital configuré.</Typography></td></tr>
                  ) : items.map((item) => (
                    <tr key={item.id}>
                      <td><Typography level="body-sm" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>{item.code}</Typography></td>
                      <td>{item.libelle}</td>
                      <td>{item.unite ?? '—'}</td>
                      <td>{item.ordre ?? 0}</td>
                      <td><BoolIcon value={item.demandeAuTriage} /></td>
                      <td><BoolIcon value={item.obligatoireAuTriage} /></td>
                      <td><Chip size="sm" variant="soft" color={item.statut === 'ACTIF' ? 'success' : 'neutral'}>{SIGNE_VITAL_STATUT_LABELS[item.statut] ?? item.statut}</Chip></td>
                      {showActions ? (
                        <td>
                          <Stack direction="row" spacing={0.5}>
                            {canUpdate ? (
                              <IconButton size="sm" variant="plain" color="neutral" onClick={() => openEdit(item)}><Pencil size={16} /></IconButton>
                            ) : null}
                            {canDelete ? (
                              <IconButton size="sm" variant="plain" color="danger" onClick={() => handleDelete(item)}><Trash2 size={16} /></IconButton>
                            ) : null}
                          </Stack>
                        </td>
                      ) : null}
                    </tr>
                  ))}
                </tbody>
              </Table>
            </Sheet>

            <AppPagination page={pagination.page} totalPages={pagination.totalPages} total={pagination.total} limit={limit} limitOptions={SIGNE_VITAL_PAGE_SIZE_OPTIONS} onPageChange={setPage} onLimitChange={setLimit} />
          </Stack>
        </Card>
      </Stack>

      <SigneVitalFormModal open={formOpen} mode={formMode} initialValues={formValues} loading={formLoading} error={formError} onClose={() => setFormOpen(false)} onSubmit={handleSubmit} />
    </Box>
  );
}
