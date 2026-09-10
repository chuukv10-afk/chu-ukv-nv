function num(value) {
  if (value === '' || value === null || value === undefined) return null;
  const n = Number(value);
  return Number.isFinite(n) ? n : null;
}

/** Saisie en mètres (1.75). Une valeur > 3 est lue comme des cm. */
export function tailleMetres(value) {
  const n = num(value);
  if (n == null || n <= 0) return null;
  return n > 3 ? Math.round((n / 100) * 100) / 100 : n;
}

export function tailleCentimetres(value) {
  const metres = tailleMetres(value);
  return metres == null ? null : Math.round(metres * 100 * 100) / 100;
}

export function computeAptitudeIndices(form) {
  const poidsKg = num(form.poidsKg);
  const tailleM = tailleMetres(form.tailleM);
  const tailleCm = tailleCentimetres(form.tailleM);
  const perimetre = num(form.perimetreThoraciqueCm);
  const p1 = num(form.p1);
  const p2 = num(form.p2);
  const p3 = num(form.p3);

  let imc = null;
  if (poidsKg > 0 && tailleM > 0) {
    imc = Math.round((poidsKg / (tailleM * tailleM)) * 100) / 100;
  }

  let imcClasse = null;
  if (imc != null) {
    if (imc < 18.5) imcClasse = 'MAIGREUR';
    else if (imc < 25) imcClasse = 'NORMAL';
    else if (imc < 30) imcClasse = 'SURPOIDS';
    else if (imc < 35) imcClasse = 'OBESITE_I';
    else if (imc < 40) imcClasse = 'OBESITE_II';
    else imcClasse = 'OBESITE_III';
  }

  let pignet = null;
  if (tailleCm != null && poidsKg != null && perimetre != null) {
    pignet = Math.round((tailleCm - (poidsKg + perimetre)) * 100) / 100;
  }

  let pignetRobustesse = null;
  if (pignet != null) {
    if (pignet <= 10) pignetRobustesse = 'TRES_FORTE';
    else if (pignet <= 15) pignetRobustesse = 'FORTE';
    else if (pignet <= 20) pignetRobustesse = 'BONNE';
    else if (pignet <= 25) pignetRobustesse = 'MOYENNE';
    else if (pignet <= 30) pignetRobustesse = 'FAIBLE';
    else if (pignet <= 35) pignetRobustesse = 'TRES_FAIBLE';
    else pignetRobustesse = 'EXTREME';
  }

  let ruffier = null;
  let dickson = null;
  if (p1 != null && p2 != null && p3 != null) {
    ruffier = Math.round(((p1 + p2 + p3 - 200) / 10) * 100) / 100;
    dickson = Math.round(((p2 - 70 + 2 * (p3 - p1)) / 10) * 100) / 100;
  }

  let ruffierClasse = null;
  if (ruffier != null) {
    if (ruffier < 0) ruffierClasse = 'EXCELLENTE';
    else if (ruffier <= 5) ruffierClasse = 'BONNE';
    else if (ruffier <= 10) ruffierClasse = 'MOYENNE';
    else if (ruffier <= 15) ruffierClasse = 'INSUFFISANTE';
    else ruffierClasse = 'MAUVAISE';
  }

  let dicksonClasse = null;
  if (dickson != null) {
    if (dickson < 0) dicksonClasse = 'EXCELLENT';
    else if (dickson <= 2) dicksonClasse = 'TRES_BON';
    else if (dickson <= 4) dicksonClasse = 'BON';
    else if (dickson <= 6) dicksonClasse = 'MOYEN';
    else if (dickson <= 8) dicksonClasse = 'FAIBLE';
    else dicksonClasse = 'MAUVAIS';
  }

  const verdictPropose = ruffierClasse
    ? (ruffierClasse === 'INSUFFISANTE' || ruffierClasse === 'MAUVAISE' ? 'INAPTE' : 'APTE')
    : null;

  return {
    tailleM,
    tailleCm,
    imc,
    imcClasse,
    pignet,
    pignetRobustesse,
    ruffier,
    dickson,
    ruffierClasse,
    dicksonClasse,
    verdictPropose,
  };
}

export function formatIndice(value) {
  if (value === null || value === undefined || value === '') return '—';
  return Number(value).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
