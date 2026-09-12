import { readNamedCache } from './cache.js';

function namedItems(raw) {
  if (Array.isArray(raw)) return raw;
  if (Array.isArray(raw?.items)) return raw.items;
  if (Array.isArray(raw?.data)) return raw.data;
  if (Array.isArray(raw?.data?.items)) return raw.data.items;
  return [];
}

export async function priceVenteLignes(lignes = []) {
  const medicaments = namedItems(await readNamedCache('pharmacie.medicaments'));
  const byId = new Map(medicaments.map((item) => [String(item.id), item]));
  const priced = lignes.map((ligne) => {
    const medicament = byId.get(String(ligne.medicamentId));
    const override = ligne.prixUnitaire !== undefined && ligne.prixUnitaire !== null && ligne.prixUnitaire !== '';
    const prix = Number(override ? ligne.prixUnitaire : (medicament?.prixVente ?? 0));
    const quantite = Number(ligne.quantite || 0);
    const prixTotal = prix * quantite;
    return {
      ...ligne,
      prixUnitaire: String(prix),
      prixTotal: String(prixTotal),
      medicament: medicament
        ? { id: medicament.id, code: medicament.code, libelle: medicament.libelle }
        : ligne.medicament,
    };
  });
  const montantTotal = priced.reduce((sum, ligne) => sum + Number(ligne.prixTotal || 0), 0);
  return {
    lignes: priced,
    montantTotal: String(montantTotal),
  };
}
