export const RECEPTION_STATUTS = [
  { value: 'BROUILLON', label: 'Brouillon', color: 'neutral' },
  { value: 'VALIDEE', label: 'Validée', color: 'success' },
];

export const RECEPTION_STATUT_LABELS = RECEPTION_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const RECEPTION_STATUT_COLORS = RECEPTION_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.color;
  return acc;
}, {});

export const DEFAULT_RECEPTION_PAGE_SIZE = 10;
export const RECEPTION_PAGE_SIZE_OPTIONS = [10, 25, 50];

export const EMPTY_RECEPTION_LIGNE = {
  medicamentId: '',
  numeroLot: '',
  datePeremption: '',
  quantite: '',
  prixAchatUnitaire: '',
  prixVente: '',
};

export function computePrixVenteFromAchat(prixAchat, tauxMarge) {
  const achat = Number(String(prixAchat ?? '').replace(',', '.').trim());
  const taux = Number(String(tauxMarge ?? '').replace(',', '.').trim());
  if (!Number.isFinite(achat) || achat < 0 || !Number.isFinite(taux) || taux < 0) {
    return '';
  }
  const rounded = Math.round(achat * (1 + taux / 100) * 100) / 100;
  if (!Number.isFinite(rounded)) {
    return '';
  }

  return Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(2);
}

export function applyTauxToLignes(lignes, tauxMarge) {
  return (lignes ?? []).map((ligne) => {
    const computed = computePrixVenteFromAchat(ligne.prixAchatUnitaire, tauxMarge);
    return computed === '' ? ligne : { ...ligne, prixVente: computed };
  });
}

export function emptyReceptionForm(dateReception) {
  return {
    fournisseurId: '',
    dateReception,
    referenceExterne: '',
    tauxMarge: '',
    lignes: [{ ...EMPTY_RECEPTION_LIGNE }],
  };
}
