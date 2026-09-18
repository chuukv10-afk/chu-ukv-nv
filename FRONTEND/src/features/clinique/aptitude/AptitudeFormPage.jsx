import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  Alert, Box, Button, Card, Chip, FormControl, FormLabel, Input, Option, Radio, RadioGroup, Select, Stack, Tab, TabList, TabPanel, Tabs, Typography,
} from '@mui/joy';
import { Activity, ArrowLeft, FileText, HeartPulse, Save, Stamp, Trash2, UserRound, XCircle } from 'lucide-react';
import ConfirmModal from '../../../components/ui/ConfirmModal.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { ROUTES } from '../../../constants/routes.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { fetchPatientApi } from '../../patient/patients/patientsApi.js';
import { computeAptitudeIndices, formatIndice } from './aptitudeCalc.js';
import PatientSearchAutocomplete from './components/PatientSearchAutocomplete.jsx';
import { aptitudeFieldSx } from './aptitudeUi.js';
import {
  APTITUDE_MOTIFS,
  APTITUDE_STATUT_COLORS,
  APTITUDE_STATUT_LABELS,
  emptyAptitudeForm,
  formFromDetail,
  IMC_LABELS,
  PIGNET_LABELS,
  RUFFIER_LABELS,
  DICKSON_LABELS,
} from './aptitudeConstants.js';
import {
  annulerAptitudeApi,
  createAptitudeApi,
  deleteAptitudeApi,
  fetchAptitudeApi,
  fetchAptitudeFilieresApi,
  fetchAptitudeServicesApi,
  openAptitudePdfApi,
  signerAptitudeApi,
  updateAptitudeApi,
} from './aptitudeApi.js';

function toPayload(form) {
  return {
    serviceId: Number(form.serviceId),
    patientId: form.patientId || null,
    nom: form.nom.trim(),
    postNom: form.postNom.trim(),
    prenom: form.prenom.trim() || null,
    sexe: form.sexe,
    etatCivil: form.etatCivil.trim() || null,
    dateNaissance: form.dateNaissance || null,
    lieuNaissance: form.lieuNaissance.trim() || null,
    adresse: form.adresse.trim() || null,
    motif: form.motif,
    motifAutre: form.motif === 'AUTRE' ? (form.motifAutre.trim() || null) : null,
    filiereId: form.motif === 'ADMISSION_UKV' && form.filiereId ? Number(form.filiereId) : null,
    poidsKg: form.poidsKg === '' ? null : Number(form.poidsKg),
    tailleM: form.tailleM === '' ? null : Number(form.tailleM),
    perimetreThoraciqueCm: form.perimetreThoraciqueCm === '' ? null : Number(form.perimetreThoraciqueCm),
    p1: form.p1 === '' ? null : Number(form.p1),
    p2: form.p2 === '' ? null : Number(form.p2),
    p3: form.p3 === '' ? null : Number(form.p3),
    imcClasse: form.imcClasse || null,
    verdict: form.verdict || null,
  };
}

function CheckRow({ options, value }) {
  return (
    <Stack direction="row" spacing={2} flexWrap="wrap" useFlexGap>
      {options.map((opt) => (
        <Typography key={opt.value} level="body-sm" sx={{ color: value === opt.value ? 'primary.600' : 'neutral.600' }}>
          {value === opt.value ? '☑' : '☐'} {opt.label}
        </Typography>
      ))}
    </Stack>
  );
}

export default function AptitudeFormPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const isNew = !id;
  const canCreate = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_CREATE);
  const canDelete = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_DELETE);
  const canDeleteDefinitif = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_DELETE_DEFINITIF);
  const canSign = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_SIGN);
  const canExport = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_EXPORT);
  const canReadPatients = hasPermission(PERMISSIONS.PATIENT.PATIENT_READ);
  const sectionCreate = isNew && canCreate;
  const canEditIdentite = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_IDENTITE_UPDATE) || sectionCreate;
  const canEditImc = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_IMC_UPDATE) || sectionCreate;
  const canEditPignet = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_PIGNET_UPDATE) || sectionCreate;
  const canEditRuffier = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_RUFFIER_UPDATE) || sectionCreate;
  const canEditVerdict = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_VERDICT_UPDATE) || sectionCreate;
  const canViewIdentite = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_IDENTITE_READ) || canEditIdentite;
  const canViewImc = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_IMC_READ) || canEditImc;
  const canViewPignet = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_PIGNET_READ) || canEditPignet;
  const canViewRuffier = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_RUFFIER_READ) || canEditRuffier;
  const canViewVerdict = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_VERDICT_READ) || canEditVerdict;

  const [form, setForm] = useState(emptyAptitudeForm);
  const [detail, setDetail] = useState(null);
  const [services, setServices] = useState([]);
  const [filieres, setFilieres] = useState([]);
  const [selectedPatient, setSelectedPatient] = useState(null);
  const [loading, setLoading] = useState(!isNew);
  const [saving, setSaving] = useState(false);
  const [pdfLoading, setPdfLoading] = useState(false);
  const [error, setError] = useState('');
  const [confirmAction, setConfirmAction] = useState(null);
  const [confirmLoading, setConfirmLoading] = useState(false);
  const [tab, setTab] = useState(
    canViewIdentite ? 'identite'
      : canViewImc ? 'imc'
        : canViewPignet ? 'pignet'
          : canViewRuffier ? 'ruffier'
            : canViewVerdict ? 'verdict'
              : 'identite',
  );
  const lastPropose = useRef(null);
  const lastImcPropose = useRef(null);

  const locked = Boolean(detail && detail.statut !== 'BROUILLON');
  const identiteLocked = locked || !canEditIdentite;
  const imcLocked = locked || !canEditImc;
  const pignetLocked = locked || !canEditPignet;
  const ruffierLocked = locked || !canEditRuffier;
  const verdictLocked = locked || !canEditVerdict;
  const canSave = isNew ? canCreate : (canEditIdentite || canEditImc || canEditPignet || canEditRuffier || canEditVerdict) && !locked;

  const indices = useMemo(() => computeAptitudeIndices(form), [form]);

  useEffect(() => {
    const available = [
      canViewIdentite ? 'identite' : null,
      canViewImc ? 'imc' : null,
      canViewPignet ? 'pignet' : null,
      canViewRuffier ? 'ruffier' : null,
      canViewVerdict ? 'verdict' : null,
    ].filter(Boolean);
    if (available.length > 0 && !available.includes(tab)) {
      setTab(available[0]);
    }
  }, [canViewIdentite, canViewImc, canViewPignet, canViewRuffier, canViewVerdict, tab]);

  useEffect(() => {
    if (verdictLocked) return;
    const proposed = indices.verdictPropose;
    setForm((prev) => {
      if (!proposed) return prev;
      if (!prev.verdict || prev.verdict === lastPropose.current) {
        return { ...prev, verdict: proposed };
      }
      return prev;
    });
    lastPropose.current = proposed;
  }, [indices.verdictPropose, verdictLocked]);

  useEffect(() => {
    if (imcLocked) return;
    const proposed = indices.imcClasse;
    setForm((prev) => {
      if (!proposed) return prev;
      if (!prev.imcClasse || prev.imcClasse === lastImcPropose.current) {
        return { ...prev, imcClasse: proposed };
      }
      return prev;
    });
    lastImcPropose.current = proposed;
  }, [indices.imcClasse, imcLocked]);

  useEffect(() => {
    fetchAptitudeServicesApi().then(setServices).catch(() => setServices([]));
    fetchAptitudeFilieresApi().then(setFilieres).catch(() => setFilieres([]));
  }, []);

  useEffect(() => {
    if (isNew) return undefined;
    let cancelled = false;
    setLoading(true);
    fetchAptitudeApi(id)
      .then((data) => {
        if (cancelled) return;
        setDetail(data);
        setForm(formFromDetail(data));
        lastPropose.current = data.verdictPropose;
        lastImcPropose.current = data.imcClasseProposee ?? data.imcClasse;
        if (data.patientId) {
          fetchPatientApi(data.patientId).then(setSelectedPatient).catch(() => setSelectedPatient(null));
        } else {
          setSelectedPatient(null);
        }
      })
      .catch((err) => setError(err.message || 'Impossible de charger le certificat.'))
      .finally(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
  }, [id, isNew]);

  const setField = (key, value) => setForm((prev) => ({ ...prev, [key]: value }));

  const applyPatient = async (patient) => {
    if (!patient) {
      setSelectedPatient(null);
      setField('patientId', '');
      return;
    }
    let detailPatient = patient;
    try {
      detailPatient = await fetchPatientApi(patient.id);
    } catch {
      detailPatient = patient;
    }
    setSelectedPatient(detailPatient);
    setForm((prev) => ({
      ...prev,
      patientId: String(detailPatient.id),
      nom: detailPatient.nom ?? prev.nom,
      postNom: detailPatient.postNom ?? prev.postNom,
      prenom: detailPatient.prenom ?? prev.prenom,
      sexe: detailPatient.sexe ?? prev.sexe,
      dateNaissance: detailPatient.dateNaissance ?? prev.dateNaissance,
      lieuNaissance: detailPatient.lieuNaissance ?? prev.lieuNaissance,
      adresse: detailPatient.adresse ?? prev.adresse,
      filiereId: detailPatient.filiere?.id ? String(detailPatient.filiere.id) : prev.filiereId,
    }));
  };

  const handleSave = async () => {
    if (!form.serviceId) {
      setError('Sélectionnez un service.');
      return;
    }
    setSaving(true);
    setError('');
    try {
      const payload = toPayload(form);
      const saved = isNew ? await createAptitudeApi(payload) : await updateAptitudeApi(id, payload);
      showSuccess(isNew ? 'Brouillon enregistré.' : 'Certificat mis à jour.');
      if (isNew) {
        navigate(ROUTES.CLINIQUE.APTITUDE_DETAIL.replace(':id', saved.id), { replace: true });
      } else {
        setDetail(saved);
        setForm(formFromDetail(saved));
      }
    } catch (err) {
      setError(err.message || 'Enregistrement impossible.');
    } finally {
      setSaving(false);
    }
  };

  const handlePdf = async () => {
    if (!detail) return;
    setPdfLoading(true);
    try {
      await openAptitudePdfApi(detail.id);
    } catch (err) {
      showError(err.message || 'Impossible de générer le PDF.');
    } finally {
      setPdfLoading(false);
    }
  };

  const runConfirm = async () => {
    if (!confirmAction || !detail) return;
    setConfirmLoading(true);
    try {
      if (confirmAction === 'signer') {
        const signed = await signerAptitudeApi(detail.id);
        setDetail(signed);
        setForm(formFromDetail(signed));
        showSuccess('Certificat signé.');
      } else if (confirmAction === 'annuler') {
        const cancelled = await annulerAptitudeApi(detail.id);
        setDetail(cancelled);
        showSuccess('Certificat annulé.');
      } else if (confirmAction === 'delete') {
        await deleteAptitudeApi(detail.id);
        showSuccess(detail.statut === 'BROUILLON' ? 'Brouillon supprimé.' : 'Certificat supprimé définitivement.');
        navigate(ROUTES.CLINIQUE.APTITUDES, { replace: true });
      }
      setConfirmAction(null);
    } catch (err) {
      showError(err.message || 'Action impossible.');
    } finally {
      setConfirmLoading(false);
    }
  };

  if (loading) {
    return <Typography level="body-sm" sx={{ color: 'neutral.500' }}>Chargement…</Typography>;
  }

  return (
    <Stack spacing={2}>
      <Stack direction="row" justifyContent="space-between" alignItems="center" flexWrap="wrap" useFlexGap spacing={1}>
        <Button variant="plain" startDecorator={<ArrowLeft size={16} />} onClick={() => navigate(ROUTES.CLINIQUE.APTITUDES)}>
          Retour
        </Button>
        {detail ? (
          <Chip size="sm" color={APTITUDE_STATUT_COLORS[detail.statut] || 'neutral'} variant="soft">
            {APTITUDE_STATUT_LABELS[detail.statut] || detail.statut}
            {detail.numero ? ` · ${detail.numero}` : ''}
          </Chip>
        ) : null}
      </Stack>

      <Box>
        <Typography level="h2" sx={{ fontWeight: 700 }}>
          {isNew ? 'Nouveau certificat' : 'Certificat d’aptitude physique'}
        </Typography>
        <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
          Chaque onglet est soumis à une permission distincte. Sans droit de lecture, l’onglet reste visible mais désactivé.
        </Typography>
      </Box>

      {error ? <Alert color="danger" variant="soft">{error}</Alert> : null}

      <Tabs
        value={tab}
        onChange={(_, value) => {
          if (value === 'identite' && !canViewIdentite) return;
          if (value === 'imc' && !canViewImc) return;
          if (value === 'pignet' && !canViewPignet) return;
          if (value === 'ruffier' && !canViewRuffier) return;
          if (value === 'verdict' && !canViewVerdict) return;
          if (value) setTab(value);
        }}
      >
        <TabList
          sx={{
            overflowX: 'auto',
            flexWrap: 'nowrap',
            WebkitOverflowScrolling: 'touch',
            '&::-webkit-scrollbar': { height: 0 },
          }}
        >
          <Tab value="identite" disabled={!canViewIdentite} title={!canViewIdentite ? 'Permission requise pour consulter l’identité' : undefined} sx={{ flex: '0 0 auto', minHeight: 44, whiteSpace: 'nowrap' }}>
            <UserRound size={16} style={{ marginRight: 6 }} />Identité
          </Tab>
          <Tab value="imc" disabled={!canViewImc} title={!canViewImc ? 'Permission requise pour consulter l’IMC' : undefined} sx={{ flex: '0 0 auto', minHeight: 44, whiteSpace: 'nowrap' }}>
            <Activity size={16} style={{ marginRight: 6 }} />IMC
          </Tab>
          <Tab value="pignet" disabled={!canViewPignet} title={!canViewPignet ? 'Permission requise pour consulter l’indice de Pignet' : undefined} sx={{ flex: '0 0 auto', minHeight: 44, whiteSpace: 'nowrap' }}>
            <HeartPulse size={16} style={{ marginRight: 6 }} />Pignet
          </Tab>
          <Tab value="ruffier" disabled={!canViewRuffier} title={!canViewRuffier ? 'Permission requise pour consulter Ruffier-Dickson' : undefined} sx={{ flex: '0 0 auto', minHeight: 44, whiteSpace: 'nowrap' }}>
            <Activity size={16} style={{ marginRight: 6 }} />Ruffier
          </Tab>
          <Tab value="verdict" disabled={!canViewVerdict} title={!canViewVerdict ? 'Permission requise pour consulter le verdict' : undefined} sx={{ flex: '0 0 auto', minHeight: 44, whiteSpace: 'nowrap' }}>
            <Stamp size={16} style={{ marginRight: 6 }} />Verdict
          </Tab>
        </TabList>

        <TabPanel value="identite" sx={{ p: 0, pt: 2 }}>
          <Card variant="outlined">
            <Stack spacing={2}>
              <FormControl>
                <FormLabel>Rechercher un patient (auto-complétion)</FormLabel>
                <PatientSearchAutocomplete
                  value={selectedPatient}
                  disabled={identiteLocked || !canReadPatients}
                  onSelect={applyPatient}
                />
              </FormControl>
              <FormControl required>
                <FormLabel>Service</FormLabel>
                <Select
                  value={form.serviceId}
                  disabled={identiteLocked}
                  sx={aptitudeFieldSx}
                  slotProps={{ listbox: { sx: { zIndex: 1300 } } }}
                  onChange={(_, v) => setField('serviceId', v ?? '')}
                >
                  {services.map((s) => <Option key={s.id} value={String(s.id)}>{s.libelle}</Option>)}
                </Select>
              </FormControl>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Nom</FormLabel>
                  <Input value={form.nom} disabled={identiteLocked} sx={aptitudeFieldSx} onChange={(e) => setField('nom', e.target.value)} />
                </FormControl>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Postnom</FormLabel>
                  <Input value={form.postNom} disabled={identiteLocked} sx={aptitudeFieldSx} onChange={(e) => setField('postNom', e.target.value)} />
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Prénom</FormLabel>
                  <Input value={form.prenom} disabled={identiteLocked} sx={aptitudeFieldSx} onChange={(e) => setField('prenom', e.target.value)} />
                </FormControl>
              </Stack>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
                <FormControl required sx={{ minWidth: { xs: '100%', md: 160 } }}>
                  <FormLabel>Sexe</FormLabel>
                  <RadioGroup
                    orientation="horizontal"
                    value={form.sexe}
                    onChange={(e) => setField('sexe', e.target.value)}
                    sx={{ gap: 2, minHeight: 44, alignItems: 'center' }}
                  >
                    <Radio value="M" label="M" disabled={identiteLocked} />
                    <Radio value="F" label="F" disabled={identiteLocked} />
                  </RadioGroup>
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>État civil</FormLabel>
                  <Input value={form.etatCivil} disabled={identiteLocked} sx={aptitudeFieldSx} onChange={(e) => setField('etatCivil', e.target.value)} />
                </FormControl>
              </Stack>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Né(e) le</FormLabel>
                  <Input type="date" value={form.dateNaissance} disabled={identiteLocked} sx={aptitudeFieldSx} onChange={(e) => setField('dateNaissance', e.target.value)} />
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>à</FormLabel>
                  <Input value={form.lieuNaissance} disabled={identiteLocked} sx={aptitudeFieldSx} onChange={(e) => setField('lieuNaissance', e.target.value)} />
                </FormControl>
              </Stack>
              <FormControl>
                <FormLabel>Adresse de résidence</FormLabel>
                <Input value={form.adresse} disabled={identiteLocked} sx={aptitudeFieldSx} onChange={(e) => setField('adresse', e.target.value)} />
              </FormControl>
              <FormControl required>
                <FormLabel>Motif de l’examen</FormLabel>
                <RadioGroup
                  value={form.motif}
                  onChange={(e) => {
                    const next = e.target.value;
                    setForm((prev) => ({
                      ...prev,
                      motif: next,
                      filiereId: next === 'ADMISSION_UKV' ? prev.filiereId : '',
                      motifAutre: next === 'AUTRE' ? prev.motifAutre : '',
                    }));
                  }}
                >
                  {APTITUDE_MOTIFS.map((m) => (
                    <Radio key={m.value} value={m.value} label={m.label} disabled={identiteLocked} />
                  ))}
                </RadioGroup>
              </FormControl>
              {form.motif === 'ADMISSION_UKV' ? (
                <FormControl required>
                  <FormLabel>Filière</FormLabel>
                  <Select
                    placeholder={filieres.length ? 'Choisir une filière' : 'Aucune filière enregistrée'}
                    value={form.filiereId}
                    disabled={identiteLocked}
                    sx={aptitudeFieldSx}
                    slotProps={{ listbox: { sx: { zIndex: 1300 } } }}
                    onChange={(_, v) => setField('filiereId', v ?? '')}
                  >
                    {filieres.map((f) => (
                      <Option key={f.id} value={String(f.id)}>{f.code} — {f.libelle}</Option>
                    ))}
                  </Select>
                </FormControl>
              ) : null}
              {form.motif === 'AUTRE' ? (
                <FormControl>
                  <FormLabel>Préciser le motif</FormLabel>
                  <Input value={form.motifAutre} disabled={identiteLocked} sx={aptitudeFieldSx} onChange={(e) => setField('motifAutre', e.target.value)} />
                </FormControl>
              ) : null}
            </Stack>
          </Card>
        </TabPanel>

        <TabPanel value="imc" sx={{ p: 0, pt: 2 }}>
          <Card variant="outlined">
            <Stack spacing={2}>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Poids (kg)</FormLabel>
                  <Input type="number" value={form.poidsKg} disabled={imcLocked} sx={aptitudeFieldSx} slotProps={{ input: { inputMode: 'decimal', step: '0.1', min: '0' } }} onChange={(e) => setField('poidsKg', e.target.value)} />
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Taille (m)</FormLabel>
                  <Input
                    type="number"
                    slotProps={{ input: { inputMode: 'decimal', step: '0.01', min: '0.5', placeholder: '1.75' } }}
                    value={form.tailleM}
                    disabled={imcLocked}
                    sx={aptitudeFieldSx}
                    onChange={(e) => setField('tailleM', e.target.value)}
                  />
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>IMC (kg/m²)</FormLabel>
                  <Input value={formatIndice(indices.imc)} disabled sx={aptitudeFieldSx} />
                </FormControl>
              </Stack>
              <Box>
                <Typography level="body-sm" sx={{ mb: 0.5, color: 'neutral.500' }}>
                  Interprétation OMS (proposée, modifiable)
                </Typography>
                <RadioGroup
                  value={form.imcClasse}
                  onChange={(e) => setField('imcClasse', e.target.value)}
                >
                  {Object.entries(IMC_LABELS).map(([value, label]) => (
                    <Radio key={value} value={value} label={label} disabled={imcLocked} />
                  ))}
                </RadioGroup>
                {indices.imcClasse && form.imcClasse && form.imcClasse !== indices.imcClasse ? (
                  <Alert color="warning" variant="soft" sx={{ mt: 1 }}>
                    Proposition automatique : {IMC_LABELS[indices.imcClasse] || indices.imcClasse}.
                    Vous avez choisi {IMC_LABELS[form.imcClasse] || form.imcClasse}.
                  </Alert>
                ) : null}
              </Box>
            </Stack>
          </Card>
        </TabPanel>

        <TabPanel value="pignet" sx={{ p: 0, pt: 2 }}>
          <Card variant="outlined">
            <Stack spacing={2}>
              <FormControl sx={{ maxWidth: 280 }}>
                <FormLabel>Périmètre thoracique (cm)</FormLabel>
                <Input type="number" value={form.perimetreThoraciqueCm} disabled={pignetLocked} sx={aptitudeFieldSx} slotProps={{ input: { inputMode: 'decimal', step: '0.5', min: '0' } }} onChange={(e) => setField('perimetreThoraciqueCm', e.target.value)} />
              </FormControl>
              <Typography level="body-sm">Indice de Pignet : <strong>{formatIndice(indices.pignet)}</strong></Typography>
              <Box>
                <Typography level="body-sm" sx={{ mb: 0.5, color: 'neutral.500' }}>Constitution physique</Typography>
                <CheckRow
                  value={indices.pignetRobustesse}
                  options={Object.entries(PIGNET_LABELS).map(([value, label]) => ({ value, label }))}
                />
              </Box>
            </Stack>
          </Card>
        </TabPanel>

        <TabPanel value="ruffier" sx={{ p: 0, pt: 2 }}>
          <Card variant="outlined">
            <Stack spacing={2}>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>P1 repos (/min)</FormLabel>
                  <Input type="number" value={form.p1} disabled={ruffierLocked} sx={aptitudeFieldSx} slotProps={{ input: { inputMode: 'numeric', min: '0' } }} onChange={(e) => setField('p1', e.target.value)} />
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>P2 post-effort (/min)</FormLabel>
                  <Input type="number" value={form.p2} disabled={ruffierLocked} sx={aptitudeFieldSx} slotProps={{ input: { inputMode: 'numeric', min: '0' } }} onChange={(e) => setField('p2', e.target.value)} />
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>P3 récupération 1' (/min)</FormLabel>
                  <Input type="number" value={form.p3} disabled={ruffierLocked} sx={aptitudeFieldSx} slotProps={{ input: { inputMode: 'numeric', min: '0' } }} onChange={(e) => setField('p3', e.target.value)} />
                </FormControl>
              </Stack>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                <Typography level="body-sm">Ruffier : <strong>{formatIndice(indices.ruffier)}</strong></Typography>
                <Typography level="body-sm">Dickson : <strong>{formatIndice(indices.dickson)}</strong></Typography>
              </Stack>
              <Box>
                <Typography level="body-sm" sx={{ mb: 0.5, color: 'neutral.500' }}>Adaptation Ruffier</Typography>
                <CheckRow
                  value={indices.ruffierClasse}
                  options={Object.entries(RUFFIER_LABELS).map(([value, label]) => ({ value, label }))}
                />
              </Box>
              <Box>
                <Typography level="body-sm" sx={{ mb: 0.5, color: 'neutral.500' }}>Adaptation Dickson</Typography>
                <CheckRow
                  value={indices.dicksonClasse}
                  options={Object.entries(DICKSON_LABELS).map(([value, label]) => ({ value, label }))}
                />
              </Box>
            </Stack>
          </Card>
        </TabPanel>

        <TabPanel value="verdict" sx={{ p: 0, pt: 2 }}>
          <Card variant="outlined">
            <Stack spacing={2}>
              <Typography level="body-sm" sx={{ color: 'neutral.600' }}>
                Après examen clinique complet, le/la candidat(e) est déclaré(e) :
              </Typography>
              <RadioGroup
                orientation="horizontal"
                value={form.verdict}
                onChange={(e) => setField('verdict', e.target.value)}
                sx={{ gap: 2, flexWrap: 'wrap', minHeight: 44 }}
              >
                <Radio value="APTE" label="APTE" disabled={verdictLocked} />
                <Radio value="INAPTE" label="INAPTE" disabled={verdictLocked} />
              </RadioGroup>
              {indices.verdictPropose && form.verdict && form.verdict !== indices.verdictPropose ? (
                <Alert color="warning" variant="soft">
                  Proposition automatique : {indices.verdictPropose}. Vous avez choisi {form.verdict}.
                </Alert>
              ) : null}
            </Stack>
          </Card>
        </TabPanel>
      </Tabs>

      <Box sx={{ display: { xs: 'block', sm: 'none' }, height: 96 }} />

      <Stack
        direction={{ xs: 'column', sm: 'row' }}
        spacing={1}
        justifyContent="flex-end"
        sx={{
          position: { xs: 'fixed', sm: 'static' },
          bottom: 0,
          left: 0,
          right: 0,
          zIndex: 20,
          bgcolor: 'background.body',
          py: { xs: 1.5, sm: 0 },
          mt: 1,
          px: { xs: 2, sm: 0 },
          pb: { xs: 'calc(12px + env(safe-area-inset-bottom, 0px))', sm: 0 },
          borderTop: { xs: '1px solid', sm: 'none' },
          borderColor: 'divider',
          boxShadow: { xs: '0 -8px 24px rgba(15, 23, 42, 0.08)', sm: 'none' },
        }}
      >
        <Button
          size="lg"
          startDecorator={<Save size={16} />}
          loading={saving}
          disabled={!canSave}
          title={!canSave ? 'Permission requise pour enregistrer' : undefined}
          onClick={handleSave}
        >
          Enregistrer
        </Button>
        {detail?.statut === 'BROUILLON' ? (
          <Button
            size="lg"
            color="success"
            startDecorator={<Stamp size={16} />}
            disabled={!canSign}
            title={!canSign ? 'Permission requise pour signer' : undefined}
            onClick={() => {
              if (!canSign) return;
              if (form.motif === 'ADMISSION_UKV' && !form.filiereId) {
                setError('Sélectionnez une filière avant de signer une admission UKV.');
                return;
              }
              setConfirmAction('signer');
            }}
          >
            Signer
          </Button>
        ) : null}
        {detail && detail.statut !== 'BROUILLON' ? (
          <Button
            size="lg"
            variant="outlined"
            startDecorator={<FileText size={16} />}
            loading={pdfLoading}
            disabled={!canExport || pdfLoading}
            title={!canExport ? 'Permission requise pour imprimer' : undefined}
            onClick={handlePdf}
          >
            {pdfLoading ? 'Génération…' : 'PDF'}
          </Button>
        ) : null}
        {detail?.statut === 'SIGNE' ? (
          <Button
            size="lg"
            color="danger"
            variant="outlined"
            startDecorator={<XCircle size={16} />}
            disabled={!canSign}
            title={!canSign ? 'Permission requise pour annuler' : undefined}
            onClick={() => { if (canSign) setConfirmAction('annuler'); }}
          >
            Annuler le certificat
          </Button>
        ) : null}
        {detail && (detail.statut === 'BROUILLON' ? canDelete || canDeleteDefinitif : canDeleteDefinitif) ? (
          <Button
            size="lg"
            color="danger"
            variant="plain"
            startDecorator={<Trash2 size={16} />}
            title={detail.statut === 'BROUILLON' ? 'Supprimer le brouillon' : 'Supprimer définitivement'}
            onClick={() => setConfirmAction('delete')}
          >
            {detail.statut === 'BROUILLON' ? 'Supprimer' : 'Supprimer définitivement'}
          </Button>
        ) : null}
      </Stack>

      <ConfirmModal
        open={Boolean(confirmAction)}
        title={
          confirmAction === 'signer' ? 'Signer le certificat'
            : confirmAction === 'annuler' ? 'Annuler le certificat'
              : detail?.statut === 'BROUILLON' ? 'Supprimer le brouillon'
                : 'Supprimer définitivement'
        }
        message={
          confirmAction === 'signer'
            ? 'Le numéro officiel sera attribué et le PDF figé. Continuer ?'
            : confirmAction === 'annuler'
              ? 'Le certificat restera archivé avec son numéro, marqué annulé.'
              : detail?.statut === 'BROUILLON'
                ? 'Cette action est définitive.'
                : 'Le certificat sera effacé, y compris s’il est signé ou déjà annulé. Cette action est irréversible.'
        }
        confirmLabel={confirmAction === 'signer' ? 'Signer' : confirmAction === 'annuler' ? 'Annuler le certificat' : 'Supprimer définitivement'}
        color={confirmAction === 'signer' ? 'success' : 'danger'}
        loading={confirmLoading}
        onClose={() => { if (!confirmLoading) setConfirmAction(null); }}
        onConfirm={runConfirm}
      />
    </Stack>
  );
}
