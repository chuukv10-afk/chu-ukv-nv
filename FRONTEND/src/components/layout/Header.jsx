import {
  Avatar,
  Box,
  Breadcrumbs,
  Chip,
  Divider,
  Dropdown,
  IconButton,
  Input,
  ListItemDecorator,
  Menu,
  MenuButton,
  MenuItem,
  Stack,
  Typography,
} from '@mui/joy';
import { Bell, ChevronDown, LogOut, Menu as MenuIcon, Search, User } from 'lucide-react';
import LogoutConfirmModal from '../auth/LogoutConfirmModal.jsx';
import { LAYOUT } from '../../constants/layout.js';
import { useAuth } from '../../hooks/useAuth.js';
import { useLogoutConfirm } from '../../hooks/useLogoutConfirm.js';
import { usePageMeta } from '../../hooks/usePageMeta.js';
import { useAppDispatch } from '../../hooks/useAppStore.js';
import { setSidebarOpen } from '../../store/ui/uiSlice.js';
import { LOTRU_NEUTRAL, LOTRU_PRIMARY } from '../../theme/lotruPalette.js';
import {
  formatRoleAssignment,
  getDisplayName,
  getInitials,
  getPersonnelTypeLabel,
} from '../../utils/profile.js';

function ProfileMenu({ profile, displayName, personnelLabel, roleAssignments, onLogout }) {
  const metaLine = [profile?.matricule, profile?.grade || 'Personnel'].filter(Boolean).join(' · ');

  return (
    <Menu
      placement="bottom-end"
      variant="outlined"
      sx={{
        minWidth: 260,
        borderRadius: 'lg',
        p: 1,
        bgcolor: '#fff',
        border: '1px solid',
        borderColor: LOTRU_NEUTRAL[200],
        boxShadow: '0 4px 6px -1px rgba(0,0,0,0.06), 0 2px 4px -2px rgba(0,0,0,0.04)',
        '--ListItem-radius': '8px',
        '--ListItem-minHeight': '36px',
      }}
    >
      <Box sx={{ px: 1.5, py: 1 }}>
        <Typography level="title-sm" sx={{ fontWeight: 600 }}>
          {displayName}
        </Typography>
        <Typography level="body-xs" sx={{ color: 'neutral.500', mt: 0.25 }}>
          {personnelLabel}
        </Typography>
        {metaLine ? (
          <Typography level="body-xs" sx={{ color: 'neutral.400', mt: 0.25 }}>
            {metaLine}
          </Typography>
        ) : null}
        {profile?.service ? (
          <Typography level="body-xs" sx={{ color: 'neutral.400', mt: 0.25 }} noWrap>
            {profile.service}
          </Typography>
        ) : null}
      </Box>

      {roleAssignments.length > 0 ? (
        <>
          <Divider sx={{ my: 0.5 }} />
          <Typography
            level="body-xs"
            sx={{ px: 1.5, py: 0.5, color: 'neutral.500', fontWeight: 600 }}
          >
            Affectations
          </Typography>
          {roleAssignments.map((assignment, index) => (
            <MenuItem
              key={`${assignment.role}-${index}`}
              disabled
              sx={{ py: 0.75, alignItems: 'flex-start' }}
            >
              <Typography level="body-xs" sx={{ color: 'neutral.600', whiteSpace: 'normal' }}>
                {formatRoleAssignment(assignment)}
              </Typography>
            </MenuItem>
          ))}
        </>
      ) : null}

      <Divider sx={{ my: 0.5 }} />

      <MenuItem sx={{ gap: 1 }}>
        <ListItemDecorator sx={{ minInlineSize: 24 }}>
          <User size={18} />
        </ListItemDecorator>
        Mon profil
      </MenuItem>

      <MenuItem color="danger" onClick={onLogout} sx={{ gap: 1 }}>
        <ListItemDecorator sx={{ minInlineSize: 24 }}>
          <LogOut size={18} />
        </ListItemDecorator>
        Déconnexion
      </MenuItem>
    </Menu>
  );
}

export default function Header() {
  const dispatch = useAppDispatch();
  const { profile } = useAuth();
  const { title, section } = usePageMeta();
  const { logoutOpen, logoutLoading, requestLogout, cancelLogout, confirmLogout } = useLogoutConfirm();

  const displayName = getDisplayName(profile);
  const roleAssignments = profile?.roleAssignments ?? [];
  const personnelLabel = getPersonnelTypeLabel(profile?.type);

  return (
    <>
      <Box
        component="header"
        sx={{
          height: LAYOUT.headerHeight,
          px: { xs: 2, md: 3 },
          bgcolor: 'background.surface',
          borderBottom: '1px solid',
          borderColor: 'divider',
          display: 'flex',
          alignItems: 'center',
          position: 'sticky',
          top: 0,
          zIndex: 1000,
        }}
      >
        <Stack
          direction="row"
          alignItems="center"
          justifyContent="space-between"
          spacing={2}
          sx={{ width: '100%' }}
        >
          <Stack direction="row" alignItems="center" spacing={1.5} sx={{ minWidth: 0 }}>
            <IconButton
              variant="plain"
              color="neutral"
              onClick={() => dispatch(setSidebarOpen(true))}
              sx={{ display: { md: 'none' } }}
            >
              <MenuIcon size={20} />
            </IconButton>

            <Box sx={{ minWidth: 0 }}>
              <Breadcrumbs size="sm" sx={{ '--Breadcrumbs-gap': '6px', mb: 0.25 }}>
                <Typography level="body-xs" sx={{ color: 'neutral.500' }}>
                  {section}
                </Typography>
              </Breadcrumbs>
              <Typography level="title-lg" sx={{ fontWeight: 700, lineHeight: 1.2 }} noWrap>
                {title}
              </Typography>
            </Box>
          </Stack>

          <Stack direction="row" alignItems="center" spacing={1}>
            <Input
              size="sm"
              placeholder="Rechercher..."
              startDecorator={<Search size={16} />}
              sx={{
                width: { xs: 0, sm: 220, md: 280 },
                display: { xs: 'none', sm: 'flex' },
                bgcolor: 'background.level2',
                border: 'none',
                boxShadow: 'none',
              }}
            />

            <IconButton variant="plain" color="neutral" sx={{ display: { xs: 'none', sm: 'inline-flex' } }}>
              <Bell size={20} />
            </IconButton>

            {profile?.type ? (
              <Chip size="sm" variant="soft" color="primary" sx={{ display: { xs: 'none', lg: 'inline-flex' } }}>
                {personnelLabel}
              </Chip>
            ) : null}

            <Divider orientation="vertical" sx={{ height: 24, display: { xs: 'none', sm: 'block' } }} />

            <Dropdown>
              <MenuButton
                variant="plain"
                sx={{
                  borderRadius: 'lg',
                  px: { xs: 0.5, md: 1 },
                  py: 0.5,
                  minHeight: 'unset',
                  '&:hover': { bgcolor: 'background.level1' },
                  '&[aria-expanded="true"]': { bgcolor: 'background.level1' },
                }}
              >
                <Stack direction="row" alignItems="center" spacing={1}>
                  <Avatar
                    size="sm"
                    variant="soft"
                    sx={{ bgcolor: LOTRU_PRIMARY[100], color: LOTRU_PRIMARY[700], fontWeight: 600 }}
                  >
                    {getInitials(profile)}
                  </Avatar>
                  <Box sx={{ display: { xs: 'none', md: 'block' }, textAlign: 'left', minWidth: 0 }}>
                    <Typography level="title-sm" sx={{ fontWeight: 600, lineHeight: 1.2 }} noWrap>
                      {displayName}
                    </Typography>
                    <Typography level="body-xs" sx={{ color: 'neutral.500' }} noWrap>
                      {personnelLabel}
                    </Typography>
                  </Box>
                  <ChevronDown size={15} color={LOTRU_NEUTRAL[400]} style={{ flexShrink: 0 }} />
                </Stack>
              </MenuButton>

              <ProfileMenu
                profile={profile}
                displayName={displayName}
                personnelLabel={personnelLabel}
                roleAssignments={roleAssignments}
                onLogout={requestLogout}
              />
            </Dropdown>
          </Stack>
        </Stack>
      </Box>

      <LogoutConfirmModal
        open={logoutOpen}
        onClose={cancelLogout}
        onConfirm={confirmLogout}
        loading={logoutLoading}
      />
    </>
  );
}
