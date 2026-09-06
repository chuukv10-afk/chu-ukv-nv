export function normalizeSelectedComplaint(item) {
  if (typeof item === 'string') {
    const libelle = item.trim();
    return libelle ? { id: null, libelle } : null;
  }
  if (item && typeof item === 'object') {
    const libelle = String(item.libelle ?? '').trim();
    if (!libelle) return null;
    const id = item.id != null ? Number(item.id) : null;
    return { id: id > 0 ? id : null, libelle };
  }
  return null;
}

export function complaintKey(item) {
  const normalized = normalizeSelectedComplaint(item);
  if (!normalized) return '';
  return normalized.id ? `id:${normalized.id}` : `lib:${normalized.libelle.toLowerCase()}`;
}

export function isComplaintSelected(selected = [], plainte) {
  if (!plainte?.libelle) return false;
  return selected.some((item) => {
    const normalized = normalizeSelectedComplaint(item);
    if (!normalized) return false;
    if (plainte.id && normalized.id) return normalized.id === plainte.id;
    return normalized.libelle.toLowerCase() === plainte.libelle.toLowerCase();
  });
}

export function toggleComplaintSelection(selected = [], plainte) {
  if (!plainte?.libelle) return selected;
  const exists = isComplaintSelected(selected, plainte);
  if (exists) {
    return selected.filter((item) => {
      const normalized = normalizeSelectedComplaint(item);
      if (!normalized) return false;
      if (plainte.id && normalized.id) return normalized.id !== plainte.id;
      return normalized.libelle.toLowerCase() !== plainte.libelle.toLowerCase();
    });
  }
  return [...selected, { id: plainte.id ?? null, libelle: plainte.libelle }];
}

export function normalizeSymptoms(symptoms = {}) {
  const selected = Array.isArray(symptoms.selectedComplaints) ? symptoms.selectedComplaints : [];
  return {
    mode: symptoms.mode === 'COMPLAINTS' ? 'COMPLAINTS' : 'NONE',
    selectedComplaints: selected
      .map((item) => normalizeSelectedComplaint(item))
      .filter(Boolean),
    freeText: symptoms.freeText ?? '',
  };
}
