import { useMemo, useState } from 'react';
import { Button, Card, Chip, Sheet, Stack, Table, Typography } from '@mui/joy';
import { BedDouble, LogOut, Stethoscope } from 'lucide-react';
import {
  formatDateTime,
  formatStayDuration,
  getHospitalisationEnd,
  getHospitalisationStart,
  isHospitalisation,
} from '../hospitalisationsUtils.js';
import HospitalisationDischargeModal from './HospitalisationDischargeModal.jsx';
import { canDischargeHospitalization } from '../visiteConstants.js';
import { visiteHasActiveConsultation } from '../../consultations/consultationConstants.js';

export default function PatientHospitalisationsTab({
  visites = [],
  loading = false,
  error = '',
  canCreateConsultation = false,
  canDischarge = false,
  recordWritable = true,
  consultationLoadingId = null,
  dischargeLoadingId = null,
  onCreateConsultation,
  onResumeConsultation,
  onDischarge,
}) {
  const [dischargeOpen, setDischargeOpen] = useState(false);

  const hospitalisations = useMemo(
    () => [...visites].filter(isHospitalisation).sort((left, right) => {
      const leftStart = new Date(getHospitalisationStart(left) || 0).getTime();
      const rightStart = new Date(getHospitalisationStart(right) || 0).getTime();
      return rightStart - leftStart;
    }),
    [visites],
  );

  const current = hospitalisations.find((item) => item.statut === 'HOSPITALISE' || item.isCurrentHospitalization);
  const currentHasActiveConsultation = visiteHasActiveConsultation(current);
  const canCreateNew = Boolean(canCreateConsultation && recordWritable && current && !currentHasActiveConsultation);
  const canResume = Boolean(canCreateConsultation && recordWritable && current && currentHasActiveConsultation);
  const showDischarge = Boolean(canDischarge && recordWritable && current && canDischargeHospitalization(current));

  const handleCreateTour = async () => {
    if (!current) return;
    await onCreateConsultation?.(current);
  };

  const handleDischarge = async () => {
    if (!current) return;
    const success = await onDischarge?.(current);
    if (success) {
      setDischargeOpen(false);
    }
  };

  return (
    <Card variant="outlined" sx={{ borderRadius: 'lg', p: 2.5 }}>
      <Stack spacing={2}>
        <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={1.5}>
          <Stack direction="row" spacing={1} alignItems="center">
            <BedDouble size={18} />
            <Typography level="title-md" sx={{ fontWeight: 700 }}>Hospitalisations</Typography>
          </Stack>
          {canCreateNew ? (
            <Button
              size="sm"
              startDecorator={<Stethoscope size={16} />}
              loading={consultationLoadingId === current.id}
              onClick={handleCreateTour}
            >
              Nouveau tour de salle
            </Button>
          ) : null}
          {canResume ? (
            <Button
              size="sm"
              variant="soft"
              color="success"
              startDecorator={<Stethoscope size={16} />}
              loading={consultationLoadingId === current.id}
              onClick={() => onResumeConsultation?.(current)}
            >
              Reprendre le tour
            </Button>
          ) : null}
          {showDischarge ? (
            <Button
              size="sm"
              variant="soft"
              color="primary"
              startDecorator={<LogOut size={16} />}
              loading={dischargeLoadingId === current.id}
              onClick={() => setDischargeOpen(true)}
            >
              Terminer le séjour
            </Button>
          ) : null}
        </Stack>

        {currentHasActiveConsultation ? (
          <Typography level="body-sm" color="warning" sx={{ bgcolor: 'warning.50', p: 1.5, borderRadius: 'md' }}>
            Une consultation est en cours pour ce séjour. Clôturez-la avant d&apos;en créer une nouvelle ou de terminer l&apos;hospitalisation.
          </Typography>
        ) : null}

        {current ? (
          <Card variant="soft" color="warning" sx={{ borderRadius: 'md', p: 2 }}>
            <Stack spacing={0.75}>
              <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
                <Chip size="sm" variant="solid" color="warning">En cours</Chip>
                <Typography level="title-sm" sx={{ fontWeight: 700 }}>
                  {current.service?.libelle ?? 'Service'}
                </Typography>
              </Stack>
              <Typography level="body-sm">
                Depuis le {formatDateTime(getHospitalisationStart(current))}
                {current.lit ? ` · Lit ${current.lit.code}${current.lit.bloc ? ` (${current.lit.bloc})` : ''}` : ''}
              </Typography>
              <Typography level="body-sm" sx={{ fontWeight: 600 }}>
                Durée du séjour : {formatStayDuration(getHospitalisationStart(current), null)}
              </Typography>
            </Stack>
          </Card>
        ) : (
          <Typography level="body-sm" sx={{ color: 'neutral.600' }}>
            Aucune hospitalisation en cours.
          </Typography>
        )}

        {error ? (
          <Typography level="body-sm" color="danger" sx={{ bgcolor: 'danger.50', p: 1.5, borderRadius: 'md' }}>
            {error}
          </Typography>
        ) : null}

        {loading ? (
          <Typography level="body-sm">Chargement des hospitalisations…</Typography>
        ) : hospitalisations.length === 0 ? (
          <Typography level="body-sm" color="neutral">Aucune hospitalisation enregistrée.</Typography>
        ) : (
          <Sheet variant="outlined" sx={{ borderRadius: 'md', overflow: 'auto' }}>
            <Table stickyHeader hoverRow sx={{ minWidth: 860 }}>
              <thead>
                <tr>
                  <th>Admission</th>
                  <th>Sortie</th>
                  <th>Durée</th>
                  <th>Service</th>
                  <th>Lit</th>
                  <th>Nb consultations</th>
                  <th>Statut</th>
                </tr>
              </thead>
              <tbody>
                {hospitalisations.map((visite) => {
                  const start = getHospitalisationStart(visite);
                  const end = getHospitalisationEnd(visite);
                  const isCurrent = visite.statut === 'HOSPITALISE' || visite.isCurrentHospitalization;
                  return (
                    <tr key={visite.id}>
                      <td>{formatDateTime(start)}</td>
                      <td>{isCurrent ? '—' : formatDateTime(end)}</td>
                      <td>
                        <Typography level="body-sm" sx={{ fontWeight: 600 }}>
                          {formatStayDuration(start, end)}
                          {isCurrent ? ' (en cours)' : ''}
                        </Typography>
                      </td>
                      <td>{visite.service?.libelle ?? '—'}</td>
                      <td>{visite.lit ? `${visite.lit.code}${visite.lit.bloc ? ` (${visite.lit.bloc})` : ''}` : '—'}</td>
                      <td>{visite.consultationCount ?? 0}</td>
                      <td>
                        <Chip size="sm" variant="soft" color={isCurrent ? 'warning' : 'neutral'}>
                          {isCurrent ? 'En cours' : 'Terminée'}
                        </Chip>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </Table>
          </Sheet>
        )}
      </Stack>

      <HospitalisationDischargeModal
        open={dischargeOpen}
        visite={current}
        loading={dischargeLoadingId === current?.id}
        onClose={() => setDischargeOpen(false)}
        onConfirm={handleDischarge}
      />
    </Card>
  );
}
