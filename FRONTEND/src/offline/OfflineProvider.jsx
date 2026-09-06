import { createContext, useCallback, useEffect, useMemo, useState } from 'react';
import { getConnectivity, startConnectivityMonitor, subscribeConnectivity } from './connectivity.js';
import { refreshOutboxCounts } from './outbox.js';
import { runSyncCycle } from './syncEngine.js';

export const OfflineContext = createContext(null);

export default function OfflineProvider({ children }) {
  const [status, setStatus] = useState(getConnectivity());

  useEffect(() => {
    const stop = startConnectivityMonitor();
    const unsubscribe = subscribeConnectivity(setStatus);
    refreshOutboxCounts();
    return () => {
      stop();
      unsubscribe();
    };
  }, []);

  useEffect(() => {
    if (status.online && status.serverReachable && status.pending > 0 && !status.syncing) {
      runSyncCycle().catch(() => {});
    }
  }, [status.online, status.serverReachable, status.pending, status.syncing]);

  const syncNow = useCallback(async () => {
    await runSyncCycle();
  }, []);

  const value = useMemo(() => ({
    ...status,
    syncNow,
  }), [status, syncNow]);

  return (
    <OfflineContext.Provider value={value}>
      {children}
    </OfflineContext.Provider>
  );
}
