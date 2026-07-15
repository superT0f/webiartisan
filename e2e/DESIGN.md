# Design : suite de tests end-to-end WebiArtisan + dashboard e2e.prigent.tech

## Contexte

La production WebiArtisan rencontre actuellement un **parcours d'authentification qui boucle**. L'analyse de la stack montre plusieurs hypothèses :

- Le cookie `user_token` défini par la WebView Flutter sur `.prigent.tech` n'a pas les flags `Secure`/`SameSite`, ce qui peut le faire rejeter/rejeter en HTTPS.
- Un token consommateur peut être utilisé pour accéder à une route artisan (`/espace`), provoquant un retour au login.
- Un `city_slug` artisan non concordant avec la ville chargée renvoie vers la page publique, qui renvoie vers le login.
- Des `redirect` en cascade dans les composants Vue (`UserProfile.vue`, `AppNav.vue`) peuvent créer une course au login.

Le projet actuel possède déjà des smoke tests API (`scripts/test-api.sh`) mais aucun test E2E couvrant le parcours complet web → API → mobile web. De plus, il n'existe pas d'interface centralisée pour visualiser les exécutions de tests et les logs de production en temps réel.

## Objectifs

1. Créer une suite de tests E2E open source couvrant le web (Vue), l'API (PHP) et le mobile web (PWA).
2. Reproduire et détecter la boucle d'authentification en production.
3. Fournir un dashboard web sur `e2e.prigent.tech` avec login admin et visualisation des tests.
4. Lire les logs de production en temps réel via le point de montage Gandi `~/mnt/gandi/vhosts`.
5. Permettre le déclenchement manuel et automatique (cron/CI) des tests.

## Approches considérées

| Approche | Description | Avantages | Inconvénients |
|----------|-------------|-----------|---------------|
| A. Playwright tout-en-un | Tests web/API/mobile web + log watcher dans Playwright | Batteries incluses, traces vidéo, gestion cross-domain | Nécessite Playwright (aussi OSS) au lieu de Puppeteer |
| **B. Puppeteer + Vitest** (choix retenu) | Puppeteer pour le navigateur, Vitest comme runner, Node.js pour l'API et le tail de logs | 100 % open source, stack maîtrisée, très proche du métal | Moins d'helpers intégrés que Playwright |
| C. Cypress | Tests web via Cypress, API via `cy.request` | Très populaire, debug visuel | Moins bon pour les cookies cross-domain et les multi-contextes |

## Architecture

```text
┌─────────────────────────────────────────────────────────────┐
│  e2e.prigent.tech (dashboard)                               │
│  Vue 3 + Vite frontend  ←──SSE──→  Node.js/Express backend  │
│                                     SQLite (runs.sqlite)    │
└──────────────────────────┬──────────────────────────────────┘
                           │ lit / écrit
┌──────────────────────────▼──────────────────────────────────┐
│  e2e/ runner Vitest + Puppeteer                             │
│  - specs web/API/mobile web                                 │
│  - logWatcher sur logs Gandi                                │
│  - écrit reports/latest.json + latest-logs.jsonl            │
└──────────────────────────┬──────────────────────────────────┘
                           │ exécute contre
        ┌──────────────────┼──────────────────┐
        ▼                  ▼                  ▼
 artisans-*.prigent.tech  api.prigent.tech   app.prigent.tech
      (Vue frontend)        (API PHP)         (PWA/WebView)
```

## Structure du projet

```text
webiartisan.new/e2e/
├── package.json
├── vitest.config.ts
├── tsconfig.json
├── .env.example
├── README.md
├── data/
│   └── runs.sqlite              # historique des exécutions
├── api/                         # backend dashboard
│   ├── src/
│   │   ├── server.ts
│   │   ├── db.ts
│   │   ├── auth.ts
│   │   └── routes/
│   │       ├── runs.ts          # CRUD exécutions
│   │       ├── logs.ts          # SSE/logs temps réel
│   │       └── trigger.ts       # lancer une suite manuellement
│   └── package.json
├── dashboard/                   # frontend Vue
│   ├── src/
│   │   ├── views/
│   │   │   ├── HomeView.vue     # liste des runs
│   │   │   ├── RunView.vue      # détail d'une exécution
│   │   │   └── LiveView.vue     # logs temps réel + bouton run
│   │   └── api.ts
│   └── package.json
├── src/                         # suite de tests
│   ├── config/
│   │   └── env.ts               # URLs, credentials test, chemins logs
│   ├── fixtures/
│   │   └── users.ts             # comptes de test
│   ├── helpers/
│   │   ├── browser.ts           # lancement Puppeteer
│   │   ├── api.ts               # client API
│   │   ├── cookies.ts           # helpers cross-domain
│   │   └── logWatcher.ts        # tail logs Gandi
│   ├── pages/                   # Page Object Model
│   │   ├── LoginPage.ts
│   │   ├── DashboardPage.ts
│   │   ├── MapPage.ts
│   │   └── ProfilePage.ts
│   └── specs/
│       ├── auth.loop.spec.ts
│       ├── consumer.magiclink.spec.ts
│       ├── artisan.dashboard.spec.ts
│       ├── api.smoke.spec.ts
│       ├── mobile.web.spec.ts
│       └── cookie.crossdomain.spec.ts
└── reports/
    ├── latest.json
    └── latest-logs.jsonl
```

## Composants

### `src/config/env.ts`

Centralise la configuration et valide les variables d'environnement obligatoires en mode production.

```ts
export const env = {
  mode: process.env.E2E_RUN_AGAINST_PROD === 'true' ? 'prod' : 'local',
  apiUrl: process.env.E2E_API_URL || 'http://localhost:8080/api',
  cityUrls: {
    livry: process.env.E2E_LIVRY_URL || 'http://localhost',
    combs: process.env.E2E_COMBS_URL || 'http://localhost',
    vertSaintDenis: process.env.E2E_VSD_URL || 'http://localhost',
  },
  admin: {
    username: process.env.E2E_ADMIN_USER!,
    password: process.env.E2E_ADMIN_PASS!,
  },
  testAccounts: {
    password: process.env.E2E_TEST_PASSWORD!,
  },
  logPaths: {
    api: '/home/tof/mnt/gandi/vhosts/api.prigent.tech/storage/logs',
    app: '/home/tof/mnt/gandi/vhosts/app.prigent.tech/htdocs/logs',
  },
};
```

### `src/helpers/logWatcher.ts`

- Détermine le fichier de log du jour (`api-YYYY-MM-DD.log`, `visits-YYYY-MM-DD.log`).
- Utilise `fs.watchFile` sur le fichier courant.
- Émet un événement Node.js par nouvelle ligne.
- Persiste dans `reports/latest-logs.jsonl`.
- Fournit une méthode `attach(testName)` pour tagger les logs par test courant.

### `src/helpers/api.ts`

Client API utilisant `fetch` natif de Node.js 18+ :

- `createConsumer(email)`
- `createArtisan(email, citySlug)`
- `login(email, password)`
- `requestMagicLink(email)`
- `cleanupTestAccount(id)`

### `src/helpers/browser.ts`

Lance Puppeteer avec un profil propre par test pour éviter la contamination des cookies/localStorage.

```ts
export async function newBrowserContext() {
  const browser = await puppeteer.launch({ headless: 'new' });
  const page = await browser.newPage();
  return { browser, page };
}
```

## Flux des tests

### `auth.loop.spec.ts`

1. Créer un consommateur via API.
2. Ouvrir `https://artisans-livry.prigent.tech` avec un `user_token` périmé en `localStorage`.
3. Vérifier qu'il y a **une et une seule** redirection vers `/login`.
4. Remplir le formulaire de login.
5. Vérifier l'atterrissage sur `/carte` et que `/users/me` retourne 200.
6. Variante artisan avec `artisan_token` sur `/espace`.

### `consumer.magiclink.spec.ts`

1. API : demander un magic-link.
2. En production : lire la boîte email de test ou utiliser un endpoint debug `/e2e/magic-link` si `E2E_ALLOWED=true`.
3. Ouvrir le lien et vérifier la connexion.

### `artisan.dashboard.spec.ts`

1. API : créer un artisan avec `city_slug = livry`.
2. Ouvrir `/espace` avec `artisan_token`.
3. Vérifier les widgets dashboard.

### `api.smoke.spec.ts`

Tests API purs : healthcheck, login consommateur/artisan, route protégée renvoie 401, rate-limit renvoie 429.

### `mobile.web.spec.ts`

Viewport mobile + touch events. Parcours accueil → carte → login → profil.

### `cookie.crossdomain.spec.ts`

Se connecter sur `artisans-livry.prigent.tech`, ouvrir `artisans-combs.prigent.tech`, vérifier le comportement du token.

## Dashboard web `e2e.prigent.tech`

### Stack

- **Frontend** : Vue 3 + Vite + TypeScript, dans `e2e/dashboard/`.
- **Backend** : Node.js/Express + TypeScript, dans `e2e/api/`.
- **Base de données** : SQLite (`e2e/data/runs.sqlite`).
- **Auth** : JWT avec un seul utilisateur admin, mot de passe en env.
- **Déploiement** : le frontend est buildé et poussé sur Gandi comme un site statique. Le backend Node.js/Express nécessite un hébergement supportant Node (VPS Gandi Cloud, ou exécuté localement derrière un reverse proxy). Alternative à valider : réécrire le backend en PHP pour rester sur Gandi Simple Hosting.

### Routes backend

| Méthode | Route | Description |
|---------|-------|-------------|
| POST | `/api/auth/login` | login admin |
| GET | `/api/runs` | liste des exécutions |
| GET | `/api/runs/:id` | détail d'un run |
| POST | `/api/runs/trigger` | lancer une suite |
| GET | `/api/logs/stream` | SSE logs temps réel |

### Vues frontend

- **HomeView** : tableau des runs avec statut, durée, nb passés/échoués.
- **RunView** : détail d'une exécution, erreurs, captures d'écran, logs associés.
- **LiveView** : logs de prod en temps réel + bouton "Lancer la suite".

## Sécurité & garde-fous

1. **Exécution conditionnelle** : `E2E_RUN_AGAINST_PROD=true` obligatoire pour la prod. Par défaut, les tests tournent contre `localhost`.
2. **Comptes de test dédiés** : emails `e2e-{uuid}@prigent.tech` créés et nettoyés par les tests.
3. **Tests non destructeurs** : pas de paiement Stripe réel, pas de suppression de données d'autrui.
4. **Feature flag API** : `E2E_ALLOWED=true` dans `.env.production` pour activer les endpoints de cleanup/magic-link debug.
5. **Backup prod** : un dump `dump-prod.sql` est réalisé avant la première exécution.
6. **Rate-limit** : backoff exponentiel côté test et surveillance des 429 dans les logs.

## CI/CD

### Commandes Makefile (ajouts dans `webiartisan.new/Makefile`)

```make
e2e-test:
	@cd e2e && npm run test:prod

e2e-dashboard-dev:
	@cd e2e && npm run dashboard

push-e2e:
	@cd e2e/dashboard && npm run build
	@rsync -avz e2e/dashboard/dist/ sites/e2e-dashboard/htdocs/
	@$(MAKE) -C sites/e2e-dashboard push
```

> Le backend Express est déployé séparément (VPS, PM2, systemd) ou réécrit en PHP si on reste sur Gandi Simple Hosting.


### Cycle de vie d'un run

1. Trigger (cron, manuel, ou dashboard).
2. Vitest exécute les specs.
3. Les rapports `latest.json` et `latest-logs.jsonl` sont générés.
4. Le backend dashboard importe le JSON dans SQLite.
5. Le frontend affiche le résultat.

### Exécution locale

```bash
cd webiartisan.new/e2e
cp .env.example .env
npm install
npm test              # contre localhost
npm run test:prod     # contre production
npm run dashboard     # backend + frontend dashboard
```

## Non-goals

- Pas de test de l'application Flutter native (hors scope, on reste sur la PWA/mobile web).
- Pas de modification de l'architecture d'authentification existante dans cette itération (hors patchs de cleanup mineurs).
- Pas d'envoi de vrais emails en production dans les tests (utilisation d'un endpoint debug dédié).

## Notes de mise en œuvre

- Vérifier que le point de montage Gandi `~/mnt/gandi/vhosts` est disponible sur la machine d'exécution.
- Le dashboard doit être déployé derrière HTTPS sur `e2e.prigent.tech`.
- Les credentials admin et le token E2E API doivent être injectés via variables d'environnement, jamais committés.
- Penser à ajouter `e2e/` dans `.gitignore` si des rapports locaux ne doivent pas être versionnés.
- Mettre à jour le `Makefile` principal et le README du monorepo avec les nouvelles commandes.
