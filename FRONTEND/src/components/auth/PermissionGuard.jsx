import { Navigate } from 'react-router-dom';
import { usePermissions } from '../../hooks/usePermissions.js';
import { ROUTES } from '../../constants/routes.js';

export function PermissionGuard({ permission, children }) {
  const { hasPermission } = usePermissions();

  if (!hasPermission(permission)) {
    return <Navigate to={ROUTES.ACCESS_DENIED} replace />;
  }

  return children;
}
