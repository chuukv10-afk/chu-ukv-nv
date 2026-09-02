import { Box, Drawer } from '@mui/joy';
import { Outlet } from 'react-router-dom';
import Header from './Header.jsx';
import Sidebar, { SidebarNav } from './Sidebar.jsx';
import { LAYOUT } from '../../constants/layout.js';
import { useAppDispatch, useAppSelector } from '../../hooks/useAppStore.js';
import { setSidebarOpen } from '../../store/ui/uiSlice.js';

export default function AppLayout() {
  const dispatch = useAppDispatch();
  const sidebarOpen = useAppSelector((state) => state.ui.sidebarOpen);

  const closeMobileSidebar = () => {
    dispatch(setSidebarOpen(false));
  };

  return (
    <Box sx={{ display: 'flex', minHeight: '100vh', bgcolor: 'background.body' }}>
      <Box sx={{ display: { xs: 'none', md: 'block' }, flexShrink: 0 }}>
        <Sidebar />
      </Box>

      <Drawer
        open={sidebarOpen}
        onClose={closeMobileSidebar}
        anchor="left"
        sx={{ display: { md: 'none' } }}
        slotProps={{
          content: {
            sx: { width: LAYOUT.sidebarWidth, p: 0 },
          },
        }}
      >
        <SidebarNav onNavigate={closeMobileSidebar} collapsed={false} />
      </Drawer>

      <Box
        sx={{
          flex: 1,
          minWidth: 0,
          display: 'flex',
          flexDirection: 'column',
          maxWidth: '100%',
        }}
      >
        <Header />
        <Box
          component="main"
          sx={{
            flex: 1,
            p: { xs: 2, md: 3 },
            maxWidth: LAYOUT.contentMaxWidth,
            width: '100%',
            mx: 'auto',
          }}
        >
          <Outlet />
        </Box>
      </Box>
    </Box>
  );
}
