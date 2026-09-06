import { formatDateTime, formatPatientName, formatPrix } from '../shared/format.js';

const TICKET_STYLES = `
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: 'Courier New', Courier, monospace;
    color: #111;
    font-size: 12px;
    line-height: 1.35;
    width: 72mm;
  }
  .ticket { padding: 4px 2px 8px; }
  .center { text-align: center; }
  .muted { color: #444; }
  .brand { font-size: 14px; font-weight: 800; letter-spacing: 0.4px; text-transform: uppercase; }
  .sub { font-size: 11px; margin-top: 2px; }
  .rule { border: none; border-top: 1px dashed #111; margin: 8px 0; }
  .row { display: flex; justify-content: space-between; gap: 8px; }
  .grow { flex: 1; }
  .qty { width: 18px; text-align: right; }
  .amt { min-width: 64px; text-align: right; white-space: nowrap; }
  .total { font-size: 14px; font-weight: 800; }
  .footer { margin-top: 10px; font-size: 11px; }
  @page { size: 80mm auto; margin: 3mm; }
  @media print {
    body { width: 72mm; }
  }
`;

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function clientLabel(vente) {
  if (vente.clientType === 'PATIENT') return formatPatientName(vente.patient);
  return vente.clientNom || 'Passant';
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
    const lot = ligne.lot?.numeroLot ? `Lot ${ligne.lot.numeroLot}` : '';
    return `
      <div class="row">
        <div class="grow">
          ${escapeHtml(name)}
          ${lot ? `<div class="muted">${escapeHtml(lot)}</div>` : ''}
        </div>
        <div class="qty">${escapeHtml(ligne.quantite)}</div>
        <div class="amt">${escapeHtml(formatPrix(ligne.prixTotal ?? 0))}</div>
      </div>`;
  }).join('');

  const html = `<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<title>Ticket ${escapeHtml(vente.numero ?? '')}</title>
<style>${TICKET_STYLES}</style></head>
<body>
  <div class="ticket">
    <div class="center">
      <div class="brand">CHU UKV</div>
      <div class="sub">Pharmacie centrale</div>
      <div class="muted">Ticket de caisse</div>
    </div>
    <hr class="rule" />
    <div class="row"><span>N°</span><strong>${escapeHtml(vente.numero ?? '—')}</strong></div>
    <div class="row"><span>Date</span><span>${escapeHtml(formatDateTime(vente.dateVente || vente.createdAt))}</span></div>
    <div class="row"><span>Client</span><span>${escapeHtml(vente.origine === 'HOSPITALISE' ? 'Hospitalisé' : (vente.clientType === 'PATIENT' ? 'Patient' : 'Passant'))}</span></div>
    <div class="row"><span></span><span>${escapeHtml(clientLabel(vente))}</span></div>
    ${vente.visite?.service?.libelle ? `<div class="row"><span>Service</span><span>${escapeHtml(vente.visite.service.libelle)}</span></div>` : ''}
    <div class="row"><span>Paiement</span><span>${escapeHtml(paiementLabel(vente.modePaiement))}</span></div>
    <hr class="rule" />
    <div class="row muted"><span class="grow">Article</span><span class="qty">Q</span><span class="amt">Montant</span></div>
    ${rows || '<div class="muted">Aucune ligne</div>'}
    <hr class="rule" />
    <div class="row total"><span>TOTAL</span><span>${escapeHtml(formatPrix(vente.montantTotal))}</span></div>
    ${vente.statut === 'ANNULEE' ? '<div class="center" style="margin-top:8px;font-weight:800">*** ANNULÉE ***</div>' : ''}
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
  win.onload = () => {
    win.focus();
    win.print();
  };
}
