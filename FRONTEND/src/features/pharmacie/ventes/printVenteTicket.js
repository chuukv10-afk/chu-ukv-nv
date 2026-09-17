import logoChu from '../../../assets/img/logo.jpg';
import { formatDateTime, formatPatientName, formatPrix } from '../shared/format.js';

const TICKET_STYLES = `
  * { margin: 0; padding: 0; box-sizing: border-box; font-weight: 700; }
  body {
    font-family: 'Courier New', Courier, monospace;
    color: #111;
    font-size: 14px;
    font-weight: 700;
    line-height: 1.35;
    width: 72mm;
  }
  .ticket { padding: 4px 2px 8px; }
  .header { display: flex; align-items: center; justify-content: space-between; gap: 6px; }
  .header-text { text-align: left; flex: 1; min-width: 0; }
  .header-logo {
    height: 12mm;
    width: auto;
    max-width: 14mm;
    flex-shrink: 0;
    object-fit: contain;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .center { text-align: center; }
  .muted { color: #111; }
  .brand { font-size: 16px; font-weight: 800; letter-spacing: 0.4px; text-transform: uppercase; }
  .sub { font-size: 13px; margin-top: 2px; }
  .rule { border: none; border-top: 1px dashed #111; margin: 8px 0; }
  .row { display: flex; justify-content: space-between; gap: 10px; }
  .grow { flex: 1; min-width: 0; }
  .qty { width: 44px; flex-shrink: 0; text-align: right; padding-right: 12px; }
  .amt { min-width: 88px; flex-shrink: 0; text-align: right; white-space: nowrap; padding-left: 4px; }
  .total { font-size: 16px; font-weight: 800; }
  .footer { margin-top: 10px; font-size: 13px; }
  @page { size: 80mm auto; margin: 3mm; }
  @media print {
    body { width: 72mm; }
  }
`;

let sharpLogoPromise;

function loadSharpLogo() {
  if (!sharpLogoPromise) {
    sharpLogoPromise = new Promise((resolve) => {
      const img = new Image();
      img.onload = () => {
        const canvas = document.createElement('canvas');
        canvas.width = img.naturalWidth;
        canvas.height = img.naturalHeight;
        const ctx = canvas.getContext('2d');
        if (!ctx) {
          resolve({ src: logoChu, width: img.naturalWidth, height: img.naturalHeight });
          return;
        }
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(img, 0, 0);
        resolve({
          src: canvas.toDataURL('image/png'),
          width: img.naturalWidth,
          height: img.naturalHeight,
        });
      };
      img.onerror = () => resolve({ src: logoChu, width: 0, height: 0 });
      img.src = logoChu;
    });
  }
  return sharpLogoPromise;
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function clientLabel(vente) {
  const fromPatient = formatPatientName(vente.patient);
  if (fromPatient && fromPatient !== '—') return fromPatient;
  const fromVisite = vente.visite?.patientName || formatPatientName(vente.visite?.patient);
  if (fromVisite && fromVisite !== '—') return fromVisite;
  return String(vente.clientNom || '').trim();
}

function paiementLabel(mode) {
  if (mode === 'ESPECES') return 'Espèces';
  if (mode === 'MOBILE') return 'Mobile money';
  return mode || '—';
}

export function printVenteTicket(vente) {
  if (!vente) return;

  const lignes = Array.isArray(vente.lignes) ? vente.lignes : [];
  const rows = lignes.map((ligne) => {
    const name = ligne.medicament?.libelle || 'Médicament';
    return `
      <div class="row">
        <div class="grow">${escapeHtml(name)}</div>
        <div class="qty">x${escapeHtml(ligne.quantite)}</div>
        <div class="amt">${escapeHtml(formatPrix(ligne.prixTotal ?? 0))}</div>
      </div>`;
  }).join('');

  loadSharpLogo().then((logo) => {
    openTicketWindow(vente, rows, logo.src);
  });
}

function openTicketWindow(vente, rows, logoSrc) {
  const client = clientLabel(vente);
  const html = `<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<title>Ticket ${escapeHtml(vente.numero ?? '')}</title>
<style>${TICKET_STYLES}</style></head>
<body>
  <div class="ticket">
    <div class="header">
      <div class="header-text">
        <div class="brand">CHU UKV</div>
        <div class="sub">Pharmacie centrale</div>
        <div class="muted">Ticket de caisse</div>
      </div>
      <img class="header-logo" src="${logoSrc}" alt="Logo CHU UKV" />
    </div>
    <hr class="rule" />
    <div class="row"><span>N°</span><strong>${escapeHtml(vente.numero ?? '—')}</strong></div>
    <div class="row"><span>Date</span><span>${escapeHtml(formatDateTime(vente.dateVente || vente.createdAt))}</span></div>
    ${client ? `<div class="row"><span>Client</span><span>${escapeHtml(client)}</span></div>` : ''}
    ${vente.visite?.service?.libelle ? `<div class="row"><span>Service</span><span>${escapeHtml(vente.visite.service.libelle)}</span></div>` : ''}
    <div class="row"><span>Paiement</span><span>${escapeHtml(vente.statut === 'BON_POUR' ? 'Bon pour — non encaissé' : paiementLabel(vente.modePaiement))}</span></div>
    <hr class="rule" />
    <div class="row muted"><span class="grow">Article</span><span class="qty">Qté</span><span class="amt">Montant</span></div>
    ${rows || '<div class="muted">Aucune ligne</div>'}
    <hr class="rule" />
    <div class="row total"><span>TOTAL</span><span>${escapeHtml(formatPrix(vente.montantTotal))}</span></div>
    ${vente.statut === 'ANNULEE' ? '<div class="center" style="margin-top:8px;font-weight:800">*** ANNULÉE ***</div>' : ''}
    ${vente.statut === 'BON_POUR' ? '<div class="center" style="margin-top:8px;font-weight:800">*** BON POUR ***</div>' : ''}
    <div class="footer center">
      Merci de votre visite.<br />
      Conservez ce ticket.
    </div>
  </div>
</body></html>`;

  const win = window.open('', '_blank', 'width=420,height=640');
  if (!win) return;
  win.document.write(html);
  win.document.close();
  const printNow = () => {
    win.focus();
    win.print();
  };
  const logo = win.document.querySelector('.header-logo');
  if (logo && !logo.complete) {
    logo.onload = printNow;
    logo.onerror = printNow;
  } else {
    win.onload = printNow;
  }
}
