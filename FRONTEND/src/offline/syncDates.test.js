import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { sanitizeSyncDates } from './syncDates.js';

describe('sanitizeSyncDates — file d’attente .exe', () => {
  it('normalise une vente antérieure T12:00:00 sans flag historique', () => {
    const next = sanitizeSyncDates({
      dateVente: '2026-08-29T12:00:00',
      modePaiement: 'ESPECES',
    });
    assert.equal(next.dateVente, '2026-08-29');
  });

  it('retire dateVente pour une vente du jour (ISO Z)', () => {
    const next = sanitizeSyncDates({
      dateVente: new Date().toISOString(),
      modePaiement: 'ESPECES',
    });
    assert.equal(next.dateVente, undefined);
  });
});
