/* Repro prod : login superT0f + trace réseau complète sur /carte */
const puppeteer = require('puppeteer');

(async () => {
  const login = await fetch('https://api.prigent.tech/users/login', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email: 'superT0f@proton.me', password: 'totototo', rememberMe: true }),
  }).then((r) => r.json());
  console.log('login:', login.success ? 'OK (session créée)' : JSON.stringify(login));
  if (!login.token) return process.exit(1);

  const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();

  const counts = {};
  page.on('response', (res) => {
    const u = new URL(res.url());
    if (u.host.includes('api.prigent.tech')) {
      const key = `${res.status()} ${u.pathname}`;
      counts[key] = (counts[key] || 0) + 1;
    }
  });

  await page.goto('https://artisans-combs.prigent.tech/carte', { waitUntil: 'domcontentloaded' });
  await page.evaluate((t) => {
    document.cookie = `user_token=${encodeURIComponent(t)}; expires=${new Date(Date.now() + 365 * 864e5).toUTCString()}; path=/; SameSite=Lax; Secure`;
  }, login.token);
  await page.goto('https://artisans-combs.prigent.tech/carte', { waitUntil: 'networkidle0', timeout: 60000 });

  console.log('--- observation 60s ---');
  await new Promise((r) => setTimeout(r, 60000));

  const sorted = Object.entries(counts).sort((a, b) => b[1] - a[1]);
  for (const [k, v] of sorted) console.log(`${v}x ${k}`);
  await browser.close();
})().catch((e) => { console.error('FATAL:', e.message); process.exit(1); });
