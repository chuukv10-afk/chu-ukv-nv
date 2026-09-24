export const STRUCTURE_TYPES = [
  { value: 'MUTUELLE', label: 'Mutuelle' },
  { value: 'ASSURANCE', label: 'Assurance' },
  { value: 'ONG', label: 'ONG' },
  { value: 'ENTREPRISE', label: 'Entreprise' },
  { value: 'PARTENAIRE', label: 'Partenaire' },
];

export const STRUCTURE_TYPE_LABELS = STRUCTURE_TYPES.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const STRUCTURE_STATUTS = [
  { value: 'ACTIF', label: 'Actif', color: 'success' },
  { value: 'INACTIF', label: 'Inactif', color: 'neutral' },
];

export const STRUCTURE_STATUT_LABELS = STRUCTURE_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const CATEGORIES_TARIFAIRES = [
  { value: 'A0', label: 'A0 — Indigent', requiresStructure: false, structureTypes: [] },
  { value: 'A1', label: 'A1 — Mutuelle locale', requiresStructure: true, structureTypes: ['MUTUELLE'] },
  { value: 'A', label: 'A — Tarif standard', requiresStructure: false, structureTypes: [] },
  { value: 'B', label: 'B — Privé', requiresStructure: false, structureTypes: [] },
  { value: 'C', label: 'C — Sponsorisé / assurance', requiresStructure: true, structureTypes: ['ASSURANCE', 'ONG', 'ENTREPRISE', 'PARTENAIRE'] },
];

export const CATEGORIE_TARIFAIRE_LABELS = CATEGORIES_TARIFAIRES.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export function categorieDefinition(code) {
  return CATEGORIES_TARIFAIRES.find((item) => item.value === code) ?? null;
}

export function structureRequiredFor(code) {
  return Boolean(categorieDefinition(code)?.requiresStructure);
}

export function allowedStructureTypesFor(code) {
  return categorieDefinition(code)?.structureTypes ?? [];
}

export const DEFAULT_STRUCTURE_PAGE_SIZE = 10;
export const STRUCTURE_PAGE_SIZE_OPTIONS = [10, 25, 50];

export const EMPTY_STRUCTURE_FORM = {
  code: '',
  libelle: '',
  type: 'MUTUELLE',
  telephone: '',
  adresse: '',
  statut: 'ACTIF',
};

export const DEFAULT_CATEGORIE_TARIFAIRE = 'A';

export const FACTURE_STATUTS = [
  { value: 'BROUILLON', label: 'Brouillon', color: 'neutral' },
  { value: 'VALIDEE', label: 'Validée', color: 'success' },
  { value: 'ANNULEE', label: 'Annulée', color: 'danger' },
];

export const FACTURE_STATUT_LABELS = FACTURE_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const FACTURE_STATUT_COLORS = FACTURE_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.color;
  return acc;
}, {});

export const DEFAULT_FACTURE_PAGE_SIZE = 10;
export const FACTURE_PAGE_SIZE_OPTIONS = [10, 25, 50];

export function tarifActePour(acte, categorie) {
  const key = {
    A0: 'tarifA0',
    A1: 'tarifA1',
    A: 'tarifA',
    B: 'tarifB',
    C: 'tarifC',
  }[categorie] || 'tarifA';
  const amount = Number(acte?.[key] ?? 0);
  return Number.isNaN(amount) ? 0 : amount;
}

export function formatFc(value) {
  const amount = Number(value);
  if (value === null || value === undefined || value === '' || Number.isNaN(amount)) return '—';
  return `${amount.toLocaleString('fr-CD', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} FC`;
}

export function todayIsoKinshasa() {
  const now = new Date();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  return `${now.getFullYear()}-${month}-${day}`;
}

export function patientLabel(patient) {
  if (!patient) return '';
  return patient.fullName || [patient.nom, patient.postNom, patient.prenom].filter(Boolean).join(' ');
}

export const REMISE_NONE = 'NONE';
export const REMISE_TYPES = [
  { value: 'NONE', label: 'Sans remise' },
  { value: 'POURCENTAGE', label: 'Pourcentage' },
  { value: 'MONTANT', label: 'Montant fixe' },
];

export function emptyRemise() {
  return { remiseType: REMISE_NONE, remiseValeur: '' };
}

export function computeRemise(brut, type, valeur) {
  const base = Number(brut) || 0;
  const amount = Number(String(valeur ?? '').replace(',', '.')) || 0;
  if (!base || amount <= 0 || type === REMISE_NONE || !type) return 0;
  if (type === 'POURCENTAGE') return Math.min(base, (base * Math.min(amount, 100)) / 100);
  return Math.min(base, amount);
}

export function formatRemiseLabel(type, valeur, montant) {
  if (!type || type === REMISE_NONE || !(Number(montant) > 0)) return null;
  if (type === 'POURCENTAGE') return `−${Number(valeur) || 0} % (${formatFc(montant)})`;
  return `−${formatFc(montant)}`;
}

export function emptyFactureForm(dateFacture = todayIsoKinshasa()) {
  return {
    patientId: '',
    dateFacture,
    categorieTarifaire: DEFAULT_CATEGORIE_TARIFAIRE,
    structureId: '',
    numeroAffiliation: '',
    notes: '',
    remiseType: REMISE_NONE,
    remiseValeur: '',
    lignes: [],
  };
}

export function repriceLigne(ligne, categorie) {
  const unitaire = ligne.acte ? tarifActePour(ligne.acte, categorie) : Number(ligne.tarifUnitaire ?? 0);
  const quantite = Math.max(1, Number(ligne.quantite) || 1);
  const tarifBrut = unitaire * quantite;
  const remiseMontant = computeRemise(tarifBrut, ligne.remiseType, ligne.remiseValeur);
  return {
    ...ligne,
    quantite,
    tarifUnitaire: unitaire,
    tarifBrut,
    remiseType: ligne.remiseType || REMISE_NONE,
    remiseValeur: ligne.remiseType && ligne.remiseType !== REMISE_NONE ? ligne.remiseValeur : '',
    remiseMontant,
    tarifTotal: tarifBrut - remiseMontant,
  };
}

export function repriceLignes(lignes, categorie) {
  return (lignes ?? []).map((ligne) => repriceLigne(ligne, categorie));
}

export function computeFactureTotals(form) {
  const lignes = form.lignes ?? [];
  const montantBrut = lignes.reduce((sum, ligne) => sum + Number(ligne.tarifBrut || 0), 0);
  const remiseLignes = lignes.reduce((sum, ligne) => sum + Number(ligne.remiseMontant || 0), 0);
  const sousTotal = montantBrut - remiseLignes;
  const remiseGlobale = computeRemise(sousTotal, form.remiseType, form.remiseValeur);
  return {
    montantBrut,
    remiseLignes,
    sousTotal,
    remiseGlobale,
    montantTotal: sousTotal - remiseGlobale,
  };
}

export const DEFAULT_ACTE_PAGE_SIZE = 25;
export const ACTE_PAGE_SIZE_OPTIONS = [10, 25, 50, 100];

export const ACTE_STATUTS = [
  { value: 'ACTIF', label: 'Actif', color: 'success' },
  { value: 'INACTIF', label: 'Inactif', color: 'neutral' },
];

export const EMPTY_ACTE_FORM = {
  code: '',
  serviceGrille: '',
  sousCategorie: '',
  libelle: '',
  tarifA0: '0',
  tarifA1: '0',
  tarifA: '0',
  tarifB: '0',
  tarifC: '0',
  statut: 'ACTIF',
};
