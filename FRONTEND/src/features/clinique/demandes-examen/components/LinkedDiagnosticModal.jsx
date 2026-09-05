import { useEffect, useRef, useState } from 'react';
import {
  Box,
  Button,
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
  Option,
  Select,
  Sheet,
  Stack,
  Textarea,
  Typography,
} from '@mui/joy';
import { Search } from 'lucide-react';
import { fetchMaladiesApi } from '../../maladies/maladiesApi.js';
import {
  DEFAULT_DIAGNOSTIC_FORM,
  DIAGNOSTIC_CERTITUDE_LABELS,
  DIAGNOSTIC_TYPE_LABELS,
} from '../../diagnostics/diagnosticConstants.js';
import { fetchDiagnosticMetaApi } from '../../diagnostics/diagnosticsApi.js';

export default function LinkedDiagnosticModal({
  open,
  saving = false,
  examenLabel,
  onClose,
  onSubmit,
}) {
  const [form, setForm] = useState(DEFAULT_DIAGNOSTIC_FORM);
  const [meta, setMeta] = useState({ types: Object.keys(DIAGNOSTIC_TYPE_LABELS), certitudes: Object.keys(DIAGNOSTIC_CERTITUDE_LABELS) });
  const [maladieQuery, setMaladieQuery] = useState('');
  const [maladieResults, setMaladieResults] = useState([]);
  const [maladieLoading, setMaladieLoading] = useState(false);
  const [selectedMaladie, setSelectedMaladie] = useState(null);
  const debounceRef = useRef(null);

  useEffect(() => {
    if (!open) return undefined;
    setForm(DEFAULT_DIAGNOSTIC_FORM);
    setMaladieQuery('');
    setMaladieResults([]);
    setSelectedMaladie(null);
    fetchDiagnosticMetaApi()
      .then((data) => setMeta({
        types: Array.isArray(data?.types) ? data.types : Object.keys(DIAGNOSTIC_TYPE_LABELS),
        certitudes: Array.isArray(data?.certitudes) ? data.certitudes : Object.keys(DIAGNOSTIC_CERTITUDE_LABELS),
      }))
      .catch(() => {});
    return undefined;
  }, [open]);

  useEffect(() => {
    if (debounceRef.current) clearTimeout(debounceRef.current);
    if (!open || !maladieQuery || maladieQuery.length < 2 || selectedMaladie) {
      setMaladieResults([]);
      return undefined;
    }
    debounceRef.current = setTimeout(async () => {
      try {
        setMaladieLoading(true);
        const result = await fetchMaladiesApi({ page: 1, limit: 15, search: maladieQuery.trim() });
        setMaladieResults(Array.isArray(result.items) ? result.items : []);
      } catch {
        setMaladieResults([]);
      } finally {
        setMaladieLoading(false);
      }
    }, 350);
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, [maladieQuery, open, selectedMaladie]);

  return (
    <Modal open={open} onClose={() => !saving && onClose()}>
      <ModalDialog sx={{ borderRadius: 'lg', maxWidth: 540, width: '100%' }}>
        <ModalClose />
        <Typography level="title-lg" sx={{ fontWeight: 700, mb: 0.5 }}>
          Poser un diagnostic lié
        </Typography>
        {examenLabel ? (
          <Typography level="body-sm" sx={{ color: 'neutral.600', mb: 1 }}>
            Examen : {examenLabel}
          </Typography>
        ) : null}
        <Divider sx={{ mb: 2 }} />
        <Stack spacing={2}>
          <FormControl required>
            <FormLabel>Maladie (CIM-10)</FormLabel>
            <Input
              placeholder="Rechercher par code ou libellé…"
              value={maladieQuery}
              onChange={(event) => {
                setMaladieQuery(event.target.value);
                setSelectedMaladie(null);
              }}
              endDecorator={maladieLoading ? <CircularProgress size="sm" /> : <Search size={16} />}
            />
            {selectedMaladie ? (
              <Chip
                size="sm"
                variant="soft"
                color="primary"
                sx={{ mt: 1, alignSelf: 'flex-start' }}
                endDecorator={(
                  <IconButton size="sm" variant="plain" onClick={() => { setSelectedMaladie(null); setMaladieQuery(''); }}>
                    ×
                  </IconButton>
                )}
              >
                {selectedMaladie.codeCim10} — {selectedMaladie.libelle}
              </Chip>
            ) : null}
            {maladieResults.length > 0 && !selectedMaladie ? (
              <Sheet variant="outlined" sx={{ borderRadius: 'md', mt: 1, maxHeight: 180, overflow: 'auto' }}>
                {maladieResults.map((maladie) => (
                  <Box
                    key={maladie.id}
                    onClick={() => {
                      setSelectedMaladie(maladie);
                      setMaladieQuery(maladie.codeCim10);
                      setMaladieResults([]);
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
                      <strong>{maladie.codeCim10}</strong> — {maladie.libelle}
                    </Typography>
                  </Box>
                ))}
              </Sheet>
            ) : null}
          </FormControl>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
            <FormControl sx={{ flex: 1 }}>
              <FormLabel>Type</FormLabel>
              <Select value={form.type} onChange={(_, value) => setForm((current) => ({ ...current, type: value ?? 'PROVISOIRE' }))}>
                {meta.types.map((type) => (
                  <Option key={type} value={type}>{DIAGNOSTIC_TYPE_LABELS[type] ?? type}</Option>
                ))}
              </Select>
            </FormControl>
            <FormControl sx={{ flex: 1 }}>
              <FormLabel>Certitude</FormLabel>
              <Select value={form.certitude} onChange={(_, value) => setForm((current) => ({ ...current, certitude: value ?? 'SUSPECTE' }))}>
                {meta.certitudes.map((certitude) => (
                  <Option key={certitude} value={certitude}>{DIAGNOSTIC_CERTITUDE_LABELS[certitude] ?? certitude}</Option>
                ))}
              </Select>
            </FormControl>
          </Stack>
          <FormControl>
            <FormLabel>Remarque (optionnel)</FormLabel>
            <Textarea
              minRows={2}
              value={form.remarque}
              onChange={(event) => setForm((current) => ({ ...current, remarque: event.target.value }))}
            />
          </FormControl>
        </Stack>
        <Stack direction="row" spacing={1.5} justifyContent="flex-end" sx={{ mt: 3 }}>
          <Button variant="soft" color="neutral" onClick={onClose} disabled={saving}>Annuler</Button>
          <Button
            loading={saving}
            onClick={() => onSubmit({
              maladieId: selectedMaladie?.id,
              type: form.type,
              certitude: form.certitude,
              remarque: form.remarque.trim() || null,
            })}
          >
            Enregistrer
          </Button>
        </Stack>
      </ModalDialog>
    </Modal>
  );
}
