# Spec — GIF animé de marche (vue de dos) pour l’avatar joueur

Date : 2026-08-01
Statut : design validé

## Contexte

Les sites WebiArtisan affichent un avatar joueur sur la carte (`player-128.png`, vue de dos pieds joints) et en grand format (`player-back-512.png`). Pour donner de la vie au déplacement sur la carte, on veut une version animée « marche » vu de dos.

## Objectif

Créer un GIF animé transparent montrant l’avatar de dos en train de faire un pas en avant, avec un effet de marche en boucle.

## Décisions

- Base frame 1 : `webiartisan.new/sites/artisans-shared/public/avatar/player-back-512.png` (vue de dos, pieds joints).
- Frame 2 à générer : même personnage, vue de dos, un pied en avant (pied droit), fond transparent, dimensions cohérentes (~512×454 px).
- Frame 3 : retour à frame 1 (pieds joints).
- Frame 4 : frame 2 inversée horizontalement (pied gauche en avant).
- Timing : 200 ms/frame ; cycle complet 800 ms.
- Export en deux tailles : 128 px et 512 px de large.

## Livrables

1. `sites/artisans-shared/public/avatar/player-back-walk-step-512.png` — frame 2 générée (pied droit en avant).
2. `sites/artisans-shared/public/avatar/player-back-walk-512.gif` — GIF animé 512 px, fond transparent.
3. `sites/artisans-shared/public/avatar/player-back-walk-128.gif` — GIF animé 128 px, fond transparent.

## Plan technique

1. Générer la frame 2 à partir de `player-back-512.png` :
   - soit via ImageMagick (découpe des jambes, décalage, perspective), si le rendu est acceptable ;
   - sinon demander à l’utilisateur de générer la frame via son outil d’IA et l’importer.
2. Créer la frame 4 par miroir horizontal de la frame 2.
3. Assembler le GIF avec ImageMagick (`convert` / `magick`), transparence préservée, palette optimisée.
4. Redimensionner à 128 px.
5. Vérifier visuellement le résultat.
6. Rebuild et déploiement des 4 villes (`make -C sites/<ville> push`) avec vérification des hash en prod (piège du montage sshfs mort).

## Critères de succès

- Les deux GIF existent et sont lisibles dans un navigateur.
- Le personnage reste reconnaissable (même couleurs, même style).
- L’effet de marche est perçu en boucle.
- Les fichiers servent correctement sur la prod après déploiement.

## Risques

- La génération locale de la frame 2 peut produire un rendu peu naturel ; fallback = génération IA par l’utilisateur.
- Ombre et proportions peuvent ne pas être parfaites ; acceptable pour une première version.
