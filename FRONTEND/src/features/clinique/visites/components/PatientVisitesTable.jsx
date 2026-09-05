import { Button, Chip, Sheet, Stack, Table, Typography } from '@mui/joy';
import { Stethoscope } from 'lucide-react';
import VisiteTransitionPanel from './VisiteTransitionPanel.jsx';
import {
  TYPE_ENTREE_LABELS,
  VISITE_STATUT_COLORS,
  VISITE_STATUT_LABELS,
} from '../visiteConstants.js';
import { VISITE_CONSULTATION_STATUTS } from '../../consultations/consultationConstants.js';

function formatDateTime(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('fr-FR');
}

function StatusChip({ statut }) {
  return (
    <Chip size="sm" variant="soft" color={VISITE_STATUT_COLORS[statut] ?? 'neutral'}>
      {VISITE_STATUT_LABELS[statut] ?? statut}
    </Chip>
  );
}

export default function PatientVisitesTable({
  visites = [],
  lits = [],
  canUpdate = false,
  canConsult = false,
  consultationLoadingId = null,
  transitionLoadingId = null,
  onTransition,
  onOpenConsultation,
}) {
  if (visites.length === 0) {
    return null;
  }

  return (
    <Sheet variant="outlined" sx={{ borderRadius: 'md', overflow: 'auto' }}>
      <Table stickyHeader hoverRow sx={{ minWidth: 960 }}>
        <thead>
          <tr>
            <th>Entrée</th>
            <th>Service</th>
            <th>Type</th>
            <th>Motif</th>
            <th>Statut</th>
            <th>Lit</th>
            <th>Consult.</th>
            <th style={{ minWidth: 360 }}>Actions</th>
          </tr>
        </thead>
        <tbody>
          {visites.map((visite) => (
            <tr key={visite.id}>
              <td>{formatDateTime(visite.enterAt)}</td>
              <td>{visite.service?.libelle ?? '—'}</td>
              <td>{TYPE_ENTREE_LABELS[visite.triage?.typeEntree] ?? visite.triage?.typeEntree ?? '—'}</td>
              <td>
                <Typography level="body-sm" sx={{ maxWidth: 220 }} noWrap title={visite.triage?.motif ?? ''}>
                  {visite.triage?.motif ?? '—'}
                </Typography>
              </td>
              <td>
                <Stack direction="row" spacing={0.5} alignItems="center" flexWrap="wrap" useFlexGap>
                  <StatusChip statut={visite.statut} />
                  {visite.pendingHospitalization ? (
                    <Chip size="sm" variant="soft" color="warning">À hospitaliser</Chip>
                  ) : null}
                </Stack>
              </td>
              <td>{visite.lit ? `${visite.lit.code}${visite.lit.bloc ? ` (${visite.lit.bloc})` : ''}` : '—'}</td>
              <td>{visite.consultationCount ?? 0}</td>
              <td style={{ whiteSpace: 'nowrap' }}>
                <Stack direction="row" spacing={0.75} alignItems="center" flexWrap="nowrap" useFlexGap>
                  {canConsult && VISITE_CONSULTATION_STATUTS.includes(visite.statut) && (
                    <Button
                      size="sm"
                      variant="soft"
                      color="primary"
                      startDecorator={<Stethoscope size={14} />}
                      loading={consultationLoadingId === visite.id}
                      onClick={() => onOpenConsultation?.(visite)}
                    >
                      Consulter
                    </Button>
                  )}
                  <VisiteTransitionPanel
                    visite={visite}
                    lits={lits}
                    canUpdate={canUpdate}
                    loading={transitionLoadingId === visite.id}
                    onTransition={(payload) => onTransition?.(visite, payload)}
                  />
                </Stack>
              </td>
            </tr>
          ))}
        </tbody>
      </Table>
    </Sheet>
  );
}
