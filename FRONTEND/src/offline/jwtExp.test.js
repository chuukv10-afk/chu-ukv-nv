import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { decodeJwtExp } from './jwtExp.js';

describe('session JWT', () => {
  it('lit la date d’expiration du jeton', () => {
    const exp = 1_800_000_000;
    const payload = btoa(JSON.stringify({ exp, roles: ['ROLE_PERSONNEL'] }));
    const token = `aaa.${payload}.sig`;
    assert.equal(decodeJwtExp(token), exp * 1000);
  });

  it('ignore un jeton vide ou invalide', () => {
    assert.equal(decodeJwtExp(''), null);
    assert.equal(decodeJwtExp('pas-un-jwt'), null);
    assert.equal(decodeJwtExp(null), null);
  });
});
