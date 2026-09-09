// Laisser vide en dev web pour utiliser le proxy Vite (/api → backend).
// En Electron, l’URL est injectée au boot depuis la config SQLite du poste.
export let API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? '';

export function setApiBaseUrl(url = '') {
  API_BASE_URL = String(url || '').replace(/\/$/, '');
}

export const AUTH_TOKEN_KEY = 'chu_ukv_token';
export const AUTH_REFRESH_TOKEN_KEY = 'chu_ukv_refresh_token';
