/** Grille d'examen physique tête-pieds — structure clinique CHU */

export function createEmptyPhysicalExam() {
  return {
    generalState: { status: '', alteredDetails: '' },
    skin: '',
    headNeck: {
      crane: '',
      cheveux: '',
      face: '',
      paupieres: '',
      conjonctives: '',
      globesOculaires: '',
      pupilles: '',
      levresBuccales: '',
      muqueuseBuccale: '',
      gencives: '',
      langue: '',
      haleine: '',
      gorge: '',
      nez: '',
      oreilles: '',
      parotides: '',
      thyroide: '',
      airesGanglionnaires: '',
    },
    thorax: {
      osseux: '',
      sein: '',
      poumon: {
        inspection: '',
        palpation: '',
        percussion: '',
        auscultation: '',
        syndromePulmonaire: '',
      },
      coeur: {
        inspection: '',
        palpation: '',
        auscultation: '',
      },
      vaisseaux: {
        arteres: '',
        veines: '',
      },
    },
    abdomen: {
      perimetreOmbilical: '',
      inspection: '',
      palpation: '',
      percussion: '',
      auscultation: '',
    },
    fossesLombaires: '',
    genitalOrgans: {
      pubis: '',
      oge: '',
      toucherVaginal: '',
      toucherRectal: '',
    },
    locomotor: {
      demarche: '',
      membresSuperieurs: '',
      membresInferieurs: '',
      rachis: '',
    },
    neurological: {
      etatMental: '',
      posture: '',
      demarche: '',
      nerfsCraniens: '',
      motricite: {
        forceMusculaire: '',
        tonusMusculaire: '',
      },
      coordination: '',
      reflexes: '',
      sensibilite: '',
      signesMeninges: '',
    },
  };
}

function isFilled(value) {
  return typeof value === 'string' && value.trim() !== '';
}

export function normalizePhysicalExam(data) {
  const empty = createEmptyPhysicalExam();
  if (!data || typeof data !== 'object') return empty;

  return {
    generalState: {
      status: data.generalState?.status || '',
      alteredDetails: data.generalState?.alteredDetails || '',
    },
    skin: data.skin || '',
    headNeck: { ...empty.headNeck, ...(data.headNeck || {}) },
    thorax: {
      osseux: data.thorax?.osseux || '',
      sein: data.thorax?.sein || '',
      poumon: { ...empty.thorax.poumon, ...(data.thorax?.poumon || {}) },
      coeur: { ...empty.thorax.coeur, ...(data.thorax?.coeur || {}) },
      vaisseaux: { ...empty.thorax.vaisseaux, ...(data.thorax?.vaisseaux || {}) },
    },
    abdomen: { ...empty.abdomen, ...(data.abdomen || {}) },
    fossesLombaires: data.fossesLombaires || '',
    genitalOrgans: { ...empty.genitalOrgans, ...(data.genitalOrgans || {}) },
    locomotor: { ...empty.locomotor, ...(data.locomotor || {}) },
    neurological: {
      etatMental: data.neurological?.etatMental || '',
      posture: data.neurological?.posture || '',
      demarche: data.neurological?.demarche || '',
      nerfsCraniens: data.neurological?.nerfsCraniens || '',
      motricite: { ...empty.neurological.motricite, ...(data.neurological?.motricite || {}) },
      coordination: data.neurological?.coordination || '',
      reflexes: data.neurological?.reflexes || '',
      sensibilite: data.neurological?.sensibilite || '',
      signesMeninges: data.neurological?.signesMeninges || '',
    },
  };
}

export function setPhysicalExamField(exam, path, value) {
  const next = normalizePhysicalExam(exam);
  const keys = path.split('.');
  let cursor = next;
  for (let i = 0; i < keys.length - 1; i += 1) {
    cursor = cursor[keys[i]];
  }
  cursor[keys[keys.length - 1]] = value;
  return next;
}

export function hasPhysicalExamData(exam) {
  const data = normalizePhysicalExam(exam);
  if (data.generalState.status) return true;
  if (isFilled(data.generalState.alteredDetails)) return true;
  if (isFilled(data.skin)) return true;

  const walk = (obj) => {
    if (typeof obj === 'string') return isFilled(obj);
    if (!obj || typeof obj !== 'object') return false;
    return Object.values(obj).some(walk);
  };

  return walk(data.headNeck)
    || walk(data.thorax)
    || isFilled(data.fossesLombaires)
    || walk(data.genitalOrgans)
    || walk(data.locomotor)
    || walk(data.neurological);
}

function row(label, value) {
  if (!isFilled(value)) return '';
  return `<div class="pe-row"><span class="pe-label">${label} :</span> <span class="pe-value">${value}</span></div>`;
}

function block(title, rowsHtml) {
  const content = rowsHtml.filter(Boolean).join('');
  if (!content) return '';
  return `<div class="pe-block"><div class="pe-section">${title}</div>${content}</div>`;
}

/** Rendu HTML pour l'impression */
export function formatPhysicalExamHTML(exam, freeNotes = '') {
  const d = normalizePhysicalExam(exam);
  const parts = [];

  const generalRows = [];
  if (d.generalState.status === 'conserved') {
    generalRows.push(row('État général', 'Conservé'));
  } else if (d.generalState.status === 'altered') {
    generalRows.push(row('État général', `Altéré${d.generalState.alteredDetails ? ` — ${d.generalState.alteredDetails}` : ''}`));
  }
  parts.push(block('1. État général', generalRows));
  parts.push(block('2. Peau', [row('Peau', d.skin)]));

  const hn = d.headNeck;
  parts.push(block('3. Tête et cou', [
    row('Crâne', hn.crane),
    row('Cheveux', hn.cheveux),
    row('Face', hn.face),
    row('Paupières', hn.paupieres),
    row('Conjonctives', hn.conjonctives),
    row('Globes oculaires', hn.globesOculaires),
    row('Pupilles', hn.pupilles),
    row('Lèvres buccales', hn.levresBuccales),
    row('Muqueuse buccale', hn.muqueuseBuccale),
    row('Gencives', hn.gencives),
    row('Langue', hn.langue),
    row('Haleine', hn.haleine),
    row('Gorge', hn.gorge),
    row('Nez', hn.nez),
    row('Oreilles', hn.oreilles),
    row('Parotides', hn.parotides),
    row('Thyroïde', hn.thyroide),
    row('Aires ganglionnaires superficielles', hn.airesGanglionnaires),
  ]));

  const th = d.thorax;
  parts.push(block('4. Thorax', [
    row('Thorax osseux', th.osseux),
    row('Sein', th.sein),
    row('Poumon — Inspection', th.poumon.inspection),
    row('Poumon — Palpation', th.poumon.palpation),
    row('Poumon — Percussion', th.poumon.percussion),
    row('Poumon — Auscultation', th.poumon.auscultation),
    row('Syndrome pulmonaire', th.poumon.syndromePulmonaire),
    row('Cœur — Inspection', th.coeur.inspection),
    row('Cœur — Palpation', th.coeur.palpation),
    row('Cœur — Auscultation', th.coeur.auscultation),
    row('Artères', th.vaisseaux.arteres),
    row('Veines', th.vaisseaux.veines),
  ]));

  const ab = d.abdomen;
  parts.push(block('5. Abdomen', [
    row('Périmètre ombilical', ab.perimetreOmbilical),
    row('Inspection', ab.inspection),
    row('Palpation', ab.palpation),
    row('Percussion', ab.percussion),
    row('Auscultation', ab.auscultation),
  ]));

  parts.push(block('6. Fosses lombaires', [row('Fosses lombaires', d.fossesLombaires)]));

  const go = d.genitalOrgans;
  parts.push(block('7. Organes génitaux externes et touchers pelviens', [
    row('Pubis', go.pubis),
    row('OGE', go.oge),
    row('Toucher vaginal', go.toucherVaginal),
    row('Toucher rectal', go.toucherRectal),
  ]));

  const loc = d.locomotor;
  parts.push(block('8. Appareil locomoteur', [
    row('Démarche', loc.demarche),
    row('Membres supérieurs', loc.membresSuperieurs),
    row('Membres inférieurs', loc.membresInferieurs),
    row('Rachis', loc.rachis),
  ]));

  const neuro = d.neurological;
  parts.push(block('9. Examen neurologique', [
    row('État mental (fonctions supérieures)', neuro.etatMental),
    row('Posture', neuro.posture),
    row('Démarche', neuro.demarche),
    row('Nerfs crâniens', neuro.nerfsCraniens),
    row('Force musculaire', neuro.motricite.forceMusculaire),
    row('Tonus musculaire', neuro.motricite.tonusMusculaire),
    row('Coordination des mouvements', neuro.coordination),
    row('Examen des réflexes', neuro.reflexes),
    row('Examen de la sensibilité', neuro.sensibilite),
    row('Signes méningés', neuro.signesMeninges),
  ]));

  if (isFilled(freeNotes)) {
    parts.push(`<div class="pe-block"><div class="pe-section">Observations complémentaires</div><div class="pe-value">${freeNotes}</div></div>`);
  }

  const html = parts.filter(Boolean).join('');
  return html || '<div class="clinical-text empty">Non renseigné</div>';
}
