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
import { clearOfflineSession, persistOfflineSession, readOfflineSession } from '../../offline/session.js';
import { pullSnapshots } from '../../offline/syncEngine.js';

export async function loginUser(dispatch, { telephone, password, remember = false }) {
  dispatch(setAuthLoading(true));

  try {
    const { token, refreshToken } = await loginApi({ telephone, password });
    const profile = await fetchCurrentUserApi();

    if (remember) {
      setRememberedTelephone(telephone);
    } else {
      clearRememberedTelephone();
    }

    const authState = mapProfileToAuthState(profile, token);
    dispatch(setCredentials(authState));
    await persistOfflineSession({ ...authState, refreshToken });
    pullSnapshots().catch(() => {});

    return { success: true };
  } catch (error) {
    logoutStorage();
    dispatch(clearCredentials());

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
      if (cached?.profile && cached?.token) {
        dispatch(setCredentials(mapProfileToAuthState(cached.profile, cached.token)));
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
