# Avatar Back Walk GIF Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Créer un GIF animé transparent de l’avatar joueur vu de dos en train de faire un pas en avant, en deux tailles (128 px et 512 px), et le déployer sur les 4 villes.

**Architecture:** On part de `player-back-512.png` (frame 1, pieds joints). On génère une frame 2 « pas en avant » par manipulation locale (ImageMagick). On crée la frame 4 par miroir horizontal de la frame 2. On assemble un GIF 4 frames (stand → droit avant → stand → gauche avant) puis on redimensionne à 128 px.

**Tech Stack:** ImageMagick 6.9.12 (`convert`, `identify`), shell bash, git.

## Global Constraints

- Copie UI en français ; commits style conventionnel français (`feat(avatar): …`).
- Le dossier `docs/superpowers/` est gitignoré mais les specs/plans y sont versionnés : `git add -f`.
- Avant déploiement, vérifier le montage sshfs Gandi (`/home/tof/mnt/gandi`) ; après push, comparer les hash `index-*.js` live vs `dist/assets/` pour chaque ville.
- Ne pas déployer si le montage sshfs n’est pas actif.

## File Structure

| Fichier | Rôle |
|---|---|
| `sites/artisans-shared/public/avatar/player-back-512.png` | Frame 1 (existant, pieds joints) |
| `sites/artisans-shared/public/avatar/player-back-walk-step-512.png` | Frame 2 (créée, pied droit en avant) |
| `sites/artisans-shared/public/avatar/player-back-walk-step-512-mirror.png` | Frame 4 (créée, pied gauche en avant) |
| `sites/artisans-shared/public/avatar/player-back-walk-512.gif` | GIF animé 512 px |
| `sites/artisans-shared/public/avatar/player-back-walk-128.gif` | GIF animé 128 px |

---

### Task 1: Générer la frame 2 (pied droit en avant)

**Files:**
- Create: `sites/artisans-shared/public/avatar/player-back-walk-step-512.png`
- Read: `sites/artisans-shared/public/avatar/player-back-512.png`

**Interfaces:**
- Produces: `player-back-walk-step-512.png` (PNG transparent, 512×454 px, même style que `player-back-512.png`)

- [ ] **Step 1: Vérifier les dimensions de l’image source**

Run:
```bash
identify sites/artisans-shared/public/avatar/player-back-512.png
```
Expected: `PNG 512x454 512x454+0+0 8-bit sRGB ...`

- [ ] **Step 2: Créer la frame 2 par décalage du bas du corps**

On découpe l’image en deux : le haut (corps + tête, environ 0–360 px) et le bas (jambes + pieds, 360–454 px). On décale le bas de 12 px vers le bas et 8 px vers la droite pour simuler le pied droit en avant. On recolle le tout.

Run:
```bash
cd sites/artisans-shared/public/avatar
convert player-back-512.png -crop 512x360+0+0 +repage top.png
convert player-back-512.png -crop 512x94+0+360 +repage bottom.png
convert bottom.png -geometry +8+12 -background none bottom-shifted.png
convert top.png bottom-shifted.png -composite player-back-walk-step-512.png
rm -f top.png bottom.png bottom-shifted.png
```

- [ ] **Step 3: Vérifier la frame 2 générée**

Run:
```bash
identify sites/artisans-shared/public/avatar/player-back-walk-step-512.png
```
Expected: `PNG 512x454 ...` (dimensions conservées).

Puis ouvrir/afficher l’image pour inspection visuelle. Si le décalage ne rend pas un « pas en avant » crédible (jambes déformées, ombre cassée), **stopper la tâche et demander à l’utilisateur de générer la frame 2 via son outil d’IA**, puis la copier ici avant de reprendre.

- [ ] **Step 4: Commit**

Run:
```bash
cd sites/artisans-shared/public/avatar
git add player-back-walk-step-512.png
git commit -m "feat(avatar): frame 2 du cycle de marche (pied droit en avant)"
```

---

### Task 2: Générer la frame 4 (miroir pied gauche)

**Files:**
- Create: `sites/artisans-shared/public/avatar/player-back-walk-step-512-mirror.png`
- Read: `sites/artisans-shared/public/avatar/player-back-walk-step-512.png`

**Interfaces:**
- Consumes: `player-back-walk-step-512.png`
- Produces: `player-back-walk-step-512-mirror.png`

- [ ] **Step 1: Créer le miroir horizontal**

Run:
```bash
cd sites/artisans-shared/public/avatar
convert player-back-walk-step-512.png -flop player-back-walk-step-512-mirror.png
```

- [ ] **Step 2: Vérifier**

Run:
```bash
identify sites/artisans-shared/public/avatar/player-back-walk-step-512-mirror.png
```
Expected: `PNG 512x454 ...`

- [ ] **Step 3: Commit**

Run:
```bash
cd sites/artisans-shared/public/avatar
git add player-back-walk-step-512-mirror.png
git commit -m "feat(avatar): frame 4 du cycle de marche (miroir pied gauche)"
```

---

### Task 3: Assembler le GIF 512 px

**Files:**
- Create: `sites/artisans-shared/public/avatar/player-back-walk-512.gif`
- Read: `sites/artisans-shared/public/avatar/player-back-512.png`
- Read: `sites/artisans-shared/public/avatar/player-back-walk-step-512.png`
- Read: `sites/artisans-shared/public/avatar/player-back-walk-step-512-mirror.png`

**Interfaces:**
- Produces: `player-back-walk-512.gif` (GIF animé transparent, boucle infinie, 4 frames × 200 ms)

- [ ] **Step 1: Assembler le GIF**

Run:
```bash
cd sites/artisans-shared/public/avatar
convert -delay 20 -loop 0 \
  player-back-512.png \
  player-back-walk-step-512.png \
  player-back-512.png \
  player-back-walk-step-512-mirror.png \
  -dispose Background \
  player-back-walk-512.gif
```

- [ ] **Step 2: Vérifier le GIF**

Run:
```bash
identify sites/artisans-shared/public/avatar/player-back-walk-512.gif
```
Expected: 4 lignes, chacune `GIF 512x454 ...` avec `delay 20`.

Puis ouvrir le GIF dans un navigateur ou viewer pour confirmer l’animation et la transparence.

- [ ] **Step 3: Commit**

Run:
```bash
cd sites/artisans-shared/public/avatar
git add player-back-walk-512.gif
git commit -m "feat(avatar): GIF animé 512px du cycle de marche vu de dos"
```

---

### Task 4: Créer la version 128 px

**Files:**
- Create: `sites/artisans-shared/public/avatar/player-back-walk-128.gif`
- Read: `sites/artisans-shared/public/avatar/player-back-walk-512.gif`

**Interfaces:**
- Consumes: `player-back-walk-512.gif`
- Produces: `player-back-walk-128.gif`

- [ ] **Step 1: Redimensionner**

Run:
```bash
cd sites/artisans-shared/public/avatar
convert player-back-walk-512.gif -resize 128x113 player-back-walk-128.gif
```

- [ ] **Step 2: Vérifier**

Run:
```bash
identify sites/artisans-shared/public/avatar/player-back-walk-128.gif
```
Expected: 4 lignes, chacune `GIF 128x113 ...` avec `delay 20`.

- [ ] **Step 3: Commit**

Run:
```bash
cd sites/artisans-shared/public/avatar
git add player-back-walk-128.gif
git commit -m "feat(avatar): GIF animé 128px du cycle de marche vu de dos"
```

---

### Task 5: Vérification finale et déploiement

**Files:**
- Modify: `sites/artisans-shared/public/avatar/` (déjà commité)
- Deploy: 4 villes (`webiartisan-livry`, `webiartisan-combs`, `webiartisan-vert-saint-denis`, `webiartisan-lieusaint`)

**Interfaces:**
- Consumes: tous les assets créés dans Tasks 1–4

- [ ] **Step 1: Vérifier le montage sshfs**

Run:
```bash
mount | grep /home/tof/mnt/gandi
```
Expected: ligne contenant `sshfs` et `/home/tof/mnt/gandi`. Si absent, **stopper** et remonter le montage :
```bash
sshfs 4144916@sftp.dc2.gpaas.net:/ /home/tof/mnt/gandi -o password_stdin -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o reconnect < webiartisan.new/.gpaas-console-password
```

- [ ] **Step 2: Rebuild et push des 4 villes**

Run:
```bash
cd webiartisan.new
for s in webiartisan-livry webiartisan-combs webiartisan-vert-saint-denis webiartisan-lieusaint; do
  make -C sites/$s push
done
```
Expected: chaque push se termine sans erreur.

- [ ] **Step 3: Vérifier les hash en prod**

Pour chaque ville, comparer le hash `index-*.js` live vs `dist/assets/` :

Run:
```bash
for s in webiartisan-livry webiartisan-combs webiartisan-vert-saint-denis webiartisan-lieusaint; do
  echo "=== $s ==="
  local=$(ls -t sites/$s/dist/assets/index-*.js | head -1 | xargs -I{} md5sum {} | awk '{print $1}')
  live=$(curl -s https://webiartisan-${s#webiartisan-}.prigent.tech/ | grep -o 'assets/index-[^"]*\.js' | head -1 | xargs -I{} curl -s https://webiartisan-${s#webiartisan-}.prigent.tech/{} | md5sum | awk '{print $1}')
  echo "local=$local live=$live"
done
```
Expected: pour chaque ville, `local` == `live`. Si MISMATCH, remonter le montage et repousser.

- [ ] **Step 4: Commit final**

Run:
```bash
cd sites/artisans-shared/public/avatar
git add .
git commit -m "feat(avatar): déploiement du GIF de marche vu de dos"
```

---

## Self-Review

**1. Spec coverage**
- Frame 2 générée : Task 1 ✓
- Frame 4 miroir : Task 2 ✓
- GIF 512 px : Task 3 ✓
- GIF 128 px : Task 4 ✓
- Vérification visuelle : Task 3 Step 2 ✓
- Déploiement + vérification hash : Task 5 ✓

**2. Placeholder scan**
- Pas de TBD/TODO. Toutes les commandes sont explicites.

**3. Type consistency**
- Les noms de fichiers sont identiques entre les tâches. Les chemins sont cohérents (`sites/artisans-shared/public/avatar/`).
