/* Test e2e : cadeaux artisans de bout en bout (stack locale docker + vite dev)
 *
 * Scénario :
 *   1. Enregistre un artisan frais via l'API → login → token
 *   2. /e2e/prepare-artisan : activation + plan premium + coordonnées GPS
 *   3. Navigateur : /artisan/jeux → section « Cadeaux sur la carte »
 *      → clic « Placer un cadeau à ma boutique » → message de succès
 *   4. GET /objects/mine : le cadeau existe
 *   5. Joueur frais : géoloc sur la boutique, /carte → marker 🎁 visible
 *      → FAB « Ramasser » → toast « +15 XP » (coût énergie 0)
 *
 * Run: node artisan-gifts-test.cjs   (exit 0 si tout passe, 1 sinon)
 */
const puppeteer = require('puppeteer');

const BASE = 'http://localhost:5173';
const API = 'http://localhost:8080/api';
const E2E_TOKEN = 'local-e2e-token';
const SHOP = { lat: 49.1081, lng: -0.7658, slug: 'livry' };
const PASSWORD = 'Password123!';

const results = [];
function assert(name, cond, detail = '') {
  results.push({ name, ok: !!cond });
  console.log(`${cond ? 'PASS' : 'FAIL'} — ${name}${detail ? ` — ${detail}` : ''}`);
}
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function api(method, path, body, headers = {}) {
  const res = await fetch(`${API}${path}`, {
    method,
    headers: { 'Content-Type': 'application/json', ...headers },
    body: body ? JSON.stringify(body) : undefined,
  });
  return { status: res.status, json: await res.json() };
}

(async () => {
  // --- 1. Artisan frais ---------------------------------------------------
  const artisanEmail = `e2e-gift-${Date.now()}@prigent.tech`;
  const reg = await api('POST', '/artisans/register', {
    company_name: 'Boulangerie E2E Gifts',
    city_slug: SHOP.slug,
    category_slug: 'boulangerie',
    email: artisanEmail,
    phone: '0600000000',
    password: PASSWORD,
    address: '1 rue de la République, Livry',
  });
  assert('register artisan', reg.json.success === true, JSON.stringify(reg.json).slice(0, 150));
  const artisanId = reg.json.data?.id;
  assert('id artisan retourné', !!artisanId);
  if (!artisanId) process.exit(1);

  // --- 2. Activation + premium + géo via helper e2e (avant login : pending) -
  const prep = await api('POST', `/e2e/prepare-artisan/${artisanId}`, { plan: 'premium', lat: SHOP.lat, lng: SHOP.lng }, { 'X-E2E-Token': E2E_TOKEN });
  assert('prepare-artisan (activation + premium + géo)', prep.json.ok === true, JSON.stringify(prep.json).slice(0, 120));

  const login = await api('POST', '/artisans/login', { email: artisanEmail, password: PASSWORD, rememberMe: true });
  const artisanToken = login.json.token;
  assert('login artisan', !!artisanToken, JSON.stringify(login.json).slice(0, 150));
  if (!artisanToken) process.exit(1);

  // --- 3. Placement via l'UI ----------------------------------------------
  const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 900, height: 700 });
  await page.evaluateOnNewDocument((t) => {
    localStorage.setItem('artisan_token', t);
  }, artisanToken);
  await page.goto(`${BASE}/artisan/jeux`, { waitUntil: 'networkidle2', timeout: 30000 });
  await sleep(3000);

  const giftSection = await page.evaluate(() => document.body.innerText.includes('Cadeaux sur la carte'));
  assert('section cadeaux visible', giftSection);

  const placeBtn = await page.evaluateHandle(() =>
    [...document.querySelectorAll('button')].find((b) => b.textContent.includes('Placer un cadeau'))
  );
  const hasBtn = await placeBtn.evaluate((b) => !!b).catch(() => false);
  assert('bouton « Placer un cadeau » présent (premium)', !!hasBtn);
  if (hasBtn) {
    await placeBtn.asElement().click();
    await sleep(3000);
    const success = await page.evaluate(() => document.body.innerText.includes('Cadeau placé'));
    assert('message « Cadeau placé » après clic', success);
    await page.screenshot({ path: '/tmp/artisan-gifts-config.png' });
  }

  // --- 4. Vérif API --------------------------------------------------------
  const mine = await api('GET', '/objects/mine', null, { 'X-Artisan-Token': artisanToken });
  const gifts = mine.json.data || [];
  assert('cadeau visible via /objects/mine', gifts.length >= 1, JSON.stringify(gifts).slice(0, 150));

  // --- 5. Ramassage joueur -------------------------------------------------
  const playerEmail = `e2e-gift-player-${Date.now()}@prigent.tech`;
  await api('POST', '/users/register', { email: playerEmail, password: PASSWORD });
  const playerLogin = await api('POST', '/users/login', { email: playerEmail, password: PASSWORD });
  const playerToken = playerLogin.json.token;
  assert('register + login joueur', !!playerToken);

  const p2 = await browser.newPage();
  await p2.setViewport({ width: 800, height: 600 });
  const ctx = browser.defaultBrowserContext();
  await ctx.overridePermissions(BASE, ['geolocation']);
  await p2.setGeolocation({ latitude: SHOP.lat, longitude: SHOP.lng, accuracy: 5 });
  await p2.evaluateOnNewDocument((t) => {
    localStorage.setItem('spin_user_token', t);
  }, playerToken);
  await p2.goto(`${BASE}/carte`, { waitUntil: 'networkidle2', timeout: 30000 });
  await sleep(5000);

  const giftMarker = await p2.$('.object-marker--cadeau_artisan');
  assert('marker 🎁 sur la carte', !!giftMarker);

  await p2.waitForSelector('.checkin-fab--pickup', { timeout: 15000 }).catch(() => null);
  const fab = await p2.$('.checkin-fab--pickup');
  assert('FAB ramassage visible', !!fab);
  if (fab) {
    const fabText = await fab.evaluate((e) => e.textContent);
    await fab.click();
    const toastEl = await p2.waitForSelector('.toast', { timeout: 15000 }).catch(() => null);
    const toasts = toastEl
      ? await p2.$$eval('.toast', (els) => els.map((e) => e.textContent).join(' | ')).catch(() => '')
      : '';
    assert('toast +15 XP (cadeau, énergie gratuite)', /\+15 XP/.test(toasts), `${fabText.trim().slice(0, 60)} → ${toasts.slice(0, 100)}`);
  }
  await p2.screenshot({ path: '/tmp/artisan-gifts-map.png' });
  await browser.close();

  const failed = results.filter((r) => !r.ok);
  console.log(failed.length ? `\n${failed.length} test(s) en échec` : '\nTout passe ✅');
  process.exit(failed.length ? 1 : 0);
})();
