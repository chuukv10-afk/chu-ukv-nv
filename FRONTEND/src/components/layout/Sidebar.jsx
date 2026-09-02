import {
  Box,
  IconButton,
  List,
  ListItem,
  ListItemButton,
  ListItemContent,
  ListItemDecorator,
  Stack,
  Tooltip,
  Typography,
} from '@mui/joy';
import {
  Award,
  BedDouble,
  Boxes,
  Building2,
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  ClipboardList,
  DoorOpen,
  FileText,
  FlaskConical,
  FolderOpen,
  KeyRound,
  LayoutDashboard,
  LogOut,
  Microscope,
  Network,
  Shield,
  ShieldCheck,
  Stethoscope,
  UserCog,
  Users,
} from 'lucide-react';
import { NavLink } from 'react-router-dom';
import logo from '../../assets/img/logo.jpg';
import LogoutConfirmModal from '../auth/LogoutConfirmModal.jsx';
import { LAYOUT } from '../../constants/layout.js';
import { NAV_SECTIONS } from '../../constants/navigation.js';
import { useAppDispatch, useAppSelector } from '../../hooks/useAppStore.js';
import { useLogoutConfirm } from '../../hooks/useLogoutConfirm.js';
import { usePermissions } from '../../hooks/usePermissions.js';
import { toggleSidebarCollapsed } from '../../store/ui/uiSlice.js';
import { LOTRU_LAYOUT, LOTRU_PRIMARY } from '../../theme/lotruPalette.js';

const NAV_ICONS = {
  dashboard: LayoutDashboard,
  building: Building2,
  network: Network,
  bed: BedDouble,
  door: DoorOpen,
  boxes: Boxes,
  award: Award,
  stethoscope: Stethoscope,
  flask: FlaskConical,
  file: FileText,
  microscope: Microscope,
  calendar: CalendarDays,
  clipboard: ClipboardList,
  users: Users,
  folder: FolderOpen,
  userCog: UserCog,
  shield: Shield,
  key: KeyRound,
};

function SidebarHeader({ collapsed }) {
  const dispatch = useAppDispatch();

  return (
    <Stack
      alignItems="center"
      spacing={1}
      sx={{
        px: collapsed ? 1 : 2,
        py: 2,
        borderBottom: '1px solid',
        borderColor: LOTRU_LAYOUT.sidebarBorder,
        flexShrink: 0,
      }}
    >
      <Stack
        direction="row"
        alignItems="center"
        justifyContent={collapsed ? 'center' : 'space-between'}
        spacing={1.25}
        sx={{ width: '100%' }}
      >
        <Stack direction="row" alignItems="center" spacing={1.25} sx={{ minWidth: 0, flex: 1 }}>
          <Box
            component="img"
            src={logo}
            alt="CHU UKV"
            sx={{ width: 44, height: 44, objectFit: 'contain', flexShrink: 0, borderRadius: 'sm' }}
          />
          {!collapsed ? (
            <Typography level="title-md" sx={{ fontWeight: 700, lineHeight: 1.1, color: '#fff' }} noWrap>
              CHU UKV
            </Typography>
          ) : null}
        </Stack>

        {!collapsed ? (
          <Tooltip title="Réduire le menu" placement="right">
            <IconButton
              size="sm"
              variant="soft"
              onClick={() => dispatch(toggleSidebarCollapsed())}
              sx={{
                flexShrink: 0,
                bgcolor: LOTRU_LAYOUT.sidebarSurface,
                color: LOTRU_LAYOUT.sidebarTextMuted,
                '&:hover': { bgcolor: LOTRU_PRIMARY[600], color: '#fff' },
              }}
            >
              <ChevronLeft size={18} />
            </IconButton>
          </Tooltip>
        ) : null}
      </Stack>

      {collapsed ? (
        <Tooltip title="Déplier le menu" placement="right">
          <IconButton
            size="sm"
            variant="soft"
            onClick={() => dispatch(toggleSidebarCollapsed())}
            sx={{
              bgcolor: LOTRU_LAYOUT.sidebarSurface,
              color: LOTRU_LAYOUT.sidebarTextMuted,
              '&:hover': { bgcolor: LOTRU_PRIMARY[600], color: '#fff' },
            }}
          >
            <ChevronRight size={18} />
          </IconButton>
        </Tooltip>
      ) : null}
    </Stack>
  );
}

function NavItem({ item, collapsed, onNavigate }) {
  const Icon = NAV_ICONS[item.icon] ?? ShieldCheck;

  const button = (
    <ListItemButton
      component={NavLink}
      to={item.to}
      onClick={onNavigate}
      sx={{
        py: 1,
        px: collapsed ? 1 : 1.5,
        justifyContent: collapsed ? 'center' : 'flex-start',
        color: LOTRU_LAYOUT.sidebarTextMuted,
        '&:hover': {
          bgcolor: LOTRU_LAYOUT.sidebarSurface,
          color: LOTRU_LAYOUT.sidebarText,
        },
        '&.active': {
          bgcolor: LOTRU_PRIMARY[500],
          color: '#fff',
          fontWeight: 600,
          '& .nav-icon': { color: '#fff' },
        },
      }}
    >
      <ListItemDecorator sx={{ marginInlineEnd: collapsed ? 0 : undefined }}>
        <Icon size={18} className="nav-icon" />
      </ListItemDecorator>
      {!collapsed ? <ListItemContent>{item.label}</ListItemContent> : null}
    </ListItemButton>
  );

  return (
    <ListItem sx={{ p: 0 }}>
      {collapsed ? (
        <Tooltip title={item.label} placement="right">
          <Box component="span" sx={{ display: 'flex', width: '100%' }}>
            {button}
          </Box>
        </Tooltip>
      ) : (
        button
      )}
    </ListItem>
  );
}

function LogoutNavItem({ collapsed, onNavigate, onRequestLogout }) {
  const button = (
    <ListItemButton
      onClick={() => {
        onNavigate?.();
        onRequestLogout();
      }}
      sx={{
        py: 1,
        px: collapsed ? 1 : 1.5,
        justifyContent: collapsed ? 'center' : 'flex-start',
        color: LOTRU_LAYOUT.sidebarTextMuted,
        '&:hover': {
          bgcolor: LOTRU_LAYOUT.sidebarSurface,
          color: '#fca5a5',
        },
      }}
    >
      <ListItemDecorator sx={{ marginInlineEnd: collapsed ? 0 : undefined }}>
        <LogOut size={18} />
      </ListItemDecorator>
      {!collapsed ? <ListItemContent>Déconnexion</ListItemContent> : null}
    </ListItemButton>
  );

  return (
    <ListItem sx={{ p: 0, mt: 1 }}>
      {collapsed ? (
        <Tooltip title="Déconnexion" placement="right">
          <Box component="span" sx={{ display: 'flex', width: '100%' }}>
            {button}
          </Box>
        </Tooltip>
      ) : (
        button
      )}
    </ListItem>
  );
}

function SidebarNav({ onNavigate, collapsed = false }) {
  const { canSeeNavItem, isAdmin } = usePermissions();
  const { logoutOpen, logoutLoading, requestLogout, cancelLogout, confirmLogout } = useLogoutConfirm();

  const visibleSections = NAV_SECTIONS.map((section) => {
    if (section.adminOnly && !isAdmin) {
      return null;
    }

    if (section.module && !canSeeNavItem({ module: section.module })) {
      return null;
    }

    const items = (section.items ?? []).filter((item) => canSeeNavItem(item));

    if (!items.length) {
      return null;
    }

    return { ...section, items };
  }).filter(Boolean);

  return (
    <Box
      sx={{
        display: 'flex',
        flexDirection: 'column',
        height: '100%',
        bgcolor: LOTRU_LAYOUT.sidebarBg,
      }}
    >
      <SidebarHeader collapsed={collapsed} />

      <Box sx={{ flex: 1, overflowY: 'auto', overflowX: 'hidden', px: collapsed ? 0.75 : 1.5, py: 2 }}>
        {visibleSections.map((section) => (
          <Box key={section.id} sx={{ mb: collapsed ? 1 : 2 }}>
            {!collapsed ? (
              <Typography
                level="body-xs"
                sx={{
                  px: 1.5,
                  mb: 0.75,
                  fontWeight: 600,
                  letterSpacing: '0.06em',
                  textTransform: 'uppercase',
                  color: LOTRU_LAYOUT.sidebarSection,
                }}
              >
                {section.title}
              </Typography>
            ) : null}

            <List size="sm" sx={{ '--ListItem-radius': '12px', '--List-gap': '4px' }}>
              {section.items.map((item) => (
                <NavItem
                  key={item.to}
                  item={item}
                  collapsed={collapsed}
                  onNavigate={onNavigate}
                />
              ))}
            </List>
          </Box>
        ))}

        <List size="sm" sx={{ '--ListItem-radius': '12px', '--List-gap': '4px' }}>
          <LogoutNavItem collapsed={collapsed} onNavigate={onNavigate} onRequestLogout={requestLogout} />
        </List>
      </Box>

      <LogoutConfirmModal
        open={logoutOpen}
        onClose={cancelLogout}
        onConfirm={confirmLogout}
        loading={logoutLoading}
      />
    </Box>
  );
}

export default function Sidebar({ onNavigate }) {
  const collapsed = useAppSelector((state) => state.ui.sidebarCollapsed);
  const width = collapsed ? LAYOUT.sidebarCollapsedWidth : LAYOUT.sidebarWidth;

  return (
    <Box
      component="aside"
      sx={{
        width,
        height: '100vh',
        position: 'sticky',
        top: 0,
        bgcolor: LOTRU_LAYOUT.sidebarBg,
        display: 'flex',
        flexDirection: 'column',
        transition: 'width 0.25s ease',
        flexShrink: 0,
      }}
    >
      <SidebarNav onNavigate={onNavigate} collapsed={collapsed} />
    </Box>
  );
}

export { SidebarNav };
