function normalizePath(endpoint = '') {
  const [path] = String(endpoint).split('?');
  return path;
}

function idFrom(path, pattern) {
  const raw = path.match(pattern)?.[1];
  if (!raw) return raw;
  const numeric = Number(raw);
  return Number.isFinite(numeric) && numeric > 0 ? numeric : raw;
}

export const WRITE_POLICIES = [
  { method: 'POST', test: (path) => path === '/api/v1/pharmacie/ventes', action: 'pharmacie.vente.create', module: 'pharmacie' },
  { method: 'PUT', test: (path) => /\/api\/v1\/pharmacie\/ventes\/[^/]+$/.test(path), action: 'pharmacie.vente.update', module: 'pharmacie', idFromPath: (path) => idFrom(path, /ventes\/([^/]+)$/) },
  { method: 'POST', test: (path) => /\/api\/v1\/pharmacie\/ventes\/[^/]+\/valider$/.test(path), action: 'pharmacie.vente.valider', module: 'pharmacie', idFromPath: (path) => idFrom(path, /ventes\/([^/]+)\/valider$/) },
  { method: 'POST', test: (path) => /\/api\/v1\/pharmacie\/ventes\/[^/]+\/annuler$/.test(path), action: 'pharmacie.vente.annuler', module: 'pharmacie', idFromPath: (path) => idFrom(path, /ventes\/([^/]+)\/annuler$/) },
  { method: 'DELETE', test: (path) => /\/api\/v1\/pharmacie\/ventes\/[^/]+$/.test(path), action: 'pharmacie.vente.delete', module: 'pharmacie', idFromPath: (path) => idFrom(path, /ventes\/([^/]+)$/) },

  { method: 'POST', test: (path) => path === '/api/v1/pharmacie/demandes-service', action: 'pharmacie.demande_service.create', module: 'pharmacie' },
  { method: 'PUT', test: (path) => /\/api\/v1\/pharmacie\/demandes-service\/[^/]+$/.test(path), action: 'pharmacie.demande_service.update', module: 'pharmacie', idFromPath: (path) => idFrom(path, /demandes-service\/([^/]+)$/) },
  { method: 'POST', test: (path) => /\/demandes-service\/[^/]+\/envoyer$/.test(path), action: 'pharmacie.demande_service.envoyer', module: 'pharmacie', idFromPath: (path) => idFrom(path, /demandes-service\/([^/]+)\/envoyer$/) },
  { method: 'POST', test: (path) => /\/demandes-service\/[^/]+\/delivrer$/.test(path), action: 'pharmacie.demande_service.delivrer', module: 'pharmacie', idFromPath: (path) => idFrom(path, /demandes-service\/([^/]+)\/delivrer$/) },
  { method: 'POST', test: (path) => /\/demandes-service\/[^/]+\/refuser$/.test(path), action: 'pharmacie.demande_service.refuser', module: 'pharmacie', idFromPath: (path) => idFrom(path, /demandes-service\/([^/]+)\/refuser$/) },
  { method: 'POST', test: (path) => /\/demandes-service\/[^/]+\/regler$/.test(path), action: 'pharmacie.demande_service.regler', module: 'pharmacie', idFromPath: (path) => idFrom(path, /demandes-service\/([^/]+)\/regler$/) },
  { method: 'DELETE', test: (path) => /\/api\/v1\/pharmacie\/demandes-service\/[^/]+$/.test(path), action: 'pharmacie.demande_service.delete', module: 'pharmacie', idFromPath: (path) => idFrom(path, /demandes-service\/([^/]+)$/) },

  { method: 'POST', test: (path) => path === '/api/v1/pharmacie/receptions', action: 'pharmacie.reception.create', module: 'pharmacie' },
  { method: 'PUT', test: (path) => /\/api\/v1\/pharmacie\/receptions\/[^/]+$/.test(path), action: 'pharmacie.reception.update', module: 'pharmacie', idFromPath: (path) => idFrom(path, /receptions\/([^/]+)$/) },
  { method: 'POST', test: (path) => /\/receptions\/[^/]+\/valider$/.test(path), action: 'pharmacie.reception.valider', module: 'pharmacie', idFromPath: (path) => idFrom(path, /receptions\/([^/]+)\/valider$/) },
  { method: 'DELETE', test: (path) => /\/api\/v1\/pharmacie\/receptions\/[^/]+$/.test(path), action: 'pharmacie.reception.delete', module: 'pharmacie', idFromPath: (path) => idFrom(path, /receptions\/([^/]+)$/) },

  { method: 'POST', test: (path) => path === '/api/v1/pharmacie/ajustements', action: 'pharmacie.ajustement.create', module: 'pharmacie' },

  { method: 'POST', test: (path) => path === '/api/v1/pharmacie/medicaments', action: 'pharmacie.medicament.create', module: 'pharmacie' },
  { method: 'PUT', test: (path) => /\/api\/v1\/pharmacie\/medicaments\/[^/]+$/.test(path), action: 'pharmacie.medicament.update', module: 'pharmacie', idFromPath: (path) => idFrom(path, /medicaments\/([^/]+)$/) },
  { method: 'DELETE', test: (path) => /\/api\/v1\/pharmacie\/medicaments\/[^/]+$/.test(path), action: 'pharmacie.medicament.delete', module: 'pharmacie', idFromPath: (path) => idFrom(path, /medicaments\/([^/]+)$/) },

  { method: 'POST', test: (path) => path === '/api/v1/pharmacie/unites', action: 'pharmacie.unite.create', module: 'pharmacie' },
  { method: 'PUT', test: (path) => /\/api\/v1\/pharmacie\/unites\/[^/]+$/.test(path), action: 'pharmacie.unite.update', module: 'pharmacie', idFromPath: (path) => idFrom(path, /unites\/([^/]+)$/) },
  { method: 'DELETE', test: (path) => /\/api\/v1\/pharmacie\/unites\/[^/]+$/.test(path), action: 'pharmacie.unite.delete', module: 'pharmacie', idFromPath: (path) => idFrom(path, /unites\/([^/]+)$/) },

  { method: 'POST', test: (path) => path === '/api/v1/pharmacie/familles', action: 'pharmacie.famille.create', module: 'pharmacie' },
  { method: 'PUT', test: (path) => /\/api\/v1\/pharmacie\/familles\/[^/]+$/.test(path), action: 'pharmacie.famille.update', module: 'pharmacie', idFromPath: (path) => idFrom(path, /familles\/([^/]+)$/) },
  { method: 'DELETE', test: (path) => /\/api\/v1\/pharmacie\/familles\/[^/]+$/.test(path), action: 'pharmacie.famille.delete', module: 'pharmacie', idFromPath: (path) => idFrom(path, /familles\/([^/]+)$/) },

  { method: 'POST', test: (path) => path === '/api/v1/pharmacie/fournisseurs', action: 'pharmacie.fournisseur.create', module: 'pharmacie' },
  { method: 'PUT', test: (path) => /\/api\/v1\/pharmacie\/fournisseurs\/[^/]+$/.test(path), action: 'pharmacie.fournisseur.update', module: 'pharmacie', idFromPath: (path) => idFrom(path, /fournisseurs\/([^/]+)$/) },
  { method: 'DELETE', test: (path) => /\/api\/v1\/pharmacie\/fournisseurs\/[^/]+$/.test(path), action: 'pharmacie.fournisseur.delete', module: 'pharmacie', idFromPath: (path) => idFrom(path, /fournisseurs\/([^/]+)$/) },

  { method: 'POST', test: (path) => path === '/api/v1/patients', action: 'patient.create', module: 'patient' },
  { method: 'POST', test: (path) => path === '/api/v1/clinique/visites', action: 'clinique.visite.create', module: 'clinique' },
  { method: 'POST', test: (path) => path === '/api/v1/clinique/consultations', action: 'clinique.consultation.create', module: 'clinique' },
  { method: 'PUT', test: (path) => /\/api\/v1\/clinique\/consultations\/\d+$/.test(path), action: 'clinique.consultation.update', module: 'clinique', idFromPath: (path) => Number(path.match(/consultations\/(\d+)$/)?.[1] ?? 0) },
];

export function matchWritePolicy(method, endpoint) {
  const path = normalizePath(endpoint);
  return WRITE_POLICIES.find((policy) => policy.method === method && policy.test(path)) ?? null;
}

export function isAuthBypassEndpoint(endpoint) {
  const path = normalizePath(endpoint);
  return [
    '/api/v1/login',
    '/api/v1/health',
    '/api/v1/token/refresh',
  ].includes(path);
}

export function shouldBypassCache(endpoint) {
  const path = normalizePath(endpoint);
  return isAuthBypassEndpoint(endpoint)
    || path.startsWith('/api/v1/admin')
    || path.includes('/export')
    || path.includes('/fiches-stock');
}

export function namedListForAction(action = '') {
  if (action.startsWith('pharmacie.vente')) return 'pharmacie.ventes';
  if (action.startsWith('pharmacie.demande_service')) return 'pharmacie.demandes';
  if (action.startsWith('pharmacie.reception')) return 'pharmacie.receptions';
  if (action.startsWith('pharmacie.medicament')) return 'pharmacie.medicaments';
  if (action.startsWith('pharmacie.unite')) return 'pharmacie.unites';
  if (action.startsWith('pharmacie.famille')) return 'pharmacie.familles';
  if (action.startsWith('pharmacie.fournisseur')) return 'pharmacie.fournisseurs';
  if (action.startsWith('pharmacie.ajustement')) return 'pharmacie.mouvements';
  return null;
}
