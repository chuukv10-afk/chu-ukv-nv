import { Navigate, useLocation } from 'react-router-dom';
import { usePermissions } from '../../hooks/usePermissions.js';
import { ROUTES } from '../../constants/routes.js';
import { buildAccessDeniedMessage } from '../../utils/permissionLabels.js';

export function PermissionGuard({ permission, children }) {
  const { hasPermission } = usePermissions();
  const location = useLocation();

  if (!hasPermission(permission)) {
    return (
      <Navigate
        to={ROUTES.ACCESS_DENIED}
        replace
        state={{
          permission,
          message: buildAccessDeniedMessage(permission),
          from: location.pathname,
        }}
      />
    );
  }

  return children;
}
