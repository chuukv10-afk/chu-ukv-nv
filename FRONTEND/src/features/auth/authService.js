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

export async function loginUser(dispatch, { telephone, password, remember = false }) {
  dispatch(setAuthLoading(true));

  try {
    const { token } = await loginApi({ telephone, password });
    const profile = await fetchCurrentUserApi();

    if (remember) {
      setRememberedTelephone(telephone);
    } else {
      clearRememberedTelephone();
    }

    dispatch(setCredentials(mapProfileToAuthState(profile, token)));

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

    dispatch(setCredentials(mapProfileToAuthState(profile, token)));

    return { success: true };
  } catch {
    logoutStorage();
    dispatch(clearCredentials());
    return { success: false };
  } finally {
    dispatch(setAuthLoading(false));
  }
}

export function logoutUser(dispatch) {
  logoutStorage();
  dispatch(clearCredentials());
}
