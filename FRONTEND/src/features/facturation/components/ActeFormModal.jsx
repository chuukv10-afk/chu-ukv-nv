import { useEffect, useState } from 'react';
import {
  Box, Button, Checkbox, FormControl, FormHelperText, FormLabel, Input, Modal, ModalDialog, Option, Select, Stack, Typography,
} from '@mui/joy';
import { Wallet } from 'lucide-react';
import {
  ACTE_STATUTS,
  formatIndiceTarif,
  indicesTarifActe,
  montantsTarifEgaux,
  proposerTarifsActe,
  SERVICE_GRILLE_AUTRE,
  serviceGrilleImagerie,
} from '../facturationConstants.js';

const MODAL_SX = {
  borderRadius: 'xl',
  maxWidth: 720,
  width: '100%',
  p: 0,
  overflow: 'hidden',
  boxShadow: 'lg',
  maxHeight: 'min(90vh, 860px)',
  display: 'flex',
  flexDirection: 'column',
};

const CATEGORIES_DERIVEES = ['A0', 'A1', 'B', 'C'];

function tarifValue(value) {
  if (value === null || value === undefined || value === '') return '0';
  return String(value);
}

function champTarif(code) {
  return code === 'A' ? 'tarifA' : `tarif${code}`;
}

function saisisDepuisValeurs(values, exceptionConcurrence) {
  const proposed = proposerTarifsActe(values.tarifA, exceptionConcurrence);
  return {
    A0: !montantsTarifEgaux(values.tarifA0, proposed.tarifA0),
    A1: !montantsTarifEgaux(values.tarifA1, proposed.tarifA1),
    B: !montantsTarifEgaux(values.tarifB, proposed.tarifB),
    C: !montantsTarifEgaux(values.tarifC, proposed.tarifC),
  };
}

function exceptionDepuisValeurs(values) {
  if (montantsTarifEgaux(values.tarifA, 0)) {
    return serviceGrilleImagerie(values.serviceGrille);
  }
  const standard = proposerTarifsActe(values.tarifA, false);
  const exception = proposerTarifsActe(values.tarifA, true);
  const suit = (proposal) => montantsTarifEgaux(values.tarifB, proposal.tarifB)
    && montantsTarifEgaux(values.tarifC, proposal.tarifC);
  if (suit(exception) && !suit(standard)) return true;
  return false;
}

function appliquerProposition(current, tarifA, exceptionConcurrence, saisis) {
  const proposed = proposerTarifsActe(tarifA, exceptionConcurrence);
  return {
    ...current,
    tarifA,
    tarifA0: saisis.A0 ? current.tarifA0 : proposed.tarifA0,
    tarifA1: saisis.A1 ? current.tarifA1 : proposed.tarifA1,
    tarifB: saisis.B ? current.tarifB : proposed.tarifB,
    tarifC: saisis.C ? current.tarifC : proposed.tarifC,
  };
}

export default function ActeFormModal({
  open,
  mode = 'create',
  initialValues,
  serviceGrilles = [],
  loading = false,
  error = '',
  onClose,
  onSubmit,
}) {
  const [form, setForm] = useState(initialValues);
  const [saisis, setSaisis] = useState({ A0: false, A1: false, B: false, C: false });
  const [exception, setException] = useState(false);
  const [exceptionChoisie, setExceptionChoisie] = useState(false);
  const isEdit = mode === 'edit';
  const indices = indicesTarifActe(exception);

  useEffect(() => {
    if (!open) return;
    const exceptionInit = exceptionDepuisValeurs(initialValues);
    setForm(isEdit ? initialValues : appliquerProposition(initialValues, initialValues.tarifA, exceptionInit, {
      A0: false, A1: false, B: false, C: false,
    }));
    setException(exceptionInit);
    setExceptionChoisie(isEdit);
    setSaisis(isEdit ? saisisDepuisValeurs(initialValues, exceptionInit) : { A0: false, A1: false, B: false, C: false });
  }, [open, initialValues, isEdit]);

  const handleChange = (field, value) => setForm((current) => ({ ...current, [field]: value }));

  const handleTarifStandard = (value) => {
    setForm((current) => appliquerProposition(current, value, exception, saisis));
  };

  const handleTarifDerive = (code, value) => {
    setSaisis((current) => ({ ...current, [code]: true }));
    setForm((current) => ({ ...current, [champTarif(code)]: value }));
  };

  const reprendreProposition = (code) => {
    const proposed = proposerTarifsActe(form.tarifA, exception);
    setSaisis((current) => ({ ...current, [code]: false }));
    setForm((current) => ({ ...current, [champTarif(code)]: proposed[champTarif(code)] }));
  };

  const handleException = (checked) => {
    setException(checked);
    setExceptionChoisie(true);
    setForm((current) => appliquerProposition(current, current.tarifA, checked, saisis));
  };

  const handleService = (value) => {
    const nextException = exceptionChoisie ? exception : serviceGrilleImagerie(value);
    if (!exceptionChoisie) setException(nextException);
    setForm((current) => appliquerProposition(
      { ...current, serviceGrille: value },
      current.tarifA,
      nextException,
      saisis,
    ));
  };

  const handleSubmit = (event) => {
    event.preventDefault();
    onSubmit({
      serviceGrille: form.serviceGrille.trim(),
      sousCategorie: form.sousCategorie.trim() || null,
      libelle: form.libelle.trim(),
      tarifA0: tarifValue(form.tarifA0),
      tarifA1: tarifValue(form.tarifA1),
      tarifA: tarifValue(form.tarifA),
      tarifB: tarifValue(form.tarifB),
      tarifC: tarifValue(form.tarifC),
      statut: form.statut,
    });
  };

  return (
    <Modal open={open} onClose={onClose}>
      <ModalDialog variant="outlined" sx={MODAL_SX}>
        <Box sx={{ px: 3, py: 2.5, borderBottom: '1px solid', borderColor: 'divider', flexShrink: 0 }}>
          <Stack direction="row" spacing={1.5} alignItems="center">
            <Box sx={{
              width: 40, height: 40, borderRadius: 'md', bgcolor: 'primary.50', color: 'primary.600',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
            }}
            >
              <Wallet size={20} />
            </Box>
            <Typography level="title-lg" sx={{ fontWeight: 700 }}>
              {isEdit ? 'Modifier l\'acte' : 'Nouvel acte tarifaire'}
            </Typography>
          </Stack>
        </Box>
        <Box component="form" onSubmit={handleSubmit} sx={{ display: 'flex', flexDirection: 'column', minHeight: 0 }}>
          <Box sx={{ p: 3, overflow: 'auto' }}>
            <Stack spacing={2}>
              {error ? (
                <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
                  {error}
                </Typography>
              ) : null}

              {isEdit ? (
                <FormControl>
                  <FormLabel>Code</FormLabel>
                  <Input value={form.code} disabled readOnly />
                </FormControl>
              ) : null}

              <FormControl required>
                <FormLabel>Service</FormLabel>
                <Select
                  value={form.serviceGrille || null}
                  onChange={(_, value) => handleService(value ?? '')}
                  disabled={loading}
                  placeholder="Choisir un service"
                >
                  {Array.from(new Set([
                    ...serviceGrilles,
                    SERVICE_GRILLE_AUTRE,
                    ...(form.serviceGrille ? [form.serviceGrille] : []),
                  ])).sort((left, right) => left.localeCompare(right, 'fr')).map((service) => (
                    <Option key={service} value={service}>{service}</Option>
                  ))}
                </Select>
              </FormControl>

              <FormControl required>
                <FormLabel>Libellé de l'acte</FormLabel>
                <Input
                  value={form.libelle}
                  onChange={(e) => handleChange('libelle', e.target.value)}
                  disabled={loading}
                  slotProps={{ input: { maxLength: 180 } }}
                  placeholder="Ex. Consultation médicale Jour"
                />
              </FormControl>

              <FormControl>
                <FormLabel>Sous-catégorie</FormLabel>
                <Input
                  value={form.sousCategorie}
                  onChange={(e) => handleChange('sousCategorie', e.target.value)}
                  disabled={loading}
                  slotProps={{ input: { maxLength: 80 } }}
                />
              </FormControl>

              <FormControl required>
                <FormLabel>Tarif A — standard (FC)</FormLabel>
                <Input
                  type="number"
                  value={form.tarifA}
                  onChange={(event) => handleTarifStandard(event.target.value)}
                  disabled={loading}
                  slotProps={{ input: { min: 0, step: '0.01' } }}
                />
                <FormHelperText>
                  Les autres catégories se proposent à partir de ce montant. Chaque proposition reste modifiable.
                </FormHelperText>
              </FormControl>

              <Checkbox
                label="Exception concurrence (B × 1,2 et C × 1,4)"
                checked={exception}
                disabled={loading}
                onChange={(event) => handleException(event.target.checked)}
              />

              <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', sm: '1fr 1fr' }, gap: 1.5 }}>
                {CATEGORIES_DERIVEES.map((code) => {
                  const field = champTarif(code);
                  const saisi = saisis[code];
                  return (
                    <FormControl key={code} sx={{ flex: 1 }}>
                      <FormLabel>Tarif {code} (FC)</FormLabel>
                      <Input
                        type="number"
                        value={form[field]}
                        onChange={(event) => handleTarifDerive(code, event.target.value)}
                        disabled={loading}
                        slotProps={{ input: { min: 0, step: '0.01' } }}
                      />
                      <FormHelperText component="div">
                        {saisi ? (
                          <Button
                            size="sm"
                            variant="plain"
                            disabled={loading}
                            onClick={() => reprendreProposition(code)}
                            sx={{ minHeight: 0, px: 0, py: 0 }}
                          >
                            Reprendre {formatIndiceTarif(indices[code])}
                          </Button>
                        ) : (
                          `Proposé ${formatIndiceTarif(indices[code])}`
                        )}
                      </FormHelperText>
                    </FormControl>
                  );
                })}
              </Box>

              <FormControl required>
                <FormLabel>Statut</FormLabel>
                <Select
                  value={form.statut}
                  onChange={(_, value) => handleChange('statut', value ?? 'ACTIF')}
                  disabled={loading}
                >
                  {ACTE_STATUTS.map((item) => (
                    <Option key={item.value} value={item.value}>{item.label}</Option>
                  ))}
                </Select>
              </FormControl>
            </Stack>
          </Box>

          <Stack direction="row" spacing={1.5} justifyContent="flex-end" sx={{ px: 3, py: 2, borderTop: '1px solid', borderColor: 'divider', flexShrink: 0 }}>
            <Button variant="plain" color="neutral" onClick={onClose} disabled={loading}>Annuler</Button>
            <Button type="submit" loading={loading}>{isEdit ? 'Enregistrer' : 'Créer l\'acte'}</Button>
          </Stack>
        </Box>
      </ModalDialog>
    </Modal>
  );
}
