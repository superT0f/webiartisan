/* Test : check-in en tant qu'artisan SANS token consommateur → le pont doit éviter la popin */
const puppeteer = require('puppeteer');

const BASE = 'http://localhost:5173';
const API = 'http://localhost:8080/api';

(async () => {
  const login = await fetch(`${API}/artisans/login`, {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email: 'e2e-1784306761@prigent.tech', password: 'Password123!' }),
  }).then((r) => r.json());
  const artisanToken = login.token;
  console.log('artisan token OK:', !!artisanToken);

  const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 900 });
  const ctx = browser.defaultBrowserContext();
  await ctx.overridePermissions(BASE, ['geolocation']);
  await page.setGeolocation({ latitude: 49.1083, longitude: -0.7655, accuracy: 5 });

  await page.goto(`${BASE}/carte`, { waitUntil: 'domcontentloaded' });
  await page.evaluate((t) => {
    localStorage.setItem('artisan_token', t);
    localStorage.removeItem('spin_user_token');
  }, artisanToken);
  await page.goto(`${BASE}/carte`, { waitUntil: 'networkidle0', timeout: 60000 });
  await new Promise((r) => setTimeout(r, 3000));

  const adminVisible = await page.evaluate(() => document.body.innerText.includes('Halo 200 m'));
  console.log('admin visible (halo row):', adminVisible);

  // Le pont doit avoir créé un token consommateur au montage
  const bridged = await page.evaluate(() => {
    const ls = localStorage.getItem('spin_user_token');
    const m = document.cookie.match(/(^| )user_token=([^;]+)/);
    return !!(ls || m);
  });
  console.log('token consommateur ponté au mount:', bridged);

  // Cliquer le FAB check-in
  await page.waitForSelector('.checkin-fab, [class*="checkin"]', { timeout: 10000 }).catch(() => {});
  const fabInfo = await page.evaluate(() => {
    const btns = [...document.querySelectorAll('button')];
    const fab = btns.find((b) => b.textContent.includes('Check-in'));
    if (!fab) return null;
    fab.click();
    return fab.textContent.trim().slice(0, 60);
  });
  console.log('FAB cliqué:', fabInfo);
  await new Promise((r) => setTimeout(r, 3000));

  const authPopup = await page.evaluate(() => !!document.querySelector('.game-overlay input[type="email"]'));
  const toastXP = await page.evaluate(() => document.querySelector('.toast-container')?.innerText || '(pas de toast)');
  console.log('popin connexion affichée:', authPopup, '— attendu: false');
  console.log('toast:', toastXP);
  await page.screenshot({ path: '/tmp/checkin-bridge.png' });

  const ok = adminVisible && !authPopup;
  console.log(ok ? 'TEST OK : pas de popin, check-in passé (ou tenté sans popin)' : 'TEST KO');
  await browser.close();
  process.exit(ok ? 0 : 1);
})().catch((e) => { console.error('FATAL:', e.message); process.exit(1); });
