export const ROLE_PERIMETRES = [
  { value: 'GLOBAL', label: 'Global — tout l\'établissement' },
  { value: 'DEPARTEMENT', label: 'Département — ciblage par département' },
  { value: 'SERVICE', label: 'Service — ciblage par service' },
];

export const ROLE_PERIMETRE_LABELS = {
  GLOBAL: 'Global',
  DEPARTEMENT: 'Département',
  SERVICE: 'Service',
};

export const EMPTY_ROLE_FORM = {
  code: '',
  libelle: '',
  perimetre: 'GLOBAL',
};
