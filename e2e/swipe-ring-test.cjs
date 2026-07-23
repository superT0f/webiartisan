/* Test e2e : anneau de swipe (gamification carte)
 *
 * Scénario :
 *   1. Joueur frais via l'API, géoloc mockée sur un objet du monde
 *   2. /carte → marqueur actif (halo .marker--active) visible
 *   3. Tap marqueur → overlay .ring-overlay
 *   4. Swipe synthétique (cercle complet) → toast « +… XP »
 *
 * Run: node swipe-ring-test.cjs   (exit 0 si tout passe, 1 sinon)
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
  // 1. Joueur frais + un objet à portée
  const email = `e2e-swipe-${Date.now()}@prigent.tech`;
  await api('POST', '/users/register', { email, password: 'Password123!' });
  const login = await api('POST', '/users/login', { email, password: 'Password123!' });
  const token = login.json.token;
  assert('register + login joueur', !!token);

  const list = await api('GET', `/objects?lat=${CITY.lat}&lng=${CITY.lng}&city=${CITY.slug}`, null, token);
  const obj = list.json.data?.objects?.[0];
  assert('objet disponible via API', !!obj);
  if (!obj || !token) process.exit(1);

  // 2. Navigateur mocké sur la position de l'objet
  const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 800, height: 600 });
  const ctx = browser.defaultBrowserContext();
  await ctx.overridePermissions(BASE, ['geolocation']);
  await page.setGeolocation({ latitude: obj.lat, longitude: obj.lng, accuracy: 5 });
  await page.evaluateOnNewDocument((t) => {
    localStorage.setItem('spin_user_token', t);
  }, token);
  await page.goto(`${BASE}/carte`, { waitUntil: 'networkidle2', timeout: 30000 });
  await sleep(5000);

  // 3. Marqueur actif (halo) → tap → overlay
  await page.waitForSelector('.object-marker.marker--active', { timeout: 15000 }).catch(() => null);
  const activeMarker = await page.$('.object-marker.marker--active');
  assert('marqueur actif (halo) visible', !!activeMarker);
  if (activeMarker) {
    await activeMarker.click();
    await page.waitForSelector('.ring-overlay', { timeout: 5000 }).catch(() => null);
    assert('overlay anneau ouvert', !!(await page.$('.ring-overlay')));

    // 4. Swipe synthétique : cercle complet autour du centre du stage
    const stage = await page.$('.ring-stage');
    if (stage) {
      const box = await stage.boundingBox();
      const cx = box.x + box.width / 2;
      const cy = box.y + box.height / 2;
      const r = box.width * 0.43;
      await page.mouse.move(cx + r, cy);
      await page.mouse.down();
      for (let a = 0; a <= 48; a++) {
        const t = (a / 48) * 2 * Math.PI;
        await page.mouse.move(cx + r * Math.cos(t), cy + r * Math.sin(t));
        await sleep(20);
      }
      await page.mouse.up();

      const toastEl = await page.waitForSelector('.toast', { timeout: 15000 }).catch(() => null);
      const toasts = toastEl ? await page.$$eval('.toast', (els) => els.map((e) => e.textContent).join(' | ')).catch(() => '') : '';
      assert('toast XP après swipe', /XP/.test(toasts), toasts.slice(0, 120));
      await page.screenshot({ path: '/tmp/swipe-ring-test.png' });
    }
  }

  await browser.close();
  const failed = results.filter((r) => !r.ok);
  console.log(failed.length ? `\n${failed.length} test(s) en échec` : '\nTout passe ✅');
  process.exit(failed.length ? 1 : 0);
})();
