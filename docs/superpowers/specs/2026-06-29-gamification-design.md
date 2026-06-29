# Design — Gamification consommateur / habitant

## Contexte

WebiArtisan dispose déjà d’un compte utilisateur simple par email (magic link) et d’un module « Spin Wheel ». L’objectif est de transformer ce compte en un **personnage ludique** qui progresse en découvrant les artisans, les recettes et les commerçants locaux.

## Objectifs

- Favoriser la création de compte et le retour sur le site.
- Valoriser les actions utiles pour l’écosystème local.
- Offrir un sentiment de progression sans complexité excessive.
- Permettre une personnalisation simple du profil.

## Décisions clés

| Sujet | Décision |
|-------|----------|
| Public cible | **Consommateurs / habitants** (pas d’artisan ici). |
| Style d’avatar | **Image utilisateur** OU avatar choisi dans une **bibliothèque fournie** par l’équipe. Distinction homme / femme. |
| Progression | **Niveaux + XP** avec une courbe simple. |
| Récompenses | **Cosmétiques uniquement** (titres, badges, avatars débloqués). Pas d’avantage sur la roue pour rester équitable. |
| Quêtes | Actions du quotidien avec **cooldowns** (quotidien, horaire, par ressource). |

## Modèle de données

### Tables ajoutées / enrichies

#### `local_users` (colonnes supplémentaires)

| Colonne | Type | Description |
|---------|------|-------------|
| `display_name` | VARCHAR(80) | Pseudo public du personnage. |
| `avatar_type` | ENUM('default','upload','custom') | Origine de l’avatar actuel. |
| `avatar_url` | VARCHAR(255) | Chemin relatif vers l’image. |
| `avatar_gender` | ENUM('male','female','neutral') | Filtre de bibliothèque. |
| `level` | INT DEFAULT 1 | Niveau actuel. |
| `xp` | INT DEFAULT 0 | XP accumulée dans le niveau en cours. |
| `title` | VARCHAR(80) | Titre public affiché. |

#### `local_user_actions`

Log de chaque action gamifiée.

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | |
| `user_id` | INT FK | |
| `action_key` | VARCHAR(50) | Identifiant de l’action. |
| `xp_amount` | INT | XP gagnée. |
| `metadata` | JSON | Contexte (ex: `{"artisan_id": 12}`). |
| `created_at` | TIMESTAMP | |

#### `local_user_cooldowns`

Cooldowns par action.

| Colonne | Type | Description |
|---------|------|-------------|
| `user_id` | INT FK | |
| `action_key` | VARCHAR(50) | |
| `period` | VARCHAR(20) | `daily`, `hourly`, `once_per_resource`. |
| `resource_key` | VARCHAR(255) | Optionnel, ex: `artisan:12`. |
| `last_at` | TIMESTAMP | Dernier déclenchement. |

#### `local_user_badges`

Badges débloqués.

| Colonne | Type | Description |
|---------|------|-------------|
| `user_id` | INT FK | |
| `badge_key` | VARCHAR(50) | |
| `unlocked_at` | TIMESTAMP | |

#### `local_user_streaks`

Série de connexion.

| Colonne | Type | Description |
|---------|------|-------------|
| `user_id` | INT FK | |
| `current_streak` | INT | Jours consécutifs. |
| `last_visit_date` | DATE | |

## Système d’XP et niveaux

### Courbe

`xp_to_next_level = level * 100`

| Niveau | XP nécessaire |
|--------|---------------|
| 1 → 2 | 100 |
| 2 → 3 | 200 |
| 5 → 6 | 500 |
| 10 → 11 | 1000 |

### Actions récompensées

| Action | XP | Cooldown |
|--------|----|----------|
| Visiter une fiche artisan | +5 | 1h par artisan |
| Tourner la roue | +10 | Quotidien |
| Valider un QR code chez un artisan | +25 | Une fois par offre |
| Consulter une recette | +3 | 1 jour par recette |
| Partager une fiche/recette | +15 | 3 par jour au total |
| Laisser un avis | +20 | 1 avis par artisan |
| Suggérer une amélioration de recette | +10 | 1 par recette |
| Revenir 3 jours de suite | +30 | Série |

### Level up

Lorsque `xp >= level * 100` :
1. `level += 1`.
2. `xp -= level * 100`.
3. Mettre à jour le titre si un palier est atteint.
4. Déclencher une animation côté client.

## Avatars et personnalisation

### Bibliothèque fournie

- Répertoire statique : `sites/webiartisan-xxx/public/avatars/{male,female}/`.
- Chaque avatar a un fichier `metadata.json` associé :

```json
{
  "id": "boulanger_male_01",
  "gender": "male",
  "name": "Le Boulanger",
  "unlock_level": 1,
  "unlock_badge": null
}
```

- Le nouvel utilisateur a un avatar neutre de niveau 1 par défaut.

### Upload personnel

- Formats acceptés : JPG, PNG.
- Poids max : 2 Mo.
- Redimensionnement backend : 256×256.
- Stockage : `uploads/avatars/{user_id}.jpg`.

### Personnalisation accessible

- Modifier `display_name`.
- Choisir le genre pour filtrer la bibliothèque.
- Choisir un avatar débloqué ou son upload.

## Cooldowns et anti-abus

- Chaque action est loguée dans `local_user_actions`.
- Avant d’attribuer de l’XP, le serveur vérifie `local_user_cooldowns`.
- Types de cooldowns :
  - `daily` : 1 fois par jour calendaire.
  - `hourly` : 1 fois par heure.
  - `once_per_resource` : 1 fois par ressource (artisan, recette, offre).

## Titres

| Niveau | Titre |
|--------|-------|
| 1 | « Nouveau dans le quartier » |
| 3 | « Explorateur local » |
| 5 | « Habitulé du marché » |
| 10 | « Ambassadeur du terroir » |
| 20 | « Légende du village » |

## Badges

| Badge | Condition |
|-------|-----------|
| Première visite | Visiter une fiche artisan. |
| Gourmand | Consulter 10 recettes. |
| Chanceux | Gagner 5 offres à la roue. |
| Bienfaiteur | Laisser 3 avis. |
| Généreux | Partager 5 pages. |
| Fidèle | Série de 7 jours de connexion. |

Certains badges débloquent des avatars exclusifs.

## UI / UX

### Header

- Icône de profil avec badge de niveau.
- Au clic : drawer ou navigation vers `/profil`.

### Page `/profil`

- Avatar, pseudo, titre, niveau, barre d’XP.
- Liste des badges débloqués.
- Historique des dernières actions.
- Bouton « Modifier mon personnage ».

### Page `/personnage` (ou modal)

- Choix du genre.
- Grille d’avatars disponibles (débloqués / verrouillés).
- Upload personnel.
- Modification du pseudo.

### Feedback en temps réel

- Toast « +5 XP — Visite d’artisan ».
- Animation level-up (confetti, popup récompenses).
- Roue : affichage du gain d’XP après un spin.

## API

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `/users/me` | GET | Profil enrichi (level, xp, title, avatar, badges). |
| `/users/me/avatar` | POST | Choisir ou uploader un avatar. |
| `/users/me/profile` | PUT | Modifier pseudo / titre. |
| `/actions` | POST | Enregistrer une action gamifiée (avec cooldown serveur). |
| `/users/me/actions` | GET | Historique. |
| `/users/me/badges` | GET | Badges débloqués. |
| `/avatars` | GET | Liste publique des avatars, filtrable par `gender` et `level`. |

## Non-objectifs

- Pas de classement public pour l’instant.
- Pas d’échange d’XP entre utilisateurs.
- Pas d’avantages concrets sur la roue (probabilités, spins supplémentaires).
- Pas de gamification côté artisan dans ce périmètre.

## Prochaines étapes

1. Créer la migration SQL.
2. Préparer le répertoire `public/avatars/{male,female}/` avec les assets.
3. Implémenter les endpoints API.
4. Créer les pages `/profil` et `/personnage`.
5. Ajouter les toasts et animations d’XP / level-up.
