# WebiArtisan Brand Identity — Design Spec

> Date: 2026-07-13
> Scope: logo system, color palette, typography, brand voice, and migration plan for the web platform and the Flutter app. Functional mini-games redesign is a separate spec.

---

## 1. Goal

Give WebiArtisan a single, coherent brand identity across the web platform (shared Vue front, city sites, landing page) and the Flutter app, replacing today's ad-hoc visuals. The identity must carry the positioning: **libre (100% open source), local, conçu en France** — warm and artisanal, not generic-startup.

---

## 2. Background & Audit Findings

Current state, as found in the repository:

- **Name written 3 ways**: `WebiArtisan` (README), `WebIArtisan` (landing page), `Webi Artisans` (CSS comments).
- **No real logo**: the landing page (`sites/app-landing/index.php`) uses the 🏘️ emoji; the Flutter app icon is a teal→blue gradient square with a plain "W".
- **Two disconnected palettes**: web uses green/gold/cream (`artisans-shared/src/style.css`), the app uses teal/blue.
- **Marketing copy oversells games**: README and landing mention "coupon, quiz, tirage, vote, battle…" while only 3 game engines exist and none is used in production (see mini-games analysis, 2026-07-13).

---

## 3. Brand Decisions

- **Official spelling**: `WebiArtisan` (camelCase, one word).
- **Direction**: artisanal warmth ("chaleur") with a French touch carried by the **symbol** (a baguette), not by flag colors splashed everywhere. The tricolor appears only inside the baguette's slashes (grignes) and in the "Conçu en France" badge.
- **Positioning pillars**: **Libre** (open source, no hidden commission), **Local** (city-by-city, real proximity), **Français** (designed and hosted in France).
- **Multi-city model**: one master brand everywhere; each city keeps one accent color for badges and highlights.

---

## 4. Logo System

Base: draft **A2 « Chaleur »** (`docs/brand/drafts/a2-chaleur.svg`).

### 4.1 Primary logo (horizontal lockup)

- **Symbol**: stylized baguette, golden gradient `#D9A45B → #B5712B`, tilted −35°, with three grignes (slashes): blue `#2F4E8C`, cream `#F5F0E6`, red `#C8433C` — the discreet tricolor signature.
- **Wordmark**: "Webi" in terracotta `#C07A2E` + "Artisan" in ink `#2B2118`, Outfit ExtraBold (Inter ExtraBold acceptable as fallback in drafts).

### 4.2 Variants

| Variant | Usage |
|---------|-------|
| Horizontal (symbol + wordmark) | Headers, documents, landing |
| Icon (baguette alone, rounded square, cream background) | Flutter app icon, favicon, PWA icons |
| Symbol alone | Social avatars |
| Monochrome ink / white | Invoices, stamps, single-color print |

### 4.3 Rules

- Clear space: one grigne height around the logo on all sides.
- Minimum sizes: 24 px (icon), 120 px wide (horizontal lockup).
- Never recolor the symbol outside this charter; never stretch, rotate (other than the designed −35°), or add effects.
- On dark backgrounds: use the cream-background icon variant or the white monochrome wordmark.

---

## 5. Color Palette

| Role | Name | Hex | Usage |
|------|------|-----|-------|
| Primary | Terracotta | `#C07A2E` | Buttons, active links, "Webi" |
| Symbol gradient | Or baguette | `#D9A45B → #B5712B` | Baguette only |
| Text | Encre | `#2B2118` | Headings, body on cream |
| Background | Crème | `#FEF8EC` | Page backgrounds |
| Background alt | Crème 2 | `#F5F0E6` | Cards, the white grigne |
| Secondary text | Gris chaud | `#6B5D4F` | Muted text, tagline |
| Accent (rare) | Rouge | `#C8433C` | Red grigne, alerts, badges |
| France (rare) | Bleu nuit | `#2F4E8C` | Blue grigne, France badge only |

**City accents** (master brand stays terracotta; accent used for city badges, highlights, landing tiles):

| City | Emoji | Accent |
|------|-------|--------|
| Livry | 🌳 | Vert forêt `#2D6A4F` |
| Combs-la-Ville | 🏠 | Bleu nuit `#2F4E8C` |
| Vert-Saint-Denis | 🌻 | Jaune tournesol `#E9B949` |

---

## 6. Typography

No new fonts. Keep what the platform already loads:

- **Outfit** (700/800) — headings, wordmark final version
- **Inter** (400/500/600) — body, UI

Existing type scale in `artisans-shared/src/style.css` is preserved as-is.

---

## 7. Brand Voice & Messages

- **Tagline**: « L'annuaire libre des artisans de proximité »
- **Trust badge**: « 🇫🇷 100% open source · Conçu en France »
- **Existing signature kept**: « Faites vivre le commerce local »
- **Tone**: warm, direct, "vous" (consistent with the current site). Short sentences, artisan trade vocabulary (devis, intervention, savoir-faire).
- **Avoid**: startup jargon, empty superlatives ("révolutionnaire", "disruptif"), unnecessary anglicisms.
- **Games messaging**: marketing pages must describe the simplified game model (one free daily game with XP, merchant-activated games, premium tier) instead of the old "coupon, quiz, tirage, vote, battle" list. Exact wording follows the separate mini-games spec.

---

## 8. Deliverables

All brand assets live in `webiartisan.new/docs/brand/`:

- Master SVGs: horizontal logo, rounded-square icon, symbol alone, ink/white monochrome versions.
- PNG exports: favicons (16/32/180), PWA icons (192/512), Flutter launcher source (1024).
- `CHARTE.md`: the full brand guidelines, versioned in the repo.
- Drafts already produced: `docs/brand/drafts/` (A/B/C concepts + A2/A3 variants, SVG + PNG).

---

## 9. Migration Waves

| Wave | Focus | Deployable Alone |
|------|-------|------------------|
| 1 | Brand assets + `CHARTE.md` in `docs/brand/` — no code change | Yes |
| 2 | Web: CSS tokens in `artisans-shared/src/style.css` (green/gold → terracotta/cream), landing `app-landing/index.php` (🏘️ emoji → real logo + palette + trust badge), favicons for the 3 city sites | Yes |
| 3 | Flutter app: replace `assets/images/logo.png` + launcher icons via `flutter_launcher_icons` | Yes |

---

## 10. Out of Scope & Prerequisites

- **Mini-games redesign** (simplification, map integration, 3-tier model): separate spec, brainstormed after this one. Only the marketing *messages* are aligned here (§7).
- **Prerequisite for the "100% open source" claim**: the README currently states « Projet privé — WebiArtisan ». Before publishing the trust badge publicly, the owner must decide on the license and repository visibility. This spec does not cover that decision.
- No changes to game logic, XP, Stripe, or APIs.

---

## 11. Open Points (none blocking)

- Final wordmark rendering in Outfit (drafts use Inter, visually close).
- Icon-variant background (cream vs. terracotta) to be confirmed when producing the master SVGs in wave 1.
