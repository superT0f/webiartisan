# Galerie photo communautaire dans les POI — Design (2026-07-27)

## Intent

Les POI n'ont aujourd'hui qu'une image unique (`local_pois.image_url`, gérée par
admin ou artisan owner via claims, migration 043). La galerie ouvre la
contribution à **tout joueur connecté** : chacun peut photographier un lieu
(Lidl, médiathèque, mairie…) et partager la photo dans la fiche POI.
Publication **immédiate**, modération **a posteriori** par signalement + file
admin. La fiche POI passe à deux onglets : **Photos** (défaut) et **Détails**
(infos pratiques).

## Décisions validées avec l'utilisateur

- Contributeurs : tout joueur connecté (auth consommateur JWT).
- Modération : signalement + file dans `/espace/admin` (pas de validation préalable).
- Affichage : galerie à la place des horaires dans la vue principale ; onglet
  « Détails » pour les infos pratiques (horaires d'abord, extensible).
- Architecture : table dédiée + chargement paresseux à l'ouverture de la fiche.
- Évolution 1 : accès rapide admin à l'upload de couverture existant depuis la fiche.
- Évolution 2 : cadeau inventaire pour l'uploadeur quand l'admin valide (« garde »)
  une photo signalée — message « Merci pour la photo, ami artisan », 1×/photo.

## Base de données (migration 049, additive, auto-appliquée)

```sql
CREATE TABLE local_poi_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poi_id INT NOT NULL,
    user_id INT NOT NULL,
    file_path VARCHAR(190) NOT NULL,             -- /uploads/pois/gallery/xxx.jpg
    status ENUM('active','validated','hidden','deleted') NOT NULL DEFAULT 'active',
    gifted_at DATETIME NULL,                     -- cadeau déjà versé (1×/photo)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_poi_status (poi_id, status),
    INDEX idx_user_created (user_id, created_at)
);

CREATE TABLE local_poi_photo_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    photo_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_report (photo_id, user_id),
    INDEX idx_photo (photo_id)
);

ALTER TABLE local_user_inventory
    ADD COLUMN source_label VARCHAR(120) NULL AFTER source_object_id;
```

Statuts photo : `active` (visible, non modérée), `validated` (visible, gardée
par l'admin), `hidden` (masquée), `deleted` (supprimée — fichier retiré).

## API (`routes/pois.php` étendu + `routes/admin.php` existant)

| Endpoint | Auth | Rôle |
|---|---|---|
| `GET /pois/:id/photos` | publique | photos `active`+`validated` du POI (id, url, created_at, `mine` si connecté) |
| `POST /pois/:id/photos` | joueur | upload multipart → conversion GD → insert |
| `DELETE /pois/photos/:id` | auteur ou admin | status → `deleted` + unlink fichier |
| `POST /pois/photos/:id/report` | joueur | signalement (1/photo/joueur), photo toujours visible |
| `GET /admin/photo-reports` | admin | file : photos signalées + nb de signalements |
| `POST /admin/photo-reports/:id/keep` | admin | status → `validated` + **cadeau inventaire** si pas déjà versé |
| `POST /admin/photo-reports/:id/hide` | admin | status → `hidden` |
| `POST /admin/photo-reports/:id/delete` | admin | status → `deleted` + unlink fichier |

### Upload (conversion basse résolution — exigence utilisateur)

- Multipart `image` : JPEG/PNG/WebP, 5 Mo max, vérif `finfo` (pattern 043).
- GD : `imagecreatefrom*` → `imagescale` ≤ 1600 px (largeur) → ré-encodage
  **JPEG q80** dans `uploads/pois/gallery/poi{id}_{user}_{hex}.jpg`
  (~200–400 Ko au lieu de plusieurs Mo). `uploads/` est déjà exclu du rsync.
- **Rate limit : 10 photos / jour / joueur** (`COUNT(*) … created_at >= CURDATE()` → 422).
- **Plafond : 30 photos actives / POI** (422 au-delà — évite les POI fourre-tout).
- Réponse : `{ id, file_path }` + toast front.

### Cadeau de validation (« Merci pour la photo, ami artisan »)

À `keep` : si `gifted_at IS NULL` → insert dans `local_user_inventory`
(`user_id` = uploadeur, `object_type='energy_store'`,
`source_label='Merci pour la photo, ami artisan'`) puis `gifted_at = NOW()`.
La vue Inventaire affiche `source_label` sous le libellé quand présent.

## Front

### PoiSheet → deux onglets

- **Photos** (défaut) :
  - Carousel horizontal à swipe : couverture `image_url` en premier si présente,
    puis les photos de galerie (`GET /pois/:id/photos` à l'ouverture de la fiche).
  - Bouton **📷 Ajouter une photo** : app → bridge `pickImage` (caméra/galerie,
    existant) ; web → `<input type=file>`. Envoi multipart (`FormData`).
  - **🚩 Signaler** sur chaque photo (confirmation, puis disabled).
  - **🗑️** sur mes photos (`mine`).
  - **📷 Couverture** visible si `isAdmin` : upload direct vers l'endpoint image
    unique existant (X-Artisan-Token) — accès rapide admin sans passer par
    `/espace/admin`.
  - Compteur « N photos » ; état vide : « Sois le premier à photographier ce lieu ! »
- **Détails** : horaires (`schedules`), adresse, téléphone — déplacés de la vue
  principale (onglet extensible : infos pratiques futures).
- Le bouton **check-in** reste visible quel que soit l'onglet.

### Admin `/espace/admin` — onglet « Signalements photos »

Liste : miniature, POI, uploadeur (anonymisé), nb de signalements, date ;
actions **Garder** (→ validated + cadeau), **Masquer**, **Supprimer**.

## Tests

- `test_poi_photos.php` : auth requise (401), upload OK + resize réel ≤ 1600 px
  (vérifié via `getimagesize`), mime refusé, rate limit 10/j, plafond 30/POI,
  liste publique, report unique (2e → 409/422), keep → `validated` + cadeau
  inventaire 1× (2e keep → pas de doublon), hide/delete, auteur supprime sa
  photo, tiers ne peut pas.
- Régressions : `test_poi_images.php` (couverture 043 intacte), vitest 56.

## Hors scope (YAGNI)

- Commentaires/légendes sur les photos, likes, tri, notifications push à la
  validation, galerie plein écran dédiée, modération automatique (2+ signalements),
  suppression de `image_url` (reste la couverture).
