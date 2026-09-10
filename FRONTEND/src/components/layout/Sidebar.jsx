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
  Activity,
  AlertTriangle,
  Archive,
  ArrowLeftRight,
  Award,
  BarChart3,
  BedDouble,
  Boxes,
  Building2,
  CalendarDays,
  ChevronLeft,
  ClipboardList,
  DoorOpen,
  FileText,
  FlaskConical,
  FolderOpen,
  HeartPulse,
  KeyRound,
  Layers,
  LayoutDashboard,
  Link2,
  LogOut,
  MessageSquare,
  Microscope,
  Network,
  Package,
  PackagePlus,
  Pill,
  Receipt,
  ScanLine,
  Wallet,
  Shield,
  ShieldCheck,
  ShoppingCart,
  SlidersHorizontal,
  Stethoscope,
  Tags,
  Truck,
  UserCog,
  Users,
  Database,
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

const SIDEBAR_SCROLL_SX = {
  scrollbarWidth: 'thin',
  scrollbarColor: `${LOTRU_LAYOUT.sidebarSurface} transparent`,
  '&::-webkit-scrollbar': {
    width: 4,
  },
  '&::-webkit-scrollbar-track': {
    background: 'transparent',
  },
  '&::-webkit-scrollbar-thumb': {
    backgroundColor: LOTRU_LAYOUT.sidebarSurface,
    borderRadius: 4,
  },
  '&::-webkit-scrollbar-thumb:hover': {
    backgroundColor: LOTRU_LAYOUT.sidebarTextMuted,
  },
};

const NAV_ICONS = {
  dashboard: LayoutDashboard,
  archive: Archive,
  scan: ScanLine,
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
  heartPulse: HeartPulse,
  heartbeat: HeartPulse,
  activity: Activity,
  messageSquare: MessageSquare,
  pill: Pill,
  tags: Tags,
  package: Package,
  truck: Truck,
  packagePlus: PackagePlus,
  layers: Layers,
  arrows: ArrowLeftRight,
  cart: ShoppingCart,
  clipboard: ClipboardList,
  receipt: Receipt,
  wallet: Wallet,
  sliders: SlidersHorizontal,
  alert: AlertTriangle,
  chart: BarChart3,
  calendar: CalendarDays,
  users: Users,
  folder: FolderOpen,
  userCog: UserCog,
  shield: Shield,
  key: KeyRound,
  link: Link2,
  database: Database,
};

function SidebarHeader({ collapsed }) {
  const dispatch = useAppDispatch();

  return (
    <Stack
      alignItems="center"
      justifyContent="center"
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
        <Stack
          direction="row"
          alignItems="center"
          justifyContent={collapsed ? 'center' : 'flex-start'}
          spacing={1.25}
          sx={{ minWidth: 0, flex: collapsed ? 0 : 1, width: collapsed ? '100%' : 'auto' }}
        >
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
    </Stack>
  );
}

function NavItem({ item, collapsed, onNavigate }) {
  const Icon = NAV_ICONS[item.icon] ?? ShieldCheck;

  const button = collapsed ? (
    <ListItemButton
      component={NavLink}
      to={item.to}
      onClick={onNavigate}
      sx={{
        width: 44,
        height: 40,
        mx: 'auto',
        p: 0,
        justifyContent: 'center',
        alignItems: 'center',
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
      <Icon size={18} className="nav-icon" />
    </ListItemButton>
  ) : (
    <ListItemButton
      component={NavLink}
      to={item.to}
      onClick={onNavigate}
      sx={{
        py: 1,
        px: 1.5,
        justifyContent: 'flex-start',
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
      <ListItemDecorator>
        <Icon size={18} className="nav-icon" />
      </ListItemDecorator>
      <ListItemContent>{item.label}</ListItemContent>
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
  const button = collapsed ? (
    <ListItemButton
      onClick={() => {
        onNavigate?.();
        onRequestLogout();
      }}
      sx={{
        width: 44,
        height: 40,
        mx: 'auto',
        p: 0,
        justifyContent: 'center',
        alignItems: 'center',
        color: LOTRU_LAYOUT.sidebarTextMuted,
        '&:hover': {
          bgcolor: LOTRU_LAYOUT.sidebarSurface,
          color: '#fca5a5',
        },
      }}
    >
      <LogOut size={18} />
    </ListItemButton>
  ) : (
    <ListItemButton
      onClick={() => {
        onNavigate?.();
        onRequestLogout();
      }}
      sx={{
        py: 1,
        px: 1.5,
        justifyContent: 'flex-start',
        color: LOTRU_LAYOUT.sidebarTextMuted,
        '&:hover': {
          bgcolor: LOTRU_LAYOUT.sidebarSurface,
          color: '#fca5a5',
        },
      }}
    >
      <ListItemDecorator>
        <LogOut size={18} />
      </ListItemDecorator>
      <ListItemContent>Déconnexion</ListItemContent>
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
  const { canSeeNavItem } = usePermissions();
  const { logoutOpen, logoutLoading, requestLogout, cancelLogout, confirmLogout } = useLogoutConfirm();

  const visibleSections = NAV_SECTIONS.map((section) => {
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

      <Box
        sx={{
          flex: 1,
          overflowY: 'auto',
          overflowX: 'hidden',
          px: collapsed ? 0 : 1.5,
          py: 2,
          ...SIDEBAR_SCROLL_SX,
        }}
      >
        {visibleSections.map((section) => (
          <Box key={section.id} sx={{ mb: collapsed ? 1 : 2, width: '100%' }}>
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

            <List
              size="sm"
              sx={{
                '--ListItem-radius': '12px',
                '--List-gap': '4px',
                width: '100%',
                ...(collapsed ? { alignItems: 'center' } : {}),
              }}
            >
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

        <List
          size="sm"
          sx={{
            '--ListItem-radius': '12px',
            '--List-gap': '4px',
            width: '100%',
            ...(collapsed ? { alignItems: 'center' } : {}),
          }}
        >
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
