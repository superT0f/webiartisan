/* Test e2e : objets du monde sur la carte (stack locale docker + vite dev)
 *
 * Scénario :
 *   1. Enregistre un joueur frais via l'API → login → token
 *   2. GET /api/objects près du centre ville → récupère un objet à portée (ou échoue)
 *   3. Géolocalisation mockée sur les coordonnées de l'objet, token en localStorage
 *   4. Charge /carte → markers .object-marker visibles, barre d'énergie visible
 *   5. FAB « Ramasser » → click → toast « +… XP »
 *
 * Run: node game-objects-test.cjs   (exit 0 si tout passe, 1 sinon)
 */
const puppeteer = require('puppeteer');

const BASE = 'http://localhost:5173';
const API = 'http://localhost:8080/api';
const CITY = { lat: 49.1081, lng: -0.7658, slug: 'livry' };

const results = [];
function assert(name, cond, detail = '') {
  results.push({ name, ok: !!cond });
  console.log(`${cond ? 'PASS' : 'FAIL'} — ${name}${detail ? ` — ${detail}` : ''}`);
}
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function api(method, path, body, token) {
  const res = await fetch(`${API}${path}`, {
    method,
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  return { status: res.status, json: await res.json() };
}

(async () => {
  // 1. Joueur frais (register silencieux → login pour le token)
  const email = `e2e-objects-${Date.now()}@prigent.tech`;
  await api('POST', '/users/register', { email, password: 'Password123!' });
  const login = await api('POST', '/users/login', { email, password: 'Password123!' });
  const token = login.json.token;
  assert('register + login joueur', !!token, JSON.stringify(login.json).slice(0, 120));

  // 2. Un objet à portée (spawn paresseux au premier appel)
  const list = await api('GET', `/objects?lat=${CITY.lat}&lng=${CITY.lng}&city=${CITY.slug}`, null, token);
  const obj = list.json.data?.objects?.[0];
  assert('objets visibles via API', !!obj, JSON.stringify(list.json).slice(0, 200));
  assert('énergie 100 au départ', list.json.data?.energy?.current === 100);
  assert('propreté exposée', typeof list.json.data?.city_cleanliness === 'number');
  if (!obj || !token) process.exit(1);

  // 3. Navigateur mocké sur la position de l'objet
  const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox'] });
  const page = await browser.newPage();
  const ctx = browser.defaultBrowserContext();
  await ctx.overridePermissions(BASE, ['geolocation']);
  await page.setGeolocation({ latitude: obj.lat, longitude: obj.lng, accuracy: 5 });
  await page.evaluateOnNewDocument((t) => {
    localStorage.setItem('spin_user_token', t);
  }, token);
  await page.goto(`${BASE}/carte`, { waitUntil: 'networkidle2', timeout: 30000 });

  // 4. Carte + HUD
  await sleep(4000);
  const objectMarkers = await page.$$('.object-marker');
  assert('markers objets sur la carte', objectMarkers.length >= 1, `${objectMarkers.length} markers`);
  const energyBar = await page.$('.energy-bar');
  assert('barre d\'énergie visible', !!energyBar);
  const cleanChip = await page.$('.cleanliness-chip');
  assert('badge ville propre visible', !!cleanChip);

  // 5. Ramassage via le FAB
  await page.waitForSelector('.checkin-fab--pickup', { timeout: 15000 }).catch(() => null);
  const fab = await page.$('.checkin-fab--pickup');
  assert('FAB ramassage visible', !!fab);
  if (fab) {
    await fab.click();
    const toastEl = await page.waitForSelector('.toast', { timeout: 15000 }).catch(() => null);
    const toasts = toastEl
      ? await page.$$eval('.toast', (els) => els.map((e) => e.textContent).join(' | ')).catch(() => '')
      : '';
    assert('toast XP après ramassage', /XP/.test(toasts), toasts.slice(0, 120));
    await sleep(1500);
  }

  await page.screenshot({ path: '/tmp/game-objects-test.png' });
  await browser.close();

  const failed = results.filter((r) => !r.ok);
  console.log(failed.length ? `\n${failed.length} test(s) en échec` : '\nTout passe ✅');
  process.exit(failed.length ? 1 : 0);
})();
