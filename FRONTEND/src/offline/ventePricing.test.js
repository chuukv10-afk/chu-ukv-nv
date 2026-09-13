import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { priceLignes } from './ventePricing.js';

const catalogue = [{ id: 7, code: 'PARA', libelle: 'Paracétamol', prixVente: 1500 }];

describe('prix de vente (catalogue vs prix du jour exe)', () => {
  it('vente du jour : le catalogue l’emporte si aucun prix saisi', () => {
    const result = priceLignes([{ medicamentId: 7, quantite: 2 }], catalogue);
    assert.equal(result.lignes[0].prixUnitaire, '1500');
    assert.equal(result.montantTotal, '3000');
  });

  it('vente antérieure : le prix saisi ce jour-là est conservé', () => {
    const result = priceLignes(
      [{ medicamentId: 7, quantite: 3, prixUnitaire: 900 }],
      catalogue,
    );
    assert.equal(result.lignes[0].prixUnitaire, '900');
    assert.equal(result.lignes[0].prixTotal, '2700');
    assert.equal(result.montantTotal, '2700');
  });

  it('vente antérieure : prix vide → retombe sur le catalogue', () => {
    const result = priceLignes(
      [{ medicamentId: 7, quantite: 1, prixUnitaire: '' }],
      catalogue,
    );
    assert.equal(result.lignes[0].prixUnitaire, '1500');
  });

  it('médicament inconnu sans prix : 0', () => {
    const result = priceLignes([{ medicamentId: 99, quantite: 2 }], catalogue);
    assert.equal(result.montantTotal, '0');
  });
});
