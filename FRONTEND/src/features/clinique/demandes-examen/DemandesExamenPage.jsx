import { useCallback, useEffect, useState } from 'react';
import { Link as RouterLink } from 'react-router-dom';
import {
  Box, Button, Card, Chip, FormControl, FormLabel, Input, Link, Modal, ModalClose, ModalDialog,
  Option, Select, Sheet, Stack, Table, Textarea, Typography,
} from '@mui/joy';
import { FlaskConical, HeartPulse, Search, XCircle } from 'lucide-react';
import AppPagination from '../../../components/ui/AppPagination.jsx';
import { referentiel } from '../../../api/endpoints.js';
import { ROUTES } from '../../../constants/routes.js';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { createReferentielApi } from '../../referentiel/shared/referentielApi.js';
import LinkedDiagnosticModal from './components/LinkedDiagnosticModal.jsx';
import LinkedDiagnosticsCell from './components/LinkedDiagnosticsCell.jsx';
import {
  DEFAULT_DEMANDE_PAGE_SIZE,
  DEMANDE_EXAMEN_STATUT_COLORS,
  DEMANDE_EXAMEN_STATUT_LABELS,
  DEMANDE_PAGE_SIZE_OPTIONS,
  RESULTAT_MAX_LENGTH,
} from './demandeExamenConstants.js';
import {
  annulerDemandeApi,
  createLinkedDiagnosticApi,
  fetchDemandesExamenApi,
  prendreEnChargeDemandeApi,
  saisirResultatDemandeApi,
  validerDemandeApi,
} from './demandesExamenApi.js';

const typesExamenApi = createReferentielApi(referentiel.typesExamen);
const EMPTY_PAGINATION = { page: 1, limit: DEFAULT_DEMANDE_PAGE_SIZE, total: 0, totalPages: 0 };

function formatDateTime(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('fr-FR');
}

export default function DemandesExamenPage() {
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const canCancel = hasPermission(PERMISSIONS.CLINIQUE.DEMANDE_EXAMEN_CANCEL);
  const canSaisie = hasPermission(PERMISSIONS.CLINIQUE.DEMANDE_EXAMEN_SAISIE_RESULTAT);
  const canValidate = hasPermission(PERMISSIONS.CLINIQUE.DEMANDE_EXAMEN_VALIDATE);
  const canCreateDiagnostic = hasPermission(PERMISSIONS.CLINIQUE.DIAGNOSTIC_CREATE);

  const [items, setItems] = useState([]);
  const [types, setTypes] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [statutFilter, setStatutFilter] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_DEMANDE_PAGE_SIZE);
  const [saving, setSaving] = useState(false);
  const [confirmCancelId, setConfirmCancelId] = useState(null);
  const [resultatTarget, setResultatTarget] = useState(null);
  const [resultatForm, setResultatForm] = useState({ resultat: '', fichier: '' });
  const [diagnosticTarget, setDiagnosticTarget] = useState(null);

  useEffect(() => {
    typesExamenApi.fetchLookup().then((list) => setTypes(Array.isArray(list) ? list : [])).catch(() => setTypes([]));
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => { setPage(1); }, [debouncedSearch, statutFilter, typeFilter, limit]);

  const load = useCallback(async (targetPage = page) => {
    setLoading(true);
    setListError('');
    try {
      const result = await fetchDemandesExamenApi({
        page: targetPage,
        limit,
        search: debouncedSearch || undefined,
        statut: statutFilter || undefined,
        typeExamenId: typeFilter || undefined,
      });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (err) {
      setListError(err.message || 'Impossible de charger les demandes.');
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, [debouncedSearch, limit, page, statutFilter, typeFilter]);

  useEffect(() => { load(page); }, [load, page]);

  const runAction = async (action, message) => {
    setSaving(true);
    try {
      await action();
      await load(page);
      showSuccess(message);
    } catch (err) {
      showError(err.message || 'Action impossible.');
    } finally {
      setSaving(false);
    }
  };

  const handleResultat = async () => {
    if (!resultatForm.resultat.trim()) {
      showError('Saisissez le résultat.');
      return;
    }
    setSaving(true);
    try {
      await saisirResultatDemandeApi(resultatTarget.id, {
        resultat: resultatForm.resultat.trim(),
        fichier: resultatForm.fichier.trim() || null,
      });
      setResultatTarget(null);
      await load(page);
      showSuccess('Résultat enregistré.');
    } catch (err) {
      showError(err.message || 'Saisie impossible.');
    } finally {
      setSaving(false);
    }
  };

  const handleLinkedDiagnostic = async (payload) => {
    if (!canCreateDiagnostic) {
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
      await load(page);
      showSuccess('Diagnostic lié enregistré.');
    } catch (err) {
      showError(err.message || 'Ajout du diagnostic impossible.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Stack spacing={3}>
      <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" spacing={2}>
        <Box>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <FlaskConical size={26} color={LOTRU_PRIMARY[600]} />
            <Typography level="h2" sx={{ fontWeight: 700 }}>Demandes d&apos;examens</Typography>
          </Stack>
          <Typography level="body-md" sx={{ color: 'neutral.500', mt: 0.5 }}>
            File globale : prescription, prise en charge, résultats et validation.
          </Typography>
        </Box>
      </Stack>

      <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
        <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} alignItems={{ md: 'flex-end' }}>
          <FormControl sx={{ flex: 1, minWidth: 220 }}>
            <FormLabel>Recherche</FormLabel>
            <Input
              placeholder="Patient, dossier, examen…"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              startDecorator={<Search size={16} />}
            />
          </FormControl>
          <FormControl sx={{ minWidth: 180 }}>
            <FormLabel>Statut</FormLabel>
            <Select value={statutFilter} onChange={(_, value) => setStatutFilter(value ?? '')}>
              <Option value="">Tous</Option>
              {Object.entries(DEMANDE_EXAMEN_STATUT_LABELS).map(([value, label]) => (
                <Option key={value} value={value}>{label}</Option>
              ))}
            </Select>
          </FormControl>
          <FormControl sx={{ minWidth: 180 }}>
            <FormLabel>Type d&apos;examen</FormLabel>
            <Select value={typeFilter} onChange={(_, value) => setTypeFilter(value ?? '')}>
              <Option value="">Tous</Option>
              {types.map((type) => (
                <Option key={type.id} value={String(type.id)}>{type.libelle}</Option>
              ))}
            </Select>
          </FormControl>
        </Stack>
      </Card>

      {listError ? (
        <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
          {listError}
        </Typography>
      ) : null}

      <Card variant="outlined" sx={{ borderRadius: 'lg', p: 0 }}>
        <Sheet variant="outlined" sx={{ border: 'none', overflow: 'auto' }}>
          <Table stickyHeader hoverRow sx={{ minWidth: 980 }}>
            <thead>
              <tr>
                <th>Patient</th>
                <th>Examen</th>
                <th>Statut</th>
                <th>Diagnostic lié</th>
                <th>Prescripteur</th>
                <th>Date</th>
                <th>Résultat</th>
                <th style={{ textAlign: 'right' }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={8}><Typography level="body-sm" sx={{ p: 2 }}>Chargement…</Typography></td></tr>
              ) : items.length === 0 ? (
                <tr><td colSpan={8}><Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600], p: 2 }}>Aucune demande.</Typography></td></tr>
              ) : items.map((record) => (
                <tr key={record.id}>
                  <td>
                    <Typography level="body-sm" sx={{ fontWeight: 600 }}>{record.patient?.fullName ?? '—'}</Typography>
                    <Typography level="body-xs" sx={{ fontFamily: 'monospace' }}>{record.patient?.numDossier ?? ''}</Typography>
                    {record.consultation?.id ? (
                      <Link
                        component={RouterLink}
                        to={ROUTES.CLINIQUE.CONSULTATIONS_DETAIL.replace(':id', record.consultation.id)}
                        level="body-xs"
                      >
                        Consultation #{record.consultation.id}
                      </Link>
                    ) : null}
                  </td>
                  <td>
                    <Stack spacing={0.25}>
                      <Typography level="body-sm">{record.examen?.libelle ?? '—'}</Typography>
                      {record.examen?.typeExamen?.libelle ? (
                        <Typography level="body-xs" sx={{ color: LOTRU_NEUTRAL[600] }}>
                          {record.examen.typeExamen.libelle}
                        </Typography>
                      ) : null}
                    </Stack>
                  </td>
                  <td>
                    <Chip size="sm" variant="soft" color={DEMANDE_EXAMEN_STATUT_COLORS[record.statut] ?? 'neutral'}>
                      {DEMANDE_EXAMEN_STATUT_LABELS[record.statut] ?? record.statut}
                    </Chip>
                  </td>
                  <td>
                    <LinkedDiagnosticsCell diagnostics={record.diagnostics} />
                  </td>
                  <td>{record.prescripteur?.fullName ?? '—'}</td>
                  <td>{formatDateTime(record.demandeAt)}</td>
                  <td>
                    <Typography level="body-xs" sx={{ maxWidth: 220, whiteSpace: 'pre-wrap' }}>
                      {record.resultat ?? '—'}
                    </Typography>
                  </td>
                  <td style={{ textAlign: 'right' }}>
                    <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                      {canSaisie && record.statut === 'DEMANDE' ? (
                        <Button size="sm" variant="soft" disabled={saving} onClick={() => runAction(() => prendreEnChargeDemandeApi(record.id), 'Demande prise en charge.')}>
                          Prendre en charge
                        </Button>
                      ) : null}
                      {canSaisie && record.statut === 'EN_COURS' ? (
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
                      {canValidate && record.statut === 'RESULTAT_DISPONIBLE' ? (
                        <Button size="sm" variant="soft" color="success" disabled={saving} onClick={() => runAction(() => validerDemandeApi(record.id), 'Résultat validé.')}>
                          Valider
                        </Button>
                      ) : null}
                      {canCreateDiagnostic && record.consultation?.isEditable !== false ? (
                        <Button size="sm" variant="plain" startDecorator={<HeartPulse size={14} />} onClick={() => setDiagnosticTarget(record)}>
                          Diagnostic
                        </Button>
                      ) : null}
                      {canCancel && record.canCancel && record.consultation?.isEditable !== false ? (
                        <Button size="sm" variant="soft" color="danger" startDecorator={<XCircle size={14} />} onClick={() => setConfirmCancelId(record.id)}>
                          Annuler
                        </Button>
                      ) : null}
                    </Stack>
                  </td>
                </tr>
              ))}
            </tbody>
          </Table>
        </Sheet>
        <Box sx={{ p: 2 }}>
          <AppPagination
            page={page}
            limit={limit}
            total={pagination.total}
            totalPages={pagination.totalPages}
            limitOptions={DEMANDE_PAGE_SIZE_OPTIONS}
            onPageChange={setPage}
            onLimitChange={setLimit}
          />
        </Box>
      </Card>

      <Modal open={resultatTarget != null} onClose={() => !saving && setResultatTarget(null)}>
        <ModalDialog sx={{ borderRadius: 'lg', maxWidth: 520, width: '100%' }}>
          <ModalClose />
          <Typography level="title-lg" sx={{ fontWeight: 700 }}>Saisir le résultat</Typography>
          <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600], mb: 2 }}>
            {resultatTarget?.examen?.code} — {resultatTarget?.examen?.libelle}
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
            La demande passera au statut Annulée.
          </Typography>
          <Stack direction="row" spacing={1.5} justifyContent="flex-end" sx={{ mt: 2 }}>
            <Button variant="soft" color="neutral" onClick={() => setConfirmCancelId(null)} disabled={saving}>Retour</Button>
            <Button
              color="danger"
              loading={saving}
              onClick={() => runAction(async () => {
                await annulerDemandeApi(confirmCancelId);
                setConfirmCancelId(null);
              }, 'Demande annulée.')}
            >
              Annuler la demande
            </Button>
          </Stack>
        </ModalDialog>
      </Modal>

      <LinkedDiagnosticModal
        open={diagnosticTarget != null}
        saving={saving}
        examenLabel={diagnosticTarget ? `${diagnosticTarget.examen?.code} — ${diagnosticTarget.examen?.libelle}` : ''}
        onClose={() => setDiagnosticTarget(null)}
        onSubmit={handleLinkedDiagnostic}
      />
    </Stack>
  );
}
