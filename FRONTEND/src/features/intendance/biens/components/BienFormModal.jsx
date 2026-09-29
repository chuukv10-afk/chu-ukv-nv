import { useEffect, useState } from 'react';
import {
  Box, Button, Checkbox, FormControl, FormLabel, Input, Modal, ModalDialog, Option, Select, Stack, Textarea, Typography,
} from '@mui/joy';
import Autocomplete from '@mui/joy/Autocomplete';
import AutocompleteOption from '@mui/joy/AutocompleteOption';
import { Archive } from 'lucide-react';
import { fetchLocauxActifsApi } from '../../locaux/locauxApi.js';
import { proposerCodeApi } from '../biensApi.js';
import { BIEN_ETATS, EMPTY_BIEN_FORM } from '../bienConstants.js';

function fold(value) {
  return String(value ?? '')
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .toLowerCase();
}

function matchesQuery(query, parts) {
  if (!query) return true;
  const needle = fold(query);
  return parts.some((part) => fold(part).includes(needle));
}

const LISTBOX_SLOT = {
  disablePortal: true,
  sx: { zIndex: 20, maxHeight: 240, overflow: 'auto' },
};

const MODAL_SX = {
  borderRadius: 'xl',
  maxWidth: 720,
  width: '100%',
  p: 0,
  overflow: 'hidden',
  boxShadow: 'lg',
  maxHeight: 'min(92vh, 900px)',
  display: 'flex',
  flexDirection: 'column',
};

export default function BienFormModal({
  open,
  mode = 'create',
  initialValues = EMPTY_BIEN_FORM,
  types = [],
  services = [],
  loading = false,
  error = '',
  onClose,
  onSubmit,
}) {
  const [form, setForm] = useState(initialValues);
  const [locaux, setLocaux] = useState([]);
  const [codeTouched, setCodeTouched] = useState(false);
  const [proposing, setProposing] = useState(false);
  const isEdit = mode === 'edit';
  const grouped = !isEdit && Number(form.copies) > 1;

  useEffect(() => {
    if (open) {
      setForm(initialValues);
      setCodeTouched(Boolean(initialValues.codeInventaire));
    }
  }, [open, initialValues]);

  useEffect(() => {
    if (!form.serviceId) {
      setLocaux([]);
      return;
    }
    fetchLocauxActifsApi(form.serviceId).then(setLocaux).catch(() => setLocaux([]));
  }, [form.serviceId]);

  const handleChange = (field, value) => setForm((current) => ({ ...current, [field]: value }));

  const propose = async () => {
    if (!form.serviceId || !form.typeId) return;
    setProposing(true);
    try {
      const result = await proposerCodeApi({
        serviceId: form.serviceId,
        typeId: form.typeId,
        count: grouped ? Number(form.copies) || 2 : 1,
      });
      if (grouped) {
        handleChange('codes', result.codes ?? []);
      } else {
        handleChange('codeInventaire', result.code ?? '');
      }
      setCodeTouched(false);
    } finally {
      setProposing(false);
    }
  };

  useEffect(() => {
    if (!open || isEdit || codeTouched || !form.serviceId || !form.typeId) return;
    propose();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, form.serviceId, form.typeId, form.copies]);

  const handleSubmit = (event) => {
    event.preventDefault();
    const payload = {
      typeId: Number(form.typeId),
      serviceId: Number(form.serviceId),
      localId: form.localId ? Number(form.localId) : null,
      precision: form.precision.trim() || null,
      marque: form.marque.trim() || null,
      modele: form.modele.trim() || null,
      numeroSerie: form.numeroSerie.trim() || null,
      complementLocalisation: form.complementLocalisation.trim() || null,
      etat: form.etat,
      dateAcquisition: form.dateAcquisition || null,
      observation: form.observation.trim() || null,
    };
    if (grouped) {
      onSubmit({
        ...payload,
        copies: Number(form.copies),
        codes: form.codes ?? [],
      });
      return;
    }
    onSubmit({
      ...payload,
      codeInventaire: form.codeInventaire.trim() || null,
    });
  };

  const selectedType = types.find((item) => String(item.id) === String(form.typeId));

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog variant="outlined" sx={MODAL_SX}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider', flexShrink: 0 }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{ width: 40, height: 40, borderRadius: 'md', bgcolor: 'primary.50', color: 'primary.600', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Archive size={20} />
            </Box>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>
              {isEdit ? 'Modifier le bien' : 'Enregistrer un bien'}
            </Typography>
          </Stack>
        </Box>
        <Box component="form" onSubmit={handleSubmit} sx={{ overflow: 'auto' }}>
          <Stack spacing={2} sx={{ p: 3 }}>
            {error ? <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>{error}</Typography> : null}

            {!isEdit ? (
              <FormControl>
                <FormLabel>Nombre de copies (création groupée)</FormLabel>
                <Input type="number" value={form.copies} onChange={(e) => handleChange('copies', e.target.value)} slotProps={{ input: { min: 1, max: 200 } }} disabled={loading} />
              </FormControl>
            ) : null}

            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
              <FormControl required sx={{ flex: 1 }}>
                <FormLabel>Type</FormLabel>
                <Autocomplete
                  options={types}
                  value={selectedType ?? null}
                  disabled={loading}
                  placeholder="Rechercher un type…"
                  noOptionsText="Aucun type"
                  openOnFocus
                  sx={{ width: '100%' }}
                  getOptionLabel={(option) => option?.libelle ?? ''}
                  isOptionEqualToValue={(option, selected) => String(option.id) === String(selected.id)}
                  filterOptions={(options, state) => options.filter((item) => matchesQuery(state.inputValue, [
                    item.libelle, item.code, item.famille?.code, item.famille?.libelle,
                  ]))}
                  onChange={(_, selected) => handleChange('typeId', selected ? String(selected.id) : '')}
                  slotProps={{ input: { autoComplete: 'off', required: true }, listbox: LISTBOX_SLOT }}
                  renderOption={(props, option) => (
                    <AutocompleteOption {...props} key={option.id}>
                      <Box>
                        <Typography level="title-sm">{option.libelle}</Typography>
                        <Typography level="body-xs" sx={{ color: 'neutral.500' }}>
                          {option.code}{option.famille ? ` · ${option.famille.code} — ${option.famille.libelle}` : ''}
                        </Typography>
                      </Box>
                    </AutocompleteOption>
                  )}
                />
              </FormControl>
              <FormControl required sx={{ flex: 1 }}>
                <FormLabel>Service</FormLabel>
                <Select value={form.serviceId === '' ? null : String(form.serviceId)} onChange={(_, value) => { handleChange('serviceId', value ?? ''); handleChange('localId', ''); }} disabled={loading || isEdit}>
                  {services.map((item) => <Option key={item.id} value={String(item.id)}>{item.code} — {item.libelle}</Option>)}
                </Select>
              </FormControl>
            </Stack>
            {selectedType ? (
              <Typography level="body-sm" sx={{ color: 'neutral.500' }}>Famille : {selectedType.famille?.code} — {selectedType.famille?.libelle}</Typography>
            ) : null}

            <FormControl>
              <FormLabel>Local (optionnel)</FormLabel>
              <Autocomplete
                options={locaux}
                value={locaux.find((item) => String(item.id) === String(form.localId)) ?? null}
                disabled={loading || !form.serviceId}
                placeholder={form.serviceId ? 'Rechercher un local…' : 'Choisissez d’abord un service'}
                noOptionsText="Aucun local"
                openOnFocus
                sx={{ width: '100%' }}
                getOptionLabel={(option) => option?.libelle ?? ''}
                isOptionEqualToValue={(option, selected) => String(option.id) === String(selected.id)}
                filterOptions={(options, state) => options.filter((item) => matchesQuery(state.inputValue, [item.libelle, item.code]))}
                onChange={(_, selected) => handleChange('localId', selected ? String(selected.id) : '')}
                slotProps={{ input: { autoComplete: 'off' }, listbox: LISTBOX_SLOT }}
                renderOption={(props, option) => (
                  <AutocompleteOption {...props} key={option.id}>
                    <Box>
                      <Typography level="title-sm">{option.libelle}</Typography>
                      <Typography level="body-xs" sx={{ color: 'neutral.500' }}>{option.code}</Typography>
                    </Box>
                  </AutocompleteOption>
                )}
              />
            </FormControl>

            {grouped ? (
              <Stack spacing={1}>
                <Stack direction="row" justifyContent="space-between" alignItems="center">
                  <Typography level="title-sm">Codes proposés ({form.codes?.length || 0})</Typography>
                  <Button size="sm" variant="outlined" onClick={propose} loading={proposing}>Reproposer</Button>
                </Stack>
                {(form.codes ?? []).map((code, index) => (
                  <Input
                    key={`${code}-${index}`}
                    value={code}
                    onChange={(e) => {
                      const next = [...(form.codes ?? [])];
                      next[index] = e.target.value.toUpperCase();
                      handleChange('codes', next);
                      setCodeTouched(true);
                    }}
                    disabled={loading}
                  />
                ))}
              </Stack>
            ) : (
              <Stack direction="row" spacing={1} alignItems="flex-end">
                <FormControl required sx={{ flex: 1 }}>
                  <FormLabel>Code inventaire</FormLabel>
                  <Input
                    value={form.codeInventaire}
                    onChange={(e) => { handleChange('codeInventaire', e.target.value.toUpperCase()); setCodeTouched(true); }}
                    disabled={loading}
                  />
                </FormControl>
                <Button variant="outlined" onClick={propose} loading={proposing} disabled={loading || !form.serviceId || !form.typeId}>Reproposer</Button>
              </Stack>
            )}

            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
              <FormControl sx={{ flex: 1 }}>
                <FormLabel>Marque</FormLabel>
                <Input value={form.marque} onChange={(e) => handleChange('marque', e.target.value)} disabled={loading} />
              </FormControl>
              <FormControl sx={{ flex: 1 }}>
                <FormLabel>Modèle</FormLabel>
                <Input value={form.modele} onChange={(e) => handleChange('modele', e.target.value)} disabled={loading} />
              </FormControl>
            </Stack>
            {!grouped ? (
              <FormControl>
                <FormLabel>N° de série fabricant</FormLabel>
                <Input value={form.numeroSerie} onChange={(e) => handleChange('numeroSerie', e.target.value)} disabled={loading} />
              </FormControl>
            ) : null}
            <FormControl>
              <FormLabel>Précision</FormLabel>
              <Input value={form.precision} onChange={(e) => handleChange('precision', e.target.value)} disabled={loading} placeholder="Ex. HP ProDesk secrétariat" />
            </FormControl>
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
              <FormControl sx={{ flex: 1 }}>
                <FormLabel>État</FormLabel>
                <Select value={form.etat} onChange={(_, value) => handleChange('etat', value ?? 'F')} disabled={loading}>
                  {BIEN_ETATS.filter((item) => item.value !== 'R').map((item) => (
                    <Option key={item.value} value={item.value}>{item.label}</Option>
                  ))}
                </Select>
              </FormControl>
              <FormControl sx={{ flex: 1 }}>
                <FormLabel>Date d&apos;acquisition</FormLabel>
                <Input type="date" value={form.dateAcquisition} onChange={(e) => handleChange('dateAcquisition', e.target.value)} disabled={loading} />
              </FormControl>
            </Stack>
            <FormControl>
              <FormLabel>Complément de localisation</FormLabel>
              <Input value={form.complementLocalisation} onChange={(e) => handleChange('complementLocalisation', e.target.value)} disabled={loading} />
            </FormControl>
            <FormControl>
              <FormLabel>Observation</FormLabel>
              <Textarea minRows={2} value={form.observation} onChange={(e) => handleChange('observation', e.target.value)} disabled={loading} />
            </FormControl>
            {!isEdit && grouped ? (
              <Checkbox checked readOnly label={`${form.copies} fiches seront créées, chacune avec un code unique.`} />
            ) : null}
          </Stack>
          <Stack direction="row" spacing={1.5} justifyContent="flex-end" sx={{ px: 3, py: 2, borderTop: '1px solid', borderColor: 'divider' }}>
            <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>Annuler</Button>
            <Button type="submit" loading={loading}>{isEdit ? 'Enregistrer' : grouped ? `Créer ${form.copies} fiches` : 'Créer'}</Button>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
