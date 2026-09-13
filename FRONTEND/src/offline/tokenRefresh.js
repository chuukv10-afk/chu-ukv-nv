import { API_BASE_URL, AUTH_TOKEN_KEY } from '../constants/apiConfig.js';
import { auth } from '../api/endpoints.js';
import { getStoredRefreshToken, persistOfflineSession, readOfflineSession, setStoredRefreshToken } from './session.js';

let refreshPromise = null;

export async function refreshAccessToken() {
  const refreshToken = getStoredRefreshToken();
  if (!refreshToken) {
    return false;
  }
  if (refreshPromise) {
    return refreshPromise;
  }

  refreshPromise = (async () => {
    try {
      const response = await fetch(`${API_BASE_URL}${auth.refresh}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refreshToken }),
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload?.token) {
        return false;
      }
      localStorage.setItem(AUTH_TOKEN_KEY, payload.token);
      setStoredRefreshToken(payload.refreshToken || '');
      const session = await readOfflineSession();
      if (session?.profile) {
        await persistOfflineSession({
          token: payload.token,
          refreshToken: payload.refreshToken || '',
          profile: session.profile,
          roles: session.roles,
          permissions: session.permissions,
        });
      }
      return true;
    } catch {
      return false;
    } finally {
      refreshPromise = null;
    }
  })();

  return refreshPromise;
}
