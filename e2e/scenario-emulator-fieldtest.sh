#!/usr/bin/env bash
# Scenario émulateur — test terrain WebiArtisan contre la PROD
# Usage: e2e/scenario-emulator-fieldtest.sh [emulator-id]
# Prérequis: émulateur démarré, app installée, compte avec mot de passe.
set -u
ADB="${ADB:-adb}"
EMU="${1:-emulator-5554}"
EMAIL="${E2E_EMAIL:-superT0f@proton.me}"
PASS="${E2E_PASSWORD:-totototo}"
# Position de test : Lidl Combs-la-Ville
GEO_LNG="2.5641628"
GEO_LAT="48.6593660"
SHOT_DIR="/tmp/fieldtest"
mkdir -p "$SHOT_DIR"

A() { $ADB -s "$EMU" "$@"; }
shot() { A exec-out screencap -p > "$SHOT_DIR/$1.png" && echo "📸 $1"; }
tap() { A shell input tap "$1" "$2"; sleep "${3:-1}"; }
type_text() { A shell input text "$1"; sleep 1; }

# Trouve un élément par texte dans le dump uiautomator et tape son centre
tap_text() {
  local what="$1"
  A shell uiautomator dump /sdcard/ui.xml >/dev/null 2>&1
  A exec-out cat /sdcard/ui.xml 2>/dev/null > "$SHOT_DIR/ui.xml" || A pull /sdcard/ui.xml "$SHOT_DIR/ui.xml" >/dev/null 2>&1
  python3 - "$SHOT_DIR/ui.xml" "$what" <<'EOF'
import re, sys
xml = open(sys.argv[1], encoding='utf-8', errors='ignore').read()
what = sys.argv[2]
m = re.search(r'text="[^"]*' + re.escape(what) + r'[^"]*"[^>]*bounds="\[(\d+),(\d+)\]\[(\d+),(\d+)\]"', xml)
if not m:
    print("NOTFOUND"); sys.exit(0)
x = (int(m.group(1)) + int(m.group(3))) // 2
y = (int(m.group(2)) + int(m.group(4))) // 2
print(f"{x} {y}")
EOF
}

wait_network() {
  echo "attente réseau…"
  for i in $(seq 1 20); do
    if A shell ping -c 1 -W 2 8.8.8.8 2>/dev/null | grep -q "1 received"; then
      echo "réseau OK"; return 0
    fi
    sleep 3
  done
  echo "❌ pas de réseau"; return 1
}

echo "=== 0. Réseau ==="
wait_network || exit 1

echo "=== 1. Fresh login ==="
A shell pm clear tech.prigent.webiartisan
A shell monkey -p tech.prigent.webiartisan -c android.intent.category.LAUNCHER 1 >/dev/null 2>&1
sleep 10
tap 540 1224 1                       # champ Email
type_text "${EMAIL/@/\\@}"
A shell input keyevent 61            # TAB → champ mot de passe
sleep 1
type_text "$PASS"
sleep 1
C=$(tap_text "Se connecter")
[ "$C" != "NOTFOUND" ] && tap $C 8 || A shell input keyevent 66
# retry si « Pas de connexion internet »
if [ "$(tap_text 'Pas de connexion')" != "NOTFOUND" ]; then
  echo "connectivity flake — retry dans 10s"
  sleep 10
  C=$(tap_text "Se connecter")
  [ "$C" != "NOTFOUND" ] && tap $C 8
fi
sleep 6
shot 01-login

echo "=== 2. Ville : Combs-la-Ville ==="
C=$(tap_text "Combs-la-Ville")
[ "$C" != "NOTFOUND" ] && tap $C 12 || tap 540 348 12
shot 02-ville

echo "=== 3. Position GPS (Lidl) ==="
A emu geo fix "$GEO_LNG" "$GEO_LAT"
# accepter la permission localisation si affichée
P=$(tap_text "While using the app")
[ "$P" != "NOTFOUND" ] && tap $P 3 || true
sleep 6
shot 03-map

echo "=== 4. Check-in ==="
C=$(tap_text "Check-in")
if [ "$C" = "NOTFOUND" ]; then
  echo "⚠️ FAB Check-in introuvable (position non acquise ?) — reload et retry"
  tap 972 2220 8
  C=$(tap_text "Check-in")
fi
[ "$C" != "NOTFOUND" ] && tap $C 5 || echo "❌ toujours pas de FAB"
sleep 3
shot 04-checkin

echo "=== 5. État final ==="
B=$(tap_text "Connexion")
[ "$B" != "NOTFOUND" ] && echo "❌ popin Connexion affichée" || echo "✅ pas de popin"
T=$(tap_text "XP")
[ "$T" != "NOTFOUND" ] && echo "✅ toast XP visible"
echo "Terminé — captures dans $SHOT_DIR"
