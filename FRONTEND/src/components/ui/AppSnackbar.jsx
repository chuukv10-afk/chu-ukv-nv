import { Snackbar } from '@mui/joy';
import { CheckCircle2, CircleAlert, Info, TriangleAlert } from 'lucide-react';
import { useAppDispatch, useAppSelector } from '../../hooks/useAppStore.js';
import { clearToast } from '../../store/ui/uiSlice.js';

const TOAST_ICONS = {
  success: CheckCircle2,
  danger: CircleAlert,
  warning: TriangleAlert,
  neutral: Info,
};

export default function AppSnackbar() {
  const dispatch = useAppDispatch();
  const toast = useAppSelector((state) => state.ui.toast);

  const open = Boolean(toast?.message);
  const severity = toast?.severity ?? 'neutral';
  const duration = toast?.duration ?? 4000;
  const Icon = TOAST_ICONS[severity] ?? Info;

  const handleClose = (_, reason) => {
    if (reason === 'clickaway') {
      return;
    }

    dispatch(clearToast());
  };

  return (
    <Snackbar
      open={open}
      onClose={handleClose}
      autoHideDuration={duration}
      variant="soft"
      color={severity}
      anchorOrigin={{ vertical: 'top', horizontal: 'center' }}
      startDecorator={<Icon size={18} />}
      sx={{
        top: { xs: 16, md: 20 },
        zIndex: 1500,
        boxShadow: 'lg',
        borderRadius: 'lg',
        maxWidth: { xs: 'calc(100% - 32px)', sm: 420 },
      }}
    >
      {toast?.message}
    </Snackbar>
  );
}
