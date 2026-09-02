const PERSONNEL_TYPE_LABELS = {
  MEDICAL: 'Médical',
  PARAMEDICAL: 'Paramédical',
  ADMINISTRATIF: 'Administratif',
  TECHNIQUE: 'Technique',
};

const PERIMETRE_LABELS = {
  GLOBAL: 'Périmètre global',
  DEPARTEMENT: 'Périmètre département',
  SERVICE: 'Périmètre service',
};

export function getDisplayName(profile) {
  if (!profile) {
    return 'Utilisateur';
  }

  return [profile.prenom, profile.nom, profile.postNom].filter(Boolean).join(' ').trim()
    || profile.matricule
    || 'Utilisateur';
}

export function getInitials(profile) {
  const name = getDisplayName(profile);

  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('')
    || 'U';
}

export function getPersonnelTypeLabel(type) {
  return PERSONNEL_TYPE_LABELS[type] || type || 'Personnel';
}

export function getPerimetreLabel(perimetre) {
  return PERIMETRE_LABELS[perimetre] || perimetre || 'Non défini';
}

export function formatRoleAssignment(assignment) {
  const scope = assignment.service || assignment.departement || 'Tout l\'établissement';

  return `${assignment.role} · ${getPerimetreLabel(assignment.perimetre)} · ${scope}`;
}
