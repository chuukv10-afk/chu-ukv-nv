import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { DATE_STOCK_OUVERTURE } from '../features/pharmacie/ventes/venteConstants.js';
import { assertDemandePayload, assertVentePayload } from './pharmacyRules.js';

function today() {
  const now = new Date();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  return `${now.getFullYear()}-${month}-${day}`;
}

function shift(isoDay, days) {
  const date = new Date(`${isoDay}T12:00:00`);
  date.setDate(date.getDate() + days);
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${date.getFullYear()}-${month}-${day}`;
}

function ligne(extra = {}) {
  return { medicamentId: 12, quantite: 2, ...extra };
}

function passant(extra = {}) {
  return {
    clientType: 'PASSANT',
    clientNom: 'Kabila Jean',
    modePaiement: 'ESPECES',
    lignes: [ligne()],
    ...extra,
  };
}

function throws(fn, needle) {
  assert.throws(fn, (error) => String(error.message).includes(needle));
}

describe('règles vente (caisse / exe)', () => {
  it('refuse un passant sans nom', () => {
    throws(() => assertVentePayload(passant({ clientNom: '' })), 'passant');
  });

  it('refuse un patient sans id', () => {
    throws(
      () => assertVentePayload({ clientType: 'PATIENT', modePaiement: 'ESPECES', lignes: [ligne()] }),
      'patient',
    );
  });

  it('accepte un hospitalisé via visiteId', () => {
    assertVentePayload({
      clientType: 'HOSPITALISE',
      visiteId: 44,
      modePaiement: 'MOBILE',
      lignes: [ligne()],
    });
  });

  it('refuse sans ligne ou quantité nulle', () => {
    throws(() => assertVentePayload(passant({ lignes: [] })), 'ligne');
    throws(() => assertVentePayload(passant({ lignes: [ligne({ quantite: 0 })] })), 'quantité');
  });

  it('refuse une date antérieure = aujourd’hui ou future', () => {
    throws(() => assertVentePayload(passant({ dateVente: today() })), 'antérieure à aujourd');
    throws(() => assertVentePayload(passant({ dateVente: shift(today(), 1) })), 'antérieure à aujourd');
  });

  it('refuse une date avant le stock d’ouverture', () => {
    throws(
      () => assertVentePayload(passant({ dateVente: '2026-08-27' })),
      '28/08/2026',
    );
  });

  it('accepte une vente antérieure au 28/08 avec prix du jour', () => {
    assertVentePayload(passant({
      dateVente: DATE_STOCK_OUVERTURE,
      lignes: [ligne({ prixUnitaire: 750 })],
    }));
  });

  it('refuse un prix du jour négatif', () => {
    throws(
      () => assertVentePayload(passant({
        dateVente: shift(today(), -1),
        lignes: [ligne({ prixUnitaire: -1 })],
      })),
      'prix',
    );
  });

  it('accepte une vente du jour sans date (exe caisse live)', () => {
    assertVentePayload(passant());
  });
});

function demande(extra = {}) {
  return {
    serviceId: 3,
    motif: 'Rattrapage',
    lignes: [ligne()],
    ...extra,
  };
}

describe('règles demande de service antérieure', () => {
  it('refuse une date d’approvisionnement = aujourd’hui ou future', () => {
    throws(() => assertDemandePayload(demande({ dateLivraison: today() })), 'antérieure à aujourd');
    throws(() => assertDemandePayload(demande({ dateLivraison: shift(today(), 1) })), 'antérieure à aujourd');
  });

  it('refuse une date avant le stock d’ouverture', () => {
    throws(
      () => assertDemandePayload(demande({ dateLivraison: '2026-08-27' })),
      '28/08/2026',
    );
  });

  it('accepte un approvisionnement antérieur au 28/08 avec prix du jour', () => {
    assertDemandePayload(demande({
      dateLivraison: DATE_STOCK_OUVERTURE,
      lignes: [ligne({ prixUnitaire: 750 })],
    }));
  });

  it('refuse un prix du jour négatif', () => {
    throws(
      () => assertDemandePayload(demande({
        dateLivraison: shift(today(), -1),
        lignes: [ligne({ prixUnitaire: -1 })],
      })),
      'prix',
    );
  });
});
