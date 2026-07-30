# AGENTS.md — WebiArtisan

Guide pour les agents travaillant sur ce workspace. À garder à jour quand les conventions bougent.

## C'est quoi

Annuaire gamifié des artisans de proximité (multi-villes : Livry, Combs-la-Ville, Vert-Saint-Denis, Lieusaint). Sites web Vue 3 par ville + API PHP partagée + app Android Flutter (webview + pont natif). Gamification : XP, énergie, quêtes, déchets à ramasser, boss « Affamer de Gaffe », inventaire, galerie photo communautaire des POI.

## Layout du workspace

| Chemin | Rôle |
|---|---|
| `webiartisan.new/` | **Repo principal** (git, master) : `sites/api` (PHP), `sites/artisans-shared` (Vue partagé), `sites/webiartisan-<ville>` (4 sites, `artisans-shared` y est un **symlink**), `sites/e2e-dashboard` |
| `webiartisan-flutter-app/` | **App Android** (git séparé) : webview du site + pont `FlutterBridge` (GPS, biométrie, pickImage), fastlane/CI Play internal |
| `docs/` | Suivi `TODO.md` (hors git), dumps SQL |
| `archives/` | Backups hors git (ex: vhost admin legacy, contient des secrets — ne jamais committer) |
| `trash/`, `test-tof/` | Ancien code / zone de test perso — ne pas toucher |

## Commandes clés

```bash
# Front (par ville ; livry = référence de build)
cd webiartisan.new/sites/webiartisan-livry && npm run build
cd webiartisan.new/sites/artisans-shared && npm test        # vitest (56 tests)

# API locale (stack docker de contournement — TOUJOURS ce préfixe)
export COMPOSE_PROJECT_NAME=webiartisanfix
cd webiartisan.new && make test-php FILE=test_boss.php      # un fichier de sites/api/tests

# Avant une suite HTTP : purger le rate limit `login` (10/fenêtre, sinon 429)
docker compose exec -T php php -r '$pdo = new PDO("mysql:host=mysql;dbname=".getenv("DB_NAME"), getenv("DB_USER"), getenv("DB_PASS")); $pdo->exec("DELETE FROM api_rate_limits WHERE endpoint = \"login\"");'
```

- Vite de dev manuel : `VITE_API_URL=/api VITE_API_PROXY_TARGET=http://localhost:8080 npm run dev` (sinon l'app appelle la **prod** → 401 et purge du token).
- `php -l` sur tout fichier PHP modifié ; GD dispo dans le conteneur (sauf `imagecreatefromwebp` — la prod Gandi l'a, le conteneur non).

## Déploiement (Gandi Simple Hosting)

- **Montage sshfs** : `/home/tof/mnt/gandi` (vhosts sous `lamp0/web/vhosts/`). Si mort : `sshfs 4144916@sftp.dc2.gpaas.net:/ /home/tof/mnt/gandi -o password_stdin -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o reconnect < webiartisan.new/.gpaas-console-password`
- **API** : `make -C sites/api push` (rsync, `.env`/`uploads/`/`storage/` exclus). **Puis OBLIGATOIREMENT vérifier `curl -s -o /dev/null -w "%{http_code}" https://api.prigent.tech/api/health` = 200** (leçon de l'incident DB_USER, prod down 45 min).
- **Fronts** : `for s in webiartisan-livry webiartisan-combs webiartisan-vert-saint-denis webiartisan-lieusaint; do make -C sites/$s push; done` — déploiement **atomique en 3 phases** (assets hashés sans delete → index.html en dernier → ménage >7 j). Puis **OBLIGATOIREMENT** comparer les hash `index-*.js` live vs `dist/assets/` pour chaque ville — **le montage sshfs peut lâcher en silence en cours de rsync** (déjà 2 incidents) : sur MISMATCH, remonter le montage et repousser.
- **App** : bump `version:` dans `pubspec.yaml` (versionCode+1), `flutter test`, commit, `make publish-app` (podman, upload Play internal — `GOOGLE_PLAY_SERVICE_ACCOUNT_JSON` dans l'env shell). Vérifier le contenu de l'AAB avec `unzip -l build/app/outputs/bundle/release/app-release.aab`.
- **Migrations** : additives **uniquement**, dans `sites/api/lib/Migrations.php` (auto-appliquées au 1er appel API, registre `local_migrations`). Tout destructif = fichier `sites/api/migrations/0NN_*.sql` appliqué **à la main via phpMyAdmin** par l'utilisateur. Vérifier l'application via `/api/ops/health`.

## Endpoints ops (`/api/ops/*`)

`GET /ops/health` (migrations, DB, file email, maintenance), `GET /ops/logs`, `GET/POST /ops/maintenance` (+ `GET /api/maintenance` public), `GET /ops/db/tables`, `GET /ops/db/export?table=` (CSV anonymisé). Auth : `Authorization: Bearer $OPS_TOKEN` — token dans le `.env` prod, copie locale `OPS_TOKEN_PROD` dans `sites/api/.env` (gitignoré). Chaque accès est audit-logué.

## Pièges connus (appris en prod)

- **Gandi injecte `DB_USER=hosting-db` dans le FPM** du vhost : le `.env` de l'API doit gagner **inconditionnellement** pour `DB_*` (bloc dans `index.php` — ne jamais reconditionner à `getenv()`).
- **Varnish** cache les réponses anonymes : les sondes/debug doivent utiliser un cache-buster (`?v=$(date +%s)`) ; les réponses avec token sont `no-store`.
- **`docs/superpowers/` est gitignoré** mais les specs/plans y sont versionnés : `git add -f`.
- **`origin` pousse sur GitHub ET GitLab** (2 push URLs) — un seul `git push` suffit.
- **Position GPS** : `useGeolocation` est un **singleton** avec cache localStorage 5 min — ne pas ré-instancier d'état parallèle.
- **Legacy admin archivé** (2026-07-27) : `admin.prigent.tech` = page statique ; backup dans `~/project/webiartisan/archives/`. Admin actuel = `/espace/admin*` sur chaque ville + `/api/ops/*`.
- **File email** : la cron Gandi n'est pas configurée → fallback in-band dans `index.php` (1 traitement / 5 min, 5 mails, horodatage `local_settings.email_queue_last_run`). Trigger manuel : `GET /api/cron/process-email-queue?token=$CRON_SECRET`. Surveiller `email_queue_pending` dans `/ops/health`.
- **Comptes de test prod** : admin = artisan id 18 (`is_admin=1`, user id 2) ; le boss garanti spawn sur l'admin (0 m) ; garanties/bbox dans `routes/objects.php`.
- **RGPD** : exports anonymisés par défaut ; le ré-encodage GD des photos supprime l'EXIF.

## Conventions

- **Copie UI en français** ; commits style conventionnel français (`feat(api): …`, `fix(gallery): …`). L'utilisateur autorise commit + push directs sur master dans ce repo (déploiement continu).
- API : enveloppe JSON `{success, data|error, message}` ; auth joueur = `user_require_auth` (Bearer JWT), artisan = `X-Artisan-Token`, admin = artisan `is_admin` ; montants/rate limits via `middleware/RateLimit.php`.
- Front : composables singletons pour l'état partagé (pattern `useWorldObjects`) ; ne pas ajouter de dépendance sans vérifier l'existant.
- Suivi de session : consigner les décisions/incidents dans `docs/TODO.md` (racine, hors git).

## Workflow features (établi)

Boucle Superpowers pour toute évolution non triviale : **brainstorming** (questions une par une, design validé) → spec `docs/superpowers/specs/` → **writing-plans** → plan `docs/superpowers/plans/` → exécution **subagent-driven** (implémenteur + reviewer par tâche, ledger `.superpowers/sdd/progress.md`) → déploiement + vérifs prod → tests réels par l'utilisateur sur téléphone (souvent au Lidl de Combs). Bugs : cause racine d'abord (logs via `/api/ops/logs`, sondes temporaires supprimées après usage).

## Environnement utilisateur

Linux, tests en prod depuis un téléphone Android (app Flutter ou Firefox mobile). L'utilisateur déploie et teste en conditions réelles ; les retours arrivent en screenshots (zips dans `~/Downloads`).
