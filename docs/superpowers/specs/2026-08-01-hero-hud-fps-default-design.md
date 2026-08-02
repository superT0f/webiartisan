# Spec — HUD héros carte + vue FPS par défaut + avatar animé contextuel

Date : 2026-08-01
Statut : design validé

## Contexte

La carte devient l'écran principal du jeu, centrée sur la vue première personne (FPS). L'avatar animé en permanence nuit à l'immersion quand le joueur est statique. On veut un HUD joueur (avatar, niveau, énergie, inventaire) en haut à gauche, et masquer le bouton de changement de vue par défaut.

## Décisions

### A. Avatar animé au déplacement uniquement

- État par défaut du marker joueur en vue FP : image statique `player-back-512.png`.
- Quand la position GPS change de plus de 3 m entre deux mises à jour : le marker affiche `player-back-walk-128.gif` pendant 5 secondes, puis repasse en statique.
- Si un nouveau déplacement survient pendant la fenêtre, le timer de 5 s est réarmé.
- Implémentation : classe CSS `is-moving` togglée dans `upsertUserPosition()` d'`ImmersiveMap.vue` ; le `background-image` bascule en CSS (`.user-location-marker--avatar` statique, `.user-location-marker--avatar.is-moving` GIF). Aucune animation JS.

### B. Widget héros (`HeroWidget.vue`, nouveau)

- Position : `absolute; top: 12px; left: 12px; z-index: 20` dans `MapView.vue` (coin libre), visible dans tous les modes de carte, uniquement si `authenticated`.
- Contenu :
  - Avatar face (`avatar_url` du profil via `resolveAvatarUrl`, fallback `/avatar/player-512.png`) dans un cercle.
  - Anneau SVG doré autour du cercle, rempli à `xp / xp_needed` % (pattern repris de `UserProfile.vue`).
  - Badge « Nv.X » sous le cercle.
  - Barre d'énergie intégrée sous le badge (track + fill + `current/max`, label regen « +5 dans N min »), alimentée par `useEnergy()` — remplace `EnergyBar` haut-centre, qui est **retirée** de `MapView.vue`.
  - Chips d'inventaire groupées par `type` : `🔋×n` (`energy_store`), `🎯×n` (`boss_spawner`). Affichées seulement si n > 0.
- Consommation : clic sur une chip → popover de confirmation dans le widget (« Consommer 1 Réserve d'énergie ? +30 ⚡ » / « Invoquer Affamer de Gaffe ici ? », Confirmer/Annuler) → `refreshPosition()` one-shot → `activateInventoryItem(id, { lat, lng, city })` → compteur décrémenté, `setEnergy` pour `energy_store`, message d'erreur inline si GPS indisponible ou échec API.
- Données : `fetchUserMe(token)` (profil : level, xp, xp_needed, avatar_url) + `getInventory()` regroupé par type. Chargées au montage et rechargées après chaque consommation. Rafraîchies aussi quand `authEvents` change.
- Icônes items : emojis existants (`ITEM_META` d'`Inventory.vue` déplacé dans un module partagé ou dupliqué — pas d'images dédiées).

### C. Vue FPS par défaut + bouton de changement de vue masqué

- `MapView.vue` : si `localStorage.map_mode` absent → défaut `'fp'` (au lieu de `'2d'`). La migration douce depuis `map_3d` est conservée.
- Le bouton `.map3d-fab` (cycle 2D/3D/FP) est masqué par défaut ; affiché seulement si `localStorage.map_mode_switch === '1'`.
- `UserProfile.vue` : nouvelle section « ⚙️ Paramètres » avec interrupteur « Afficher le bouton de changement de vue » (checkbox liée à `localStorage.map_mode_switch`, cochée = `'1'`). Effet immédiat à la prochaine visite de la carte (pas de state global réactif requis — localStorage lu au montage de MapView).

## Livrables

1. `sites/artisans-shared/src/components/HeroWidget.vue` (nouveau).
2. `MapView.vue` : intégration HeroWidget, retrait EnergyBar, défaut `fp`, visibilité conditionnelle du bouton de vue.
3. `ImmersiveMap.vue` : classe `is-moving` + CSS statique/GIF.
4. `UserProfile.vue` : section Paramètres + interrupteur.
5. Tests vitest : logique de regroupement d'inventaire et bascule statique/animée (fonctions pures extraites).

## Critères de succès

- En vue FP, l'avatar est statique au repos et marche 5 s après un déplacement.
- Le HUD affiche avatar, anneau XP, niveau, énergie et inventaire à jour après consommation.
- La consommation d'une Réserve d'énergie crédite +30 ⚡ visible immédiatement dans le HUD.
- Le bouton de changement de vue est masqué par défaut et réactivable via `/profil` > Paramètres.
- 56+ tests vitest passent ; déploiement 4 villes avec hash vérifiés.

## Risques

- Le GPS web bruité peut déclencher l'animation sans déplacement réel → seuil 3 m.
- `activateInventoryItem` exige lat/lng → si GPS refusé, message d'erreur clair (pattern repris d'`Inventory.vue`).
- Joueurs existants avec `map_mode=2d` persisté : ils gardent leur mode (pas de migration forcée vers fp) — seul le défaut des nouveaux visiteurs change.
