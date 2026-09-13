import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
  DATE_STOCK_OUVERTURE,
  isHistoriqueDate,
  toDateOnly,
  toDateVenteIso,
} from './venteConstants.js';

function ymd(date) {
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${date.getFullYear()}-${month}-${day}`;
}

function shiftDay(isoDay, days) {
  const date = new Date(`${isoDay}T12:00:00`);
  date.setDate(date.getDate() + days);
  return ymd(date);
}

describe('dates envoyées par le .exe', () => {
  const today = ymd(new Date());
  const yesterday = shiftDay(today, -1);

  it('ignore les valeurs vides', () => {
    assert.equal(toDateOnly(null), null);
    assert.equal(toDateOnly(''), null);
    assert.equal(toDateOnly('   '), null);
    assert.equal(toDateOnly('pas-une-date'), null);
  });

  it('garde le jour saisi dans le formulaire (vente antérieure)', () => {
    assert.equal(toDateOnly('2026-08-29'), '2026-08-29');
    assert.equal(toDateOnly(DATE_STOCK_OUVERTURE), '2026-08-28');
    assert.equal(isHistoriqueDate('2026-08-29'), true);
  });

  it('normalise toDateVenteIso() sans fuseau : YYYY-MM-DDTHH:MM:SS', () => {
    assert.equal(toDateOnly('2026-08-29T12:00:00'), '2026-08-29');
    assert.equal(toDateOnly(`${yesterday}T12:00:00`), yesterday);
    assert.equal(toDateOnly(`${today}T12:00:00`), today);
    assert.equal(toDateOnly('2026-08-29 12:00:00'), '2026-08-29');
  });

  it('convertit new Date().toISOString() (UTC Z) en jour local', () => {
    assert.equal(toDateOnly('2026-08-29T12:00:00.000Z'), ymd(new Date('2026-08-29T12:00:00.000Z')));
    assert.equal(toDateOnly('2026-09-11T23:30:00.000Z'), ymd(new Date('2026-09-11T23:30:00.000Z')));
    assert.equal(toDateOnly('2026-09-11T22:30:00.000Z'), ymd(new Date('2026-09-11T22:30:00.000Z')));
  });

  it('le contrat serveur Kinshasa UTC+1 : 23:30Z = 00:30 le lendemain', () => {
    const utc = Date.parse('2026-09-11T23:30:00.000Z');
    const kinshasa = new Date(utc + 60 * 60 * 1000);
    assert.equal(
      `${kinshasa.getUTCFullYear()}-${String(kinshasa.getUTCMonth() + 1).padStart(2, '0')}-${String(kinshasa.getUTCDate()).padStart(2, '0')}`,
      '2026-09-12',
    );
  });

  it('une vente du jour n’est pas historique, une vente d’hier l’est', () => {
    assert.equal(isHistoriqueDate(`${today}T12:00:00`), false);
    assert.equal(isHistoriqueDate(`${yesterday}T12:00:00`), true);
    assert.equal(isHistoriqueDate(new Date().toISOString()), false);
  });

  it('toDateVenteIso reconstruit le format exe T12:00:00', () => {
    assert.equal(toDateVenteIso('2026-08-29'), '2026-08-29T12:00:00');
    assert.equal(toDateVenteIso('2026-08-29T18:44:01.000Z'), '2026-08-29T12:00:00');
  });
});
