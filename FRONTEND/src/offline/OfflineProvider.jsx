import { createContext, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { getConnectivity, startConnectivityMonitor, subscribeConnectivity } from './connectivity.js';
import { refreshOutboxCounts } from './outbox.js';
import { startSessionKeepAlive } from './sessionKeepAlive.js';
import { runSyncCycle } from './syncEngine.js';

export const OfflineContext = createContext(null);

export default function OfflineProvider({ children }) {
  const [status, setStatus] = useState(getConnectivity());
  const healedOnReachable = useRef(false);

  useEffect(() => {
    const stop = startConnectivityMonitor();
    const unsubscribe = subscribeConnectivity(setStatus);
    refreshOutboxCounts();
    const stopKeepAlive = startSessionKeepAlive();
    return () => {
      stop();
      unsubscribe();
      stopKeepAlive();
    };
  }, []);

  useEffect(() => {
    const reachable = status.online && status.serverReachable;
    if (!reachable) {
      healedOnReachable.current = false;
      return;
    }
    if (status.syncing) return;

    if (status.pending > 0) {
      runSyncCycle().catch(() => {});
      return;
    }

    if (!healedOnReachable.current && status.conflicts > 0) {
      healedOnReachable.current = true;
      runSyncCycle({ retryConflicts: true }).catch(() => {});
    }
  }, [status.online, status.serverReachable, status.pending, status.conflicts, status.syncing]);

  const syncNow = useCallback(async () => {
    await runSyncCycle({ retryConflicts: true });
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
