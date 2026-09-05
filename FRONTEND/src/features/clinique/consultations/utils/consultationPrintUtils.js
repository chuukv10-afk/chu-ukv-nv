import logoChu from '../../../../assets/logo-chu.svg';
import { formatPhysicalExamHTML, hasPhysicalExamData } from './physicalExamSchema.js';

const PRINT_STYLES = `
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif; color: #1a1a1a; font-size: 12px; line-height: 1.5; }
  .page { position: relative; padding: 0 20px; }
  .print-header {
    display: flex; align-items: center; justify-content: space-between;
    background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
    color: white; padding: 14px 24px; border-radius: 0 0 12px 12px;
    margin: 0 -20px 18px;
  }
  .print-header .logo-area { display: flex; align-items: center; gap: 14px; }
  .print-header .logo-area img { height: 48px; width: auto; border-radius: 6px; background: white; padding: 4px; }
  .print-header .hospital-info h1 { font-size: 16px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase; margin-bottom: 1px; }
  .print-header .hospital-info p { font-size: 10px; opacity: 0.85; }
  .patient-band {
    display: flex; justify-content: space-between; align-items: stretch;
    background: #f0f5fb; border: 1px solid #d0dcea; border-radius: 8px;
    padding: 12px 18px; margin-bottom: 16px; gap: 18px;
  }
  .patient-band .col { flex: 1; }
  .patient-band .patient-name { font-size: 16px; font-weight: 800; color: #4f46e5; margin-bottom: 4px; }
  .patient-band .info-line { font-size: 11px; color: #444; margin-bottom: 2px; }
  .patient-band .info-line strong { color: #1a1a1a; font-weight: 600; }
  .record-badge {
    display: inline-block; font-family: Consolas, monospace; font-size: 12px;
    font-weight: 600; border: 2px solid #4f46e5; color: #4f46e5;
    padding: 2px 10px; border-radius: 6px; letter-spacing: 1px;
  }
  .section { margin-bottom: 14px; page-break-inside: avoid; }
  .section-title {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    color: #4f46e5; letter-spacing: 0.6px;
    border-bottom: 2px solid #4f46e5; padding-bottom: 3px; margin-bottom: 8px;
  }
  .clinical-block { padding: 6px 0; }
  .clinical-label { font-size: 10px; font-weight: 700; color: #4f46e5; text-transform: uppercase; margin-bottom: 3px; }
  .clinical-text { font-size: 11.5px; white-space: pre-wrap; color: #333; padding: 4px 12px; border-left: 3px solid #d0dcea; margin-bottom: 8px; min-height: 16px; }
  .clinical-text.empty { color: #bbb; font-style: italic; }
  .pe-block { margin-bottom: 10px; padding-left: 8px; border-left: 2px solid #d0dcea; }
  .pe-section { font-size: 10.5px; font-weight: 700; color: #4f46e5; margin-bottom: 4px; text-transform: uppercase; }
  .pe-row { font-size: 11px; margin-bottom: 2px; line-height: 1.45; }
  .pe-label { font-weight: 600; color: #444; }
  .pe-value { color: #333; }
  .tag { display: inline-block; background: #e8eef5; color: #4f46e5; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; margin: 2px 3px 2px 0; }
  .print-footer {
    margin-top: 24px; text-align: center; font-size: 9px; color: #999;
    padding: 6px 20px; border-top: 1px solid #ddd;
    display: flex; justify-content: space-between;
  }
  @media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    @page { margin: 10mm 8mm 18mm 8mm; }
  }
`;

const GENDER_LABELS = { M: 'Masculin', F: 'Féminin' };

function fmtDateTime(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('fr-FR', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function openPrintWindow(html) {
  const win = window.open('', '_blank', 'width=900,height=700');
  if (!win) return;
  win.document.write(html);
  win.document.close();
  win.onload = () => {
    win.focus();
    win.print();
  };
}

function buildPatientBand(consultation) {
  return `
  <div class="patient-band">
    <div class="col">
      <div class="patient-name">${consultation.patientName ?? '—'}</div>
      <div class="info-line"><strong>Sexe :</strong> ${GENDER_LABELS[consultation.patientSexe] ?? consultation.patientSexe ?? '—'}</div>
      <div class="info-line"><strong>Service :</strong> ${consultation.service?.libelle ?? '—'}</div>
    </div>
    <div class="col">
      <div class="info-line" style="margin-bottom:6px"><strong>N° Dossier :</strong> <span class="record-badge">${consultation.numDossier ?? '—'}</span></div>
      <div class="info-line"><strong>Consultation :</strong> ${fmtDateTime(consultation.consultedAt)}</div>
      <div class="info-line"><strong>Médecin :</strong> ${consultation.openedBy?.fullName ?? '—'}</div>
    </div>
  </div>`;
}

function buildHeader() {
  return `
  <div class="print-header">
    <div class="logo-area">
      <img src="${logoChu}" alt="Logo CHU" />
      <div class="hospital-info">
        <h1>CHU Universitaire</h1>
        <p>Dossier de consultation clinique</p>
      </div>
    </div>
  </div>`;
}

function buildFooter() {
  return `
  <div class="print-footer">
    <span>CHU — Document confidentiel</span>
    <span>Imprimé le ${fmtDateTime(new Date().toISOString())}</span>
  </div>`;
}

function buildClinicalContent(form) {
  const anamnesis = Array.isArray(form.complementAnamnese) ? form.complementAnamnese : [];
  return `
  <div class="section">
    <div class="section-title">Examen clinique</div>
    <div class="clinical-block">
      <div class="clinical-label">Motif de consultation</div>
      <div class="clinical-text ${form.motif ? '' : 'empty'}">${form.motif || 'Non renseigné'}</div>
    </div>
    <div class="clinical-block">
      <div class="clinical-label">Histoire de la maladie</div>
      <div class="clinical-text ${form.histoireMaladie ? '' : 'empty'}">${form.histoireMaladie || 'Non renseigné'}</div>
    </div>
    <div class="clinical-block">
      <div class="clinical-label">Examen physique</div>
      ${hasPhysicalExamData(form.physicalExam) || form.physicalExamText
    ? formatPhysicalExamHTML(form.physicalExam, form.physicalExamText)
    : '<div class="clinical-text empty">Non renseigné</div>'}
    </div>
    ${anamnesis.length > 0 ? `
    <div class="clinical-block">
      <div class="clinical-label">Anamnèse complémentaire</div>
      <div style="padding: 4px 0;">${anamnesis.map((item) => `<span class="tag">${item}</span>`).join('')}</div>
    </div>` : ''}
    <div class="clinical-block">
      <div class="clinical-label">Plan de suivi</div>
      <div class="clinical-text ${form.conduireATenir ? '' : 'empty'}">${form.conduireATenir || 'Non renseigné'}</div>
    </div>
  </div>`;
}

/**
 * Imprime la section examen physique / clinique d'une consultation.
 */
export function printPhysicalExamSection(consultation, clinicalForm) {
  const form = clinicalForm ?? {
    motif: consultation.motif,
    histoireMaladie: consultation.histoireMaladie,
    physicalExam: consultation.physicalExam,
    physicalExamText: consultation.physicalExamText,
    complementAnamnese: consultation.complementAnamnese,
    conduireATenir: consultation.conduireATenir ?? consultation.followUpPlan,
  };

  const html = `<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<title>Examen clinique — ${consultation.patientName ?? 'Consultation'}</title>
<style>${PRINT_STYLES}</style></head><body>
<div class="page">
  ${buildHeader()}
  <h2 style="text-align:center;color:#4f46e5;font-size:14px;margin-bottom:14px;text-transform:uppercase;letter-spacing:1px;">Examen clinique</h2>
  ${buildPatientBand(consultation)}
  ${buildClinicalContent(form)}
</div>
${buildFooter()}
</body></html>`;

  openPrintWindow(html);
}
