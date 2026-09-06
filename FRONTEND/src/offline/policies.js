function normalizePath(endpoint = '') {
  const [path] = String(endpoint).split('?');
  return path;
}

export const WRITE_POLICIES = [
  {
    method: 'POST',
    test: (path) => path === '/api/v1/pharmacie/ventes',
    action: 'pharmacie.vente.create',
    module: 'pharmacie',
  },
  {
    method: 'POST',
    test: (path) => /\/api\/v1\/pharmacie\/ventes\/\d+\/valider$/.test(path),
    action: 'pharmacie.vente.valider',
    module: 'pharmacie',
    idFromPath: (path) => Number(path.match(/ventes\/(\d+)\/valider/)?.[1] ?? 0),
  },
  {
    method: 'POST',
    test: (path) => path === '/api/v1/pharmacie/demandes-service',
    action: 'pharmacie.demande_service.create',
    module: 'pharmacie',
  },
  {
    method: 'POST',
    test: (path) => path === '/api/v1/patients',
    action: 'patient.create',
    module: 'patient',
  },
  {
    method: 'POST',
    test: (path) => path === '/api/v1/clinique/visites',
    action: 'clinique.visite.create',
    module: 'clinique',
  },
  {
    method: 'POST',
    test: (path) => path === '/api/v1/clinique/consultations',
    action: 'clinique.consultation.create',
    module: 'clinique',
  },
  {
    method: 'PUT',
    test: (path) => /\/api\/v1\/clinique\/consultations\/\d+$/.test(path),
    action: 'clinique.consultation.update',
    module: 'clinique',
    idFromPath: (path) => Number(path.match(/consultations\/(\d+)$/)?.[1] ?? 0),
  },
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
