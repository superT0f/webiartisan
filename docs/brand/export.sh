#!/usr/bin/env bash
# Régénère les exports PNG du logo depuis docs/brand/icon.svg.
# Prérequis : google-chrome (rasterisation SVG fidèle) + ImageMagick convert (resize PNG).
# NE PAS rasteriser le SVG avec convert seul : le moteur MSVG casse gradients et texte
# (constaté le 2026-07-14). Chrome headless = même moteur que le web.
set -euo pipefail
cd "$(dirname "$0")"
mkdir -p exports

CHROME="${CHROME:-google-chrome}"
WRAPPER="$(mktemp --suffix=.html)"
trap 'rm -f "$WRAPPER"' EXIT

# Un seul screenshot 1024×1024, puis downscales PNG→PNG (fiables).
printf '<!doctype html><meta charset="utf-8"><style>*{margin:0;padding:0}</style><img src="file://%s/icon.svg" width="1024" height="1024">' "$PWD" > "$WRAPPER"
"$CHROME" --headless --disable-gpu --no-sandbox \
  --default-background-color=00000000 --force-device-scale-factor=1 \
  --window-size=1024,1024 --screenshot="$PWD/exports/master-1024.png" \
  "file://$WRAPPER" >/dev/null 2>&1
echo "✓ exports/master-1024.png"

for size in 16 32 180 192 512; do
  case "$size" in
    16)  out=favicon-16.png ;;
    32)  out=favicon-32.png ;;
    180) out=apple-touch-icon-180.png ;;
    192) out=icon-192.png ;;
    512) out=icon-512.png ;;
  esac
  convert exports/master-1024.png -resize "${size}x${size}" "exports/$out"
  echo "✓ exports/$out"
done
