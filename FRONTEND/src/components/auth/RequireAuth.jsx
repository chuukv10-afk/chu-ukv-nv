import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth.js';
import { ROUTES } from '../../constants/routes.js';

export function RequireAuth() {
  const { isAuthenticated } = useAuth();

  if (!isAuthenticated) {
    return <Navigate to={ROUTES.LOGIN} replace />;
  }

  return <Outlet />;
}
