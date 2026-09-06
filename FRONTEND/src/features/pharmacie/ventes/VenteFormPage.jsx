import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  Box, Button, Card, Chip, FormControl, FormLabel, IconButton, Input, Modal, ModalDialog,
  Option, Select, Stack, Typography,
} from '@mui/joy';
import { ArrowLeft, Ban, Check, Plus, Printer, Search, ShoppingCart, Trash2 } from 'lucide-react';
import ConfirmModal from '../../../components/ui/ConfirmModal.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { ROUTES } from '../../../constants/routes.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { fetchPatientsApi } from '../../patient/patients/patientsApi.js';
import { fetchLotsVendablesApi } from '../lots/lotsApi.js';
import { fetchMedicamentsActifsApi } from '../medicaments/medicamentsApi.js';
import MedicamentAutocomplete from '../shared/MedicamentAutocomplete.jsx';
import { entityId, formatDate, formatPatientName, formatPrix, isSameCalendarDay } from '../shared/format.js';
import { printVenteTicket } from './printVenteTicket.js';
import {
  EMPTY_VENTE_LIGNE,
  VENTE_CLIENT_TYPES,
  VENTE_MODES_PAIEMENT,
  VENTE_STATUT_COLORS,
  VENTE_STATUT_LABELS,
  emptyVenteForm,
} from './venteConstants.js';
import OfflineHint from '../../../offline/OfflineHint.jsx';
import { useOffline } from '../../../offline/useOffline.js';
import {
  annulerVenteApi,
  canUseOfflineCaisse,
  completeVenteOfflineApi,
  createVenteApi,
  deleteVenteApi,
  fetchVenteApi,
  updateVenteApi,
  validerVenteApi,
  fetchVisitesHospitaliseesApi,
} from './ventesApi.js';

function toPayload(form) {
  const hospitalise = form.clientType === 'HOSPITALISE';
  return {
    clientType: hospitalise ? 'PATIENT' : form.clientType,
    patientId: form.clientType === 'PATIENT' ? form.patientId || null : null,
    clientNom: form.clientType === 'PASSANT' ? form.clientNom.trim() : null,
    visiteId: hospitalise && form.visiteId ? Number(form.visiteId) : null,
    modePaiement: form.modePaiement,
    lignes: form.lignes.map((ligne) => ({
      medicamentId: Number(ligne.medicamentId),
      lotId: ligne.lotId ? Number(ligne.lotId) : null,
      quantite: Number(ligne.quantite),
    })),
  };
}

export default function VenteFormPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const { serverReachable, online } = useOffline();
  const offlineCaisse = !online || !serverReachable || canUseOfflineCaisse();
  const isNew = !id;
  const canCreate = hasPermission(PERMISSIONS.PHARMACIE.VENTE_CREATE);
  const canUpdate = hasPermission(PERMISSIONS.PHARMACIE.VENTE_UPDATE);
  const canDelete = hasPermission(PERMISSIONS.PHARMACIE.VENTE_DELETE);
  const canValider = hasPermission(PERMISSIONS.PHARMACIE.VENTE_VALIDER);
  const canAnnulerJ = hasPermission(PERMISSIONS.PHARMACIE.VENTE_ANNULER);
  const canAnnulerHorsJ = hasPermission(PERMISSIONS.PHARMACIE.VENTE_ANNULER_HORS_DELAI);

  const [form, setForm] = useState(emptyVenteForm);
  const [vente, setVente] = useState(null);
  const [medicaments, setMedicaments] = useState([]);
  const [lotsByMedicament, setLotsByMedicament] = useState({});
  const [patientQuery, setPatientQuery] = useState('');
  const [patientResults, setPatientResults] = useState([]);
  const [selectedPatient, setSelectedPatient] = useState(null);
  const [visiteQuery, setVisiteQuery] = useState('');
  const [visiteResults, setVisiteResults] = useState([]);
  const [selectedVisite, setSelectedVisite] = useState(null);
  const [loading, setLoading] = useState(!isNew);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [annulerOpen, setAnnulerOpen] = useState(false);
  const [motif, setMotif] = useState('');
  const [confirmAction, setConfirmAction] = useState(null);
  const [confirmLoading, setConfirmLoading] = useState(false);

  const readOnly = vente?.statut === 'VALIDEE' || vente?.statut === 'ANNULEE';
  const canSave = isNew ? canCreate : canUpdate && !readOnly;
  const sameDay = isSameCalendarDay(vente?.dateVente || vente?.createdAt);
  const canAnnuler = vente?.statut === 'VALIDEE' && (sameDay ? canAnnulerJ : canAnnulerHorsJ);

  const medicamentMap = useMemo(
    () => Object.fromEntries(medicaments.map((item) => [String(item.id), item])),
    [medicaments],
  );

  const estimatedTotal = form.lignes.reduce((sum, ligne) => {
    const medicament = medicamentMap[String(ligne.medicamentId)];
    const prix = Number(medicament?.prixVente ?? 0);
    const qty = Number(ligne.quantite) || 0;
    return sum + prix * qty;
  }, 0);

  useEffect(() => {
    let cancelled = false;
    fetchMedicamentsActifsApi()
      .then((list) => { if (!cancelled) setMedicaments(list); })
      .catch((err) => { if (!cancelled) setError(err.message || 'Catalogue indisponible.'); });
    return () => { cancelled = true; };
  }, []);

  useEffect(() => {
    if (isNew) return undefined;
    let cancelled = false;
    (async () => {
      setLoading(true);
      setError('');
      try {
        const data = await fetchVenteApi(id);
        if (cancelled) return;
        setVente(data);
        setSelectedPatient(data.patient ?? null);
        setSelectedVisite(data.visite ?? null);
        setMedicaments((current) => {
          const extras = (data.lignes ?? []).map((ligne) => ligne.medicament).filter(Boolean);
          const map = new Map(current.map((item) => [String(item.id), item]));
          extras.forEach((item) => {
            if (item?.id && !map.has(String(item.id))) map.set(String(item.id), item);
          });
          return [...map.values()];
        });
        setForm({
          clientType: data.origine === 'HOSPITALISE' ? 'HOSPITALISE' : (data.clientType ?? 'PASSANT'),
          patientId: entityId(data.patientId),
          clientNom: data.clientNom ?? '',
          visiteId: data.visiteId ? String(data.visiteId) : '',
          modePaiement: data.modePaiement ?? 'ESPECES',
          lignes: (data.lignes ?? []).length
            ? data.lignes.map((ligne) => ({
              medicamentId: ligne.medicamentId ? String(ligne.medicamentId) : '',
              lotId: ligne.lotId ? String(ligne.lotId) : '',
              quantite: ligne.quantite ?? 1,
            }))
            : [{ ...EMPTY_VENTE_LIGNE }],
        });
      } catch (err) {
        if (!cancelled) setError(err.message || 'Vente introuvable.');
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true; };
  }, [id, isNew]);

  useEffect(() => {
    const ids = [...new Set(form.lignes.map((ligne) => ligne.medicamentId).filter(Boolean))];
    ids.forEach((medicamentId) => {
      if (lotsByMedicament[medicamentId]) return;
      fetchLotsVendablesApi(medicamentId)
        .then((lots) => setLotsByMedicament((current) => ({ ...current, [medicamentId]: lots })))
        .catch(() => setLotsByMedicament((current) => ({ ...current, [medicamentId]: [] })));
    });
  }, [form.lignes, lotsByMedicament]);

  useEffect(() => {
    if (form.clientType !== 'HOSPITALISE') return undefined;
    const timer = window.setTimeout(async () => {
      const q = visiteQuery.trim();
      if (q.length < 2) {
        setVisiteResults([]);
        return;
      }
      try {
        setVisiteResults(await fetchVisitesHospitaliseesApi(q));
      } catch {
        setVisiteResults([]);
      }
    }, 300);
    return () => window.clearTimeout(timer);
  }, [visiteQuery, form.clientType]);

  useEffect(() => {
    if (form.clientType !== 'PATIENT') return undefined;
    const timer = window.setTimeout(async () => {
      const q = patientQuery.trim();
      if (q.length < 2) {
        setPatientResults([]);
        return;
      }
      try {
        const result = await fetchPatientsApi({ page: 1, limit: 8, search: q });
        setPatientResults(result.items);
      } catch {
        setPatientResults([]);
      }
    }, 300);
    return () => window.clearTimeout(timer);
  }, [patientQuery, form.clientType]);

  const setField = (field, value) => setForm((current) => ({ ...current, [field]: value }));

  const setLigne = (index, field, value) => {
    setForm((current) => ({
      ...current,
      lignes: current.lignes.map((ligne, i) => {
        if (i !== index) return ligne;
        const next = { ...ligne, [field]: value };
        if (field === 'medicamentId') next.lotId = '';
        return next;
      }),
    }));
  };

  const addLigne = () => setForm((current) => ({ ...current, lignes: [...current.lignes, { ...EMPTY_VENTE_LIGNE }] }));

  const removeLigne = (index) => {
    setForm((current) => ({
      ...current,
      lignes: current.lignes.length === 1 ? current.lignes : current.lignes.filter((_, i) => i !== index),
    }));
  };

  const handleSave = async () => {
    if (form.clientType === 'HOSPITALISE' && !form.visiteId) {
      setError('Sélectionnez une visite hospitalisée.');
      return;
    }
    setSaving(true);
    setError('');
    try {
      const payload = toPayload(form);
      if (isNew) {
        const created = await createVenteApi(payload);
        showSuccess('Brouillon de vente enregistré.');
        navigate(ROUTES.PHARMACIE.VENTE_DETAIL.replace(':id', String(created.id)), { replace: true });
      } else {
        const updated = await updateVenteApi(id, payload);
        setVente(updated);
        showSuccess('Vente mise à jour.');
      }
    } catch (err) {
      setError(err.message || 'Enregistrement impossible.');
    } finally {
      setSaving(false);
    }
  };

  const handleValider = async () => {
    setConfirmLoading(true);
    setSaving(true);
    setError('');
    try {
      if (isNew && offlineCaisse) {
        const validated = await completeVenteOfflineApi(toPayload(form));
        setVente(validated);
        setConfirmAction(null);
        showSuccess('Vente encaissée hors-ligne. Elle sera synchronisée au retour du serveur.');
        printVenteTicket(validated);
        return;
      }
      if (canSave) {
        await updateVenteApi(id, toPayload(form));
      }
      const validated = await validerVenteApi(id);
      setVente(validated);
      setConfirmAction(null);
      showSuccess('Vente validée, stock décrémenté.');
      printVenteTicket(validated);
    } catch (err) {
      setError(err.message || 'Validation impossible.');
    } finally {
      setSaving(false);
      setConfirmLoading(false);
    }
  };

  const handleAnnuler = async () => {
    if (!sameDay && !motif.trim()) {
      setError('Le motif est obligatoire pour une annulation hors délai.');
      return;
    }
    setSaving(true);
    setError('');
    try {
      const cancelled = await annulerVenteApi(id, motif.trim());
      setVente(cancelled);
      setAnnulerOpen(false);
      showSuccess('Vente annulée, stock repris.');
    } catch (err) {
      setError(err.message || 'Annulation impossible.');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    setConfirmLoading(true);
    try {
      await deleteVenteApi(id);
      setConfirmAction(null);
      showSuccess('Brouillon supprimé.');
      navigate(ROUTES.PHARMACIE.VENTES);
    } catch (err) {
      showError(err.message || 'Suppression impossible.');
    } finally {
      setConfirmLoading(false);
    }
  };

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={2.5}>
        <OfflineHint>
          Caisse hors-ligne : encaissez un passant ou un patient déjà ouvert. Le serveur rejouera le FEFO à la reconnexion.
        </OfflineHint>
        <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'flex-start' }} spacing={1.5}>
          <Stack spacing={1}>
            <Button variant="plain" color="neutral" startDecorator={<ArrowLeft size={16} />} onClick={() => navigate(ROUTES.PHARMACIE.VENTES)} sx={{ alignSelf: 'flex-start', px: 0 }}>
              Retour aux ventes
            </Button>
            <Stack direction="row" spacing={1.5} alignItems="center">
              <ShoppingCart size={24} color={LOTRU_PRIMARY[600]} />
              <Box>
                <Typography level="h2" sx={{ fontWeight: 700 }}>
                  {isNew ? 'Nouvelle vente' : vente?.numero ?? 'Vente'}
                </Typography>
                <Typography level="body-md" sx={{ color: 'neutral.500' }}>
                  Paiement immédiat. Un lot par ligne ; FEFO si aucun lot n’est choisi.
                </Typography>
              </Box>
              {vente?.statut ? (
                <Chip size="sm" variant="soft" color={VENTE_STATUT_COLORS[vente.statut] ?? 'neutral'}>
                  {VENTE_STATUT_LABELS[vente.statut] ?? vente.statut}
                </Chip>
              ) : null}
            </Stack>
          </Stack>
          <Stack direction="row" spacing={1} flexWrap="wrap">
            {canDelete && !isNew && !readOnly ? (
              <Button variant="outlined" color="danger" startDecorator={<Trash2 size={16} />} onClick={() => setConfirmAction('delete')} disabled={saving}>
                Supprimer
              </Button>
            ) : null}
            {canAnnuler ? (
              <Button variant="outlined" color="danger" startDecorator={<Ban size={16} />} onClick={() => { setMotif(''); setAnnulerOpen(true); }} disabled={saving}>
                Annuler la vente
              </Button>
            ) : null}
            {canSave ? (
              <Button onClick={handleSave} loading={saving}>Enregistrer</Button>
            ) : null}
            {vente?.statut && vente.statut !== 'BROUILLON' ? (
              <Button variant="outlined" startDecorator={<Printer size={16} />} onClick={() => printVenteTicket(vente)} disabled={saving}>
                Imprimer le ticket
              </Button>
            ) : null}
            {canValider && !readOnly && (!isNew || offlineCaisse) ? (
              <Button color="success" startDecorator={<Check size={16} />} onClick={() => setConfirmAction('valider')} loading={saving}>
                Encaisser{offlineCaisse && isNew ? ' hors-ligne' : ''}
              </Button>
            ) : null}
          </Stack>
        </Stack>

        {error ? (
          <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
            {error}
          </Typography>
        ) : null}

        {vente?.motifAnnulation ? (
          <Typography level="body-sm" sx={{ bgcolor: 'neutral.50', p: 1.5, borderRadius: 'md' }}>
            Motif d’annulation : {vente.motifAnnulation}
          </Typography>
        ) : null}

        {loading ? (
          <Typography level="body-sm">Chargement…</Typography>
        ) : (
          <>
            <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5 }}>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                <FormControl required sx={{ minWidth: 180 }}>
                  <FormLabel>Client</FormLabel>
                  <Select
                    value={form.clientType}
                    onChange={(_, value) => setField('clientType', value ?? 'PASSANT')}
                    disabled={readOnly || saving}
                  >
                    {VENTE_CLIENT_TYPES.map((item) => (
                      <Option key={item.value} value={item.value}>{item.label}</Option>
                    ))}
                  </Select>
                </FormControl>
                <FormControl required sx={{ minWidth: 180 }}>
                  <FormLabel>Paiement</FormLabel>
                  <Select
                    value={form.modePaiement}
                    onChange={(_, value) => setField('modePaiement', value ?? 'ESPECES')}
                    disabled={readOnly || saving}
                  >
                    {VENTE_MODES_PAIEMENT.map((item) => (
                      <Option key={item.value} value={item.value}>{item.label}</Option>
                    ))}
                  </Select>
                </FormControl>
                {form.clientType === 'PASSANT' ? (
                  <FormControl required sx={{ flex: 1 }}>
                    <FormLabel>Nom du passant</FormLabel>
                    <Input
                      value={form.clientNom}
                      onChange={(e) => setField('clientNom', e.target.value)}
                      disabled={readOnly || saving}
                    />
                  </FormControl>
                ) : form.clientType === 'HOSPITALISE' ? (
                  <FormControl required sx={{ flex: 1 }}>
                    <FormLabel>Visite hospitalisée</FormLabel>
                    {selectedVisite ? (
                      <Stack spacing={0.5}>
                        <Typography level="body-sm" sx={{ fontWeight: 600 }}>
                          {selectedVisite.patientName || formatPatientName(selectedVisite.patient || selectedPatient)}
                        </Typography>
                        <Typography level="body-xs" sx={{ color: 'neutral.500' }}>
                          {selectedVisite.service?.libelle ? `Service ${selectedVisite.service.libelle}` : 'Hospitalisé'}
                          {selectedVisite.numDossier ? ` · dossier ${selectedVisite.numDossier}` : ''}
                        </Typography>
                        {!readOnly ? (
                          <Button size="sm" variant="plain" onClick={() => { setSelectedVisite(null); setField('visiteId', ''); setSelectedPatient(null); }}>
                            Changer
                          </Button>
                        ) : null}
                      </Stack>
                    ) : (
                      <Stack spacing={0.75}>
                        <Input
                          startDecorator={<Search size={16} />}
                          placeholder="Nom du patient ou n° dossier (2 caractères min.)"
                          value={visiteQuery}
                          onChange={(e) => setVisiteQuery(e.target.value)}
                          disabled={readOnly || saving}
                        />
                        {visiteResults.length > 0 ? (
                          <Stack spacing={0.5}>
                            {visiteResults.map((visite) => (
                              <Button
                                key={visite.id}
                                size="sm"
                                variant="soft"
                                color="neutral"
                                onClick={() => {
                                  setSelectedVisite(visite);
                                  setSelectedPatient(visite.patient ?? null);
                                  setField('visiteId', String(visite.id));
                                  setVisiteQuery('');
                                  setVisiteResults([]);
                                }}
                              >
                                {visite.patientName || formatPatientName(visite.patient)} — {visite.service?.libelle || 'Hospitalisé'}
                              </Button>
                            ))}
                          </Stack>
                        ) : null}
                      </Stack>
                    )}
                  </FormControl>
                ) : (
                  <FormControl required sx={{ flex: 1 }}>
                    <FormLabel>Patient</FormLabel>
                    {selectedPatient ? (
                      <Stack direction="row" spacing={1} alignItems="center">
                        <Typography level="body-sm" sx={{ fontWeight: 600 }}>{formatPatientName(selectedPatient)}</Typography>
                        {!readOnly ? (
                          <Button size="sm" variant="plain" onClick={() => { setSelectedPatient(null); setField('patientId', ''); }}>
                            Changer
                          </Button>
                        ) : null}
                      </Stack>
                    ) : (
                      <Stack spacing={0.75}>
                        <Input
                          startDecorator={<Search size={16} />}
                          placeholder="Rechercher un patient (2 caractères min.)"
                          value={patientQuery}
                          onChange={(e) => setPatientQuery(e.target.value)}
                          disabled={readOnly || saving}
                        />
                        {patientResults.length > 0 ? (
                          <Stack spacing={0.5}>
                            {patientResults.map((patient) => (
                              <Button
                                key={entityId(patient.id)}
                                size="sm"
                                variant="soft"
                                color="neutral"
                                onClick={() => {
                                  setSelectedPatient(patient);
                                  setField('patientId', entityId(patient.id));
                                  setPatientQuery('');
                                  setPatientResults([]);
                                }}
                              >
                                {formatPatientName(patient)}
                              </Button>
                            ))}
                          </Stack>
                        ) : null}
                      </Stack>
                    )}
                  </FormControl>
                )}
              </Stack>
            </Card>

            <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5 }}>
              <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 2 }}>
                <Typography level="title-md" sx={{ fontWeight: 700 }}>Lignes</Typography>
                {!readOnly ? (
                  <Button size="sm" variant="outlined" startDecorator={<Plus size={14} />} onClick={addLigne} disabled={saving}>
                    Ajouter une ligne
                  </Button>
                ) : null}
              </Stack>
              <Stack spacing={2}>
                {form.lignes.map((ligne, index) => {
                  const medicament = medicamentMap[String(ligne.medicamentId)];
                  const lots = lotsByMedicament[ligne.medicamentId] ?? [];
                  const lineTotal = (Number(medicament?.prixVente ?? 0) * (Number(ligne.quantite) || 0));
                  return (
                    <Stack key={`vente-ligne-${index}`} direction={{ xs: 'column', lg: 'row' }} spacing={1.5} alignItems={{ lg: 'flex-end' }}>
                      <FormControl required sx={{ flex: 1.8 }}>
                        <FormLabel>Médicament</FormLabel>
                        <MedicamentAutocomplete
                          options={medicaments}
                          valueId={ligne.medicamentId}
                          disabled={readOnly || saving}
                          onSelect={(medicament) => setLigne(index, 'medicamentId', medicament ? String(medicament.id) : '')}
                        />
                      </FormControl>
                      <FormControl sx={{ flex: 1.2 }}>
                        <FormLabel>Lot (FEFO si vide)</FormLabel>
                        <Select
                          value={ligne.lotId || ''}
                          onChange={(_, value) => setLigne(index, 'lotId', value ?? '')}
                          disabled={readOnly || saving || !ligne.medicamentId}
                          placeholder="Automatique (FEFO)"
                        >
                          <Option value="">Automatique (FEFO)</Option>
                          {lots.map((lot) => (
                            <Option key={lot.id} value={String(lot.id)}>
                              {lot.numeroLot} — {formatDate(lot.datePeremption)} ({lot.quantiteRestante})
                            </Option>
                          ))}
                        </Select>
                      </FormControl>
                      <FormControl required sx={{ width: { lg: 110 } }}>
                        <FormLabel>Qté</FormLabel>
                        <Input
                          type="number"
                          value={ligne.quantite}
                          onChange={(e) => setLigne(index, 'quantite', e.target.value)}
                          disabled={readOnly || saving}
                          slotProps={{ input: { min: 1 } }}
                        />
                      </FormControl>
                      <Typography level="body-sm" sx={{ minWidth: 110, pb: 1 }}>{formatPrix(lineTotal)}</Typography>
                      {!readOnly ? (
                        <IconButton variant="plain" color="danger" onClick={() => removeLigne(index)} disabled={saving || form.lignes.length === 1}>
                          <Trash2 size={16} />
                        </IconButton>
                      ) : null}
                    </Stack>
                  );
                })}
              </Stack>
              <Typography level="title-lg" sx={{ mt: 2, textAlign: 'right', fontWeight: 700 }}>
                Total : {formatPrix(vente?.statut === 'VALIDEE' || vente?.statut === 'ANNULEE' ? vente.montantTotal : estimatedTotal)}
              </Typography>
            </Card>
          </>
        )}
      </Stack>

      <ConfirmModal
        open={confirmAction === 'valider'}
        title="Encaisser la vente"
        message={offlineCaisse
          ? 'La vente sera encaissée localement. Le serveur rejouera le FEFO à la reconnexion.'
          : 'Le stock sera décrémenté (FEFO si aucun lot n’est choisi).'}
        confirmLabel="Encaisser"
        color="success"
        loading={confirmLoading}
        onClose={() => setConfirmAction(null)}
        onConfirm={handleValider}
      />
      <ConfirmModal
        open={confirmAction === 'delete'}
        title="Supprimer le brouillon"
        message="Supprimer ce brouillon de vente ?"
        confirmLabel="Supprimer"
        loading={confirmLoading}
        onClose={() => setConfirmAction(null)}
        onConfirm={handleDelete}
      />

      <Modal open={annulerOpen} onClose={() => setAnnulerOpen(false)}>
        <ModalDialog sx={{ maxWidth: 460, width: '100%' }}>
          <Typography level="title-lg" sx={{ fontWeight: 700 }}>Annuler la vente</Typography>
          <Typography level="body-sm" sx={{ color: 'neutral.600' }}>
            {sameDay
              ? 'Annulation le jour même : le stock des lots d’origine sera repris.'
              : 'Annulation hors délai : un motif est obligatoire. Le stock sera repris.'}
          </Typography>
          <FormControl required={!sameDay}>
            <FormLabel>Motif</FormLabel>
            <Input value={motif} onChange={(e) => setMotif(e.target.value)} slotProps={{ input: { maxLength: 255 } }} />
          </FormControl>
          <Stack direction="row" spacing={1} justifyContent="flex-end">
            <Button variant="plain" color="neutral" onClick={() => setAnnulerOpen(false)} disabled={saving}>Fermer</Button>
            <Button color="danger" onClick={handleAnnuler} loading={saving}>Confirmer l’annulation</Button>
          </Stack>
        </ModalDialog>
      </Modal>
    </Box>
  );
}
