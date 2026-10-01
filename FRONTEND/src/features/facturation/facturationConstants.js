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
  { value: 'VALIDEE', label: 'Approuvée', color: 'success' },
  { value: 'ANNULEE', label: 'Annulée', color: 'danger' },
];

export const FACTURE_PAIEMENT_STATUTS = [
  { value: 'NON_PAYEE', label: 'Non payée', color: 'warning' },
  { value: 'PARTIELLE', label: 'Partielle', color: 'primary' },
  { value: 'PAYEE', label: 'Payée', color: 'success' },
];

export const FACTURE_PAIEMENT_LABELS = FACTURE_PAIEMENT_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const FACTURE_PAIEMENT_COLORS = FACTURE_PAIEMENT_STATUTS.reduce((acc, item) => {
  acc[item.value] = item.color;
  return acc;
}, {});

export const REGLEMENT_MODES = [
  { value: 'ESPECES', label: 'Espèces' },
  { value: 'MOBILE', label: 'Mobile money' },
  { value: 'BANQUE', label: 'Banque' },
  { value: 'CHEQUE', label: 'Chèque' },
];

export const REGLEMENT_MODE_LABELS = REGLEMENT_MODES.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

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

function parseAmount(value) {
  const amount = Number(String(value ?? '').replace(/\s/g, '').replace(',', '.'));
  return Number.isNaN(amount) ? 0 : amount;
}

export function prixDiffereDeLaGrille(tarifUnitaire, acte, categorie) {
  if (!acte) return false;
  return Math.abs(parseAmount(tarifUnitaire) - tarifActePour(acte, categorie)) > 0.009;
}

export function repriceLigne(ligne, categorie) {
  const grille = ligne.acte ? tarifActePour(ligne.acte, categorie) : parseAmount(ligne.tarifUnitaire);
  const requested = ligne.prixPersonnalise ? parseAmount(ligne.tarifUnitaire) : grille;
  const prixPersonnalise = Boolean(ligne.prixPersonnalise) && Math.abs(requested - grille) > 0.009;
  const unitaire = prixPersonnalise ? requested : grille;
  const quantite = Math.max(1, Number(ligne.quantite) || 1);
  const tarifBrut = unitaire * quantite;
  const remiseMontant = computeRemise(tarifBrut, ligne.remiseType, ligne.remiseValeur);
  return {
    ...ligne,
    quantite,
    tarifGrille: grille,
    tarifUnitaire: prixPersonnalise ? ligne.tarifUnitaire : unitaire,
    prixPersonnalise,
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

export const ACTE_ORIGINE_GRILLE = 'GRILLE';
export const ACTE_ORIGINE_MANUEL = 'MANUEL';

export const ACTE_ORIGINES = [
  { value: ACTE_ORIGINE_GRILLE, label: 'Grille importée' },
  { value: ACTE_ORIGINE_MANUEL, label: 'Ajouté' },
];

/** Indices appliqués au tarif standard (catégorie A). */
export const INDICE_TARIF_STANDARD = { A0: 0.6, A1: 0.8, B: 1.5, C: 2 };
/** Exception concurrence : B et C seulement. A0 et A1 restent inchangés. */
export const INDICE_TARIF_EXCEPTION = { A0: 0.6, A1: 0.8, B: 1.2, C: 1.4 };

export function indicesTarifActe(exceptionConcurrence = false) {
  return exceptionConcurrence ? INDICE_TARIF_EXCEPTION : INDICE_TARIF_STANDARD;
}

export function formatIndiceTarif(indice) {
  return `× ${String(indice).replace('.', ',')}`;
}

export function serviceGrilleImagerie(serviceGrille) {
  return String(serviceGrille || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .includes('imagerie');
}

export function proposerTarifCategorie(tarifA, indice) {
  const amount = Math.round(parseAmount(tarifA) * Number(indice) * 100) / 100;
  return Number.isInteger(amount) ? String(amount) : amount.toFixed(2);
}

export function proposerTarifsActe(tarifA, exceptionConcurrence = false) {
  const indices = indicesTarifActe(exceptionConcurrence);
  return {
    tarifA0: proposerTarifCategorie(tarifA, indices.A0),
    tarifA1: proposerTarifCategorie(tarifA, indices.A1),
    tarifB: proposerTarifCategorie(tarifA, indices.B),
    tarifC: proposerTarifCategorie(tarifA, indices.C),
  };
}

export function montantsTarifEgaux(left, right) {
  return Math.abs(parseAmount(left) - parseAmount(right)) < 0.009;
}

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
