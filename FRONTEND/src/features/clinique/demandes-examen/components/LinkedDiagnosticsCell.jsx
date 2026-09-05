import { useState } from 'react';
import {
  Chip,
  Divider,
  Modal,
  ModalClose,
  ModalDialog,
  Stack,
  Table,
  Typography,
} from '@mui/joy';
import { LOTRU_NEUTRAL } from '../../../../theme/lotruPalette.js';
import {
  DIAGNOSTIC_CERTITUDE_COLORS,
  DIAGNOSTIC_CERTITUDE_LABELS,
  DIAGNOSTIC_TYPE_COLORS,
  DIAGNOSTIC_TYPE_LABELS,
} from '../../diagnostics/diagnosticConstants.js';

function formatDateTime(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('fr-FR');
}

function diagnosticLabel(diagnostic) {
  return diagnostic?.maladie?.libelle ?? 'Diagnostic';
}

export default function LinkedDiagnosticsCell({ diagnostics = [] }) {
  const [open, setOpen] = useState(false);
  const items = Array.isArray(diagnostics) ? diagnostics : [];

  if (items.length === 0) {
    return '—';
  }

  const first = items[0];
  const extraCount = items.length;

  return (
    <>
      <Stack direction="row" spacing={0.75} alignItems="center" useFlexGap flexWrap="wrap">
        <Typography level="body-sm">{diagnosticLabel(first)}</Typography>
        {items.length > 1 ? (
          <Chip
            size="sm"
            variant="soft"
            color="primary"
            onClick={() => setOpen(true)}
            sx={{ cursor: 'pointer', fontWeight: 700 }}
          >
            {extraCount}
          </Chip>
        ) : null}
      </Stack>

      <Modal open={open} onClose={() => setOpen(false)}>
        <ModalDialog sx={{ borderRadius: 'lg', maxWidth: 720, width: '100%' }}>
          <ModalClose />
          <Typography level="title-lg" sx={{ fontWeight: 700 }}>
            Diagnostics liés
          </Typography>
          <Typography level="body-sm" sx={{ color: LOTRU_NEUTRAL[600], mb: 1.5 }}>
            {items.length} diagnostic{items.length > 1 ? 's' : ''} associé{items.length > 1 ? 's' : ''} à cette demande.
          </Typography>
          <Divider sx={{ mb: 1.5 }} />
          <Table stickyHeader sx={{ minWidth: 560 }}>
            <thead>
              <tr>
                <th>Code CIM-10</th>
                <th>Libellé</th>
                <th>Type</th>
                <th>Certitude</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              {items.map((diagnostic) => (
                <tr key={diagnostic.id}>
                  <td>{diagnostic.maladie?.codeCim10 ?? '—'}</td>
                  <td>{diagnostic.maladie?.libelle ?? '—'}</td>
                  <td>
                    <Chip size="sm" variant="soft" color={DIAGNOSTIC_TYPE_COLORS[diagnostic.type] ?? 'neutral'}>
                      {DIAGNOSTIC_TYPE_LABELS[diagnostic.type] ?? diagnostic.type ?? '—'}
                    </Chip>
                  </td>
                  <td>
                    <Chip size="sm" variant="soft" color={DIAGNOSTIC_CERTITUDE_COLORS[diagnostic.certitude] ?? 'neutral'}>
                      {DIAGNOSTIC_CERTITUDE_LABELS[diagnostic.certitude] ?? diagnostic.certitude ?? '—'}
                    </Chip>
                  </td>
                  <td>{formatDateTime(diagnostic.createdAt)}</td>
                </tr>
              ))}
            </tbody>
          </Table>
        </ModalDialog>
      </Modal>
    </>
  );
}
