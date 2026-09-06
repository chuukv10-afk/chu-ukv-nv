import { API_BASE_URL } from '../constants/apiConfig.js';
import { auth } from '../api/endpoints.js';

const listeners = new Set();

let state = {
  online: typeof navigator !== 'undefined' ? navigator.onLine : true,
  serverReachable: true,
  syncing: false,
  pending: 0,
  conflicts: 0,
};

export function getConnectivity() {
  return { ...state };
}

export function isServerReachable() {
  return state.online && state.serverReachable;
}

export function subscribeConnectivity(listener) {
  listeners.add(listener);
  listener(getConnectivity());
  return () => listeners.delete(listener);
}

function emit() {
  const snapshot = getConnectivity();
  listeners.forEach((listener) => listener(snapshot));
}

export function setConnectivityPatch(patch) {
  state = { ...state, ...patch };
  emit();
}

export async function pingServer() {
  try {
    const response = await fetch(`${API_BASE_URL}${auth.health}`, { method: 'GET', cache: 'no-store' });
    setConnectivityPatch({ online: true, serverReachable: response.ok });
    return response.ok;
  } catch {
    setConnectivityPatch({ serverReachable: false });
    return false;
  }
}

export function startConnectivityMonitor() {
  const onOnline = () => {
    setConnectivityPatch({ online: true });
    pingServer();
  };
  const onOffline = () => setConnectivityPatch({ online: false, serverReachable: false });

  window.addEventListener('online', onOnline);
  window.addEventListener('offline', onOffline);
  pingServer();
  const timer = window.setInterval(pingServer, 15000);

  return () => {
    window.removeEventListener('online', onOnline);
    window.removeEventListener('offline', onOffline);
    window.clearInterval(timer);
  };
}
