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
