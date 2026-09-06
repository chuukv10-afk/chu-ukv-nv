import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  Box, Button, Card, Chip, FormControl, FormLabel, IconButton, Input, Modal, ModalDialog, Option, Select, Stack, Typography,
} from '@mui/joy';
import { ArrowLeft, Ban, Check, ClipboardList, Plus, Search, Send, Trash2 } from 'lucide-react';
import ConfirmModal from '../../../components/ui/ConfirmModal.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { ROUTES } from '../../../constants/routes.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import OfflineHint from '../../../offline/OfflineHint.jsx';
import { LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { fetchMedicamentsActifsApi } from '../medicaments/medicamentsApi.js';
import MedicamentAutocomplete from '../shared/MedicamentAutocomplete.jsx';
import { formatPatientName, formatPrix } from '../shared/format.js';
import { fetchVisitesHospitaliseesApi } from '../ventes/ventesApi.js';
import {
  DEMANDE_STATUT_COLORS,
  DEMANDE_STATUT_LABELS,
  EMPTY_DEMANDE_LIGNE,
  PAIEMENT_STATUT_COLORS,
  PAIEMENT_STATUT_LABELS,
  emptyDemandeForm,
} from './demandeConstants.js';
import {
  createDemandeServiceApi,
  deleteDemandeServiceApi,
  delivrerDemandeServiceApi,
  envoyerDemandeServiceApi,
  fetchDemandeServiceApi,
  fetchServicesActifsPharmacieApi,
  refuserDemandeServiceApi,
  reglerDemandeServiceApi,
  updateDemandeServiceApi,
} from './demandesServiceApi.js';

function toPayload(form) {
  return {
    serviceId: Number(form.serviceId),
    visiteId: form.visiteId ? Number(form.visiteId) : null,
    motif: form.motif.trim() || null,
    lignes: form.lignes.map((ligne) => ({
      medicamentId: Number(ligne.medicamentId),
      quantite: Number(ligne.quantite),
    })),
  };
}

export default function DemandeServiceFormPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const isNew = !id;
  const canCreate = hasPermission(PERMISSIONS.PHARMACIE.DEMANDE_SERVICE_CREATE);
  const canUpdate = hasPermission(PERMISSIONS.PHARMACIE.DEMANDE_SERVICE_UPDATE);
  const canDelete = hasPermission(PERMISSIONS.PHARMACIE.DEMANDE_SERVICE_DELETE);
  const canEnvoyer = hasPermission(PERMISSIONS.PHARMACIE.DEMANDE_SERVICE_ENVOYER);
  const canDelivrer = hasPermission(PERMISSIONS.PHARMACIE.DEMANDE_SERVICE_DELIVRER);
  const canRefuser = hasPermission(PERMISSIONS.PHARMACIE.DEMANDE_SERVICE_REFUSER);
  const canRegler = hasPermission(PERMISSIONS.PHARMACIE.DEMANDE_SERVICE_REGLER);

  const [form, setForm] = useState(emptyDemandeForm);
  const [demande, setDemande] = useState(null);
  const [services, setServices] = useState([]);
  const [medicaments, setMedicaments] = useState([]);
  const [loading, setLoading] = useState(!isNew);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [confirmAction, setConfirmAction] = useState(null);
  const [confirmLoading, setConfirmLoading] = useState(false);
  const [refusOpen, setRefusOpen] = useState(false);
  const [motifRefus, setMotifRefus] = useState('');
  const [reglerOpen, setReglerOpen] = useState(false);
  const [modePaiement, setModePaiement] = useState('ESPECES');
  const [visiteQuery, setVisiteQuery] = useState('');
  const [visiteResults, setVisiteResults] = useState([]);
  const [selectedVisite, setSelectedVisite] = useState(null);

  const readOnly = demande && demande.statut !== 'BROUILLON';
  const canSave = isNew ? canCreate : canUpdate && !readOnly;

  useEffect(() => {
    Promise.all([fetchServicesActifsPharmacieApi(), fetchMedicamentsActifsApi()])
      .then(([serviceList, medicamentList]) => {
        setServices(serviceList);
        setMedicaments(medicamentList);
      })
      .catch((err) => setError(err.message || 'Référentiels indisponibles.'));
  }, []);

  useEffect(() => {
    if (isNew) return undefined;
    let cancelled = false;
    (async () => {
      setLoading(true);
      try {
        const data = await fetchDemandeServiceApi(id);
        if (cancelled) return;
        setDemande(data);
        setSelectedVisite(data.visite ?? null);
        setForm({
          serviceId: data.serviceId ? String(data.serviceId) : '',
          visiteId: data.visiteId ? String(data.visiteId) : '',
          motif: data.motif ?? '',
          lignes: (data.lignes ?? []).length
            ? data.lignes.map((ligne) => ({
              medicamentId: ligne.medicamentId ? String(ligne.medicamentId) : '',
              quantite: ligne.quantite ?? 1,
            }))
            : [{ ...EMPTY_DEMANDE_LIGNE }],
        });
      } catch (err) {
        if (!cancelled) setError(err.message || 'Demande introuvable.');
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true; };
  }, [id, isNew]);

  useEffect(() => {
    if (readOnly || !form.serviceId) {
      setVisiteResults([]);
      return undefined;
    }
    const timer = window.setTimeout(async () => {
      const q = visiteQuery.trim();
      if (q.length < 2) {
        setVisiteResults([]);
        return;
      }
      try {
        setVisiteResults(await fetchVisitesHospitaliseesApi(q, form.serviceId));
      } catch {
        setVisiteResults([]);
      }
    }, 300);
    return () => window.clearTimeout(timer);
  }, [visiteQuery, form.serviceId, readOnly]);

  const setField = (field, value) => setForm((current) => ({ ...current, [field]: value }));
  const setLigne = (index, field, value) => setForm((current) => ({
    ...current,
    lignes: current.lignes.map((ligne, i) => (i === index ? { ...ligne, [field]: value } : ligne)),
  }));

  const runAction = async (action, successMessage) => {
    setConfirmLoading(true);
    setError('');
    try {
      const updated = await action();
      setDemande(updated);
      setConfirmAction(null);
      showSuccess(successMessage);
    } catch (err) {
      setError(err.message || 'Action impossible.');
    } finally {
      setConfirmLoading(false);
    }
  };

  const handleSave = async () => {
    if (!form.serviceId) {
      setError('Le service est obligatoire.');
      return;
    }
    if (form.lignes.some((ligne) => !ligne.medicamentId || Number(ligne.quantite) < 1)) {
      setError('Chaque ligne doit avoir un médicament et une quantité.');
      return;
    }
    setSaving(true);
    setError('');
    try {
      if (isNew) {
        const created = await createDemandeServiceApi(toPayload(form));
        showSuccess('Brouillon enregistré.');
        navigate(ROUTES.PHARMACIE.DEMANDE_SERVICE_DETAIL.replace(':id', String(created.id)), { replace: true });
      } else {
        setDemande(await updateDemandeServiceApi(id, toPayload(form)));
        showSuccess('Demande mise à jour.');
      }
    } catch (err) {
      setError(err.message || 'Enregistrement impossible.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={2.5}>
        <OfflineHint>
          Brouillon de demande hors-ligne : il sera créé sur le serveur à la reconnexion. Envoi et délivrance restent en ligne.
        </OfflineHint>
        <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" spacing={1.5}>
          <Stack spacing={1}>
            <Button variant="plain" color="neutral" startDecorator={<ArrowLeft size={16} />} onClick={() => navigate(ROUTES.PHARMACIE.DEMANDES_SERVICE)} sx={{ alignSelf: 'flex-start', px: 0 }}>
              Retour aux demandes
            </Button>
            <Stack direction="row" spacing={1.5} alignItems="center">
              <ClipboardList size={24} color={LOTRU_PRIMARY[600]} />
              <Typography level="h2" sx={{ fontWeight: 700 }}>{isNew ? 'Nouvelle demande' : demande?.numero ?? 'Demande'}</Typography>
              {demande?.statut ? <Chip size="sm" variant="soft" color={DEMANDE_STATUT_COLORS[demande.statut]}>{DEMANDE_STATUT_LABELS[demande.statut]}</Chip> : null}
              {demande?.statutPaiement ? <Chip size="sm" variant="soft" color={PAIEMENT_STATUT_COLORS[demande.statutPaiement]}>{PAIEMENT_STATUT_LABELS[demande.statutPaiement]}</Chip> : null}
            </Stack>
          </Stack>
          <Stack direction="row" spacing={1} flexWrap="wrap">
            {canDelete && !isNew && !readOnly ? <Button variant="outlined" color="danger" startDecorator={<Trash2 size={16} />} onClick={() => setConfirmAction('delete')}>Supprimer</Button> : null}
            {canSave ? <Button onClick={handleSave} loading={saving}>Enregistrer</Button> : null}
            {canEnvoyer && demande?.statut === 'BROUILLON' ? <Button startDecorator={<Send size={16} />} onClick={() => setConfirmAction('envoyer')}>Envoyer</Button> : null}
            {canDelivrer && demande?.statut === 'ENVOYEE' ? <Button color="success" startDecorator={<Check size={16} />} onClick={() => setConfirmAction('delivrer')}>Délivrer</Button> : null}
            {canRefuser && demande?.statut === 'ENVOYEE' ? <Button variant="outlined" color="danger" startDecorator={<Ban size={16} />} onClick={() => { setMotifRefus(''); setRefusOpen(true); }}>Refuser</Button> : null}
            {canRegler && demande?.statut === 'DELIVREE' && demande?.statutPaiement === 'IMPAYEE' ? <Button color="success" onClick={() => setReglerOpen(true)}>Régler</Button> : null}
          </Stack>
        </Stack>

        {error ? <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>{error}</Typography> : null}
        {demande?.motifRefus ? <Typography level="body-sm">Motif de refus : {demande.motifRefus}</Typography> : null}

        {loading ? <Typography level="body-sm">Chargement…</Typography> : (
          <>
            <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5 }}>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Service</FormLabel>
                  <Select
                    value={form.serviceId || null}
                    onChange={(_, value) => {
                      setField('serviceId', value ?? '');
                      setField('visiteId', '');
                      setSelectedVisite(null);
                      setVisiteQuery('');
                      setVisiteResults([]);
                    }}
                    disabled={readOnly || saving}
                  >
                    {services.map((item) => <Option key={item.id} value={String(item.id)}>{item.code} — {item.libelle}</Option>)}
                  </Select>
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Patient hospitalisé (optionnel)</FormLabel>
                  {!form.serviceId ? (
                    <Typography level="body-sm" sx={{ color: 'neutral.500' }}>Choisissez d’abord le service.</Typography>
                  ) : selectedVisite ? (
                    <Stack spacing={0.5}>
                      <Typography level="body-sm" sx={{ fontWeight: 600 }}>
                        {selectedVisite.patientName || formatPatientName(selectedVisite.patient)}
                      </Typography>
                      <Typography level="body-xs" sx={{ color: 'neutral.500' }}>
                        {selectedVisite.numDossier ? `Dossier ${selectedVisite.numDossier}` : 'Hospitalisé dans ce service'}
                      </Typography>
                      {!readOnly ? (
                        <Button size="sm" variant="plain" onClick={() => { setSelectedVisite(null); setField('visiteId', ''); }}>
                          Retirer
                        </Button>
                      ) : null}
                    </Stack>
                  ) : (
                    <Stack spacing={0.75}>
                      <Input
                        startDecorator={<Search size={16} />}
                        placeholder="Nom ou n° dossier (2 caractères min.)"
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
                                setField('visiteId', String(visite.id));
                                setVisiteQuery('');
                                setVisiteResults([]);
                              }}
                            >
                              {visite.patientName || formatPatientName(visite.patient)}
                              {visite.numDossier ? ` · ${visite.numDossier}` : ''}
                            </Button>
                          ))}
                        </Stack>
                      ) : null}
                    </Stack>
                  )}
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Motif</FormLabel>
                  <Input value={form.motif} onChange={(e) => setField('motif', e.target.value)} disabled={readOnly || saving} />
                </FormControl>
              </Stack>
            </Card>
            <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5 }}>
              <Stack direction="row" justifyContent="space-between" sx={{ mb: 2 }}>
                <Typography level="title-md" sx={{ fontWeight: 700 }}>Lignes</Typography>
                {!readOnly ? <Button size="sm" variant="outlined" startDecorator={<Plus size={14} />} onClick={() => setForm((c) => ({ ...c, lignes: [...c.lignes, { ...EMPTY_DEMANDE_LIGNE }] }))}>Ajouter</Button> : null}
              </Stack>
              <Stack spacing={1.5}>
                {form.lignes.map((ligne, index) => (
                  <Stack key={`d-${index}`} direction={{ xs: 'column', md: 'row' }} spacing={1.5} alignItems={{ md: 'flex-end' }}>
                    <FormControl required sx={{ flex: 1 }}>
                      <FormLabel>Médicament</FormLabel>
                      <MedicamentAutocomplete options={medicaments} valueId={ligne.medicamentId} disabled={readOnly || saving} onSelect={(item) => setLigne(index, 'medicamentId', item ? String(item.id) : '')} />
                    </FormControl>
                    <FormControl required sx={{ width: { md: 120 } }}>
                      <FormLabel>Qté</FormLabel>
                      <Input type="number" value={ligne.quantite} onChange={(e) => setLigne(index, 'quantite', e.target.value)} disabled={readOnly || saving} slotProps={{ input: { min: 1 } }} />
                    </FormControl>
                    {!readOnly ? <IconButton variant="plain" color="danger" disabled={form.lignes.length === 1} onClick={() => setForm((c) => ({ ...c, lignes: c.lignes.filter((_, i) => i !== index) }))}><Trash2 size={16} /></IconButton> : null}
                  </Stack>
                ))}
              </Stack>
              <Typography level="title-lg" sx={{ mt: 2, textAlign: 'right', fontWeight: 700 }}>Total : {formatPrix(demande?.montantTotal)}</Typography>
            </Card>
          </>
        )}
      </Stack>

      <ConfirmModal open={confirmAction === 'envoyer'} title="Envoyer la demande" message="Le service pourra ensuite se voir délivrer le stock." confirmLabel="Envoyer" color="primary" loading={confirmLoading} onClose={() => setConfirmAction(null)} onConfirm={() => runAction(() => envoyerDemandeServiceApi(id), 'Demande envoyée.')} />
      <ConfirmModal open={confirmAction === 'delivrer'} title="Délivrer" message="Le stock sortira (FEFO) et une créance IMPAYEE sera ouverte." confirmLabel="Délivrer" color="success" loading={confirmLoading} onClose={() => setConfirmAction(null)} onConfirm={() => runAction(() => delivrerDemandeServiceApi(id), 'Demande délivrée.')} />
      <ConfirmModal open={confirmAction === 'delete'} title="Supprimer" message="Supprimer ce brouillon ?" confirmLabel="Supprimer" loading={confirmLoading} onClose={() => setConfirmAction(null)} onConfirm={async () => { setConfirmLoading(true); try { await deleteDemandeServiceApi(id); showSuccess('Supprimé.'); navigate(ROUTES.PHARMACIE.DEMANDES_SERVICE); } catch (err) { showError(err.message); } finally { setConfirmLoading(false); } }} />

      <Modal open={refusOpen} onClose={() => setRefusOpen(false)}>
        <ModalDialog sx={{ maxWidth: 440, width: '100%' }}>
          <Typography level="title-lg" sx={{ fontWeight: 700 }}>Refuser la demande</Typography>
          <FormControl required>
            <FormLabel>Motif</FormLabel>
            <Input value={motifRefus} onChange={(e) => setMotifRefus(e.target.value)} />
          </FormControl>
          <Stack direction="row" spacing={1} justifyContent="flex-end">
            <Button variant="plain" onClick={() => setRefusOpen(false)}>Fermer</Button>
            <Button color="danger" loading={confirmLoading} onClick={async () => { setConfirmLoading(true); try { setDemande(await refuserDemandeServiceApi(id, motifRefus)); setRefusOpen(false); showSuccess('Demande refusée.'); } catch (err) { setError(err.message); } finally { setConfirmLoading(false); } }}>Refuser</Button>
          </Stack>
        </ModalDialog>
      </Modal>

      <Modal open={reglerOpen} onClose={() => setReglerOpen(false)}>
        <ModalDialog sx={{ maxWidth: 440, width: '100%' }}>
          <Typography level="title-lg" sx={{ fontWeight: 700 }}>Régler la créance</Typography>
          <Typography level="body-sm">Montant : {formatPrix(demande?.montantTotal)} — aucun mouvement de stock.</Typography>
          <FormControl required>
            <FormLabel>Paiement</FormLabel>
            <Select value={modePaiement} onChange={(_, value) => setModePaiement(value ?? 'ESPECES')}>
              <Option value="ESPECES">Espèces</Option>
              <Option value="MOBILE">Mobile money</Option>
            </Select>
          </FormControl>
          <Stack direction="row" spacing={1} justifyContent="flex-end">
            <Button variant="plain" onClick={() => setReglerOpen(false)}>Fermer</Button>
            <Button color="success" loading={confirmLoading} onClick={async () => { setConfirmLoading(true); try { setDemande(await reglerDemandeServiceApi(id, modePaiement)); setReglerOpen(false); showSuccess('Créance réglée.'); } catch (err) { setError(err.message); } finally { setConfirmLoading(false); } }}>Encaisser</Button>
          </Stack>
        </ModalDialog>
      </Modal>
    </Box>
  );
}
