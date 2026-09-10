import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  Alert, Box, Button, Card, Chip, FormControl, FormLabel, Input, Option, Radio, RadioGroup, Select, Stack, Typography,
} from '@mui/joy';
import Autocomplete from '@mui/joy/Autocomplete';
import { ArrowLeft, FileText, Save, Stamp, Trash2, XCircle } from 'lucide-react';
import ConfirmModal from '../../../components/ui/ConfirmModal.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { ROUTES } from '../../../constants/routes.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import { fetchPatientApi, fetchPatientsApi } from '../../patient/patients/patientsApi.js';
import { computeAptitudeIndices, formatIndice } from './aptitudeCalc.js';
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
  const canUpdate = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_UPDATE);
  const canDelete = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_DELETE);
  const canSign = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_SIGN);
  const canExport = hasPermission(PERMISSIONS.CLINIQUE.APTITUDE_EXPORT);
  const canReadPatients = hasPermission(PERMISSIONS.PATIENT.PATIENT_READ);

  const [form, setForm] = useState(emptyAptitudeForm);
  const [detail, setDetail] = useState(null);
  const [services, setServices] = useState([]);
  const [patientOptions, setPatientOptions] = useState([]);
  const [patientQuery, setPatientQuery] = useState('');
  const [loading, setLoading] = useState(!isNew);
  const [saving, setSaving] = useState(false);
  const [pdfLoading, setPdfLoading] = useState(false);
  const [error, setError] = useState('');
  const [confirmAction, setConfirmAction] = useState(null);
  const [confirmLoading, setConfirmLoading] = useState(false);
  const lastPropose = useRef(null);
  const lastImcPropose = useRef(null);

  const locked = Boolean(detail && detail.statut !== 'BROUILLON');
  const canSave = isNew ? canCreate : canUpdate && !locked;

  const indices = useMemo(() => computeAptitudeIndices(form), [form]);

  useEffect(() => {
    if (locked) return;
    const proposed = indices.verdictPropose;
    setForm((prev) => {
      if (!proposed) return prev;
      if (!prev.verdict || prev.verdict === lastPropose.current) {
        return { ...prev, verdict: proposed };
      }
      return prev;
    });
    lastPropose.current = proposed;
  }, [indices.verdictPropose, locked]);

  useEffect(() => {
    if (locked) return;
    const proposed = indices.imcClasse;
    setForm((prev) => {
      if (!proposed) return prev;
      if (!prev.imcClasse || prev.imcClasse === lastImcPropose.current) {
        return { ...prev, imcClasse: proposed };
      }
      return prev;
    });
    lastImcPropose.current = proposed;
  }, [indices.imcClasse, locked]);

  useEffect(() => {
    fetchAptitudeServicesApi().then(setServices).catch(() => setServices([]));
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
      })
      .catch((err) => setError(err.message || 'Impossible de charger le certificat.'))
      .finally(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
  }, [id, isNew]);

  useEffect(() => {
    if (!canReadPatients) return undefined;
    const q = patientQuery.trim();
    const timer = window.setTimeout(async () => {
      try {
        const result = await fetchPatientsApi({ page: 1, limit: 15, search: q || undefined });
        setPatientOptions(result.items);
      } catch {
        setPatientOptions([]);
      }
    }, 250);
    return () => window.clearTimeout(timer);
  }, [patientQuery, canReadPatients]);

  const setField = (key, value) => setForm((prev) => ({ ...prev, [key]: value }));

  const applyPatient = async (patient) => {
    if (!patient) {
      setField('patientId', '');
      return;
    }
    let detailPatient = patient;
    try {
      detailPatient = await fetchPatientApi(patient.id);
    } catch {
      detailPatient = patient;
    }
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
        showSuccess('Brouillon supprimé.');
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
          Les indices sont calculés automatiquement. Le verdict proposé peut être modifié avant signature.
        </Typography>
      </Box>

      {error ? <Alert color="danger" variant="soft">{error}</Alert> : null}

      <Card variant="outlined">
        <Stack spacing={2}>
          <Typography level="title-md">I. Identification du candidat</Typography>
          {canReadPatients && !locked ? (
            <FormControl>
              <FormLabel>Lier un patient existant (optionnel)</FormLabel>
              <Autocomplete
                options={patientOptions}
                placeholder="Rechercher un patient…"
                getOptionLabel={(item) => item.fullName || `${item.nom} ${item.postNom}`}
                isOptionEqualToValue={(a, b) => String(a.id) === String(b.id)}
                onInputChange={(_, value) => setPatientQuery(value)}
                onChange={(_, selected) => applyPatient(selected)}
                slotProps={{ input: { autoComplete: 'off' } }}
              />
            </FormControl>
          ) : null}
          <FormControl required>
            <FormLabel>Service</FormLabel>
            <Select
              value={form.serviceId}
              disabled={locked}
              onChange={(_, v) => setField('serviceId', v ?? '')}
            >
              {services.map((s) => <Option key={s.id} value={String(s.id)}>{s.libelle}</Option>)}
            </Select>
          </FormControl>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
            <FormControl required sx={{ flex: 1 }}>
              <FormLabel>Nom</FormLabel>
              <Input value={form.nom} disabled={locked} onChange={(e) => setField('nom', e.target.value)} />
            </FormControl>
            <FormControl required sx={{ flex: 1 }}>
              <FormLabel>Postnom</FormLabel>
              <Input value={form.postNom} disabled={locked} onChange={(e) => setField('postNom', e.target.value)} />
            </FormControl>
            <FormControl sx={{ flex: 1 }}>
              <FormLabel>Prénom</FormLabel>
              <Input value={form.prenom} disabled={locked} onChange={(e) => setField('prenom', e.target.value)} />
            </FormControl>
          </Stack>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
            <FormControl required sx={{ minWidth: 160 }}>
              <FormLabel>Sexe</FormLabel>
              <RadioGroup
                orientation="horizontal"
                value={form.sexe}
                onChange={(e) => setField('sexe', e.target.value)}
              >
                <Radio value="M" label="M" disabled={locked} />
                <Radio value="F" label="F" disabled={locked} />
              </RadioGroup>
            </FormControl>
            <FormControl sx={{ flex: 1 }}>
              <FormLabel>État civil</FormLabel>
              <Input value={form.etatCivil} disabled={locked} onChange={(e) => setField('etatCivil', e.target.value)} />
            </FormControl>
          </Stack>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
            <FormControl sx={{ flex: 1 }}>
              <FormLabel>Né(e) le</FormLabel>
              <Input type="date" value={form.dateNaissance} disabled={locked} onChange={(e) => setField('dateNaissance', e.target.value)} />
            </FormControl>
            <FormControl sx={{ flex: 1 }}>
              <FormLabel>à</FormLabel>
              <Input value={form.lieuNaissance} disabled={locked} onChange={(e) => setField('lieuNaissance', e.target.value)} />
            </FormControl>
          </Stack>
          <FormControl>
            <FormLabel>Adresse de résidence</FormLabel>
            <Input value={form.adresse} disabled={locked} onChange={(e) => setField('adresse', e.target.value)} />
          </FormControl>
          <FormControl required>
            <FormLabel>Motif de l’examen</FormLabel>
            <RadioGroup
              value={form.motif}
              onChange={(e) => setField('motif', e.target.value)}
            >
              {APTITUDE_MOTIFS.map((m) => (
                <Radio key={m.value} value={m.value} label={m.label} disabled={locked} />
              ))}
            </RadioGroup>
          </FormControl>
          {form.motif === 'AUTRE' ? (
            <FormControl>
              <FormLabel>Préciser le motif</FormLabel>
              <Input value={form.motifAutre} disabled={locked} onChange={(e) => setField('motifAutre', e.target.value)} />
            </FormControl>
          ) : null}
        </Stack>
      </Card>

      <Card variant="outlined">
        <Stack spacing={2}>
          <Typography level="title-md">II. Indice de masse corporelle (IMC)</Typography>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
            <FormControl sx={{ flex: 1 }}>
              <FormLabel>Poids (kg)</FormLabel>
              <Input type="number" value={form.poidsKg} disabled={locked} onChange={(e) => setField('poidsKg', e.target.value)} />
            </FormControl>
            <FormControl sx={{ flex: 1 }}>
              <FormLabel>Taille (m)</FormLabel>
              <Input
                type="number"
                slotProps={{ input: { step: '0.01', min: '0.5', placeholder: '1.75' } }}
                value={form.tailleM}
                disabled={locked}
                onChange={(e) => setField('tailleM', e.target.value)}
              />
            </FormControl>
            <FormControl sx={{ flex: 1 }}>
              <FormLabel>IMC (kg/m²)</FormLabel>
              <Input value={formatIndice(indices.imc)} disabled />
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
                <Radio key={value} value={value} label={label} disabled={locked} />
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

      <Card variant="outlined">
        <Stack spacing={2}>
          <Typography level="title-md">III. Indice de Pignet (constitution physique)</Typography>
          <FormControl sx={{ maxWidth: 280 }}>
            <FormLabel>Périmètre thoracique (cm)</FormLabel>
            <Input type="number" value={form.perimetreThoraciqueCm} disabled={locked} onChange={(e) => setField('perimetreThoraciqueCm', e.target.value)} />
          </FormControl>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
            <Typography level="body-sm">Indice de Pignet : <strong>{formatIndice(indices.pignet)}</strong></Typography>
          </Stack>
          <Box>
            <Typography level="body-sm" sx={{ mb: 0.5, color: 'neutral.500' }}>Constitution physique</Typography>
            <CheckRow
              value={indices.pignetRobustesse}
              options={Object.entries(PIGNET_LABELS).map(([value, label]) => ({ value, label }))}
            />
          </Box>
        </Stack>
      </Card>

      <Card variant="outlined">
        <Stack spacing={2}>
          <Typography level="title-md">IV. Indice de Ruffier-Dickson</Typography>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
            <FormControl sx={{ flex: 1 }}>
              <FormLabel>P1 repos (/min)</FormLabel>
              <Input type="number" value={form.p1} disabled={locked} onChange={(e) => setField('p1', e.target.value)} />
            </FormControl>
            <FormControl sx={{ flex: 1 }}>
              <FormLabel>P2 post-effort (/min)</FormLabel>
              <Input type="number" value={form.p2} disabled={locked} onChange={(e) => setField('p2', e.target.value)} />
            </FormControl>
            <FormControl sx={{ flex: 1 }}>
              <FormLabel>P3 récupération 1' (/min)</FormLabel>
              <Input type="number" value={form.p3} disabled={locked} onChange={(e) => setField('p3', e.target.value)} />
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

      <Card variant="outlined">
        <Stack spacing={2}>
          <Typography level="title-md">V. Conclusion et verdict médical</Typography>
          <Typography level="body-sm" sx={{ color: 'neutral.600' }}>
            Après examen clinique complet, le/la candidat(e) est déclaré(e) :
          </Typography>
          <RadioGroup
            orientation="horizontal"
            value={form.verdict}
            onChange={(e) => setField('verdict', e.target.value)}
          >
            <Radio value="APTE" label="APTE" disabled={locked} />
            <Radio value="INAPTE" label="INAPTE" disabled={locked} />
          </RadioGroup>
          {indices.verdictPropose && form.verdict && form.verdict !== indices.verdictPropose ? (
            <Alert color="warning" variant="soft">
              Proposition automatique : {indices.verdictPropose}. Vous avez choisi {form.verdict}.
            </Alert>
          ) : null}
        </Stack>
      </Card>

      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} justifyContent="flex-end">
        {canSave ? (
          <Button startDecorator={<Save size={16} />} loading={saving} onClick={handleSave}>
            Enregistrer
          </Button>
        ) : null}
        {canSign && detail?.statut === 'BROUILLON' ? (
          <Button color="success" startDecorator={<Stamp size={16} />} onClick={() => setConfirmAction('signer')}>
            Signer
          </Button>
        ) : null}
        {canExport && detail && detail.statut !== 'BROUILLON' ? (
          <Button
            variant="outlined"
            startDecorator={<FileText size={16} />}
            loading={pdfLoading}
            disabled={pdfLoading}
            onClick={handlePdf}
          >
            {pdfLoading ? 'Génération…' : 'PDF'}
          </Button>
        ) : null}
        {canSign && detail?.statut === 'SIGNE' ? (
          <Button color="danger" variant="outlined" startDecorator={<XCircle size={16} />} onClick={() => setConfirmAction('annuler')}>
            Annuler le certificat
          </Button>
        ) : null}
        {canDelete && detail?.statut === 'BROUILLON' ? (
          <Button color="danger" variant="plain" startDecorator={<Trash2 size={16} />} onClick={() => setConfirmAction('delete')}>
            Supprimer
          </Button>
        ) : null}
      </Stack>

      <ConfirmModal
        open={Boolean(confirmAction)}
        title={
          confirmAction === 'signer' ? 'Signer le certificat'
            : confirmAction === 'annuler' ? 'Annuler le certificat'
              : 'Supprimer le brouillon'
        }
        message={
          confirmAction === 'signer'
            ? 'Le numéro officiel sera attribué et le PDF figé. Continuer ?'
            : confirmAction === 'annuler'
              ? 'Le certificat restera archivé avec son numéro, marqué annulé.'
              : 'Cette action est définitive.'
        }
        confirmLabel={confirmAction === 'signer' ? 'Signer' : confirmAction === 'annuler' ? 'Annuler le certificat' : 'Supprimer'}
        color={confirmAction === 'signer' ? 'success' : 'danger'}
        loading={confirmLoading}
        onClose={() => { if (!confirmLoading) setConfirmAction(null); }}
        onConfirm={runConfirm}
      />
    </Stack>
  );
}
