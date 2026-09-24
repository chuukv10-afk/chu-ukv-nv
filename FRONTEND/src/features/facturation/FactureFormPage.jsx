import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  Box, Button, Card, Chip, FormControl, FormHelperText, FormLabel, IconButton, Input, Option, Select, Stack, Table, Typography,
} from '@mui/joy';
import { ArrowLeft, Check, Plus, Receipt, Trash2, UserPlus } from 'lucide-react';
import ConfirmModal from '../../components/ui/ConfirmModal.jsx';
import PatientSearchAutocomplete from '../clinique/aptitude/components/PatientSearchAutocomplete.jsx';
import PatientFormModal from '../patient/patients/components/PatientFormModal.jsx';
import { EMPTY_PATIENT_FORM } from '../patient/patients/patientConstants.js';
import { createPatientApi, fetchPatientMetaApi } from '../patient/patients/patientsApi.js';
import { PERMISSIONS } from '../../constants/permissions.js';
import { ROUTES } from '../../constants/routes.js';
import { usePermissions } from '../../hooks/usePermissions.js';
import { useToast } from '../../hooks/useToast.js';
import { LOTRU_PRIMARY } from '../../theme/lotruPalette.js';
import ActeSearchAutocomplete from './components/ActeSearchAutocomplete.jsx';
import RemiseFields from './components/RemiseFields.jsx';
import {
  allowedStructureTypesFor,
  CATEGORIES_TARIFAIRES,
  CATEGORIE_TARIFAIRE_LABELS,
  computeFactureTotals,
  DEFAULT_CATEGORIE_TARIFAIRE,
  emptyFactureForm,
  emptyRemise,
  FACTURE_STATUT_COLORS,
  FACTURE_STATUT_LABELS,
  formatFc,
  formatRemiseLabel,
  patientLabel,
  REMISE_NONE,
  repriceLigne,
  repriceLignes,
  structureRequiredFor,
} from './facturationConstants.js';
import {
  createFactureApi,
  deleteFactureApi,
  fetchFactureApi,
  fetchStructuresActivesApi,
  updateFactureApi,
  validerFactureApi,
} from './facturationApi.js';

function toPayload(form, canRemise = false) {
  const requiresStructure = structureRequiredFor(form.categorieTarifaire);
  return {
    patientId: form.patientId,
    dateFacture: form.dateFacture,
    categorieTarifaire: form.categorieTarifaire || DEFAULT_CATEGORIE_TARIFAIRE,
    structureId: requiresStructure && form.structureId ? Number(form.structureId) : null,
    numeroAffiliation: form.numeroAffiliation.trim() || null,
    notes: form.notes.trim() || null,
    remiseType: canRemise && form.remiseType ? form.remiseType : REMISE_NONE,
    remiseValeur: canRemise && form.remiseType && form.remiseType !== REMISE_NONE ? String(form.remiseValeur || '0') : '0',
    lignes: form.lignes.map((ligne) => ({
      acteId: Number(ligne.acteId),
      quantite: Number(ligne.quantite) || 1,
      remiseType: canRemise && ligne.remiseType ? ligne.remiseType : REMISE_NONE,
      remiseValeur: canRemise && ligne.remiseType && ligne.remiseType !== REMISE_NONE ? String(ligne.remiseValeur || '0') : '0',
    })),
  };
}

function applyPatientSnapshot(current, patient) {
  if (!patient) {
    return {
      ...current,
      patientId: '',
      categorieTarifaire: DEFAULT_CATEGORIE_TARIFAIRE,
      structureId: '',
      numeroAffiliation: '',
      lignes: repriceLignes(current.lignes, DEFAULT_CATEGORIE_TARIFAIRE),
    };
  }
  const categorie = CATEGORIES_TARIFAIRES.some((item) => item.value === patient.categorieTarifaire)
    ? patient.categorieTarifaire
    : DEFAULT_CATEGORIE_TARIFAIRE;
  return {
    ...current,
    patientId: patient.id,
    categorieTarifaire: categorie,
    structureId: patient.structure?.id ? String(patient.structure.id) : '',
    numeroAffiliation: patient.numeroAffiliation ?? '',
    lignes: repriceLignes(current.lignes, categorie),
  };
}

export default function FactureFormPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const isNew = !id;
  const canCreate = hasPermission(PERMISSIONS.FACTURATION.FACTURE_CREATE);
  const canUpdate = hasPermission(PERMISSIONS.FACTURATION.FACTURE_UPDATE);
  const canDelete = hasPermission(PERMISSIONS.FACTURATION.FACTURE_DELETE);
  const canValider = hasPermission(PERMISSIONS.FACTURATION.FACTURE_VALIDER);
  const canRemise = hasPermission(PERMISSIONS.FACTURATION.FACTURE_REMISE);
  const canCreatePatient = hasPermission(PERMISSIONS.PATIENT.PATIENT_CREATE);

  const [form, setForm] = useState(() => emptyFactureForm());
  const [facture, setFacture] = useState(null);
  const [patient, setPatient] = useState(null);
  const [structures, setStructures] = useState([]);
  const [patientMeta, setPatientMeta] = useState({ structures: [], filieres: [], organisations: [] });
  const [loading, setLoading] = useState(!isNew);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [patientModalOpen, setPatientModalOpen] = useState(false);
  const [patientFormLoading, setPatientFormLoading] = useState(false);
  const [patientFormError, setPatientFormError] = useState('');
  const [confirmAction, setConfirmAction] = useState(null);
  const [confirmLoading, setConfirmLoading] = useState(false);

  const readOnly = Boolean(facture && facture.statut !== 'BROUILLON');
  const canSave = isNew ? canCreate : canUpdate && !readOnly;
  const requiresStructure = structureRequiredFor(form.categorieTarifaire);
  const allowedTypes = allowedStructureTypesFor(form.categorieTarifaire);
  const filteredStructures = useMemo(
    () => structures.filter((item) => !allowedTypes.length || allowedTypes.includes(item.type)),
    [structures, allowedTypes],
  );
  const totals = computeFactureTotals(form);
  const montantTotal = totals.montantTotal;
  const canEditRemise = canRemise && !readOnly;

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const [structureList, meta] = await Promise.all([
          fetchStructuresActivesApi(),
          canCreatePatient ? fetchPatientMetaApi().catch(() => ({})) : Promise.resolve({}),
        ]);
        if (!cancelled) {
          setStructures(structureList);
          setPatientMeta({
            structures: meta.structures ?? [],
            filieres: meta.filieres ?? [],
            organisations: meta.organisations ?? [],
          });
        }
      } catch (err) {
        if (!cancelled) setError(err.message || 'Référentiels indisponibles.');
      }
    })();
    return () => { cancelled = true; };
  }, [canCreatePatient]);

  useEffect(() => {
    if (isNew) return undefined;
    let cancelled = false;
    (async () => {
      setLoading(true);
      setError('');
      try {
        const data = await fetchFactureApi(id);
        if (cancelled) return;
        setFacture(data);
        setPatient(data.patient ?? null);
        setForm({
          patientId: data.patientId ?? data.patient?.id ?? '',
          dateFacture: data.dateFacture ?? emptyFactureForm().dateFacture,
          categorieTarifaire: data.categorieTarifaire || DEFAULT_CATEGORIE_TARIFAIRE,
          structureId: data.structureId ? String(data.structureId) : '',
          numeroAffiliation: data.numeroAffiliation ?? '',
          notes: data.notes ?? '',
          remiseType: data.remiseType || REMISE_NONE,
          remiseValeur: data.remiseType && data.remiseType !== REMISE_NONE ? String(data.remiseValeur ?? '') : '',
          lignes: (data.lignes ?? []).map((ligne) => repriceLigne({
            acteId: ligne.acteId,
            codeActe: ligne.codeActe,
            libelle: ligne.libelle,
            serviceGrille: ligne.serviceGrille,
            quantite: ligne.quantite,
            tarifUnitaire: Number(ligne.tarifUnitaire),
            remiseType: ligne.remiseType || REMISE_NONE,
            remiseValeur: ligne.remiseType && ligne.remiseType !== REMISE_NONE ? String(ligne.remiseValeur ?? '') : '',
            acte: ligne.acte,
          }, data.categorieTarifaire || DEFAULT_CATEGORIE_TARIFAIRE)),
        });
      } catch (err) {
        if (!cancelled) setError(err.message || 'Facture introuvable.');
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true; };
  }, [id, isNew]);

  const selectPatient = (next) => {
    setPatient(next);
    setForm((current) => applyPatientSnapshot(current, next));
  };

  const setCategorie = (value) => {
    const categorie = value || DEFAULT_CATEGORIE_TARIFAIRE;
    setForm((current) => ({
      ...current,
      categorieTarifaire: categorie,
      structureId: structureRequiredFor(categorie) ? current.structureId : '',
      lignes: repriceLignes(current.lignes, categorie),
    }));
  };

  const addActe = (acte) => {
    setForm((current) => {
      const existing = current.lignes.find((ligne) => String(ligne.acteId) === String(acte.id));
      if (existing) {
        return {
          ...current,
          lignes: repriceLignes(
            current.lignes.map((ligne) => (
              String(ligne.acteId) === String(acte.id)
                ? { ...ligne, quantite: Number(ligne.quantite) + 1, acte }
                : ligne
            )),
            current.categorieTarifaire,
          ),
        };
      }
      return {
        ...current,
        lignes: [
          ...current.lignes,
          repriceLigne({
            acteId: acte.id,
            codeActe: acte.code,
            libelle: acte.libelle,
            serviceGrille: acte.serviceGrille,
            quantite: 1,
            ...emptyRemise(),
            acte,
          }, current.categorieTarifaire),
        ],
      };
    });
  };

  const setQuantite = (index, value) => {
    setForm((current) => ({
      ...current,
      lignes: repriceLignes(
        current.lignes.map((ligne, i) => (i === index ? { ...ligne, quantite: value } : ligne)),
        current.categorieTarifaire,
      ),
    }));
  };

  const setLigneRemise = (index, remiseType, remiseValeur) => {
    setForm((current) => ({
      ...current,
      lignes: repriceLignes(
        current.lignes.map((ligne, i) => (
          i === index ? { ...ligne, remiseType, remiseValeur: remiseType === REMISE_NONE ? '' : remiseValeur } : ligne
        )),
        current.categorieTarifaire,
      ),
    }));
  };

  const setRemiseGlobale = (remiseType, remiseValeur) => {
    setForm((current) => ({
      ...current,
      remiseType,
      remiseValeur: remiseType === REMISE_NONE ? '' : remiseValeur,
    }));
  };

  const removeLigne = (index) => {
    setForm((current) => ({
      ...current,
      lignes: current.lignes.filter((_, i) => i !== index),
    }));
  };

  const assertForm = () => {
    if (!form.patientId) throw new Error('Recherchez ou ajoutez le patient concerné.');
    if (!form.dateFacture) throw new Error('La date de facture est obligatoire.');
    if (requiresStructure && !form.structureId) {
      throw new Error('Une structure est obligatoire pour cette catégorie.');
    }
  };

  const handleSave = async () => {
    try {
      assertForm();
    } catch (err) {
      setError(err.message);
      return;
    }
    setSaving(true);
    setError('');
    try {
      if (isNew) {
        const created = await createFactureApi(toPayload(form, canRemise));
        showSuccess('Brouillon de facture enregistré.');
        navigate(ROUTES.FACTURATION.FACTURE_DETAIL.replace(':id', String(created.id)), { replace: true });
      } else {
        const updated = await updateFactureApi(id, toPayload(form, canRemise));
        setFacture(updated);
        showSuccess('Facture mise à jour.');
      }
    } catch (err) {
      setError(err.message || 'Enregistrement impossible.');
    } finally {
      setSaving(false);
    }
  };

  const handleValider = async () => {
    try {
      assertForm();
      if (!form.lignes.length) throw new Error('Ajoutez au moins un acte avant de valider.');
    } catch (err) {
      setError(err.message);
      setConfirmAction(null);
      return;
    }
    setConfirmLoading(true);
    setSaving(true);
    setError('');
    try {
      let targetId = id;
      if (isNew || canSave) {
        const saved = isNew
          ? await createFactureApi(toPayload(form, canRemise))
          : await updateFactureApi(id, toPayload(form, canRemise));
        targetId = saved.id;
        setFacture(saved);
      }
      const validated = await validerFactureApi(targetId);
      setFacture(validated);
      setConfirmAction(null);
      showSuccess('Facture validée.');
      if (isNew) {
        navigate(ROUTES.FACTURATION.FACTURE_DETAIL.replace(':id', String(validated.id)), { replace: true });
      }
    } catch (err) {
      setError(err.message || 'Validation impossible.');
    } finally {
      setSaving(false);
      setConfirmLoading(false);
    }
  };

  const handleDelete = async () => {
    setConfirmLoading(true);
    try {
      await deleteFactureApi(id);
      showSuccess('Facture supprimée.');
      navigate(ROUTES.FACTURATION.FACTURES);
    } catch (err) {
      showError(err.message || 'Suppression impossible.');
    } finally {
      setConfirmLoading(false);
      setConfirmAction(null);
    }
  };

  const handleCreatePatient = async (payload) => {
    setPatientFormLoading(true);
    setPatientFormError('');
    try {
      const created = await createPatientApi({
        ...payload,
        categorieTarifaire: payload.categorieTarifaire || DEFAULT_CATEGORIE_TARIFAIRE,
      });
      setPatientModalOpen(false);
      selectPatient(created);
      showSuccess('Patient enregistré et sélectionné.');
    } catch (err) {
      setPatientFormError(err.message || 'Enregistrement du patient impossible.');
    } finally {
      setPatientFormLoading(false);
    }
  };

  if (loading) {
    return (
      <Box sx={{ p: { xs: 2, md: 3 } }}>
        <Typography level="body-sm">Chargement…</Typography>
      </Box>
    );
  }

  return (
    <Box sx={{ p: { xs: 2, md: 3 } }}>
      <Stack spacing={2.5}>
        <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={1.5}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <IconButton variant="plain" onClick={() => navigate(ROUTES.FACTURATION.FACTURES)}>
              <ArrowLeft size={18} />
            </IconButton>
            <Receipt size={24} color={LOTRU_PRIMARY[600]} />
            <Box>
              <Stack direction="row" spacing={1} alignItems="center">
                <Typography level="h2" sx={{ fontWeight: 700 }}>
                  {isNew ? 'Nouvelle facture' : facture?.numero || 'Facture'}
                </Typography>
                {facture?.statut ? (
                  <Chip size="sm" variant="soft" color={FACTURE_STATUT_COLORS[facture.statut] ?? 'neutral'}>
                    {FACTURE_STATUT_LABELS[facture.statut] ?? facture.statut}
                  </Chip>
                ) : (
                  <Chip size="sm" variant="soft">Brouillon</Chip>
                )}
              </Stack>
              <Typography level="body-md" sx={{ color: 'neutral.500' }}>
                Tarif standard (A) par défaut — les autres catégories reprennent les colonnes de la grille.
              </Typography>
            </Box>
          </Stack>
          <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
            {canDelete && !isNew && facture?.statut === 'BROUILLON' ? (
              <Button variant="outlined" color="danger" startDecorator={<Trash2 size={16} />} onClick={() => setConfirmAction('delete')}>
                Supprimer
              </Button>
            ) : null}
            {canValider && !readOnly ? (
              <Button variant="outlined" startDecorator={<Check size={16} />} disabled={saving} onClick={() => setConfirmAction('valider')}>
                Valider
              </Button>
            ) : null}
            {canSave ? (
              <Button startDecorator={<Plus size={16} />} disabled={saving} onClick={handleSave}>
                Enregistrer
              </Button>
            ) : null}
          </Stack>
        </Stack>

        {error ? (
          <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
            {error}
          </Typography>
        ) : null}

        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
          <Stack spacing={2}>
            <Typography level="title-md">Patient</Typography>
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} alignItems={{ sm: 'flex-end' }}>
              <FormControl sx={{ flex: 1 }} required>
                <FormLabel>Rechercher le patient</FormLabel>
                <PatientSearchAutocomplete
                  value={patient}
                  disabled={readOnly}
                  onSelect={selectPatient}
                />
              </FormControl>
              {canCreatePatient && !readOnly ? (
                <Button variant="outlined" startDecorator={<UserPlus size={16} />} onClick={() => {
                  setPatientFormError('');
                  setPatientModalOpen(true);
                }}>
                  Nouveau patient
                </Button>
              ) : null}
            </Stack>
            {patient ? (
              <Typography level="body-sm" sx={{ color: 'neutral.600' }}>
                {patientLabel(patient)}
                {patient.numDossier ? ` · DPI ${patient.numDossier}` : ''}
                {patient.codeUkv ? ` · UKV ${patient.codeUkv}` : ''}
                {patient.telephone ? ` · ${patient.telephone}` : ''}
              </Typography>
            ) : (
              <FormHelperText>Si le patient n’existe pas encore, ajoutez-le avant de facturer.</FormHelperText>
            )}

            <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
              <FormControl sx={{ minWidth: 180 }}>
                <FormLabel>Date</FormLabel>
                <Input
                  type="date"
                  value={form.dateFacture}
                  disabled={readOnly}
                  onChange={(event) => setForm((current) => ({ ...current, dateFacture: event.target.value }))}
                />
              </FormControl>
              <FormControl sx={{ flex: 1 }} required>
                <FormLabel>Catégorie tarifaire</FormLabel>
                <Select
                  value={form.categorieTarifaire}
                  disabled={readOnly}
                  onChange={(_, value) => setCategorie(value)}
                >
                  {CATEGORIES_TARIFAIRES.map((item) => (
                    <Option key={item.value} value={item.value}>{item.label}</Option>
                  ))}
                </Select>
              </FormControl>
              {requiresStructure ? (
                <FormControl sx={{ flex: 1 }} required>
                  <FormLabel>Structure</FormLabel>
                  <Select
                    value={form.structureId || null}
                    disabled={readOnly}
                    placeholder="Mutuelle, assurance, ONG…"
                    onChange={(_, value) => setForm((current) => ({ ...current, structureId: value ?? '' }))}
                  >
                    {filteredStructures.map((item) => (
                      <Option key={item.id} value={String(item.id)}>
                        {item.libelle} ({item.type})
                      </Option>
                    ))}
                  </Select>
                </FormControl>
              ) : null}
              {requiresStructure ? (
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>N° affiliation</FormLabel>
                  <Input
                    value={form.numeroAffiliation}
                    disabled={readOnly}
                    onChange={(event) => setForm((current) => ({ ...current, numeroAffiliation: event.target.value }))}
                  />
                </FormControl>
              ) : null}
            </Stack>
          </Stack>
        </Card>

        <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2 }}>
          <Stack spacing={2}>
            <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" spacing={1}>
              <Box>
                <Typography level="title-md">Actes</Typography>
                <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
                  Prix issus de la grille pour {CATEGORIE_TARIFAIRE_LABELS[form.categorieTarifaire] ?? form.categorieTarifaire}.
                </Typography>
              </Box>
              <Typography level="title-lg">{formatFc(montantTotal)}</Typography>
            </Stack>
            {!readOnly ? (
              <ActeSearchAutocomplete
                categorieTarifaire={form.categorieTarifaire}
                onSelect={addActe}
              />
            ) : null}
            <Table sx={{ minWidth: canRemise || form.lignes.some((ligne) => Number(ligne.remiseMontant) > 0) ? 1080 : 720 }}>
              <thead>
                <tr>
                  <th>Acte</th>
                  <th>Service</th>
                  <th style={{ width: 90 }}>Qté</th>
                  <th>P.U.</th>
                  {(canRemise || form.lignes.some((ligne) => Number(ligne.remiseMontant) > 0)) ? <th style={{ minWidth: 220 }}>Remise</th> : null}
                  <th>Total</th>
                  {!readOnly ? <th style={{ width: 48 }} /> : null}
                </tr>
              </thead>
              <tbody>
                {form.lignes.length === 0 ? (
                  <tr>
                    <td colSpan={readOnly ? (canRemise ? 6 : 5) : (canRemise ? 7 : 6)}>
                      <Typography level="body-sm" sx={{ color: 'neutral.500', py: 1 }}>
                        Aucun acte. Recherchez un libellé de la grille pour l’ajouter.
                      </Typography>
                    </td>
                  </tr>
                ) : form.lignes.map((ligne, index) => (
                  <tr key={`${ligne.acteId}-${index}`}>
                    <td>
                      <Typography level="body-sm" sx={{ fontWeight: 600 }}>{ligne.codeActe}</Typography>
                      <Typography level="body-xs" sx={{ color: 'neutral.500' }}>{ligne.libelle}</Typography>
                    </td>
                    <td>{ligne.serviceGrille || '—'}</td>
                    <td>
                      {readOnly ? ligne.quantite : (
                        <Input
                          type="number"
                          value={ligne.quantite}
                          slotProps={{ input: { min: 1 } }}
                          onChange={(event) => setQuantite(index, event.target.value)}
                        />
                      )}
                    </td>
                    <td>{formatFc(ligne.tarifUnitaire)}</td>
                    {(canRemise || Number(ligne.remiseMontant) > 0 || form.lignes.some((item) => Number(item.remiseMontant) > 0)) ? (
                      <td>
                        <RemiseFields
                          compact
                          type={ligne.remiseType}
                          valeur={ligne.remiseValeur}
                          montant={ligne.remiseMontant}
                          disabled={!canEditRemise}
                          onChange={(type, valeur) => setLigneRemise(index, type, valeur)}
                        />
                      </td>
                    ) : null}
                    <td>
                      {Number(ligne.remiseMontant) > 0 ? (
                        <Stack>
                          <Typography level="body-xs" sx={{ textDecoration: 'line-through', color: 'neutral.500' }}>
                            {formatFc(ligne.tarifBrut)}
                          </Typography>
                          <Typography level="body-sm">{formatFc(ligne.tarifTotal)}</Typography>
                        </Stack>
                      ) : formatFc(ligne.tarifTotal)}
                    </td>
                    {!readOnly ? (
                      <td>
                        <IconButton size="sm" variant="plain" color="danger" onClick={() => removeLigne(index)}>
                          <Trash2 size={16} />
                        </IconButton>
                      </td>
                    ) : null}
                  </tr>
                ))}
              </tbody>
            </Table>
            {(canRemise || Number(form.remiseValeur) > 0 || Number(totals.remiseGlobale) > 0) ? (
              <Card variant="soft" sx={{ borderRadius: 'md', p: 2 }}>
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={2} justifyContent="space-between" alignItems={{ md: 'flex-end' }}>
                  <Box sx={{ flex: 1 }}>
                    <RemiseFields
                      label="Remise globale"
                      type={form.remiseType}
                      valeur={form.remiseValeur}
                      montant={totals.remiseGlobale}
                      disabled={!canEditRemise}
                      onChange={setRemiseGlobale}
                    />
                    {!canRemise ? (
                      <Typography level="body-xs" sx={{ color: 'neutral.500', mt: 0.5 }}>
                        La remise nécessite la permission dédiée.
                      </Typography>
                    ) : null}
                  </Box>
                  <Stack spacing={0.5} sx={{ minWidth: 220 }}>
                    <Stack direction="row" justifyContent="space-between">
                      <Typography level="body-sm">Sous-total</Typography>
                      <Typography level="body-sm">{formatFc(totals.montantBrut)}</Typography>
                    </Stack>
                    {totals.remiseLignes > 0 ? (
                      <Stack direction="row" justifyContent="space-between">
                        <Typography level="body-sm">Remises actes</Typography>
                        <Typography level="body-sm" color="warning">− {formatFc(totals.remiseLignes)}</Typography>
                      </Stack>
                    ) : null}
                    {totals.remiseGlobale > 0 ? (
                      <Stack direction="row" justifyContent="space-between">
                        <Typography level="body-sm">
                          Remise globale
                          {formatRemiseLabel(form.remiseType, form.remiseValeur, totals.remiseGlobale)
                            && form.remiseType === 'POURCENTAGE' ? ` (${form.remiseValeur} %)` : ''}
                        </Typography>
                        <Typography level="body-sm" color="warning">− {formatFc(totals.remiseGlobale)}</Typography>
                      </Stack>
                    ) : null}
                    <Stack direction="row" justifyContent="space-between">
                      <Typography level="title-md">Net à payer</Typography>
                      <Typography level="title-md">{formatFc(montantTotal)}</Typography>
                    </Stack>
                  </Stack>
                </Stack>
              </Card>
            ) : (
              <Stack direction="row" justifyContent="flex-end">
                <Typography level="title-md">Net à payer : {formatFc(montantTotal)}</Typography>
              </Stack>
            )}
            <FormControl>
              <FormLabel>Notes</FormLabel>
              <Input
                value={form.notes}
                disabled={readOnly}
                onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))}
              />
            </FormControl>
          </Stack>
        </Card>
      </Stack>

      <PatientFormModal
        open={patientModalOpen}
        mode="create"
        initialValues={{ ...EMPTY_PATIENT_FORM, categorieTarifaire: DEFAULT_CATEGORIE_TARIFAIRE }}
        loading={patientFormLoading}
        error={patientFormError}
        onClose={() => setPatientModalOpen(false)}
        onSubmit={handleCreatePatient}
        structures={patientMeta.structures}
        filieres={patientMeta.filieres}
        organisations={patientMeta.organisations}
      />

      <ConfirmModal
        open={confirmAction === 'valider'}
        title="Valider la facture"
        message="La facture ne pourra plus être modifiée après validation."
        confirmLabel="Valider"
        loading={confirmLoading}
        onClose={() => setConfirmAction(null)}
        onConfirm={handleValider}
      />
      <ConfirmModal
        open={confirmAction === 'delete'}
        title="Supprimer la facture"
        message="Supprimer ce brouillon ?"
        confirmLabel="Supprimer"
        loading={confirmLoading}
        onClose={() => setConfirmAction(null)}
        onConfirm={handleDelete}
      />
    </Box>
  );
}
