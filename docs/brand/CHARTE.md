# Charte graphique WebiArtisan

Version 1.0 — 2026-07-14
Spec de référence : `docs/specs/2026-07-13-brand-identity-design.md`

## 1. Identité

- Nom officiel : **WebiArtisan** (W majuscule, « Artisan » avec A majuscule — jamais « WebIArtisan » ni « Webiartisan »).
- Tagline : « L'annuaire libre des artisans de proximité »
- Signature historique (conservée) : « Faites vivre le commerce local »
- Badge confiance : « 🇫🇷 Conçu en France ». La variante « 100% open source · Conçu en France » est réservée au cas où le dépôt deviendrait public sous licence open source (spec §10).

## 2. Logo

Base : draft A2 « Chaleur » (`drafts/a2-chaleur.svg`) — baguette dorée aux grignes tricolores discrètes + wordmark bicolore.

### Fichiers masters

| Fichier | Usage |
|---|---|
| `logo-horizontal.svg` | Lockup complet : symbole + wordmark + tagline + badge confiance |
| `icon.svg` | Icône carrée (favicon, app, PWA) : baguette sur fond crème arrondi |
| `symbol.svg` | Baguette seule, fond transparent |
| `logo-mono-ink.svg` | Monochrome encre `#2B2118` — tampons, documents imprimés |
| `logo-mono-white.svg` | Monochrome blanc — fonds sombres |

### Règles d'usage

- Zone de protection : 1 hauteur de grigne (≈ 10 % de la hauteur du symbole) tout autour du logo.
- Taille minimale : 24 px pour l'icône, 120 px de large pour le lockup horizontal (en dessous, utiliser `icon.svg` ou `symbol.svg`).
- Interdits : déformer, recolorer le wordmark, supprimer les grignes tricolores, utiliser le lockup complet sous 120 px.

## 3. Couleurs

### Palette principale (tokens CSS de `sites/artisans-shared/src/style.css`)

| Token | Hex | Usage |
|---|---|---|
| `--c-green` | `#C07A2E` | Terracotta — primaire (boutons, liens actifs, accents) |
| `--c-green-dark` | `#B5712B` | Hover primaire |
| `--c-green-light` | `#D9A45B` | Bordures hover, accents légers |
| `--c-gold` | `#D9A45B` | Accents « premium » (badges, bannières) |
| `--c-gold-dark` | `#B5712B` | Hover gold |
| `--c-green-tint` | `#F5E5D0` | Fonds de chips/pastilles de marque |
| `--c-cream` | `#FEF8EC` | Fond de page |
| `--c-cream-2` | `#F5F0E6` | Cartes, zones secondaires |
| `--c-text` | `#2B2118` | Encre — texte principal |
| `--c-text-2` | `#6B5D4F` | Texte secondaire |
| `--c-text-3` | `#8A7B6C` | Texte tertiaire / muted |
| `--c-border` | `#E2D9C8` | Bordures |
| `--c-shadow` | `rgba(192,122,46,0.12)` | Ombres |

Gradient baguette (logo uniquement) : `#D9A45B` → `#B5712B`.

### Accents ville (landing, futurs thèmes)

| Ville | Hex | Emoji |
|---|---|---|
| Livry | `#2D6A4F` | 🌳 |
| Combs-la-Ville | `#2F4E8C` | 🏠 |
| Vert-Saint-Denis | `#E9B949` | 🌻 |

### Couleurs sémantiques (indépendantes de la marque, ne pas recolorer)

- Succès / « ouvert » : fond `#D8F3DC`, texte `#1B5E20`
- Erreur / « fermé » : fond `#FFEBEE`, texte `#B71C1C`
- Info : fond `#E3F2FD`, texte `#0D47A1`
- Étoiles : `#FFB300`

## 4. Typographie

- Titres et wordmark : **Outfit** 700/800 (fallback Inter)
- Corps de texte : **Inter** 400/500/600
- Chargement Google Fonts (déjà en place sur les sites ville et le landing) :
  `https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap`
- Aucune autre police.

## 5. Voix et ton

- Proximité, chaleur, clarté. Phrases courtes.
- Tutoiement dans les contextes grand public (jeux, gamification) ; vouvoiement dans les espaces artisans.
- Messages clés : la tagline, la signature et le badge confiance (§1) — orthographe et wording exacts.

## 6. Exports PNG

Générés depuis `icon.svg` par `export.sh` (Chrome headless + ImageMagick) dans `exports/` :
`master-1024.png`, `icon-512.png`, `icon-192.png`, `apple-touch-icon-180.png`, `favicon-32.png`, `favicon-16.png`.

Après toute modification d'un master : `docs/brand/export.sh` puis re-vérification visuelle.
Ne jamais rasteriser les masters avec `convert` seul (moteur MSVG : gradients et texte cassés).

## 7. Déclinaisons

- Sites ville (web) : `favicon.svg` + PNG, `theme-color` `#C07A2E`, tokens CSS ci-dessus.
- Landing `app.prigent.tech` : `logo-horizontal.svg`, accents ville sur les tuiles, badge confiance.
- App Flutter : launcher icon + splash régénérés depuis `master-1024.png` (fond crème), seed Material `#C07A2E`, surfaces sombres encre `#2B2118`.
