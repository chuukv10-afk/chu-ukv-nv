# CHU UKV — Frontend (React + Vite + Joy UI + Redux)

Structure inspirée de motemaHub, adaptée aux modules du backend Symfony.

## Démarrage

```bash
cd APPLICATION/FRONTEND
npm install
cp .env.example .env
npm run dev
```

## Structure

```
src/
├── api/                 # Client HTTP + endpoints
├── assets/              # Images, icônes statiques
├── components/
│   ├── auth/            # Guards (RequireAuth, PermissionGuard…)
│   ├── layout/          # Header, Sidebar, AppLayout
│   └── ui/              # Composants UI réutilisables
├── constants/           # Routes, config API
├── features/            # Modules métier (1 dossier = 1 domaine backend)
│   ├── auth/
│   ├── organisation/
│   ├── referentiel/
│   ├── clinique/
│   ├── patient/
│   └── admin/
├── hooks/               # useAuth, usePermissions…
├── pages/               # Pages transverses (login, erreurs, dashboard)
├── routes/              # AppRouter
├── store/               # Redux global (auth, ui)
├── styles/              # CSS global
├── theme/               # Joy UI theme
└── utils/               # jwt, permissions…
```

## Règle features/

Chaque module contient :
- `*Page.jsx` — écran
- `*Api.js` — appels API du module
- `components/` — composants locaux (optionnel)

## Réponse API attendue (backend)

```json
{ "success": true, "message": "...", "data": {} }
{ "success": false, "message": "...", "errors": [] }
```
