import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth.js';
import { ROUTES } from '../../constants/routes.js';

export function GuestRoute() {
  const { isAuthenticated } = useAuth();

  if (isAuthenticated) {
    return <Navigate to={ROUTES.DASHBOARD} replace />;
  }

  return <Outlet />;
}
