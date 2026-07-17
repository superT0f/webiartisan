/* Test manuel : mini-jeu coupon + mode admin sur la carte (stack locale docker)
 *
 * Scénario :
 *   1. Enregistre un joueur frais via l'API + login artisan 24 via l'API
 *   2. Pose spin_user_token + artisan_token en localStorage, charge /carte
 *   3. Marqueur 🎁 → fiche → « Coupon test » → overlay → « Révéler » → libellé + code
 *   4. Rangée admin « 🛡️ Halo 200 m » visible (halo or sur la carte)
 *   5. « 📍 Déplacer ma position » → clic sur la carte → toast « Position fictive définie »
 *
 * Run: node game-map-test.cjs   (exit 0 si tout passe, 1 sinon)
 */
const puppeteer = require('puppeteer');

const BASE = 'http://localhost:5173';
const API = 'http://localhost:8080/api';
const ARTISAN_EMAIL = 'e2e-1784306761@prigent.tech';
const PASSWORD = 'Password123!';
const SHOT = (n) => `/tmp/game-test-${n}.png`;

const results = [];
function assert(name, cond, detail = '') {
  results.push({ name, ok: !!cond });
  console.log(`${cond ? 'PASS' : 'FAIL'} — ${name}${detail ? ` — ${detail}` : ''}`);
}

async function apiPost(path, body) {
  const res = await fetch(`${API}${path}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  return res.json();
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  // --- 1. Comptes via API -------------------------------------------------
  const playerEmail = `e2e-player-${Date.now()}@prigent.tech`;
  const reg = await apiPost('/users/register', {
    email: playerEmail,
    password: PASSWORD,
    display_name: 'E2E Player',
  });
  assert('inscription joueur via API', reg && reg.success, playerEmail);

  const login = await apiPost('/users/login', { email: playerEmail, password: PASSWORD });
  const userToken = login && login.token;
  assert('login joueur via API', !!userToken);

  const artisanLogin = await apiPost('/artisans/login', { email: ARTISAN_EMAIL, password: PASSWORD });
  const artisanToken = artisanLogin && artisanLogin.token;
  assert('login artisan 24 via API', !!artisanToken);
  if (!userToken || !artisanToken) throw new Error('Tokens manquants, abandon');

  // --- 2. Navigateur ------------------------------------------------------
  const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });
  try {
    // Ouvre /carte ; si les tuiles/style MapTiler sont trop lents (marqueur
    // utilisateur absent ou hors écran), on retente sur une page NEUVE.
    // NB : la géolocalisation est mockée via evaluateOnNewDocument —
    // page.setGeolocation (override CDP) se révèle flaky en headless avec les
    // abonnements concurrents de l'app (getCurrentPosition x2 + watchPosition).
    let page = null;
    for (let attempt = 0; attempt < 3 && !page; attempt++) {
      const candidate = await browser.newPage();
      try {
        await candidate.setViewport({ width: 1280, height: 900 });
        await candidate.evaluateOnNewDocument((lat, lng) => {
          const coords = {
            latitude: lat, longitude: lng, accuracy: 10,
            altitude: null, altitudeAccuracy: null, heading: null, speed: null,
          };
          const position = { coords, timestamp: Date.now() };
          navigator.geolocation.getCurrentPosition = (ok) => setTimeout(() => ok(position), 0);
          navigator.geolocation.watchPosition = (ok) => { setTimeout(() => ok(position), 0); return 1; };
          navigator.geolocation.clearWatch = () => {};
        }, 49.1083, -0.7655);

        await candidate.goto(`${BASE}/carte`, { waitUntil: 'domcontentloaded' });
        await candidate.evaluate(
          (u, a) => {
            localStorage.setItem('spin_user_token', u);
            localStorage.setItem('artisan_token', a);
          },
          userToken,
          artisanToken
        );
        await candidate.goto(`${BASE}/carte`, { waitUntil: 'networkidle0', timeout: 60000 });
        await candidate.waitForSelector('.user-location-marker', { timeout: 20000 });
        // Le marqueur doit être visible à l'écran (sinon le flyTo n'a pas eu
        // lieu : carte centrée ailleurs, halo invisible sur le screenshot).
        await candidate.waitForFunction(
          () => {
            const el = document.querySelector('.user-location-marker');
            if (!el) return false;
            const r = el.getBoundingClientRect();
            return r.x >= 0 && r.y >= 0 && r.right <= window.innerWidth && r.bottom <= window.innerHeight;
          },
          { timeout: 20000 }
        );
        page = candidate;
      } catch (e) {
        console.log(`carte non prête (tentative ${attempt + 1}/3), nouvelle page…`);
        await candidate.close();
      }
    }
    if (!page) throw new Error('Carte non chargée : marqueur utilisateur absent');

    // --- 3. Marqueur 🎁 → fiche → jeu --------------------------------------
    await page.waitForSelector('.artisan-marker .marker-gift', { timeout: 20000 });
    const giftCount = await page.$$eval('.artisan-marker .marker-gift', (els) => els.length);
    assert('marqueur avec 🎁 présent', giftCount > 0, `${giftCount} marqueur(s)`);
    await page.screenshot({ path: SHOT('1-carte') });

    // Les marqueurs sont re-rendus à chaque chargement (artisans/pois) : on réessaie
    let sheetOpened = false;
    for (let attempt = 0; attempt < 4 && !sheetOpened; attempt++) {
      await page.evaluate(() => {
        document.querySelector('.artisan-marker .marker-gift')?.closest('.artisan-marker')?.click();
      });
      try {
        await page.waitForSelector('.sheet', { timeout: 2500 });
        sheetOpened = true;
      } catch (e) { /* marqueur re-rendu, on retente */ }
    }
    if (!sheetOpened) throw new Error('La fiche artisan ne s\'ouvre pas après 4 tentatives');
    const sheetText = await page.$eval('.sheet', (el) => el.innerText);
    assert('fiche affiche le bouton « Coupon test »', sheetText.includes('Coupon test'));
    await sleep(400); // fin de la transition slide-up
    await page.screenshot({ path: SHOT('2-fiche') });

    await page.evaluate(() => {
      [...document.querySelectorAll('.sheet .play-btn')]
        .find((b) => b.textContent.includes('Coupon test'))
        .click();
    });
    await page.waitForSelector('.game-overlay .coupon-game', { timeout: 5000 });
    assert('overlay du jeu ouvert', true);
    await sleep(400);
    await page.screenshot({ path: SHOT('3-overlay-jeu') });

    // --- 4. Révéler → libellé + code + toast --------------------------------
    await page.click('.coupon-game__btn');
    await page.waitForSelector('.coupon-game__result', { timeout: 8000 });
    const resultText = await page.$eval('.coupon-game__result', (el) => el.innerText);
    assert('résultat contient le libellé « -10% test »', resultText.includes('-10% test'), resultText.replace(/\n/g, ' | '));
    assert('résultat contient « Code : TEST10 »', resultText.includes('Code : TEST10'));

    let toastOk = false;
    try {
      await page.waitForFunction(
        () => document.querySelector('.toast-container')?.innerText.includes('Coupon débloqué'),
        { timeout: 4000 }
      );
      toastOk = true;
    } catch (e) { /* toast manquant */ }
    assert('toast « 🎁 Coupon débloqué ! » affiché', toastOk);
    await page.screenshot({ path: SHOT('4-resultat') });

    // --- 5. Mode admin : rangée halo + halo or visible ----------------------
    await page.click('.game-panel__close'); // referme l'overlay pour voir la carte
    await page.waitForFunction(() => !document.querySelector('.game-overlay'), { timeout: 5000 });

    // Referme aussi la fiche et la popup du marqueur pour dégager la carte
    await page.evaluate(() => document.querySelector('.sheet-close')?.click());
    await page.waitForFunction(() => !document.querySelector('.sheet'), { timeout: 5000 }).catch(() => {});
    await page.evaluate(() => document.querySelector('.maplibregl-popup-close-button')?.click());

    await page.waitForFunction(
      () => document.querySelector('.map-controls')?.innerText.includes('Halo 200 m'),
      { timeout: 10000 }
    );
    const adminRow = await page.$eval('.map-controls .admin-row', (el) => el.innerText);
    assert('rangée admin « 🛡️ Halo 200 m » visible', adminRow.includes('Halo 200 m'));
    const haloChecked = await page.$eval('.map-controls .admin-row input[type=checkbox]', (el) => el.checked);
    assert('toggle halo activé par défaut', haloChecked);

    // Attend que le flyTo sur la position utilisateur se stabilise
    await page.waitForSelector('.user-location-marker', { timeout: 15000 });
    let prevPos = null;
    for (let i = 0; i < 25; i++) {
      const cur = await page.$eval('.user-location-marker', (el) => {
        const r = el.getBoundingClientRect();
        return `${Math.round(r.x)},${Math.round(r.y)}`;
      });
      if (cur === prevPos) break;
      prevPos = cur;
      await sleep(400);
    }
    await page.screenshot({ path: SHOT('5-halo-admin') });

    // --- 6. Téléportation de position ---------------------------------------
    const markerPosBefore = await page.$eval('.user-location-marker', (el) => {
      const r = el.getBoundingClientRect();
      return { x: r.x + r.width / 2, y: r.y + r.height / 2 };
    });

    await page.evaluate(() => {
      [...document.querySelectorAll('.map-controls button')]
        .find((b) => b.textContent.includes('Déplacer ma position'))
        .click();
    });
    await page.waitForFunction(
      () => document.querySelector('.toast-container')?.innerText.includes('Cliquez sur la carte'),
      { timeout: 5000 }
    );

    // Clic sur le canvas de la carte, 300 px à droite du centre
    await page.mouse.click(1280 / 2 + 300, 900 / 2);

    let teleportToastOk = false;
    try {
      await page.waitForFunction(
        () => document.querySelector('.toast-container')?.innerText.includes('Position fictive définie'),
        { timeout: 5000 }
      );
      teleportToastOk = true;
    } catch (e) { /* toast manquant */ }
    assert('toast « Position fictive définie » affiché', teleportToastOk);

    await sleep(500);
    const markerPosAfter = await page.$eval('.user-location-marker', (el) => {
      const r = el.getBoundingClientRect();
      return { x: r.x + r.width / 2, y: r.y + r.height / 2 };
    });
    const clickX = 1280 / 2 + 300;
    const clickY = 900 / 2;
    const distFromClick = Math.round(Math.hypot(markerPosAfter.x - clickX, markerPosAfter.y - clickY));
    const movedPx = Math.round(Math.hypot(markerPosAfter.x - markerPosBefore.x, markerPosAfter.y - markerPosBefore.y));
    assert(
      'marqueur utilisateur déplacé au point cliqué',
      movedPx > 50 && distFromClick < 40,
      `avant (${Math.round(markerPosBefore.x)},${Math.round(markerPosBefore.y)}) → après (${Math.round(markerPosAfter.x)},${Math.round(markerPosAfter.y)}), à ${distFromClick}px du clic`
    );
    await page.screenshot({ path: SHOT('6-teleport') });
  } finally {
    await browser.close();
  }

  // --- Bilan ----------------------------------------------------------------
  const failed = results.filter((r) => !r.ok);
  console.log(`\n${results.length - failed.length}/${results.length} assertions OK — screenshots /tmp/game-test-*.png`);
  if (failed.length) {
    console.error(`ÉCHECS: ${failed.map((f) => f.name).join(' ; ')}`);
    process.exit(1);
  }
  console.log('TOUT EST OK');
})().catch((e) => {
  console.error('FAIL fatal:', e.message);
  process.exit(1);
});
