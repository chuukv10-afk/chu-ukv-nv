import { AUTH_TOKEN_KEY } from '../../constants/apiConfig.js';
import { auth } from '../../api/endpoints.js';
import { callApiGet, callApiPost } from '../../api/apiClient.js';
import { setStoredRefreshToken } from '../../offline/session.js';

const REMEMBER_TELEPHONE_KEY = 'chu_ukv_remember_telephone';

export async function loginApi({ telephone, password }) {
  const data = await callApiPost(auth.login, { telephone, password });

  if (!data?.token) {
    throw new Error('Aucun token reçu du serveur.');
  }

  localStorage.setItem(AUTH_TOKEN_KEY, data.token);
  if (data.refreshToken) {
    setStoredRefreshToken(data.refreshToken);
  }

  return data;
}

export async function fetchCurrentUserApi() {
  const response = await callApiGet(auth.me);
  return response.data ?? response;
}

export function getRememberedTelephone() {
  return localStorage.getItem(REMEMBER_TELEPHONE_KEY) || '';
}

export function setRememberedTelephone(telephone) {
  localStorage.setItem(REMEMBER_TELEPHONE_KEY, telephone);
}

export function clearRememberedTelephone() {
  localStorage.removeItem(REMEMBER_TELEPHONE_KEY);
}

export function logoutStorage() {
  localStorage.removeItem(AUTH_TOKEN_KEY);
  setStoredRefreshToken('');
}

function splitRoles(rawRoles = []) {
  const roles = [];
  const permissions = [];

  rawRoles.forEach((role) => {
    if (role.startsWith('ROLE_')) {
      roles.push(role);
      return;
    }

    if (role.includes('.')) {
      permissions.push(role);
    }
  });

  return { roles, permissions };
}

export function mapProfileToAuthState(profile, token) {
  const fromRoles = splitRoles(profile?.roles ?? []);
  const explicit = Array.isArray(profile?.permissions) ? profile.permissions : [];
  const permissions = [...new Set([...fromRoles.permissions, ...explicit])]
    .map((permission) => String(permission).toLowerCase());

  return {
    token,
    profile,
    roles: fromRoles.roles,
    permissions,
  };
}
