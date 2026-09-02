import { useAppSelector } from './useAppStore.js';

export function useAuth() {
  const auth = useAppSelector((state) => state.auth);

  return {
    ...auth,
    roles: auth.roles ?? [],
    permissions: auth.permissions ?? [],
    isMedical: auth.profile?.type === 'MEDICAL' || auth.profile?.type === 'PARAMEDICAL',
  };
}
