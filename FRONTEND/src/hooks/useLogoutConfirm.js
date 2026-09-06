import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ROUTES } from '../constants/routes.js';
import { logoutUser } from '../features/auth/authService.js';
import { useAppDispatch } from './useAppStore.js';

export function useLogoutConfirm() {
  const dispatch = useAppDispatch();
  const navigate = useNavigate();
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);

  const requestLogout = () => setOpen(true);
  const cancelLogout = () => {
    if (!loading) {
      setOpen(false);
    }
  };

  const confirmLogout = async () => {
    try {
      setLoading(true);
      await logoutUser(dispatch);
      navigate(ROUTES.LOGIN, { replace: true });
    } finally {
      setLoading(false);
      setOpen(false);
    }
  };

  return {
    logoutOpen: open,
    logoutLoading: loading,
    requestLogout,
    cancelLogout,
    confirmLogout,
  };
}
