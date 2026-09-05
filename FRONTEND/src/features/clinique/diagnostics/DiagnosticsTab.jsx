import { useCallback, useEffect, useRef, useState } from 'react';
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
  Textarea,
  Typography,
} from '@mui/joy';
import { HeartPulse, Plus, Search, Trash2 } from 'lucide-react';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { CONSULTATION_TYPE_LABELS } from '../consultations/consultationConstants.js';
import { fetchMaladiesApi } from '../maladies/maladiesApi.js';
import {
  DEFAULT_DIAGNOSTIC_FORM,
  DIAGNOSTIC_CERTITUDE_COLORS,
  DIAGNOSTIC_CERTITUDE_LABELS,
  DIAGNOSTIC_TYPE_COLORS,
  DIAGNOSTIC_TYPE_LABELS,
} from './diagnosticConstants.js';
import {
  createConsultationDiagnosticApi,
  deleteConsultationDiagnosticApi,
  fetchConsultationDiagnosticsApi,
  fetchDiagnosticMetaApi,
  fetchPatientDiagnosticsApi,
} from './diagnosticsApi.js';

function formatDateTime(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('fr-FR');
}

export default function DiagnosticsTab({
  patientId,
  consultationId,
  readOnly = false,
  canCreate = false,
  canDelete = false,
  showConsultationContext = false,
  stayScope = false,
  onRecordsChange,
}) {
  const { showSuccess, showError } = useToast();
  const { hasPermission } = usePermissions();
  const isConsultationMode = Boolean(consultationId);
  const allowCreate = !readOnly && hasPermission(PERMISSIONS.CLINIQUE.DIAGNOSTIC_CREATE);
  const allowDelete = !readOnly && hasPermission(PERMISSIONS.CLINIQUE.DIAGNOSTIC_DELETE);

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [records, setRecords] = useState([]);
  const [meta, setMeta] = useState({ types: [], certitudes: [] });
  const [openModal, setOpenModal] = useState(false);
  const [confirmDeleteId, setConfirmDeleteId] = useState(null);
  const [form, setForm] = useState(DEFAULT_DIAGNOSTIC_FORM);
  const [maladieQuery, setMaladieQuery] = useState('');
  const [maladieResults, setMaladieResults] = useState([]);
  const [maladieLoading, setMaladieLoading] = useState(false);
  const [selectedMaladie, setSelectedMaladie] = useState(null);
  const maladieDebounceRef = useRef(null);

  const loadMeta = useCallback(async () => {
    try {
      const data = await fetchDiagnosticMetaApi();
      setMeta({
        types: Array.isArray(data?.types) ? data.types : Object.keys(DIAGNOSTIC_TYPE_LABELS),
        certitudes: Array.isArray(data?.certitudes) ? data.certitudes : Object.keys(DIAGNOSTIC_CERTITUDE_LABELS),
      });
    } catch {
      setMeta({
        types: Object.keys(DIAGNOSTIC_TYPE_LABELS),
        certitudes: Object.keys(DIAGNOSTIC_CERTITUDE_LABELS),
      });
    }
  }, []);

  const loadRecords = useCallback(async () => {
    if (!patientId && !consultationId) return;

    setLoading(true);
    setError('');
    try {
      const params = { page: 1, limit: 200 };
      if (stayScope) {
        params.scope = 'visite';
      }
      const result = isConsultationMode
        ? await fetchConsultationDiagnosticsApi(consultationId, params)
        : await fetchPatientDiagnosticsApi(patientId, params);
      setRecords(Array.isArray(result.items) ? result.items : []);
    } catch (err) {
      setError(err.message || 'Impossible de charger les diagnostics.');
      setRecords([]);
    } finally {
      setLoading(false);
    }
  }, [consultationId, isConsultationMode, patientId, stayScope]);

  useEffect(() => {
    loadMeta();
  }, [loadMeta]);

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

  const resetForm = () => {
    setForm(DEFAULT_DIAGNOSTIC_FORM);
    setMaladieQuery('');
    setMaladieResults([]);
    setSelectedMaladie(null);
  };

  const handleAdd = async () => {
    if (!allowCreate) {
      showError('Permission insuffisante pour ajouter un diagnostic.');
      return;
    }
    if (!selectedMaladie?.id) {
      showError('Sélectionnez une maladie (CIM-10).');
      return;
    }

    setSaving(true);
    try {
      await createConsultationDiagnosticApi(consultationId, {
        maladieId: Number(selectedMaladie.id),
        type: form.type,
        certitude: form.certitude,
        remarque: form.remarque.trim() || null,
      });
      setOpenModal(false);
      resetForm();
      await loadRecords();
      onRecordsChange?.();
      showSuccess('Diagnostic ajouté.');
    } catch (err) {
      showError(err.message || 'Ajout impossible.');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (diagnosticId) => {
    setSaving(true);
    try {
      await deleteConsultationDiagnosticApi(consultationId, diagnosticId);
      setConfirmDeleteId(null);
      await loadRecords();
      onRecordsChange?.();
      showSuccess('Diagnostic supprimé.');
    } catch (err) {
      showError(err.message || 'Suppression impossible.');
    } finally {
      setSaving(false);
    }
  };

  const showContext = showConsultationContext || stayScope;
  const showActions = isConsultationMode && allowDelete;
  const showAdd = isConsultationMode && allowCreate;
  const canDeleteRecord = (record) => showActions && (!stayScope || record.fromCurrentConsultation);

  return (
    <Stack spacing={2}>
      <Stack direction="row" spacing={1.5} alignItems="center">
        <HeartPulse size={20} color={LOTRU_PRIMARY[600]} />
        <Box>
          <Typography level="title-md" sx={{ fontWeight: 700, color: LOTRU_NEUTRAL[900] }}>
            {stayScope ? 'Diagnostics du séjour' : 'Diagnostics CIM-10'}
          </Typography>
          {stayScope ? (
            <Typography level="body-xs" sx={{ color: LOTRU_NEUTRAL[600] }}>
              Tous les diagnostics posés depuis l&apos;admission. Seuls ceux de ce tour peuvent être retirés.
            </Typography>
          ) : null}
        </Box>
      </Stack>

      {error ? (
        <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
          {error}
        </Typography>
      ) : null}

      <Card variant="outlined" sx={{ borderRadius: 'lg', p: 0 }}>
        <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ p: 2 }}>
          <Typography level="title-sm" sx={{ fontWeight: 700 }}>
            {stayScope
              ? 'Diagnostics du séjour hospitalier'
              : showContext ? 'Historique des diagnostics' : 'Diagnostics posés'}
          </Typography>
          {showAdd ? (
            <Button size="sm" startDecorator={<Plus size={16} />} onClick={() => { resetForm(); setOpenModal(true); }}>
              Ajouter
            </Button>
          ) : null}
        </Stack>
        <Divider />
        {loading ? (
          <Box sx={{ display: 'flex', justifyContent: 'center', py: 3 }}>
            <CircularProgress size="sm" />
          </Box>
        ) : records.length === 0 ? (
          <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600], p: 2.5 }}>
            {stayScope
              ? 'Aucun diagnostic sur ce séjour.'
              : 'Aucun diagnostic enregistré.'}
          </Typography>
        ) : (
          <Sheet variant="outlined" sx={{ borderRadius: 0, border: 'none', overflow: 'auto' }}>
            <Table stickyHeader hoverRow sx={{ minWidth: 760 }}>
              <thead>
                <tr>
                  {showContext ? <th>Consultation</th> : null}
                  {stayScope ? <th>Origine</th> : null}
                  <th>Code CIM-10</th>
                  <th>Libellé</th>
                  <th>Type</th>
                  <th>Certitude</th>
                  <th>Examen lié</th>
                  <th>Remarque</th>
                  <th>Date</th>
                  {showActions ? <th style={{ textAlign: 'right' }}>Action</th> : null}
                </tr>
              </thead>
              <tbody>
                {records.map((record) => (
                  <tr key={record.id}>
                    {showContext ? (
                      <td>
                        <Typography level="body-xs">
                          {formatDateTime(record.consultation?.consultedAt)}
                          {record.consultation?.service?.libelle ? ` · ${record.consultation.service.libelle}` : ''}
                        </Typography>
                      </td>
                    ) : null}
                    {stayScope ? (
                      <td>
                        {record.fromCurrentConsultation ? (
                          <Chip size="sm" variant="solid" color="warning">Ce tour</Chip>
                        ) : (
                          <Chip size="sm" variant="soft" color="neutral">
                            {CONSULTATION_TYPE_LABELS[record.consultation?.typeConsultation] ?? 'Séjour'}
                          </Chip>
                        )}
                      </td>
                    ) : null}
                    <td>
                      <Chip size="sm" variant="outlined" color="primary">
                        {record.maladie?.codeCim10 ?? '—'}
                      </Chip>
                    </td>
                    <td>{record.maladie?.libelle ?? '—'}</td>
                    <td>
                      <Chip size="sm" variant="soft" color={DIAGNOSTIC_TYPE_COLORS[record.type] ?? 'neutral'}>
                        {DIAGNOSTIC_TYPE_LABELS[record.type] ?? record.type ?? '—'}
                      </Chip>
                    </td>
                    <td>
                      <Chip size="sm" variant="soft" color={DIAGNOSTIC_CERTITUDE_COLORS[record.certitude] ?? 'neutral'}>
                        {DIAGNOSTIC_CERTITUDE_LABELS[record.certitude] ?? record.certitude ?? '—'}
                      </Chip>
                    </td>
                    <td>
                      {record.demandeExamen?.examen?.libelle ? (
                        <Typography level="body-sm" sx={{ fontWeight: 600, color: LOTRU_PRIMARY[700] }}>
                          {record.demandeExamen.examen.libelle}
                        </Typography>
                      ) : (
                        '—'
                      )}
                    </td>
                    <td>{record.remarque ?? '—'}</td>
                    <td>{formatDateTime(record.createdAt)}</td>
                    {showActions ? (
                      <td style={{ textAlign: 'right' }}>
                        {canDeleteRecord(record) ? (
                          <IconButton
                            size="sm"
                            variant="soft"
                            color="danger"
                            onClick={() => setConfirmDeleteId(record.id)}
                          >
                            <Trash2 size={16} />
                          </IconButton>
                        ) : (
                          '—'
                        )}
                      </td>
                    ) : null}
                  </tr>
                ))}
              </tbody>
            </Table>
          </Sheet>
        )}
      </Card>

      {showAdd ? (
        <Modal open={openModal} onClose={() => !saving && setOpenModal(false)}>
          <ModalDialog sx={{ borderRadius: 'lg', maxWidth: 540, width: '100%' }}>
            <ModalClose />
            <Typography level="title-lg" sx={{ fontWeight: 700, mb: 1 }}>
              Ajouter un diagnostic
            </Typography>
            <Divider sx={{ mb: 2 }} />
            <Stack spacing={2}>
              <FormControl required>
                <FormLabel>Maladie (CIM-10)</FormLabel>
                <Input
                  placeholder="Rechercher par code ou libellé…"
                  value={maladieQuery}
                  onChange={(event) => {
                    setMaladieQuery(event.target.value);
                    setSelectedMaladie(null);
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

              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Type</FormLabel>
                  <Select
                    value={form.type}
                    onChange={(_, value) => setForm((current) => ({ ...current, type: value ?? 'PROVISOIRE' }))}
                  >
                    {meta.types.map((type) => (
                      <Option key={type} value={type}>{DIAGNOSTIC_TYPE_LABELS[type] ?? type}</Option>
                    ))}
                  </Select>
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Certitude</FormLabel>
                  <Select
                    value={form.certitude}
                    onChange={(_, value) => setForm((current) => ({ ...current, certitude: value ?? 'SUSPECTE' }))}
                  >
                    {meta.certitudes.map((certitude) => (
                      <Option key={certitude} value={certitude}>{DIAGNOSTIC_CERTITUDE_LABELS[certitude] ?? certitude}</Option>
                    ))}
                  </Select>
                </FormControl>
              </Stack>

              <FormControl>
                <FormLabel>Remarque (optionnel)</FormLabel>
                <Textarea
                  minRows={2}
                  value={form.remarque}
                  onChange={(event) => setForm((current) => ({ ...current, remarque: event.target.value }))}
                  placeholder="Notes complémentaires…"
                />
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
      ) : null}

      {showActions ? (
        <Modal open={confirmDeleteId != null} onClose={() => !saving && setConfirmDeleteId(null)}>
          <ModalDialog variant="outlined" role="alertdialog" sx={{ borderRadius: 'lg', maxWidth: 400 }}>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>
              Supprimer ce diagnostic ?
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
      ) : null}
    </Stack>
  );
}

