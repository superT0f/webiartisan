# Design — Amélioration de l’authentification visiteur et de la gamification

**Date :** 2026-07-02  
**Statut :** Approuvé

## Contexte

WebiArtisan dispose déjà d’un espace visiteur (`/roue`, `/jeux`, `/jeu/:id`, `/profil`) avec une authentification par magic link (`/users/magic-link`). Cependant, l’expérience actuelle présente des frictions :

- Le magic link renvoie systématiquement vers `/roue`, même si la demande est faite depuis un mini-jeu (`/jeu/:id`).
- Il n’y a pas d’option “Rester connecté” pour les visiteurs.
- Aucun CTA “Se connecter / Mon compte” n’est visible dans la navigation quand l’utilisateur n’est pas connecté.
- Les artisans connectés à `/espace` ne peuvent pas jouer aux mini-jeux d’autres artisans, car les jeux nécessitent un token visiteur distinct.

## Objectifs

1. Permettre aux visiteurs de rester connectés longtemps (cookie rememberMe).
2. Ramener l’utilisateur sur la page d’origine après connexion via magic link.
3. Rendre le login visiteur plus visible dans la navigation.
4. Permettre aux artisans de jouer aux mini-jeux en leur créant / récupérant un compte visiteur lié.

## Choix retenus

- **Authentification obligatoire mais fluide.** Le visiteur doit se connecter pour jouer, mais les frictions sont réduites au maximum.
- **Compte visiteur lié automatiquement pour les artisans.** L’artisan authentifié obtient un token visiteur basé sur son email artisan, sans refaire de magic link.

## Backend

### `POST /users/magic-link`

- Reçoit `email`, `rememberMe` (défaut `true`) et `redirect` (path de la page d’origine, défaut `/roue`).
- Crée le compte dans `local_users` s’il n’existe pas.
- Génère un `magic_token` valable 1 heure.
- Construit le lien : `<origin>/<redirect>?token=<token>`.
- Loggue l’envoi (to, from, result, error) via les logs PHP.

### `POST /users/auth?token=<token>&rememberMe=<bool>`

- Valide le `magic_token` et son expiration.
- Crée un `session_token` :
  - `+365 days` si `rememberMe=true`,
  - `+30 days` sinon.
- Réinitialise `magic_token` et `magic_token_exp`.
- Retourne `{ success, token, data }`.

### Nouveau : `POST /artisans/me/consumer-token`

- Requiert `X-Artisan-Token` valide.
- Récupère l’email de l’artisan depuis `local_artisans`.
- Cherche un compte `local_users` avec le même email :
  - s’il existe, l’utilise ;
  - sinon, le crée avec `display_name = company_name` de l’artisan.
- Génère un `session_token` valable 365 jours.
- Retourne `{ success, token, data }`.

### Jeux et gamification

- Aucun changement dans `/games/:id/play` ni `/gamification/xp`.
- L’artisan joue avec son token visiteur, comme n’importe quel habitant.

## Frontend

### Helpers auth visiteur (`sites/artisans-shared/src/api.js`)

- `getUserToken()` : lit d’abord le cookie `user_token`, puis `localStorage`.
- `setUserToken(token, remember = false)` : écrit un cookie `Secure; SameSite=Strict; path=/` valable 365 jours si `remember`, sinon `localStorage`.
- `removeUserToken()` : supprime le cookie et le `localStorage`.

### Formulaires magic link

- `SpinWheel.vue` et `GamePlay.vue` affichent une case “Rester connecté sur cet appareil” cochée par défaut.
- `requestUserMagicLink(email, rememberMe, redirect)` transmet les trois paramètres.
- Après validation du token (`authUser`), le frontend redirige vers le `redirect` reçu.

### Navigation (`AppNav.vue`)

- Si aucun token visiteur : afficher “Se connecter / Mon compte” pointant vers `/profil`.
- Si token visiteur : afficher l’avatar + niveau (comportement actuel).
- `UserProfile.vue` redirige vers `/roue?redirect=/profil` si non connecté.

### Espace artisan (`Dashboard.vue`)

- Ajouter une tuile “🎮 Jouer aux mini-jeux”.
- Au clic : appeler `POST /artisans/me/consumer-token`.
- Stocker le token visiteur avec `rememberMe=true`.
- Rediriger vers `/jeux`.

### Profil / personnage

- Aucune modification structurelle ; le token visiteur persiste grâce au cookie.

## Flux artisan → joueur

1. Artisan connecté sur `/espace`.
2. Clic sur “Jouer aux mini-jeux”.
3. Frontend : `POST /artisans/me/consumer-token` avec `X-Artisan-Token`.
4. Backend : crée/récupère le compte visiteur lié, génère un token valable 365 jours.
5. Frontend : stocke le token dans un cookie et redirige vers `/jeux`.
6. L’artisan joue comme un visiteur classique.

## Sécurité

- Le magic token reste à usage unique et expire en 1 heure.
- Le cookie visiteur est `Secure` en prod, `SameSite=Strict`, `path=/`. Il n’est pas `HttpOnly` car le frontend JS en a besoin pour les appels API.
- L’endpoint `/artisans/me/consumer-token` ne divulgue le token visiteur que de l’artisan authentifié lui-même.

## Tests

- `scripts/test-api.sh` : ajouter un scénario magic-link visiteur + récupération de profil.
- Tests manuels :
  - magic link depuis `/roue`, `/jeux`, `/jeu/:id`, `/profil` ;
  - cookie rememberMe persistant après fermeture du navigateur ;
  - artisan connecté qui clique sur “Jouer” et arrive sur `/jeux` avec un token visiteur valide ;
  - déconnexion visiteur qui supprime cookie + `localStorage`.
