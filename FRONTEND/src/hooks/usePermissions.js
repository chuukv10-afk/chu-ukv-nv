import { canAccessModule } from '../utils/permissions.js';
import { ROLES } from '../constants/permissions.js';
import { useAuth } from './useAuth.js';

export function usePermissions() {
  const { permissions = [], roles = [], profile } = useAuth();

  const isAdmin = roles.includes(ROLES.ADMIN);

  const hasPermission = (code) => {
    if (!code) {
      return true;
    }

    if (isAdmin) {
      return true;
    }

    return permissions.includes(code);
  };

  const hasAnyPermission = (codes = []) => {
    if (!codes.length) {
      return true;
    }

    return codes.some((code) => hasPermission(code));
  };

  const canReadModule = (modulePrefix) =>
    permissions.some((permission) => permission.startsWith(`${modulePrefix}.`) && permission.endsWith('.read'));

  const canSeeNavItem = (item) => {
    if (item.adminOnly && !isAdmin) {
      return false;
    }

    if (item.module && !canAccessModule(profile?.type, item.module)) {
      return false;
    }

    if (item.permission && !hasPermission(item.permission)) {
      return false;
    }

    return true;
  };

  return {
    permissions,
    roles,
    isAdmin,
    hasPermission,
    hasAnyPermission,
    canRead: canReadModule,
    canSeeNavItem,
  };
}
