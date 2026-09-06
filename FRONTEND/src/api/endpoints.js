export const auth = {
  login: '/api/v1/login',
  me: '/api/v1/me',
};

export const organisation = {
  departements: '/api/v1/organisation/departements',
  services: '/api/v1/organisation/services',
  lits: '/api/v1/organisation/lits',
  chambres: '/api/v1/organisation/chambres',
  blocs: '/api/v1/organisation/blocs',
};

export const pharmacie = {
  unites: '/api/v1/pharmacie/unites',
  familles: '/api/v1/pharmacie/familles',
  medicaments: '/api/v1/pharmacie/medicaments',
  fournisseurs: '/api/v1/pharmacie/fournisseurs',
  receptions: '/api/v1/pharmacie/receptions',
  lots: '/api/v1/pharmacie/lots',
  mouvements: '/api/v1/pharmacie/mouvements',
  ventes: '/api/v1/pharmacie/ventes',
  visitesHospitalisees: '/api/v1/pharmacie/visites-hospitalisees',
  servicesActifs: '/api/v1/pharmacie/services-actifs',
  demandesService: '/api/v1/pharmacie/demandes-service',
  ajustements: '/api/v1/pharmacie/ajustements',
  recettes: '/api/v1/pharmacie/recettes',
  statistiques: '/api/v1/pharmacie/statistiques',
};

export const referentiel = {
  grades: '/api/v1/referentiel/grades',
  specialites: '/api/v1/referentiel/specialites',
  typesExamen: '/api/v1/referentiel/types-examen',
  typesAntecedent: '/api/v1/referentiel/types-antecedent',
  signesVitaux: '/api/v1/referentiel/signes-vitaux',
  plaintes: '/api/v1/referentiel/plaintes',
};

export const clinique = {
  examens: '/api/v1/clinique/examens',
  maladies: '/api/v1/clinique/maladies',
  visites: '/api/v1/clinique/visites',
  consultations: '/api/v1/clinique/consultations',
  diagnostics: '/api/v1/clinique/diagnostics',
  demandesExamen: '/api/v1/clinique/demandes-examen',
};

export const patient = {
  list: '/api/v1/patients',
};

export const admin = {
  personnel: '/api/v1/admin/personnels',
  personnels: '/api/v1/admin/personnels',
  roles: '/api/v1/admin/roles',
  permissions: '/api/v1/admin/permissions',
  rolePermissions: '/api/v1/admin/role-permissions',
};
