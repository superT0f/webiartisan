/* Repro : cookie user_token seul (comme l'app Flutter), puis reload → toujours connecté ? */
const puppeteer = require('puppeteer');

const BASE = 'http://localhost:5173';
const API = 'http://localhost:8080/api';

(async () => {
  // 1. Créer un joueur et récupérer son token via l'API
  const email = `e2e-reload-${Date.now()}@prigent.tech`;
  const reg = await fetch(`${API}/users/register`, {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password: 'Password123!' }),
  }).then((r) => r.json());
  const login = await fetch(`${API}/users/login`, {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password: 'Password123!' }),
  }).then((r) => r.json());
  const token = login.token;
  console.log('token obtenu:', !!token);

  const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();

  // 2. Poser le cookie user_token comme le fait WebViewCookieManager (session cookie, domaine courant)
  await page.goto(`${BASE}/carte`, { waitUntil: 'domcontentloaded' });
  await page.evaluate((t) => {
    document.cookie = `user_token=${encodeURIComponent(t)}; path=/; SameSite=Lax`;
  }, token);

  async function authState(label) {
    const state = await page.evaluate(async () => {
      const ls = localStorage.getItem('spin_user_token');
      const m = document.cookie.match(/(^| )user_token=([^;]+)/);
      return { localStorage: !!ls, cookie: m ? decodeURIComponent(m[2]).slice(0, 12) : null };
    });
    // état UI : le menu burger contient-il « Mon profil (Lv.… » (connecté) ou « Se connecter » ?
    await page.click('.nav-burger').catch(() => {});
    await new Promise((r) => setTimeout(r, 400));
    const menuText = await page.evaluate(() => document.querySelector('.nav-mobile')?.innerText || '');
    await page.click('.nav-burger').catch(() => {});
    const connected = menuText.includes('Mon profil');
    console.log(`${label}: localStorage=${state.localStorage} cookie=${state.cookie} UI-connecté=${connected}`);
    return connected;
  }

  await page.reload({ waitUntil: 'networkidle0' });
  await new Promise((r) => setTimeout(r, 1500));
  const after1 = await authState('après reload #1');

  await page.reload({ waitUntil: 'networkidle0' });
  await new Promise((r) => setTimeout(r, 1500));
  const after2 = await authState('après reload #2');

  console.log(after1 && after2 ? 'REPRO ÉCHOUÉE (reste connecté — bug pas côté web)' : 'BUG REPRODUIT côté web : déconnexion au reload');
  await browser.close();
})().catch((e) => { console.error('FATAL:', e.message); process.exit(1); });
