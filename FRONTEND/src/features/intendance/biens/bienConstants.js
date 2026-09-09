export const BIEN_ETATS = [
  { value: 'F', label: 'Fonctionnel', color: 'success' },
  { value: 'FP', label: 'Fonctionnel avec réserve', color: 'warning' },
  { value: 'P', label: 'En panne', color: 'danger' },
  { value: 'H.U', label: 'Hors usage', color: 'neutral' },
  { value: 'R', label: 'Réformé', color: 'neutral' },
  { value: 'M', label: 'Manquant', color: 'warning' },
];

export const BIEN_ETAT_LABELS = BIEN_ETATS.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const BIEN_ETAT_COLORS = BIEN_ETATS.reduce((acc, item) => {
  acc[item.value] = item.color;
  return acc;
}, {});

export const HISTORIQUE_TYPE_LABELS = {
  CREATION: 'Création',
  MODIFICATION: 'Modification',
  MODIFICATION_CODE: 'Changement de code',
  TRANSFERT: 'Transfert',
  CHANGEMENT_LOCAL: 'Changement de local',
  CHANGEMENT_ETAT: 'Changement d’état',
  REFORME: 'Réforme',
  SUPPRESSION: 'Suppression',
};

export const DEFAULT_BIEN_PAGE_SIZE = 20;
export const BIEN_PAGE_SIZE_OPTIONS = [10, 20, 50];

export const EMPTY_BIEN_FORM = {
  copies: 1,
  typeId: '',
  serviceId: '',
  localId: '',
  codeInventaire: '',
  codes: [],
  precision: '',
  marque: '',
  modele: '',
  numeroSerie: '',
  complementLocalisation: '',
  etat: 'F',
  dateAcquisition: '',
  observation: '',
};
