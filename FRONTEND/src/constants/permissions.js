export const PERMISSIONS = {
  ORGANISATION: {
    DEPARTEMENT_READ: 'organisation.departement.read',
    SERVICE_READ: 'organisation.service.read',
    LIT_READ: 'organisation.lit.read',
    CHAMBRE_READ: 'organisation.chambre.read',
    BLOC_READ: 'organisation.bloc.read',
  },
  REFERENTIEL: {
    GRADE_READ: 'referentiel.grade.read',
    SPECIALITE_READ: 'referentiel.specialite.read',
    TYPE_EXAMEN_READ: 'referentiel.type_examen.read',
    TYPE_ANTECEDENT_READ: 'referentiel.type_antecedent.read',
  },
  CLINIQUE: {
    EXAMEN_READ: 'clinique.examen.read',
  },
};

export const ROLES = {
  ADMIN: 'ROLE_ADMIN',
  PERSONNEL: 'ROLE_PERSONNEL',
};
