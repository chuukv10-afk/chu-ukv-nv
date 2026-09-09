import { readNamedCache } from './cache.js';
import { getLocalStock } from './stockLocal.js';

function namedItems(raw) {
  if (Array.isArray(raw)) return raw;
  if (Array.isArray(raw?.items)) return raw.items;
  if (Array.isArray(raw?.data)) return raw.data;
  if (Array.isArray(raw?.data?.items)) return raw.data.items;
  return [];
}

function calendarDay(raw) {
  const date = new Date(raw);
  if (!Number.isNaN(date.getTime())) {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${date.getFullYear()}-${month}-${day}`;
  }
  return String(raw || '').slice(0, 10);
}

function inRange(raw, from, to) {
  if (!from && !to) return true;
  if (!raw) return true;
  const day = calendarDay(raw);
  if (from && day < from) return false;
  if (to && day > to) return false;
  return true;
}

function money(value) {
  return Number(value || 0).toFixed(4);
}

function paginate(rows, page = 1, limit = 15) {
  const total = rows.length;
  const safePage = Math.max(1, Number(page) || 1);
  const safeLimit = Math.max(1, Number(limit) || 15);
  const start = (safePage - 1) * safeLimit;
  return {
    items: rows.slice(start, start + safeLimit),
    pagination: {
      page: safePage,
      limit: safeLimit,
      total,
      totalPages: total > 0 ? Math.ceil(total / safeLimit) : 0,
    },
  };
}

function venteLibelle(vente) {
  if (vente.origine === 'HOSPITALISE' || vente.clientType === 'HOSPITALISE') {
    return `Hospitalisé — ${vente.patient?.fullName || vente.clientNom || '—'}`;
  }
  if (vente.clientType === 'PATIENT') {
    return `Patient — ${vente.patient?.fullName || [vente.patient?.nom, vente.patient?.postNom, vente.patient?.prenom].filter(Boolean).join(' ') || '—'}`;
  }
  return `Passant — ${vente.clientNom || '—'}`;
}

export async function buildLocalRecettes(params) {
  const type = String(params.get('type') || '').toUpperCase();
  const from = params.get('dateFrom');
  const to = params.get('dateTo');
  const search = String(params.get('search') || '').toLowerCase();
  const rows = [];

  if (!type || type === 'VENTE') {
    for (const vente of namedItems(await readNamedCache('pharmacie.ventes'))) {
      if (vente.statut && vente.statut !== 'VALIDEE') continue;
      const date = vente.dateVente || vente.createdAt;
      if (!inRange(date, from, to)) continue;
      const item = {
        id: vente.id,
        type: 'VENTE',
        numero: vente.numero,
        date,
        libelle: venteLibelle(vente),
        montant: vente.montantTotal,
        statut: 'ENCAISSEE',
        origine: vente.origine || vente.clientType,
      };
      if (search && !`${item.numero} ${item.libelle}`.toLowerCase().includes(search)) continue;
      rows.push(item);
    }
  }

  if (!type || type === 'SERVICE') {
    for (const demande of namedItems(await readNamedCache('pharmacie.demandes'))) {
      if (demande.statut !== 'DELIVREE' && demande.statutPaiement !== 'PAYEE' && demande.statutPaiement !== 'IMPAYEE') {
        continue;
      }
      const date = demande.payeAt || demande.delivreeAt || demande.createdAt;
      if (!inRange(date, from, to)) continue;
      const item = {
        id: demande.id,
        type: 'SERVICE',
        numero: demande.numero,
        date,
        libelle: `Service — ${demande.service?.libelle || demande.motif || '—'}`,
        montant: demande.montantTotal,
        statut: demande.statutPaiement === 'PAYEE' ? 'ENCAISSEE' : 'IMPAYEE',
        origine: 'SERVICE',
      };
      if (search && !`${item.numero} ${item.libelle}`.toLowerCase().includes(search)) continue;
      rows.push(item);
    }
  }

  rows.sort((left, right) => String(right.date || '').localeCompare(String(left.date || '')));

  let encaisseVentes = 0;
  let encaisseServices = 0;
  let creancesOuvertes = 0;
  let creancesCount = 0;
  for (const row of rows) {
    if (row.statut === 'ENCAISSEE') {
      if (row.type === 'VENTE') encaisseVentes += Number(row.montant || 0);
      else encaisseServices += Number(row.montant || 0);
    } else if (row.type === 'SERVICE') {
      creancesOuvertes += Number(row.montant || 0);
      creancesCount += 1;
    }
  }

  const page = paginate(rows, params.get('page'), params.get('limit'));
  return {
    success: true,
    data: {
      ...page,
      totaux: {
        encaisseVentes: money(encaisseVentes),
        encaisseServices: money(encaisseServices),
        encaisseTotal: money(encaisseVentes + encaisseServices),
        creancesOuvertes: money(creancesOuvertes),
        creancesCount,
      },
    },
  };
}

function daysBetween(from, to) {
  const start = from ? new Date(`${from}T00:00:00`) : new Date();
  const end = to ? new Date(`${to}T00:00:00`) : new Date();
  const days = [];
  for (let cursor = new Date(start); cursor <= end; cursor.setDate(cursor.getDate() + 1)) {
    const month = String(cursor.getMonth() + 1).padStart(2, '0');
    const day = String(cursor.getDate()).padStart(2, '0');
    days.push(`${cursor.getFullYear()}-${month}-${day}`);
  }
  return days;
}

export async function buildLocalStatistiques(params) {
  const from = params.get('dateFrom');
  const to = params.get('dateTo');
  const medicaments = namedItems(await readNamedCache('pharmacie.medicaments'));
  const ventes = namedItems(await readNamedCache('pharmacie.ventes'))
    .filter((item) => item.statut === 'VALIDEE' && inRange(item.dateVente || item.createdAt, from, to));
  const demandes = namedItems(await readNamedCache('pharmacie.demandes'))
    .filter((item) => item.statut === 'DELIVREE' && inRange(item.delivreeAt || item.createdAt, from, to));
  const mouvements = namedItems(await readNamedCache('pharmacie.mouvements'))
    .filter((item) => inRange(item.createdAt, from, to));

  let stockValue = 0;
  const stockAlerts = [];
  for (const medicament of medicaments) {
    const local = await getLocalStock(medicament.id);
    const stock = Number(local?.stockDisponible ?? medicament.stockDisponible ?? 0);
    const seuil = Number(medicament.seuilAlerte || 0);
    stockValue += stock * Number(medicament.prixVente || 0);
    if (seuil > 0 && stock <= seuil) {
      stockAlerts.push({
        id: medicament.id,
        name: medicament.libelle,
        code: medicament.code,
        currentStock: stock,
        stockMin: seuil,
      });
    }
  }

  const revenuVentes = ventes.reduce((sum, item) => sum + Number(item.montantTotal || 0), 0);
  const servicesPayes = demandes.filter((item) => item.statutPaiement === 'PAYEE');
  const revenuServices = servicesPayes.reduce((sum, item) => sum + Number(item.montantTotal || 0), 0);

  const perDay = Object.fromEntries(daysBetween(from, to).map((day) => [day, {
    date: day,
    ventes: 0,
    services: 0,
    revenue: 0,
  }]));
  for (const vente of ventes) {
    const day = calendarDay(vente.dateVente || vente.createdAt);
    if (!perDay[day]) continue;
    perDay[day].ventes += 1;
    perDay[day].revenue += Number(vente.montantTotal || 0);
  }
  for (const demande of demandes) {
    const day = calendarDay(demande.delivreeAt || demande.createdAt);
    if (!perDay[day]) continue;
    perDay[day].services += 1;
    if (demande.statutPaiement === 'PAYEE') {
      perDay[day].revenue += Number(demande.montantTotal || 0);
    }
  }

  const mouvementsPerDayMap = {};
  for (const mouvement of mouvements) {
    const day = calendarDay(mouvement.createdAt);
    if (!mouvementsPerDayMap[day]) {
      mouvementsPerDayMap[day] = { date: day, entrees: 0, sorties: 0 };
    }
    if (String(mouvement.sens || mouvement.type || '').includes('ENTREE') || String(mouvement.type || '').startsWith('ENTREE')) {
      mouvementsPerDayMap[day].entrees += Number(mouvement.quantite || 0);
    } else {
      mouvementsPerDayMap[day].sorties += Number(mouvement.quantite || 0);
    }
  }

  const topMap = new Map();
  const famMap = new Map();
  for (const vente of ventes) {
    for (const ligne of vente.lignes || []) {
      const id = String(ligne.medicamentId || ligne.medicament?.id || '');
      if (!id) continue;
      const current = topMap.get(id) || { id, name: ligne.medicament?.libelle || id, count: 0 };
      current.count += Number(ligne.quantite || 0);
      topMap.set(id, current);
      const famille = ligne.medicament?.famille?.libelle
        || medicaments.find((item) => String(item.id) === id)?.famille?.libelle
        || 'Autre';
      famMap.set(famille, (famMap.get(famille) || 0) + Number(ligne.quantite || 0));
    }
  }

  return {
    success: true,
    data: {
      periode: { dateFrom: from, dateTo: to },
      kpis: {
        totalMedicaments: medicaments.filter((item) => !item.statut || item.statut === 'ACTIF').length,
        stockValue: money(stockValue),
        totalDispensations: ventes.length + demandes.length,
        totalRevenue: money(revenuVentes + revenuServices),
        stockAlerts: stockAlerts.length,
      },
      salesPerDay: Object.values(perDay),
      mouvementsPerDay: Object.values(mouvementsPerDayMap).sort((a, b) => a.date.localeCompare(b.date)),
      categoryDistribution: [...famMap.entries()].map(([category, count]) => ({ category, count })),
      topMedicaments: [...topMap.values()].sort((a, b) => b.count - a.count).slice(0, 10),
      stockAlerts,
    },
  };
}
