import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  Box, Button, Card, Chip, FormControl, FormHelperText, FormLabel, IconButton, Input, Option, Select, Stack, Typography,
} from '@mui/joy';
import { ArrowLeft, Check, PackagePlus, Plus, Trash2 } from 'lucide-react';
import ConfirmModal from '../../../components/ui/ConfirmModal.jsx';
import { PERMISSIONS } from '../../../constants/permissions.js';
import { ROUTES } from '../../../constants/routes.js';
import { usePermissions } from '../../../hooks/usePermissions.js';
import { useToast } from '../../../hooks/useToast.js';
import OfflineHint from '../../../offline/OfflineHint.jsx';
import { LOTRU_PRIMARY } from '../../../theme/lotruPalette.js';
import { fetchFournisseursActifsApi } from '../fournisseurs/fournisseursApi.js';
import { fetchMedicamentsActifsApi } from '../medicaments/medicamentsApi.js';
import MedicamentAutocomplete from '../shared/MedicamentAutocomplete.jsx';
import { todayIso } from '../shared/format.js';
import {
  EMPTY_RECEPTION_LIGNE,
  RECEPTION_STATUT_COLORS,
  RECEPTION_STATUT_LABELS,
  emptyReceptionForm,
} from './receptionConstants.js';
import {
  createReceptionApi,
  deleteReceptionApi,
  fetchReceptionApi,
  updateReceptionApi,
  validerReceptionApi,
} from './receptionsApi.js';

function toPayload(form) {
  return {
    fournisseurId: Number(form.fournisseurId),
    dateReception: form.dateReception,
    referenceExterne: form.referenceExterne.trim() || null,
    lignes: form.lignes.map((ligne) => ({
      medicamentId: Number(ligne.medicamentId),
      numeroLot: String(ligne.numeroLot).trim().toUpperCase(),
      datePeremption: ligne.datePeremption,
      quantite: Number(ligne.quantite),
      prixAchatUnitaire: String(ligne.prixAchatUnitaire).trim(),
      prixVente: String(ligne.prixVente ?? '').trim() || null,
    })),
  };
}

function mergeMedicaments(list, extras = []) {
  const map = new Map(list.map((item) => [String(item.id), item]));
  extras.forEach((item) => {
    if (item?.id && !map.has(String(item.id))) {
      map.set(String(item.id), item);
    }
  });
  return [...map.values()];
}

export default function ReceptionFormPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const { showSuccess, showError } = useToast();
  const isNew = !id;
  const canCreate = hasPermission(PERMISSIONS.PHARMACIE.RECEPTION_CREATE);
  const canUpdate = hasPermission(PERMISSIONS.PHARMACIE.RECEPTION_UPDATE);
  const canDelete = hasPermission(PERMISSIONS.PHARMACIE.RECEPTION_DELETE);
  const canValider = hasPermission(PERMISSIONS.PHARMACIE.RECEPTION_VALIDER);

  const [form, setForm] = useState(() => emptyReceptionForm(todayIso()));
  const [reception, setReception] = useState(null);
  const [fournisseurs, setFournisseurs] = useState([]);
  const [medicaments, setMedicaments] = useState([]);
  const [loading, setLoading] = useState(!isNew);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [confirmAction, setConfirmAction] = useState(null);
  const [confirmLoading, setConfirmLoading] = useState(false);

  const readOnly = reception?.statut === 'VALIDEE';
  const canSave = isNew ? canCreate : canUpdate && !readOnly;

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const [fournisseurList, medicamentList] = await Promise.all([
          fetchFournisseursActifsApi(),
          fetchMedicamentsActifsApi(),
        ]);
        if (!cancelled) {
          setFournisseurs(fournisseurList);
          setMedicaments(medicamentList);
        }
      } catch (err) {
        if (!cancelled) setError(err.message || 'Référentiels indisponibles.');
      }
    })();
    return () => { cancelled = true; };
  }, []);

  useEffect(() => {
    if (isNew) return undefined;
    let cancelled = false;
    (async () => {
      setLoading(true);
      setError('');
      try {
        const data = await fetchReceptionApi(id);
        if (cancelled) return;
        setReception(data);
        setForm({
          fournisseurId: data.fournisseurId ? String(data.fournisseurId) : '',
          dateReception: data.dateReception ?? todayIso(),
          referenceExterne: data.referenceExterne ?? '',
          lignes: (data.lignes ?? []).length
            ? data.lignes.map((ligne) => ({
              medicamentId: ligne.medicamentId ? String(ligne.medicamentId) : '',
              numeroLot: ligne.numeroLot ?? '',
              datePeremption: ligne.datePeremption ?? '',
              quantite: ligne.quantite ?? '',
              prixAchatUnitaire: ligne.prixAchatUnitaire ?? '',
              prixVente: ligne.medicament?.prixVente ?? ligne.prixVente ?? '',
            }))
            : [{ ...EMPTY_RECEPTION_LIGNE }],
        });
        setMedicaments((current) => mergeMedicaments(current, (data.lignes ?? []).map((ligne) => ligne.medicament)));
      } catch (err) {
        if (!cancelled) setError(err.message || 'Réception introuvable.');
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true; };
  }, [id, isNew]);

  const setField = (field, value) => setForm((current) => ({ ...current, [field]: value }));

  const setLigne = (index, field, value) => {
    setForm((current) => ({
      ...current,
      lignes: current.lignes.map((ligne, i) => (i === index ? { ...ligne, [field]: value } : ligne)),
    }));
  };

  const selectMedicament = (index, medicament) => {
    setForm((current) => ({
      ...current,
      lignes: current.lignes.map((ligne, i) => (
        i === index
          ? {
            ...ligne,
            medicamentId: medicament ? String(medicament.id) : '',
            prixVente: medicament?.prixVente ?? '',
          }
          : ligne
      )),
    }));
  };

  const setPrixVenteLigne = (index, value) => {
    const medicamentId = form.lignes[index]?.medicamentId;
    setForm((current) => ({
      ...current,
      lignes: current.lignes.map((ligne, i) => {
        if (i === index) return { ...ligne, prixVente: value };
        if (medicamentId && ligne.medicamentId === medicamentId) return { ...ligne, prixVente: value };
        return ligne;
      }),
    }));
    if (medicamentId) {
      setMedicaments((current) => current.map((item) => (
        String(item.id) === String(medicamentId) ? { ...item, prixVente: value } : item
      )));
    }
  };

  const addLigne = () => setForm((current) => ({ ...current, lignes: [...current.lignes, { ...EMPTY_RECEPTION_LIGNE }] }));

  const removeLigne = (index) => {
    setForm((current) => ({
      ...current,
      lignes: current.lignes.length === 1 ? current.lignes : current.lignes.filter((_, i) => i !== index),
    }));
  };

  const handleSave = async () => {
    setSaving(true);
    setError('');
    try {
      const payload = toPayload(form);
      if (isNew) {
        const created = await createReceptionApi(payload);
        showSuccess('Brouillon de réception enregistré.');
        navigate(ROUTES.PHARMACIE.RECEPTION_DETAIL.replace(':id', String(created.id)), { replace: true });
      } else {
        const updated = await updateReceptionApi(id, payload);
        setReception(updated);
        showSuccess('Réception mise à jour.');
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
      if (canSave) {
        const updated = await updateReceptionApi(id, toPayload(form));
        setReception(updated);
      }
      const validated = await validerReceptionApi(id);
      setReception(validated);
      setConfirmAction(null);
      showSuccess('Réception validée, stock mis à jour.');
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
      await deleteReceptionApi(id);
      setConfirmAction(null);
      showSuccess('Réception supprimée.');
      navigate(ROUTES.PHARMACIE.RECEPTIONS);
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
          Réception hors-ligne : le brouillon reste un brouillon. Une fois validée, le stock, les lots et le journal des mouvements se mettent à jour tout de suite. Le serveur confirme à la synchro.
        </OfflineHint>
        <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'flex-start' }} spacing={1.5}>
          <Stack spacing={1}>
            <Button variant="plain" color="neutral" startDecorator={<ArrowLeft size={16} />} onClick={() => navigate(ROUTES.PHARMACIE.RECEPTIONS)} sx={{ alignSelf: 'flex-start', px: 0 }}>
              Retour aux réceptions
            </Button>
            <Stack direction="row" spacing={1.5} alignItems="center">
              <PackagePlus size={24} color={LOTRU_PRIMARY[600]} />
              <Box>
                <Typography level="h2" sx={{ fontWeight: 700 }}>
                  {isNew ? 'Nouvelle réception' : reception?.numero ?? 'Réception'}
                </Typography>
                <Typography level="body-md" sx={{ color: 'neutral.500' }}>
                  Prix d’achat sur le lot. Le prix de vente catalogue s’applique à tous les lots, y compris les anciens.
                </Typography>
              </Box>
              {reception?.statut ? (
                <Chip size="sm" variant="soft" color={RECEPTION_STATUT_COLORS[reception.statut] ?? 'neutral'}>
                  {RECEPTION_STATUT_LABELS[reception.statut] ?? reception.statut}
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
            {canSave ? (
              <Button onClick={handleSave} loading={saving}>Enregistrer</Button>
            ) : null}
            {canValider && !isNew && !readOnly ? (
              <Button color="success" startDecorator={<Check size={16} />} onClick={() => setConfirmAction('valider')} loading={saving}>
                Valider l’entrée
              </Button>
            ) : null}
          </Stack>
        </Stack>

        {error ? (
          <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
            {error}
          </Typography>
        ) : null}

        {loading ? (
          <Typography level="body-sm">Chargement…</Typography>
        ) : (
          <>
            <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5 }}>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Fournisseur</FormLabel>
                  <Select
                    value={form.fournisseurId || null}
                    onChange={(_, value) => setField('fournisseurId', value ?? '')}
                    disabled={readOnly || saving}
                    placeholder="Choisir un fournisseur"
                  >
                    {fournisseurs.map((item) => (
                      <Option key={item.id} value={String(item.id)}>{item.code} — {item.libelle}</Option>
                    ))}
                  </Select>
                </FormControl>
                <FormControl required sx={{ minWidth: 180 }}>
                  <FormLabel>Date de réception</FormLabel>
                  <Input
                    type="date"
                    value={form.dateReception}
                    onChange={(e) => setField('dateReception', e.target.value)}
                    disabled={readOnly || saving}
                  />
                </FormControl>
                <FormControl sx={{ flex: 1 }}>
                  <FormLabel>Référence externe</FormLabel>
                  <Input
                    value={form.referenceExterne}
                    onChange={(e) => setField('referenceExterne', e.target.value)}
                    disabled={readOnly || saving}
                    placeholder="BL / facture fournisseur"
                  />
                </FormControl>
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
                {form.lignes.map((ligne, index) => (
                  <Box key={`ligne-${index}`} sx={{ p: 1.5, borderRadius: 'md', border: '1px solid', borderColor: 'divider' }}>
                    <Stack spacing={1.5}>
                      <Stack direction="row" spacing={1} alignItems="flex-start">
                        <FormControl required sx={{ flex: 1 }}>
                          <FormLabel>Médicament</FormLabel>
                          <MedicamentAutocomplete
                            options={medicaments}
                            valueId={ligne.medicamentId}
                            disabled={readOnly || saving}
                            onSelect={(medicament) => selectMedicament(index, medicament)}
                          />
                        </FormControl>
                        {!readOnly ? (
                          <IconButton variant="plain" color="danger" onClick={() => removeLigne(index)} disabled={saving || form.lignes.length === 1} sx={{ mt: 3 }}>
                            <Trash2 size={16} />
                          </IconButton>
                        ) : null}
                      </Stack>
                      <Stack direction={{ xs: 'column', lg: 'row' }} spacing={1.5}>
                        <FormControl required sx={{ flex: 0.8 }}>
                          <FormLabel>N° lot</FormLabel>
                          <Input
                            value={ligne.numeroLot}
                            onChange={(e) => setLigne(index, 'numeroLot', e.target.value.toUpperCase())}
                            disabled={readOnly || saving}
                          />
                        </FormControl>
                        <FormControl required sx={{ flex: 0.8 }}>
                          <FormLabel>Péremption</FormLabel>
                          <Input
                            type="date"
                            value={ligne.datePeremption}
                            onChange={(e) => setLigne(index, 'datePeremption', e.target.value)}
                            disabled={readOnly || saving}
                          />
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
                        <FormControl required sx={{ flex: 0.8 }}>
                          <FormLabel>Prix d’achat</FormLabel>
                          <Input
                            value={ligne.prixAchatUnitaire}
                            onChange={(e) => setLigne(index, 'prixAchatUnitaire', e.target.value)}
                            disabled={readOnly || saving}
                            placeholder="0"
                          />
                        </FormControl>
                        <FormControl required sx={{ flex: 0.9 }}>
                          <FormLabel>Prix de vente</FormLabel>
                          <Input
                            value={ligne.prixVente}
                            onChange={(e) => setPrixVenteLigne(index, e.target.value)}
                            disabled={readOnly || saving || !ligne.medicamentId}
                            placeholder="Catalogue"
                          />
                          <FormHelperText>
                            Tous les lots de ce médicament seront vendus à ce tarif.
                          </FormHelperText>
                        </FormControl>
                      </Stack>
                    </Stack>
                  </Box>
                ))}
              </Stack>
            </Card>
          </>
        )}
      </Stack>
      <ConfirmModal
        open={confirmAction === 'valider'}
        title="Valider la réception"
        message="Les lots entreront en stock. Cette action est irréversible."
        confirmLabel="Valider l’entrée"
        color="success"
        loading={confirmLoading}
        onClose={() => setConfirmAction(null)}
        onConfirm={handleValider}
      />
      <ConfirmModal
        open={confirmAction === 'delete'}
        title="Supprimer le brouillon"
        message="Supprimer ce brouillon de réception ?"
        confirmLabel="Supprimer"
        loading={confirmLoading}
        onClose={() => setConfirmAction(null)}
        onConfirm={handleDelete}
      />
    </Box>
  );
}
