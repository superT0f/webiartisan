/* Test e2e : images POI (upload owner → affichage carte)
 *
 * Scénario :
 *   1. Register artisan → /e2e/prepare-artisan (activation + géo + POI possédé)
 *   2. Upload d'une image PNG via l'API /pois/:id/image (route testée en PHP,
 *      ici on valide la chaîne d'affichage)
 *   3. /espace/quartier : vignette visible, POI listé
 *   4. /carte : marker photo (.poi-marker--photo) visible
 *
 * Run: node poi-image-test.cjs   (exit 0 si tout passe, 1 sinon)
 */
const puppeteer = require('puppeteer');
const fs = require('fs');

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
    headers: { ...headers },
    body: body === undefined ? undefined : body,
  });
  return { status: res.status, json: await res.json().catch(() => null) };
}

(async () => {
  // 1. Artisan + POI possédé
  const artisanEmail = `e2e-poi-${Date.now()}@prigent.tech`;
  const reg = await api('POST', '/artisans/register', JSON.stringify({
    company_name: 'Fleuriste E2E POI',
    city_slug: SHOP.slug,
    category_slug: 'boulangerie',
    email: artisanEmail,
    phone: '0600000000',
    password: PASSWORD,
  }), { 'Content-Type': 'application/json' });
  const artisanId = reg.json?.data?.id;
  assert('register artisan', !!artisanId, JSON.stringify(reg.json).slice(0, 120));
  if (!artisanId) process.exit(1);

  const prep = await api('POST', `/e2e/prepare-artisan/${artisanId}`, JSON.stringify({ lat: SHOP.lat, lng: SHOP.lng, make_poi_owner: true }), { 'Content-Type': 'application/json', 'X-E2E-Token': E2E_TOKEN });
  assert('prepare-artisan (POI possédé créé)', prep.json?.ok === true, JSON.stringify(prep.json).slice(0, 120));

  const login = await api('POST', '/artisans/login', JSON.stringify({ email: artisanEmail, password: PASSWORD, rememberMe: true }), { 'Content-Type': 'application/json' });
  const artisanToken = login.json?.token;
  assert('login artisan', !!artisanToken);
  if (!artisanToken) process.exit(1);

  // 2. Upload d'une image PNG (1×1) via l'API
  const mine = await api('GET', '/pois/my-claims', undefined, { 'X-Artisan-Token': artisanToken });
  const owned = mine.json?.data?.owned || [];
  assert('POI possédé visible', owned.length === 1, JSON.stringify(mine.json).slice(0, 150));
  const poi = owned[0];

  const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');
  const form = new FormData();
  form.append('image', new Blob([png], { type: 'image/png' }), 'test.png');
  const up = await fetch(`${API}/pois/${poi.id}/image`, {
    method: 'POST',
    headers: { 'X-Artisan-Token': artisanToken },
    body: form,
  });
  const upJson = await up.json();
  assert('upload image API', up.status === 200 && upJson?.data?.image_url, JSON.stringify(upJson).slice(0, 150));

  // 3. UI « Mon quartier » : vignette
  const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 900, height: 700 });
  await page.evaluateOnNewDocument((t) => {
    localStorage.setItem('artisan_token', t);
  }, artisanToken);
  await page.goto(`${BASE}/espace/quartier`, { waitUntil: 'networkidle2', timeout: 30000 });
  await sleep(3000);
  const thumb = await page.$('.poi-thumb');
  assert('vignette POI dans Mon quartier', !!thumb);
  await page.screenshot({ path: '/tmp/poi-image-quartier.png' });

  // 4. Carte : marker photo
  const p2 = await browser.newPage();
  await p2.setViewport({ width: 800, height: 600 });
  const ctx = browser.defaultBrowserContext();
  await ctx.overridePermissions(BASE, ['geolocation']);
  await p2.setGeolocation({ latitude: SHOP.lat, longitude: SHOP.lng, accuracy: 5 });
  await p2.goto(`${BASE}/carte`, { waitUntil: 'networkidle2', timeout: 30000 });
  await sleep(5000);
  const photoMarker = await p2.$('.poi-marker--photo');
  assert('marker photo sur la carte', !!photoMarker);
  await p2.screenshot({ path: '/tmp/poi-image-map.png' });

  await browser.close();
  const failed = results.filter((r) => !r.ok);
  console.log(failed.length ? `\n${failed.length} test(s) en échec` : '\nTout passe ✅');
  process.exit(failed.length ? 1 : 0);
})();
