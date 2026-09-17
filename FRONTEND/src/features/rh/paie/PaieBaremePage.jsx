import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Box, Button, FormControl, FormLabel, IconButton, Input, Modal, ModalDialog,
  Option, Select, Sheet, Stack, Table, Typography,
} from '@mui/joy';
import { ArrowLeft, Pencil, Plus, Trash2 } from 'lucide-react';
import ConfirmModal from '../../../components/ui/ConfirmModal.jsx';
import ExportButtons from '../../../components/export/ExportButtons.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { ROUTES } from '../../../constants/routes.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL } from '../../../theme/lotruPalette.js';
import {
  deletePaieBaremeApi,
  exportPaieBaremeApi,
  fetchPaieBaremesApi,
  fetchPaieLookupsApi,
  updatePaieBaremeApi,
  upsertPaieBaremeApi,
} from './paieApi.js';
import { formatPaieMontant } from './paieConstants.js';

function emptyForm() {
  return { id: null, gradeId: null, fonctionId: null, montant: '' };
}

export default function PaieBaremePage() {
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canUpdate = hasPermission(PERMISSIONS.RH.PAIE_UPDATE);

  const [items, setItems] = useState([]);
  const [lookups, setLookups] = useState({ grades: [], fonctions: [] });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [saving, setSaving] = useState(false);
  const [exportLoading, setExportLoading] = useState(null);
  const [pendingDelete, setPendingDelete] = useState(null);
  const [confirmLoading, setConfirmLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [baremes, lists] = await Promise.all([fetchPaieBaremesApi(), fetchPaieLookupsApi()]);
      setItems(baremes);
      setLookups(lists);
    } catch (err) {
        setError(err.message || 'Impossible de charger les tarifs.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const closeForm = () => {
    setOpen(false);
    setForm(emptyForm());
  };

  const openCreate = () => {
    setForm(emptyForm());
    setOpen(true);
  };

  const openEdit = (item) => {
    setForm({
      id: item.id,
      gradeId: item.grade?.id ?? null,
      fonctionId: item.fonction?.id ?? null,
      montant: item.montant != null ? String(Number(item.montant)) : '',
    });
    setOpen(true);
  };

  const handleSave = async () => {
    if (!form.fonctionId) {
      showError('La fonction est obligatoire.');
      return;
    }
    if (form.montant === '' || Number(form.montant) < 0) {
      showError('Indiquez un montant.');
      return;
    }
    const payload = {
      gradeId: form.gradeId || null,
      fonctionId: Number(form.fonctionId),
      montant: String(form.montant).replace(',', '.'),
    };
    setSaving(true);
    try {
      if (form.id) {
        await updatePaieBaremeApi(form.id, payload);
        showSuccess('Tarif mis à jour.');
      } else {
        await upsertPaieBaremeApi(payload);
        showSuccess('Tarif enregistré.');
      }
      closeForm();
      await load();
    } catch (err) {
      showError(err.message || 'Enregistrement impossible.');
    } finally {
      setSaving(false);
    }
  };

  const handleExport = async (format) => {
    setExportLoading(format);
    try {
      await exportPaieBaremeApi(format);
    } catch (err) {
      showError(err.message || 'Export impossible.');
    } finally {
      setExportLoading(null);
    }
  };

  const handleDelete = async () => {
    if (!pendingDelete) return;
    setConfirmLoading(true);
    try {
      await deletePaieBaremeApi(pendingDelete.id);
      showSuccess('Tarif supprimé.');
      setPendingDelete(null);
      await load();
    } catch (err) {
      showError(err.message || 'Suppression impossible.');
    } finally {
      setConfirmLoading(false);
    }
  };

  const isEdit = Boolean(form.id);

  return (
    <Stack spacing={2.5}>
      <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'flex-start' }} spacing={1.5}>
        <Stack spacing={1}>
          <Button variant="plain" color="neutral" startDecorator={<ArrowLeft size={16} />} sx={{ alignSelf: 'flex-start', px: 0 }} onClick={() => navigate(ROUTES.RH.PAIE)}>
            Retour à la paie
          </Button>
          <Box>
            <Typography level="h2" sx={{ fontWeight: 700, mb: 0.5 }}>Tarifs de la prime</Typography>
            <Typography level="body-md" sx={{ color: 'neutral.500' }}>
              Un montant par grade et fonction. Quand vous préparez un mois, ces tarifs remplissent le listing tout seuls.
            </Typography>
          </Box>
        </Stack>
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} alignItems={{ sm: 'center' }}>
          <ExportButtons onExport={handleExport} loading={exportLoading} size="sm" disabled={items.length === 0} />
          {canUpdate ? (
            <Button startDecorator={<Plus size={16} />} onClick={openCreate}>Ajouter</Button>
          ) : null}
        </Stack>
      </Stack>

      {error ? (
        <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
          {error}
        </Typography>
      ) : null}

      <Sheet variant="outlined" sx={{ borderRadius: 'lg', overflow: 'auto' }}>
        <Table stickyHeader hoverRow sx={{ minWidth: 640 }}>
          <thead>
            <tr>
              <th>Grade</th>
              <th>Fonction</th>
              <th>Montant</th>
              {canUpdate ? <th style={{ textAlign: 'right' }}>Actions</th> : null}
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr><td colSpan={canUpdate ? 4 : 3}><Typography level="body-sm" sx={{ p: 2 }}>Chargement…</Typography></td></tr>
            ) : items.length === 0 ? (
              <tr>
                <td colSpan={canUpdate ? 4 : 3}>
                  <Typography level="body-sm" sx={{ p: 2, color: LOTRU_NEUTRAL[600] }}>
                    Aucun tarif. Ajoutez un montant pour un grade et une fonction.
                  </Typography>
                </td>
              </tr>
            ) : items.map((item) => (
              <tr key={item.id}>
                <td>{item.grade?.libelle || 'Toutes (sans grade)'}</td>
                <td>{item.fonction?.libelle || '—'}</td>
                <td>{formatPaieMontant(item.montant)}</td>
                {canUpdate ? (
                  <td style={{ textAlign: 'right' }}>
                    <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                      <IconButton size="sm" variant="plain" onClick={() => openEdit(item)}>
                        <Pencil size={16} />
                      </IconButton>
                      <IconButton size="sm" variant="plain" color="danger" onClick={() => setPendingDelete(item)}>
                        <Trash2 size={16} />
                      </IconButton>
                    </Stack>
                  </td>
                ) : null}
              </tr>
            ))}
          </tbody>
        </Table>
      </Sheet>

      <Modal open={open} onClose={saving ? undefined : closeForm}>
        <ModalDialog sx={{ borderRadius: 'lg', width: 440 }}>
          <Typography level="title-lg">{isEdit ? 'Modifier le tarif' : 'Nouveau tarif'}</Typography>
          <Stack spacing={1.5} sx={{ mt: 1 }}>
            <FormControl>
              <FormLabel>Grade (optionnel)</FormLabel>
              <Select value={form.gradeId ?? ''} onChange={(_, value) => setForm((prev) => ({ ...prev, gradeId: value || null }))} placeholder="Toutes (sans grade)">
                <Option value="">Toutes (sans grade)</Option>
                {lookups.grades.map((grade) => (
                  <Option key={grade.id} value={grade.id}>{grade.libelle}</Option>
                ))}
              </Select>
            </FormControl>
            <FormControl>
              <FormLabel>Fonction</FormLabel>
              <Select value={form.fonctionId} onChange={(_, value) => setForm((prev) => ({ ...prev, fonctionId: value }))} placeholder="Choisir…">
                {lookups.fonctions.map((fonction) => (
                  <Option key={fonction.id} value={fonction.id}>{fonction.libelle}</Option>
                ))}
              </Select>
            </FormControl>
            <FormControl>
              <FormLabel>Montant net</FormLabel>
              <Input value={form.montant} onChange={(event) => setForm((prev) => ({ ...prev, montant: event.target.value }))} />
            </FormControl>
            <Stack direction="row" spacing={1} justifyContent="flex-end">
              <Button variant="plain" color="neutral" disabled={saving} onClick={closeForm}>Annuler</Button>
              <Button loading={saving} onClick={handleSave}>{isEdit ? 'Enregistrer' : 'Ajouter'}</Button>
            </Stack>
          </Stack>
        </ModalDialog>
      </Modal>

      <ConfirmModal
        open={Boolean(pendingDelete)}
        title="Supprimer cette ligne ?"
        message="Les prochains mois n’utiliseront plus ce tarif."
        confirmLabel="Supprimer"
        color="danger"
        loading={confirmLoading}
        onClose={() => setPendingDelete(null)}
        onConfirm={handleDelete}
      />
    </Stack>
  );
}
