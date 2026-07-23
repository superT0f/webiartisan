/* Test e2e : arène Big Brother (spawn → carte → arène → manche)
 *
 * Scénario :
 *   1. /e2e/spawn-boss : un Big Brother apparaît près du centre
 *   2. Joueur frais, géoloc sur le boss, /carte → marker 🎩 visible
 *   3. Tap marker → /arene?boss=<id>, HUD 3/3 ❤️
 *   4. « 📚 Quiz savoir-faire » → question → clic sur un choix → manche résolue
 *
 * Run: node boss-arena-test.cjs   (exit 0 si tout passe, 1 sinon)
 */
const puppeteer = require('puppeteer');

const BASE = 'http://localhost:5173';
const API = 'http://localhost:8080/api';
const E2E_TOKEN = 'local-e2e-token';
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
  return { status: res.status, json: await res.json().catch(() => null) };
}

(async () => {
  // 1. Spawn du boss
  const spawn = await api('POST', '/e2e/spawn-boss', { lat: 49.1081, lng: -0.7658 }, { 'X-E2E-Token': E2E_TOKEN });
  const bossId = spawn.json?.id;
  assert('spawn-boss', !!bossId, JSON.stringify(spawn.json).slice(0, 100));
  if (!bossId) process.exit(1);

  // 2. Joueur frais
  const email = `e2e-boss-${Date.now()}@prigent.tech`;
  await api('POST', '/users/register', { email, password: PASSWORD });
  const login = await api('POST', '/users/login', { email, password: PASSWORD });
  const token = login.json?.token;
  assert('register + login joueur', !!token);
  if (!token) process.exit(1);

  const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 800, height: 600 });
  const ctx = browser.defaultBrowserContext();
  await ctx.overridePermissions(BASE, ['geolocation']);
  await page.setGeolocation({ latitude: 49.1081, longitude: -0.7658, accuracy: 5 });
  await page.evaluateOnNewDocument((t) => {
    localStorage.setItem('spin_user_token', t);
  }, token);
  await page.goto(`${BASE}/carte`, { waitUntil: 'networkidle2', timeout: 30000 });
  await sleep(5000);

  // 3. Marker boss → tap → arène
  const bossMarker = await page.$('.object-marker--big_brother');
  assert('marker Big Brother visible', !!bossMarker);
  if (bossMarker) {
    await bossMarker.click();
    await sleep(2500);
    const url = page.url();
    assert('navigation vers /arene', url.includes('/arene'), url.slice(0, 80));

    await sleep(4000);
    const hearts = await page.$$('.hp-heart');
    assert('HUD 3+3 cœurs', hearts.length >= 6, `${hearts.length} cœurs`);
    const weapons = await page.evaluate(() => document.body.innerText.includes('Choisis ton arme'));
    assert('choix des 3 jeux affiché', weapons);

    // 4. Manche quiz
    const quizBtn = await page.evaluateHandle(() =>
      [...document.querySelectorAll('button')].find((b) => b.textContent.includes('Quiz savoir-faire'))
    );
    const hasQuizBtn = await quizBtn.evaluate((b) => !!b).catch(() => false);
    assert('bouton quiz présent', !!hasQuizBtn);
    if (hasQuizBtn) {
      await quizBtn.asElement().click();
      await sleep(2500);
      const question = await page.$('.quiz-question');
      assert('question affichée', !!question);
      const choices = await page.$$('.quiz-choice');
      assert('4 choix affichés', choices.length === 4, `${choices.length} choix`);
      if (choices.length > 0) {
        await choices[0].click();
        await sleep(2500);
        // Manche résolue : reveal visible (bonne ou mauvaise réponse) ou retour au choix d'arme
        const resolved = await page.evaluate(() =>
          !!document.querySelector('.quiz-choice--good, .quiz-choice--bad')
          || document.body.innerText.includes('Choisis ton arme')
        );
        assert('manche quiz résolue', resolved);
      }
    }
    await page.screenshot({ path: '/tmp/boss-arena.png' });
  }

  await browser.close();
  const failed = results.filter((r) => !r.ok);
  console.log(failed.length ? `\n${failed.length} test(s) en échec` : '\nTout passe ✅');
  process.exit(failed.length ? 1 : 0);
})();
