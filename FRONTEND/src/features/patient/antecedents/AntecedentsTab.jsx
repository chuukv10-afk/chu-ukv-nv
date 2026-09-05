import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  Box,
  Button,
  Card,
  Chip,
  CircularProgress,
  Divider,
  FormControl,
  FormLabel,
  IconButton,
  Input,
  Modal,
  ModalClose,
  ModalDialog,
  Option,
  Select,
  Sheet,
  Stack,
  Table,
  Typography,
} from '@mui/joy';
import { Plus, Search, Trash2, UserRound } from 'lucide-react';
import { referentiel } from '../../../api/endpoints.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { fetchMaladiesApi } from '../../clinique/maladies/maladiesApi.js';
import { createReferentielApi } from '../../referentiel/shared/referentielApi.js';
import {
  createConsultationAntecedentApi,
  createPatientAntecedentApi,
  deleteConsultationAntecedentApi,
  deletePatientAntecedentApi,
  fetchConsultationAntecedentsApi,
  fetchPatientAntecedentsApi,
} from './antecedentsApi.js';

const typesAntecedentApi = createReferentielApi(referentiel.typesAntecedent);

const TYPE_COLORS = ['primary', 'danger', 'warning', 'success', 'neutral'];

function formatDate(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function resolveTypeColor(index) {
  return TYPE_COLORS[index % TYPE_COLORS.length];
}

export default function AntecedentsTab({
  patientId,
  consultationId,
  readOnly = false,
  canCreate = false,
  canDelete = false,
  onChanged,
}) {
  const { showSuccess, showError } = useToast();
  const isConsultationMode = Boolean(consultationId);

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [records, setRecords] = useState([]);
  const [types, setTypes] = useState([]);
  const [filterTypeId, setFilterTypeId] = useState('');
  const [openModal, setOpenModal] = useState(false);
  const [confirmDeleteId, setConfirmDeleteId] = useState(null);
  const [form, setForm] = useState({ typeId: '', maladieId: '' });
  const [maladieQuery, setMaladieQuery] = useState('');
  const [maladieResults, setMaladieResults] = useState([]);
  const [maladieLoading, setMaladieLoading] = useState(false);
  const [selectedMaladie, setSelectedMaladie] = useState(null);
  const maladieDebounceRef = useRef(null);

  const typeColorMap = useMemo(() => {
    const map = new Map();
    types.forEach((type, index) => {
      map.set(String(type.id), resolveTypeColor(index));
    });
    return map;
  }, [types]);

  const loadTypes = useCallback(async () => {
    try {
      const items = await typesAntecedentApi.fetchLookup();
      setTypes(Array.isArray(items) ? items : []);
    } catch {
      setTypes([]);
    }
  }, []);

  const loadRecords = useCallback(async () => {
    if (!patientId && !consultationId) return;

    setLoading(true);
    setError('');
    try {
      const params = { page: 1, limit: 200 };
      const result = isConsultationMode
        ? await fetchConsultationAntecedentsApi(consultationId, params)
        : await fetchPatientAntecedentsApi(patientId, params);
      setRecords(Array.isArray(result.items) ? result.items : []);
    } catch (err) {
      setError(err.message || 'Impossible de charger les antécédents.');
      setRecords([]);
    } finally {
      setLoading(false);
    }
  }, [consultationId, isConsultationMode, patientId]);

  useEffect(() => {
    loadTypes();
  }, [loadTypes]);

  useEffect(() => {
    loadRecords();
  }, [loadRecords]);

  useEffect(() => {
    if (maladieDebounceRef.current) clearTimeout(maladieDebounceRef.current);

    if (!maladieQuery || maladieQuery.length < 2 || selectedMaladie) {
      setMaladieResults([]);
      return undefined;
    }

    maladieDebounceRef.current = setTimeout(async () => {
      try {
        setMaladieLoading(true);
        const result = await fetchMaladiesApi({ page: 1, limit: 15, search: maladieQuery.trim() });
        setMaladieResults(Array.isArray(result.items) ? result.items : []);
      } catch {
        setMaladieResults([]);
      } finally {
        setMaladieLoading(false);
      }
    }, 350);

    return () => {
      if (maladieDebounceRef.current) clearTimeout(maladieDebounceRef.current);
    };
  }, [maladieQuery, selectedMaladie]);

  const typeCounts = useMemo(() => {
    const counts = new Map();
    records.forEach((record) => {
      const typeId = record?.type?.id;
      if (typeId) {
        counts.set(String(typeId), (counts.get(String(typeId)) ?? 0) + 1);
      }
    });
    return counts;
  }, [records]);

  const displayedRecords = filterTypeId
    ? records.filter((record) => String(record?.type?.id) === filterTypeId)
    : records;

  const resetForm = () => {
    const defaultTypeId = types.length > 0 ? String(types[0].id) : '';
    setForm({ typeId: defaultTypeId, maladieId: '' });
    setMaladieQuery('');
    setMaladieResults([]);
    setSelectedMaladie(null);
  };

  const openAddModal = () => {
    resetForm();
    setOpenModal(true);
  };

  const handleAdd = async () => {
    if (!form.typeId) {
      showError('Sélectionnez un type d\'antécédent.');
      return;
    }
    if (!selectedMaladie?.id) {
      showError('Sélectionnez une maladie (CIM-10).');
      return;
    }

    setSaving(true);
    try {
      const payload = {
        typeId: Number(form.typeId),
        maladieId: Number(selectedMaladie.id),
      };

      if (isConsultationMode) {
        await createConsultationAntecedentApi(consultationId, payload);
      } else {
        await createPatientAntecedentApi(patientId, payload);
      }

      setOpenModal(false);
      resetForm();
      await loadRecords();
      onChanged?.();
      showSuccess('Antécédent ajouté.');
    } catch (err) {
      showError(err.message || 'Ajout impossible.');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (antecedentId) => {
    setSaving(true);
    try {
      if (isConsultationMode) {
        await deleteConsultationAntecedentApi(consultationId, antecedentId);
      } else {
        await deletePatientAntecedentApi(patientId, antecedentId);
      }

      setConfirmDeleteId(null);
      await loadRecords();
      onChanged?.();
      showSuccess('Antécédent supprimé.');
    } catch (err) {
      showError(err.message || 'Suppression impossible.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Stack spacing={2}>
      <Stack direction="row" spacing={1.5} alignItems="center">
        <UserRound size={20} color={LOTRU_PRIMARY[600]} />
        <Typography level="title-md" sx={{ fontWeight: 700, color: LOTRU_NEUTRAL[900] }}>
          Antécédents
        </Typography>
      </Stack>

      {error ? (
        <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
          {error}
        </Typography>
      ) : null}

      <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
        <Chip
          size="sm"
          variant={filterTypeId === '' ? 'solid' : 'soft'}
          color="neutral"
          onClick={() => setFilterTypeId('')}
          sx={{ cursor: 'pointer', fontWeight: 700 }}
        >
          Tous ({records.length})
        </Chip>
        {types.map((type, index) => (
          <Chip
            key={type.id}
            size="sm"
            variant={filterTypeId === String(type.id) ? 'solid' : 'soft'}
            color={resolveTypeColor(index)}
            onClick={() => setFilterTypeId(filterTypeId === String(type.id) ? '' : String(type.id))}
            sx={{ cursor: 'pointer', fontWeight: 600 }}
          >
            {type.libelle} ({typeCounts.get(String(type.id)) ?? 0})
          </Chip>
        ))}
      </Stack>

      <Card variant="outlined" sx={{ borderRadius: 'lg', p: 0 }}>
        <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ p: 2 }}>
          <Typography level="title-sm" sx={{ fontWeight: 700 }}>
            Historique des antécédents
          </Typography>
          {canCreate && !readOnly ? (
            <Button size="sm" startDecorator={<Plus size={16} />} onClick={openAddModal}>
              Ajouter
            </Button>
          ) : null}
        </Stack>
        <Divider />
        {loading ? (
          <Box sx={{ display: 'flex', justifyContent: 'center', py: 3 }}>
            <CircularProgress size="sm" />
          </Box>
        ) : displayedRecords.length === 0 ? (
          <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600], p: 2.5 }}>
            Aucun antécédent enregistré.
          </Typography>
        ) : (
          <Sheet variant="outlined" sx={{ borderRadius: 0, border: 'none', overflow: 'auto' }}>
            <Table stickyHeader hoverRow sx={{ minWidth: 720 }}>
              <thead>
                <tr>
                  <th>Type</th>
                  <th>Maladie (CIM-10)</th>
                  <th>Date</th>
                  {canDelete && !readOnly ? <th style={{ textAlign: 'right' }}>Action</th> : null}
                </tr>
              </thead>
              <tbody>
                {displayedRecords.map((record) => (
                  <tr key={record.id}>
                    <td>
                      <Chip
                        size="sm"
                        variant="soft"
                        color={typeColorMap.get(String(record?.type?.id)) ?? 'neutral'}
                      >
                        {record?.type?.libelle ?? '—'}
                      </Chip>
                    </td>
                    <td>
                      {record?.maladie ? (
                        <Chip size="sm" variant="outlined" color="primary">
                          {record.maladie.codeCim10} — {record.maladie.libelle}
                        </Chip>
                      ) : (
                        '—'
                      )}
                    </td>
                    <td>{formatDate(record.createdAt)}</td>
                    {canDelete && !readOnly ? (
                      <td style={{ textAlign: 'right' }}>
                        <IconButton
                          size="sm"
                          variant="soft"
                          color="danger"
                          onClick={() => setConfirmDeleteId(record.id)}
                        >
                          <Trash2 size={16} />
                        </IconButton>
                      </td>
                    ) : null}
                  </tr>
                ))}
              </tbody>
            </Table>
          </Sheet>
        )}
      </Card>

      <Modal open={openModal} onClose={() => !saving && setOpenModal(false)}>
        <ModalDialog sx={{ borderRadius: 'lg', maxWidth: 520, width: '100%' }}>
          <ModalClose />
          <Typography level="title-lg" sx={{ fontWeight: 700, mb: 1 }}>
            Ajouter un antécédent
          </Typography>
          <Divider sx={{ mb: 2 }} />
          <Stack spacing={2}>
            <FormControl required>
              <FormLabel>Type d&apos;antécédent</FormLabel>
              <Select
                placeholder="Sélectionner…"
                value={form.typeId}
                onChange={(_, value) => setForm((current) => ({ ...current, typeId: value ?? '' }))}
              >
                {types.map((type) => (
                  <Option key={type.id} value={String(type.id)}>
                    {type.libelle}
                  </Option>
                ))}
              </Select>
            </FormControl>

            <FormControl required>
              <FormLabel>Maladie (CIM-10)</FormLabel>
              <Input
                placeholder="Rechercher par code ou libellé…"
                value={maladieQuery}
                onChange={(event) => {
                  setMaladieQuery(event.target.value);
                  setSelectedMaladie(null);
                  setForm((current) => ({ ...current, maladieId: '' }));
                }}
                endDecorator={maladieLoading ? <CircularProgress size="sm" /> : <Search size={16} />}
              />
              {selectedMaladie ? (
                <Chip
                  size="sm"
                  variant="soft"
                  color="primary"
                  sx={{ mt: 1, alignSelf: 'flex-start' }}
                  endDecorator={(
                    <IconButton
                      size="sm"
                      variant="plain"
                      onClick={() => {
                        setSelectedMaladie(null);
                        setMaladieQuery('');
                        setForm((current) => ({ ...current, maladieId: '' }));
                      }}
                    >
                      ×
                    </IconButton>
                  )}
                >
                  {selectedMaladie.codeCim10} — {selectedMaladie.libelle}
                </Chip>
              ) : null}
              {maladieResults.length > 0 && !selectedMaladie ? (
                <Sheet variant="outlined" sx={{ borderRadius: 'md', mt: 1, maxHeight: 180, overflow: 'auto' }}>
                  {maladieResults.map((maladie) => (
                    <Box
                      key={maladie.id}
                      onClick={() => {
                        setSelectedMaladie(maladie);
                        setMaladieQuery(maladie.codeCim10);
                        setMaladieResults([]);
                        setForm((current) => ({ ...current, maladieId: String(maladie.id) }));
                      }}
                      sx={{
                        px: 2,
                        py: 1,
                        cursor: 'pointer',
                        '&:hover': { bgcolor: 'primary.50' },
                        borderBottom: '1px solid',
                        borderColor: 'divider',
                      }}
                    >
                      <Typography level="body-sm">
                        <strong>{maladie.codeCim10}</strong> — {maladie.libelle}
                      </Typography>
                    </Box>
                  ))}
                </Sheet>
              ) : null}
            </FormControl>
          </Stack>
          <Stack direction="row" spacing={1.5} justifyContent="flex-end" sx={{ mt: 3 }}>
            <Button variant="soft" color="neutral" onClick={() => setOpenModal(false)} disabled={saving}>
              Annuler
            </Button>
            <Button loading={saving} onClick={handleAdd}>
              Ajouter
            </Button>
          </Stack>
        </ModalDialog>
      </Modal>

      <Modal open={confirmDeleteId != null} onClose={() => !saving && setConfirmDeleteId(null)}>
        <ModalDialog variant="outlined" role="alertdialog" sx={{ borderRadius: 'lg', maxWidth: 400 }}>
          <Typography level="title-lg" sx={{ fontWeight: 700 }}>
            Supprimer cet antécédent ?
          </Typography>
          <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600], mt: 1 }}>
            Cette action est irréversible.
          </Typography>
          <Stack direction="row" spacing={1.5} justifyContent="flex-end" sx={{ mt: 2 }}>
            <Button variant="soft" color="neutral" onClick={() => setConfirmDeleteId(null)} disabled={saving}>
              Annuler
            </Button>
            <Button color="danger" loading={saving} onClick={() => handleDelete(confirmDeleteId)}>
              Supprimer
            </Button>
          </Stack>
        </ModalDialog>
      </Modal>
    </Stack>
  );
}

