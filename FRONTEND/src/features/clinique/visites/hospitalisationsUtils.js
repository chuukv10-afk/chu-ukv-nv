export function isHospitalisation(visite) {
  return Boolean(
    visite?.isHospitalization
    || visite?.isCurrentHospitalization
    || visite?.statut === 'HOSPITALISE'
    || visite?.hospitalizedAt,
  );
}

export function getHospitalisationStart(visite) {
  return visite?.hospitalizedAt || visite?.enterAt || null;
}

export function getHospitalisationEnd(visite) {
  if (visite?.statut === 'HOSPITALISE') {
    return null;
  }
  return visite?.sortedAt || null;
}

export function formatStayDuration(startIso, endIso) {
  if (!startIso) return '—';
  const start = new Date(startIso);
  if (Number.isNaN(start.getTime())) return '—';

  const end = endIso ? new Date(endIso) : new Date();
  if (Number.isNaN(end.getTime()) || end < start) return '—';

  const totalMinutes = Math.floor((end.getTime() - start.getTime()) / 60000);
  const days = Math.floor(totalMinutes / (60 * 24));
  const hours = Math.floor((totalMinutes % (60 * 24)) / 60);

  if (days === 0 && hours === 0) {
    return 'Moins d’une heure';
  }
  if (days === 0) {
    return hours === 1 ? '1 heure' : `${hours} heures`;
  }
  if (hours === 0) {
    return days === 1 ? '1 jour' : `${days} jours`;
  }
  return `${days} j ${hours} h`;
}

export function formatDateTime(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('fr-FR');
}
