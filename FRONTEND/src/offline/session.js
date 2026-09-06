import { AUTH_REFRESH_TOKEN_KEY, AUTH_TOKEN_KEY } from '../constants/apiConfig.js';
import { offlineDb, clearOfflineData } from './db.js';

const SESSION_ID = 'current';

export async function persistOfflineSession({ token, refreshToken, profile, roles, permissions }) {
  const nextRefresh = refreshToken ?? localStorage.getItem(AUTH_REFRESH_TOKEN_KEY);
  if (nextRefresh) {
    localStorage.setItem(AUTH_REFRESH_TOKEN_KEY, nextRefresh);
  }
  await offlineDb.session.put({
    id: SESSION_ID,
    token,
    refreshToken: nextRefresh,
    profile,
    roles,
    permissions,
    savedAt: new Date().toISOString(),
  });
}

export async function readOfflineSession() {
  const row = await offlineDb.session.get(SESSION_ID);
  if (!row?.token || !row?.profile) {
    return null;
  }
  return row;
}

export async function clearOfflineSession() {
  localStorage.removeItem(AUTH_TOKEN_KEY);
  localStorage.removeItem(AUTH_REFRESH_TOKEN_KEY);
  await clearOfflineData();
}

export function getStoredRefreshToken() {
  return localStorage.getItem(AUTH_REFRESH_TOKEN_KEY) || '';
}

export function setStoredRefreshToken(token) {
  if (token) {
    localStorage.setItem(AUTH_REFRESH_TOKEN_KEY, token);
  } else {
    localStorage.removeItem(AUTH_REFRESH_TOKEN_KEY);
  }
}
