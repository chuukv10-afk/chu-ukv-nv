import { offlineDb } from './db.js';

const ITERATIONS = 120000;

function normalizeTelephone(telephone = '') {
  return String(telephone).replace(/\s+/g, '').trim();
}

function bytesToHex(buffer) {
  return [...new Uint8Array(buffer)].map((byte) => byte.toString(16).padStart(2, '0')).join('');
}

function hexToBytes(hex) {
  const clean = String(hex || '');
  const bytes = new Uint8Array(clean.length / 2);
  for (let i = 0; i < bytes.length; i += 1) {
    bytes[i] = parseInt(clean.slice(i * 2, i * 2 + 2), 16);
  }
  return bytes;
}

async function derivePasswordHash(password, saltBytes) {
  if (!globalThis.crypto?.subtle) {
    throw new Error('Impossible de sécuriser le mot de passe hors-ligne sur ce navigateur.');
  }
  const key = await crypto.subtle.importKey(
    'raw',
    new TextEncoder().encode(password),
    'PBKDF2',
    false,
    ['deriveBits'],
  );
  const bits = await crypto.subtle.deriveBits(
    { name: 'PBKDF2', salt: saltBytes, iterations: ITERATIONS, hash: 'SHA-256' },
    key,
    256,
  );
  return bytesToHex(bits);
}

export async function saveLocalUser({
  telephone,
  password,
  profile,
  roles,
  permissions,
  token,
  refreshToken,
}) {
  const phone = normalizeTelephone(telephone || profile?.telephone);
  if (!phone || !password) {
    return;
  }

  const saltBytes = crypto.getRandomValues(new Uint8Array(16));
  const passwordHash = await derivePasswordHash(password, saltBytes);

  await offlineDb.localUsers.put({
    telephone: phone,
    passwordSalt: bytesToHex(saltBytes),
    passwordHash,
    iterations: ITERATIONS,
    profile,
    roles: roles || [],
    permissions: permissions || [],
    token: token || '',
    refreshToken: refreshToken || '',
    savedAt: new Date().toISOString(),
  });
}

export async function verifyLocalUser(telephone, password) {
  const row = await offlineDb.localUsers.get(normalizeTelephone(telephone));
  if (!row?.passwordHash || !row?.passwordSalt) {
    return null;
  }
  const hash = await derivePasswordHash(password, hexToBytes(row.passwordSalt));
  if (hash !== row.passwordHash) {
    return null;
  }
  return row;
}

export async function hasLocalUser(telephone) {
  const row = await offlineDb.localUsers.get(normalizeTelephone(telephone));
  return Boolean(row?.passwordHash);
}
