import { AUTH_TOKEN_KEY } from '../../constants/apiConfig.js';
import { ROUTES } from '../../constants/routes.js';
import { store } from '../../store/index.js';
import { clearCredentials } from '../../store/auth/authSlice.js';

const LOGIN_ENDPOINT = '/api/v1/login';

let redirecting = false;

export function handleUnauthorizedApiResponse(status, endpoint = '') {
  if (status !== 401 || endpoint.includes(LOGIN_ENDPOINT)) {
    return false;
  }

  if (redirecting) {
    return true;
  }

  redirecting = true;
  localStorage.removeItem(AUTH_TOKEN_KEY);
  store.dispatch(clearCredentials());

  const loginUrl = `${ROUTES.LOGIN}?session=expired`;
  if (window.location.pathname !== ROUTES.LOGIN) {
    window.location.replace(loginUrl);
  } else {
    redirecting = false;
  }

  return true;
}

export function createSessionExpiredError() {
  const error = new Error('Votre session a expiré. Veuillez vous reconnecter.');
  error.status = 401;
  error.sessionExpired = true;
  return error;
}
