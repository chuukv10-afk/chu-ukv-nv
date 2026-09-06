import { useContext } from 'react';
import { OfflineContext } from './OfflineProvider.jsx';

export function useOffline() {
  const context = useContext(OfflineContext);
  if (!context) {
    return {
      online: true,
      serverReachable: true,
      syncing: false,
      pending: 0,
      conflicts: 0,
      syncNow: async () => {},
    };
  }
  return context;
}
