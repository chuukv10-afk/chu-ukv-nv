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
  Sheet,
  Stack,
  Table,
  Textarea,
  Typography,
} from '@mui/joy';
import { FlaskConical, HeartPulse, Plus, Search, XCircle } from 'lucide-react';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { fetchExamensApi } from '../examens/examensApi.js';
import LinkedDiagnosticModal from './components/LinkedDiagnosticModal.jsx';
import LinkedDiagnosticsCell from './components/LinkedDiagnosticsCell.jsx';
import {
  DEMANDE_EXAMEN_STATUT_COLORS,
  DEMANDE_EXAMEN_STATUT_LABELS,
  RESULTAT_MAX_LENGTH,
} from './demandeExamenConstants.js';
import {
  annulerDemandeApi,
  createConsultationDemandeExamenApi,
  createLinkedDiagnosticApi,
  fetchConsultationDemandesExamenApi,
  fetchPatientDemandesExamenApi,
  prendreEnChargeDemandeApi,
  saisirResultatDemandeApi,
  validerDemandeApi,
} from './demandesExamenApi.js';

function formatDateTime(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('fr-FR');
}

function examenLabel(record) {
  return record?.examen?.libelle ?? '—';
}

export default function DemandesExamenTab({
  patientId,
  consultationId,
  readOnly = false,
  canCreate = false,
  canCancel = false,
  canSaisie = false,
  canValidate = false,
  canCreateDiagnostic = false,
  showConsultationContext = false,
}) {
  const { showSuccess, showError } = useToast();
  const { hasPermission } = usePermissions();
  const isConsultationMode = Boolean(consultationId);
  const allowCreate = canCreate && !readOnly && hasPermission(PERMISSIONS.CLINIQUE.DEMANDE_EXAMEN_CREATE);
  const allowCancel = canCancel && !readOnly && hasPermission(PERMISSIONS.CLINIQUE.DEMANDE_EXAMEN_CANCEL);
  const allowSaisie = canSaisie && hasPermission(PERMISSIONS.CLINIQUE.DEMANDE_EXAMEN_SAISIE_RESULTAT);
  const allowValidate = canValidate && hasPermission(PERMISSIONS.CLINIQUE.DEMANDE_EXAMEN_VALIDATE);
  const allowCreateDiagnostic = !readOnly && hasPermission(PERMISSIONS.CLINIQUE.DIAGNOSTIC_CREATE);

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [records, setRecords] = useState([]);

  const [openCreate, setOpenCreate] = useState(false);
  const [noteMedecin, setNoteMedecin] = useState('');
  const [examenQuery, setExamenQuery] = useState('');
  const [examenResults, setExamenResults] = useState([]);
  const [examenLoading, setExamenLoading] = useState(false);
  const [selectedExamen, setSelectedExamen] = useState(null);
  const examenDebounceRef = useRef(null);

  const [confirmCancelId, setConfirmCancelId] = useState(null);
  const [resultatTarget, setResultatTarget] = useState(null);
  const [resultatForm, setResultatForm] = useState({ resultat: '', fichier: '' });
  const [diagnosticTarget, setDiagnosticTarget] = useState(null);

  const loadRecords = useCallback(async () => {
    if (!patientId && !consultationId) return;
    setLoading(true);
    setError('');
    try {
      const params = { page: 1, limit: 200 };
      const result = isConsultationMode
        ? await fetchConsultationDemandesExamenApi(consultationId, params)
        : await fetchPatientDemandesExamenApi(patientId, params);
      setRecords(Array.isArray(result.items) ? result.items : []);
    } catch (err) {
      setError(err.message || 'Impossible de charger les demandes d\'examen.');
      setRecords([]);
    } finally {
      setLoading(false);
    }
  }, [consultationId, isConsultationMode, patientId]);

  useEffect(() => {
    loadRecords();
  }, [loadRecords]);

  useEffect(() => {
    if (examenDebounceRef.current) clearTimeout(examenDebounceRef.current);
    if (!examenQuery || examenQuery.length < 2 || selectedExamen) {
      setExamenResults([]);
      return undefined;
    }
    examenDebounceRef.current = setTimeout(async () => {
      try {
        setExamenLoading(true);
        const result = await fetchExamensApi({ page: 1, limit: 15, search: examenQuery.trim() });
        setExamenResults(Array.isArray(result.items) ? result.items : []);
      } catch {
        setExamenResults([]);
      } finally {
        setExamenLoading(false);
      }
    }, 350);
    return () => {
      if (examenDebounceRef.current) clearTimeout(examenDebounceRef.current);
    };
  }, [examenQuery, selectedExamen]);

  const resetCreate = () => {
    setNoteMedecin('');
    setExamenQuery('');
    setExamenResults([]);
    setSelectedExamen(null);
  };

  const handleCreate = async () => {
    if (!selectedExamen?.id) {
      showError('Sélectionnez un examen du catalogue.');
      return;
    }
    setSaving(true);
    try {
      await createConsultationDemandeExamenApi(consultationId, {
        examenId: Number(selectedExamen.id),
        noteMedecin: noteMedecin.trim() || null,
      });
      setOpenCreate(false);
      resetCreate();
      await loadRecords();
      showSuccess('Demande d\'examen créée.');
    } catch (err) {
      showError(err.message || 'Création impossible.');
    } finally {
      setSaving(false);
    }
  };

  const runAction = async (action, successMessage) => {
    setSaving(true);
    try {
      await action();
      await loadRecords();
      showSuccess(successMessage);
    } catch (err) {
      showError(err.message || 'Action impossible.');
    } finally {
      setSaving(false);
    }
  };

  const handleCancel = (demandeId) => runAction(async () => {
    await annulerDemandeApi(demandeId);
    setConfirmCancelId(null);
  }, 'Demande annulée.');

  const handleTake = (demandeId) => runAction(
    () => prendreEnChargeDemandeApi(demandeId),
    'Demande prise en charge.',
  );

  const handleValidate = (demandeId) => runAction(
    () => validerDemandeApi(demandeId),
    'Résultat validé.',
  );

  const handleResultat = async () => {
    if (!resultatForm.resultat.trim()) {
      showError('Saisissez le résultat.');
      return;
    }
    if (resultatForm.resultat.length > RESULTAT_MAX_LENGTH) {
      showError(`Le résultat ne peut pas dépasser ${RESULTAT_MAX_LENGTH} caractères.`);
      return;
    }
    setSaving(true);
    try {
      await saisirResultatDemandeApi(resultatTarget.id, {
        resultat: resultatForm.resultat.trim(),
        fichier: resultatForm.fichier.trim() || null,
      });
      setResultatTarget(null);
      await loadRecords();
      showSuccess('Résultat enregistré.');
    } catch (err) {
      showError(err.message || 'Saisie impossible.');
    } finally {
      setSaving(false);
    }
  };

  const handleLinkedDiagnostic = async (payload) => {
    if (!allowCreateDiagnostic) {
      showError('Permission insuffisante pour poser un diagnostic.');
      return;
    }
    if (!payload.maladieId) {
      showError('Sélectionnez une maladie (CIM-10).');
      return;
    }
    setSaving(true);
    try {
      await createLinkedDiagnosticApi(diagnosticTarget.id, payload);
      setDiagnosticTarget(null);
      await loadRecords();
      showSuccess('Diagnostic lié enregistré.');
    } catch (err) {
      showError(err.message || 'Ajout du diagnostic impossible.');
    } finally {
      setSaving(false);
    }
  };

  const showCreate = isConsultationMode && allowCreate;
  const showLabActions = allowSaisie || allowValidate;
  const showRowActions = showCreate || allowCancel || showLabActions || allowCreateDiagnostic;

  return (
    <Stack spacing={2}>
      <Stack direction="row" spacing={1.5} alignItems="center">
        <FlaskConical size={20} color={LOTRU_PRIMARY[600]} />
        <Typography level="title-md" sx={{ fontWeight: 700, color: LOTRU_NEUTRAL[900] }}>
          Demandes d&apos;examens
        </Typography>
      </Stack>

      {error ? (
        <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
          {error}
        </Typography>
      ) : null}

      <Card variant="outlined" sx={{ borderRadius: 'lg', p: 0 }}>
        <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ p: 2 }}>
          <Typography level="title-sm" sx={{ fontWeight: 700 }}>
            {showConsultationContext ? 'Historique des examens' : 'Examens prescrits'}
          </Typography>
          {showCreate ? (
            <Button size="sm" startDecorator={<Plus size={16} />} onClick={() => { resetCreate(); setOpenCreate(true); }}>
              Demander
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
            Aucune demande d&apos;examen.
          </Typography>
        ) : (
          <Sheet variant="outlined" sx={{ borderRadius: 0, border: 'none', overflow: 'auto' }}>
            <Table stickyHeader hoverRow sx={{ minWidth: 860 }}>
              <thead>
                <tr>
                  {showConsultationContext ? <th>Consultation</th> : null}
                  <th>Examen</th>
                  <th>Type</th>
                  <th>Statut</th>
                  <th>Diagnostic lié</th>
                  <th>Note</th>
                  <th>Résultat</th>
                  <th>Date</th>
                  {showRowActions ? <th style={{ textAlign: 'right' }}>Actions</th> : null}
                </tr>
              </thead>
              <tbody>
                {records.map((record) => (
                  <tr key={record.id}>
                    {showConsultationContext ? (
                      <td>
                        <Typography level="body-xs">
                          {formatDateTime(record.consultation?.consultedAt)}
                          {record.consultation?.service?.libelle ? ` · ${record.consultation.service.libelle}` : ''}
                        </Typography>
                      </td>
                    ) : null}
                    <td>
                      <Typography level="body-sm">{examenLabel(record)}</Typography>
                    </td>
                    <td>
                      <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[700] }}>
                        {record.examen?.typeExamen?.libelle ?? '—'}
                      </Typography>
                    </td>
                    <td>
                      <Chip size="sm" variant="soft" color={DEMANDE_EXAMEN_STATUT_COLORS[record.statut] ?? 'neutral'}>
                        {DEMANDE_EXAMEN_STATUT_LABELS[record.statut] ?? record.statut}
                      </Chip>
                    </td>
                    <td>
                      <LinkedDiagnosticsCell diagnostics={record.diagnostics} />
                    </td>
                    <td>{record.noteMedecin ?? '—'}</td>
                    <td>
                      <Typography level="body-xs" sx={{ maxWidth: 220, whiteSpace: 'pre-wrap' }}>
                        {record.resultat ?? '—'}
                      </Typography>
                    </td>
                    <td>{formatDateTime(record.demandeAt)}</td>
                    {showRowActions ? (
                      <td style={{ textAlign: 'right' }}>
                        <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                          {allowSaisie && record.statut === 'DEMANDE' ? (
                            <Button size="sm" variant="soft" disabled={saving} onClick={() => handleTake(record.id)}>
                              Prendre en charge
                            </Button>
                          ) : null}
                          {allowSaisie && record.statut === 'EN_COURS' ? (
                            <Button
                              size="sm"
                              variant="soft"
                              disabled={saving}
                              onClick={() => {
                                setResultatForm({ resultat: record.resultat ?? '', fichier: record.fichier ?? '' });
                                setResultatTarget(record);
                              }}
                            >
                              Résultat
                            </Button>
                          ) : null}
                          {allowValidate && record.statut === 'RESULTAT_DISPONIBLE' ? (
                            <Button size="sm" variant="soft" color="success" disabled={saving} onClick={() => handleValidate(record.id)}>
                              Valider
                            </Button>
                          ) : null}
                          {allowCreateDiagnostic ? (
                            <IconButton
                              size="sm"
                              variant="soft"
                              color="primary"
                              title="Poser un diagnostic lié"
                              onClick={() => setDiagnosticTarget(record)}
                            >
                              <HeartPulse size={16} />
                            </IconButton>
                          ) : null}
                          {allowCancel && record.canCancel ? (
                            <IconButton size="sm" variant="soft" color="danger" onClick={() => setConfirmCancelId(record.id)}>
                              <XCircle size={16} />
                            </IconButton>
                          ) : null}
                        </Stack>
                      </td>
                    ) : null}
                  </tr>
                ))}
              </tbody>
            </Table>
          </Sheet>
        )}
      </Card>

      {showCreate ? (
        <Modal open={openCreate} onClose={() => !saving && setOpenCreate(false)}>
          <ModalDialog sx={{ borderRadius: 'lg', maxWidth: 520, width: '100%' }}>
            <ModalClose />
            <Typography level="title-lg" sx={{ fontWeight: 700, mb: 1 }}>Demander un examen</Typography>
            <Divider sx={{ mb: 2 }} />
            <Stack spacing={2}>
              <FormControl required>
                <FormLabel>Examen</FormLabel>
                <Input
                  placeholder="Rechercher par code ou libellé…"
                  value={examenQuery}
                  onChange={(event) => {
                    setExamenQuery(event.target.value);
                    setSelectedExamen(null);
                  }}
                  endDecorator={examenLoading ? <CircularProgress size="sm" /> : <Search size={16} />}
                />
                {selectedExamen ? (
                  <Chip
                    size="sm"
                    variant="soft"
                    color="primary"
                    sx={{ mt: 1, alignSelf: 'flex-start' }}
                    endDecorator={(
                      <IconButton size="sm" variant="plain" onClick={() => { setSelectedExamen(null); setExamenQuery(''); }}>
                        ×
                      </IconButton>
                    )}
                  >
                    {selectedExamen.code} — {selectedExamen.libelle}
                    {selectedExamen.typeExamen?.libelle ? ` (${selectedExamen.typeExamen.libelle})` : ''}
                  </Chip>
                ) : null}
                {examenResults.length > 0 && !selectedExamen ? (
                  <Sheet variant="outlined" sx={{ borderRadius: 'md', mt: 1, maxHeight: 180, overflow: 'auto' }}>
                    {examenResults.map((examen) => (
                      <Box
                        key={examen.id}
                        onClick={() => {
                          setSelectedExamen(examen);
                          setExamenQuery(examen.code);
                          setExamenResults([]);
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
                          <strong>{examen.code}</strong> — {examen.libelle}
                          {examen.typeExamen?.libelle ? ` · ${examen.typeExamen.libelle}` : ''}
                        </Typography>
                      </Box>
                    ))}
                  </Sheet>
                ) : null}
              </FormControl>
              <FormControl>
                <FormLabel>Note du médecin (optionnel)</FormLabel>
                <Textarea minRows={2} value={noteMedecin} onChange={(event) => setNoteMedecin(event.target.value)} placeholder="Indications cliniques…" />
              </FormControl>
            </Stack>
            <Stack direction="row" spacing={1.5} justifyContent="flex-end" sx={{ mt: 3 }}>
              <Button variant="soft" color="neutral" onClick={() => setOpenCreate(false)} disabled={saving}>Annuler</Button>
              <Button loading={saving} onClick={handleCreate}>Demander</Button>
            </Stack>
          </ModalDialog>
        </Modal>
      ) : null}

      <Modal open={resultatTarget != null} onClose={() => !saving && setResultatTarget(null)}>
        <ModalDialog sx={{ borderRadius: 'lg', maxWidth: 520, width: '100%' }}>
          <ModalClose />
          <Typography level="title-lg" sx={{ fontWeight: 700, mb: 1 }}>Saisir le résultat</Typography>
          <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600], mb: 2 }}>
            {examenLabel(resultatTarget)}
          </Typography>
          <Stack spacing={2}>
            <FormControl required>
              <FormLabel>Résultat ({resultatForm.resultat.length}/{RESULTAT_MAX_LENGTH})</FormLabel>
              <Textarea
                minRows={4}
                value={resultatForm.resultat}
                onChange={(event) => setResultatForm((current) => ({ ...current, resultat: event.target.value.slice(0, RESULTAT_MAX_LENGTH) }))}
              />
            </FormControl>
            <FormControl>
              <FormLabel>Référence fichier (optionnel)</FormLabel>
              <Input
                value={resultatForm.fichier}
                onChange={(event) => setResultatForm((current) => ({ ...current, fichier: event.target.value }))}
                placeholder="Nom ou chemin du compte-rendu…"
              />
            </FormControl>
          </Stack>
          <Stack direction="row" spacing={1.5} justifyContent="flex-end" sx={{ mt: 3 }}>
            <Button variant="soft" color="neutral" onClick={() => setResultatTarget(null)} disabled={saving}>Annuler</Button>
            <Button loading={saving} onClick={handleResultat}>Enregistrer</Button>
          </Stack>
        </ModalDialog>
      </Modal>

      <Modal open={confirmCancelId != null} onClose={() => !saving && setConfirmCancelId(null)}>
        <ModalDialog variant="outlined" role="alertdialog" sx={{ borderRadius: 'lg', maxWidth: 400 }}>
          <Typography level="title-lg" sx={{ fontWeight: 700 }}>Annuler cette demande ?</Typography>
          <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600], mt: 1 }}>
            La demande passera au statut Annulée. Cette action est irréversible.
          </Typography>
          <Stack direction="row" spacing={1.5} justifyContent="flex-end" sx={{ mt: 2 }}>
            <Button variant="soft" color="neutral" onClick={() => setConfirmCancelId(null)} disabled={saving}>Retour</Button>
            <Button color="danger" loading={saving} onClick={() => handleCancel(confirmCancelId)}>Annuler la demande</Button>
          </Stack>
        </ModalDialog>
      </Modal>

      <LinkedDiagnosticModal
        open={diagnosticTarget != null}
        saving={saving}
        examenLabel={examenLabel(diagnosticTarget)}
        onClose={() => setDiagnosticTarget(null)}
        onSubmit={handleLinkedDiagnostic}
      />
    </Stack>
  );
}
