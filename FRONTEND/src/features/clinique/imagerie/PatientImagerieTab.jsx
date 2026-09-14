import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Button, Chip, FormControl, FormLabel, Modal, ModalDialog, Option, Select, Sheet, Stack, Table, Textarea, Typography,
} from '@mui/joy';
import { Plus, ScanLine } from 'lucide-react';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { fetchExamensApi } from '../examens/examensApi.js';
import { formatDateTime } from '../../pharmacie/shared/format.js';
import {
  IMAGERIE_STATUT_COLORS,
  IMAGERIE_STATUT_LABELS,
  imagerieDetailPath,
} from './imagerieConstants.js';
import { createEtudeImagerieApi, fetchEtudesImagerieApi } from './imagerieApi.js';

export default function PatientImagerieTab({ patientId, patientName = '' }) {
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canRead = hasPermission(PERMISSIONS.CLINIQUE.IMAGERIE_READ);
  const canCreate = hasPermission(PERMISSIONS.CLINIQUE.IMAGERIE_CREATE);

  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [createOpen, setCreateOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [examens, setExamens] = useState([]);
  const [form, setForm] = useState({ examenId: '', indication: '' });

  const load = useCallback(async () => {
    if (!canRead || !patientId) return;
    setLoading(true);
    setError('');
    try {
      const result = await fetchEtudesImagerieApi({ page: 1, limit: 50, patientId });
      setItems(result.items || []);
    } catch (err) {
      setError(err.message || 'Impossible de charger l\'imagerie de ce patient.');
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, [canRead, patientId]);

  useEffect(() => { load(); }, [load]);

  useEffect(() => {
    if (!createOpen) return;
    fetchExamensApi({ page: 1, limit: 100, imagerie: true })
      .then((result) => setExamens(result.items || []))
      .catch(() => setExamens([]));
  }, [createOpen]);

  const openDetail = (id) => {
    navigate(imagerieDetailPath(id), {
      state: { fromDpi: patientId },
    });
  };

  const handleCreate = async () => {
    if (!form.examenId) {
      showError('Sélectionnez l\'examen d\'imagerie.');
      return;
    }
    setSaving(true);
    try {
      const created = await createEtudeImagerieApi({
        patientId,
        examenId: Number(form.examenId),
        indication: form.indication || null,
      });
      showSuccess('Étude créée. Vous pouvez maintenant charger les images.');
      setCreateOpen(false);
      openDetail(created.id);
    } catch (err) {
      showError(err.message || 'Création impossible.');
    } finally {
      setSaving(false);
    }
  };

  if (!canRead) {
    return (
      <Typography level="body-sm" color="warning" sx={{ bgcolor: 'warning.50', p: 1.5, borderRadius: 'md' }}>
        Permission insuffisante pour consulter l&apos;imagerie.
      </Typography>
    );
  }

  return (
    <Stack spacing={2}>
      <Stack direction="row" justifyContent="space-between" alignItems="center" flexWrap="wrap" gap={1}>
        <Typography level="title-md" startDecorator={<ScanLine size={18} />}>
          Imagerie de ce dossier
        </Typography>
        {canCreate ? (
          <Button size="sm" startDecorator={<Plus size={16} />} onClick={() => setCreateOpen(true)} sx={{ bgcolor: LOTRU_PRIMARY[500] }}>
            Nouvelle étude
          </Button>
        ) : null}
      </Stack>
      {error ? <Typography color="danger">{error}</Typography> : null}
      <Sheet variant="outlined" sx={{ overflow: 'auto', borderRadius: 'sm' }}>
        <Table stickyHeader hoverRow>
          <thead>
            <tr>
              <th>N°</th>
              <th>Date</th>
              <th>Examen</th>
              <th>Images</th>
              <th>Statut</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr><td colSpan={5}>Chargement…</td></tr>
            ) : items.length === 0 ? (
              <tr><td colSpan={5}>Aucune étude d&apos;imagerie pour ce patient.</td></tr>
            ) : items.map((item) => (
              <tr key={item.id} onClick={() => openDetail(item.id)} style={{ cursor: 'pointer' }}>
                <td>{item.numero}</td>
                <td>{formatDateTime(item.createdAt)}</td>
                <td>{item.examen?.libelle || '—'}</td>
                <td>{item.imagesCount ?? 0}</td>
                <td>
                  <Chip size="sm" color={IMAGERIE_STATUT_COLORS[item.statut] || 'neutral'} variant="soft">
                    {IMAGERIE_STATUT_LABELS[item.statut] || item.statut}
                  </Chip>
                </td>
              </tr>
            ))}
          </tbody>
        </Table>
      </Sheet>

      <Modal open={createOpen} onClose={() => setCreateOpen(false)}>
        <ModalDialog sx={{ width: 520, maxWidth: '95vw' }}>
          <Typography level="h4">Nouvelle étude d&apos;imagerie</Typography>
          <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600] }}>
            Patient : <strong>{patientName || 'dossier courant'}</strong>
          </Typography>
          <Stack spacing={1.5} sx={{ mt: 1 }}>
            <FormControl>
              <FormLabel>Examen</FormLabel>
              <Select
                value={form.examenId}
                onChange={(_, value) => setForm((current) => ({ ...current, examenId: value || '' }))}
                placeholder="Radio, echo, scanner…"
              >
                {examens.map((examen) => (
                  <Option key={examen.id} value={String(examen.id)}>{examen.libelle}</Option>
                ))}
              </Select>
            </FormControl>
            <FormControl>
              <FormLabel>Indication clinique</FormLabel>
              <Textarea minRows={3} value={form.indication} onChange={(e) => setForm((current) => ({ ...current, indication: e.target.value }))} />
            </FormControl>
            <Stack direction="row" justifyContent="flex-end" spacing={1}>
              <Button variant="plain" color="neutral" onClick={() => setCreateOpen(false)}>Annuler</Button>
              <Button loading={saving} onClick={handleCreate} sx={{ bgcolor: LOTRU_PRIMARY[500] }}>Créer</Button>
            </Stack>
          </Stack>
        </ModalDialog>
      </Modal>
    </Stack>
  );
}
