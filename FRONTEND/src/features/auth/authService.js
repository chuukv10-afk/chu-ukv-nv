import {
  clearRememberedTelephone,
  fetchCurrentUserApi,
  loginApi,
  logoutStorage,
  mapProfileToAuthState,
  setRememberedTelephone,
} from './authApi.js';
import { AUTH_TOKEN_KEY } from '../../constants/apiConfig.js';
import { clearCredentials, setAuthLoading, setCredentials } from '../../store/auth/authSlice.js';
import { pingServer } from '../../offline/connectivity.js';
import { saveLocalUser, verifyLocalUser } from '../../offline/localUsers.js';
import { clearOfflineSession, persistOfflineSession, readOfflineSession } from '../../offline/session.js';
import { pullSnapshots } from '../../offline/syncEngine.js';

function applyAuthState(dispatch, authState) {
  dispatch(setCredentials(authState));
}

async function loginFromLocal(dispatch, { telephone, password, remember = false }) {
  const local = await verifyLocalUser(telephone, password);
  if (!local?.profile) {
    throw new Error('Compte inconnu hors-ligne. Connectez-vous une première fois lorsque le serveur est joignable.');
  }

  const token = local.token || 'offline-local';
  const authState = mapProfileToAuthState(local.profile, token);
  if (local.refreshToken) {
    authState.refreshToken = local.refreshToken;
  }
  if (remember) {
    setRememberedTelephone(telephone);
  } else {
    clearRememberedTelephone();
  }
  applyAuthState(dispatch, authState);
  await persistOfflineSession({ ...authState, refreshToken: local.refreshToken });
  return { success: true, offline: true };
}

export async function loginUser(dispatch, { telephone, password, remember = false }) {
  dispatch(setAuthLoading(true));

  try {
    const reachable = await pingServer();
    if (!reachable) {
      return loginFromLocal(dispatch, { telephone, password, remember });
    }

    try {
      const { token, refreshToken } = await loginApi({ telephone, password });
      const profile = await fetchCurrentUserApi();

      if (remember) {
        setRememberedTelephone(telephone);
      } else {
        clearRememberedTelephone();
      }

      const authState = mapProfileToAuthState(profile, token);
      applyAuthState(dispatch, authState);
      await persistOfflineSession({ ...authState, refreshToken });
      await saveLocalUser({
        telephone: telephone || profile?.telephone,
        password,
        profile,
        roles: authState.roles,
        permissions: authState.permissions,
        token,
        refreshToken,
      });
      pullSnapshots().catch(() => {});

      return { success: true };
    } catch (error) {
      const networkDown = error?.offline || error?.message === 'Failed to fetch' || error instanceof TypeError;
      if (networkDown) {
        return loginFromLocal(dispatch, { telephone, password, remember });
      }
      logoutStorage();
      dispatch(clearCredentials());
      throw error;
    }
  } catch (error) {
    if (!error?.offline && error?.message?.includes('hors-ligne')) {
      dispatch(clearCredentials());
    }
    throw error;
  } finally {
    dispatch(setAuthLoading(false));
  }
}

export async function fetchMe(dispatch) {
  try {
    dispatch(setAuthLoading(true));
    const profile = await fetchCurrentUserApi();
    const token = localStorage.getItem(AUTH_TOKEN_KEY);
    const authState = mapProfileToAuthState(profile, token);
    dispatch(setCredentials(authState));
    await persistOfflineSession(authState);
    pullSnapshots().catch(() => {});

    return { success: true };
  } catch (error) {
    const canUseCache = error?.offline || error?.status >= 500 || error?.message === 'Failed to fetch';
    if (canUseCache) {
      const cached = await readOfflineSession();
      if (cached?.profile) {
        dispatch(setCredentials(mapProfileToAuthState(cached.profile, cached.token || 'offline-local')));
        return { success: true, offline: true };
      }
    }

    logoutStorage();
    dispatch(clearCredentials());
    return { success: false, error };
  } finally {
    dispatch(setAuthLoading(false));
  }
}

export async function logoutUser(dispatch) {
  logoutStorage();
  await clearOfflineSession();
  dispatch(clearCredentials());
}
