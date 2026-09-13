import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { mutationNeedsOwnServerId, rankForAction } from './outboxOrder.js';

describe('ordre de synchro ventes', () => {
  it('create_and_valider n’attend pas un id serveur', () => {
    assert.equal(mutationNeedsOwnServerId('pharmacie.vente.create_and_valider'), false);
    assert.equal(mutationNeedsOwnServerId('pharmacie.vente.create_and_bon_pour'), false);
    assert.equal(mutationNeedsOwnServerId('pharmacie.demande_service.create_and_delivrer'), false);
    assert.equal(mutationNeedsOwnServerId('pharmacie.vente.create'), false);
  });

  it('update/valider seuls attendent un id serveur', () => {
    assert.equal(mutationNeedsOwnServerId('pharmacie.vente.update'), true);
    assert.equal(mutationNeedsOwnServerId('pharmacie.vente.valider'), true);
    assert.equal(mutationNeedsOwnServerId('pharmacie.vente.bon_pour'), true);
    assert.equal(mutationNeedsOwnServerId('pharmacie.vente.encaisser'), true);
  });

  it('create_and_valider a le même rang qu’une création de vente', () => {
    assert.equal(rankForAction('pharmacie.vente.create_and_valider'), rankForAction('pharmacie.vente.create'));
    assert.equal(rankForAction('pharmacie.vente.create_and_bon_pour'), rankForAction('pharmacie.vente.create'));
    assert.equal(rankForAction('pharmacie.demande_service.create_and_delivrer'), rankForAction('pharmacie.demande_service.create'));
  });
});
