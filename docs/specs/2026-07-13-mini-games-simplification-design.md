# WebiArtisan Mini-Games Simplification — Design Spec

> Date: 2026-07-13
> Scope: replace the 7-type games hub and the hidden legacy spin wheel with **3 games integrated directly into the map** (web + Flutter WebView): free GPS check-in, merchant-activated coupon, premium avatar spin (PokéStop-style, €2.99/month artisan subscription). Brand identity is a separate spec (`2026-07-13-brand-identity-design.md`).

---

## 1. Goal

Make games **instantly playable from the map** with a model a user understands in 10 seconds:

1. **Check-in POI** — free for everyone, rewards real visits with XP (Pokéstop loop).
2. **Coupon** — free, activated and configured by the merchant.
3. **Avatar spin (« Tournez l'avatar »)** — premium, unlocked by the existing €2.99/month artisan subscription.

Entry requires only a simplified account (email + magic link, single step).

---

## 2. Background — Why the Current System Fails

Facts from the codebase and the production dump (`docs/webiartisan.sql`, 2026-07-13):

- **Zero usage**: 0 game instances configured, 0 hub plays, 0 wheel wins, 1 spin in two weeks (by the admin), 0 real Stripe subscriptions.
- **Phantom games**: 7 game types seeded (`local_game_types`), only 3 engines exist (coupon, poll, vote); the 4 "premium" types sold at upgrade (quiz, bingo, rebus, wheel) have no engine.
- **Two parallel systems**: legacy wheel (`local_spin_*`, `/spin`, `/roue`) and hub (`local_game_*`, `/games`, `/jeux`) — the wheel also exists as a hub type with no engine.
- **Entry friction**: 4 auth tabs before spinning; magic-link round-trip before hub play; `/roue` linked from nowhere.
- **Games separated from the map**: they live on `/jeux` and `/roue`, not on `/carte`.
- **App is a WebView**: everything shipped in the Vue front is automatically in the app; the GPS bridge (`getPosition`/`watchPosition`) already exists.

Reusable as-is: XP/levels/badges/streaks engine (`sites/api/lib/Gamification.php`), the full Stripe premium stack (checkout, portal, idempotent webhooks, `artisanIsPremium()` gating), the legacy wheel backend (weighted draw, stock, win codes, shop validation, atomic daily limit), the map stack (MapLibre + `ArtisanSheet.vue`), consumer auth (`local_users`, magic link).

---

## 3. Game Model

### 3.1 Check-in POI (free, everyone)

- **Targets**: artisans **and** POIs already in the DB (migration 025).
- **Range**: button enabled when the player's GPS is within **200 m** of the point; distance validated **server-side** (haversine on stored coordinates).
- **Rewards (Pokéstop loop)**:
  - First check-in of the day at a point: **100 XP**.
  - The point then "recharges" every **10 minutes**: **10 XP** per check-in.
- **Balance note (accepted behavior)**: camping next to one point yields ~60 XP/h, below the 100 XP daily bonus — walking between points stays the optimal strategy.
- **Limits storage**: existing `local_user_cooldowns` — keys `poi_daily:{target_type}:{id}` (24 h) and `poi_spin:{target_type}:{id}` (10 min).

### 3.2 Coupon (free, merchant-activated)

- Existing engine kept (`CouponGame.vue` + `routes/games.php`); merchant configures the offer from `/artisan/jeux` (e.g. "-10% on first service").
- **Free-tier limit: 1 active game** per artisan (down from `FREE_TIER_MAX_ACTIVE_GAMES = 2`).
- Marker gets a 🎁 badge; "Jouer" button in the artisan bottom sheet.

### 3.3 Avatar spin — PokéStop-style (premium, €2.99/month)

> Amended 2026-07-13 (user decision): the fortune-wheel canvas UI is replaced by a PokéStop-style mechanic — the artisan's avatar spins to reveal the reward. Draw backend unchanged.

- Legacy backend **reused as-is**: stock-managed offers (`local_spin_offers`), unique win codes (`LIV-XXXXXXXX`), in-shop validation (`/espace/spin-wins`), atomic 1 spin/day/city limit.
- Gating already implemented (`artisanIsPremium()` + Stripe subscription at exactly €2.99/month — the brief's price).
- Playable from the artisan's bottom sheet as a PokéStop-style overlay: the artisan's avatar (`logo_url`, 🛍️ fallback) spins to reveal the reward; the hidden `/roue` page is removed.

### 3.4 Identification

- Simplified account **before** playing: email + magic link, **single step** (the 4-tab auth of `SpinWheel.vue` is dropped).
- XP is always tied to a `local_users` account — no anonymous/device XP.

---

## 4. Map UX ("Playable Map")

- **Markers**: 🎁 badge when the artisan has an active game (coupon configured, or avatar spin if premium); data via `GET /games?city=` extended with a `has_active_game` flag.
- **Floating "Check-in" button**: appears when any point enters the 200 m radius; GPS from `flutterBridge` (app) or `navigator.geolocation` (web).
- **`ArtisanSheet.vue`**: new "Jouer" section — Check-in button (enabled/greyed by distance + cooldown, shows remaining time), Coupon card if configured, « 🌀 Tourner l'avatar » button if the artisan is premium.
- **Game overlays**: coupon and avatar spin open as full-screen mobile panels over the map. Routes `/jeux`, `/jeu/:id`, `/roue` leave the nav; `/roue` redirects to `/carte`.
- **Feedback**: "+100 XP" / "+10 XP" toasts via the existing `useGamification` composable; confetti kept.

---

## 5. Data Model & API

### 5.1 New table `local_checkins`

```sql
CREATE TABLE local_checkins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    city VARCHAR(64) NOT NULL,
    target_type ENUM('artisan','poi') NOT NULL,
    target_id INT NOT NULL,
    xp_awarded INT NOT NULL,
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_target (user_id, target_type, target_id, checked_at),
    INDEX idx_city (city)
);
```

### 5.2 Endpoints

| Endpoint | Role |
|----------|------|
| `POST /checkin` | Body: `{ target_type, target_id, lat, lng }`. Auth: `local_users` session. Server validates distance (≤ 200 m), applies `poi_daily` / `poi_spin` cooldowns transactionally, inserts `local_checkins`, delegates XP to `gamificationRecordAction('poi_checkin')`. Returns `{ xp_awarded, next_spin_at, level_up? }`. |
| `GET /checkin/status?lat=&lng=` | Points within range with their cooldown state, for the floating button and sheet. |
| `GET /games?city=` | Extended with `has_active_game` per artisan (marker badges). |
| `POST /spin` (existing) | Unchanged backend; consumed from the map overlay. |

### 5.3 XP

- New `XP_ACTIONS` entry: `poi_checkin` (100 daily / 10 spin handled by the endpoint, cooldowns authoritative).
- Levels, titles, badges, streaks unchanged (`Gamification.php`).

---

## 6. Cleanup & Migration

**Removals** (no production data to preserve — verified on the dump):

- Phantom game types quiz, bingo, rebus, vote, poll: removed from the `local_game_types` seed and the `GameRenderer.vue` dispatch (coupon remains).
- `GamesHub.vue`, `GamePlay.vue`, routes `/jeux` and `/jeu/:id`, "beta" banners.
- `SpinWheel.vue` (739 lines) rewritten as a PokéStop-style map overlay (artisan avatar spin); the draw backend is kept.
- `routes/actions.php` (duplicate of `gamification/xp`) removed.
- `routes/avatars.php` (writes to the wrong `users` table) removed.
- `POST /games/:id/claim` (501) replaced: all real-world rewards use the legacy spin win-code system.

**Migration**:

- SQL `039`: create `local_checkins`, reseed `local_game_types` (keep `coupon`, add `wheel` mapped to the legacy system), idempotent (`CREATE TABLE IF NOT EXISTS`, `INSERT ... ON DUPLICATE KEY`).
- Free-tier active-game limit 2 → 1: no artisan has configured any game, zero impact.
- Deploy order (existing Makefile flow): SQL migration → API → city frontends.

---

## 7. Flutter App

**Zero code change** — the app is a WebView; everything arrives via the Vue front. Only verification: geolocation permissions are already declared and the `getPosition`/`watchPosition` bridge already exists.

---

## 8. Marketing Alignment

Per the brand spec (§7), marketing copy replaces "coupon, quiz, tirage, vote, battle" with the real model:

- « Faites un check-in chez vos artisans et gagnez de l'XP »
- « Des coupons offerts par vos commerçants »
- « Tournez l'avatar en boutique »

Touches: `sites/app-landing/index.php`, README, city sites home copy.

---

## 9. Out of Scope

- Player-side payments (the €2.99 subscription stays artisan-side, confirmed).
- New game engines beyond the three above.
- Anti-spoofing GPS beyond server-side distance validation (accepted risk for v1).
- Brand identity work (separate spec, same branch family).
