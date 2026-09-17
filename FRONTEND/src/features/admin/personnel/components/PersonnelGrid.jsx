import { Box, Card, Chip, IconButton, Stack, Typography } from '@mui/joy';
import { Pencil, Trash2, Users } from 'lucide-react';
import AuthAvatar from '../../../../components/ui/AuthAvatar.jsx';
import RoleAssignmentLabel from '../../../../components/common/RoleAssignmentLabel.jsx';
import { getDisplayName, getInitials } from '../../../../utils/profile.js';
import { LOTRU_NEUTRAL } from '../../../../theme/lotruPalette.js';
import { PERSONNEL_STATUS_COLORS, PERSONNEL_STATUS_LABELS, PERSONNEL_TYPE_LABELS } from '../personnelConstants.js';

function StatusChip({ status }) {
  return (
    <Chip size="sm" variant="soft" color={PERSONNEL_STATUS_COLORS[status] ?? 'neutral'}>
      {PERSONNEL_STATUS_LABELS[status] ?? status}
    </Chip>
  );
}

export default function PersonnelGrid({
  personnels,
  loading,
  showActions,
  canUpdate,
  canDelete,
  onEdit,
  onDelete,
  hideRoles = false,
}) {
  if (loading) {
    return (
      <Typography level="body-sm" sx={{ py: 5, textAlign: 'center', color: 'neutral.500' }}>
        Chargement...
      </Typography>
    );
  }

  if (personnels.length === 0) {
    return (
      <Stack alignItems="center" spacing={1} sx={{ py: 6 }}>
        <Box sx={{ width: 48, height: 48, borderRadius: 'md', bgcolor: 'primary.50', color: 'primary.600', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <Users size={22} />
        </Box>
        <Typography level="title-sm" sx={{ fontWeight: 600 }}>Aucun personnel trouvé</Typography>
      </Stack>
    );
  }

  return (
    <Box
      sx={{
        display: 'grid',
        gap: 1.5,
        gridTemplateColumns: {
          xs: '1fr',
          sm: 'repeat(2, minmax(0, 1fr))',
          lg: 'repeat(3, minmax(0, 1fr))',
        },
      }}
    >
      {personnels.map((personnel) => (
        <Card
          key={personnel.id}
          variant="outlined"
          sx={{
            p: 2,
            borderColor: LOTRU_NEUTRAL[200],
            gap: 1.25,
          }}
        >
          <Stack direction="row" spacing={1.5} alignItems="flex-start">
            <AuthAvatar
              src={personnel.avatarUrl}
              fallback={getInitials(personnel)}
              size="lg"
              sx={{ bgcolor: 'primary.50', color: 'primary.700', fontWeight: 600, flexShrink: 0 }}
            />
            <Box sx={{ minWidth: 0, flex: 1 }}>
              <Typography level="title-sm" sx={{ fontWeight: 700 }} noWrap>
                {getDisplayName(personnel)}
              </Typography>
              <Typography level="body-xs" sx={{ color: 'neutral.500' }} noWrap>
                {[personnel.grade?.libelle, personnel.fonction?.libelle].filter(Boolean).join(' · ') || 'Sans poste'}
              </Typography>
              <Typography level="body-xs" sx={{ color: 'neutral.500' }} noWrap>
                {personnel.service?.libelle ?? 'Sans service'}
              </Typography>
            </Box>
            {showActions ? (
              <Stack direction="row" spacing={0.25}>
                {canUpdate ? (
                  <IconButton size="sm" variant="plain" color="neutral" onClick={() => onEdit(personnel)} title="Modifier">
                    <Pencil size={16} />
                  </IconButton>
                ) : null}
                {canDelete ? (
                  <IconButton size="sm" variant="plain" color="danger" onClick={() => onDelete(personnel)} title="Supprimer">
                    <Trash2 size={16} />
                  </IconButton>
                ) : null}
              </Stack>
            ) : null}
          </Stack>

          <Stack direction="row" spacing={0.75} useFlexGap flexWrap="wrap">
            <Chip size="sm" variant="soft" color="neutral">
              {PERSONNEL_TYPE_LABELS[personnel.type] ?? personnel.type}
            </Chip>
            <StatusChip status={personnel.status} />
          </Stack>

          <Stack spacing={0.25}>
            <Typography level="body-xs" sx={{ color: 'neutral.500' }}>
              {personnel.matricule ? `Matricule ${personnel.matricule}` : 'Sans matricule'}
              {personnel.telephone ? ` · ${personnel.telephone}` : ''}
            </Typography>
            {!hideRoles && (personnel.roleAssignments ?? []).length > 0 ? (
              <Stack direction="row" spacing={0.5} useFlexGap flexWrap="wrap">
                {(personnel.roleAssignments ?? []).slice(0, 2).map((assignment) => (
                  <Chip key={assignment.id} size="sm" variant="outlined" color="primary">
                    <RoleAssignmentLabel
                      assignment={{
                        roleLibelle: assignment.roleLibelle,
                        roleCode: assignment.roleCode,
                        service: assignment.serviceLibelle,
                        departement: assignment.departementLibelle,
                      }}
                      preferCode
                      component="span"
                    />
                  </Chip>
                ))}
                {(personnel.roleAssignments?.length ?? 0) > 2 ? (
                  <Chip size="sm" variant="soft" color="neutral">+{personnel.roleAssignments.length - 2}</Chip>
                ) : null}
              </Stack>
            ) : null}
          </Stack>
        </Card>
      ))}
    </Box>
  );
}
