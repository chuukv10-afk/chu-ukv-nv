import { useEffect, useMemo, useState } from 'react';
import {
  Box, Button, Checkbox, FormControl, FormLabel, IconButton, Input, Modal, ModalClose, ModalDialog,
  Stack, Typography,
} from '@mui/joy';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { clampFichePercent, splitFichesByPercent } from './fichesImprimables.js';

const PERCENT_PRESETS = [10, 25, 50];

export default function FichesImprimablesDialog({
  open,
  medicaments,
  loading = false,
  onClose,
  onGenerate,
}) {
  const [percentInput, setPercentInput] = useState('50');
  const [pageIndex, setPageIndex] = useState(0);
  const [unchecked, setUnchecked] = useState({});

  useEffect(() => {
    if (!open) {
      return;
    }
    setPercentInput('50');
    setPageIndex(0);
    setUnchecked({});
  }, [open]);

  const sorted = useMemo(
    () => [...medicaments].sort((left, right) => String(left.libelle ?? '').localeCompare(String(right.libelle ?? ''), 'fr', { sensitivity: 'base' })),
    [medicaments],
  );
  const { rate, pages } = splitFichesByPercent(sorted, percentInput);
  const safePageIndex = pages.length === 0 ? 0 : Math.min(pageIndex, pages.length - 1);
  const currentPage = pages[safePageIndex] ?? [];
  const selected = currentPage.filter((item) => !unchecked[item.id]);

  const setChecked = (id, checked) => {
    setUnchecked((previous) => {
      const next = { ...previous };
      if (checked) {
        delete next[id];
      } else {
        next[id] = true;
      }
      return next;
    });
  };

  const setPageChecked = (checked) => {
    setUnchecked((previous) => {
      const next = { ...previous };
      currentPage.forEach((item) => {
        if (checked) {
          delete next[item.id];
        } else {
          next[item.id] = true;
        }
      });
      return next;
    });
  };

  const changePercent = (value) => {
    setPercentInput(String(value));
    setPageIndex(0);
  };

  return (
    <Modal open={open} onClose={loading ? undefined : onClose}>
      <ModalDialog
        sx={{
          width: 'min(680px, calc(100vw - 32px))',
          maxHeight: '90vh',
          overflow: 'hidden',
          display: 'flex',
          flexDirection: 'column',
          gap: 1.5,
        }}
      >
        <ModalClose disabled={loading} />
        <Typography level="h4">Fiches à compléter</Typography>
        <Typography level="body-sm" sx={{ color: 'neutral.500' }}>
          Les médicaments actifs sont déjà cochés. {rate} % découpe la liste en {pages.length || 0} partie{pages.length > 1 ? 's' : ''}. Chaque partie ouvre un seul PDF, avec une page par médicament.
        </Typography>

        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} alignItems={{ sm: 'flex-end' }}>
          <FormControl sx={{ width: { xs: '100%', sm: 180 } }}>
            <FormLabel>Pourcentage</FormLabel>
            <Input
              type="number"
              value={percentInput}
              disabled={loading}
              slotProps={{ input: { min: 1, max: 100, step: 1 } }}
              endDecorator="%"
              onChange={(event) => changePercent(event.target.value)}
            />
          </FormControl>
          <Stack direction="row" spacing={0.75}>
            {PERCENT_PRESETS.map((preset) => (
              <Button
                key={preset}
                size="sm"
                variant={clampFichePercent(percentInput) === preset ? 'solid' : 'outlined'}
                disabled={loading}
                onClick={() => changePercent(preset)}
              >
                {preset} %
              </Button>
            ))}
          </Stack>
        </Stack>

        <Stack direction="row" justifyContent="space-between" alignItems="center">
          <Typography level="body-sm">
            Partie {pages.length === 0 ? 0 : safePageIndex + 1} / {pages.length} · {selected.length} coché{selected.length > 1 ? 's' : ''} sur {currentPage.length}
          </Typography>
          <Stack direction="row" spacing={0.5}>
            <Button size="sm" variant="plain" disabled={loading || currentPage.length === 0} onClick={() => setPageChecked(true)}>
              Tout cocher
            </Button>
            <Button size="sm" variant="plain" disabled={loading || currentPage.length === 0} onClick={() => setPageChecked(false)}>
              Tout décocher
            </Button>
          </Stack>
        </Stack>

        <Box sx={{ overflow: 'auto', minHeight: 180, maxHeight: 360, border: '1px solid', borderColor: 'neutral.outlinedBorder', borderRadius: 'md', p: 1 }}>
          {currentPage.length === 0 ? (
            <Typography level="body-sm" sx={{ color: 'neutral.500', p: 1 }}>Aucun médicament actif.</Typography>
          ) : currentPage.map((item) => (
            <Checkbox
              key={item.id}
              size="sm"
              disabled={loading}
              checked={!unchecked[item.id]}
              label={`${item.code} — ${item.libelle}`}
              onChange={(event) => setChecked(item.id, event.target.checked)}
              sx={{ display: 'flex', py: 0.4 }}
            />
          ))}
        </Box>

        <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" spacing={1}>
          <Stack direction="row" spacing={0.5} alignItems="center">
            <IconButton
              size="sm"
              variant="outlined"
              disabled={loading || safePageIndex <= 0}
              onClick={() => setPageIndex(Math.max(0, safePageIndex - 1))}
            >
              <ChevronLeft size={16} />
            </IconButton>
            <Typography level="body-sm" sx={{ minWidth: 88, textAlign: 'center' }}>
              {pages.length === 0 ? '0 / 0' : `${safePageIndex + 1} / ${pages.length}`}
            </Typography>
            <IconButton
              size="sm"
              variant="outlined"
              disabled={loading || safePageIndex >= pages.length - 1}
              onClick={() => setPageIndex(Math.min(pages.length - 1, safePageIndex + 1))}
            >
              <ChevronRight size={16} />
            </IconButton>
          </Stack>
          <Button
            loading={loading}
            disabled={loading || selected.length === 0}
            onClick={() => onGenerate(selected.map((item) => item.id), safePageIndex + 1, pages.length)}
          >
            Générer la partie {pages.length === 0 ? 0 : safePageIndex + 1}
          </Button>
        </Stack>
      </ModalDialog>
    </Modal>
  );
}
