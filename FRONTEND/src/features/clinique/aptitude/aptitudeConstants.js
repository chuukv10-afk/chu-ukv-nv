export const DEFAULT_APTITUDE_PAGE_SIZE = 10;
export const APTITUDE_PAGE_SIZE_OPTIONS = [10, 25, 50];

export const APTITUDE_STATUTS = ['BROUILLON', 'SIGNE', 'ANNULE'];
export const APTITUDE_STATUT_LABELS = {
  BROUILLON: 'Brouillon',
  SIGNE: 'Signé',
  ANNULE: 'Annulé',
};
export const APTITUDE_STATUT_COLORS = {
  BROUILLON: 'neutral',
  SIGNE: 'success',
  ANNULE: 'danger',
};

export const APTITUDE_MOTIFS = [
  { value: 'ADMISSION_UKV', label: 'Admission Universitaire UKV' },
  { value: 'EMPLOI', label: 'Emploi' },
  { value: 'AUTRE', label: 'Autre' },
];

export const APTITUDE_VERDICT_LABELS = {
  APTE: 'APTE',
  INAPTE: 'INAPTE',
};

export const IMC_LABELS = {
  MAIGREUR: 'Insuffisance pondérale',
  NORMAL: 'Corpulence normale',
  SURPOIDS: 'Surpoids',
  OBESITE_I: 'Obésité modérée (I)',
  OBESITE_II: 'Obésité sévère (II)',
  OBESITE_III: 'Obésité morbide (III)',
};

export const PIGNET_LABELS = {
  TRES_FORTE: 'Très forte (≤ 10)',
  FORTE: 'Forte (11–15)',
  BONNE: 'Bonne (16–20)',
  MOYENNE: 'Moyenne (21–25)',
  FAIBLE: 'Faible (26–30)',
  TRES_FAIBLE: 'Très faible (31–35)',
  EXTREME: 'Extrêmement faible (> 35)',
};

export const RUFFIER_LABELS = {
  EXCELLENTE: 'Excellente (< 0)',
  BONNE: 'Bonne (0–5)',
  MOYENNE: 'Moyenne (5,1–10)',
  INSUFFISANTE: 'Insuffisante (10,1–15)',
  MAUVAISE: 'Mauvaise (> 15)',
};

export const DICKSON_LABELS = {
  EXCELLENT: 'Excellent (< 0)',
  TRES_BON: 'Très bon (0–2)',
  BON: 'Bon (2,1–4)',
  MOYEN: 'Moyen (4,1–6)',
  FAIBLE: 'Faible (6,1–8)',
  MAUVAIS: 'Mauvais (> 8)',
};

export function emptyAptitudeForm() {
  return {
    serviceId: '',
    patientId: '',
    patientLabel: '',
    nom: '',
    postNom: '',
    prenom: '',
    sexe: 'M',
    etatCivil: '',
    dateNaissance: '',
    lieuNaissance: '',
    adresse: '',
    motif: 'ADMISSION_UKV',
    motifAutre: '',
    filiereId: '',
    poidsKg: '',
    tailleM: '',
    perimetreThoraciqueCm: '',
    p1: '',
    p2: '',
    p3: '',
    imcClasse: '',
    verdict: '',
  };
}

export function formFromDetail(detail) {
  return {
    serviceId: detail.service?.id ? String(detail.service.id) : '',
    patientId: detail.patientId ?? '',
    patientLabel: '',
    nom: detail.nom ?? '',
    postNom: detail.postNom ?? '',
    prenom: detail.prenom ?? '',
    sexe: detail.sexe ?? 'M',
    etatCivil: detail.etatCivil ?? '',
    dateNaissance: detail.dateNaissance ?? '',
    lieuNaissance: detail.lieuNaissance ?? '',
    adresse: detail.adresse ?? '',
    motif: detail.motif ?? 'ADMISSION_UKV',
    motifAutre: detail.motifAutre ?? '',
    filiereId: detail.filiere?.id ? String(detail.filiere.id) : '',
    poidsKg: detail.poidsKg ?? '',
    tailleM: detail.tailleM ?? '',
    perimetreThoraciqueCm: detail.perimetreThoraciqueCm ?? '',
    p1: detail.p1 ?? '',
    p2: detail.p2 ?? '',
    p3: detail.p3 ?? '',
    imcClasse: detail.imcClasse ?? '',
    verdict: detail.verdict ?? '',
  };
}
