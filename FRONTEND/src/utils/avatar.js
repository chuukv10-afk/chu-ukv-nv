import { API_BASE_URL, AUTH_TOKEN_KEY } from '../constants/apiConfig.js';

const avatarBlobCache = new Map();

export const AVATAR_ACCEPT = 'image/jpeg,image/png,image/webp';
export const AVATAR_MAX_SIZE_BYTES = 2 * 1024 * 1024;

export function validateAvatarFile(file) {
  if (!file) {
    return null;
  }

  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
    return 'Format non supporté. Utilisez JPG, PNG ou WebP.';
  }

  if (file.size > AVATAR_MAX_SIZE_BYTES) {
    return 'La photo ne doit pas dépasser 2 Mo.';
  }

  return null;
}

export function invalidateAvatarCache(src) {
  if (!src) {
    return;
  }

  const cachedUrl = avatarBlobCache.get(src);
  if (cachedUrl) {
    URL.revokeObjectURL(cachedUrl);
    avatarBlobCache.delete(src);
  }
}

export async function fetchAuthenticatedAvatarUrl(src) {
  if (!src) {
    return null;
  }

  if (avatarBlobCache.has(src)) {
    return avatarBlobCache.get(src);
  }

  const token = localStorage.getItem(AUTH_TOKEN_KEY);
  const response = await fetch(`${API_BASE_URL}${src}`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  });

  if (!response.ok) {
    return null;
  }

  const blob = await response.blob();
  const objectUrl = URL.createObjectURL(blob);
  avatarBlobCache.set(src, objectUrl);

  return objectUrl;
}

export function readFilePreview(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(typeof reader.result === 'string' ? reader.result : null);
    reader.onerror = () => reject(new Error('Impossible de lire la photo sélectionnée.'));
    reader.readAsDataURL(file);
  });
}
