// Laisser vide en dev pour utiliser le proxy Vite (/api → backend).
export const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? '';

export const AUTH_TOKEN_KEY = 'chu_ukv_token';
export const AUTH_REFRESH_TOKEN_KEY = 'chu_ukv_refresh_token';
