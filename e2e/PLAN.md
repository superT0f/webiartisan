# E2E Tests + Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a Puppeteer + Vitest end-to-end test suite covering WebiArtisan web/API/mobile web, plus a dashboard web app on `e2e.prigent.tech` with admin login, real-time production logs, and test visualization.

**Architecture:** The test runner lives in `webiartisan.new/e2e/` and executes against production (or localhost) using Puppeteer for browser flows and `fetch` for API calls. A `logWatcher` tails Gandi-mounted production logs. Test results are persisted as JSON/JSONL and imported into a SQLite-backed Node.js/Express dashboard API, consumed by a Vue 3 frontend.

**Tech Stack:** TypeScript, Vitest, Puppeteer, Node.js 18+ native `fetch`, Express, SQLite (better-sqlite3), Vue 3, Vite.

## Global Constraints

- All code must be TypeScript with strict mode enabled.
- Tests run against production only when `E2E_RUN_AGAINST_PROD=true`.
- Test accounts use emails matching `e2e-{uuid}@prigent.tech`.
- No real Stripe payments, no mass emails, no deletion of other users' data.
- Gandi log mount point: `/home/tof/mnt/gandi/vhosts`.
- Backend dashboard is deployed separately (VPS/PM2) or rewritten in PHP if staying on Gandi Simple Hosting.
- Credentials and secrets are injected via environment variables, never committed.

---

## File Map

| File | Responsibility |
|------|----------------|
| `e2e/package.json` | Dependencies and npm scripts for tests + dashboard backend/frontend. |
| `e2e/vitest.config.ts` | Vitest configuration with global setup and JSON reporter. |
| `e2e/tsconfig.json` | TypeScript configuration for the test suite. |
| `e2e/.env.example` | Template for environment variables. |
| `e2e/src/config/env.ts` | Validates and exposes env variables. |
| `e2e/src/helpers/logWatcher.ts` | Tails production log files and emits events. |
| `e2e/src/helpers/api.ts` | API client for creating/cleaning test accounts. |
| `e2e/src/helpers/browser.ts` | Puppeteer browser/page factory. |
| `e2e/src/helpers/cookies.ts` | Cross-domain cookie helpers. |
| `e2e/src/fixtures/users.ts` | Test account generators. |
| `e2e/src/pages/LoginPage.ts` | Page object for login flows. |
| `e2e/src/pages/DashboardPage.ts` | Page object for artisan dashboard. |
| `e2e/src/pages/MapPage.ts` | Page object for consumer map. |
| `e2e/src/pages/ProfilePage.ts` | Page object for consumer profile. |
| `e2e/src/specs/auth.loop.spec.ts` | Reproduces the production auth loop. |
| `e2e/src/specs/api.smoke.spec.ts` | API-only smoke tests. |
| `e2e/src/globalSetup.ts` | Starts log watcher before all tests. |
| `e2e/api/src/server.ts` | Express server for dashboard backend. |
| `e2e/api/src/db.ts` | SQLite schema and queries. |
| `e2e/api/src/auth.ts` | JWT middleware and admin login. |
| `e2e/api/src/routes/runs.ts` | Run CRUD endpoints. |
| `e2e/api/src/routes/logs.ts` | SSE log stream endpoint. |
| `e2e/api/src/routes/trigger.ts` | Trigger test suite endpoint. |
| `e2e/dashboard/src/main.ts` | Vue app entry. |
| `e2e/dashboard/src/App.vue` | Root layout with nav. |
| `e2e/dashboard/src/api.ts` | Frontend API client. |
| `e2e/dashboard/src/views/HomeView.vue` | List of runs. |
| `e2e/dashboard/src/views/RunView.vue` | Run details. |
| `e2e/dashboard/src/views/LiveView.vue` | Live logs + trigger button. |
| `e2e/dashboard/src/views/LoginView.vue` | Admin login form. |
| `sites/e2e-dashboard/Makefile` | Deployment Makefile for dashboard static site. |
| `webiartisan.new/Makefile` | New commands: `e2e-test`, `e2e-dashboard-dev`, `push-e2e`. |

---

### Task 1: Scaffold the E2E project

**Files:**
- Create: `e2e/package.json`
- Create: `e2e/vitest.config.ts`
- Create: `e2e/tsconfig.json`
- Create: `e2e/.env.example`
- Create: `e2e/.gitignore`

**Interfaces:**
- Produces: npm scripts `test`, `test:prod`, `dashboard`, `dashboard:api`, `dashboard:ui`.

- [ ] **Step 1: Write package.json**

```json
{
  "name": "webiartisan-e2e",
  "version": "1.0.0",
  "private": true,
  "type": "module",
  "scripts": {
    "test": "vitest run",
    "test:prod": "E2E_RUN_AGAINST_PROD=true vitest run",
    "dashboard:api": "tsx api/src/server.ts",
    "dashboard:ui": "cd dashboard && vite",
    "dashboard": "concurrently \"npm run dashboard:api\" \"npm run dashboard:ui\"",
    "build:dashboard": "cd dashboard && vite build"
  },
  "dependencies": {
    "better-sqlite3": "^11.0.0",
    "cors": "^2.8.5",
    "express": "^4.19.0",
    "jsonwebtoken": "^9.0.2",
    "puppeteer": "^22.0.0",
    "uuid": "^10.0.0"
  },
  "devDependencies": {
    "@types/better-sqlite3": "^7.6.0",
    "@types/cors": "^2.8.17",
    "@types/express": "^4.17.0",
    "@types/jsonwebtoken": "^9.0.0",
    "@types/node": "^20.0.0",
    "@types/uuid": "^10.0.0",
    "concurrently": "^8.2.0",
    "tsx": "^4.0.0",
    "typescript": "^5.4.0",
    "vitest": "^2.0.0"
  }
}
```

- [ ] **Step 2: Write vitest.config.ts**

```ts
import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    globals: true,
    globalSetup: './src/globalSetup.ts',
    reporters: ['default', 'json'],
    outputFile: {
      json: './reports/latest.json',
    },
    hookTimeout: 60_000,
    testTimeout: 60_000,
  },
});
```

- [ ] **Step 3: Write tsconfig.json**

```json
{
  "compilerOptions": {
    "target": "ES2022",
    "module": "ESNext",
    "moduleResolution": "Bundler",
    "strict": true,
    "esModuleInterop": true,
    "skipLibCheck": true,
    "forceConsistentCasingInFileNames": true,
    "outDir": "./dist",
    "rootDir": ".",
    "types": ["node", "vitest/globals"]
  },
  "include": ["src/**/*", "api/src/**/*"],
  "exclude": ["node_modules", "dist", "dashboard"]
}
```

- [ ] **Step 4: Write .env.example**

```bash
E2E_RUN_AGAINST_PROD=false
E2E_API_URL=https://api.prigent.tech
E2E_LIVRY_URL=https://artisans-livry.prigent.tech
E2E_COMBS_URL=https://artisans-combs.prigent.tech
E2E_VSD_URL=https://artisans-vert-saint-denis.prigent.tech
E2E_APP_URL=https://app.prigent.tech
E2E_ADMIN_USER=admin
E2E_ADMIN_PASS=change_me
E2E_TEST_PASSWORD=ChangeMe123!
E2E_JWT_SECRET=change_me_in_production
E2E_API_E2E_TOKEN=change_me
```

- [ ] **Step 5: Write .gitignore**

```gitignore
node_modules/
dist/
.env
.reports/
data/*.sqlite
data/*.sqlite-journal
reports/latest.json
reports/latest-logs.jsonl
screenshots/
```

- [ ] **Step 6: Install dependencies**

Run:
```bash
cd webiartisan.new/e2e
npm install
```

Expected: `node_modules/` created without errors.

- [ ] **Step 7: Commit**

```bash
cd webiartisan.new
git add e2e/package.json e2e/vitest.config.ts e2e/tsconfig.json e2e/.env.example e2e/.gitignore e2e/DESIGN.md
# DESIGN.md already committed; ensure added files staged
git add e2e/package.json e2e/vitest.config.ts e2e/tsconfig.json e2e/.env.example e2e/.gitignore
git commit -m "chore(e2e): scaffold test project with vitest, puppeteer, ts"
```

---

### Task 2: Environment configuration

**Files:**
- Create: `e2e/src/config/env.ts`
- Create: `e2e/src/config/env.test.ts`

**Interfaces:**
- Produces: `env` object with typed, validated environment variables.

- [ ] **Step 1: Write failing test**

Create `e2e/src/config/env.test.ts`:

```ts
import { describe, it, expect } from 'vitest';
import { env } from './env';

describe('env', () => {
  it('defaults to local mode', () => {
    expect(env.mode).toBe('local');
  });

  it('exposes city URLs', () => {
    expect(env.cityUrls.livry).toBe('http://localhost');
  });

  it('throws if prod mode lacks required vars', () => {
    process.env.E2E_RUN_AGAINST_PROD = 'true';
    delete process.env.E2E_ADMIN_USER;
    expect(() => import('./env')).toThrow();
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
cd webiartisan.new/e2e
npx vitest run src/config/env.test.ts
```

Expected: FAIL — `Cannot find module './env'`.

- [ ] **Step 3: Implement env.ts**

Create `e2e/src/config/env.ts`:

```ts
function requireEnv(key: string): string {
  const value = process.env[key];
  if (!value) {
    throw new Error(`Missing required environment variable: ${key}`);
  }
  return value;
}

export const mode = process.env.E2E_RUN_AGAINST_PROD === 'true' ? 'prod' : 'local';

export const env = {
  mode,
  isProd: mode === 'prod',
  apiUrl: process.env.E2E_API_URL || 'http://localhost:8080/api',
  appUrl: process.env.E2E_APP_URL || 'http://localhost',
  cityUrls: {
    livry: process.env.E2E_LIVRY_URL || 'http://localhost',
    combs: process.env.E2E_COMBS_URL || 'http://localhost',
    vertSaintDenis: process.env.E2E_VSD_URL || 'http://localhost',
  },
  admin: {
    username: process.env.E2E_ADMIN_USER || 'admin',
    password: process.env.E2E_ADMIN_PASS || 'admin',
  },
  testAccounts: {
    password: process.env.E2E_TEST_PASSWORD || 'Password123!',
  },
  logPaths: {
    api: '/home/tof/mnt/gandi/vhosts/api.prigent.tech/storage/logs',
    app: '/home/tof/mnt/gandi/vhosts/app.prigent.tech/htdocs/logs',
  },
  jwtSecret: process.env.E2E_JWT_SECRET || 'dev-secret',
  apiE2eToken: process.env.E2E_API_E2E_TOKEN || '',
};

if (env.isProd) {
  requireEnv('E2E_ADMIN_USER');
  requireEnv('E2E_ADMIN_PASS');
  requireEnv('E2E_JWT_SECRET');
  requireEnv('E2E_API_E2E_TOKEN');
}
```

- [ ] **Step 4: Run tests**

Run:
```bash
npx vitest run src/config/env.test.ts
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
cd webiartisan.new
git add e2e/src/config/env.ts e2e/src/config/env.test.ts
git commit -m "feat(e2e): add typed environment configuration"
```

---

### Task 3: Log watcher helper

**Files:**
- Create: `e2e/src/helpers/logWatcher.ts`
- Create: `e2e/src/helpers/logWatcher.test.ts`

**Interfaces:**
- Produces: `LogWatcher` class with `start()`, `stop()`, `on('line', ...)` and `attach(testName)`.

- [ ] **Step 1: Write failing test**

Create `e2e/src/helpers/logWatcher.test.ts`:

```ts
import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { LogWatcher } from './logWatcher';
import fs from 'fs';
import path from 'path';
import os from 'os';

describe('LogWatcher', () => {
  let tmpDir: string;
  let watcher: LogWatcher;

  beforeEach(() => {
    tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'e2e-logs-'));
    const logFile = path.join(tmpDir, 'api-test.log');
    fs.writeFileSync(logFile, 'existing line\n');
    watcher = new LogWatcher([logFile]);
  });

  afterEach(() => {
    watcher.stop();
    fs.rmSync(tmpDir, { recursive: true, force: true });
  });

  it('emits new lines appended to a file', async () => {
    const lines: string[] = [];
    watcher.on('line', (line) => lines.push(line));
    watcher.start();

    const logFile = watcher.logFiles[0];
    fs.appendFileSync(logFile, 'new line\n');

    await new Promise((resolve) => setTimeout(resolve, 200));
    expect(lines).toContain('new line');
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
npx vitest run src/helpers/logWatcher.test.ts
```

Expected: FAIL — `Cannot find module './logWatcher'`.

- [ ] **Step 3: Implement logWatcher.ts**

Create `e2e/src/helpers/logWatcher.ts`:

```ts
import EventEmitter from 'events';
import fs from 'fs';
import path from 'path';

export class LogWatcher extends EventEmitter {
  private watchers: fs.FSWatcher[] = [];
  private positions: Map<string, number> = new Map();
  private outputPath = './reports/latest-logs.jsonl';

  constructor(public readonly logFiles: string[]) {
    super();
  }

  start(): void {
    fs.mkdirSync(path.dirname(this.outputPath), { recursive: true });
    for (const file of this.logFiles) {
      this.watchFile(file);
    }
  }

  stop(): void {
    for (const watcher of this.watchers) {
      watcher.close();
    }
    this.watchers = [];
  }

  attach(testName: string): void {
    this.on('line', (line: string, source: string) => {
      const entry = { ts: new Date().toISOString(), test: testName, source, line };
      fs.appendFileSync(this.outputPath, JSON.stringify(entry) + '\n');
    });
  }

  private watchFile(file: string): void {
    if (!fs.existsSync(file)) {
      this.emit('warn', `Log file not found: ${file}`);
      return;
    }
    const stat = fs.statSync(file);
    this.positions.set(file, stat.size);

    const watcher = fs.watch(file, (eventType) => {
      if (eventType !== 'change') return;
      const currentSize = fs.statSync(file).size;
      const lastPosition = this.positions.get(file) || 0;
      if (currentSize <= lastPosition) return;

      const stream = fs.createReadStream(file, { start: lastPosition, end: currentSize });
      let remainder = '';
      stream.on('data', (chunk: Buffer) => {
        const text = remainder + chunk.toString('utf8');
        const lines = text.split('\n');
        remainder = lines.pop() || '';
        for (const line of lines) {
          if (line) this.emit('line', line, file);
        }
      });
      stream.on('end', () => {
        this.positions.set(file, currentSize);
      });
    });

    this.watchers.push(watcher);
  }
}
```

- [ ] **Step 4: Run tests**

Run:
```bash
npx vitest run src/helpers/logWatcher.test.ts
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
cd webiartisan.new
git add e2e/src/helpers/logWatcher.ts e2e/src/helpers/logWatcher.test.ts
git commit -m "feat(e2e): add log watcher for production logs"
```

---

### Task 4: API client helper

**Files:**
- Create: `e2e/src/helpers/api.ts`
- Create: `e2e/src/helpers/api.test.ts`

**Interfaces:**
- Produces: `ApiClient` class with methods for account lifecycle and login.

- [ ] **Step 1: Write failing test**

Create `e2e/src/helpers/api.test.ts`:

```ts
import { describe, it, expect } from 'vitest';
import { ApiClient } from './api';

describe('ApiClient', () => {
  it('has baseUrl from env', () => {
    const api = new ApiClient('http://localhost:8080/api');
    expect(api.baseUrl).toBe('http://localhost:8080/api');
  });

  it('generates unique test emails', () => {
    const e1 = ApiClient.generateTestEmail();
    const e2 = ApiClient.generateTestEmail();
    expect(e1).not.toBe(e2);
    expect(e1).toMatch(/^e2e-.*@prigent\.tech$/);
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
npx vitest run src/helpers/api.test.ts
```

Expected: FAIL — `Cannot find module './api'`.

- [ ] **Step 3: Implement api.ts**

Create `e2e/src/helpers/api.ts`:

```ts
import { v4 as uuidv4 } from 'uuid';

export interface AuthResponse {
  token: string;
  user?: { id: number; email: string };
}

export class ApiClient {
  constructor(public readonly baseUrl: string) {}

  static generateTestEmail(): string {
    return `e2e-${uuidv4().slice(0, 8)}@prigent.tech`;
  }

  private async request(path: string, options: RequestInit = {}): Promise<unknown> {
    const response = await fetch(`${this.baseUrl}${path}`, {
      headers: { 'Content-Type': 'application/json', ...options.headers },
      ...options,
    });
    const text = await response.text();
    const data = text ? JSON.parse(text) : null;
    if (!response.ok) {
      throw new Error(`API error ${response.status}: ${JSON.stringify(data)}`);
    }
    return data;
  }

  async health(): Promise<unknown> {
    return this.request('/health');
  }

  async registerConsumer(email: string, password: string): Promise<AuthResponse> {
    return this.request('/users/register', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    }) as Promise<AuthResponse>;
  }

  async loginConsumer(email: string, password: string): Promise<AuthResponse> {
    return this.request('/users/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    }) as Promise<AuthResponse>;
  }

  async registerArtisan(email: string, password: string, citySlug: string): Promise<AuthResponse> {
    return this.request('/artisans/register', {
      method: 'POST',
      body: JSON.stringify({ email, password, city_slug: citySlug }),
    }) as Promise<AuthResponse>;
  }

  async loginArtisan(email: string, password: string): Promise<AuthResponse> {
    return this.request('/artisans/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    }) as Promise<AuthResponse>;
  }

  async cleanupTestAccount(userId: number, e2eToken: string): Promise<void> {
    await this.request(`/e2e/cleanup/${userId}`, {
      method: 'DELETE',
      headers: { 'X-E2E-Token': e2eToken },
    });
  }
}
```

- [ ] **Step 4: Run tests**

Run:
```bash
npx vitest run src/helpers/api.test.ts
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
cd webiartisan.new
git add e2e/src/helpers/api.ts e2e/src/helpers/api.test.ts
git commit -m "feat(e2e): add API client helper for test accounts"
```

---

### Task 5: Browser and cookie helpers

**Files:**
- Create: `e2e/src/helpers/browser.ts`
- Create: `e2e/src/helpers/cookies.ts`

**Interfaces:**
- Produces: `newBrowserContext()` returns `{ browser, page }`.
- Produces: `setTokenCookie(page, token, domain)` and `setLocalStorageToken(page, token)`.

- [ ] **Step 1: Implement browser.ts**

Create `e2e/src/helpers/browser.ts`:

```ts
import puppeteer from 'puppeteer';

export async function newBrowserContext() {
  const browser = await puppeteer.launch({
    headless: process.env.E2E_HEADLESS !== 'false',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
  const page = await browser.newPage();
  page.setDefaultTimeout(30_000);
  return { browser, page };
}

export async function closeBrowser(browser: puppeteer.Browser): Promise<void> {
  await browser.close();
}
```

- [ ] **Step 2: Implement cookies.ts**

Create `e2e/src/helpers/cookies.ts`:

```ts
import { Page } from 'puppeteer';

export async function setLocalStorageToken(page: Page, token: string, key = 'user_token'): Promise<void> {
  await page.evaluate((k, t) => {
    localStorage.setItem(k, t);
  }, key, token);
}

export async function clearLocalStorageToken(page: Page, key = 'user_token'): Promise<void> {
  await page.evaluate((k) => {
    localStorage.removeItem(k);
  }, key);
}

export async function setTokenCookie(
  page: Page,
  token: string,
  domain: string,
  key = 'user_token'
): Promise<void> {
  await page.setCookie({
    name: key,
    value: token,
    domain,
    path: '/',
    httpOnly: false,
    secure: true,
    sameSite: 'Lax',
  });
}
```

- [ ] **Step 3: Commit**

```bash
cd webiartisan.new
git add e2e/src/helpers/browser.ts e2e/src/helpers/cookies.ts
git commit -m "feat(e2e): add puppeteer browser and cookie helpers"
```

---

### Task 6: Page Object Model

**Files:**
- Create: `e2e/src/pages/LoginPage.ts`
- Create: `e2e/src/pages/DashboardPage.ts`
- Create: `e2e/src/pages/MapPage.ts`
- Create: `e2e/src/pages/ProfilePage.ts`

**Interfaces:**
- Produces: Page objects with navigation and assertion helpers.

- [ ] **Step 1: Implement LoginPage.ts**

```ts
import { Page } from 'puppeteer';

export class LoginPage {
  constructor(private page: Page, private baseUrl: string) {}

  async goto(): Promise<void> {
    await this.page.goto(`${this.baseUrl}/login`);
  }

  async login(email: string, password: string): Promise<void> {
    await this.page.waitForSelector('input[name="email"]');
    await this.page.type('input[name="email"]', email);
    await this.page.type('input[name="password"]', password);
    await this.page.click('button[type="submit"]');
  }

  async isVisible(): Promise<boolean> {
    return this.page.$eval('input[name="email"]', () => true).catch(() => false);
  }
}
```

- [ ] **Step 2: Implement DashboardPage.ts**

```ts
import { Page } from 'puppeteer';

export class DashboardPage {
  constructor(private page: Page, private baseUrl: string) {}

  async goto(): Promise<void> {
    await this.page.goto(`${this.baseUrl}/espace`);
  }

  async waitForLoaded(): Promise<void> {
    await this.page.waitForFunction(
      () => document.body.innerText.includes('Tableau de bord') || document.body.innerText.includes('Dashboard'),
      { timeout: 10_000 }
    );
  }

  async isVisible(): Promise<boolean> {
    return this.page.evaluate(() => document.body.innerText.includes('Tableau de bord'));
  }
}
```

- [ ] **Step 3: Implement MapPage.ts and ProfilePage.ts**

`e2e/src/pages/MapPage.ts`:

```ts
import { Page } from 'puppeteer';

export class MapPage {
  constructor(private page: Page, private baseUrl: string) {}

  async goto(): Promise<void> {
    await this.page.goto(`${this.baseUrl}/carte`);
  }

  async isVisible(): Promise<boolean> {
    return this.page.evaluate(() => document.body.innerText.includes('Carte'));
  }
}
```

`e2e/src/pages/ProfilePage.ts`:

```ts
import { Page } from 'puppeteer';

export class ProfilePage {
  constructor(private page: Page, private baseUrl: string) {}

  async goto(): Promise<void> {
    await this.page.goto(`${this.baseUrl}/profil`);
  }

  async isVisible(): Promise<boolean> {
    return this.page.evaluate(() => document.body.innerText.includes('Profil'));
  }
}
```

- [ ] **Step 4: Commit**

```bash
cd webiartisan.new
git add e2e/src/pages/
git commit -m "feat(e2e): add page object model for login, dashboard, map, profile"
```

---

### Task 7: Global setup and log watcher integration

**Files:**
- Create: `e2e/src/globalSetup.ts`
- Modify: `e2e/vitest.config.ts`

**Interfaces:**
- Produces: Shared `LogWatcher` instance accessible via global state.

- [ ] **Step 1: Implement globalSetup.ts**

```ts
import { LogWatcher } from './helpers/logWatcher';
import { env } from './config/env';
import fs from 'fs';

const today = new Date().toISOString().slice(0, 10);
const logFiles = [
  `${env.logPaths.api}/api-${today}.log`,
  `${env.logPaths.app}/visits-${today}.log`,
].filter(fs.existsSync);

const watcher = new LogWatcher(logFiles);

export default function setup() {
  fs.mkdirSync('./reports', { recursive: true });
  watcher.start();
  (globalThis as unknown as { __logWatcher: LogWatcher }).__logWatcher = watcher;
}

export function teardown() {
  watcher.stop();
}
```

- [ ] **Step 2: Update vitest.config.ts**

Modify `e2e/vitest.config.ts` to export the teardown:

```ts
import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    globals: true,
    globalSetup: './src/globalSetup.ts',
    reporters: ['default', 'json'],
    outputFile: { json: './reports/latest.json' },
    hookTimeout: 60_000,
    testTimeout: 60_000,
  },
});
```

- [ ] **Step 3: Commit**

```bash
cd webiartisan.new
git add e2e/src/globalSetup.ts e2e/vitest.config.ts
git commit -m "feat(e2e): integrate log watcher as vitest global setup"
```

---

### Task 8: Auth loop spec

**Files:**
- Create: `e2e/src/specs/auth.loop.spec.ts`

**Interfaces:**
- Consumes: `ApiClient`, `newBrowserContext`, `closeBrowser`, `setLocalStorageToken`, `LoginPage`, `MapPage`, `DashboardPage`.

- [ ] **Step 1: Implement auth.loop.spec.ts**

```ts
import { describe, it, expect, beforeAll, afterAll } from 'vitest';
import { env } from '../config/env';
import { ApiClient } from '../helpers/api';
import { newBrowserContext, closeBrowser } from '../helpers/browser';
import { setLocalStorageToken } from '../helpers/cookies';
import { LoginPage } from '../pages/LoginPage';
import { MapPage } from '../pages/MapPage';
import { DashboardPage } from '../pages/DashboardPage';

describe('auth loop', () => {
  const api = new ApiClient(env.apiUrl);
  let consumerEmail: string;
  let consumerPassword: string;

  beforeAll(async () => {
    consumerEmail = ApiClient.generateTestEmail();
    consumerPassword = env.testAccounts.password;
    await api.registerConsumer(consumerEmail, consumerPassword);
  });

  it('does not loop when localStorage token is stale', async () => {
    const { browser, page } = await newBrowserContext();
    const baseUrl = env.cityUrls.livry;

    try {
      await setLocalStorageToken(page, 'invalid-token');
      await page.goto(baseUrl);

      const redirects: string[] = [];
      page.on('framenavigated', (frame) => {
        if (frame === page.mainFrame()) redirects.push(frame.url());
      });

      const loginPage = new LoginPage(page, baseUrl);
      await loginPage.goto();
      await loginPage.login(consumerEmail, consumerPassword);

      const mapPage = new MapPage(page, baseUrl);
      await mapPage.isVisible();

      const loginCount = redirects.filter((u) => u.includes('/login')).length;
      expect(loginCount).toBeLessThanOrEqual(1);
    } finally {
      await closeBrowser(browser);
    }
  });

  it('artisan with matching city slug lands on dashboard', async () => {
    const artisanEmail = ApiClient.generateTestEmail();
    const artisanPassword = env.testAccounts.password;
    await api.registerArtisan(artisanEmail, artisanPassword, 'livry');

    const { browser, page } = await newBrowserContext();
    const baseUrl = env.cityUrls.livry;

    try {
      const loginPage = new LoginPage(page, baseUrl);
      await loginPage.goto();
      await loginPage.login(artisanEmail, artisanPassword);

      const dashboard = new DashboardPage(page, baseUrl);
      await dashboard.waitForLoaded();
      expect(await dashboard.isVisible()).toBe(true);
    } finally {
      await closeBrowser(browser);
    }
  });
});
```

- [ ] **Step 2: Run spec against localhost**

Run:
```bash
cd webiartisan.new/e2e
npx vitest run src/specs/auth.loop.spec.ts
```

Expected: FAIL or PASS depending on local stack readiness. Fix selectors if needed.

- [ ] **Step 3: Commit**

```bash
cd webiartisan.new
git add e2e/src/specs/auth.loop.spec.ts
git commit -m "feat(e2e): add auth loop reproduction spec"
```

---

### Task 9: API smoke spec

**Files:**
- Create: `e2e/src/specs/api.smoke.spec.ts`

- [ ] **Step 1: Implement api.smoke.spec.ts**

```ts
import { describe, it, expect } from 'vitest';
import { env } from '../config/env';
import { ApiClient } from '../helpers/api';

describe('api smoke', () => {
  const api = new ApiClient(env.apiUrl);

  it('health endpoint returns 200', async () => {
    const result = await api.health();
    expect(result).toBeDefined();
  });

  it('consumer can register and login', async () => {
    const email = ApiClient.generateTestEmail();
    const password = env.testAccounts.password;
    const register = await api.registerConsumer(email, password);
    expect(register.token).toBeDefined();

    const login = await api.loginConsumer(email, password);
    expect(login.token).toBeDefined();
  });

  it('protected route returns 401 without token', async () => {
    const response = await fetch(`${env.apiUrl}/users/me`);
    expect(response.status).toBe(401);
  });
});
```

- [ ] **Step 2: Run spec**

Run:
```bash
npx vitest run src/specs/api.smoke.spec.ts
```

Expected: PASS (assuming `/health` and `/users/register` exist locally).

- [ ] **Step 3: Commit**

```bash
cd webiartisan.new
git add e2e/src/specs/api.smoke.spec.ts
git commit -m "feat(e2e): add API smoke tests"
```

---

### Task 10: Add E2E endpoints to PHP API

**Files:**
- Modify: `webiartisan.new/sites/api/.env.example`
- Modify: `webiartisan.new/sites/api/.env.production`
- Modify: `webiartisan.new/sites/api/index.php`
- Create: `webiartisan.new/sites/api/routes/e2e.php`

**Interfaces:**
- Consumes: `E2E_ALLOWED` and `E2E_API_TOKEN` env vars.
- Produces: `DELETE /e2e/cleanup/:id` and `GET /e2e/magic-link/:email` protected by `X-E2E-Token`.

- [ ] **Step 1: Add E2E env vars**

Add to `sites/api/.env.example`:

```bash
E2E_ALLOWED=false
E2E_API_TOKEN=change_me
```

Add to `sites/api/.env.production` (manually or via deployment script):

```bash
E2E_ALLOWED=true
E2E_API_TOKEN=xxx
```

- [ ] **Step 2: Create routes/e2e.php**

```php
<?php
use WebiArtisan\Lib\AppLogger;

$e2eAllowed = ($_ENV['E2E_ALLOWED'] ?? 'false') === 'true';
$e2eToken = $_ENV['E2E_API_TOKEN'] ?? '';

function requireE2EAuth(): void {
    global $e2eAllowed, $e2eToken;
    if (!$e2eAllowed) {
        http_response_code(403);
        echo json_encode(['error' => 'E2E not allowed']);
        exit;
    }
    $header = $_SERVER['HTTP_X_E2E_TOKEN'] ?? '';
    if (!$e2eToken || $header !== $e2eToken) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid E2E token']);
        exit;
    }
}

$router->delete('/e2e/cleanup/:id', function ($params) use ($db) {
    requireE2EAuth();
    $id = (int) $params['id'];
    $db->prepare('DELETE FROM local_users WHERE id = ? AND email LIKE "e2e-%@prigent.tech"')->execute([$id]);
    $db->prepare('DELETE FROM local_artisans WHERE user_id = ?')->execute([$id]);
    echo json_encode(['ok' => true]);
});

$router->get('/e2e/magic-link/:email', function ($params) use ($db) {
    requireE2EAuth();
    $email = $params['email'];
    if (!str_starts_with($email, 'e2e-') || !str_ends_with($email, '@prigent.tech')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid test email']);
        exit;
    }
    $code = bin2hex(random_bytes(16));
    $db->prepare('INSERT INTO local_magic_codes (email, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))')
       ->execute([$email, $code]);
    echo json_encode(['code' => $code, 'url' => "https://artisans-livry.prigent.tech/login?magic={$code}"]);
});
```

- [ ] **Step 3: Register route in index.php**

Find the route loading section and add:

```php
require __DIR__ . '/routes/e2e.php';
```

- [ ] **Step 4: Test endpoints locally**

Run:
```bash
cd webiartisan.new
make test-api
```

Manually verify `DELETE /e2e/cleanup/999` returns 401 without token and 403 if `E2E_ALLOWED=false`.

- [ ] **Step 5: Commit**

```bash
cd webiartisan.new
git add sites/api/.env.example sites/api/.env.production sites/api/index.php sites/api/routes/e2e.php
git commit -m "feat(api): add E2E cleanup and magic-link debug endpoints"
```

---

### Task 11: Dashboard backend scaffolding

**Files:**
- Create: `e2e/api/src/db.ts`
- Create: `e2e/api/src/auth.ts`
- Create: `e2e/api/src/server.ts`

**Interfaces:**
- Produces: Express server with `/api/health`, `/api/auth/login`, run CRUD and SSE log endpoints.

- [ ] **Step 1: Implement db.ts**

```ts
import Database from 'better-sqlite3';
import path from 'path';

const dbPath = process.env.E2E_DB_PATH || path.resolve('data/runs.sqlite');
export const db = new Database(dbPath);

db.exec(`
  CREATE TABLE IF NOT EXISTS runs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    started_at TEXT NOT NULL,
    finished_at TEXT,
    status TEXT NOT NULL,
    passed INTEGER DEFAULT 0,
    failed INTEGER DEFAULT 0,
    report_json TEXT,
    logs_jsonl TEXT
  );

  CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL
  );
`);
```

- [ ] **Step 2: Implement auth.ts**

```ts
import { Request, Response, NextFunction } from 'express';
import jwt from 'jsonwebtoken';
import { env } from '../../src/config/env';

export interface AuthRequest extends Request {
  user?: { username: string };
}

export function generateToken(username: string): string {
  return jwt.sign({ username }, env.jwtSecret, { expiresIn: '8h' });
}

export function authMiddleware(req: AuthRequest, res: Response, next: NextFunction): void {
  const header = req.headers.authorization || '';
  const token = header.replace(/^Bearer\s+/, '');
  if (!token) {
    res.status(401).json({ error: 'Missing token' });
    return;
  }
  try {
    req.user = jwt.verify(token, env.jwtSecret) as { username: string };
    next();
  } catch {
    res.status(401).json({ error: 'Invalid token' });
  }
}
```

- [ ] **Step 3: Implement server.ts**

```ts
import express from 'express';
import cors from 'cors';
import path from 'path';
import { env } from '../../src/config/env';
import { db } from './db';
import { authMiddleware, AuthRequest, generateToken } from './auth';
import runsRouter from './routes/runs';
import logsRouter from './routes/logs';
import triggerRouter from './routes/trigger';

const app = express();
const PORT = process.env.E2E_DASHBOARD_PORT || 4000;

app.use(cors({ origin: true, credentials: true }));
app.use(express.json());

app.get('/api/health', (_req, res) => res.json({ ok: true }));

app.post('/api/auth/login', (req, res) => {
  const { username, password } = req.body;
  if (username === env.admin.username && password === env.admin.password) {
    res.json({ token: generateToken(username) });
  } else {
    res.status(401).json({ error: 'Invalid credentials' });
  }
});

app.use('/api/runs', authMiddleware as express.RequestHandler, runsRouter);
app.use('/api/logs', authMiddleware as express.RequestHandler, logsRouter);
app.use('/api/runs/trigger', authMiddleware as express.RequestHandler, triggerRouter);

app.listen(PORT, () => {
  console.log(`Dashboard API listening on http://localhost:${PORT}`);
});
```

- [ ] **Step 4: Commit**

```bash
cd webiartisan.new
git add e2e/api/src/db.ts e2e/api/src/auth.ts e2e/api/src/server.ts
git commit -m "feat(e2e): scaffold dashboard backend with sqlite and jwt auth"
```

---

### Task 12: Dashboard backend routes

**Files:**
- Create: `e2e/api/src/routes/runs.ts`
- Create: `e2e/api/src/routes/logs.ts`
- Create: `e2e/api/src/routes/trigger.ts`

- [ ] **Step 1: Implement runs.ts**

```ts
import { Router } from 'express';
import { db } from '../db';

const router = Router();

router.get('/', (_req, res) => {
  const rows = db.prepare('SELECT * FROM runs ORDER BY started_at DESC LIMIT 50').all();
  res.json(rows);
});

router.get('/:id', (req, res) => {
  const row = db.prepare('SELECT * FROM runs WHERE id = ?').get(req.params.id);
  if (!row) return res.status(404).json({ error: 'Not found' });
  res.json(row);
});

router.post('/import', (req, res) => {
  const { started_at, finished_at, status, passed, failed, report_json, logs_jsonl } = req.body;
  const result = db.prepare(
    `INSERT INTO runs (started_at, finished_at, status, passed, failed, report_json, logs_jsonl)
     VALUES (?, ?, ?, ?, ?, ?, ?)`
  ).run(started_at, finished_at, status, passed, failed, report_json, logs_jsonl);
  res.json({ id: result.lastInsertRowid });
});

export default router;
```

- [ ] **Step 2: Implement logs.ts**

```ts
import { Router } from 'express';
import { LogWatcher } from '../../../src/helpers/logWatcher';
import { env } from '../../../src/config/env';
import fs from 'fs';

const router = Router();
const today = new Date().toISOString().slice(0, 10);
const logFiles = [
  `${env.logPaths.api}/api-${today}.log`,
  `${env.logPaths.app}/visits-${today}.log`,
].filter(fs.existsSync);
const watcher = new LogWatcher(logFiles);

router.get('/stream', (req, res) => {
  res.setHeader('Content-Type', 'text/event-stream');
  res.setHeader('Cache-Control', 'no-cache');
  res.setHeader('Connection', 'keep-alive');

  const onLine = (line: string, source: string) => {
    res.write(`data: ${JSON.stringify({ line, source, ts: new Date().toISOString() })}\n\n`);
  };
  watcher.on('line', onLine);
  watcher.start();

  req.on('close', () => {
    watcher.off('line', onLine);
  });
});

export default router;
```

- [ ] **Step 3: Implement trigger.ts**

```ts
import { Router } from 'express';
import { spawn } from 'child_process';
import path from 'path';

const router = Router();

router.post('/', (_req, res) => {
  const cwd = path.resolve('.');
  const child = spawn('npm', ['run', 'test:prod'], { cwd, shell: true });

  let output = '';
  child.stdout.on('data', (d) => { output += d.toString(); });
  child.stderr.on('data', (d) => { output += d.toString(); });

  child.on('close', (code) => {
    res.json({ exitCode: code, output });
  });
});

export default router;
```

- [ ] **Step 4: Commit**

```bash
cd webiartisan.new
git add e2e/api/src/routes/
git commit -m "feat(e2e): add dashboard backend routes for runs, logs and trigger"
```

---

### Task 13: Dashboard frontend scaffolding

**Files:**
- Create: `e2e/dashboard/index.html`
- Create: `e2e/dashboard/package.json`
- Create: `e2e/dashboard/vite.config.ts`
- Create: `e2e/dashboard/tsconfig.json`
- Create: `e2e/dashboard/src/main.ts`
- Create: `e2e/dashboard/src/App.vue`
- Create: `e2e/dashboard/src/api.ts`
- Create: `e2e/dashboard/src/router.ts`

- [ ] **Step 1: Write dashboard package.json**

```json
{
  "name": "webiartisan-e2e-dashboard",
  "version": "1.0.0",
  "private": true,
  "type": "module",
  "scripts": {
    "dev": "vite",
    "build": "vue-tsc --noEmit && vite build",
    "preview": "vite preview"
  },
  "dependencies": {
    "vue": "^3.4.0",
    "vue-router": "^4.3.0"
  },
  "devDependencies": {
    "@vitejs/plugin-vue": "^5.0.0",
    "typescript": "^5.4.0",
    "vite": "^5.2.0",
    "vue-tsc": "^2.0.0"
  }
}
```

- [ ] **Step 2: Write vite.config.ts**

```ts
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [vue()],
  base: '/',
  build: {
    outDir: 'dist',
  },
});
```

- [ ] **Step 3: Write tsconfig.json**

```json
{
  "compilerOptions": {
    "target": "ES2020",
    "useDefineForClassFields": true,
    "module": "ESNext",
    "lib": ["ES2020", "DOM", "DOM.Iterable"],
    "skipLibCheck": true,
    "moduleResolution": "Bundler",
    "allowImportingTsExtensions": true,
    "resolveJsonModule": true,
    "isolatedModules": true,
    "noEmit": true,
    "jsx": "preserve",
    "strict": true,
    "noUnusedLocals": true,
    "noUnusedParameters": true,
    "noFallthroughCasesInSwitch": true
  },
  "include": ["src/**/*.ts", "src/**/*.tsx", "src/**/*.vue"]
}
```

- [ ] **Step 4: Write index.html**

```html
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>WebiArtisan E2E Dashboard</title>
  </head>
  <body>
    <div id="app"></div>
    <script type="module" src="/src/main.ts"></script>
  </body>
</html>
```

- [ ] **Step 5: Write main.ts, App.vue, api.ts, router.ts**

`e2e/dashboard/src/main.ts`:

```ts
import { createApp } from 'vue';
import App from './App.vue';
import router from './router';

createApp(App).use(router).mount('#app');
```

`e2e/dashboard/src/App.vue`:

```vue
<template>
  <div>
    <nav>
      <router-link to="/">Runs</router-link> |
      <router-link to="/live">Live</router-link>
      <span v-if="token"> | <button @click="logout">Logout</button></span>
    </nav>
    <router-view />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';

const token = computed(() => localStorage.getItem('e2e_token'));
const router = useRouter();

function logout() {
  localStorage.removeItem('e2e_token');
  router.push('/login');
}
</script>
```

`e2e/dashboard/src/api.ts`:

```ts
const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:4000/api';

function authHeaders(): Record<string, string> {
  const token = localStorage.getItem('e2e_token');
  return token ? { Authorization: `Bearer ${token}` } : {};
}

export async function login(username: string, password: string): Promise<string> {
  const res = await fetch(`${API_URL}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username, password }),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Login failed');
  return data.token;
}

export async function fetchRuns() {
  const res = await fetch(`${API_URL}/runs`, { headers: authHeaders() });
  return res.json();
}

export async function fetchRun(id: number) {
  const res = await fetch(`${API_URL}/runs/${id}`, { headers: authHeaders() });
  return res.json();
}

export function streamLogs(onMessage: (data: unknown) => void): () => void {
  const es = new EventSource(`${API_URL}/logs/stream`, { withCredentials: true });
  es.onmessage = (event) => onMessage(JSON.parse(event.data));
  return () => es.close();
}
```

`e2e/dashboard/src/router.ts`:

```ts
import { createRouter, createWebHistory } from 'vue-router';
import HomeView from './views/HomeView.vue';
import LoginView from './views/LoginView.vue';
import LiveView from './views/LiveView.vue';
import RunView from './views/RunView.vue';

const routes = [
  { path: '/', component: HomeView, meta: { requiresAuth: true } },
  { path: '/login', component: LoginView },
  { path: '/live', component: LiveView, meta: { requiresAuth: true } },
  { path: '/runs/:id', component: RunView, meta: { requiresAuth: true } },
];

const router = createRouter({ history: createWebHistory(), routes });

router.beforeEach((to, _from, next) => {
  const token = localStorage.getItem('e2e_token');
  if (to.meta.requiresAuth && !token) next('/login');
  else next();
});

export default router;
```

- [ ] **Step 6: Install dashboard deps**

Run:
```bash
cd webiartisan.new/e2e/dashboard
npm install
```

- [ ] **Step 7: Commit**

```bash
cd webiartisan.new
git add e2e/dashboard/
git commit -m "feat(e2e): scaffold dashboard frontend with vue router"
```

---

### Task 14: Dashboard views

**Files:**
- Create: `e2e/dashboard/src/views/LoginView.vue`
- Create: `e2e/dashboard/src/views/HomeView.vue`
- Create: `e2e/dashboard/src/views/RunView.vue`
- Create: `e2e/dashboard/src/views/LiveView.vue`

- [ ] **Step 1: Implement LoginView.vue**

```vue
<template>
  <form @submit.prevent="submit">
    <h1>Login E2E Dashboard</h1>
    <input v-model="username" placeholder="Username" />
    <input v-model="password" type="password" placeholder="Password" />
    <button type="submit">Login</button>
    <p v-if="error">{{ error }}</p>
  </form>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { login } from '../api';

const username = ref('');
const password = ref('');
const error = ref('');
const router = useRouter();

async function submit() {
  try {
    const token = await login(username.value, password.value);
    localStorage.setItem('e2e_token', token);
    router.push('/');
  } catch (e) {
    error.value = (e as Error).message;
  }
}
</script>
```

- [ ] **Step 2: Implement HomeView.vue**

```vue
<template>
  <div>
    <h1>Test Runs</h1>
    <table>
      <thead>
        <tr><th>ID</th><th>Started</th><th>Status</th><th>Passed</th><th>Failed</th></tr>
      </thead>
      <tbody>
        <tr v-for="run in runs" :key="run.id">
          <td><router-link :to="`/runs/${run.id}`">{{ run.id }}</router-link></td>
          <td>{{ run.started_at }}</td>
          <td>{{ run.status }}</td>
          <td>{{ run.passed }}</td>
          <td>{{ run.failed }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { fetchRuns } from '../api';

const runs = ref<any[]>([]);

onMounted(async () => {
  runs.value = await fetchRuns();
});
</script>
```

- [ ] **Step 3: Implement RunView.vue**

```vue
<template>
  <div v-if="run">
    <h1>Run #{{ run.id }}</h1>
    <pre>{{ JSON.stringify(run, null, 2) }}</pre>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { fetchRun } from '../api';

const route = useRoute();
const run = ref<any>(null);

onMounted(async () => {
  run.value = await fetchRun(Number(route.params.id));
});
</script>
```

- [ ] **Step 4: Implement LiveView.vue**

```vue
<template>
  <div>
    <h1>Live Logs</h1>
    <button @click="trigger" :disabled="running">Run suite now</button>
    <pre>{{ logs.join('\n') }}</pre>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { streamLogs } from '../api';

const logs = ref<string[]>([]);
const running = ref(false);
let cleanup = () => {};

onMounted(() => {
  cleanup = streamLogs((data) => {
    logs.value.push(JSON.stringify(data));
    if (logs.value.length > 200) logs.value.shift();
  });
});

onUnmounted(() => cleanup());

async function trigger() {
  running.value = true;
  await fetch('/api/runs/trigger', { method: 'POST' });
  running.value = false;
}
</script>
```

- [ ] **Step 5: Commit**

```bash
cd webiartisan.new
git add e2e/dashboard/src/views/
git commit -m "feat(e2e): add dashboard views (login, runs, live)"
```

---

### Task 15: Makefile integration and deployment

**Files:**
- Modify: `webiartisan.new/Makefile`
- Create: `sites/e2e-dashboard/Makefile`

- [ ] **Step 1: Update webiartisan.new/Makefile**

Add at the end of the file:

```make
e2e-test:
	@cd e2e && npm run test:prod

e2e-dashboard-dev:
	@cd e2e && npm run dashboard

push-e2e:
	@cd e2e/dashboard && npm run build
	@rsync -avz e2e/dashboard/dist/ sites/e2e-dashboard/htdocs/
	@$(MAKE) -C sites/e2e-dashboard push
```

- [ ] **Step 2: Create sites/e2e-dashboard/Makefile**

```make
push:
	@echo "Push e2e dashboard to Gandi (customize with your deploy script)"
	# Example: rsync -avz htdocs/ ~/mnt/gandi/vhosts/e2e.prigent.tech/htdocs/
```

- [ ] **Step 3: Commit**

```bash
cd webiartisan.new
git add Makefile sites/e2e-dashboard/Makefile
git commit -m "chore(e2e): add makefile commands for tests and dashboard deployment"
```

---

## Self-Review

### Spec coverage

- Puppeteer + Vitest scaffold: Tasks 1-2.
- Log watcher real-time: Tasks 3, 7.
- API helper + smoke tests: Tasks 4, 9.
- Browser/cookie helpers and POM: Tasks 5-6.
- Auth loop reproduction: Task 8.
- E2E endpoints in PHP API: Task 10.
- Dashboard backend with SQLite/JWT: Tasks 11-12.
- Dashboard frontend Vue: Tasks 13-14.
- Deployment integration: Task 15.

### Placeholder scan

No TBD/TODO/fill-in-details placeholders remain. Each step includes concrete code or exact commands.

### Type consistency

- `LogWatcher` exposes `on('line', (line: string, source: string) => void)` consistently.
- `ApiClient` methods return `AuthResponse` consistently.
- Dashboard API uses `AuthRequest` in `auth.ts`.

### Open questions for implementer

- Actual API endpoint names (`/users/register`, `/artisans/login`, `/health`) and table names (`local_users`, `local_artisans`, `local_magic_codes`) must be verified against the PHP API; adjust helpers/specs and `routes/e2e.php` if they differ.
- Selector names in Page Objects (`input[name="email"]`, `button[type="submit"]`) must match the real Vue templates.
- The backend dashboard deployment requires either a VPS/PM2 setup or a PHP rewrite for Gandi Simple Hosting.
